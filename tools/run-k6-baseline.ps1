$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_K6_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

$script = "tests/performance/k6/mercato-baseline.js"
if (!(Test-Path $script)) {
    throw "Missing k6 baseline script: $script"
}

$outDir = "reports/performance"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$stamp = Get-Date -Format "yyyyMMddHHmmss"
$summaryPath = Join-Path $outDir "k6-baseline-$stamp.json"

$k6 = Get-Command k6 -ErrorAction SilentlyContinue
if ($k6) {
    & k6 run --summary-export $summaryPath -e "MERCATO_BASE_URL=$baseUrl" -e "MERCATO_TEST_API_SECRET=$(if ($env:MERCATO_TEST_API_SECRET) { $env:MERCATO_TEST_API_SECRET } else { 'mercato-local-test-secret' })" $script
} else {
    $docker = Get-Command docker -ErrorAction SilentlyContinue
    if (!$docker) {
        throw "Neither k6 nor docker is available to execute the k6 baseline."
    }

    $dockerBaseUrl = $baseUrl -replace "localhost", "host.docker.internal"
    $repoRoot = (Resolve-Path ".").Path
    docker run --rm `
        -e "MERCATO_BASE_URL=$dockerBaseUrl" `
        -e "MERCATO_TEST_API_SECRET=$(if ($env:MERCATO_TEST_API_SECRET) { $env:MERCATO_TEST_API_SECRET } else { 'mercato-local-test-secret' })" `
        -v "${repoRoot}:/workspace" `
        -w /workspace `
        grafana/k6:0.50.0 run --summary-export "/workspace/$($summaryPath -replace '\\','/')" $script
}

if (!(Test-Path $summaryPath)) {
    throw "k6 summary was not written: $summaryPath"
}

$summary = Get-Content -Path $summaryPath -Raw | ConvertFrom-Json
$failed = @()
foreach ($name in $summary.metrics.PSObject.Properties.Name) {
    $metric = $summary.metrics.$name
    if ($metric.thresholds) {
        foreach ($thresholdName in $metric.thresholds.PSObject.Properties.Name) {
            if ($metric.thresholds.$thresholdName -eq $true) {
                $failed += "$name $thresholdName"
            }
        }
    }
}

if ($failed.Count -gt 0) {
    throw "k6 thresholds failed: $($failed -join ', ')"
}

[pscustomobject]@{
    status = "passed"
    base_url = $baseUrl
    summary = $summaryPath
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
} | ConvertTo-Json
