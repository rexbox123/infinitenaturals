# Testing and verification

## Automated tests

```bash
php artisan test
```

98 tests / 548 assertions, run against SQLite in memory by default. They also pass against MySQL
8.4 (the production database):

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33060 DB_DATABASE=<empty test db> \
DB_USERNAME=root DB_PASSWORD=<root password> php artisan test
```

| File | Covers |
|---|---|
| `StorefrontTest` | Home, shop search/filters/sorting, old WordPress URLs, product tabs, related products, sold-out, hidden drafts/archived, policy pages, contact form + honeypot, sitemap |
| `CartTest` | Add/merge/update/remove with totals, stock limits, cart ownership, quantities shrinking when stock drops, discount codes, cart kept after login |
| `CheckoutTest` | Checkout page, stock reservation + Stripe redirect for the exact total, address validation (APO, states, ZIP, terms), price-change protection, **last unit sold only once**, gateway failure rollback, closed checkout without shipping, cash on delivery, account linking, order status access control and lookup |
| `StripePaymentTest` | Return URL verified with Stripe (not trusted), signature tampering, bad webhook signature rejected, idempotent webhooks, amount mismatch flagged, pending → paid, async failure releases stock, expiry, customer cancel restores cart/stock, cancel after payment, retry, resume, scheduled cleanup, refund webhook, missing webhook secret |
| `AdminAccessTest` | Role matrix (staff/manager/admin/customer/guest), login redirect, deactivated accounts, no role mass-assignment, `store:create-admin`, last-admin protection |
| `AdminCatalogTest` | Product create with variants + stock log, HTML sanitising, validation, stock edits as deltas, archive/restore/delete rules, image upload/delete and rejecting non-images, inventory adjustments, page/homepage editing |
| `AdminOrderTest` | Shipping with tracking + e-mail, unpaid orders can't ship, cancel releases stock and expires Stripe session, refund before cancel, staff can't refund, manual payment only for COD, dashboard figures, customer history |
| `PriceCalculatorTest`, `MoneyTest` | Free-shipping threshold, discount then tax order, tax on shipping, sale windows, money parsing |

Stripe is replaced in tests by `tests/Fakes/FakeStripeApi.php`, which models the Checkout Session
lifecycle. Webhook tests send payloads signed with the real Stripe HMAC scheme, so signature
verification runs exactly as in production.

## Concurrency (overselling) check

SQLite serialises writes, so overselling can only be proven against MySQL/PostgreSQL. This script
forks N buyers that all try to buy the same item at the same instant:

```bash
docker compose exec app php scripts/oversell-check.php 3 25     # stock 3, 25 buyers
```

Results recorded on 2026-11-11 (MySQL 8.4 in Docker):

| Stock | Buyers | Succeeded | Refused | Stock left | Result |
|---|---|---|---|---|---|
| 3 | 20 | 3 | 17 | 0 | PASS |
| 1 | 20 | 1 | 19 | 0 | PASS |
| 5 | 20 | 5 | 15 | 0 | PASS |
| 2 | 50 | 2 | 48 | 0 | PASS |
| 3 | 25 (inside the app container) | 3 | 22 | 0 | PASS |

## Manual verification log (2026-11-11)

Done:

- Fresh clone → `docker compose --profile dev up -d --build` → all services healthy; migrations ran
  on start; `db:seed` and `store:create-admin` worked; storefront pages returned 200.
- Queue worker delivered a contact-form e-mail to Mailpit; scheduler running.
- Browser walkthrough (desktop and 375 px mobile): home, shop, category, product, cart, checkout,
  cash-on-delivery order → confirmation page; admin login → dashboard checklist → order → marked
  shipped with tracking. All storefront and admin pages checked for horizontal overflow at 375 px.
- Bugs found and fixed during verification: Stripe return-URL signature (Stripe appends
  `session_id`), order-number placeholder too long for MySQL, mobile menu clipped by the header,
  admin tables overflowing on phones.

**Not yet done — needs the owner's Stripe test keys:** a real round-trip against Stripe's test
environment. Once keys are in `.env`, run through the table in [payments.md](payments.md#verifying-payments-in-test-mode)
(success, 3-D Secure, decline, cancel, ACH pending → paid/failed, refund) with the `stripe` Compose
profile forwarding webhooks.
