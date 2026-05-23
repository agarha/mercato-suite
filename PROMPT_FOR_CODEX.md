# Prompt for Codex (paste this at the start of your next session)

> Copy everything below the `---` line and paste it into Codex as the first message of your next session. Save it as a project instruction if your Codex environment supports that.

---

**Project context — read carefully before writing any code.**

You are working on **Mercato** — a **multi-tenant SaaS services-marketplace platform** that powers many Xusmo-branded marketplaces (Gigsii is the flagship tenant). The implementation repo is `agarha/mercato-suite`. The documentation suite is `agarha/Mercato` (folder `docs_v2/`).

**Before writing or modifying any code in this session, re-read `CODEX_DIRECTIVE.md` (Rev 2) at the root of `agarha/mercato-suite`.** It supersedes Rev 1. If a code pattern contradicts that directive, the directive wins.

---

## The core rule

Mercato is **NOT** a physical-goods marketplace. It is **NOT** a WooCommerce replacement. It is the **SaaS services-marketplace operations layer ON TOP OF WooCommerce**.

The job split is sharp:

- **WooCommerce + selected WC plugins own:** cart, checkout, payment gateway UI, payment processing, tax engine, coupons, subscription mechanics, customer account, admin shell. **Stop reimplementing any of this.**
- **Mercato owns:** SaaS tenancy + tenant routing (`/t/<tenant>`), providers (formerly called "vendors"), service templates (formerly "products"), service requests, provider bids/auctions, awarded jobs, job lifecycle, dispatch & operations, commissions, payouts, audit/outbox, Xusmo integration surface.
- **Adapters bridge them:** `JobToOrder`, `OrderToJob`, `RefundToJob`, `SubscriptionToRecurringService`, `CouponContext`, `TaxContext`, `StripeConnect` (payouts only — never the initial charge).

## Vocabulary translation while reading existing docs

Existing `docs_v2/` was drafted with goods-marketplace vocabulary. Translate mentally:

| Old / doc term | What it actually is |
|---|---|
| Vendor | **Provider** (someone who sells services) |
| Product | **Service template** (a bookable service offering) |
| Order | **Job** (backed by a WC order for the payment record) |
| Sub-order | Mostly N/A for services — usually 1 provider per job |
| Shipping zones | **Provider service areas** (geographic radii) |
| Shipping tracking | **Job dispatch / status updates** |
| Multi-vendor cart | **Service request → provider bids → award → single payment** |
| KYC for vendors | KYC + **provider verification** (identity + insurance + licensing) |

Storage table names like `wp_mercato_suborders` stay — only the *concept* renames. Future PR will rename `vendor → provider` etc. across code; don't do this unilaterally.

## What you must NOT do (commerce mechanics WC already owns)

❌ Build a `Mercato\Cart\*` namespace, a `Mercato\Checkout\*` class, or a parallel payment-intent system.
❌ Call `wc_create_order()` outside the explicit `JobToOrder` adapter.
❌ Implement `process_payment()` in any Mercato module.
❌ Extend `WC_Payment_Gateway` from a Mercato module.
❌ Build a custom coupon engine — use WC coupons + a `CouponContext` adapter.
❌ Build a custom tax calculator — use WC + TaxJar/Avalara integration + a `TaxContext` adapter.
❌ Build a parallel subscription cadence/dunning engine — use WC Subscriptions + a `SubscriptionToRecurringService` adapter.
❌ Build a `wp_mercato_customers` table — use `wp_users` + `buyer_user_id` references.
❌ Build custom session / nonce / cron systems — WP owns them.
❌ Write into `wp_postmeta` for marketplace-domain data — use `wp_mercato_*` tables.

## What you SHOULD build (the services-marketplace primitives that are still missing)

✅ **Tenant routing.** `/t/<tenant_slug>/*` resolver + tenant-scoped query injection. Subdomain mode for Pro+ tiers.
✅ **Provider model.** Registration → approval → KYC → operational-status transitions (`available` / `busy` / `offline` / `vacation`) → suspension.
✅ **Provider service areas.** Geographic radii / coverage polygons; replaces shipping-zone thinking.
✅ **Service templates.** Tenant-scoped catalog of bookable services with required-qualification flags.
✅ **Service request board.** Buyer-posted job specs: scope, urgency, location, attachments, budget.
✅ **Provider bidding / auction engine.** Multiple providers respond with price + ETA; tenant policy decides award rules (auto-lowest, manual, AI-recommend).
✅ **Award flow.** Once awarded, mint a WC order via `JobToOrder` adapter with `mercato_job_id` line-item meta.
✅ **Job lifecycle state machine.** `awarded → scheduled → en_route → on_site → in_progress → completed → reviewed → paid`.
✅ **Dispatch / on-site events.** Provider arrived, started, blocker, finished.
✅ **Commission rules engine.** Per tenant, per category, per provider tier.
✅ **Payout orchestration.** Stripe Connect Transfers (NOT initial charge), batch scheduling, failed-payout retry, reconciliation against Stripe Treasury.
✅ **Tenant provisioning.** Control Plane → spin up tenant in <60s (pooled) / <5min (silo).
✅ **Feature flags + capability JWT.** Per-tenant feature gates, refreshed every 24h.
✅ **Audit log + event outbox.** Immutable history + cross-service messaging.
✅ **Xusmo integration surface.** Branding, SSO into Xusmo, billing roll-up, analytics shipment.

