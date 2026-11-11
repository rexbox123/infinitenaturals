# Infinite Naturals — online store

A custom Laravel e-commerce site that replaces the WordPress/WooCommerce store at
<https://www.infinitenaturals.com>. It keeps the brand (gold `#D5B981`, deep umber `#6e1900`,
Prata + Open Sans), the product catalogue, copy, contact details and policies from the current
site, and adds a full shopping journey, Stripe payments and an admin dashboard.

| | |
|---|---|
| **Stack** | PHP 8.3+ · Laravel 13 · MySQL 8.4 · Blade + Tailwind CSS + Alpine.js · Vite |
| **Payments** | Stripe Checkout (hosted page — card data never touches this server), optional cash on delivery |
| **Runs on** | Docker (nginx + PHP-FPM image, queue worker, scheduler, MySQL; Mailpit/Stripe CLI for development; Caddy for HTTPS) |

## What's included

**Storefront** — home page (hero slider, categories, featured products, promotions, brand story,
contact), product listing with search (name, description, ingredients, SKU), category filter,
price range, in-stock and on-sale filters and sorting; product pages with gallery, variants,
live price/stock, Description/Benefits/Ingredients tabs and related products; cart with quantity
editing, removal and discount codes; checkout with contact + delivery details, US-only/no-APO
validation, shipping options, live totals (shipping, free-shipping threshold, state sales tax);
order confirmation e-mails; order status page and "Track your order" lookup; customer accounts with
order history; About, Contact (with form), FAQ, Shipping, Returns, Privacy and Terms pages;
sitemap.xml; old WordPress URLs (`/product/…`, `/product-category/…`, `/about-us/`) still work.

**Payments** — Stripe Checkout in test or live mode. Orders are marked paid only after the server
confirms the payment with Stripe (signed webhook or API lookup). Handles success, card failure,
customer cancellation, delayed ("processing") payments, async failure, expired sessions and
refunds. Duplicate/out-of-order webhooks are harmless. See [docs/payments.md](docs/payments.md).

**Inventory** — stock is reserved when an order is placed using row locks, so two customers
cannot buy the last unit (verified with 50 simultaneous buyers, see [docs/testing.md](docs/testing.md)).
Unpaid card orders release their stock when the payment is cancelled, fails or expires. Every
change is recorded in a stock-movement history.

**Admin dashboard** (`/admin`, role-based) — sales summary and chart, orders to fulfil, low-stock
list and a "before you go live" checklist; products (variants, prices, sale prices with dates, SKUs,
stock, photos, rich-text descriptions, archive/delete); categories; inventory with restock/stocktake;
orders (payment status, fulfilment, tracking with customer e-mail, notes, packing slip, cancel,
Stripe refunds, cash-collected for COD); customers with order history; discount codes; banners
(announcement bar, hero slides, promotions, with schedules); homepage text; editable pages;
store/payment/shipping/tax settings; staff accounts; contact-form messages.

Roles: **Administrator** (everything), **Store manager** (catalogue, orders, refunds, customers,
promotions, content), **Fulfillment staff** (orders, shipping updates, inventory).
See [docs/admin-guide.md](docs/admin-guide.md).

## Quick start with Docker (recommended)

Requirements: Docker Desktop / OrbStack / Docker Engine with Compose v2.

```bash
git clone https://github.com/rexbox123/infinitenaturals.git
cd infinitenaturals
cp .env.docker.example .env
```

Edit `.env` and set `DB_PASSWORD` and `DB_ROOT_PASSWORD` to strong random values, then generate the
application key and paste it into `APP_KEY=`:

```bash
docker compose run --rm --no-deps app php artisan key:generate --show
```

Start everything (the app container runs database migrations automatically on start):

```bash
docker compose --profile dev up -d --build
```

Load the store content (and, in `APP_ENV=local`, demo stock, demo shipping rates, sample orders
and demo logins):

```bash
docker compose exec app php artisan db:seed --force
```

Create your own admin account (you will be prompted for the password):

```bash
docker compose exec app php artisan store:create-admin
```

| URL | What |
|---|---|
| <http://localhost:8080> | Store |
| <http://localhost:8080/admin> | Admin dashboard |
| <http://localhost:8025> | Mailpit — every e-mail the app sends in development |

Demo logins created by the demo seeder (local only — never in production):
`admin@example.com` / `staff@example.com` / `customer@example.com`, password `password`.

To take test payments, add Stripe **test** keys to `.env` and follow [docs/payments.md](docs/payments.md).

## Running without Docker

Requires PHP 8.3+ (pdo_sqlite or pdo_mysql, intl), Composer and Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # or point DB_* at MySQL
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan store:create-admin
php artisan serve                   # http://127.0.0.1:8000
```

Run `php artisan queue:work` (e-mails are queued) and `php artisan schedule:work` (releases stock
from abandoned checkouts) in two more terminals.

## Tests

```bash
php artisan test                         # 98 feature/unit tests (SQLite in memory)
php scripts/oversell-check.php 2 50      # concurrency check — needs MySQL (see docs/testing.md)
```

## Documentation

| Document | Contents |
|---|---|
| [docs/configuration.md](docs/configuration.md) | Every environment variable |
| [docs/payments.md](docs/payments.md) | Stripe setup, test cards, webhooks, verifying, going live, cash on delivery |
| [docs/shipping-and-tax.md](docs/shipping-and-tax.md) | Shipping methods, free-shipping threshold, sales tax |
| [docs/deployment.md](docs/deployment.md) | Production server, HTTPS, backups, updates |
| [docs/admin-guide.md](docs/admin-guide.md) | Roles and day-to-day store management |
| [docs/testing.md](docs/testing.md) | Automated tests, concurrency check, manual verification log |
| [docs/owner-checklist.md](docs/owner-checklist.md) | **Content, credentials and decisions the owner still needs to provide** |
| [docs/git.md](docs/git.md) | Git setup and workflow |

## Project layout

```
app/
  Console/Commands/      store:create-admin, store:stripe-check, store:import-images, orders:expire-unpaid
  Enums/                 Product, payment, fulfilment and discount states
  Http/Controllers/      Storefront controllers; Admin/ for the dashboard
  Mail/                  Order confirmation, shipped, new-order alert, contact message
  Models/                Eloquent models (money is stored as integer cents)
  Services/
    Cart/                Database-backed cart
    Checkout/            PriceCalculator (single source of truth for totals), PlaceOrder
    Inventory/           Locked stock reservation/release and movement log
    Payments/            Stripe Checkout, webhook-safe order payment state machine
    Settings.php         Owner-editable settings (no secrets)
config/store.php         Roles/permissions, shipping states, carriers, order prefix
database/seeders/        StoreContentSeeder (real site content), DemoSeeder (local only)
database/seeders/data/   catalog.json + pages.php scraped from the current site
docker/, Dockerfile, docker-compose.yml
resources/views/         store/, admin/, account/, mail/, components/
scripts/oversell-check.php
tests/                   Feature + unit tests; tests/Fakes/FakeStripeApi.php
```
