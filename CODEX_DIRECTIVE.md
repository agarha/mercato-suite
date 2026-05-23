# Codex — Directive: Build on WooCommerce, Do Not Replace It

> **This file is the single most important rule in the entire codebase.**
> Read it before writing or modifying any module under `apps/wordpress/.../mercato-suite/modules/`.
> If a piece of code in this repo contradicts this directive, the directive wins and the code is wrong.

---

## TL;DR for Codex

Mercato is an **overlay on WordPress + WooCommerce**. It is not a replacement, not a fork, not a parallel checkout. Carts, products, orders, refunds, payment intents, taxes, shipping rates, customers, sessions, and the admin shell all belong to WooCommerce. Mercato's job is to add the **multivendor layer around** those things — vendors, sub-orders, commissions, payouts, KYC — and to listen for WooCommerce events through documented hooks.

If you find yourself implementing a cart, a checkout, a payment processor adapter, a product object, an order object, a refund engine, a tax engine, a shipping zone engine, or a customer record — **stop**. WooCommerce already has it. Hook into it.

---

## What WooCommerce owns (DO NOT reimplement)

| Domain | WooCommerce class / table | Mercato position |
|---|---|---|
| Cart lifecycle | `WC()->cart`, `WC_Cart` | Use as-is. Add line-item meta via `woocommerce_add_cart_item_data`. |
| Products | `WC_Product`, `wp_wc_product_meta_lookup` | Mercato keeps source-of-truth in `wp_mercato_products`, but projects to WC tables for cart/checkout compatibility (shadow projection). |
| Parent order | `WC_Order`, `wp_wc_orders` (HPOS) | Canonical for buyer. Mercato never overrides WC order create/save. |
| Order items | `WC_Order_Item_Product`, `wp_wc_order_items` | Canonical. Mercato reads via hooks. |
| Refunds | `WC_Order_Refund` | Canonical. Mercato reacts via `woocommerce_refund_created`. |
| Payment intents / charge | Stripe gateway plugin or WC payment APIs | Mercato never re-creates payment objects. Mercato Stripe Connect adapter wraps **payouts** (Destination Charges), not the initial charge. |
| Tax calculation | WC tax engine + tax-engine integration plugins | Mercato consumes results from `woocommerce_cart_calculate_fees`/`woocommerce_order_calculate_taxes`. We never recompute WC's tax. |
| Shipping zones (per buyer ZIP) | WC shipping zones | Buyer-side shipping is WC. Mercato adds **per-vendor** shipping zones on top, allocated at split time. |
| Customer | `WC_Customer`, `wp_users` | Canonical. Mercato references `buyer_user_id` only. |
| WP sessions / nonces | WP core | Use WP. Don't roll your own session layer. |
| Admin shell (menus, nonces, transients, options) | WP core | Use WP. Custom SPAs are mounted on WP page templates, not replacements for wp-admin. |
| Cron framework | WP-Cron / system cron | Use what's configured (system cron in prod per Vol 11). Don't write your own scheduler. |
| User capabilities (base set) | WP roles | Use WP roles for buyers/admins. Mercato RBAC sits on top for marketplace-specific capabilities. |

---

## What Mercato owns

| Domain | Mercato | Justification |
|---|---|---|
| Vendor identity & KYC state | `wp_mercato_vendors`, `wp_mercato_kyc_records` | No WC equivalent. |
| Vendor staff roles | `wp_mercato_vendor_staff` + Mercato RBAC | WC has admin/shop_manager only. |
| Sub-orders (per-vendor split) | `wp_mercato_suborders`, `wp_mercato_suborder_items` | WC doesn't split orders by vendor. |
| Commission rules + ledger | `wp_mercato_commission_rules`, `wp_mercato_commissions` | No WC equivalent. |
| Payout ledger + batching | `wp_mercato_payouts`, `wp_mercato_payout_batches` | WC doesn't disburse to multiple sellers. |
| Per-vendor shipping zones | `wp_mercato_shipping_zones` | Layered on top of WC shipping. |
| Marketplace audit log | `wp_mercato_audit_log` | WC has no immutable audit. |
| Event outbox (cross-service messaging) | `wp_mercato_event_outbox` | WC has no broker integration. |
| Idempotency store | `wp_mercato_idempotency` | WC has no idempotency layer. |
| Tenant isolation | `wp_mercato_tenants` + `tenant_id` column on every Mercato table | Multi-tenant SaaS is Mercato's domain. |
| Marketplace settings (commission policy, payout schedule, etc.) | `wp_mercato_tenant_settings` JSON | Marketplace policy, not WC config. |
| Vendor/Tenant SPAs | `apps/vendor-spa/`, `apps/admin-spa/` | Modern app UX on top of WP. |

