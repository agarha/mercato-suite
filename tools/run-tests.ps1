$ErrorActionPreference = "Stop"

python tools\validate-manifests.py
powershell -ExecutionPolicy Bypass -File tools\run-phpunit.ps1
npm test
