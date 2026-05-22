# Platform Tenant And Catalog Model

Mercato is the source platform. Tenant products such as Gigsii should consume Mercato as the engine and apply brand, seed data, feature flags, and optional tenant modules on top of it.

## Tenant Boundary

Every core domain table is tenant-scoped with `tenant_id`. Repositories must resolve the current tenant through `Mercato\Core\Tenant\Resolver` and include `tenant_id` in reads and writes.

Tenant projects should not fork core behavior for normal feature work. Shared primitives belong in Mercato first so all tenant products inherit them.

## Catalog Relationship Model

The catalog supports two layers:

- `mercato_products`: canonical product/service templates.
- `mercato_vendor_service_offerings`: provider/vendor-specific offers for a product/service.

This keeps existing one-vendor product checkout behavior compatible while enabling many-to-many service marketplaces:

```text
Tenant
  -> Services / Products
  -> Vendors / Providers
  -> Vendor Service Offerings

Service A can be offered by Provider 1, Provider 2, and Provider 3.
Provider 1 can offer Service A, Service B, and Service C.
```

## Categorization

Categories are tenant-scoped:

- `mercato_categories`
- `mercato_product_categories`

A service/product can be in multiple categories, and categories are isolated by tenant.

## Geolocation

Provider/vendor location is tenant-scoped:

- `mercato_vendor_locations`
- `mercato_service_areas`

The products API supports geo filtering with `latitude`, `longitude`, and `radius_km`. This is intended for local-service marketplaces like Gigsii.

## Gigsii Consumption Pattern

Gigsii should be a tenant/product layer on top of Mercato:

- Brand and public website through tenant configuration or a theme layer.
- Gigsii categories as tenant data.
- Gigsii providers as Mercato vendors with provider profile extensions.
- Gigsii services as Mercato products/service templates.
- Gigsii provider availability/pricing as vendor service offerings.
- Gigsii service areas through provider locations and service areas.

Changes to shared marketplace primitives should be made in Mercato, then pulled or released into Gigsii.
