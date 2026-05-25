<?php
/**
 * Plugin Name: Nana'Gs Stripe
 * Description: Moli Party, Danger Ahead, Me to jaongi, Zamana thoke ga.
 * Version: 1.0.0
 * Author: Ather Mariana
 * Text Domain: stripe-payment
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'SPWC_PLUGIN_FILE', __FILE__ );
define( 'SPWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', 'spwc_init_gateway', 11 );

/**
 * Boot the gateway after WooCommerce is available.
 */
function spwc_init_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'spwc_missing_woocommerce_notice' );
		return;
	}

	require_once SPWC_PLUGIN_DIR . 'includes/class-spwc-stripe-gateway.php';

	add_filter( 'woocommerce_payment_gateways', 'spwc_register_gateway' );
	add_action( 'woocommerce_api_spwc_stripe_webhook', 'spwc_handle_stripe_webhook' );
	add_action( 'woocommerce_api_spwc_stripe_return', 'spwc_handle_stripe_return' );
}

/**
 * Register the gateway with WooCommerce.
 *
 * @param array $gateways Payment gateway classes.
 * @return array
 */
function spwc_register_gateway( $gateways ) {
	$gateways[] = 'SPWC_Stripe_Gateway';
	return $gateways;
}

/**
 * Handle Stripe webhook requests even when WooCommerce has not instantiated gateways yet.
 */
function spwc_handle_stripe_webhook() {
	$gateway = new SPWC_Stripe_Gateway();
	$gateway->handle_webhook();
}

/**
 * Handle customer returns from Stripe Checkout.
 */
function spwc_handle_stripe_return() {
	$gateway = new SPWC_Stripe_Gateway();
	$gateway->handle_return();
}

/**
 * Admin notice shown when WooCommerce is missing.
 */
function spwc_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Stripe Payment for WooCommerce requires WooCommerce to be installed and active.', 'stripe-payment' );
	echo '</p></div>';
}
