# Mercato Suite Implementation Plan

Source of truth: `../docs_v2`, with `00_mvp_cut/MVP_Cut.md` controlling MVP scope.

## 1. Repository Principle

The implementation lives in `mercato-suite` as an independent repository. Documentation remains in `docs_v2` and is not mixed with runtime code.

The WordPress deliverable is one installable plugin bundle:

```text
apps/wordpress/wp-content/plugins/mercato-suite
```

Internally, the bundle is modular:

```text
modules/
  mercato-core
  mercato-vendors
  mercato-products
  mercato-orders
  mercato-commissions
  mercato-payouts
  mercato-messaging
  mercato-notifications
  mercato-kyc-kyb
  mercato-enterprise
  mercato-stripe-connect
  mercato-sendgrid
  mercato-aws-s3
```

## 2. Coverage From the Beginning

The project must not start with only the happy-path MVP code. Every target module and every cross-cutting concern is represented immediately, even when runtime behavior is deferred. The foundation therefore includes manifests, source directories, migration directories, OpenAPI directories, event-schema directories, and test directories for every named module.

Deferred modules are disabled by feature flag and phase, not omitted from the repository.

## 3. MVP Milestones

### M0: Bootstrap

- Create independent Git repository.
- Add Docker Compose for local WordPress/WooCommerce development.
- Add plugin bundle scaffold.
- Add full target module manifests, including deferred modules and adapters.
- Add module dependency ordering and inventory smoke test.
- Add release zip script.

### M1: Core Runtime

- Implement module service provider contract.
- Implement DI container.
- Implement migrations with up/down/verify.
- Implement RBAC and capability checks.
- Implement idempotency key storage.
- Implement audit log.
- Implement outbox publisher.
- Implement WooCommerce hook adapter.

### M2: Marketplace Data Model

- Add MVP database migrations.
- Add tenant scoping conventions.
- Add vendor, product, sub-order, commission, payout, KYC, message, notification, upload tables.
- Add seed data for a demo marketplace.

### M3: Vendor and Catalog

- Vendor registration.
- Tenant approval/rejection/suspension.
- Storefront settings.
- Simple product CRUD.
- Product soft-delete.
- WooCommerce product shadow projection.
- S3/MinIO presigned upload path.

### M4: Orders, Commissions, Payouts

- Multi-vendor checkout split.
- Vendor-specific sub-orders.
- Per-vendor shipping line.
- Sub-order lifecycle.
- Tracking capture.
- Commission calculation.
- Refund reversal.
- Vendor balance ledger.
- Weekly payout batch.
- Stripe Connect destination charges.
- Daily reconciliation.

### M5: UX

- Tenant admin SPA.
- Vendor dashboard SPA.
- MVP screens for onboarding, approval, products, orders, payouts, commissions, KYC, messaging, and dashboard metrics.
- Mobile and desktop layout checks.

### M6: Integrations

- Stripe Connect adapter.
- Stripe Identity adapter.
- Stripe webhook handler.
- SendGrid adapter with Mailpit fallback.
- AWS S3 adapter with MinIO fallback.
- Fixed-rate tax mock.

### M7: Quality Gates

- PHPStan level 6.
- PHPUnit/Pest module tests.
- Playwright top 8 MVP workflows.
- k6 relaxed performance checks.
- Docker boot check.
- Accessibility smoke checks for MVP screens.

### M8: Production Foundation

- Kubernetes manifests or Helm chart.
- Terraform baseline for AWS MVP infrastructure.
- Secrets Manager integration.
- Observability baseline: Prometheus, Grafana, FluentBit, OpenTelemetry, Tempo lite.
- Backup and restore runbook support.

## 4. GitHub Plan

Preferred GitHub repository:

```text
agarha/mercato-suite
```

Branch:

```text
codex/initial-scaffold
```

First push contents:

- independent reposito