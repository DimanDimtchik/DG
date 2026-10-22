#Requires -Version 5.1
<#
.SYNOPSIS
  L??dt das DG-CRM per scp/ssh auf dg.ganz-om.de hoch.

.DESCRIPTION
  Ruft deploy.bat auf (kein cmd /c, kein verschachteltes PowerShell, keine Downloads).
  Kaspersky PDM kann Deploy-Skripte f??lschlich als Trojan.Win32.Generic melden ???
  dann deploy.bat direkt nutzen oder den Projektordner als Ausnahme eintragen.

.PARAMETER Migrate
  Nach dem Upload php bin/db-migrate.php auf dem Server ausf??hren.
#>
[CmdletBinding()]
param(
    [switch]$Migrate
)

$ErrorActionPreference = 'Stop'
$Root = $PSScriptRoot
$DeployBat = Join-Path $Root 'deploy.bat'

if (-not (Test-Path -LiteralPath $DeployBat)) {
    Write-Error "deploy.bat nicht gefunden: $DeployBat"
    exit 1
}

Write-Host 'Deploying DG CRM to dg.ganz-om.de (via deploy.bat) ...'

& $DeployBat
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Deploy fehlgeschlagen.'
    exit $LASTEXITCODE
}

if ($Migrate) {
    $Key = Join-Path $env:USERPROFILE '.ssh\id_ed25519_ganzom'
    $SshTarget = 'ssh-w0217246@dg.ganz-om.de'
    $RemoteDir = 'www/htdocs/w0217246/dg.ganz-om.de'

    Write-Host 'Running database migrations ...'
    & ssh -o BatchMode=yes -i $Key $SshTarget "cd $RemoteDir && php bin/db-migrate.php"
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'Migration fehlgeschlagen.'
        exit $LASTEXITCODE
    }
}

Write-Host 'CRM upload complete.'
exit 0
