# Payments (Stripe) and cash on delivery

## Why Stripe

Infinite Naturals sells in USD to the 50 U.S. states from Florida. **Stripe Checkout** was chosen
because it is a widely used U.S. gateway that:

- hosts the payment page itself, so card numbers never reach this server (lowest PCI burden — SAQ A);
- supports cards, Apple Pay, Google Pay, Link, ACH bank debit and buy-now-pay-later methods
  (Klarna/Afterpay/Affirm), all switched on or off in the Stripe Dashboard without code changes;
- has a full test ("sandbox") mode, signed webhooks and idempotent APIs.

The integration lives in `app/Services/Payments/`. All Stripe API calls go through the small
`StripeApi` interface, so another gateway could be added later without touching checkout.

## How a card payment flows

1. **Place order** — the customer submits checkout. The server recalculates the total, locks the
   stock rows, creates the order as *Awaiting payment*, reserves stock and empties the cart.
2. **Stripe Checkout** — the server creates a Checkout Session for exactly the order total
   (one line "Infinite Naturals order IN-100123", with the items in the description) and
   redirects the customer to Stripe. The session expires after `STORE_PAYMENT_TIMEOUT_MINUTES`.
3. **Confirmation** — the order becomes *Paid* only when the server has confirmed it with Stripe:
   - the signed **webhook** `checkout.session.completed` (payment_status `paid`), or
   - the **return URL**: when the customer comes back, the server *retrieves the session from the
     Stripe API* and checks it. The redirect itself is never trusted.

   The amount and currency Stripe reports must match the order exactly, otherwise the order is
   flagged *Needs review* and not marked paid.
4. The confirmation e-mail (customer) and new-order alert (store) are sent once.

| Situation | What happens |
|---|---|
| Card declined | Stripe shows the error on its page; the customer can try another card. Order stays *Awaiting payment*. |
| Customer clicks "back" on Stripe | Session is expired via the API, order **cancelled**, stock released, items put back in the cart. |
| Customer closes the tab | Stripe expires the session; `checkout.session.expired` webhook (or the 5-minute `orders:expire-unpaid` job, which checks Stripe first) cancels the order and releases stock. |
| Delayed method (e.g. ACH bank debit) | `completed` with `unpaid` → order shows *Payment processing*; then `async_payment_succeeded` → *Paid*, or `async_payment_failed` → *Payment failed*, stock released. |
| Refund (from admin or the Stripe Dashboard) | `charge.refunded` updates the order to *Refunded* / *Partially refunded*. |
| Duplicate / out-of-order webhooks | Each event ID is processed once; state changes only move forward. |
| Payment arrives for an order already cancelled | Order is marked *Paid* **and** *Needs review* so staff can check stock and fulfil or refund. |

## Test mode (sandbox) setup

1. Create (or open) the Stripe account and switch the Dashboard to **Test mode**.
2. **Developers → API keys**: copy the *Publishable key* (`pk_test_…`) and *Secret key* (`sk_test_…`)
   into `.env` as `STRIPE_KEY` and `STRIPE_SECRET`.
3. Webhooks in development — use the Stripe CLI container, which forwards events to the app:

   ```bash
   docker compose --profile dev --profile stripe up -d
   docker compose logs stripe | grep "webhook signing secret"
   ```

   Copy the `whsec_…` value into `STRIPE_WEBHOOK_SECRET`, then restart the app containers:

   ```bash
   docker compose up -d --force-recreate app queue scheduler
   ```

4. Check the connection:

   ```bash
   docker compose exec app php artisan store:stripe-check
   ```

5. **Settings → Payment methods** in the Stripe Dashboard: turn on the methods you want
   (cards are on by default; Apple Pay/Google Pay need no extra work with hosted Checkout).

## Verifying payments in test mode

Use any future expiry date, any CVC and any ZIP. Full list: <https://docs.stripe.com/testing>.

| Scenario | How | Expected result |
|---|---|---|
| Success | Card `4242 4242 4242 4242` | Returned to the order page "payment was successful"; order *Paid*; confirmation e-mail in Mailpit; admin shows the payment intent ID. |
| 3-D Secure | Card `4000 0025 0000 3155`, approve the challenge | Same as success. |
| Decline | Card `4000 0000 0000 0002` (or `…9995` insufficient funds) | Error on Stripe's page; order remains *Awaiting payment*; no e-mail. |
| Cancellation | Click the back arrow / "←" on the Stripe page | Back on the cart with "Payment was cancelled… not been charged"; items back in cart; order *Cancelled*; stock restored (Admin → Inventory history shows "Order cancelled / unpaid"). |
| Expiry | Leave the Stripe page open past the timeout (or run `php artisan orders:expire-unpaid` after setting the order's `payment_expires_at` in the past) | Order *Cancelled*, stock restored. |
| Pending → paid / failed | Enable **ACH Direct Debit** in test mode, pay with *US bank account → Test Institution* and pick the success or failure test account | Order shows *Payment processing*, then *Paid* or *Payment failed* when Stripe sends the async webhook. |
| Refund | Admin → order → Refund panel | Stripe shows the refund; order *Refunded*; optional restock. |
| Webhook security | `curl -X POST localhost:8080/webhooks/stripe -d '{}'` | `400 Invalid signature`, nothing changes. |

Every scenario above is also covered by automated tests (`tests/Feature/StripePaymentTest.php`)
using a fake Stripe API and genuinely signed webhook payloads.

## Going live

1. Complete Stripe account activation (business details, bank account for payouts).
2. In **live mode**, copy the live keys into the production `.env` (`pk_live_…`, `sk_live_…`).
   Prefer a **restricted key** with: *Checkout Sessions — Write*, *Refunds — Write*, *Balance — Read*.
3. **Developers → Webhooks → Add endpoint**:
   - URL: `https://www.infinitenaturals.com/webhooks/stripe` (Admin → Settings shows the exact URL)
   - Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
     `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`
   - Copy its signing secret into `STRIPE_WEBHOOK_SECRET`.
4. Recreate the containers, run `php artisan store:stripe-check` (should say LIVE mode).
5. Place one real low-value order with your own card, confirm it shows *Paid*, then refund it from the admin.
6. Optional in Stripe: set the statement descriptor ("INFINITE NATURALS"), enable Radar rules, add
   your logo/colours under *Settings → Branding* so the payment page matches the store.

**Never** put live keys in a development `.env`, and never commit any key to Git.

## Cash on delivery (off by default)

Only enable this if the business decides to offer it and its carrier supports collecting payment
on delivery for your shipments — confirm the service, fees and limits with the carrier first.

Admin → Settings → Payments → "Offer cash on delivery" (+ optional instructions shown at checkout).
COD orders are confirmed immediately, reserve stock, can be fulfilled while unpaid, and staff
record the money with **Mark cash collected** on the order. Online (Stripe) orders can never be
marked paid manually.
