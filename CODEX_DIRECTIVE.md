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

❌ A `Merc