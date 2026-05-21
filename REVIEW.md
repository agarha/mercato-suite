# M0 Scaffold Review

> Reviewer: cross-functional team (architecture, eng, security, QA, DevOps)
> Date: 2026-05-21
> Scaffold under review: branch `codex/initial-scaffold`

This is a working review of the initial Codex scaffold against the documentation suite in `../docs_v2`. It records what is in place, what is missing, and the targeted fixes applied in this commit.

## TL;DR

The original Codex scaffold landed the structural skeleton correctly: monorepo layout, plugin bundle under `apps/wordpress/.../mercato-suite/`, 29 module manifests, working topological sort with cycle detection, Docker Compose stack, Go outbox-relay placeholder, smoke test for boot order. It was 80% of an M0.

This commit closes the remaining 20% (real DI contract, foundation migrations, CI, test harness, manifest validator, ini split, LICENSE, CHANGELOG) and reconciles the docs↔code naming drift on `wp_mercato_suborders`. The result is M0 = complete.

## Strengths Carried Forward From Original Scaffold

| Aspect | Source |
|---|---|
| Monorepo layout matching `docs_v2/14_packaging/Plugin_Packaging.md` | `apps/`, `services/`, `packages/`, `database/`, `docker/`, `infrastructure/`, `tests/`, `tools/` |
| One installable WordPress plugin with internal modules | `apps/wordpress/wp-content/plugins/mercato-suite/modules/` |
| Module manifests with slug, namespace, version, sdk_version, requires, capabilities, tables, tier, feature_flag | All 29 modules |
| Topological sort with cycle + missing-dep detection | `ModuleRegistry::ordered()` |
| Outbox relay as a Go binary | `services/outbox-relay/` (per ADR-006) |
| Complete local dev stack | `docker-compose.yml` — WP, MySQL 8.4, Redis 7, Kafka+ZK, MinIO, Mailpit, outbox-relay |
| PHP 8.2 strict types, PSR-4 autoload | composer.json + all PHP sources |
| Smoke test asserting boot order invariants | `tests/phpunit/module-registry-smoke.php` |

## Gaps Closed in This Commit

### 1. Real DI contract (M1 prerequisite)
Original `Bootstrap.php` only emitted `do_action('mercato_module_discovered')` — modules had no way to register services. Added:
- **`Mercato\Core\Container`** — small PSR-11-shaped container with `bind()`, `instance()`, `has()`, `get()`.
- **`Mercato\Core\ServiceProvider`** — abstract base every module extends. Two-phase lifecycle: `register()` (pure container wiring, no WP calls) → `boot()` (WP API available). Convenience `migrations()` returns the module's `migrations/*.sql` files.
- **`Mercato\Core\Bootstrap`** rewritten — discovers modules → topologically sorts → instantiates each module's `Provider` class (fallback to anonymous null-provider) → runs `register()` for all → then `boot()` for all → fires `mercato_suite_booted`.

### 2. Environment guard (FR-CORE-001)
Bootstrap now refuses to boot when:
- PHP < 8.2
- WordPress < 6.4
- WooCommerce < 8.0
- HPOS not enabled

In a WP context, the plugin self-deactivates with an admin notice. In a CLI test harness, an exception is thrown.

### 3. Foundation migrations (M1 commitment)
Five .sql files in `modules/mercato-core/migrations/`:
- `0001_event_outbox.sql` — `wp_mercato_event_outbox`, `wp_mercato_event_consumed`
- `0002_audit_log.sql` — `wp_mercato_audit_log` (partitioning deferred to maintenance job)
- `0003_idempotency.sql` — `wp_mercato_idempotency`
- `0004_tenants_and_rbac.sql` — `wp_mercato_tenants`, `wp_mercato_tenant_settings`, `wp_mercato_tenant_feature_flags`, four RBAC tables
- `0005_migrations_log.sql` — `wp_mercato_migrations`, `wp_mercato_deprecation_log`

These map 1:1 to `docs_v2/06_database/Database.md` §3 and use `{prefix}` placeholders for table prefix replacement by the migrator (to be implemented in M1).

### 4. Manifest event taxonomy (Vol 04 §4.2)
Added `provides_events` and `consumes_events` arrays to every module.json. 13 MVP modules now declare 41 events emitted and 28 events consumed in total. The remaining 16 deferred modules carry empty arrays as placeholders.

### 5. Manifest validator (CI gate)
`tools/validate-manifests.py` checks every module.json against:
- Required field presence
- Slug ↔ directory name match
- Event names matching `mercato.<plugin>.<entity>...<verb>.v<N>` taxonomy
- Dependency strings parseable as `slug@semver-range`
- Capabilities matching `mercato_<resource>_<action>` pattern
- Tables matching `wp_mercato_<snake>` pattern
- Tier value in `{foundation, domain, adapter}`

