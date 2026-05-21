$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_E2E_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

$secret = $env:MERCATO_TEST_API_SECRET
if (!$secret) {
    $secret = "mercato-local-test-secret"
}

$headers = @{ "X-Mercato-Test-Secret" = $secret }

$live = Invoke-RestMethod -Uri "$baseUrl/?rest_route=/mercato/v1/health/live" -Method GET
if ($live.status -ne "ok") {
    throw "Liveness check failed."
}

$ready = Invoke-RestMethod -Uri "$baseUrl/?rest_route=/mercato/v1/health/readiness" -Method GET -Headers $headers
if ($ready.status -ne "ok") {
    $ready | ConvertTo-Json -Depth 10
    throw "Readiness check is not ok."
}

$compose = docker compose ps --format json | ConvertFrom-Json
$stopped = @($compose | Where-Object { $_.State -ne "running" })
if ($stopped.Count -gt 0) {
    $stopped | ConvertTo-Json -Depth 5
    throw "One or more Docker services are not running."
}

[pscustomobject]@{
    base_url = $baseUrl
    live = $live.status
    readiness = $ready.status
    modules = $ready.checks.modules.count
    outbox_pending = $ready.checks.outbox.pending_count
    outbox_dlq = $ready.checks.outbox.dlq_count
    services = @($compose).Count
} | ConvertTo-Json -Depth 5
