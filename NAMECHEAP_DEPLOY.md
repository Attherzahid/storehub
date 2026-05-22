# Namecheap Deployment

This project is prepared for cPanel Git deployment on:

```text
https://storehub.orpixia.com
```

## cPanel Paths

The current `.cpanel.yml` deploys to:

```text
/home/orpiemma/storehub.orpixia.com/
```

The project root is used as the subdomain folder. Root `index.php`, `login.php`, `logout.php`, and `.htaccess` forward traffic into `public/`.

## First Deploy

1. Push the repository to GitHub.
2. In cPanel, use **Git Version Control** and deploy the checked-out branch.
3. If `config/config.php` does not exist on the server, cPanel will copy:

```text
config/production.example.php
```

to:

```text
config/config.php
```

4. Edit the live server `config/config.php` and set the real database password and a strong encryption key.

## Future Deploys

Run locally:

```bash
git status
git add .
git commit -m "Update Store Hub"
git push
```

Then click **Deploy HEAD Commit** in cPanel.

The deployment excludes these files so live secrets are not overwritten:

```text
config/config.php
config/config copy.php
```

## Live URLs

Dashboard:

```text
https://storehub.orpixia.com/
```

Login:

```text
https://storehub.orpixia.com/login
```

WooCommerce plugin sync endpoint:

```text
https://storehub.orpixia.com/api/store-sync.php
```

## Database

Import:

```sql
database/schema.sql
database/seed.sql
```

Then reset the admin login from cPanel Terminal:

```bash
php scripts/reset-admin.php ameerhamzadeveloper@gmail.com admin123
```

## Security

The root `.htaccess` blocks direct browser access to:

```text
app/
config/
database/
scripts/
wordpress-plugin/
```

Keep `config/config.php` edited only on the server, not committed with real passwords.
