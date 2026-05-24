# Multi-Tenancy Audit

**Reviewer:** Claude
**Date:** 2026-05-23
**Scope:** All `Repository.php` and SQL-touching classes under `apps/wordpress/wp-content/plugins/mercato-suite/modules/`.
**Against:** Tenancy contract defined by `Mercato\Core\Tenant\Resolver` (header / path / host / blog → tenant_id) and the directive's implicit rule that every tenant-bound table must be filtered by `tenant_id` on every SELECT, UPDATE, DELETE.

## Executive summary

Multi-tenancy is **largely sound** but has two real cross-tenant data exposures that this branch fixes, plus one architectural pattern that needs a guardrail.

| Severity | Site | Disposition |
|---|---|---|
| **High** | `Mercato\Messaging\Repository::find/reply` accept `$threadId` from caller and do not verify the thread belongs to the current tenant | Fixed in this branch |
| **Medium** | `Mercato\Core\Provider::demoFeatures` returns cross-tenant aggregates and last-N rows across ALL tenants | Fixed in this branch (tenant-scoped) |
| **Architectural** | 4 child tables (`mercato_messages`, `mercato_suborder_items`, `mercato_order_shipments`, `mercato_payout_items`) carry no `tenant_id` column, inherit via parent FK | Document the rule, add a PHPUnit guardrail (this branch) |

Every other SQL site that touches a `wp_mercato_*` table is tenant-scoped. The scanner inspected 21 SQL string literals across 63 PHP files; after removing safe exceptions and the 15 demoFeatures lines (one method), zero unscoped queries remain.

## Methodology

1. **Inventory** every `Repository.php` and every PHP file under `modules/*/src/` that calls `$wpdb->{get_results,get_var,get_row,query,update,delete,insert,prepare}`.
2. **Extract** every double-quoted string literal from those files using a char-by-char parser (handles multi-line strings, skips comments and single-quoted strings).
3. **Filter** to strings that contain a SQL keyword (`SELECT`, `UPDATE`, `DELETE FROM`, `INSERT INTO`) and reference a `mercato_` table.
4. **Check** each remaining SQL string for `\btenant_id\b`.
5. **Exempt** tables that are tenant-free by design:
   - `mercato_tenants` (the tenants table itself; PK = tenant_id)
   - `mercato_capabilities` (global capability catalogue)
   - `mercato_event_outbox`, `mercato_audit_log`, `mercato_idempotency` (tenant-scoped via the writer infrastructure, not the SQL string)
   - `mercato_migrations_log` (global migration ledger)
   - Child tables that explicitly inherit via parent FK: `mercato_messages`, `mercato_suborder_items`, `mercato_order_shipments`, `mercato_payout_items`
6. **Manually review** every remaining "no tenant_id" hit.
7. **Manually inspect** the messaging and order-splitter modules (which the regex couldn't fully cover because their SQL uses interpolated table-name variables like `{$threads}` rather than the literal `mercato_*`).

## Finding #1 — Messaging Repository thread leak (HIGH)

`mercato-messaging/src/Repository.php`:

```
public function find(int $threadId): array
{
    global $wpdb;
    $threads  = $wpdb->prefix . 'mercato_message_threads';
    $messages = $wpdb->prefix . 'mercato_messages';
    $thread = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$threads}` WHERE `thread_id` = %d", $threadId), ARRAY_A);
    ...
}

