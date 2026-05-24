# Store Hub

A dark, responsive PHP 8 + MySQL admin dashboard for managing WooCommerce stores, Stripe keys, payouts, analytics, and secure store sync.

## Install

1. Create the database and tables:

```sql
SOURCE database/schema.sql;
SOURCE database/seed.sql;
```

2. Update `config/config.php` or set environment variables:

```text
APP_URL=http://localhost/store-hub/public
APP_KEY=replace-with-a-long-random-secret
DB_HOST=127.0.0.1
DB_NAME=store_hub
DB_USER=root
DB_PASS=
```

3. Visit:

```text
http://localhost/store-hub/public/login.php
```

Seeded admin email:

```text
ameerhamzadeveloper@gmail.com
```

Set or reset its password with `php scripts/reset-admin.php ameerhamzadeveloper@gmail.com admin123`.

For an existing live database, apply the newer key workflow columns before deploying the matching PHP files:

```sql
SOURCE database/migrations/2026_05_25_key_lifecycle.sql;
SOURCE database/migrations/2026_05_25_stripe_payout_sync.sql;
```

## WordPress Plugin

Copy `wordpress-plugin/store-hub-bridge` into `wp-content/plugins/`, activate **Store Hub Bridge**, then open **Settings > Store Hub**.

Use this dashboard endpoint:

```text
https://storehub.orpixia.com/api/store-sync.php
```

Create a matching row in `store_connections` with `SHA2('your-token', 256)` and paste the plain token into the plugin settings.

## Security Notes

- Admin writes use CSRF tokens.
- Admin write and secret-reveal endpoints require an administrator session.
- SQL uses PDO prepared statements.
- Stripe secret keys are encrypted before storage.
- API store sync uses bearer tokens stored as SHA-256 hashes.
- The Keys page displays publishable keys normally and secret keys as a mask such as `sk_live_******A92f`.
- Revealing a secret key requires the signed-in administrator password and is written to activity logs.

Payout waiting cards call Stripe from the PHP backend using the encrypted secret key. The app imports the payout schedule, expected arrival date, and paid status without exposing that secret to page markup or ordinary API responses.

## Namecheap Deployment

See `NAMECHEAP_DEPLOY.md` for the cPanel Git deployment steps.