---

## The Hook Adapter — the only integration point with WooCommerce

There is exactly **one** way Mercato touches WooCommerce: through `Mercato\Core\WooCommerce\HookAdapter` and the hook map in `docs_v2/04_fsd/FSD.md` §3.6 and `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` §4.

The **canonical hook list** (do not subscribe to a WC hook outside this list without an architectural-review PR):

| WC hook | Mercato adapter | What we do |
|---|---|---|
| `plugins_loaded` p=1 | `Core\Bootstrap` | Boot the suite |
| `init` p=5 | `Core\Capabilities\Register` | Register Mercato capabilities |
| `rest_api_init` | `Core\REST\Router` | Register `/mercato/v1/*` |
| `woocommerce_init` | (refuse if HPOS off) | Sanity check |
| `woocommerce_cart_calculate_fees` | `Orders\Cart\FeeCalculator` | Read-only cart preview |
| `woocommerce_checkout_create_order` | `Orders\Checkout\SplitValidator` | Validate vendor split, throw on invalid |
| `woocommerce_new_order` | `Orders\Splitter` | Create Mercato sub-orders + emit event |
| `woocommerce_order_status_changed` | `Orders\Status\Synchronizer` | Reflect into sub-orders |
| `woocommerce_payment_complete` | `Payments\Confirmer` | Trigger commission accrual |
| `woocommerce_refund_created` | `Refunds\Reverser` | Reverse commission |
| `woocommerce_order_refunded` | `Refunds\PostRefundActions` | Post-refund side effects |
| `save_post_product` | `Products\ShadowGuard` | Block direct WC admin edits of Mercato products |
| `template_redirect` | `Storefront\VendorPageRouter` | Resolve `/store/<slug>/*` |
| `woocommerce_email_classes` | `Notifications\WCEmailOverride` | Replace WC emails with Mercato templates |
| `woocommerce_thankyou` | `Notifications\OrderReceipt` | Multi-vendor receipt |
| `woocommerce_admin_order_actions_end` | (admin row actions) | Optional |

**Any other WC hook = PR + architecture review.**

---

## Forbidden patterns — Codex, please verify your code does NONE of these

### Cart / checkout

❌ Replacing `WC()->cart` or building a parallel cart.
❌ A `Mercato\Cart\*` namespace that holds line items.
❌ A `Mercato\Checkout\*` class that mutates payment state.
❌ Calling `wc_create_order()` from Mercato (let WC create the parent; Mercato creates sub-orders **after**).
❌ Implementing a `process_payment()` method in any Mercato module — that's WC + the gateway plugin.

### Products

❌ A Mercato product class that doesn't shadow-project to WC.
❌ Storing prices, stock, or visibility only in `wp_mercato_products` — WC has to see them too via the shadow projector for cart compatibility.
❌ Storing marketplace product data in `wp_postmeta` keys (use `wp_mercato_products` + JSON columns).
❌ A separate frontend cart "add to cart" flow that bypasses WC.

### Orders

❌ A `Mercato\Order` aggregate that competes with `WC_Order` for the buyer-facing representation. Mercato sub-orders are an *adjunct*, not a replacement. Buyer-facing "your order" page is WC's `WC_Order`.
❌ Storing buyer billing/shipping address only in `wp_mercato_suborders.ship_to` — that's a *snapshot* of the WC parent's address, not the source of truth.

### Payments

