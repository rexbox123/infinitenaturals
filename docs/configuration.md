# Configuration

All environment-specific values and secrets live in the `.env` file (or real environment variables
on the server). `.env` is git-ignored — **never commit it**. Start from `.env.docker.example`
(Docker) or `.env.example` (no Docker).

Business details the owner changes day to day (address, phone, homepage text, payment toggles,
shipping rates, tax rates, low-stock level) are **not** environment variables; they are edited in
**Admin → Settings / Shipping / Sales tax / Homepage** and stored in the database.

## Application

| Variable | Example | Notes |
|---|---|---|
| `APP_NAME` | `"Infinite Naturals"` | Used in e-mail "from" name. Store name shown on the site is in Admin → Settings. |
| `APP_ENV` | `production` | `local` in development. Demo data is only seeded when `local`. |
| `APP_KEY` | `base64:…` | **Secret.** `php artisan key:generate --show`. Changing it logs everyone out. |
| `APP_DEBUG` | `false` | Must be `false` in production. |
| `APP_URL` | `https://www.infinitenaturals.com` | Used in e-mails, Stripe return URLs, sitemap. `https://` forces secure URLs. |
| `APP_PORT` | `8080` | Host port the app container listens on (Docker). |
| `APP_DOMAIN` | `www.infinitenaturals.com` | Domain Caddy obtains an HTTPS certificate for (`https` profile). |
| `APP_TIMEZONE` | `America/New_York` | Times shown in admin/e-mails. |
| `LOG_LEVEL` | `warning` | `debug` in development. |
| `TRUSTED_PROXIES` | `*` | Proxies whose `X-Forwarded-*` headers are trusted. `*` is fine behind Caddy in the same Compose network; list IPs if exposed differently. |

## Database

| Variable | Example | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | `sqlite` also works for local development. PostgreSQL (`pgsql`) is supported by the code but the Docker setup ships MySQL. |
| `DB_HOST` | `mysql` | Compose service name inside Docker. |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `infinitenaturals` | |
| `DB_USERNAME` | `infinitenaturals` | |
| `DB_PASSWORD` | — | **Secret.** |
| `DB_ROOT_PASSWORD` | — | **Secret.** Only used by the MySQL container. |
| `DB_FORWARD_PORT` | `33060` | MySQL is published on `127.0.0.1` only, for database tools. |

## Sessions, cache, queue, files

| Variable | Value | Notes |
|---|---|---|
| `SESSION_DRIVER` | `database` | |
| `SESSION_ENCRYPT` | `true` | |
| `SESSION_SECURE_COOKIE` | `true` in production | Cookies only over HTTPS. |
| `CACHE_STORE` | `database` | Redis can be added later if traffic requires it. |
| `QUEUE_CONNECTION` | `database` | E-mails are queued; the `queue` container processes them. |
| `FILESYSTEM_DISK` | `local` | Product/banner uploads always use the `public` disk (`storage/app/public`, a Docker volume). |

## E-mail

| Variable | Notes |
|---|---|
| `MAIL_MAILER` | `smtp` (or `postmark`, `ses`, `resend` with the matching key below) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | SMTP credentials — **secret**. In development these point at Mailpit (`mailpit:1025`). |
| `MAIL_FROM_ADDRESS` | e.g. `info@infinitenaturals.com` — the domain should have SPF/DKIM set up with your provider. |
| `MAIL_FROM_NAME` | |
| `POSTMARK_API_KEY` / `RESEND_API_KEY` / `AWS_*` | Only if you use those providers. |

Where e-mails go: order confirmations and shipping notices → the customer; new-order alerts →
"Send new-order alerts to" in Admin → Settings; contact-form messages → "Public email" in Admin → Settings.

## Stripe

| Variable | Notes |
|---|---|
| `STRIPE_KEY` | Publishable key `pk_test_…` / `pk_live_…`. |
| `STRIPE_SECRET` | **Secret** key `sk_test_…` / `sk_live_…` (or a restricted `rk_` key — see payments.md for the permissions it needs). |
| `STRIPE_WEBHOOK_SECRET` | **Secret** `whsec_…` of your webhook endpoint (or from `stripe listen`). |
| `STRIPE_WEBHOOK_TOLERANCE` | Optional, seconds of clock skew allowed (default 300). |

## Store

| Variable | Default | Notes |
|---|---|---|
| `STORE_ORDER_PREFIX` | `IN-` | Order numbers look like `IN-100123`. |
| `STORE_PAYMENT_TIMEOUT_MINUTES` | `60` | How long an unpaid card checkout holds stock. Stripe requires at least 30 minutes; the app uses a minimum of 31. |
| `STORE_CURRENCY` | `USD` | The store is built for USD. |

After changing `.env` in Docker, restart the containers so the cached configuration is rebuilt:

```bash
docker compose up -d --force-recreate app queue scheduler
```
