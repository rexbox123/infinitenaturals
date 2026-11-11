# What the owner still needs to provide or decide

Everything below is either missing from the current website or is a business/legal decision.
Nothing was invented to fill these gaps; each item is editable in the admin (or `.env` for secrets).
The admin dashboard shows a live "Before you go live" list for most of them.

## Credentials and accounts

| Item | Where it goes |
|---|---|
| Stripe account (test keys now, live keys at launch) and webhook signing secret | `.env` → `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` ([payments.md](payments.md)) |
| Transactional e-mail provider (SMTP/Postmark/SES…) for `info@infinitenaturals.com`, with SPF/DKIM DNS records | `.env` → `MAIL_*` |
| Hosting server and DNS access for `www.infinitenaturals.com` | [deployment.md](deployment.md) |
| Your own admin login (replace the demo accounts, which exist only in local development) | `php artisan store:create-admin` |
| Access to the WordPress host to export `wp-content/uploads` (product photos, logo) | `php artisan store:import-images` |

## Content

| Item | Notes |
|---|---|
| **Product photos** | The old site's images are hotlink-protected and could not be copied, so products show a branded placeholder. Export them or upload new ones per product. |
| **Logo files** | The site shows a text wordmark until a logo is uploaded (Admin → Settings). A transparent PNG/SVG of the current logo is ideal. |
| **Hero / promotion images** | The homepage hero and "Say Goodbye to Dry Skin" promotion use brand-colour backgrounds until images are uploaded (Admin → Banners). |
| **Stock levels** | The old site showed "in stock" without quantities, so all products start at **0 (sold out)**. Enter real counts in Admin → Inventory. |
| **SKUs** | None on the old site. Optional but recommended. |
| **Product sizes for serums, cream, cleanser, toner** | Only the soaps and shower gel state a size. Add sizes (variant name) if you want them shown. |
| **Product weights** | Needed only if you later move to weight-based/live USPS rates. |
| **Category descriptions/images** | None on the old site; optional. |
| **FAQ** | The old site had no FAQ. A starter FAQ was written only from facts already in your shipping/returns policy — review and add questions customers actually ask. |
| **Social media links** | None found on the old site; add in Admin → Settings if wanted. |
| **"We Accept" / "Proudly Present" footer sections** | These headings existed on the old site but their content (images) could not be read; not reproduced. |

## Business decisions

| Decision | Notes |
|---|---|
| **Shipping rates** | The policy names USPS Standard (free over $50), Priority and Express but not their prices. The three methods are seeded **inactive** — set rates and activate at least one (Admin → Shipping), or checkout stays closed. |
| **Sales tax** | Which states you collect in and at what rates (Florida county surtax varies). No rates are configured, so no tax is charged until you add them (Admin → Sales tax). Confirm with your accountant. |
| **Free-shipping threshold basis** | Implemented as "merchandise total after discounts ≥ $50". Change if you intended before discounts. |
| **Payment methods** | Cards are on by default in Stripe. Decide on Apple Pay/Google Pay/Link/ACH/Klarna/Afterpay in the Stripe Dashboard. |
| **Cash on delivery** | Off. Enable only if you decide to offer it and your carrier supports it. |
| **Order processing cut-off time** | The policy says "11:00 AM PST" while orders ship from Florida (Eastern time). Please confirm the intended time zone and update the Shipping Policy page. |
| **Pricing** | The old homepage cards showed "$100 monthly" for the three featured products while the shop and product pages say $69.00. The store uses **$69.00**. Confirm, and whether any subscription offer is intended (not built). |
| **Unpaid-order hold time** | Card checkouts hold stock for 60 minutes (`STORE_PAYMENT_TIMEOUT_MINUTES`). |
| **Customer accounts** | Guests can check out; accounts are optional. E-mail verification is not required — enable if desired. |

## Legal / compliance review (recommended before launch)

| Item | Why |
|---|---|
| **Privacy Policy** | Copied as-is from the old site. It does not mention Stripe (payment processor), e-mail providers, cookies used for the cart/session, data retention or state privacy rights. Have it reviewed and updated. |
| **Terms and Conditions** | Copied as-is (split into Terms, Shipping, Returns, Privacy pages). Review wording and add governing law if wanted. |
| **Product claims** | Product copy was copied verbatim from the old site. Some statements may be read as drug-like claims for a cosmetic (e.g. "Anti-inflammatory properties soothe damaged skin", "protection from the effects of UV exposure", collagen-stimulating, "skin lightening… reducing hyperpigmentation", "550 times more powerful than vitamin E"). Review against FDA cosmetic labelling rules and edit in Admin → Products. |
| **"Natural" / "organic" wording** | The About page and homepage describe products as natural and organic. If products are not certified organic (e.g. USDA), consider rewording. No certifications were added. |
| **Country of origin** | Bar soaps say "Made in France"; shower gel and heart soap say "Product of France". Confirm. |
| **"As seen in the feature film…"** | Kept from the old hero slider; confirm you still want it. |
