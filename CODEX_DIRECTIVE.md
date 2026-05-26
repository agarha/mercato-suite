# Codex — Directive: Mercato is the SaaS Services-Marketplace Layer ON TOP OF WooCommerce

> **This file is the single most important rule in the entire codebase.**
> Read it before writing or modifying any module under `apps/wordpress/.../mercato-suite/modules/`.
> If a piece of code in this repo contradicts this directive, the directive wins and the code is wrong.

**Revision 2 — 2026-05-21.** Supersedes Rev 1. Reframed to reflect the actual product (Gigsii/Xusmo: a services marketplace, not a physical-goods marketplace) per joint clarification between project owner and Codex.

---

## 0. TL;DR

Mercato is a **multi-tenant SaaS services-marketplace platform** that powers many Xusmo-branded marketplaces (e.g. `gigsii`). It sits **on top of WordPress + WooCommerce** — WooCommerce is the commerce engine; Mercato is the services-marketplace operations engine.

The job split is sharp:

- **WooCommerce + selected WC plugins own:** cart, checkout, payment gateway UI, payment processing, tax engine, coupons, subscription mechanics, customer account pages, the admin shell.
- **Mercato owns:** SaaS tenancy & routing, providers (= service sellers), service templates, service requests, provider bids/auctions, awarded jobs, dispatch & job lifecycle, provider operational status, commissions, payouts, audit, the outbox, and the Xusmo integration surface.
- **Adapters bridge them:** Mercato routes tenant/provider context into WooCommerce, consumes Woo events back into Mercato, and turns awarded jobs into WC orders (or settles them via WC subscriptions where recurring).

If you find yourself implementing a cart, a checkout flow, a payment processor adapter, a tax engine, a coupon engine, a subscription mechanic, or a customer account record — **stop**. WooCommerce already has it. Hook into it.

If you find yourself implementing your own service-request board, provider-bid auction, job dispatch state machine, operational status tracker, or tenant routing — **good, that's Mercato's job.** Build it.

---

## 1. Product framing (read this first)

Mercato is the platform behind:

- **Xusmo** — a SaaS marketplace product. One Mercato codebase serves many Xusmo tenants.
- **Gigsii** — the first/flagship Xusmo tenant.
- Future Xusmo customers — each gets their own tenant on the same Mercato codebase.

A "tenant" is one branded marketplace (e.g. `/t/gigsii` or `gigsii.xusmo.com`). Per tenant, Mercato hosts:

- The tenant's **providers** (formerly called "vendors" — see §2 vocabulary): individuals/businesses who offer services.
- The tenant's **service templates** (formerly called "products" in some docs): the kinds of services available (e.g. "house cleaning, 2-hour standard").
- The tenant's **service requests**: buyers post jobs (or buyers select from canned options).
- The tenant's **bids/awards**: providers bid; one is awarded the job.
- The tenant's **job lifecycle**: dispatch → in-progress → completed → reviewed → paid.
- The tenant's **branding, feature flags, integrations, and SaaS billing**.

The commerce settlement of every awarded job (the actual buyer payment) happens through **WooCommerce** — not a Mercato-built checkout.

---

## 2. Vocabulary reconciliation

Some terms in `docs_v2/` come from a physical-goods marketplace mindset (the original draft). Translate them mentally as you read:

| Old / generic term | Services-marketplace term | Notes |
|---|---|---|
| Vendor | **Provider** | Sells services, not stock |
| Product | **Service template** (or just "service") | A canned service offering, not a SKU |
| Order | **Job** (with WC order as the payment record) | Often 1 provider per job; sub-orders rare |
| Sub-order | Mostly N/A for services | Only relevant if a job involves multiple providers (advanced case) |
| Shipping tracking | **Job status / dispatch updates** | Not parcels — appointments, on-site arrival, completion |
| Shipping zones | **Provider service areas** | Geographic radius / coverage |
| KYC | KYC + **provider verification** | Identity + insurance + license where applicable |
| Catalog browsing | **Service discovery + request posting** | Buyer's flow is "describe what you need," not "browse SKUs" |
| Multi-vendor cart | **Service request → bids → award** | Many providers may compete; one wins |
| Multi-vendor checkout | Single-provider payment (post-award) | Buyer pays once, after award |

