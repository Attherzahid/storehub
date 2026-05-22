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

Demo login:

```text
admin@storehub.local
password
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
- SQL uses PDO prepared statements.
- Stripe secret keys are encrypted before storage.
- API store sync uses bearer tokens stored as SHA-256 hashes.
- Secret keys are never rendered in frontend responses.

For production Stripe verification, install `stripe/stripe-php` with Composer and replace `api/stripe-verify.php` with a server-side Account API call.
