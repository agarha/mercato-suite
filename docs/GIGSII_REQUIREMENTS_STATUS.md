# Gigsii Requirements Status

Source documents: `C:/GIGsii/Gigsii_Project_Artifacts_v1.1/*_v1.1.docx`.

## System Placement

| Requirement area | Placement | Current status | Evidence |
|---|---|---:|---|
| Tenant branding/marketing homepage | Gigsii tenant config | Done | `tools/seed-gigsii-tenant.ps1`, `/t/gigsii` |
| Taskrabbit-style category/subcategory catalog | Gigsii tenant data | Done | `tools/seed-gigsii-taskrabbit-taxonomy.ps1`; local tenant currently has 19 parent categories and 195 subcategories |
| Canada/USA location readiness and geo search | Mercato codebase + Gigsii data | Done | `mercato_products`, `mercato_vendor_locations`, `mercato_service_areas`; product API supports latitude/longitude/radius |
| Many providers offering same service | Mercato codebase | Done | `mercato_vendor_service_offerings`; order splitting persists `offering_id` |
| One provider offering many services | Mercato codebase | Done | `mercato_products.vendor_id` plus offering table |
| Admin approval gate for providers | Mercato codebase | Done | `mercato-vendors` statuses, onboarding checklist, audit write |
| Service listing baseline | Mercato codebase | Partial | Categories, title, description, price, images/storage, city/service areas exist; exact Gigsii stepper UI still needs SPA polish |
| Booking request creates job | Mercato codebase | Done | `mercato-service-ops` booking/job tables and REST routes |
| Job lifecycle and dispatch assignment | Mercato codebase | Done | `mercato-service-ops` job states, transition validation, optimistic assignment conflict |
| Lead to estimate to job flow | Mercato codebase | Done | `mercato-service-ops` lead/estimate tables, accept-estimate job conversion |
| Messaging | Mercato codebase | Done | `mercato-messaging` module |
| Referral tracking and points accrual | Mercato codebase | Done | `mercato-service-ops` referrals table; redemption returns `FEATURE_DISABLED` |
| Client service request posting and provider bidding/auction | Mercato codebase + Gigsii tenant flag | Done | `mercato-service-ops` service request/bid tables and REST routes; `gigsii.task_posting=true` |
| Soft-launch disabled features | Gigsii tenant flags | Done | `gigsii.otp=false`, `gigsii.monetization=false`, `gigsii.referral_redemption=false`, `mercato.ai=false`; task posting enabled for the current Gigsii demo per updated product direction |
| RBAC server-side enforcement | Mercato codebase | Partial | REST permissions and test secret exist; full Gigsii role matrix still needs role-specific capabilities beyond admin/authenticated split |
| Public/client/provider/org/admin screens | Mercato UI | Partial | Tenant storefront and WP admin/vendor views exist; complete Gigsii SPA-level portals remain open |
| Offline technician mode | Mercato codebase | Deferred | SRS marks Phase 1; not soft-launch blocker |
| Privacy DSAR/retention jobs | Mercato codebase | Partial | Audit and tenant data model exist; DSAR export/delete and retention job still open |

## Soft Launch Decision

Gigsii is now represented as a tenant on the shared Mercato codebase, not as a fork. Tenant-specific differences should be seeded through tenant settings, feature flags, integrations, categories, providers, products, and offerings. Shared platform behavior should continue to be added to Mercato modules so Xusmo-provisioned tenants inherit it automatically.

## Remaining High-Priority Gaps

1. Replace broad `canRead`/`canManage` checks with the full Gigsii permission catalog: Client, Giger, Pro Giger, Org Owner, Dispatcher, Estimator, Technician, Admin, Super Admin, Support.
2. Build Gigsii-grade public/client/provider/org/admin UI screens over the existing REST surface.
3. Add full API integration tests for booking/job/estimate/referral flows against WordPress.
4. Add DSAR export/delete, data retention job, and public privacy/terms content for launch.
5. Add performance tests for marketplace search and job detail targets from the Gigsii test plan.