## Your hook surface is restricted

You may subscribe to ONLY the canonical WC hooks listed in `CODEX_DIRECTIVE.md` §4 (and `docs_v2/04_fsd/FSD.md` §3.6). Subscribing to any other WC hook = open a PR labeled `contract-change` first.

## Your immediate tasks for this session

1. **Run the 6 drift-detection greps** in `CODEX_DIRECTIVE.md` §9. Any match outside its allowed exception = drift. File issues tagged `wc-overlap`.

2. **Audit `modules/` for past drift.** Identify any module / class that duplicates a commerce mechanic WC already provides (cart helpers, payment-intent equivalents, custom session/nonce, custom coupon logic, custom tax math, custom subscription cadence). Plan removal in a follow-up PR.

3. **Build out the missing services-marketplace primitives** from the "What you SHOULD build" list above. Suggested order:
   - tenant routing (`/t/<slug>` resolver in `mercato-enterprise`)
   - provider model lifecycle additions (operational status, service areas)
   - service templates
   - service request board
   - bid/auction engine
   - `JobToOrder` adapter
   - job lifecycle state machine
   - `OrderToJob` adapter (consumes WC payment-complete → updates job state)

4. **Do NOT start a vendor→provider / product→service-template / sub-order→job rename pass without alignment.** That needs to be a coordinated PR per concept. Mention the rename in your planning notes; do the actual rename in a separate PR after sign-off.

5. **Never subscribe to a WC hook outside the canonical §4 list.** If you need a new one, open a `contract-change` PR first describing the use case.

## How to verify your work before each commit

```bash
# Manifest contracts
python3 tools/validate-manifests.py    # must print "All manifests valid."

# Module boot ordering
php tests/phpunit/module-registry-smoke.php

# Static analysis
composer analyse

# Unit tests
vendor/bin/phpunit --testsuite unit

# Drift detection (see CODEX_DIRECTIVE.md §9)
# Run all 6 greps; any match needs justification or removal.
```

## What "good" looks like

A good Mercato module is one of:
- A **services-marketplace** primitive (provider, service template, bid, job).
- A **SaaS-tenancy** primitive (tenant, routing, feature flag, provisioning).
- An **operational** primitive (audit, outbox, idempotency, RBAC, migrations).
- A **thin adapter** at the WC↔Mercato seam (`*Adapter` / `*Bridge` / `*Linker`, ≤300 LOC).

A good module subscribes to 1–3 canonical WC hooks (often zero) and emits events via the outbox.

A bad module looks like `class MercatoCart extends WC_Cart` or `class MercatoPaymentGateway extends WC_Payment_Gateway`. If you see code like that, delete it and replace with a hook subscription.

## Documents to know

| Topic | Read |
|---|---|
| **The directive itself (READ FIRST)** | `CODEX_DIRECTIVE.md` |
| Previous handoff context | `HANDOFF_FROM_CLAUDE.md` |
| Review of M0 work | `REVIEW.md` |
| Plugin packaging strategy | `../docs_v2/14_packaging/Plugin_Packaging.md` |
| WC integration boundary | `../docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md` |
| MVP scope | `../docs_v2/00_mvp_cut/MVP_Cut.md` |
| Architectural non-negotiables | `../docs_v2/01_architecture/Blueprint.md` §16 |

## Confirmation

Before you write any code in this session, reply to confirm:

1. You have read `CODEX_DIRECTIVE.md` Rev 2.
2. You understand Mercato is a **services-marketplace SaaS layer on top of WooCommerce**, not a WC replacement and not a goods marketplace.
3. You will not implement commerce mechanics WC already owns.
4. You will not subscribe to any WC hook outside the canonical §4 list without a `contract-change` PR.
5. You will run the §9 drift greps before each commit.

Then begin your task.

---

**End of prompt. Above this line is what you paste into Codex.**

---

## Notes for the human (not for Codex)

- This prompt is intentionally repetitive — Codex sessions don't retain state across runs, so the rules are stated explicitly even if they're also in `CODEX_DIRECTIVE.md`.
- If you want to shorten it, the absolute minimum is the **core rule** + **what you must NOT do** + **what you SHOULD build** + the link to `CODEX_DIRECTIVE.md`. Everything else is reinforcement.
- The `Confirmation` block at the bottom is a forcing function — making Codex acknowledge the rules in its first response gives you a clean abort point if it didn't read the directive.
- If Codex pushes back on any rule (e.g. "this would be simpler if I just built a small cart helper"), the answer is **no** — that's exactly the drift this directive exists to prevent. Direct it to use the appropriate adapter or hook instead.
