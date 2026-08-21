<#
.SYNOPSIS
  Builds a release ZIP for the DG CRM update server.
  Prefer server-side: php bin/build-release.php (Unix paths).
  This Windows helper builds locally; UpdateChecker normalizes backslash paths.
  Usage:  .\bin\build-release.ps1 [-Upload]
#>
param(
    [switch]$Upload
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$version = (Get-Content "$root\config\version.php" -Raw) -replace "(?s).*return\s+'([^']+)'.*", '$1'

Write-Host "Building release v$version ..." -ForegroundColor Cyan
Write-Host "Hinweis: Fuer den Update-Server besser auf dem Server: php bin/build-release.php" -ForegroundColor Yellow

$tmpDir = Join-Path $env:TEMP "dg-crm-release"
$zipName = "dg-crm-$version.zip"
$zipPath = Join-Path $root "bin\$zipName"

if (Test-Path $tmpDir) { Remove-Item $tmpDir -Recurse -Force }
New-Item -ItemType Directory -Path $tmpDir | Out-Null

$codeDirs = @('src', 'views', 'assets', 'database', 'bin')
foreach ($dir in $codeDirs) {
    $src = Join-Path $root $dir
    if (Test-Path $src) {
        Copy-Item $src -Destination (Join-Path $tmpDir $dir) -Recurse
    }
}

$codeFiles = @('index.php', 'bootstrap.php', '.htaccess')
foreach ($f in $codeFiles) {
    $src = Join-Path $root $f
    if (Test-Path $src) {
        Copy-Item $src -Destination (Join-Path $tmpDir $f)
    }
}

$configDir = Join-Path $tmpDir 'config'
New-Item -ItemType Directory -Path $configDir -Force | Out-Null
Copy-Item "$root\config\version.php" -Destination "$configDir\version.php"
Copy-Item "$root\config\app.php" -Destination "$configDir\app.php"
Copy-Item "$root\config\database.php" -Destination "$configDir\database.php"

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path "$tmpDir\*" -DestinationPath $zipPath -CompressionLevel Optimal

Remove-Item $tmpDir -Recurse -Force

$size = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host "Created $zipName ($size MB)" -ForegroundColor Green

if ($Upload) {
    $sshKey = "$env:USERPROFILE\.ssh\id_ed25519_ganzom"
    $sshHost = "ssh-w0217246@w0217246.kasserver.com"
    $remotePath = "/www/htdocs/w0217246/dg.ganz-om.de/update/releases/$zipName"

    Write-Host "Uploading to $remotePath ..." -ForegroundColor Cyan
    scp -i $sshKey $zipPath "${sshHost}:${remotePath}"

    $versionJson = @"
{
    "version": "$version",
    "url": "https://dg.ganz-om.de/update/releases/$zipName",
    "critical": false,
    "released": "$(Get-Date -Format 'yyyy-MM-dd')",
    "notes": ""
}
"@
    $versionJson | ssh -o BatchMode=yes -i $sshKey $sshHost "cat > /www/htdocs/w0217246/dg.ganz-om.de/update/version.json"

    Write-Host "Release v$version published!" -ForegroundColor Green
}
