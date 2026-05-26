# Store Hub Bridge

This is a companion plugin for Store Hub and the existing **Nana'Gs Stripe** WooCommerce gateway.

- Store Hub Bridge sends order and revenue data to the dashboard.
- Store Hub Bridge retrieves the ready key assigned to this store and copies it into the existing Stripe plugin's manual key fields.
- Nana'Gs Stripe remains unchanged and continues handling Stripe Checkout, webhooks, and payment completion.

## Install

1. Keep your working **Nana'Gs Stripe** plugin installed and active.
2. Upload and activate **Store Hub Bridge** in WordPress.
3. Open **Settings > Store Hub**, enter the dashboard sync endpoint and token, and enable automatic copying of assigned keys.
4. Click **Sync now** or **Get assigned key and apply to Stripe Payment**.
5. Open **WooCommerce > Settings > Payments > Stripe Checkout** to confirm the assigned manual keys are populated and enable your existing gateway.
6. Continue using the existing Stripe webhook endpoint from your Stripe Payment plugin:

```text
https://your-store.example/?wc-api=spwc_stripe_webhook
```

Subscribe the endpoint to:

```text
checkout.session.completed
payment_intent.payment_failed
```

Store Hub does not change or replace the webhook signing secret.

## Key Updates

- Your original Stripe payment source and checkout flow are not modified by this bridge.
- The bridge writes credentials into `woocommerce_spwc_stripe_settings`, the same manual settings already used by Nana'Gs Stripe.
- Test keys populate the test fields and enable test mode; live keys populate the live fields and disable test mode.
- When an active store does not yet have any key attached, Store Hub automatically assigns an active ready key during sync or the first key refresh. A key may be assigned to multiple stores; an existing store assignment is never replaced automatically.
- Refreshing the assigned key updates only the normal Stripe key/mode fields; your gateway enable setting, display text, and webhook signing secret remain intact.