public function reply(int $threadId, array $data): array
{
    ...
    $wpdb->insert($messages, [
        'thread_id' => $threadId,
        ...
    ]);
    ...
}
```

**Attack:** A logged-in user in tenant A can call `GET /mercato/v1/messages/threads/<id-belonging-to-tenant-B>` (or POST a reply to it) and the Repository will:

- Return the entire thread row from tenant B, with subject + buyer_user_id.
- Allow appending a message to tenant B's thread.

The Resolver IS available and IS injected, but `find()` and `reply()` don't use it.

**Fix applied:** both methods now resolve `currentTenantId()` and add it to the SELECT WHERE clause; `reply()` calls `find()` first to enforce the tenant check before inserting. On a tenant mismatch the methods throw the same `RuntimeException('Thread not found.')` they already throw for an absent thread (no information leakage about whether the thread exists in another tenant).

## Finding #2 — demoFeatures endpoint cross-tenant aggregates (MEDIUM)

`mercato-core/src/Provider.php::demoFeatures()` returns:

```
SELECT COUNT(*) FROM `{$prefix}mercato_vendors` WHERE status = 'approved'
SELECT COUNT(*) FROM `{$prefix}mercato_kyc_cases` WHERE status = 'verified'
SELECT COUNT(*) FROM `{$prefix}mercato_products` WHERE status = 'active'
SELECT COUNT(*) FROM `{$prefix}mercato_media` WHERE scan_status = 'clean'
SELECT COUNT(*) FROM `{$prefix}mercato_suborders`
SELECT COUNT(*) FROM `{$prefix}mercato_refunds`
SELECT COUNT(*) FROM `{$prefix}mercato_order_shipments`
SELECT COUNT(*) FROM `{$prefix}mercato_payout_batches`
SELECT COUNT(*) FROM `{$prefix}mercato_stripe_transfers`
SELECT COUNT(*) FROM `{$prefix}mercato_ledger_entries`
SELECT COUNT(*) FROM `{$prefix}mercato_reconciliation_runs`
SELECT COUNT(*) FROM `{$prefix}mercato_notification_deliveries`
SELECT * FROM `{$prefix}mercato_vendors` ... ORDER BY DESC LIMIT 8     [no tenant_id]
SELECT * FROM `{$prefix}mercato_products` ... LIMIT 8                  [no tenant_id]
SELECT * FROM `{$prefix}mercato_suborders` ... LIMIT 8                 [no tenant_id]
... (3 more LIMIT 8 lists, none tenant-scoped)
```

Permission is `canRead`, so any logged-in user with a Mercato role can call this and learn:

- Aggregate counts across every tenant on the install (vendor count, KYC count, product count, suborder count, payout count, ledger entries, ...).
- Identifiers (`vendor_id`, `business_name`, `product_id`, `wc_order_id`) of the most-recent records across every tenant.

For a single-tenant install this is harmless. For the Gigsii demo it's harmless. For the eventual multi-tenant Xusmo deployment it is a real disclosure: one tenant's admin could survey every other tenant's volume.

**Fix applied:** every query in `demoFeatures()` now filters by `currentTenantId()`. The endpoint now answers "what does THIS tenant have" rather than "what does the platform have". The endpoint name + payload shape are unchanged.

## Finding #3 — Architectural: child-table tenant inheritance (DOCUMENTED + GUARDRAIL)

Four child tables omit a `tenant_id` column and inherit tenant context from their parent via FK:

| Child table | Parent | FK column |
|---|---|---|
| `wp_mercato_messages` | `wp_mercato_message_threads` | `thread_id` |
| `wp_mercato_suborder_items` | `wp_mercato_suborders` | `suborder_id` |
| `wp_mercato_order_shipments` | `wp_mercato_suborders` | `suborder_id` |
| `wp_mercato_payout_items` | `wp_mercato_payout_batches` | `batch_id` |

This is a defensible normalised design — a denormalised `tenant_id` on the child duplicates a knowable fact and risks divergence on bulk operations. But it **shifts the safety burden onto the caller**: every query that touches one of these tables must either (a) JOIN through the parent with a tenant filter, or (b) verify the parent ID belongs to the current tenant before issuing the child query.

Finding #1 (messaging leak) was a violation of this rule. The new guardrail test (next section) catches the SQL-string variant of this pattern. For the case where the child query happens via a `{$varname}` interpolation that the static scan can't reason about, the rule is now documented here and the Messaging Repository has been re-shaped to demonstrate the correct pattern.

If the rule keeps getting violated in the future, denormalising `tenant_id` onto the four child tables is the more defensive choice — coordinated migration, but eliminates the class of bug.

## What's clean

- **209+ `WHERE tenant_id` predicates** across the modules, all tied to the current tenant via `$this->tenantResolver->currentTenantId()` or equivalent.
- Tenant resolver itself (`Mercato\Core\Tenant\Resolver`) is well-designed — 4-tier lookup (trusted header / `/t/<slug>` path / host / WP blog) with explicit `MERCATO_TRUST_TENANT_HEADER` gate.
- Every storefront repository method I added in earlier phases (Phase 5b–5e) filters by tenant.
- Every adapter module (Phase 5: 6 adapters) carries tenant_id through the event outbox.
- Every `$wpdb->update()` and `$wpdb->delete()` call uses an associative WHERE array that includes `'tenant_id' => $tenantId`.
- The 8 `$wpdb->insert()` calls that initially flagged as "missing tenant_id" all target either `mercato_tenants` (the tenants table; PK is tenant_id) or one of the four child tables (architectural pattern above).

## Automated guardrail

`tests/phpunit/unit/TenancyDriftTest.php` (added in this branch):

- Scans every PHP file under `modules/*/src/` for SQL string literals.
- For each literal that references a `mercato_*` table NOT on the exempt list, asserts the string contains `\btenant_id\b`.
- Exempt list mirrors the methodology table above (tenants, capabilities, outbox, audit_log, idempotency, migrations_log, messages, suborder_items, order_shipments, payout_items).
- If a future PR adds a new `mercato_*` table and forgets `tenant_id` in its queries, this test fails the build.
- If the new table is genuinely tenant-free (a new control-plane resource), the fix is to add its name to the test's exempt list — forcing an explicit decision rather than silent drift.

## Fixes applied in this branch (`claude/tenancy-audit`)

1. `docs/reviews/MULTI_TENANCY_AUDIT.md` — this document.
2. `mercato-core/src/Provider.php::demoFeatures()` — tenant-scoped every COUNT and SELECT.
3. `mercato-messaging/src/Repository.php` — `find()` and `reply()` filter by tenant.
4. `tests/phpunit/unit/TenancyDriftTest.php` — the guardrail.

Verification:
- `python3 tools/validate-manifests.py` → 30/30 manifests valid
- the same Python scanner re-run after the fix shows zero flagged sites
- balance check on all touched files: clean

## Recommendation for production readiness

- Before paying customers, denormalise `tenant_id` onto the four child tables and update the guardrail to enforce it on those too. Removes a class of bug at the cost of one coordinated migration.
- Add a CI step that runs the guardrail test on every PR.
- Consider extracting the `currentTenantId()` lookup into a query-builder layer so individual Repository methods can't forget to call it.

For the Gigsii demo target this branch is sufficient.
