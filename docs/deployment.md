# Production deployment

The same Docker image runs in development and production. Production differences are entirely in
`.env` (`APP_ENV=production`, `APP_DEBUG=false`, real mail and live Stripe keys) plus the `https`
profile, which puts Caddy in front for automatic HTTPS.

## 1. Server

Any Linux VPS with Docker Engine + Compose v2 (e.g. 2 vCPU / 2–4 GB RAM is plenty to start).
Open ports 80 and 443 only. Point the DNS `A`/`AAAA` record for `www.infinitenaturals.com`
(and the bare domain, if used) at the server.

## 2. First deploy

```bash
git clone https://github.com/rexbox123/infinitenaturals.git /opt/infinitenaturals
cd /opt/infinitenaturals
cp .env.docker.example .env
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.infinitenaturals.com
APP_DOMAIN=www.infinitenaturals.com
APP_PORT=8080                 # only reachable through Caddy; firewall it from the internet
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
DB_PASSWORD=<strong random>
DB_ROOT_PASSWORD=<strong random>
MAIL_MAILER=smtp              # + your provider's MAIL_* settings
STRIPE_KEY=pk_live_…
STRIPE_SECRET=sk_live_… (or rk_live_…)
STRIPE_WEBHOOK_SECRET=whsec_…
```

Generate `APP_KEY` and start:

```bash
docker compose run --rm --no-deps app php artisan key:generate --show   # paste into APP_KEY
docker compose --profile https up -d --build
docker compose exec app php artisan db:seed --class=StoreContentSeeder --force
docker compose exec app php artisan store:create-admin
docker compose exec app php artisan store:stripe-check
```

Do **not** run the plain `db:seed` (or `DemoSeeder`) in production — it would add demo logins.
`StoreContentSeeder` is safe to re-run; it never overwrites edits made in the admin.

Then work through the admin dashboard's **Before you go live** list and
[owner-checklist.md](owner-checklist.md), and register the live webhook ([payments.md](payments.md)).

### Product photos from the old site

Export `wp-content/uploads` from the WordPress host (FTP/hosting file manager or a backup plugin),
copy it to the server and run:

```bash
docker compose cp ./uploads app:/tmp/uploads
docker compose exec app php artisan store:import-images /tmp/uploads
```

Images are matched to products by their original file names (listed in
`database/seeders/data/catalog.json`). Photos can also be uploaded per product in the admin.

### Redirects from the old site

Product (`/product/<slug>/`), category (`/product-category/<slug>/`), `/about-us/`, `/contact-us/`,
`/terms-and-conditions/`, `/shop/` and `/cart/` URLs are the same as on WordPress; `/my-account`
redirects to `/account`. Submit `https://www.infinitenaturals.com/sitemap.xml` in Google Search Console.

## 3. Updating

```bash
cd /opt/infinitenaturals
git pull
docker compose --profile https up -d --build
```

Migrations run automatically when the `app` container starts. Configuration, routes and views are
cached at start, so `.env` changes need `docker compose up -d --force-recreate app queue scheduler`.

## 4. Backups

Persistent data lives in Docker volumes: `mysql-data` (database) and `storage-app` (uploaded
photos/logos). Back up both, off the server, at least daily:

```bash
docker compose exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction infinitenaturals' | gzip > backup-$(date +%F).sql.gz
docker run --rm -v infinitenaturals_storage-app:/data -v "$PWD":/backup alpine tar czf /backup/storage-$(date +%F).tgz -C /data .
```

(The volume name prefix is the Compose project name, normally the folder name.) Test a restore
before launch.

## 5. Operations

| Task | Command |
|---|---|
| Logs | `docker compose logs -f app queue scheduler` (app log file: volume `storage-logs`) |
| Health | `curl -fsS https://www.infinitenaturals.com/up` |
| Failed e-mails/jobs | `docker compose exec app php artisan queue:failed` / `queue:retry all` |
| Maintenance mode | `docker compose exec app php artisan down` / `up` |
| Reset an admin password | `docker compose exec app php artisan store:create-admin --email=you@…` |

Security notes: keep `APP_DEBUG=false`; the MySQL port is bound to `127.0.0.1` only; containers run
PHP as the unprivileged `www-data` user; secrets exist only in `.env` on the server; uploads are
restricted to JPG/PNG/WebP images; admin rich text is sanitised; all forms use CSRF protection,
and checkout, contact and order lookup are rate-limited.

### Without Docker

Any PHP 8.3+ host with nginx/Apache, MySQL 8 and Composer works: deploy the code, `composer install
--no-dev -o`, `npm ci && npm run build`, set `.env`, `php artisan migrate --force`,
`php artisan storage:link`, `php artisan optimize`, point the web root at `public/`, run
`php artisan queue:work` under a process manager (systemd/Supervisor) and add the cron entry
`* * * * * php /path/artisan schedule:run`.
