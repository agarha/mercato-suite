$ErrorActionPreference = "Stop"

$mysql = (docker ps --filter name=mercato-mysql --format "{{.ID}}" | Select-Object -First 1)
if (!$mysql) {
    throw "Mercato MySQL container is not running."
}

$sql = Get-Content -Path "tools/sql/partition-maintenance.sql" -Raw
$result = $sql | docker exec -i $mysql mysql -umercato -pmercato --batch --skip-column-names -D mercato

if (($result -join "`n") -notmatch "pt-online-schema-change") {
    throw "Partition maintenance check did not return the expected online schema change guidance."
}

[pscustomobject]@{
    status = "passed"
    result = $result
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
} | ConvertTo-Json -Depth 5
