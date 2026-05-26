# Store Hub Bridge and Stripe Payments

One WooCommerce plugin with two independent modules:

- Store Hub synchronization sends order and revenue data to the dashboard.
- Store Hub Stripe Checkout optionally accepts payments through Stripe Checkout.

The official Stripe PHP SDK is bundled in the plugin archive and remains the payment-processing client.

## Install

1. Upload and activate this plugin in WordPress.
2. Open **Settings > Store Hub** to enable dashboard synchronization, enter the dashboard sync endpoint and token, and test **Sync now**.
3. Open **WooCommerce > Settings > Payments > Store Hub Stripe Checkout** to enable Stripe payments and enter Stripe keys.
4. Create a Stripe webhook endpoint for:

```text
https://your-store.example/?wc-api=store_hub_stripe_webhook
```

Subscribe the endpoint to:

```text
checkout.session.completed
payment_intent.payment_failed
```

Then enter its signing secret in the payment gateway settings.

## Independent Operation

- Turning off dashboard synchronization does not disable Stripe checkout payments.
- Leaving Stripe Checkout disabled does not stop Store Hub synchronization.
- In **Manual keys entered below** mode, Stripe Checkout uses the keys saved in the WooCommerce gateway settings.
- In **Dashboard managed assignment** mode, the plugin obtains the currently assigned ready key from Store Hub over HTTPS immediately before starting each payment.
- When an active store does not yet have any key attached, Store Hub automatically assigns an active ready key during sync or the first key refresh. A key may be assigned to multiple stores; an existing store assignment is never replaced automatically.
- If a dashboard-managed key becomes payout waiting and no replacement key is assigned, new checkout payments are stopped instead of using that waiting key.
- The gateway supports both the classic WooCommerce checkout and the Checkout Block. Enable **Store Hub Stripe Checkout** under **WooCommerce > Settings > Payments** before it can appear to customers.
- If the separate `spwc_stripe` Stripe plugin was configured before activation, this plugin imports its settings once into the new gateway configuration.
- The new payment gateway ID and webhook endpoint are unique, so both plugins can temporarily remain installed while testing. Disable the old Stripe payment gateway once the Store Hub gateway is confirmed working to avoid showing two Stripe options at checkout.
