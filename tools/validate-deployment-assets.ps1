$ErrorActionPreference = "Stop"

$chart = "infrastructure\helm\mercato-suite"
$required = @(
    "$chart\Chart.yaml",
    "$chart\values.yaml",
    "$chart\templates\wordpress.yaml",
    "$chart\templates\outbox-relay.yaml",
    "$chart\templates\migration-job.yaml",
    "$chart\templates\cronjobs.yaml",
    "$chart\templates\servicemonitor.yaml",
    "infrastructure\k8s\README.md"
)

foreach ($path in $required) {
    if (!(Test-Path $path)) {
        throw "Missing deployment asset: $path"
    }
}

$chartText = Get-Content "$chart\Chart.yaml" -Raw
$valuesText = Get-Content "$chart\values.yaml" -Raw
$templatesText = (Get-Content "$chart\templates\*.yaml" -Raw) -join "`n"

if ($chartText -notmatch "apiVersion:\s+v2") { throw "Helm chart is missing apiVersion v2." }
if ($valuesText -notmatch "outboxRelay:" -or $valuesText -notmatch "migrations:" -or $valuesText -notmatch "metrics:") {
    throw "Helm values must include outboxRelay, migrations, and metrics sections."
}
if ($templatesText -notmatch "kind:\s+Deployment" -or $templatesText -notmatch "mercato-outbox-relay") {
    throw "Helm templates must include the outbox relay deployment."
}
if ($templatesText -notmatch "helm.sh/hook:\s+pre-install,pre-upgrade") {
    throw "Helm templates must include a pre-install/pre-upgrade migration job."
}
if ($templatesText -notmatch "kind:\s+CronJob") {
    throw "Helm templates must include WP cron replacement."
}
if ($templatesText -notmatch "kind:\s+ServiceMonitor" -or $valuesText -notmatch "path:\s+/metrics") {
    throw "Helm templates must include Prometheus metrics scraping."
}

if (Get-Command helm -ErrorAction SilentlyContinue) {
    helm lint $chart | Out-Host
}

[pscustomobject]@{
    chart = $chart
    required_assets = $required.Count
    helm_lint = [bool](Get-Command helm -ErrorAction SilentlyContinue)
} | ConvertTo-Json
