$ErrorActionPreference = "Stop"

python tools\validate-manifests.py
powershell -ExecutionPolicy Bypass -File tools\validate-deployment-assets.ps1
powershell -ExecutionPolicy Bypass -File tools\run-phpunit.ps1
npm test

if ($env:MERCATO_RUN_E2E -eq "1") {
    powershell -ExecutionPolicy Bypass -File tools\run-e2e-smoke.ps1
}
