# Academy: Kassenbuch, OPOS, GuV, Steuerberater-Export, Statistik
# Usage (PowerShell from repo root):
#   .\bin\academy-build-buchhaltung-kacheln-videos.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $Root "bin\academy-export-buchhaltung-kacheln-html.php"))) {
  $Root = $PSScriptRoot + "\.."
}
Set-Location $Root

$Key = Join-Path $env:USERPROFILE ".ssh\id_ed25519_ganzom"
$Remote = "ssh-w0217246@dg.ganz-om.de"
$Inst = "ganz-soft.de"
$Base = "https://ganz-soft.de"
$UserId = "9"
$FfmpegDir = Split-Path (Get-Command ffmpeg -ErrorAction SilentlyContinue).Source -ErrorAction SilentlyContinue
if ($FfmpegDir) { $env:Path = "$FfmpegDir;$env:Path" }

Write-Host "==> 1/4 Export-HTML auf Server"
scp -i $Key "bin\academy-export-buchhaltung-kacheln-html.php" "${Remote}:www/htdocs/w0217246/$Inst/bin/"
scp -i $Key "bin\academy-setup-buchhaltung-kacheln-course.php" "${Remote}:www/htdocs/w0217246/$Inst/bin/"
ssh -i $Key $Remote "cd /www/htdocs/w0217246/$Inst && php bin/academy-export-buchhaltung-kacheln-html.php --base=$Base/ --user-id=$UserId"

$dirs = @(
  @{ local = "kassenbuch"; remote = "kassenbuch"; html = "kassenbuch-page.html"; png = "kassenbuch-page.png"; regions = "kassenbuch-page-regions.json"; script = "kassenbuch-ueberblick" },
  @{ local = "opos"; remote = "opos"; html = "opos-page.html"; png = "opos-page.png"; regions = "opos-page-regions.json"; script = "opos-ueberblick" },
  @{ local = "guv"; remote = "guv"; html = "guv-page.html"; png = "guv-page.png"; regions = "guv-page-regions.json"; script = "guv-ueberblick" },
  @{ local = "steuerberater-export"; remote = "steuerberater-export"; html = "steuerberater-export-page.html"; png = "steuerberater-export-page.png"; regions = "steuerberater-export-page-regions.json"; script = "steuerberater-export-ueberblick" },
  @{ local = "statistik"; remote = "statistik"; html = "statistik-page.html"; png = "statistik-page.png"; regions = "statistik-page-regions.json"; script = "statistik-ueberblick" }
)

Write-Host "==> 2/4 HTML holen + Screenshots"
foreach ($d in $dirs) {
  $out = Join-Path $Root "storage\media\training\$($d.local)"
  New-Item -ItemType Directory -Force -Path $out | Out-Null
  scp -i $Key "${Remote}:www/htdocs/w0217246/$Inst/storage/media/training/$($d.remote)/$($d.html)" (Join-Path $out $d.html)
  python bin\academy-capture-page.py (Join-Path $out $d.html) (Join-Path $out $d.png) (Join-Path $out $d.regions)
}

if (-not (Test-Path "storage\media\training\allgemein\dashboard-capture.png")) {
  throw "Dashboard-Screenshot fehlt"
}

Write-Host "==> 3/4 Videos rendern"
foreach ($d in $dirs) {
  Write-Host "--- $($d.script)"
  python bin\academy-generate-scene-video.py --locale de --script $d.script --out-dir "storage/media/training/$($d.local)"
}

Write-Host "==> 4/4 Media + Setup auf Server"
foreach ($d in $dirs) {
  $out = "storage/media/training/$($d.local)"
  scp -i $Key "$out/$($d.script).mp4" "$out/$($d.script).vtt" "$out/$($d.script).meta.json" "${Remote}:www/htdocs/w0217246/$Inst/storage/media/training/$($d.remote)/"
  scp -i $Key "$out/$($d.png)" "$out/$($d.regions)" "${Remote}:www/htdocs/w0217246/$Inst/storage/media/training/$($d.remote)/"
  # Master + kontur
  foreach ($other in @("dg.ganz-om.de", "kontur-cosmetics.de")) {
    scp -i $Key "$out/$($d.script).mp4" "$out/$($d.script).vtt" "$out/$($d.script).meta.json" "${Remote}:www/htdocs/w0217246/$other/storage/media/training/$($d.remote)/" 2>$null
  }
}
ssh -i $Key $Remote "cd /www/htdocs/w0217246/$Inst && php bin/academy-setup-buchhaltung-kacheln-course.php"
foreach ($other in @("dg.ganz-om.de", "kontur-cosmetics.de")) {
  scp -i $Key "bin\academy-setup-buchhaltung-kacheln-course.php" "${Remote}:www/htdocs/w0217246/$other/bin/"
  ssh -i $Key $Remote "mkdir -p /www/htdocs/w0217246/$other/storage/media/training/{kassenbuch,opos,guv,steuerberater-export,statistik}; cd /www/htdocs/w0217246/$other && php bin/academy-setup-buchhaltung-kacheln-course.php" 2>&1
}

Write-Host "Fertig."
