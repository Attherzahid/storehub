<?php
/**
 * Plugin Name: Store Hub Bridge
 * Description: Secure WooCommerce data bridge for the Store Hub admin dashboard.
 * Version: 1.0.0
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
            $this->sync();
            echo '<div class="notice notice-success"><p>Store Hub sync attempted. Check dashboard activity logs.</p></div>';
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
        </div>
        <?php
    }

    public function sync(): void
    {
        $endpoint = esc_url_raw((string) get_option(self::OPTION_ENDPOINT));
        $token = sanitize_text_field((string) get_option(self::OPTION_TOKEN));
        if (!$endpoint || !$token || !class_exists('WooCommerce')) {
            return;
        }

        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        $payloadOrders = [];
        $monthlySales = 0.0;
        foreach ($orders as $order) {
            $total = (float) $order->get_total();
            if ($order->get_date_created() && $order->get_date_created()->date('Y-m') === gmdate('Y-m')) {
                $monthlySales += $total;
            }
            $payloadOrders[] = [
                'id' => 'wc_' . $order->get_id(),
                'customer_email' => $order->get_billing_email(),
                'total' => $total,
                'currency' => $order->get_currency(),
                'status' => $order->is_paid() ? 'succeeded' : 'pending',
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : current_time('mysql'),
            ];
        }

        $payload = [
            'summary' => [
                'total_sales' => (float) wc_get_total_sales(),
                'monthly_sales' => $monthlySales,
                'currency' => get_woocommerce_currency(),
                'order_count' => wc_orders_count('completed') + wc_orders_count('processing'),
                'average_order_value' => count($orders) ? array_sum(array_column($payloadOrders, 'total')) / count($orders) : 0,
                'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : '',
                'wordpress_version' => get_bloginfo('version'),
            ],
            'orders' => $payloadOrders,
        ];

        wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode($payload),
        ]);
    }
}

register_activation_hook(__FILE__, ['Store_Hub_Bridge', 'activate']);
register_deactivation_hook(__FILE__, ['Store_Hub_Bridge', 'deactivate']);
new Store_Hub_Bridge();
