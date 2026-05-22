# SaaS Tenancy

Mercato supports pooled multi-tenancy with tenant-scoped domain data and a resolver chain.

## Tenant Resolution

Tenant context is resolved in this order:

1. `MERCATO_TEST_TENANT_ID` for test fixtures.
2. Trusted `X-Mercato-Tenant-Id` header when `MERCATO_TRUST_TENANT_HEADER=true`.
3. Path prefix: `/t/{tenant_slug}/...`.
4. Exact domain mapping from `mercato_tenant_domains`.
5. Subdomain slug, such as `gigsii.mercato.example`.
6. WordPress multisite blog ID.
7. Tenant `1` fallback for local single-tenant development.

The trusted header must only be enabled behind infrastructure that strips inbound spoofed tenant headers and re-injects the trusted value.

## Tenant Domains

Tenant domains are stored in `mercato_tenant_domains`:

- `domain`
- optional `path_prefix`
- `is_primary`
- `status`
- verification timestamp

Domains can be added through:

```text
POST /wp-json/mercato/v1/enterprise/domains
```

Tenant provisioning also accepts a `domains` array so a tenant can be created with host routing in one workflow.

## Tenant Integration Settings

Per-tenant integration settings are stored in `mercato_tenant_integrations`.

Supported provider keys:

- `stripe`
- `sendgrid`
- `s3`
- `tax`
- `search`
- `sms`
- `kyc`

The table separates public configuration from secret references. Secret values should live in a secret manager, environment vault, or KMS-backed store; Mercato stores only references such as secret names, ARNs, or vault paths.

APIs:

```text
GET  /wp-json/mercato/v1/enterprise/integrations
POST /wp-json/mercato/v1/enterprise/integrations/{provider}
```

Example:

```json
{
  "status": "test",
  "public_config": {
    "mode": "test",
    "region": "ca-central-1"
  },
  "secret_refs": {
    "api_key": "vault://tenants/gigsii/sendgrid/api_key"
  }
}
```

## Still Required For Full Production SaaS

- Tenant isolation integration tests across every REST endpoint.
- Tenant admin UI for domain, branding, plan, billing, and feature flags.
- Tenant lifecycle automation for suspend, close, export, restore, and delete.
- Edge/proxy configuration that enforces trusted tenant headers safely.
- Per-tenant observability dashboards, quotas, and alert routing.
