# Current Implementation Status

Last updated: 2026-05-23.

Branch: `codex/e2e-developed`

GitHub remote: `origin https://github.com/agarha/mercato-suite.git`

## Push Status

The latest implementation work has been pushed to GitHub on `codex/e2e-developed`.

Recent pushed commits:

- `5bd46c0 Enable all Gigsii tenant capabilities`
- `6995419 Add Gigsii service request bidding`
- `714f3ec Surface Gigsii taxonomy and service ops`
- `a089b84 Add Gigsii service operations coverage`
- `4cd2df9 Upgrade tenant storefront marketing design`

## Platform Shape

Mercato is a WordPress/WooCommerce plugin bundle. WooCommerce remains the commerce engine. Mercato adds the services-marketplace SaaS layer:

- pooled tenant routing, including `/t/{tenant_slug}`
- tenant feature flags, settings, integrations, and storefront configuration
- provider registration, approval, rejection, suspension, KYC/onboarding checklist
- tenant-scoped service catalog and category/subcategory taxonomy
- many providers offering the same service through `mercato_vendor_service_offerings`
- provider geolocation and service areas
- service request board
- provider bids and open auction/sealed bid support
- award flow that creates a Mercato job record
- booking, job, dispatch assignment, status transitions, lead, estimate, referral workflows
- audit log and event outbox
- local integrations for Mailpit/SendGrid-style email and MinIO/S3 media
- Stripe Connect and payout/reconciliation scaffolding

The code still uses legacy table/module names such as `vendor`, `product`, and `suborder`. Per [CODEX_DIRECTIVE.md](../../CODEX_DIRECTIVE.md), these map conceptually to provider, service template, and job/payment record concepts. Do not run broad renames without a coordinated PR.

## Gigsii Work Completed

Gigsii is implemented as a Mercato tenant, not a fork.

Completed for the local tenant:

- tenant slug `gigsii`
- storefront path `http://localhost:8092/t/gigsii`
- tenant branding and marketing copy
- Taskrabbit-style service taxonomy seed with 19 parent categories and 195 subcategories
- Gigsii providers, service templates, provider offerings, locations, and service areas
- all Mercato and Gigsii demo feature flags enabled
- local/test integrations configured for payment, email, storage, tax/shipping/search/AI placeholders
- service request and provider bid/auction workflow
- bid acceptance that awards the request and creates a job
- booking-to-job workflow
- lead-to-estimate-to-job workflow
- referral accrual and redemption
- storefront sections for categories, services, providers, request board, all-enabled feature flags, service operations, and recent jobs

## Local Verification Evidence

Recent verification passed:

- PHP syntax checks for changed modules
- `python tools/validate-manifests.py`: all 30 module manifests valid
- PHPUnit in Docker PHP: 28 tests, 357 assertions
- `npm test`: admin asset validation passed
- HTTP smoke for `http://localhost:8092/t/gigsii`: all-enabled feature section present, AI/subscriptions/PayPal flags visible, no disabled feature pills
- REST smoke: request posted, two provider bids created, one bid accepted, request moved to `awarded`, job created for winning provider
- REST smoke: referral redemption returned `redeemed`

## Known Remaining Gaps

These are not complete production-grade features yet:

- full role-specific RBAC matrix from the Gigsii RBAC catalog
- complete public/client/provider/org/admin SPA-level UX
- `JobToOrder`, `OrderToJob`, `RefundToJob`, `CouponContext`, `TaxContext`, and subscription adapters as formal small modules
- final WooCommerce plugin compatibility matrix and installer automation
- DSAR export/delete/anonymization and retention jobs
- performance tests for marketplace search and job detail targets
- production credentials and real external service verification
- provider/service/job vocabulary rename pass

## Rule For Future Work

Before adding more code, read [CODEX_DIRECTIVE.md](../../CODEX_DIRECTIVE.md). Do not rebuild commerce mechanics that WooCommerce or mature WooCommerce plugins own.
