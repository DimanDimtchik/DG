#!/usr/bin/env bash
# Akademie-Schulungsvideos: Export → Capture → Render (2 Clips).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
ADIR="$ROOT/storage/media/training/akademie"
REMOTE="${REMOTE:-allinkl-ganzom}"
REMOTE_DIR="${REMOTE_DIR:-/www/htdocs/w0217246/ganz-soft.de}"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  if [[ $# -gt 0 ]]; then
    python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
  else
    python3 bin/academy-capture-page.py "$html" "$png" "$regions"
  fi
}

echo "==> 1/4 Akademie-HTML exportieren"
scp bin/academy-export-akademie-html.php "$REMOTE:$REMOTE_DIR/bin/"
ssh "$REMOTE" "cd $REMOTE_DIR && php bin/academy-export-akademie-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p "$ADIR"
scp "$REMOTE:$REMOTE_DIR/storage/media/training/akademie/"*.html \
    "$REMOTE:$REMOTE_DIR/storage/media/training/akademie/akademie-export.meta.json" \
    "$ADIR/" 2>/dev/null || true

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt (storage/media/training/allgemein/dashboard-capture.png)"
  exit 1
fi

echo "==> 2/4 Screenshots + Regionen"
capture "$ADIR/akademie-meine.html" "$ADIR/akademie-meine.png" "$ADIR/akademie-meine-regions.json"
capture "$ADIR/akademie-katalog.html" "$ADIR/akademie-katalog.png" "$ADIR/akademie-katalog-regions.json"
capture "$ADIR/akademie-kurs-regeln.html" "$ADIR/akademie-kurs-regeln.png" "$ADIR/akademie-kurs-regeln-regions.json" \
  rules='.dg-academy-rules'
capture "$ADIR/akademie-kurs-module.html" "$ADIR/akademie-kurs-module.png" "$ADIR/akademie-kurs-module-regions.json"
if [[ -f "$ADIR/akademie-modul-player.html" ]]; then
  capture "$ADIR/akademie-modul-player.html" "$ADIR/akademie-modul-player.png" "$ADIR/akademie-modul-player-regions.json" \
    complete='#dg-academy-complete-btn'
fi

echo "==> 3/4 Videos rendern"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script akademie-ueberblick --out-dir storage/media/training/akademie
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script akademie-kurs-lernen --out-dir storage/media/training/akademie

echo "==> 4/4 Kurs registrieren (lokal, dann auf Server)"
if command -v php >/dev/null 2>&1; then
  php bin/academy-setup-akademie-course.php || true
fi

echo "Fertig:"
echo "  $ADIR/akademie-ueberblick.mp4"
echo "  $ADIR/akademie-kurs-lernen.mp4"
echo "Danach: Medien auf Master deployen und php bin/academy-setup-akademie-course.php auf ganz-soft.de / Master."
