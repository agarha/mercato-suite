# Mercato Backup And Restore Runbook

MVP objective: prove Tier-0/Tier-1 restore readiness before launch. Production execution requires AWS credentials and an isolated restore environment.

## Local Drill

```powershell
powershell -ExecutionPolicy Bypass -File tools\backup-restore-drill.ps1
```

The local drill exports the MySQL database, restores it into a temporary database, and verifies that core Mercato tables are present.

## Production Drill

1. Select one Tier-0 table group: orders, commissions, payouts, or ledger.
2. Restore Aurora snapshot/PITR into isolated VPC.
3. Restore S3 media/report bucket version into isolated bucket prefix.
4. Replay outbox events for the selected restore window.
5. Verify checksums and trial balance drift.
6. Record RTO/RPO and attach evidence to the DR issue.

Blocked until AWS account, Aurora cluster, backup bucket, and restore role are available.
