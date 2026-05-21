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

- WordPress: http://localhost:8080
- Mailpit: http://localhost:8025
- MinIO: http://localhost:9001
- Kafka: localhost:9092
- MySQL: localhost:3306
- Redis: localhost:6379

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

Initial scaffold is in place. The current fou