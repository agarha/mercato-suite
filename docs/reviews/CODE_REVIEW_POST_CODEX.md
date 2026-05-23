# Mercato Suite — Post-Codex Code Review

**Reviewer:** Claude
**Date:** 2026-05-23
**Scope reviewed:** `apps/wordpress/wp-content/plugins/mercato-suite/` at branch `codex/e2e-developed` (HEAD `5b30351`) plus the 6 adapter modules added on `claude/adapter-extraction` (HEAD `dd1a8e3`).
**Reviewed against:** `CODEX_DIRECTIVE.md` (Rev 2), `docs_v2/`, `docs/architecture/woocommerce-boundary.md`.
**Target state for next phase:** demo-quality for investor / partner pitches (per George, 2026-05-23).

---

## 1. Executive summary

The suite is in **better shape than expected for a demo-quality target**. Multi-tenancy is genuinely enforced at the SQL layer in 209 places. The directive's WC↔Mercato boundary is now formalised via the 6 named adapters that just landed. The tenant resolver, RBAC, event outbox, audit log, idempotency store, and migrations registry all exist and pass their own unit tests.

The suite is in **worse shape than the status doc claims** for go-live in three specific places: (a) the storefront is a 400-line inline-HTML method (`storefrontHtml()`) inside `mercato-core/Provider.php` — not a template, not themable, not investor-grade; (b) 15 of 30 domain modules are scaffold-only (29 LOC each — a manifest plus a stub Provider that only exposes `/modules/<slug>` introspection); (c) test coverage is concentrated on tenancy plumbing (8 unit tests) — there are no tests for vendors, products, orders, payouts, commissions, stripe-connect, kyc-kyb, messaging, sendgrid, or the new adapters.

For a **demo-quality investor pitch**, the work splits cleanly: (1) replace the inline storefront with a proper template stack, (2) audit tenancy for query leaks, (3) fill in just enough of the scaffold modules to make the demoed feature flags non-empty, (4) skip DSAR / full RBAC matrix / production scale work (correctly deferred for post-pitch). The "30 modules / all features enabled" framing is currently misleading — half of those modules respond to their introspection endpoint and do nothing else.

---

## 2. Codebase shape

| Metric | Value |
|---|---|
| Total PHP LOC (modules) | 9,162 |
| Modules registered | 30 (including the 6 new adapters) |
| Substantive modules | 15 (≥100 LOC) |
| Scaffold-only modules | 15 (= 29 LOC each — manifest + introspection stub) |
| REST routes | 99 |
| Migrations (`.sql`) | 48 |
| PHPUnit unit tests | 8 files (covers tenancy plumbing only) |
| PHPUnit integration tests | 0 |
| Playwright e2e specs | 1 (`top-30-mvp.spec.ts`) |
| k6 perf scripts | 1 (`mercato-baseline.js`) |
| Distinct event types emitted | 49 |
| WC hooks subscribed | All within canonical §4 list (no drift) |

---

## 3. Module-by-module verdict

