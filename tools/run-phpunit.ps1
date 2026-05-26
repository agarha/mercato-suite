$ErrorActionPreference = "Stop"

$php = Get-Command php -ErrorAction Stop
$phpRoot = Split-Path -Parent $php.Source
$extensionDir = Join-Path $phpRoot "ext"
$phpunit = Join-Path $PSScriptRoot ".cache/phpunit-10.phar"

if (!(Test-Path $phpunit)) {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $phpunit) | Out-Null
    Invoke-WebRequest -Uri "https://phar.phpunit.de/phpunit-10.phar" -UseBasicParsing -OutFile $phpunit
}

& php `
    -d "extension_dir=$extensionDir" `
    -d "extension=mbstring" `
    $phpunit `
    --configuration (Join-Path (Split-Path -Parent $PSScriptRoot) "phpunit.xml.dist")