DDL table names may still say `wp_mercato_suborders` etc. — that's fine as a generic "job record" table. Don't rename storage; rename concepts mentally.

The bigger doc-debt items (PRD, FSD, UX) will be reconciled to services-marketplace vocabulary in a follow-up doc pass. For now: write code against the services model, treat goods-flavored doc passages as background.

---

## 3. Ownership matrix — who owns what

### 3.1 WooCommerce + WC ecosystem owns

| Domain | WC class / plugin | Mercato role |
|---|---|---|
| Cart lifecycle | `WC()->cart`, `WC_Cart` | Use as-is; Mercato adds line-item meta for tenant/provider/job-id context |
| Checkout UI | WC checkout templates | Override theme only; never reimplement checkout |
| Payment gateways | WC Stripe gateway, WC PayPal, etc. | Mercato never creates `PaymentIntent`s for the initial charge |
| Tax calculation | WC tax engine + TaxJar/Avalara integrations | Consume calculated tax; do not recompute |
| Coupons | WC coupons | Use WC; Mercato can flag provider/job context but doesn't compute discounts |
| Subscriptions (for recurring services) | WC Subscriptions plugin | Use it where the service is recurring; do not build a parallel subscription system |
| Customer accounts | `WC_Customer`, `wp_users` | Mercato references `buyer_user_id`; never duplicates account record |
| Order record (the *payment* record) | `WC_Order` (HPOS `wp_wc_orders`) | Canonical for buyer-facing payment state. Mercato job records reference it via `wc_order_id`. |
| Refunds | `WC_Order_Refund` | Canonical. Mercato reacts via `woocommerce_refund_created` |
| Admin shell / menus / nonces / options | WP core + WC admin | Use WP/WC; SPAs mount on top of WP page templates |
| Cron scheduler | WP-Cron or system cron | Use what's configured |
| Sessions | WP / WC customer session | Don't roll your own |

### 3.2 Mercato owns

| Domain | Mercato | Justification |
|---|---|---|
| **SaaS tenancy** | `wp_mercato_tenants`, `wp_mercato_tenant_settings`, capability JWT | WC is single-tenant; this is the whole reason Mercato exists |
| **Tenant routing** | URL handler for `/t/<tenant_slug>/*` (and subdomain mode for Pro+) | WC has no concept of branded sub-sites |
| **Providers** | `wp_mercato_providers` (formerly `wp_mercato_vendors`), approval workflow, suspension, operational status | Different lifecycle from WC customers |
| **Provider service areas** | `wp_mercato_provider_service_areas` (replaces `wp_mercato_shipping_zones` for services) | WC shipping zones model physical parcels, not geographic service radii |
| **Provider verification (KYC + licensing + insurance)** | `wp_mercato_kyc_records` extended with license/insurance fields | WC has no concept |
| **Service templates** | `wp_mercato_service_templates` (the "things the tenant offers") | More than a SKU — includes scope, default duration, required provider qualifications |
| **Service requests** | `wp_mercato_service_requests` — buyer-posted job specs | WC has no analog |
| **Bids / auctions** | `wp_mercato_bids` — providers bid on a request | Core of the services marketplace |
| **Job awards** | `wp_mercato_jobs` — awarded job linking buyer ↔ provider ↔ WC order | The "this is happening" record |
| **Job lifecycle** | Status machine: awarded → scheduled → in_progress → completed → reviewed → paid | Services-specific |
| **Dispatch / on-site events** | `wp_mercato_job_events` (provider arrived, started, blocker, finished) | Operational, not commercial |
| **Commission rules + ledger** | `wp_mercato_commission_rules`, `wp_mercato_commissions` | WC has no marketplace commission |
| **Payout ledger + Stripe Connect orchestration** | `wp_mercato_payouts`, `wp_mercato_payout_batches`, Connect Destination Charges or Transfers | WC doesn't disburse to providers |
| **Tenant feature flags + branding** | `wp_mercato_tenant_settings`, capability JWT | Multi-tenant SaaS need |
| **Tenant provisioning** | Control Plane API + WP multisite blog creation | SaaS lifecycle |
| **Marketplace audit log** | `wp_mercato_audit_log` | WC has no immutable audit |
| **Event outbox** | `wp_mercato_event_outbox` | Cross-service messaging |
| **Idempotency store** | `wp_mercato_idempotency` | API hygiene |
| **Provider, Buyer, Tenant Admin SPAs** | `apps/vendor-spa/`, `apps/admin-spa/`, future `apps/buyer-portal/` | Modern UX on top of WP |

