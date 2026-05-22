$ErrorActionPreference = "Stop"

$mysql = (docker ps --filter name=mercato-mysql --format "{{.ID}}" | Select-Object -First 1)
if (!$mysql) {
    throw "Mercato MySQL container is not running."
}

$stamp = Get-Date -Format "yyyyMMddHHmmss"
$dumpPath = "/tmp/mercato-$stamp.sql"
$restoreDb = "mercato_restore_$stamp"

docker exec $mysql sh -lc "mysqldump -uroot -proot --no-tablespaces mercato > $dumpPath"
docker exec $mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS $restoreDb; CREATE DATABASE $restoreDb;"
docker exec $mysql sh -lc "mysql -uroot -proot $restoreDb < $dumpPath"
$verify = docker exec $mysql mysql -uroot -proot --batch --skip-column-names -D $restoreDb -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$restoreDb' AND table_name IN ('wp_mercato_suborders','wp_mercato_commissions','wp_mercato_payout_batches','wp_mercato_ledger_entries','wp_mercato_event_outbox');"
docker exec $mysql mysql -uroot -proot -e "DROP DATABASE $restoreDb;"
docker exec $mysql rm -f $dumpPath

if ([int]($verify | Select-Object -Last 1) -lt 5) {
    throw "Restore drill did not find all core tables."
}

[pscustomobject]@{
    status = "passed"
    restored_tables = [int]($verify | Select-Object -Last 1)
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
} | ConvertTo-Json
