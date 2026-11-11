# Shipping and sales tax

## Shipping

**Admin → Shipping** (administrators only). Each method has a name, a description/delivery estimate
shown at checkout, a flat rate, and an optional "free when the merchandise total is at least"
amount. Only *active* methods are offered; if none is active, checkout is closed and shoppers are
asked to phone or e-mail instead.

Seeded from the current shipping policy, **inactive until you set the prices**:

| Method | Free over | Rate |
|---|---|---|
| USPS Standard | $50.00 (policy: "USPS Standard shipping is FREE on orders above $50") | **to be set by the owner** |
| USPS Priority Mail | — | **to be set by the owner** |
| USPS Priority Mail Express | — | **to be set by the owner** |

Rules built in:

- Destinations: the 50 U.S. states only (no DC, territories or military states AA/AE/AP), and
  addresses containing APO/FPO/DPO are rejected — matching "Infinite Naturals ships to all 50 U.S.
  states… We do not ship to APO or FPO addresses." If you want to add DC or territories, edit
  `shipping_states` in `config/store.php`.
- The free-shipping threshold is checked against the merchandise total **after** discount codes.
- A "Free shipping" discount code makes any method free.
- Rates are flat per order. Weight is stored on each variant (`weight_grams`) for future
  weight-based or live USPS rates, which would need a USPS/Shippo/EasyPost account.

Fulfilment: mark orders *Shipped* with carrier + tracking number in the order screen; the customer
gets an e-mail with a tracking link (USPS/UPS/FedEx links are built automatically).

## Sales tax

**Admin → Sales tax** (administrators only). Add a rate per state where you must collect tax
(e.g. Florida). Tax is calculated on the shipping address, as the current policy says, on the
merchandise total after discounts, plus shipping if you tick "Shipping charges are taxable".
States without a rate are not taxed.

Nothing is pre-filled because which states you must collect in (economic nexus) and the exact
combined rates are a legal/accounting decision. For Florida, rates vary by county surtax.
If you prefer automatic rates per address, Stripe Tax or a service such as TaxJar can replace the
table later.

The checkout shows the tax line and total before the customer pays, and the server recalculates
everything when the order is placed; if anything changed (price, stock, tax) the customer is asked
to review the new total instead of being charged a different amount.
