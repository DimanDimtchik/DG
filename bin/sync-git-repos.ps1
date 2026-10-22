#Requires -Version 5.1
<#
.SYNOPSIS
  Prüft Geschwister-Repos neben DG und klont fehlende (optional: pull bei vorhandenen).

.DESCRIPTION
  Für ein zweites Gerät: DG klonen, dann dieses Script ausführen.
  Voraussetzung: git im PATH, Zugang zu GitHub (SSH-Key oder Credential Manager).

  Liegt in DG/bin — Default-Root ist der Ordner über DG (z. B. …/Projects).

.EXAMPLE
  .\bin\sync-git-repos.ps1
  .\bin\sync-git-repos.ps1 -Root "D:\Projekte" -PullExisting
  .\bin\sync-git-repos.ps1 -WhatIf
#>
[CmdletBinding(SupportsShouldProcess)]
param(
    # Zielordner für alle Geschwister-Repos (Standard: Elternordner von DG)
    [string] $Root = '',

    # Vorhandene Repos zusätzlich auf den Remote-Stand bringen (git pull --ff-only)
    [switch] $PullExisting
)

$ErrorActionPreference = 'Stop'

# Script: …/DG/bin/sync-git-repos.ps1 → DG = Parent, Projects = Parent von DG
$DgRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = Split-Path -Parent $DgRoot
}

# Bekannte Repos (Geschwister von DG). Stand: 2026-10-22 — ohne WP-Vorstufen.
$Repos = @(
    @{ Name = 'DG';              Url = 'https://github.com/DimanDimtchik/DG.git';              Branch = 'master' }
    @{ Name = 'jahresanschluss'; Url = 'https://github.com/DimanDimtchik/jahresanschluss.git'; Branch = 'main' }
    @{ Name = 'klarwin';         Url = 'https://github.com/DimanDimtchik/klarwin.git';         Branch = 'main' }
    @{ Name = 'unfallbuch';      Url = 'https://github.com/DimanDimtchik/unfallbuch.git';      Branch = 'main' }
    @{ Name = 'WG';              Url = 'https://github.com/DimanDimtchik/WG.git';              Branch = 'main' }
)

function Test-GitAvailable {
    try {
        $null = Get-Command git -ErrorAction Stop
        return $true
    } catch {
        return $false
    }
}

if (-not (Test-GitAvailable)) {
    Write-Error 'git wurde nicht gefunden. Bitte Git for Windows installieren und PATH prüfen.'
    exit 1
}

if (-not (Test-Path -LiteralPath $Root)) {
    if ($PSCmdlet.ShouldProcess($Root, 'Ordner anlegen')) {
        New-Item -ItemType Directory -Path $Root -Force | Out-Null
        Write-Host "Ordner angelegt: $Root"
    }
}

Write-Host ''
Write-Host "Ziel: $Root"
Write-Host ('=' * 60)

$cloned = 0
$pulled = 0
$ok = 0
$skipped = 0
$failed = 0

foreach ($repo in $Repos) {
    $path = Join-Path $Root $repo.Name
    $gitDir = Join-Path $path '.git'

    Write-Host ''
    Write-Host ">>> $($repo.Name)"

    if (-not (Test-Path -LiteralPath $path)) {
        $cloneArgs = @('clone', '--branch', $repo.Branch, '--single-branch', $repo.Url, $path)
        if ($PSCmdlet.ShouldProcess($path, "git $($cloneArgs -join ' ')")) {
            try {
                & git @cloneArgs
                if ($LASTEXITCODE -ne 0) { throw "git clone Exit-Code $LASTEXITCODE" }
                Write-Host "    geklont ($($repo.Branch))" -ForegroundColor Green
                $cloned++
            } catch {
                Write-Host "    FEHLER: $_" -ForegroundColor Red
                $failed++
            }
        } else {
            $skipped++
        }
        continue
    }

    if (-not (Test-Path -LiteralPath $gitDir)) {
        Write-Host '    Ordner existiert, ist aber kein Git-Repo — übersprungen.' -ForegroundColor Yellow
        $skipped++
        continue
    }

    if ($PullExisting) {
        if ($PSCmdlet.ShouldProcess($path, 'git pull --ff-only')) {
            Push-Location $path
            try {
                & git pull --ff-only
                if ($LASTEXITCODE -ne 0) { throw "git pull Exit-Code $LASTEXITCODE" }
                Write-Host '    aktualisiert (pull)' -ForegroundColor Green
                $pulled++
            } catch {
                Write-Host "    FEHLER beim pull: $_" -ForegroundColor Red
                $failed++
            } finally {
                Pop-Location
            }
        } else {
            $skipped++
        }
    } else {
        Write-Host '    vorhanden — nichts zu tun (optional: -PullExisting)'
        $ok++
    }
}

Write-Host ''
Write-Host ('=' * 60)
Write-Host "Fertig. Geklont: $cloned | Vorhanden: $ok | Gezogen: $pulled | Übersprungen: $skipped | Fehler: $failed"
Write-Host ''
Write-Host 'Hinweis: Nur die Repos in der Liste oben. Andere lokale Ordner (z. B. Landing-Pages) ggf. manuell kopieren.'

if ($failed -gt 0) { exit 1 }
exit 0