### 3.3 Adapters bridge them

Adapters are thin wrappers that connect Mercato's services world to WooCommerce's commerce world. Each is a small module:

| Adapter | Role |
|---|---|
| **JobToOrder adapter** | When a bid is awarded, mint a WC order for the buyer to pay. Set line items to reference the job + provider. |
| **OrderToJob adapter** | When `woocommerce_payment_complete` fires for a job-backed order, transition the job to `paid` and trigger provider notification. |
| **RefundToJob adapter** | When `woocommerce_refund_created` fires, reverse commission + adjust provider payout obligation. |
| **SubscriptionToRecurringService adapter** | For recurring services (weekly cleaning), wrap WC Subscriptions to manage cadence; Mercato schedules the job, WC bills it. |
| **CouponContext adapter** | Pass tenant/provider context into WC coupon validation hooks. |
| **TaxContext adapter** | Pass service location → tax engine via WC's `woocommerce_cart_calculate_fees` flow; consume back. |
| **StripeConnect adapter** | Owns ONLY the Connect Account + Transfer side (payouts to providers). Initial charge is the WC Stripe gateway's job. |

---

## 4. The Hook Adapter — the only integration point with WooCommerce

There is exactly **one** way Mercato touches WooCommerce internals: through `Mercato\Core\WooCommerce\HookAdapter` and the canonical hook map in `docs_v2/04_fsd/FSD.md` §3.6 + `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` §4.

Canonical hook list — **subscribing to any WC hook outside this list requires a PR labeled `contract-change`**:

| WC hook | Mercato adapter | What we do |
|---|---|---|
| `plugins_loaded` p=1 | `Core\Bootstrap` | Boot the suite |
| `init` p=5 | `Core\Capabilities\Register` | Register Mercato capabilities |
| `rest_api_init` | `Core\REST\Router` | Register `/mercato/v1/*` |
| `woocommerce_init` | (refuse if HPOS off) | Sanity check |
| `woocommerce_cart_calculate_fees` | `Jobs\Cart\FeeCalculator` | Read-only cart preview (commission display) |
| `woocommerce_checkout_create_order` | `Jobs\Checkout\Contextualizer` | Attach `mercato_job_id` + `tenant_id` line-item meta |
| `woocommerce_new_order` | `Jobs\OrderToJobLinker` | Link the new WC order to the Mercato job |
| `woocommerce_order_status_changed` | `Jobs\Status\Synchronizer` | Reflect into job state |
| `woocommerce_payment_complete` | `Jobs\PaidConfirmer` | Trigger commission accrual + provider notification |
| `woocommerce_refund_created` | `Jobs\Refunds\Reverser` | Reverse commission |
| `woocommerce_order_refunded` | `Jobs\Refunds\PostRefundActions` | Post-refund side effects |
| `save_post_product` | `ServiceTemplates\ShadowGuard` | Block direct WC admin edits of Mercato-owned service templates |
| `template_redirect` | `Tenancy\TenantRouter` + `Storefront\ProviderPageRouter` | Resolve `/t/<tenant>/*` and `/store/<provider>/*` |
| `woocommerce_email_classes` | `Notifications\WCEmailOverride` | Replace WC emails with services-aware Mercato templates |
| `woocommerce_thankyou` | `Notifications\PaymentReceipt` | Show "your provider has been notified" |
| `woocommerce_subscription_status_updated` (if WC Subs installed) | `Subscriptions\RecurringServiceBridge` | Cadence sync |
| `woocommerce_coupon_is_valid` | `Coupons\TenantScopeFilter` | Restrict coupon to tenant context |