Wired as a CI job; non-zero exit on any violation.

### 6. Test harness
- `phpunit.xml.dist` — PHPUnit 10 config with `unit` + `integration` suites.
- `tests/phpunit/bootstrap.php` — loads Mercato core PHP sources for unit tests without needing a WP install.
- `tests/phpunit/unit/ModuleRegistryTest.php` — 4 test methods covering discovery, ordering, missing-dep error, and event taxonomy. Replaces the procedural smoke test (which is preserved for ad-hoc use).

### 7. Static analysis
- `phpstan.neon` — level 6 (matches Vol 00 §5 relaxed MVP gate; will tighten to level 8 in P2 per Vol 05 NFR-M-003).
- Composer script wired: `composer analyse`.

### 8. CI workflow
`.github/workflows/ci.yml` runs four jobs in parallel:
1. **php** — matrix on PHP 8.2 + 8.3; composer install + PHPStan + PHPUnit.
2. **go** — Go 1.22; vet + build outbox-relay.
3. **module-manifests** — runs the validator.
4. **docker-build** — boots WordPress + outbox-relay images as smoke.

Trigger: push to main / `codex/initial-scaffold`; PR to main.

### 9. PHP profile split
- `docker/wordpress/php.dev.ini` — `display_errors=On`, OPcache revalidates, 512MB memory.
- `docker/wordpress/php.prod.ini` — `display_errors=Off`, OPcache pinned, 256MB memory, expose_php=Off, request_terminate_timeout=30.
- Dockerfile picks via `MERCATO_PHP_PROFILE` build arg (default `dev`).
- Old `docker/wordpress/php.ini` is now unreferenced and will be deleted in a follow-up.

### 10. Docs reconciliation
The original manifests used `wp_mercato_suborders` + `wp_mercato_suborder_items` while `docs_v2/06_database/Database.md` defined them as `wp_mercato_orders` + `wp_mercato_order_items`. The code naming is more accurate (parent orders live in WC HPOS; these tables hold *sub*-orders). Docs were updated in companion PR to `agarha/Mercato`. Now consistent.

### 11. LICENSE + CHANGELOG
Both added at repo root. Proprietary license; SDK separately MIT.

## Open Items for M1

Out of scope for M0 but next on the runway:

| Area | Action |
|---|---|
| Migrator implementation | `Mercato\Core\DB\Migrator` consuming module-declared `.sql` files in dep order |
| Outbox publisher | `Mercato\Core\Events\Outbox::publish()` writing to `wp_mercato_event_outbox` |
| Outbox relay (Go) | Replace tick loop with real DB polling + Kafka publish + DLQ |
| Idempotency middleware | `Idempotency-Key` HTTP header → `wp_mercato_idempotency` lookup |
| RBAC engine | `mercato_user_can($cap, $tenant_id, $resource_owner_id)` |
| Tenant resolver | resolve `tenant_id` from JWT / multisite blog / webhook path |
| Hook adapter | `Mercato\Core\WooCommerce\HookAdapter` per Vol 04 §3.6 + Vol 13 |
| Module README per module | one-paragraph summary of each module's responsibility |
| Each MVP module's `Provider` class | empty stub OK; just demonstrates the contract |

## Open Items for M2

- All 13 MVP module migrations.
- Seed data factories.
- First REST routes (`/mercato/v1/vendors`, `/mercato/v1/products`).
- Stripe webhook ingest endpoint (signature verification only).

## Recommendations for Reviewers / Future Agents

1. **Re-run the validator on every PR** that touches module.json. Catches both event taxonomy regressions and slug drift.
2. **Add a module README template** so every module has consistent intro docs.
3. **Use `tools/validate-manifests.py` as a pre-commit hook** locally — it's fast (<1s).
4. **Keep `ServiceProvider::register()` pure.** Any WP call there will explode for headless tests.
5. **All migrations use `{prefix}`** placeholder, replaced by the migrator with `$wpdb->prefix`. Don't hardcode `wp_`.
6. **Don't grow `Bootstrap.php` beyond ~130 lines.** If logic accretes, extract into named services bound in the container.

## Cross-References

| Topic | See |
|---|---|
| MVP scope | `../docs_v2/00_mvp_cut/MVP_Cut.md` |
| Module manifest contract | `../docs_v2/04_fsd/FSD.md` §4.2 |
| Plugin packaging strategy | `../docs_v2/14_packaging/Plugin_Packaging.md` |
| Event taxonomy | `../docs_v2/01_architecture/Blueprint.md` §7 + `../docs_v2/07_openapi/AsyncAPI.yaml` |
| DDL spec | `../docs_v2/06_database/Database.md` |
| Implementation phases | `IMPLEMENTATION_PLAN.md` |

## Closing

M0 is done. The scaffold is bootable (once Docker Compose runs), validated by CI, and the module contract is real instead of decorative. Ready for M1.
