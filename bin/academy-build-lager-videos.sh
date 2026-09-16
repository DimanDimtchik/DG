#!/usr/bin/env bash
# Lager-Schulungsvideos: Screenshots + Render (4 Clips).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
LDIR="$ROOT/storage/media/training/lager"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

echo "==> 1/4 Lager-HTML exportieren"
scp bin/academy-export-lager-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-lager-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p "$LDIR"
scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/lager/*.html \
    allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/lager/lager-export.meta.json \
    "$LDIR/" 2>/dev/null || true

echo "==> 2/4 Screenshots + Regionen"
python3 bin/academy-capture-page.py "$LDIR/lager-overview.html" "$LDIR/lager-overview.png"
capture "$LDIR/lager-platz-check.html" "$LDIR/lager-platz-check.png" "$LDIR/lager-platz-check-regions.json" \
  scan_input='input[data-scan-input]' audit_place='#dg-audit-place'
python3 bin/academy-capture-page.py "$LDIR/lager-platz-check-demo.html" "$LDIR/lager-platz-check-demo.png"
capture "$LDIR/lager-wareneingang.html" "$LDIR/lager-wareneingang.png" "$LDIR/lager-wareneingang-regions.json" \
  scan_input='input[data-scan-input]'
capture "$LDIR/lager-warenausgang.html" "$LDIR/lager-warenausgang.png" "$LDIR/lager-warenausgang-regions.json" \
  voucher='#dg-issue-voucher'
capture "$LDIR/lager-inventur.html" "$LDIR/lager-inventur.png" "$LDIR/lager-inventur-regions.json" \
  inventory_date='input[name="inventory_date"]'
python3 bin/academy-capture-page.py "$LDIR/lager-bewegungen.html" "$LDIR/lager-bewegungen.png"

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt — bitte zuerst bin/academy-build-dashboard-video.sh ausführen"
  exit 1
fi

echo "==> 3/4 Videos rendern"
SCRIPTS=(lager-ueberblick lager-platz-check lager-ein-ausgang lager-inventur)
for script in "${SCRIPTS[@]}"; do
  echo "--- $script"
  python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script "$script" --out-dir storage/media/training/lager
done

echo "Fertig: $LDIR/lager-*.mp4"
