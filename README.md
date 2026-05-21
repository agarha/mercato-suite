# Mercato Suite

Mercato Suite is the implementation repository for the Mercato marketplace platform described in `../docs_v2`.

The documentation suite remains the source of truth. This repository contains the runnable WordPress/WooCommerce plugin bundle, local Docker environment, supporting services, test suites, and production infrastructure definitions.

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

Deferred modules are represented from the beginning with manifests, folders, and coverage rows. They stay disabled at runtime until their phase begins, but they are not invisible to architecture, tests, CI, or planning.

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

MVP/P1 development is active on `codex/e2e-developed`. The local stack includes WordPress/WooCommerce, MySQL, Redis, Kafka, Mailpit, MinIO, and the outbox relay. The E2E smoke cycle covers vendor onboarding, Stripe Connect test-mode, Stripe Identity KYC, S3/MinIO upload, product publishing, WooCommerce order split, PaymentIntent, partial refund, commission reversal, payout, Stripe transfer, SendGrid delivery, reconciliation, net reporting, REST security, and readiness checks.
