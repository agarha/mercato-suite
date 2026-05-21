# Changelog

All notable changes to mercato-suite. Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Mercato\Core\Container` — tiny PSR-11-shaped DI container for module wiring.
- `Mercato\Core\ServiceProvider` — abstract base class every module extends; `register()` + `boot()` + `migrations()`.
- Bootstrap now refuses to boot without PHP 8.2+, WordPress 6.4+, WooCommerce 8.0+, and HPOS enabled (FR-CORE-001).
- Five foundation migrations for `mercato-core`: event outbox, audit log, idempotency store, tenants/RBAC, migrations log.
- `provides_events` and `consumes_events` declarations on every module manifest (Vol 04 §4.2).
- Module manifest validator (`tools/validate-manifests.py`) enforcing taxonomy + naming conventions.
- PHPStan level-6 config (`phpstan.neon`).
- PHPUnit 10 config + bootstrap (`phpunit.xml.dist`, `tests/phpunit/bootstrap.php`).
- `ModuleRegistryTest` — covers discovery, ordering, missing-dep error path, manifest event taxonomy.
- GitHub Actions CI (`.github/workflows/ci.yml`) — matrix PHP 8.2/8.3 + Go vet/build + manifest validation + Docker boot smoke.
- `docker/wordpress/php.dev.ini` and `php.prod.ini` — explicit dev vs. prod profile split.
- `LICENSE` (proprietary) and `CHANGELOG.md`.

### Changed
- Table names aligned across docs and code: `wp_mercato_orders` → `wp_mercato_suborders`, `wp_mercato_order_items` → `wp_mercato_suborder_items`. (Code's "sub-order" naming is clearer; docs updated to match.)
- `Bootstrap` rewritten to instantiate `ServiceProvider` per module and run `register()` → `boot()` lifecycle.
- Dockerfile uses `MERCATO_PHP_PROFILE` arg to pick the dev or prod php.ini.

### Notes
- Old `docker/wordpress/php.ini` is no longer referenced by the Dockerfile and may be removed in a follow-up commit (Windows-mounted FS preserved it during this change).

## [0.1.0] - 2026-05-21
### Added
- Initial scaffold: WordPress plugin bundle structure, Docker Compose stack (WP, MySQL, Redis, Kafka+ZK, MinIO, Mailpit, outbox-relay), module manifests for MVP modules, basic registry + topological sort, outbox-relay Go scaffold, smoke test, implementation plan.
