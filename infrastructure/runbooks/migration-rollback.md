# Migration Rollback Runbook

Status: MVP operational runbook

Mercato migrations are forward-only for normal releases. Rollback is handled by a release restore point plus targeted downgrade SQL for low-risk metadata changes.

## Required Release Gate

1. Run `tools/backup-restore-drill.ps1` before applying production migrations.
2. Confirm the release artifact SHA in `dist/`.
3. Confirm `wp_mercato_migrations` contains the expected previous migration set.
4. Take an Aurora snapshot or logical dump before executing a migration job.

## Rollback Decision

Use restore rollback when:

- a migration drops or rewrites data,
- a table definition changes a high-volume table,
- application boot is blocked after migration,
- tenant isolation, ledger, payout, or order data is affected.

Use targeted downgrade only when:

- the migration only added nullable columns, indexes, views, or metadata tables,
- the downgrade SQL was tested in staging,
- no production writes depend on the new field.

## Local Verification

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tools\backup-restore-drill.ps1
```

Expected result:

- `status = passed`
- all core tables restored into a temporary database

## Production Evidence Required

- Snapshot ID or dump artifact path
- Migration job logs
- Restore drill report
- Application readiness report after rollback
- Owner signoff
