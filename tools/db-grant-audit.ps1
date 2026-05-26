$ErrorActionPreference = "Stop"

$mysql = (docker ps --filter name=mercato-mysql --format "{{.ID}}" | Select-Object -First 1)
if (!$mysql) {
    throw "Mercato MySQL container is not running."
}

$dbUser = $env:MERCATO_DB_AUDIT_USER
if (!$dbUser) {
    $dbUser = "mercato"
}

$reportDir = "reports/security"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$stamp = Get-Date -Format "yyyyMMddHHmmss"
$reportPath = Join-Path $reportDir "db-grants-$stamp.json"
$rootPassword = $env:MYSQL_ROOT_PASSWORD
if (!$rootPassword) {
    $rootPassword = "root"
}
$rootEnv = "MYSQL_PWD=$rootPassword"

$grants = docker exec --env $rootEnv $mysql mysql -uroot --batch --skip-column-names -e "SHOW GRANTS FOR '$dbUser'@'%';" 2>$null
if (!$grants) {
    $grants = docker exec --env $rootEnv $mysql mysql -uroot --batch --skip-column-names -e "SHOW GRANTS FOR '$dbUser'@'localhost';" 2>$null
}

if (!$grants) {
    throw "Could not read grants for user '$dbUser'."
}

$joined = $grants -join "`n"
$forbidden = @()
foreach ($privilege in @("SUPER", "FILE", "PROCESS", "SHUTDOWN", "CREATE USER", "GRANT OPTION")) {
    if ($joined -match "\b$privilege\b") {
        $forbidden += $privilege
    }
}

$result = [pscustomobject]@{
    status = if ($forbidden.Count -eq 0) { "passed" } else { "failed" }
    user = $dbUser
    forbidden_privileges = $forbidden
    grants = $grants
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
}
$result | ConvertTo-Json -Depth 5 | Set-Content -Path $reportPath
$result | ConvertTo-Json -Depth 5

if ($forbidden.Count -gt 0) {
    throw "DB grant audit failed: $($forbidden -join ', ')"
}
