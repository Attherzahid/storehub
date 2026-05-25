<?php
/**
 * Stripe Checkout gateway.
 *
 * @package StripePaymentWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce gateway backed by Stripe Checkout.
 */
class SPWC_Stripe_Gateway extends WC_Payment_Gateway {
	/**
	 * Whether test mode is active.
	 *
	 * @var bool
	 */
	private $testmode = true;

	/**
	 * Active Stripe publishable key.
	 *
	 * @var string
	 */
	private $publishable = '';

	/**
	 * Active Stripe secret key.
	 *
	 * @var string
	 */
	private $secret_key = '';

	/**
	 * Stripe webhook signing secret.
	 *
	 * @var string
	 */
	private $webhook_key = '';

	/**
	 * Stripe SDK client.
	 *
	 * @var \Stripe\StripeClient|null
	 */
	private $stripe_client = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'spwc_stripe';
		$this->method_title       = __( 'Stripe Checkout', 'stripe-payment' );
		$this->method_description = __( 'Accept card and wallet payments through Stripe Checkout.', 'stripe-payment' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title', __( 'Credit / Debit Card', 'stripe-payment' ) );
		$this->description  = $this->get_option( 'description', __( 'Pay securely with Stripe.', 'stripe-payment' ) );
		$this->enabled      = $this->get_option( 'enabled', 'no' );
		$this->testmode     = 'yes' === $this->get_option( 'testmode', 'yes' );
		$this->publishable  = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'live_publishable_key' );
		$this->secret_key   = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'live_secret_key' );
		$this->webhook_key  = $this->get_option( 'webhook_secret' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'sdk_missing_notice' ) );
	}

	/**
	 * Define gateway settings.
	 */
	public function init_form_fields() {
		$webhook_url = add_query_arg( 'wc-api', 'spwc_stripe_webhook', home_url( '/' ) );

		$this->form_fields = array(
			'enabled'              => array(
				'title'   => __( 'Enable/Disable', 'stripe-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Stripe Checkout', 'stripe-payment' ),
				'default' => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'stripe-payment' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'stripe-payment' ),
				'default'     => __( 'Credit / Debit Card', 'stripe-payment' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'stripe-payment' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'stripe-payment' ),
				'default'     => __( 'Pay securely with Stripe.', 'stripe-payment' ),
				'desc_tip'    => true,
			),
			'testmode'             => array(
				'title'       => __( 'Test mode', 'stripe-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Use Stripe test keys', 'stripe-payment' ),
				'default'     => 'yes',
				'description' => __( 'Disable when you are ready to accept live payments.', 'stripe-payment' ),
			),
			'test_publishable_key' => array(
				'title' => __( 'Test publishable key', 'stripe-payment' ),
				'type'  => 'text',
			),
			'test_secret_key'      => array(
				'title' => __( 'Test secret key', 'stripe-payment' ),
				'type'  => 'password',
			),
			'live_publishable_key' => array(
				'title' => __( 'Live publishable key', 'stripe-payment' ),
				'type'  => 'text',
			),
			'live_secret_key'      => array(
				'title' => __( 'Live secret key', 'stripe-payment' ),
				'type'  => 'password',
			),
			'webhook_secret'       => array(
				'title'       => __( 'Webhook signing secret', 'stripe-payment' ),
				'type'        => 'password',
				'description' => sprintf(
					/* translators: %s: webhook endpoint URL. */
					__( 'Create a Stripe webhook for %s and listen for checkout.session.completed and payment_intent.payment_failed.', 'stripe-payment' ),
					'<code>' . esc_html( $webhook_url ) . '</code>'
				),
			),
		);
	}

	/**
	 * Determine whether this gateway can be used.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && $this->secret_key && $this->get_client();
	}

	/**
	 * Create a Stripe Checkout session and redirect the customer.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = $this->get_client();

		if ( ! $order || ! $client ) {
			wc_add_notice( __( 'Stripe is not configured correctly. Please choose another payment method.', 'stripe-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}

		try {
			$session = $client->checkout->sessions->create(
				array(
					'mode'                 => 'payment',
					'payment_method_types' => array( 'card' ),
					'client_reference_id'  => (string) $order->get_id(),
					'customer_email'       => $order->get_billing_email(),
					'line_items'           => array(
						array(
							'quantity'   => 1,
							'price_data' => array(
								'currency'     => strtolower( $order->get_currency() ),
								'unit_amount'  => $this->get_stripe_amount( $order->get_total(), $order->get_currency() ),
								'product_data' => array(
									'name' => sprintf(
										/* translators: %s: order number. */
										__( 'Order %s', 'stripe-payment' ),
										$order->get_order_number()
									),
								),
							),
						),
					),
					'metadata'             => array(
						'order_id' => (string) $order->get_id(),
					),
					'payment_intent_data'  => array(
						'metadata' => array(
							'order_id' => (string) $order->get_id(),
						),
					),
					'success_url'          => $this->get_return_endpoint( $order, true ),
					'cancel_url'           => $order->get_cancel_order_url_raw(),
				)
			);

			$order->update_status( 'pending', __( 'Awaiting Stripe payment.', 'stripe-payment' ) );
			$order->update_meta_data( '_spwc_stripe_session_id', $session->id );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $session->url,
			);
		} catch ( Exception $e ) {
			wc_get_logger()->error(
				'Stripe Checkout session failed: ' . $e->getMessage(),
				array( 'source' => 'stripe-payment' )
			);
			wc_add_notice( __( 'Unable to start Stripe checkout. Please try again.', 'stripe-payment' ), 'error' );
			return array( 'result' => 'failure' );
		}
	}

	/**
	 * Handle Stripe's success redirect.
	 */
	public function handle_return() {
		$order_id   = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$order_key  = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$order      = wc_get_order( $order_id );

		if ( ! $order || $order->get_order_key() !== $order_key || ! $session_id ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$client = $this->get_client();

		if ( $client && ! $order->is_paid() ) {
			try {
				$session = $client->checkout->sessions->retrieve( $session_id, array() );

				if ( 'paid' === $session->payment_status ) {
					$this->complete_order( $order, $session->payment_intent, $session->id );
				}
			} catch ( Exception $e ) {
				wc_get_logger()->error(
					'Stripe return verification failed: ' . $e->getMessage(),
					array( 'source' => 'stripe-payment' )
				);
			}
		}

		wp_safe_redirect( $this->get_return_url( $order ) );
		exit;
	}

	/**
	 * Handle Stripe webhooks.
	 */
	public function handle_webhook() {
		$payload    = file_get_contents( 'php://input' );
		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';

		if ( ! $this->webhook_key || ! $this->get_client() ) {
			status_header( 400 );
			exit;
		}

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $this->webhook_key );
		} catch ( Exception $e ) {
			status_header( 400 );
			exit;
		}

		if ( 'checkout.session.completed' === $event->type ) {
			$session  = $event->data->object;
			$order_id = isset( $session->metadata->order_id ) ? absint( $session->metadata->order_id ) : 0;
			$order    = wc_get_order( $order_id );

			if ( $order && ! $order->is_paid() && 'paid' === $session->payment_status ) {
				$this->complete_order( $order, $session->payment_intent, $session->id );
			}
		}

		if ( 'payment_intent.payment_failed' === $event->type ) {
			$intent   = $event->data->object;
			$order_id = isset( $intent->metadata->order_id ) ? absint( $intent->metadata->order_id ) : 0;
			$order    = wc_get_order( $order_id );

			if ( $order && ! $order->is_paid() ) {
				$order->update_status( 'failed', __( 'Stripe payment failed.', 'stripe-payment' ) );
			}
		}

		status_header( 200 );
		exit;
	}

	/**
	 * Mark an order paid from a verified Stripe event/session.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $payment_intent Stripe payment intent ID.
	 * @param string   $session_id Stripe checkout session ID.
	 */
	private function complete_order( WC_Order $order, $payment_intent, $session_id ) {
		$order->payment_complete( $payment_intent );
		$order->update_meta_data( '_spwc_stripe_payment_intent', $payment_intent );
		$order->update_meta_data( '_spwc_stripe_session_id', $session_id );
		$order->add_order_note(
			sprintf(
				/* translators: 1: Stripe payment intent ID, 2: Stripe checkout session ID. */
				__( 'Stripe payment completed. Payment intent: %1$s. Checkout session: %2$s.', 'stripe-payment' ),
				$payment_intent,
				$session_id
			)
		);
		$order->save();
		WC()->cart && WC()->cart->empty_cart();
	}

	/**
	 * Get configured Stripe client.
	 *
	 * @return \Stripe\StripeClient|null
	 */
	private function get_client() {
		if ( $this->stripe_client ) {
			return $this->stripe_client;
		}

		$autoload = SPWC_PLUGIN_DIR . 'vendor/autoload.php';

		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		if ( ! class_exists( '\Stripe\StripeClient' ) || ! $this->secret_key ) {
			return null;
		}

		$this->stripe_client = new \Stripe\StripeClient( $this->secret_key );
		return $this->stripe_client;
	}

	/**
	 * Show a notice when the SDK dependency is missing.
	 */
	public function sdk_missing_notice() {
		if ( 'yes' !== $this->enabled || class_exists( '\Stripe\StripeClient' ) || file_exists( SPWC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Stripe Payment for WooCommerce is enabled, but the Stripe PHP SDK is missing. Run composer install in the plugin folder.', 'stripe-payment' );
		echo '</p></div>';
	}

	/**
	 * Build the return endpoint URL for Stripe Checkout.
	 *
	 * @param WC_Order $order Order.
	 * @param bool     $include_placeholder Include Stripe session placeholder.
	 * @return string
	 */
	private function get_return_endpoint( WC_Order $order, $include_placeholder = false ) {
		$args = array(
			'wc-api'   => 'spwc_stripe_return',
			'order_id' => $order->get_id(),
			'key'      => $order->get_order_key(),
		);

		if ( $include_placeholder ) {
			$args['session_id'] = '{CHECKOUT_SESSION_ID}';
		}

		$url = add_query_arg( $args, home_url( '/' ) );

		if ( $include_placeholder ) {
			$url = str_replace( rawurlencode( '{CHECKOUT_SESSION_ID}' ), '{CHECKOUT_SESSION_ID}', $url );
		}

		return $url;
	}

	/**
	 * Convert WooCommerce amount to Stripe's smallest currency unit.
	 *
	 * @param string|float $amount Amount.
	 * @param string       $currency Currency code.
	 * @return int
	 */
	private function get_stripe_amount( $amount, $currency ) {
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		$currency     = strtoupper( $currency );
		$multiplier   = in_array( $currency, $zero_decimal, true ) ? 1 : 100;

		return (int) round( (float) $amount * $multiplier );
	}
}