| Module | LOC | Tier | Verdict | Notes |
|---|---:|---|---|---|
| mercato-core | 2,299 | foundation | **Substantive, with storefront drift** | 400 LOC of inline storefront HTML inside Provider.php — see §5. Container, DI, RBAC, Tenant\Resolver, Audit, Idempotency, Observability all solid. |
| mercato-service-ops | 824 | domain | **Substantive** | Bookings, leads, estimates, jobs, dispatch, referrals. The functional heart of the services-marketplace. |
| mercato-products | 723 | domain | **Substantive** | Service templates + categories. Note: contains the one allowed `_mercato_vendor_id` bridge meta to WC post meta (rename-pass debt, not drift). |
| mercato-stripe-connect | 617 | adapter | **Substantive, mostly compliant** | Account creation, transfers, webhook, payment-intent creation route. The `createPaymentIntent` endpoint deserves directive scrutiny — see §6. |
| mercato-enterprise | 609 | domain | **Substantive** | Tenants, domains, integrations, settings, feature flags. |
| mercato-orders | 556 | domain | **Substantive** | Suborders, offerings, refunds. Houses the bid/auction → award → job pipeline. |
| mercato-payouts | 386 | domain | **Substantive** | Batch scheduling, reconciliation, trial balance. |
| mercato-vendors | 314 | domain | **Substantive** | Providers (vendor vocabulary). Approve / suspend / status. |
| mercato-commissions | 255 | domain | **Substantive** | Append-only commission ledger. |
| mercato-aws-s3 | 250 | adapter | **Substantive** | Presigned uploads, media scanning hook. |
| mercato-kyc-kyb | 246 | domain | **Substantive** | Stripe-backed KYC, webhook. |
| mercato-reports | 241 | domain | **Substantive** | Dashboard, exports, vendor reports. |
| mercato-sendgrid | 199 | adapter | **Substantive** | Event ingestion (delivered, bounced). |
| **mercato-job-to-order-adapter** | 172 | adapter | **NEW — clean** | The only allowed caller of `wc_create_order()`. |
| **mercato-subscription-bridge-adapter** | 168 | adapter | **NEW — clean** | WC Subs → recurring service jobs. |
| **mercato-order-to-job-adapter** | 161 | adapter | **NEW — clean** | `payment_complete` etc. → Mercato events. |
| mercato-messaging | 160 | domain | **Substantive** | Threads + replies. |
| **mercato-refund-to-job-adapter** | 157 | adapter | **NEW — clean** | Refund → commission reversal events. |
| **mercato-tax-context-adapter** | 147 | adapter | **NEW — clean** | Service location → WC tax flow. |
| **mercato-coupon-context-adapter** | 139 | adapter | **NEW — clean** | Tenant/provider coupon scope filter. |
| mercato-notifications | 104 | domain | **Substantive** | Email send routing. |
| mercato-ai-copilot | 29 | domain | **Scaffold only** | Manifest + `/modules/mercato-ai-copilot` introspection endpoint. No real code. |
| mercato-avalara | 29 | adapter | **Scaffold only** | Same. Feature flag is on; the integration is empty. |
| mercato-collaboration | 29 | domain | **Scaffold only** | Same. |
| mercato-disputes | 29 | domain | **Scaffold only** | Same. **Demo risk** — disputes are an investor-relevant flow. |
| mercato-fraud-risk | 29 | domain | **Scaffold only** | Same. |
| mercato-migration | 29 | foundation | **Scaffold only** | Tenant migration tool; just a stub. |
| mercato-paypal-marketplace | 29 | adapter | **Scaffold only** | Same. |
| mercato-postmark | 29 | adapter | **Scaffold only** | Same. |
| mercato-reviews | 29 | domain | **Scaffold only** | Same. **Demo risk** — ratings are visible on storefront. |
| mercato-search | 29 | domain | **Scaffold only** | Same. **Demo risk** — search is a primary marketplace UX. |
| mercato-shippo | 29 | adapter | **Scaffold only** | Same. |
| mercato-subscriptions | 29 | domain | **Scaffold only** | Same (the bridge adapter handles WC Subs; this module is separate). |
| mercato-tax-engine | 29 | domain | **Scaffold only** | Same. |
| mercato-taxjar | 29 | adapter | **Scaffold only** | Same. |
| mercato-twilio | 29 | adapter | **Scaffold only** | Same. |

**Demo-risk scaffold modules** (visible in the storefront / will look broken if clicked): `reviews`, `search`, `disputes`, possibly `ai-copilot` (the storefront mentions "AI assistance"). These need minimum-viable implementations OR their feature flags need to be off for the demo tenant.

---

## 4. Multi-tenancy assessment

**Architecture:** The `Mercato\Core\Tenant\Resolver` resolves the current tenant from four sources in priority order:

1. `X-Mercato-Tenant-Id` header (only when `MERCATO_TRUST_TENANT_HEADER=1`)
2. Path prefix: `/t/<slug>/...`
3. Host: custom domain mapping → tenant; otherwise `<slug>.<base-domain>` subdomain extraction
4. WordPress multisite `blog_id`
5. Fallback to tenant `1`