❌ Writing a payment-method registration class (WC's `WC_Payment_Gateway` is the contract).
❌ Calling `\Stripe\PaymentIntent::create()` for the initial charge — that's the WC Stripe gateway's job. Mercato's `mercato-stripe-connect` is only for **payouts** (Connect Destination Charges + Transfers).

### Tax / Shipping

❌ A `Mercato\Tax\Calculator` that reimplements jurisdiction lookup. Mercato uses WC + a tax-engine integration (TaxJar/Avalara via WC).
❌ Re-implementing WC shipping zones for the buyer. Mercato's `mercato-shipping-zones` is for the *vendor*-defined sub-shipping that gets allocated **inside** WC's shipping result.

### Customers / Users

❌ A `wp_mercato_customers` table. Use `wp_users` + `buyer_user_id` references.
❌ A custom login/auth flow for buyers. WP does login. Vendor/Admin SPAs use JWTs minted **after** WP/WC auth.

### Admin

❌ A `Mercato\Admin\MenuRegistrar` that registers things via raw PHP outside WP's menu API.
❌ Reimplementing `wp_options` / `wp_transient` / `wp_cache` patterns.

### Sessions / nonces

❌ A custom session manager. Use WP's, or WC's customer session.
❌ A custom nonce implementation. Use `wp_create_nonce()` / `check_ajax_referer()` / REST `_wpnonce`.

---

## Quick mental checklist before writing a new class

Before adding a new class to any Mercato module, ask:

1. **Does WooCommerce already do this?** Search `wc-*` and `class-wc-*` files. If yes → use it through a hook, don't replicate.
2. **Does WordPress core already do this?** Settings → `wp_options`. Cron → `wp_schedule_event` or system cron. HTTP → `wp_remote_get`. Users → `wp_users` + `wp_usermeta` (for non-marketplace data).
3. **Am I about to subscribe to a WC hook?** Verify it's in the canonical hook list (§ above). If not, open a PR labeled `contract-change` first.
4. **Am I about to write into `wp_postmeta` for marketplace data?** Stop. Use `wp_mercato_*` tables.
5. **Am I duplicating data WC already has?** A snapshot at order time is fine (we need immutability). A live duplicate is not.
6. **Is this code Mercato-specific?** Vendor, sub-order, commission, payout, KYC, tenant — yes. Cart, parent order, refund object, tax computation, shipping computation, customer, payment intent, session — no.

---

## When in doubt

The decision tree:

```
Need a feature in Mercato?
│
├── Is it about vendors / sub-orders / commissions / payouts / KYC / tenant?
│   └── YES → Mercato builds it. Use wp_mercato_* tables + hooks for WC integration.
│
├── Is it about carts / parent orders / products (the buyer-facing record) /
│   refunds / tax / customer / session / nonces / admin shell?
│   └── YES → WooCommerce or WordPress already has it. Hook in.
│
└── Mixed (e.g., "buyer browses catalog filtered by vendor")?
    └── Read from WC's storefront pipeline + Mercato's search overlay.
        Never re-implement the storefront, only augment.
```

If still unclear: **stop and read `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md`** before writing the code.

---

## Self-check for current code

Codex, before your next commit, please grep the repo for these red-flag patterns and either explain why they're justified or remove:

```bash
# Mercato code calling WC mutation APIs (mostly forbidden)
grep -rn "wc_create_order\|wc_update_order\|wc_add_to_cart\|process_payment" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/

# Mercato classes that look like WC competitors
grep -rEn "class\s+(Mercato\\\\Cart|Mercato\\\\Checkout|Mercato\\\\PaymentIntent|Mercato\\\\Customer)" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/

# Direct postmeta writes for marketplace data (forbidden)
grep -rn "update_post_meta\|add_post_meta" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/ \
  | grep -v "_mercato_vendor_id"   # only _mercato_vendor_id is allowed in postmeta

# Hook subscriptions outside the canonical list
grep -rEn "add_action\s*\(\s*['\"]woocommerce_" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/
# Cross-check each line against the canonical hook table above.

# Custom session / nonce systems (forbidden)
grep -rEn "class\s+\w*Session\b|generateNonce|createNonce" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/
```

If grep returns anything that violates the rules above, file an issue tagged `wc-overlap` and open a PR removing it.

---

## What "good" looks like

A good Mercato module:
- Adds tables under `wp_mercato_*`.
- Listens to 1–3 WC hooks from the canonical list.
- Emits Mercato events via the outbox.
- Exposes REST routes under `/wp-json/mercato/v1/`.
- Never touches `WC_Cart`, `WC_Payment_Gateway`, `WC_Tax`, or `WC_Shipping_Zone` except read-only.
- Has zero references to "checkout", "payment processing", "session management" in its class names.

A good Mercato module's first line of `Provider::register()` looks like:

```php
$this->container->bind(SomeMarketplaceConcept::class, fn($c) => new SomeMarketplaceConcept(
    $c->get(\Mercato\Core\Events\Outbox::class)
));
```

Not:

```php
class MercatoCart extends WC_Cart { ... } // ❌ NO
```

---

## Cross-references

| Topic | Read |
|---|---|
| WC integration boundary | `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` |
| Hook map (canonical) | `docs_v2/04_fsd/FSD.md` §3.6 |
| Plugin packaging (one suite, not 27 plugins) | `docs_v2/14_packaging/Plugin_Packaging.md` |
| MVP scope (what's even in scope right now) | `docs_v2/00_mvp_cut/MVP_Cut.md` |
| Non-negotiable architectural constraints | `docs_v2/01_architecture/Blueprint.md` §16 |
| Forbidden patterns | `HANDOFF_FROM_CLAUDE.md` §4.3 |

---

## Final note

Mercato is **WooCommerce + a multivendor brain**. Not WooCommerce 2.0, not a fork, not a parallel commerce engine. Every line of code you write should reinforce this.

If you catch yourself writing something WooCommerce already does, the correct response is to delete that code and add a hook subscription instead.

— Project owner
