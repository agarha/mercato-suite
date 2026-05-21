$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$plugin = Join-Path $root "apps\wordpress\wp-content\plugins\mercato-suite"
$dist = Join-Path $root "dist"

New-Item -ItemType Directory -Force -Path $dist | Out-Null
Compress-Archive -Path $plugin -DestinationPath (Join-Path $dist "mercato-suite-0.1.0.zip") -Force
