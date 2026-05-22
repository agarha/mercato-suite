$ErrorActionPreference = "Stop"

python tools\validate-manifests.py
python tools\validate-contracts.py
powershell -ExecutionPolicy Bypass -File tools\validate-deployment-assets.ps1
powershell -ExecutionPolicy Bypass -File tools\run-security-scans.ps1
powershell -ExecutionPolicy Bypass -File tools\run-phpunit.ps1
npm test
npm run test:playwright:catalog
npm run test:locales

if ($env:MERCATO_RUN_E2E -eq "1") {
    powershell -ExecutionPolicy Bypass -File tools\run-e2e-smoke.ps1
}

if ($env:MERCATO_RUN_K6 -eq "1") {
    powershell -ExecutionPolicy Bypass -File tools\run-k6-baseline.ps1
}
