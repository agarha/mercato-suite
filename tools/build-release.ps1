$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$plugin = Join-Path $root "apps\wordpress\wp-content\plugins\mercato-suite"
$dist = Join-Path $root "dist"
$main = Join-Path $plugin "mercato-suite.php"

if (!(Test-Path $main)) {
    throw "Plugin entrypoint not found: $main"
}

$pluginHeader = Get-Content $main -Raw
if ($pluginHeader -notmatch "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
    throw "Unable to determine plugin version from mercato-suite.php"
}

$version = $Matches[1]
$artifact = Join-Path $dist "mercato-suite-$version.zip"
$staging = Join-Path $dist "mercato-suite"

Remove-Item -LiteralPath $staging -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $dist | Out-Null
Copy-Item -Path $plugin -Destination $staging -Recurse -Force

$excluded = @(".git", ".DS_Store", "node_modules", "vendor", ".phpunit.cache")
foreach ($name in $excluded) {
    Get-ChildItem -Path $staging -Recurse -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -eq $name } |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

Remove-Item -LiteralPath $artifact -Force -ErrorAction SilentlyContinue
Compress-Archive -Path $staging -DestinationPath $artifact -Force

$zip = [System.IO.Compression.ZipFile]::OpenRead($artifact)
try {
    $required = @(
        "mercato-suite/mercato-suite.php",
        "mercato-suite/modules/mercato-core/module.json",
        "mercato-suite/modules/mercato-vendors/module.json",
        "mercato-suite/modules/mercato-stripe-connect/module.json",
        "mercato-suite/assets/js/mercato-admin.js",
        "mercato-suite/assets/css/mercato-admin.css"
    )
    $entries = @($zip.Entries | ForEach-Object { $_.FullName.Replace("\", "/") })
    foreach ($entry in $required) {
        if ($entries -notcontains $entry) {
            throw "Release artifact is missing $entry"
        }
    }
} finally {
    $zip.Dispose()
}

$hash = Get-FileHash -Algorithm SHA256 -Path $artifact
[pscustomobject]@{
    artifact = $artifact
    version = $version
    sha256 = $hash.Hash
    size_bytes = (Get-Item $artifact).Length
} | ConvertTo-Json -Depth 3
