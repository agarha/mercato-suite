# One-shot: bring the local Docker stack up, wait for WordPress to be
# reachable on localhost:8092, then re-run the Gigsii seed so the
# tenant config (including the new theme="taskfirst" override) lands
# in wp_mercato_tenant_settings.
#
# Phase 5n shipped the sanitizer fix that lets `theme` + `taskfirst`
# survive the API write. Without re-running the seed, the existing
# Gigsii DB row still has no theme key and the storefront keeps
# rendering the Mercato default.
#
# Usage:
#   .\tools\apply-taskfirst-to-gigsii.ps1
#
# Then open http://localhost:8092/t/gigsii/ — should render Task-First.

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

Write-Host "==> Starting Docker stack..." -ForegroundColor Cyan
docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker is not running. Open Docker Desktop, wait for the whale icon to go solid, then re-run this script." -ForegroundColor Yellow
    exit 1
}

$baseUrl = if ($env:MERCATO_E2E_BASE_URL) { $env:MERCATO_E2E_BASE_URL } else { "http://localhost:8092" }
Write-Host "==> Waiting for WordPress at $baseUrl ..." -ForegroundColor Cyan
$ready = $false
for ($i = 1; $i -le 60; $i++) {
    try {
        $r = Invoke-WebRequest -Uri "$baseUrl/" -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
        if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) {
            $ready = $true
            break
        }
    } catch { Start-Sleep -Seconds 2 }
    Write-Host "   ... still waiting ($i/60)"
}
if (-not $ready) {
    Write-Host "WordPress did not come up within 2 minutes. Check 'docker compose logs wordpress'." -ForegroundColor Yellow
    exit 1
}
Write-Host "==> WordPress is reachable." -ForegroundColor Green

Write-Host "==> Re-running Gigsii seed (writes theme=taskfirst into the tenant config)..." -ForegroundColor Cyan
& "$PSScriptRoot\seed-gigsii-tenant.ps1"

Write-Host ""
Write-Host "Done. Open http://localhost:8092/t/gigsii/ — Task-First should be live." -ForegroundColor Green
