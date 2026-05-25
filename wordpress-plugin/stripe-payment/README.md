# Stripe Payment for WooCommerce

A lightweight WooCommerce payment gateway that sends customers to Stripe Checkout using the official Stripe PHP SDK.

## Setup

1. Install dependencies from this plugin folder:

   ```bash
   composer install --no-dev
   ```

2. Activate **Stripe Payment for WooCommerce** in WordPress.
3. Go to **WooCommerce > Settings > Payments > Stripe Checkout**.
4. Enable the gateway and add your Stripe test or live API keys.
5. In Stripe, create a webhook endpoint for:

   ```text
   https://your-site.example/?wc-api=spwc_stripe_webhook
   ```

6. Add these events to the webhook:

   ```text
   checkout.session.completed
   payment_intent.payment_failed
   ```

7. Paste the webhook signing secret into the gateway settings.

## Notes

- Test mode is enabled by default.
- The order is marked paid after Stripe confirms the Checkout Session is paid, either through the webhook or the verified success return.
