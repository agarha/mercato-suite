$ErrorActionPreference = "Stop"

$items = @(
    @{ id = "EXT-GH-001"; status = "blocked"; item = "GitHub issue sync"; required = "Issue write permission or PAT for agarha/mercato-suite" },
    @{ id = "EXT-MVP-001"; status = "blocked"; item = "Formal MVP approval/signoff"; required = "Product/business owner approval record" },
    @{ id = "EXT-UAT-001"; status = "blocked"; item = "Full UAT signoff"; required = "Named testers execute UAT script and sign off" },
    @{ id = "EXT-AWS-001"; status = "blocked"; item = "Real AWS deployment proof"; required = "AWS credentials, staging account, terraform plan/apply logs" },
    @{ id = "EXT-DR-001"; status = "blocked"; item = "Cloud DR/failover drill"; required = "Staging Aurora/S3 restore and failover execution" },
    @{ id = "EXT-SEC-001"; status = "blocked"; item = "Pentest evidence"; required = "External pentest vendor report" },
    @{ id = "EXT-SOC2-001"; status = "blocked"; item = "SOC 2 evidence"; required = "Auditor engagement and evidence repository" },
    @{ id = "EXT-AI-001"; status = "deferred"; item = "AI Copilot production service"; required = "Post-MVP AI service, contracts, evals, and guardrails" }
)

$reportDir = "reports/governance"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$path = Join-Path $reportDir "external-blockers.json"
$items | ConvertTo-Json -Depth 5 | Set-Content -Path $path

[pscustomobject]@{
    status = "recorded"
    blockers = @($items | Where-Object { $_.status -eq "blocked" }).Count
    deferred = @($items | Where-Object { $_.status -eq "deferred" }).Count
    report = $path
} | ConvertTo-Json
