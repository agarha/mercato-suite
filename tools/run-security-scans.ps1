$ErrorActionPreference = "Stop"

$reportDir = "reports/security"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$stamp = Get-Date -Format "yyyyMMddHHmmss"
$reportPath = Join-Path $reportDir "security-gate-$stamp.json"

$findings = @()

function Add-Finding {
    param(
        [string]$Tool,
        [string]$Severity,
        [string]$Message,
        [string]$Path = ""
    )
    $script:findings += [pscustomobject]@{
        tool = $Tool
        severity = $Severity
        message = $Message
        path = $Path
    }
}

$secretPatterns = @(
    "sk_live_[A-Za-z0-9]+",
    "AKIA[0-9A-Z]{16}",
    "-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----",
    "xox[baprs]-[A-Za-z0-9-]+"
)
$scanFiles = Get-ChildItem -Recurse -File |
    Where-Object {
        $_.FullName -notmatch "\\.git\\" -and
        $_.FullName -notmatch "\\node_modules\\" -and
        $_.FullName -notmatch "\\vendor\\" -and
        $_.FullName -notmatch "\\dist\\" -and
        $_.FullName -notmatch "\\reports\\"
    }

foreach ($file in $scanFiles) {
    $text = Get-Content -Path $file.FullName -Raw -ErrorAction SilentlyContinue
    if ($null -eq $text) { continue }
    foreach ($pattern in $secretPatterns) {
        if ($text -match $pattern) {
            Add-Finding -Tool "secret-scan" -Severity "high" -Message "Potential secret pattern matched." -Path $file.FullName
        }
    }
}

$dangerousPhp = Select-String -Path "apps/wordpress/wp-content/plugins/mercato-suite/modules/**/*.php" -Pattern "\beval\s*\(|\bshell_exec\s*\(|\bpassthru\s*\(|\bsystem\s*\(" -ErrorAction SilentlyContinue
foreach ($match in $dangerousPhp) {
    Add-Finding -Tool "sast-lite" -Severity "high" -Message "Dangerous PHP execution primitive found: $($match.Line.Trim())" -Path "$($match.Path):$($match.LineNumber)"
}

$terraformFmt = terraform fmt -check -recursive infrastructure/terraform 2>&1
if ($LASTEXITCODE -ne 0) {
    Add-Finding -Tool "iac" -Severity "high" -Message "terraform fmt -check failed: $terraformFmt"
}

$npmAuditStatus = "not_applicable"
if (Test-Path "package-lock.json") {
    npm audit --audit-level=high --json > (Join-Path $reportDir "npm-audit-$stamp.json")
    if ($LASTEXITCODE -ne 0) {
        Add-Finding -Tool "sca-npm" -Severity "high" -Message "npm audit found high or critical vulnerabilities."
    }
    $npmAuditStatus = "executed"
}

$composerAuditStatus = "not_applicable"
if (Test-Path "composer.lock") {
    composer audit --format=json > (Join-Path $reportDir "composer-audit-$stamp.json")
    if ($LASTEXITCODE -ne 0) {
        Add-Finding -Tool "sca-composer" -Severity "high" -Message "composer audit found vulnerable packages."
    }
    $composerAuditStatus = "executed"
}

$highOrCritical = @($findings | Where-Object { $_.severity -in @("high", "critical") })
$result = [pscustomobject]@{
    status = if ($highOrCritical.Count -eq 0) { "passed" } else { "failed" }
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    files_scanned = @($scanFiles).Count
    npm_audit = $npmAuditStatus
    composer_audit = $composerAuditStatus
    findings = $findings
}
$result | ConvertTo-Json -Depth 10 | Set-Content -Path $reportPath
$result | ConvertTo-Json -Depth 10

if ($highOrCritical.Count -gt 0) {
    throw "Security gate failed with $($highOrCritical.Count) high/critical findings. Report: $reportPath"
}
