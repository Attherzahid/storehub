<?php
/**
 * Checkout Block exposure for the Store Hub Stripe gateway.
 *
 * @package StoreHubBridge
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Store_Hub_Suite_Stripe_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'store_hub_stripe';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_store_hub_stripe_settings', []);
    }

    public function is_active(): bool
    {
        if (($this->settings['enabled'] ?? 'no') !== 'yes' || !function_exists('WC') || !WC()->payment_gateways()) {
            return false;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        return isset($gateways[$this->name]) && $gateways[$this->name]->is_available();
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'store-hub-stripe-blocks',
            STORE_HUB_BRIDGE_PLUGIN_URL . 'assets/js/checkout-blocks.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            '1.1.2',
            true
        );

        return ['store-hub-stripe-blocks'];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title' => (string) ($this->settings['title'] ?? __('Credit / Debit Card', 'store-hub-bridge')),
            'description' => (string) ($this->settings['description'] ?? __('Pay securely with Stripe.', 'store-hub-bridge')),
            'supports' => ['products'],
        ];
    }
}