This is a well-designed contract. Tested by `tests/phpunit/unit/TenantResolverTest.php`.

**Enforcement at the data layer:** 209 distinct `WHERE tenant_id` predicates across the modules. Sampled spot-checks of `Repository.php` files in vendors, products, orders, payouts, commissions, service-ops all show consistent tenant scoping. **No leaks found in the spot-check**, but no automated drift test exists yet — Phase 2 should add a PHPUnit test that scans every `Repository::*` method for an unbound query and fails the build if one is missing tenant scope.

**Risk areas to verify in Phase 2:**

- The `renderDemoStorefront()` storefront does ~10 raw SQL queries directly in `Provider.php` instead of going through a repository. Each one filters by tenant_id but the pattern violates separation-of-concerns and is hard to test.
- REST permission callbacks (`Permissions::canRead`, `canManage`, `canWebhook`) need verification that they don't grant cross-tenant access via a global capability.
- `mercato-stripe-connect/Repository.php` writes `_mercato_vendor_id` to WC post meta — that meta is global to the wp_postmeta table; need to confirm consumers always re-check tenant context.

**Verdict:** Tenancy is **good enough for an investor demo today** with the explicit caveat that a formal audit (Phase 2) is required before any paying customer. No known cross-tenant leak.

---

## 5. UI assessment — the critical go-live issue

**Current state:** The storefront at `/t/<slug>` is rendered by a single PHP method, `Mercato\Core\Provider::storefrontHtml()`, that builds the entire page as a concatenated HTML string in PHP. It is ~400 lines long, lives inside `mercato-core/src/Provider.php`, mixes data access (10+ raw `$wpdb` queries) with markup, contains inline CSS via classes like `market-blue` that are styled inline in the same file, and is named `renderDemoStorefront` — Codex's own naming admits the state.

**What works:** Tenant branding (`brand`, `mark`, `title`, `hero_headline`, ...) is loaded from `wp_mercato_tenant_settings.settings JSON.storefront`. Approved vendors, active products, recent suborders, latest payout, and feature-flag pills all render with real data. CSP, security headers, and nocache are sent correctly. Layout is responsive in a basic way.

**What doesn't, for investor-grade demo:**

