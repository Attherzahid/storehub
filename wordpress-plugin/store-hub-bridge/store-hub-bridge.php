<?php
/**
 * Plugin Name: Store Hub Bridge and Stripe Payments
 * Description: Secure Store Hub dashboard sync and an optional Stripe Checkout gateway for WooCommerce.
 * Version: 1.1.2
 * Author: Store Hub
 * Requires PHP: 8.0
 * Text Domain: store-hub-bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

defined('STORE_HUB_BRIDGE_PLUGIN_FILE') || define('STORE_HUB_BRIDGE_PLUGIN_FILE', __FILE__);
defined('STORE_HUB_BRIDGE_PLUGIN_DIR') || define('STORE_HUB_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
defined('STORE_HUB_BRIDGE_PLUGIN_URL') || define('STORE_HUB_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));

final class Store_Hub_Suite_Plugin
{
    private const OPTION_SYNC_ENABLED = 'store_hub_sync_enabled';
    private const OPTION_ENDPOINT = 'store_hub_endpoint';
    private const OPTION_TOKEN = 'store_hub_token';
    private const OPTION_LOGS = 'store_hub_sync_logs';
    private const OPTION_PAYMENT_KEYS = 'store_hub_payment_keys';
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
        self::importLegacyStripeSettings();
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'store_hub_fifteen_minutes', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function importLegacyStripeSettings(): void
    {
        if (get_option('woocommerce_store_hub_stripe_settings', null) !== null) {
            return;
        }

        $legacy = get_option('woocommerce_spwc_stripe_settings', []);
        if (is_array($legacy) && $legacy) {
            add_option('woocommerce_store_hub_stripe_settings', $legacy, '', false);
        }
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
        register_setting('store_hub_bridge', self::OPTION_SYNC_ENABLED, ['sanitize_callback' => [$this, 'sanitizeCheckbox']]);
        register_setting('store_hub_bridge', self::OPTION_ENDPOINT, ['sanitize_callback' => 'esc_url_raw']);
        register_setting('store_hub_bridge', self::OPTION_TOKEN, ['sanitize_callback' => 'sanitize_text_field']);
    }

    public function sanitizeCheckbox(mixed $value): string
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    public function settingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['store_hub_sync_now']) && check_admin_referer('store_hub_sync_now')) {
            $result = $this->sync(true);
            if ($result === true) {
                echo '<div class="notice notice-success"><p>Store Hub sync completed.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result) . '</p></div>';
            }
        }
        if (isset($_POST['store_hub_refresh_payment_key']) && check_admin_referer('store_hub_refresh_payment_key')) {
            $result = self::refreshPaymentKeys(true);
            if ($result === true) {
                echo '<div class="notice notice-success"><p>Dashboard payment key refreshed.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result) . '</p></div>';
            }
        }
        $paymentKeys = get_option(self::OPTION_PAYMENT_KEYS, []);
        ?>
        <div class="wrap">
            <h1>Store Hub Bridge</h1>
            <form method="post" action="options.php">
                <?php settings_fields('store_hub_bridge'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Dashboard sync</th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_SYNC_ENABLED); ?>" value="no">
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_SYNC_ENABLED); ?>" value="yes" <?php checked($this->syncEnabled(), true); ?>> Enable scheduled Store Hub synchronization</label>
                            <p class="description">This setting does not affect the Stripe payment gateway.</p>
                        </td>
                    </tr>
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
            <hr>
            <h2>Stripe payments</h2>
            <p>The optional payment gateway is configured separately in <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=store_hub_stripe')); ?>">WooCommerce &gt; Settings &gt; Payments &gt; Store Hub Stripe Checkout</a>. Dashboard sync can be disabled without disabling checkout payments.</p>
            <?php if (is_array($paymentKeys) && !empty($paymentKeys['company_name'])): ?>
                <p>Dashboard-assigned key: <strong><?php echo esc_html($paymentKeys['company_name']); ?></strong> <code><?php echo esc_html($this->maskKey((string) ($paymentKeys['public_key'] ?? ''))); ?></code> refreshed <?php echo esc_html((string) ($paymentKeys['synced_at'] ?? '')); ?>.</p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('store_hub_refresh_payment_key'); ?>
                <p><button class="button button-secondary" name="store_hub_refresh_payment_key" value="1">Refresh assigned payment key</button></p>
            </form>
        </div>
        <?php
    }

    public function sync(bool $force = false): bool|string
    {
        $endpoint = esc_url_raw((string) get_option(self::OPTION_ENDPOINT));
        $token = sanitize_text_field((string) get_option(self::OPTION_TOKEN));
        if (!$force && !$this->syncEnabled()) {
            return true;
        }
        if (!$endpoint || !$token) {
            self::addLog('failed', 'Store Hub endpoint and API token are required.', $endpoint);
            return 'Store Hub endpoint and API token are required.';
        }
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            self::addLog('failed', 'WooCommerce is not active or its order functions are unavailable.', $endpoint);
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
            self::addLog('failed', $response->get_error_message(), $endpoint);
            return 'Store Hub sync failed: ' . $response->get_error_message();
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            self::addLog('failed', 'HTTP ' . $statusCode . ' - ' . $this->shortBody($body), $endpoint, $statusCode);
            return 'Store Hub sync failed with HTTP status ' . $statusCode . '.';
        }

        self::addLog('success', 'Synced ' . count($payloadOrders) . ' recent orders.', $endpoint, $statusCode);
        if (self::usesDashboardPaymentKeys()) {
            self::refreshPaymentKeys(true);
        }
        return true;
    }

    public static function refreshPaymentKeys(bool $force = false): bool|string
    {
        $endpoint = esc_url_raw((string) get_option(self::OPTION_ENDPOINT));
        $token = sanitize_text_field((string) get_option(self::OPTION_TOKEN));
        if (!$endpoint || !$token) {
            return 'Store Hub endpoint and API token are required before dashboard payment keys can be used.';
        }

        $stored = get_option(self::OPTION_PAYMENT_KEYS, []);
        if (!$force && is_array($stored) && !empty($stored['synced_timestamp']) && (int) $stored['synced_timestamp'] >= time() - 300) {
            return true;
        }

        $keyEndpoint = preg_replace('~/store-sync\.php(?:\?.*)?$~', '/store-key.php', $endpoint);
        if (!is_string($keyEndpoint) || $keyEndpoint === $endpoint) {
            return 'The dashboard sync endpoint must end with /api/store-sync.php.';
        }
        if (wp_parse_url($keyEndpoint, PHP_URL_SCHEME) !== 'https') {
            return 'Dashboard managed payment keys require an HTTPS dashboard endpoint.';
        }

        $response = wp_remote_get($keyEndpoint, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($response)) {
            self::addLog('failed', 'Payment key refresh failed: ' . $response->get_error_message(), $keyEndpoint);
            return 'Payment key refresh failed: ' . $response->get_error_message();
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($payload)) {
            $message = is_array($payload) ? (string) ($payload['error'] ?? 'Dashboard rejected the payment key request.') : 'Dashboard returned an invalid payment key response.';
            self::addLog('failed', 'Payment key refresh failed: ' . $message, $keyEndpoint, $statusCode);
            return $message;
        }

        $publicKey = trim((string) ($payload['public_key'] ?? ''));
        $secretKey = trim((string) ($payload['secret_key'] ?? ''));
        if (!str_starts_with($publicKey, 'pk_') || (!str_starts_with($secretKey, 'sk_') && !str_starts_with($secretKey, 'rk_'))) {
            return 'Dashboard returned invalid Stripe credentials.';
        }

        update_option(self::OPTION_PAYMENT_KEYS, [
            'key_id' => (int) ($payload['key_id'] ?? 0),
            'company_name' => sanitize_text_field((string) ($payload['company_name'] ?? '')),
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
            'synced_at' => current_time('mysql'),
            'synced_timestamp' => time(),
        ], false);
        self::addLog('success', 'Payment key refreshed from dashboard assignment.', $keyEndpoint, $statusCode);
        return true;
    }

    public static function usesDashboardPaymentKeys(): bool
    {
        $settings = get_option('woocommerce_store_hub_stripe_settings', []);
        return is_array($settings) && ($settings['key_source'] ?? 'manual') === 'dashboard';
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

    private static function addLog(string $status, string $message, string $endpoint = '', ?int $httpStatus = null): void
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

    private function maskKey(string $key): string
    {
        return $key === '' ? 'Unavailable' : substr($key, 0, min(8, strlen($key))) . '******' . substr($key, -4);
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

    private function syncEnabled(): bool
    {
        $setting = get_option(self::OPTION_SYNC_ENABLED, null);
        if ($setting !== null) {
            return $setting === 'yes';
        }

        return (bool) get_option(self::OPTION_ENDPOINT) && (bool) get_option(self::OPTION_TOKEN);
    }
}

register_activation_hook(__FILE__, ['Store_Hub_Suite_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Store_Hub_Suite_Plugin', 'deactivate']);
new Store_Hub_Suite_Plugin();

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once STORE_HUB_BRIDGE_PLUGIN_DIR . 'includes/class-store-hub-stripe-gateway.php';

    add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
        $gateways[] = 'Store_Hub_Suite_Stripe_Gateway';
        return $gateways;
    });

    add_action('woocommerce_api_store_hub_stripe_webhook', static function (): void {
        (new Store_Hub_Suite_Stripe_Gateway())->handleWebhook();
    });
    add_action('woocommerce_api_store_hub_stripe_return', static function (): void {
        (new Store_Hub_Suite_Stripe_Gateway())->handleReturn();
    });
}, 11);

add_action('woocommerce_blocks_loaded', static function (): void {
    if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once STORE_HUB_BRIDGE_PLUGIN_DIR . 'includes/class-store-hub-stripe-blocks.php';
    add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry): void {
        $registry->register(new Store_Hub_Suite_Stripe_Blocks());
    });
});
