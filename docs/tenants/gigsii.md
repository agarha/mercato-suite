# Gigsii Tenant Documentation

Gigsii is the flagship tenant running on the shared Mercato codebase. It is not a fork and should not become a fork for normal feature work.

## Tenant Identity

- Tenant slug: `gigsii`
- Local URL: `http://localhost:8092/t/gigsii`
- Provisioning script: `tools/seed-gigsii-tenant.ps1`
- Taxonomy seed: `tools/seed-gigsii-taskrabbit-taxonomy.ps1`

Tenant-specific behavior should be applied through:

- `mercato_tenants`
- `mercato_tenant_domains`
- `mercato_tenant_settings`
- `mercato_tenant_feature_flags`
- `mercato_tenant_integrations`
- Gigsii seed data for providers, service templates, categories, offerings, locations, service requests, bids, and jobs

Shared services-marketplace behavior belongs in Mercato modules so all future Xusmo tenants inherit it.

## Feature Policy

For the local Gigsii demo, all current Mercato and Gigsii feature flags are enabled so the complete product surface can be inspected in one tenant.

External integrations remain local/test mode until real credentials and vendor contracts are available.

## Service Marketplace Model

Gigsii uses the services-marketplace vocabulary from `CODEX_DIRECTIVE.md`:

- Vendor means provider.
- Product means service template.
- Order/suborder means job/payment record depending on context.
- Shipping zone means provider service area.
- Shipping tracking means job dispatch/status tracking.
- Multi-vendor cart means request-to-bid-to-award flow for services.

The existing storage names remain unchanged for now.

## Implemented Tenant Capabilities

- Branded tenant storefront.
- Category and subcategory browse.
- Tenant-scoped provider directory.
- Many providers can offer the same service.
- One provider can offer many services.
- Provider locations and service areas support geolocation matching.
- Client can post a service request.
- Providers can submit bids.
- Request can be sealed bid or open auction.
- Accepted bid awards the request and creates a job.
- Booking creates a job.
- Lead can convert to estimate and accepted estimate can create a job.
- Job lifecycle supports assignment and status transitions.
- Referral accrual and redemption work.
- Feature/integration status is visible on the storefront.

## Not A Fork

Do not describe Gigsii as a fork. The correct wording is:

> Gigsii is a Mercato tenant and flagship reference implementation.

Incorrect wording:

> Gigsii is a fork of Mercato.

If a future customer requires custom behavior, implement the shared primitive in Mercato first, then expose tenant-specific configuration or feature flags.