Anything else = PR + architectural review.

---

## 5. Forbidden patterns — Codex, verify your code does NONE of these

Group by category. Reword from Rev 1 to reflect the services-marketplace framing.

### 5.1 Cart / checkout / commerce

❌ A `Mercato\Cart\*` namespace holding line items
❌ A `Mercato\Checkout\*` class mutating payment state
❌ Calling `wc_create_order()` from Mercato outside the explicit `JobToOrder` adapter
❌ Implementing `process_payment()` anywhere in Mercato — that's WC + gateway plugin
❌ A Mercato class extending `WC_Payment_Gateway`
❌ A Mercato coupon engine — use WC coupons + a `CouponContext` adapter

### 5.2 Subscriptions

❌ A custom subscription cadence engine — use WC Subscriptions when recurring services exist
❌ A custom dunning loop — WC + gateway handle it

### 5.3 Tax / Shipping

❌ A `Mercato\Tax\Calculator` reimplementing jurisdiction lookup
❌ A Mercato shipping-rate calculator (services don't ship — replace mental model with "service area + travel-time fee," handled via WC fees and a Mercato `ServiceArea` evaluator)

### 5.4 Service templates (was: products)

❌ A `Mercato\Product` namespace that doesn't shadow-project to WC (where a WC product backs the buyer-facing PDP)
❌ Storing prices, scope, or visibility only in `wp_mercato_service_templates` — WC needs to see them to render checkout

### 5.5 Jobs (was: orders)

❌ A `Mercato\Order` aggregate competing with `WC_Order` for the buyer-facing payment record. Mercato jobs *reference* WC orders; they don't replace them.
❌ A Mercato "buyer billing/shipping address" table — snapshot from WC, never duplicate live

### 5.6 Customers / Users

❌ A `wp_mercato_customers` table — use `wp_users` + `buyer_user_id` references
❌ A custom buyer login/auth flow — WP does login; SPAs mint JWTs after WP/WC auth

### 5.7 Admin / sessions / nonces / cron

❌ A custom session manager
❌ A custom nonce implementation — use `wp_create_nonce()` / REST `_wpnonce`
❌ A custom cron scheduler — use WP-Cron or system cron
❌ A `Mercato\Admin\MenuRegistrar` that bypasses WP's menu API
❌ Reimplementing `wp_options` / `wp_transient` semantics

---

## 6. NOT-forbidden — these ARE Mercato's job, please build them

The Codex feedback noted "too many commodity WC areas drifted into custom Mercato implementation." That was the past mistake. The flip side of that mistake is **building the right things,** which we have not built enough of yet:

✅ **Tenant routing.** `/t/<tenant>/*` resolution + tenant-scoped query injection. Subdomain mode for Pro+ tiers.
✅ **Provider lifecycle.** Registration → approval → KYC → operational-status transitions → suspension. Different from WC vendor (because WC doesn't have a vendor concept).
✅ **Service templates.** Tenant-scoped catalog of bookable services, with required-qualification flags.
✅ **Service request board.** Buyers post requests; data model captures scope, urgency, location, attachments, budget.
✅ **Provider bidding / auction.** Multiple providers respond with price + ETA; tenant policy decides award rules (auto-award lowest, manual select, AI-recommend).
✅ **Award + WC order mint.** Once awarded, mint the WC order with line items and `mercato_job_id` meta.
✅ **Job lifecycle state machine.** awarded → scheduled → en_route → on_site → in_progress → completed → reviewed → paid.
✅ **Provider operations.** Operational status (`available` / `busy` / `offline` / `vacation`), service area updates, calendar, availability windows.
✅ **Commission rules engine.** Per tenant, per category, per provider tier. Run at award time + reconciled at paid time.
✅ **Payout orchestration.** Stripe Connect Transfers (NOT the initial charge), batch scheduling, failed-payout retry, reconciliation against Stripe Treasury.
✅ **Tenant provisioning.** Control Plane → spin up a new tenant in <60s (pooled) or <5min (silo).
✅ **Feature flags + capability JWT.** Per-tenant feature gates, refreshed every 24h.
✅ **Audit log + event outbox.** Immutable history + cross-service messaging.
✅ **Provider SPA + Tenant Admin SPA + Buyer Portal** (Phase 2).
✅ **Xusmo integration surface.** Whatever Xusmo (the parent SaaS product) needs to wire into Mercato: branding, single-sign-on into Xusmo, billing roll-up, analytics shipment.

---

## 7. Mental checklist before writing a new class

1. **Is this commerce mechanics WC already does?** (Cart, checkout, gateway, tax, coupons, subscriptions, customer record.) → Use WC, hook in.
2. **Is this services-marketplace mechanics that WC doesn't model?** (Provider, service request, bid, award, job lifecycle, dispatch, service area, commission, payout, tenant.) → Build in Mercato.
3. **Am I subscribing to a WC hook?** → Verify it's in §4 canonical list. If not: PR + architectural review.
4. **Am I writing into `wp_postmeta` for marketplace-domain data?** → Stop. Use `wp_mercato_*`.
5. **Am I duplicating live data WC has?** → Snapshot at event time is fine; live duplicate is not.

---

## 8. Decision tree

```
Need a feature?
│
├── Commerce mechanic (cart, checkout, gateway, tax, coupon, subscription, customer)?
│   └── Use WooCommerce or a mature WC plugin. Mercato hooks into it.
│
├── Services-marketplace primitive (provider, service template, request, bid, award, job, dispatch, service area)?
│   └── Build it in Mercato. Use wp_mercato_* tables. Emit events via the outbox.
│
├── SaaS-tenancy primitive (tenant, routing, branding, feature flag, billing, provisioning, control plane)?
│   └── Build it in Mercato. mercato-enterprise + Control Plane.
│
├── Operational primitive (audit, outbox, idempotency, RBAC, migrations)?
│   └── Build it in mercato-core.
│
└── Bridge between Mercato-domain and WC-domain (jobs ↔ WC orders, refunds ↔ commissions, subscriptions ↔ recurring services)?
    └── Build it as an adapter module (small, single-responsibility, named *Adapter or *Bridge).
```

If still unclear: read `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` and this directive together, then ask.

---

## 9. Self-audit Codex should run before each commit

Drift detection greps. Run before every commit:

```bash
# 1. Mercato code calling WC mutation APIs from outside the explicit adapter
grep -rn "wc_create_order\|wc_update_order\|wc_add_to_cart\|process_payment" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/ \
  | grep -v "modules/mercato-stripe-connect\|modules/mercato-job-to-order-adapter"

# 2. Mercato classes that look like WC competitors
grep -rEn "class\s+(Mercato\\\\Cart|Mercato\\\\Checkout|Mercato\\\\PaymentIntent|Mercato\\\\Customer|Mercato\\\\PaymentGateway|Mercato\\\\Tax\\\\Calculator|Mercato\\\\Coupon\\\\Engine)" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/

# 3. Direct postmeta writes for marketplace-domain data
grep -rn "update_post_meta\|add_post_meta" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/ \
  | grep -v "_mercato_vendor_id\|_mercato_provider_id\|_mercato_job_id\|_mercato_tenant_id"

# 4. Hook subscriptions outside the canonical list
grep -rEn "add_action\s*\(\s*['\"]woocommerce_" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/
# Cross-check each line against §4 canonical hooks.

# 5. Custom session / nonce systems
grep -rEn "class\s+\w*Session\b|generateNonce|createNonce" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/

# 6. Custom subscription cadence / dunning engines
grep -rEn "class\s+\w*(Subscription|Dunning)\w*Engine\b" \
  apps/wordpress/wp-content/plugins/mercato-suite/modules/
```

Any match outside its allowed exception is **drift** — file an issue tagged `wc-overlap` and remove that code in your next PR.

---

## 10. Past drift — what to roll back

Per Codex's own acknowledgement, the original scaffold "let too many commodity WooCommerce areas drift into custom Mercato implementation." The cleanup direction:

1. **Audit every module under `modules/` for whether its purpose is commerce-mechanic or marketplace/SaaS.**
2. **For commerce-mechanic modules that duplicate WC:** delete the duplication; replace with a hook subscription + a thin adapter where translation is needed.
3. **For marketplace/SaaS modules where the *naming* drifted (e.g. "vendor" everywhere):** plan a rename pass to services vocabulary (provider, service template, job). Do this in one coordinated PR per concept rather than touching every file twice.
4. **For the `mercato-orders` module specifically:** rename to `mercato-jobs` in a Phase-2 commit. The module continues to hold what's currently called sub-orders; it just acknowledges that "sub-order" is a degenerate case of the more general "job awarded to one provider after bidding."

Don't do this all at once. Plan a renaming PR, get sign-off, then execute.

---

## 11. What "good" looks like

A good Mercato module:
- Encapsulates a **services-marketplace, SaaS-tenancy, or operational** primitive
- Adds `wp_mercato_*` tables
- Subscribes to 1–3 WC hooks from the canonical list (often zero)
- Emits Mercato events via the outbox
- Exposes REST routes under `/wp-json/mercato/v1/`
- Never touches `WC_Cart`, `WC_Payment_Gateway`, `WC_Tax`, or `WC_Shipping_Zone` except read-only
- Has zero references to "checkout," "payment processing," "session management" in its class names

A good adapter module:
- Sits at the seam between Mercato and WC
- Single responsibility (e.g. "turn an awarded job into a WC order")
- ≤300 LOC
- Named `*Adapter`, `*Bridge`, or `*Linker`

A good `Provider::register()`:

```php
$this->container->bind(JobAwarder::class, fn($c) => new JobAwarder(
    $c->get(\Mercato\Core\Events\Outbox::class),
    $c->get(BidRepository::class),
));
```

Not:

```php
class MercatoCart extends WC_Cart { ... }                     // ❌ NO — that's WC's job
class MercatoServicePaymentIntent { ... }                     // ❌ NO — WC + Stripe gateway
class MercatoProviderSessionManager extends WP_Session { ... } // ❌ NO — WP owns sessions
```

---

## 12. Cross-references

| Topic | Read |
|---|---|
| WC integration boundary | `docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` |
| Hook map (canonical) | `docs_v2/04_fsd/FSD.md` §3.6 |
| Plugin packaging (one suite, not 27 plugins) | `docs_v2/14_packaging/Plugin_Packaging.md` |
| MVP scope | `docs_v2/00_mvp_cut/MVP_Cut.md` |
| Architectural non-negotiables | `docs_v2/01_architecture/Blueprint.md` §16 |
| Forbidden patterns (legacy) | `HANDOFF_FROM_CLAUDE.md` §4.3 |

**Doc-debt note:** docs_v2 was originally drafted with a physical-goods marketplace mindset (vendors, products, sub-orders). The services-marketplace vocabulary in this directive is the **correct** model. A coordinated doc-update pass will reconcile docs to this directive in a follow-up; until then, treat this directive as authoritative for code decisions.

---

## 13. Final note

Mercato exists because WooCommerce, on its own, cannot run a multi-tenant SaaS services marketplace. WooCommerce can run a single store excellently. Mercato is the layer that turns one WooCommerce install into the engine behind many branded services marketplaces.

Mercato's value is everything **above** the commerce mechanic — tenancy, providers, requests, bids, jobs, operations, payouts, SaaS control. Mercato's discipline is to **let WooCommerce keep the commerce mechanic intact** and to hook in cleanly.

If you catch yourself rebuilding something WooCommerce already does, delete that code and add a hook subscription instead. If you catch a gap in the services-marketplace layer, that's where Mercato code goes.

— Project owner, with thanks to Codex for the framing correction
