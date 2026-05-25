<?php
/**
 * Optional Stripe Checkout gateway for Store Hub Bridge.
 *
 * @package StoreHubBridge
 */

defined('ABSPATH') || exit;

final class Store_Hub_Suite_Stripe_Gateway extends WC_Payment_Gateway
{
    private bool $testMode = true;
    private string $keySource = 'manual';
    private string $publishableKey = '';
    private string $secretKey = '';
    private string $webhookSecret = '';
    private ?\Stripe\StripeClient $stripeClient = null;

    public function __construct()
    {
        $this->id = 'store_hub_stripe';
        $this->method_title = __('Store Hub Stripe Checkout', 'store-hub-bridge');
        $this->method_description = __('Optional Stripe Checkout payments. Runs independently from Store Hub dashboard synchronization.', 'store-hub-bridge');
        $this->has_fields = false;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Credit / Debit Card', 'store-hub-bridge'));
        $this->description = $this->get_option('description', __('Pay securely with Stripe.', 'store-hub-bridge'));
        $this->enabled = $this->get_option('enabled', 'no');
        $this->testMode = $this->get_option('testmode', 'yes') === 'yes';
        $this->keySource = $this->get_option('key_source', 'manual') === 'dashboard' ? 'dashboard' : 'manual';
        $this->loadCredentials();
        $this->webhookSecret = trim((string) $this->get_option('webhook_secret'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $webhookUrl = add_query_arg('wc-api', 'store_hub_stripe_webhook', home_url('/'));

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'store-hub-bridge'),
                'type' => 'checkbox',
                'label' => __('Enable Store Hub Stripe Checkout', 'store-hub-bridge'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'store-hub-bridge'),
                'type' => 'text',
                'description' => __('Payment method title displayed at checkout.', 'store-hub-bridge'),
                'default' => __('Credit / Debit Card', 'store-hub-bridge'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'store-hub-bridge'),
                'type' => 'textarea',
                'description' => __('Payment method description displayed at checkout.', 'store-hub-bridge'),
                'default' => __('Pay securely with Stripe.', 'store-hub-bridge'),
                'desc_tip' => true,
            ],
            'key_source' => [
                'title' => __('Stripe key source', 'store-hub-bridge'),
                'type' => 'select',
                'options' => [
                    'manual' => __('Manual keys entered below', 'store-hub-bridge'),
                    'dashboard' => __('Dashboard managed assignment', 'store-hub-bridge'),
                ],
                'default' => 'manual',
                'description' => __('Manual mode uses the keys below. Dashboard managed mode securely fetches the ready key assigned to this store before checkout.', 'store-hub-bridge'),
            ],
            'testmode' => [
                'title' => __('Test mode', 'store-hub-bridge'),
                'type' => 'checkbox',
                'label' => __('Use Stripe test keys', 'store-hub-bridge'),
                'default' => 'yes',
                'description' => __('Disable only when you are ready to accept live payments.', 'store-hub-bridge'),
            ],
            'test_publishable_key' => [
                'title' => __('Test publishable key', 'store-hub-bridge'),
                'type' => 'text',
            ],
            'test_secret_key' => [
                'title' => __('Test secret key', 'store-hub-bridge'),
                'type' => 'password',
            ],
            'live_publishable_key' => [
                'title' => __('Live publishable key', 'store-hub-bridge'),
                'type' => 'text',
            ],
            'live_secret_key' => [
                'title' => __('Live secret key', 'store-hub-bridge'),
                'type' => 'password',
            ],
            'webhook_secret' => [
                'title' => __('Webhook signing secret', 'store-hub-bridge'),
                'type' => 'password',
                'description' => sprintf(
                    __('Create a Stripe webhook for %s and subscribe to checkout.session.completed and payment_intent.payment_failed.', 'store-hub-bridge'),
                    '<code>' . esc_html($webhookUrl) . '</code>'
                ),
            ],
        ];
    }

    public function is_available(): bool
    {
        return parent::is_available() && $this->secretKey !== '' && $this->getClient() !== null;
    }

    public function admin_options()
    {
        parent::admin_options();
        if ($this->keySource === 'dashboard') {
            echo '<p>' . esc_html__('Dashboard managed mode refreshes the assigned ready Stripe key immediately before each payment. If no ready key is assigned, new Stripe payments are stopped.', 'store-hub-bridge') . '</p>';
        }
    }

    public function process_payment($orderId): array
    {
        $order = wc_get_order($orderId);
        if ($this->keySource === 'dashboard') {
            $refresh = Store_Hub_Suite_Plugin::refreshPaymentKeys(true);
            if ($refresh !== true) {
                wc_get_logger()->error('Store Hub dashboard key refresh failed: ' . $refresh, ['source' => 'store-hub-stripe']);
                wc_add_notice(__('This payment method is temporarily unavailable. Please try again or choose another method.', 'store-hub-bridge'), 'error');
                return ['result' => 'failure'];
            }
            $this->loadCredentials();
        }

        $client = $this->getClient();
        if (!$order || !$client) {
            wc_add_notice(__('Stripe is not configured correctly. Please choose another payment method.', 'store-hub-bridge'), 'error');
            return ['result' => 'failure'];
        }

        try {
            $session = $client->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => (string) $order->get_id(),
                'customer_email' => $order->get_billing_email(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->get_currency()),
                        'unit_amount' => $this->stripeAmount($order->get_total(), $order->get_currency()),
                        'product_data' => [
                            'name' => sprintf(__('Order %s', 'store-hub-bridge'), $order->get_order_number()),
                        ],
                    ],
                ]],
                'metadata' => ['order_id' => (string) $order->get_id()],
                'payment_intent_data' => ['metadata' => ['order_id' => (string) $order->get_id()]],
                'success_url' => $this->returnEndpoint($order, true),
                'cancel_url' => $order->get_cancel_order_url_raw(),
            ]);

            $order->update_status('pending', __('Awaiting Stripe payment.', 'store-hub-bridge'));
            $order->update_meta_data('_store_hub_stripe_session_id', $session->id);
            $order->save();

            return [
                'result' => 'success',
                'redirect' => $session->url,
            ];
        } catch (\Throwable $exception) {
            wc_get_logger()->error('Store Hub Stripe Checkout session failed: ' . $exception->getMessage(), ['source' => 'store-hub-stripe']);
            wc_add_notice(__('Unable to start Stripe checkout. Please try again.', 'store-hub-bridge'), 'error');
            return ['result' => 'failure'];
        }
    }

    public function handleReturn(): void
    {
        $orderId = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        $orderKey = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $sessionId = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        $order = wc_get_order($orderId);

        if (!$order || !hash_equals((string) $order->get_order_key(), $orderKey) || $sessionId === '') {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $client = $this->getClient();
        if ($client && !$order->is_paid()) {
            try {
                $session = $client->checkout->sessions->retrieve($sessionId, []);
                if ($session->payment_status === 'paid') {
                    $this->completeOrder($order, (string) $session->payment_intent, (string) $session->id);
                }
            } catch (\Throwable $exception) {
                wc_get_logger()->error('Store Hub Stripe return verification failed: ' . $exception->getMessage(), ['source' => 'store-hub-stripe']);
            }
        }

        wp_safe_redirect($this->get_return_url($order));
        exit;
    }

    public function handleWebhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_STRIPE_SIGNATURE'])) : '';

        if ($this->webhookSecret === '' || !$this->getClient()) {
            status_header(400);
            exit;
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (\Throwable $exception) {
            status_header(400);
            exit;
        }

        $object = $event->data->object;
        $orderId = isset($object->metadata->order_id) ? absint($object->metadata->order_id) : 0;
        $order = $orderId ? wc_get_order($orderId) : null;

        if ($event->type === 'checkout.session.completed' && $order && !$order->is_paid() && $object->payment_status === 'paid') {
            $this->completeOrder($order, (string) $object->payment_intent, (string) $object->id);
        }

        if ($event->type === 'payment_intent.payment_failed' && $order && !$order->is_paid()) {
            $order->update_status('failed', __('Stripe payment failed.', 'store-hub-bridge'));
        }

        status_header(200);
        exit;
    }

    private function completeOrder(WC_Order $order, string $paymentIntent, string $sessionId): void
    {
        $order->payment_complete($paymentIntent);
        $order->update_meta_data('_store_hub_stripe_payment_intent', $paymentIntent);
        $order->update_meta_data('_store_hub_stripe_session_id', $sessionId);
        $order->add_order_note(sprintf(
            __('Stripe payment completed. Payment intent: %1$s. Checkout session: %2$s.', 'store-hub-bridge'),
            $paymentIntent,
            $sessionId
        ));
        $order->save();

        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    private function loadCredentials(): void
    {
        $this->stripeClient = null;
        if ($this->keySource === 'dashboard') {
            $credentials = get_option('store_hub_payment_keys', []);
            $this->publishableKey = is_array($credentials) ? trim((string) ($credentials['public_key'] ?? '')) : '';
            $this->secretKey = is_array($credentials) ? trim((string) ($credentials['secret_key'] ?? '')) : '';
            return;
        }

        $this->publishableKey = trim((string) $this->get_option($this->testMode ? 'test_publishable_key' : 'live_publishable_key'));
        $this->secretKey = trim((string) $this->get_option($this->testMode ? 'test_secret_key' : 'live_secret_key'));
    }

    private function getClient(): ?\Stripe\StripeClient
    {
        if ($this->stripeClient) {
            return $this->stripeClient;
        }

        $autoload = STORE_HUB_BRIDGE_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('\Stripe\StripeClient') || $this->secretKey === '') {
            return null;
        }

        $this->stripeClient = new \Stripe\StripeClient($this->secretKey);
        return $this->stripeClient;
    }

    private function returnEndpoint(WC_Order $order, bool $withSessionPlaceholder = false): string
    {
        $arguments = [
            'wc-api' => 'store_hub_stripe_return',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ];
        if ($withSessionPlaceholder) {
            $arguments['session_id'] = '{CHECKOUT_SESSION_ID}';
        }

        $url = add_query_arg($arguments, home_url('/'));
        return $withSessionPlaceholder
            ? str_replace(rawurlencode('{CHECKOUT_SESSION_ID}'), '{CHECKOUT_SESSION_ID}', $url)
            : $url;
    }

    private function stripeAmount(float|string $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $multiplier = in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;

        return (int) round((float) $amount * $multiplier);
    }
}
