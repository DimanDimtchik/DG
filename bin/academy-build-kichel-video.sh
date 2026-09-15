#!/usr/bin/env bash
# Kichel-Schulungsvideo: Screenshot + Render (1 Clip).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
KDIR="$ROOT/storage/media/training/kichel"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

echo "==> 1/3 Kichel-HTML exportieren"
scp bin/academy-export-kichel-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-kichel-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p "$KDIR"
scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kichel/*.html \
    allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kichel/kichel-export.meta.json \
    "$KDIR/" 2>/dev/null || true

echo "==> 2/3 Screenshots + Regionen"
capture "$KDIR/kichel-dashboard-closed.html" "$KDIR/kichel-dashboard-closed.png" "$KDIR/kichel-regions.json" \
  fab='[data-kichel-fab]'
capture "$KDIR/kichel-dashboard-open.html" "$KDIR/kichel-dashboard-open.png" "$KDIR/kichel-open-regions.json" \
  chips='.dg-kichel-chips' input='[data-kichel-input]'

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt"
  exit 1
fi

echo "==> 3/3 Video rendern"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script kichel-ueberblick --out-dir storage/media/training/kichel

echo "Fertig: $KDIR/kichel-ueberblick.mp4"