- **Not themeable.** A different tenant cannot get a different visual identity beyond color tokens — layout, type system, component shapes are hard-coded.
- **Not maintainable.** Any copy change requires editing PHP and shipping a deploy. There is no template file, no separation of concerns.
- **No real navigation.** The nav links are all anchors (`#shop`, `#vendors`, ...) within one page. There is no `/services`, `/providers/<slug>`, `/requests/new`, `/account`, `/dashboard` — those routes don't exist.
- **No interactivity.** No JS framework, no SPA, no client-side form for posting a service request from the storefront (the REST endpoint exists; there's no UI for it).
- **Empty/error states are minimal.** "No active products yet" is the only handled state; loading, partial failure, permission-denied, etc. are all unstyled.
- **Mobile is approximated, not designed.** No tested breakpoints.
- **Accessibility is not addressed.** No skip-links, no landmark roles, no aria-labels on icon-only controls.
- **No analytics, no SEO meta, no Open Graph tags.** A demo with social-share screenshots will look bad.

**Recommendation for Phase 5 (already user-confirmed: polish WP templates, not React SPAs):**

1. Extract the inline HTML into proper WordPress template parts under `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/` (`header.php`, `hero.php`, `categories.php`, `services-grid.php`, `providers-grid.php`, `request-board.php`, `footer.php`).
2. Move the data access out of `Provider::renderDemoStorefront()` and into a `Storefront\Repository` that the template parts read from.
3. Add real client-side pages for `/t/<slug>/services`, `/t/<slug>/providers`, `/t/<slug>/providers/<slug>`, `/t/<slug>/requests/new`, `/t/<slug>/account`. Each is a separate template-redirect handler.
4. Build a small design token sheet (`storefront.css`) sourced from tenant settings JSON.
5. Add Open Graph, JSON-LD, sitemap, robots.
6. Add a11y pass: WCAG 2.1 AA via the `design:accessibility-review` skill.

This is the heaviest weighted phase given the demo-quality target.

---

## 6. Directive compliance check

Ran the §5 forbidden-pattern greps against the full suite:

| Check | Result |
|---|---|
| `wc_create_order(` | **Pass.** Found only in `mercato-job-to-order-adapter` (the one allowed location). |
| `process_payment(` | **Pass.** Not found anywhere in Mercato modules. |
| `extends WC_Payment_Gateway` | **Pass.** Not found. |
| `Mercato\Cart\` namespace | **Pass.** Not found. |
| `Mercato\Checkout\` namespace | **Pass.** Not found. |
| Custom tax calculator (`TaxCalculator` etc.) | **Pass.** Not found. |
| WC hook subscriptions outside §4 canonical list | **Pass.** All `add_action('woocommerce_...')` calls match the canonical list. |

**One item to flag for follow-up, not drift:**

`mercato-stripe-connect/src/Provider.php` exposes a `POST /mercato/v1/stripe/payment-intents` route that calls `Repository::createPaymentIntent()`. The directive (§5.1) prohibits Mercato from creating payment intents *for the initial buyer charge*. Need to verify in Phase 1 follow-up whether this endpoint is used for the initial charge (a violation) or only for non-charge use-cases like provider tooling or top-ups (allowed). Reading the code suggests it's a thin wrapper — but the route's existence is a smell. Flag for §6 in the review backlog.

**One item that is allowed exception, not drift:**

`mercato-products/Repository.php` writes `_mercato_vendor_id` to WC product post meta. This is the one allowed bridge metadata key per `docs/architecture/woocommerce-boundary.md` §"Current Drift Audit". Future vocabulary rename pass will move it to provider terminology in a coordinated PR.

---

## 7. Test coverage assessment

**Existing tests (`tests/phpunit/unit/`):**

| Test | Covers |
|---|---|
| `CatalogSchemaTest.php` | Catalog table schemas |
| `ModuleRegistryTest.php` | Module loading + registry |
| `OfferingAwareOrderSplitTest.php` | Order split per provider offering |
| `RateLimiterTest.php` | Rate limiter primitive |
| `ServiceOpsModuleTest.php` | Service ops smoke |
| `TenantIntegrationsTest.php` | Per-tenant integration settings |
| `TenantResolverTest.php` | Resolver priority + fallbacks |
| `TenantStorefrontConfigTest.php` | Storefront config merge |

Codex's status doc says **28 tests, 357 assertions** — that's test *methods* across these 8 files, which checks out.

**What's not covered:**

- **My 6 new adapter modules.** Need at least smoke tests: bootstrap + hook subscription + happy path.
- **Vendors, products, orders, payouts, commissions, kyc-kyb, messaging, sendgrid, stripe-connect, notifications, reports** — no unit tests.
- **No integration tests at all.** `tests/phpunit/integration/` is empty.
- **Playwright** has one spec (`top-30-mvp.spec.ts`) — needs running to verify it still passes against current code.
- **k6** has one baseline script — not run as part of CI.

**Recommendation:** For demo-quality, add smoke tests for the 6 new adapters (Phase 1 follow-up) and ensure Playwright passes. Skip the deeper integration coverage — that's post-pitch work.

---

## 8. Prioritized fix-up backlog

Numbered by urgency for the **demo-quality go-live** target. Items marked **DEMO BLOCKER** must land before the pitch. Items marked **POST-PITCH** are deferred.

### 8.1 DEMO BLOCKERS (must land before pitch)

| # | Item | Phase | Branch |
|---|---|---|---|
| 1 | Replace inline `storefrontHtml()` with real template-parts stack | 5 | `claude/storefront-templates` |
| 2 | Add real navigation routes: `/services`, `/providers`, `/providers/<slug>`, `/requests/new`, `/account` | 5 | `claude/storefront-navigation` |
| 3 | Minimum-viable `mercato-search` implementation (filter services + providers; no need for OpenSearch in demo) | 3 | `claude/search-mvp` |
| 4 | Minimum-viable `mercato-reviews` implementation (star + comment per provider; visible on storefront) | 3 | `claude/reviews-mvp` |
| 5 | Storefront design polish: typography, spacing, color, illustration, hero | 5 | `claude/storefront-design` |
| 6 | Mobile breakpoint pass | 5 | `claude/storefront-mobile` |
| 7 | a11y pass (WCAG 2.1 AA) | 5 | `claude/storefront-a11y` |
| 8 | Add smoke tests for the 6 new adapter modules | 1 follow-up | `claude/adapter-tests` |
| 9 | Multi-tenancy drift test (PHPUnit scans repositories) | 2 | `claude/tenancy-drift-test` |
| 10 | Verify Playwright top-30-mvp.spec.ts passes against current build | 1 follow-up | (no branch; verify only) |
| 11 | Resolve Stripe `payment-intents` route question (allowed use-case or directive drift?) | 1 follow-up | `claude/stripe-pi-route-audit` |
| 12 | Disable feature flags on demo tenant for any module that is still scaffold-only AND visible in storefront | 4 | `claude/gigsii-flag-trim` |

### 8.2 NICE-TO-HAVE FOR PITCH

| # | Item | Phase | Branch |
|---|---|---|---|
| 13 | `mercato-disputes` happy-path UI (open dispute, see status) | 3 | `claude/disputes-mvp` |
| 14 | `mercato-ai-copilot` demo stub (canned-response prompt panel) — visually impressive, low backend | 3 | `claude/ai-copilot-stub` |
| 15 | OG tags, JSON-LD, sitemap on storefront | 5 | `claude/storefront-seo` |
| 16 | Provider dashboard page (jobs, earnings, payouts, KYC status) | 5 | `claude/provider-dashboard` |
| 17 | Buyer account page (booking history, messages, receipts) | 5 | `claude/buyer-account` |

### 8.3 POST-PITCH (explicitly deferred)

| # | Item | Why deferred |
|---|---|---|
| 18 | Full RBAC matrix from Gigsii catalog | Demo can use the existing capability map; full role pivot is paying-customer territory. |
| 19 | DSAR export/delete/anonymization | Compliance work; demo doesn't hold real PII. |
| 20 | Performance tests for marketplace search and job detail | Demo scale is single-digit users. |
| 21 | Provider/service/job vocabulary rename pass | Coordinated multi-PR cleanup; doesn't affect what the investor sees. |
| 22 | Production credentials and real external service verification | Demo uses local Mailpit, MinIO, Stripe test mode. |
| 23 | WC plugin compatibility matrix + installer automation | Demo runs in fixed local Docker. |
| 24 | Real `mercato-fraud-risk`, `mercato-collaboration`, `mercato-migration`, `mercato-paypal-marketplace`, `mercato-postmark`, `mercato-shippo`, `mercato-subscriptions` (the domain module, not the bridge), `mercato-tax-engine`, `mercato-taxjar`, `mercato-twilio`, `mercato-avalara` | Not visible in demo flows. Keep their feature flags off in the demo tenant. |

---

## 9. Recommended next session order

1. **Branch `claude/code-review-report`**: commit this document.
2. **Branch `claude/adapter-tests`**: smoke tests for the 6 adapters (close my own loop).
3. **Branch `claude/tenancy-drift-test`**: automated guardrail for Phase 2.
4. **Branch `claude/storefront-templates`**: extract the inline HTML (biggest single quality win).
5. **Branch `claude/search-mvp`** + **`claude/reviews-mvp`**: fill the two demo-visible scaffold modules.
6. **Branch `claude/gigsii-flag-trim`**: turn off the remaining empty modules in the demo tenant.
7. **Branch `claude/storefront-design`** + **`claude/storefront-mobile`** + **`claude/storefront-a11y`**: visual polish triplet.
8. **Final pass**: provider dashboard + buyer account if there's time before the pitch.

Each branch is a focused PR. Total work fits a typical 1–2 week sprint, with the storefront stack being the biggest single line item.

---

**End of review.**
