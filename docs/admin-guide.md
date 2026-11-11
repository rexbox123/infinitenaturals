# Admin guide

Sign in at `/login`; staff accounts land on `/admin`.

## First admin account

```bash
docker compose exec app php artisan store:create-admin            # prompts for name, email, password
# or non-interactively (avoid putting passwords in shell history on shared machines):
docker compose exec app php artisan store:create-admin --name="Owner" --email=owner@infinitenaturals.com
```

Running it again with an existing email resets that person's password and makes them an admin.
After that, add other people under **Admin → Staff**.

## Roles

| | Administrator | Store manager | Fulfillment staff |
|---|:-:|:-:|:-:|
| Dashboard, orders, shipping/tracking, notes, cancel unpaid orders | ✓ | ✓ | ✓ |
| Inventory (restock, stocktake) | ✓ | ✓ | ✓ |
| Contact-form messages | ✓ | ✓ | ✓ |
| Refunds | ✓ | ✓ | |
| Products, categories, photos | ✓ | ✓ | |
| Customers | ✓ | ✓ | |
| Discount codes, banners, homepage, pages | ✓ | ✓ | |
| Store settings, payments, shipping rates, sales tax | ✓ | | |
| Staff accounts | ✓ | | |

Defined in `config/store.php` (`permissions`). The last active administrator cannot be demoted or
removed. Removing a staff member keeps their history and just revokes admin access.

## Products

- **Status**: *Active* (on sale), *Draft* (hidden, preview from the admin), *Archived* (hidden,
  kept for order history). Products that appear in past orders can't be deleted — archive them.
- **Variants**: every product has at least one purchasable option. Use one "Default" variant for
  simple products, or add options such as sizes, each with its own SKU, price, optional sale price
  with start/end dates, stock and weight. Untick "Track stock" for unlimited items.
- **Stock on the product form** is applied as a change ("+5"), so sales that happen while you edit
  are not overwritten. For deliveries and counts, use **Inventory** (restock / remove / set exact
  count) — each change is logged with the reason and who made it.
- **Photos**: upload JPG/PNG/WebP (up to 8 MB). The first photo is the main one; set the order and
  alt text (describe the photo for screen readers and search engines).
- **Feature on homepage**: shows the product in "Our Featured Products".
- Description, Benefits and Ingredients are rich text. Only make claims you can substantiate —
  cosmetics must not claim to treat or cure conditions.

## Orders

Order list filters: *To fulfil* (paid, or cash-on-delivery, not yet shipped) and *Needs review*.

Typical flow: **Paid** → set *Preparing order* → print the **packing slip** → set *Shipped* with
carrier + tracking number (customer is e-mailed a tracking link) → *Delivered*.

- Online payments are confirmed only by Stripe. If one looks stuck, use **Check payment with Stripe**.
- **Refund** (managers/admins): full or partial, through Stripe, with optional return to stock.
- **Cancel**: for unpaid orders (expires the Stripe page so it can no longer be paid and releases
  stock). Paid orders must be refunded first.
- **Notes**: internal by default; tick "Show to customer" to display on their order page.
- *Needs review* appears when Stripe reports an amount that doesn't match the order or a payment
  arrives after an order was cancelled. Investigate, then click "Mark reviewed".

Customers can see their order status from the confirmation e-mail link, at `/track-order`
(order number + email), or in their account.

## Promotions

- **Discount codes**: percentage or fixed amount off merchandise, or free shipping; optional
  minimum spend, start/end dates, total and per-customer usage limits. Used codes are deactivated
  rather than deleted.
- **Sale prices**: per variant on the product form, optionally scheduled. Products on sale show a
  badge and can be filtered with "On sale" in the shop.
- **Banners**: announcement bar (top of every page), homepage hero slides and promotion blocks —
  each with optional image, button and schedule.

## Content

- **Homepage**: introduction, mission, featured-products heading and contact text.
- **Pages**: About, Contact, FAQ, Shipping, Returns, Privacy and Terms are built in (editable,
  can be unpublished, cannot be deleted). You can add more pages; they get a footer link if ticked.
- **Settings**: store name, logo (until uploaded, a text wordmark is shown), contact details used
  across the site and e-mails, where new-order alerts go, payment toggles, low-stock level.
