# Mercato Suite

Mercato Suite is the implementation repository for the Mercato services-marketplace SaaS platform described in `../docs_v2`.

The documentation suite remains the source of truth. This repository contains the runnable WordPress/WooCommerce plugin bundle, local Docker environment, supporting services, test suites, and production infrastructure definitions.

Mercato is not a WooCommerce replacement. WooCommerce and selected WooCommerce plugins own cart, checkout, payment gateway UI, tax, coupons, subscriptions, customer accounts, and the admin shell. Mercato owns SaaS tenancy, providers, service templates, service requests, bids/auctions, awards, jobs, dispatch, commissions, payouts, audit/outbox, and the Xusmo integration surface. See [CODEX_DIRECTIVE.md](CODEX_DIRECTIVE.md) and [docs/architecture/woocommerce-boundary.md](docs/architecture/woocommerce-boundary.md).

## MVP Scope

The first implementation target follows `docs_v2/00_mvp_cut/MVP_Cut.md`.

MVP modules:

- `mercato-core`
- `mercato-vendors`
- `mercato-products`
- `mercato-orders`
- `mercato-commissions`
- `mercato-payouts`
- `mercato-messaging`
- `mercato-notifications`
- `mercato-kyc-kyb`
- `mercato-enterprise`
- `mercato-stripe-connect`
- `mercato-sendgrid`
- `mercato-aws-s3`

Deferred modules are represented from the beginning with manifests, folders, and coverage rows. The local Gigsii tenant can enable all feature flags for demonstration, but production tenants should enable features by plan, readiness, and integration status.

## Local Development

Copy `.env.example` to `.env`, then run:

```powershell
docker compose up --build
```

Local services:

- WordPress: http://localhost:8092
- Mailpit: http://localhost:8026
- MinIO: http://localhost:9003
- Kafka: localhost:9093
- MySQL: localhost:3316
- Redis: localhost:6382

Run the full local verification cycle:

```powershell
$env:MERCATO_RUN_E2E='1'
powershell -ExecutionPolicy Bypass -File tools\run-tests.ps1
```

Run deployment preflight checks against the local stack:

```powershell
powershell -ExecutionPolicy Bypass -File tools\deploy-preflight.ps1
```

Operational health endpoints:

- Public liveness: `GET /?rest_route=/mercato/v1/health/live`
- Secured readiness: `GET /?rest_route=/mercato/v1/health/readiness`

## Gigsii Tenant

Gigsii is the flagship Mercato tenant and reference implementation. It is not a fork.

Local storefront:

```text
http://localhost:8092/t/gigsii
```

Refresh local Gigsii tenant data:

```powershell
$env:MERCATO_E2E_BASE_URL = "http://localhost:8092"
powershell -ExecutionPolicy Bypass -File tools\seed-gigsii-tenant.ps1
powershell -ExecutionPolicy Bypass -File tools\seed-gigsii-taskrabbit-taxonomy.ps1
```

Tenant documentation:

- [docs/tenants/gigsii.md](docs/tenants/gigsii.md)
- [docs/GIGSII_REQUIREMENTS_STATUS.md](docs/GIGSII_REQUIREMENTS_STATUS.md)
- [docs/implementation/CURRENT_IMPLEMENTATION_STATUS.md](docs/implementation/CURRENT_IMPLEMENTATION_STATUS.md)

## Repository Layout

```text
apps/wordpress/wp-content/plugins/mercato-suite/  WordPress plugin bundle
apps/admin-spa/                                   Tenant admin SPA
apps/vendor-spa/                                  Vendor dashboard SPA
services/outbox-relay/                            Go event outbox relay
services/notification-worker/                     Async notification worker
packages/                                         SDKs, OpenAPI, event schemas
database/                                         Migrations, seeds, schemas
docker/                                           Local Docker assets
infrastructure/                                   IaC and deployment manifests
tests/                                            Integration, E2E, perf fixtures
tools/                                            Build and automation scripts
```

## Current Status

MVP/P1 development is active on `codex/e2e-developed`. The latest implementation documentation is in [docs/implementation/CURRENT_IMPLEMENTATION_STATUS.md](docs/implementation/CURRENT_IMPLEMENTATION_STATUS.md).

The local stack includes WordPress/WooCommerce, MySQL, Redis, Kafka, Mailpit, MinIO, and the outbox relay. Recent Gigsii work includes tenant routing, tenant storefront, categories/subcategories, provider offerings, geolocation, service request posting, provider bids/auctions, award-to-job flow, booking/job flow, lead/estimate/job flow, referral redemption, and full local feature flag enablement.
