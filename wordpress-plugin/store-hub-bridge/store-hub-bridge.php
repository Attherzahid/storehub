<?php
/**
 * Plugin Name: Store Hub Bridge
 * Description: Secure WooCommerce data bridge for the Store Hub admin dashboard.
 * Version: 1.0.2
 * Author: Store Hub
 * Requires PHP: 8.0
 * Text Domain: store-hub-bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Store_Hub_Bridge
{
    private const OPTION_ENDPOINT = 'store_hub_endpoint';
    private const OPTION_TOKEN = 'store_hub_token';
    private const OPTION_LOGS = 'store_hub_sync_logs';
    private const CRON_HOOK = 'store_hub_sync_event';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action(self::CRON_HOOK, [$this, 'sync']);
        add_filter('cron_schedules', [$this, 'schedules']);
    }

    public static function activate(): void
    {
        if (!get_option(self::OPTION_TOKEN)) {
            update_option(self::OPTION_TOKEN, wp_generate_password(48, false, false));
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'store_hub_fifteen_minutes', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function schedules(array $schedules): array
    {
        $schedules['store_hub_fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes', 'store-hub-bridge'),
        ];
        return $schedules;
    }

    public function menu(): void
    {
        add_options_page('Store Hub Bridge', 'Store Hub', 'manage_options', 'store-hub-bridge', [$this, 'settingsPage']);
    }

    public function settings(): void
    {
        register_setting('store_hub_bridge', self::OPTION_ENDPOINT, ['sanitize_callback' => 'esc_url_raw']);
        register_setting('store_hub_bridge', self::OPTION_TOKEN, ['sanitize_callback' => 'sanitize_text_field']);
    }

    public function settingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['store_hub_sync_now']) && check_admin_referer('store_hub_sync_now')) {
            $result = $this->sync();
            if ($result === true) {
                echo '<div class="notice notice-success"><p>Store Hub sync completed.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Store Hub Bridge</h1>
            <form method="post" action="options.php">
                <?php settings_fields('store_hub_bridge'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>">Dashboard sync endpoint</label></th>
                        <td><input class="regular-text" id="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>" name="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>" value="<?php echo esc_attr(get_option(self::OPTION_ENDPOINT)); ?>" placeholder="https://example.com/store-hub/api/store-sync.php"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_TOKEN); ?>">API token</label></th>
                        <td><input class="regular-text" id="<?php echo esc_attr(self::OPTION_TOKEN); ?>" name="<?php echo esc_attr(self::OPTION_TOKEN); ?>" value="<?php echo esc_attr(get_option(self::OPTION_TOKEN)); ?>"></td>
                    </tr>
                </table>
                <?php submit_button('Save connection'); ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('store_hub_sync_now'); ?>
                <p><button class="button button-secondary" name="store_hub_sync_now" value="1">Sync now</button></p>
            </form>
            <h2>Sync log</h2>
            <?php $this->renderLogs(); ?>
        </div>
        <?php
    }

    public function sync(): bool|string
    {
        $endpoint = esc_url_raw((string) get_option(self::OPTION_ENDPOINT));
        $token = sanitize_text_field((string) get_option(self::OPTION_TOKEN));
        if (!$endpoint || !$token) {
            $this->addLog('failed', 'Store Hub endpoint and API token are required.', $endpoint);
            return 'Store Hub endpoint and API token are required.';
        }
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            $this->addLog('failed', 'WooCommerce is not active or its order functions are unavailable.', $endpoint);
            return 'WooCommerce is not active or its order functions are unavailable.';
        }

        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        $payloadOrders = [];
        $recentSales = 0.0;
        foreach ($orders as $order) {
            $total = (float) $order->get_total();
            $recentSales += $order->is_paid() ? $total : 0;
            $payloadOrders[] = [
                'id' => 'wc_' . $order->get_id(),
                'customer_email' => $order->get_billing_email(),
                'total' => $total,
                'currency' => $order->get_currency(),
                'status' => $order->is_paid() ? 'succeeded' : 'pending',
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : current_time('mysql'),
            ];
        }

        $orderCount = $this->countPaidOrders();
        $payload = [
            'summary' => [
                'total_sales' => $this->totalSales($recentSales),
                'monthly_sales' => $this->monthlySales(),
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : get_option('woocommerce_currency', 'USD'),
                'order_count' => $orderCount,
                'average_order_value' => count($orders) ? array_sum(array_column($payloadOrders, 'total')) / count($orders) : 0,
                'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : '',
                'wordpress_version' => get_bloginfo('version'),
            ],
            'orders' => $payloadOrders,
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->addLog('failed', $response->get_error_message(), $endpoint);
            return 'Store Hub sync failed: ' . $response->get_error_message();
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->addLog('failed', 'HTTP ' . $statusCode . ' - ' . $this->shortBody($body), $endpoint, $statusCode);
            return 'Store Hub sync failed with HTTP status ' . $statusCode . '.';
        }

        $this->addLog('success', 'Synced ' . count($payloadOrders) . ' recent orders.', $endpoint, $statusCode);
        return true;
    }

    private function renderLogs(): void
    {
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs) || !$logs) {
            echo '<p>No sync attempts logged yet.</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Status</th>
                    <th>HTTP</th>
                    <th>Endpoint</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['time'] ?? ''); ?></td>
                        <td><?php echo esc_html($log['status'] ?? ''); ?></td>
                        <td><?php echo esc_html((string) ($log['http_status'] ?? '')); ?></td>
                        <td><code><?php echo esc_html($log['endpoint'] ?? ''); ?></code></td>
                        <td><?php echo esc_html($log['message'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function addLog(string $status, string $message, string $endpoint = '', ?int $httpStatus = null): void
    {
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'time' => current_time('mysql'),
            'status' => $status,
            'http_status' => $httpStatus,
            'endpoint' => $endpoint,
            'message' => $message,
        ]);

        update_option(self::OPTION_LOGS, array_slice($logs, 0, 20), false);
    }

    private function shortBody(string $body): string
    {
        $body = trim(wp_strip_all_tags($body));
        return strlen($body) > 180 ? substr($body, 0, 180) . '...' : $body;
    }

    private function totalSales(float $fallback): float
    {
        if (function_exists('wc_get_total_sales')) {
            return (float) wc_get_total_sales();
        }

        $orders = wc_get_orders([
            'limit' => 250,
            'status' => $this->paidStatuses(),
            'return' => 'objects',
        ]);

        if (!$orders) {
            return $fallback;
        }

        $total = 0.0;
        foreach ($orders as $order) {
            $total += (float) $order->get_total();
        }

        return $total;
    }

    private function monthlySales(): float
    {
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => $this->paidStatuses(),
            'date_created' => gmdate('Y-m-01') . '...' . gmdate('Y-m-t 23:59:59'),
            'return' => 'objects',
        ]);

        $total = 0.0;
        foreach ($orders as $order) {
            $total += (float) $order->get_total();
        }

        return $total;
    }

    private function countPaidOrders(): int
    {
        if (function_exists('wc_orders_count')) {
            return (int) wc_orders_count('completed') + (int) wc_orders_count('processing');
        }

        return count(wc_get_orders([
            'limit' => 250,
            'status' => $this->paidStatuses(),
            'return' => 'ids',
        ]));
    }

    private function paidStatuses(): array
    {
        if (function_exists('wc_get_is_paid_statuses')) {
            return array_map(static fn (string $status): string => 'wc-' . $status, wc_get_is_paid_statuses());
        }

        return ['wc-processing', 'wc-completed'];
    }
}

register_activation_hook(__FILE__, ['Store_Hub_Bridge', 'activate']);
register_deactivation_hook(__FILE__, ['Store_Hub_Bridge', 'deactivate']);
new Store_Hub_Bridge();
