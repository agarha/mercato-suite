# Foundation Coverage Matrix

This matrix exists to keep the project complete from the first implementation pass. MVP scope controls delivery order, but every target module, infrastructure concern, and quality gate must be visible from day one.

## Documentation Alignment

| Source | Coverage Rule |
|---|---|
| `docs_v2/00_mvp_cut/MVP_Cut.md` | Controls MVP build/defer/stub/mock decisions. |
| `docs_v2/01_architecture/Blueprint.md` | Controls module topology, dependency graph, event/outbox, tenancy, and service boundaries. |
| `docs_v2/04_fsd/FSD.md` | Controls module behavior contracts. |
| `docs_v2/06_database/Database.md` | Controls custom table strategy and migrations. |
| `docs_v2/07_openapi/OpenAPI.yaml` | Controls REST API shape. |
| `docs_v2/10_qa/QA_Spec.md` | Controls test gates and traceability. |
| `docs_v2/11_devops/DevOps.md` | Controls Docker, IaC, observability, and deployment. |
| `docs_v2/14_packaging/Plugin_Packaging.md` | Controls one-suite external packaging and internal module boundaries. |

## Module Inventory

The documentation has a count mismatch: packaging text says 27 modules, while the named architecture/MVP inventory currently yields 29 modules. The implementation keeps every named module and treats the mismatch as a documentation governance issue.

| Module | Phase | Initial Coverage |
|---|---|---|
| `mercato-core` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-vendors` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-products` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-orders` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-commissions` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-payouts` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-messaging` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-notifications` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-kyc-kyb` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-enterprise` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-stripe-connect` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-sendgrid` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-aws-s3` | P1 | Manifest, source, migrations, tests, OpenAPI, events |
| `mercato-reviews` | P2 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-disputes` | P2 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-reports` | P2 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-search` | P2 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-tax-engine` | P2 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-paypal-marketplace` | P2 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-twilio` | P2 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-postmark` | P2 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-taxjar` | P2 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-shippo` | P2 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-subscriptions` | P3 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-fraud-risk` | P3 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-ai-copilot` | P3 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-avalara` | P3 | Deferred adapter manifest, source, migrations, tests, OpenAPI, events |
| `mercato-collaboration` | P4 | Deferred manifest, source, migrations, tests, OpenAPI, events |
| `mercato-migration` | P4 | Deferred manifest, source, migrations, tests, OpenAPI, events |

## Early Coverage Requirements

Every module must have these before feature implementation begins:

- `module.json` with phase, feature flag, dependencies, tables, and capabilities.
- `src/` for module code.
- `migrations/` for schema.
- `openapi/` for REST fragments or explicit no-route note.
- `events/` for emitted event schemas or explicit no-event note.
- `tests/` for unit/integration tests.

Every MVP module must additionally have:

- first migration file
- service provider
- REST route registration or explicit rationale
- RBAC capability tests
- tenant scoping tests
- audit/outbox behavior tests where applicable

## Cross-Cutting Workstreams

| Workstream | Early Coverage |
|---|---|
| Docker | Local WordPress, MySQL, Redis, Kafka, MinIO, Mailpit, outbox relay |
| GitHub | Branch and commit ready; push blocked until repo/access exists |
| Database | Migration directories now exist for all modules; MVP migrations next |
| API | OpenAPI directories now exist for all modules; fragments next |
| Events | Event schema directories now exist for all modules; MVP schemas next |
| Frontend | Admin and vendor SPA folders exist; screens next |
| Testing | PHP smoke test exists; module tests and E2E next |
| Infrastructure | IaC folders exist; AWS MVP baseline next |
| Observability | Docker/service placeholders exist; OTel wiring next |
| Security | Capability/audit requirements tracked; implementation next |
