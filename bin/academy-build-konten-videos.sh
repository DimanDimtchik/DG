#!/usr/bin/env bash
# Konten-Schulungsvideos: Screenshots + Render (3 Clips).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
KDIR="$ROOT/storage/media/training/konten"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

echo "==> 1/4 Konten-HTML exportieren"
scp bin/academy-export-konten-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-konten-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p "$KDIR"
scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/konten/*.html \
    allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/konten/konten-export.meta.json \
    "$KDIR/" 2>/dev/null || true

echo "==> 2/4 Screenshots + Regionen"
capture "$KDIR/konten-search.html" "$KDIR/konten-search.png" "$KDIR/konten-search-regions.json" \
  account_number='#dg-account-number' account_search='#dg-account-search'
capture "$KDIR/konten-hint-8400.html" "$KDIR/konten-hint-8400.png" "$KDIR/konten-hint-regions.json" \
  search_terms='#dg-account-hint-search-tags' digits='#dg-account-hint-digits' examples='#dg-account-hint-examples-wrap'
python3 bin/academy-capture-page.py "$KDIR/kontenuebersicht.html" "$KDIR/kontenuebersicht.png"

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt"
  exit 1
fi

echo "==> 3/4 Videos rendern"
SCRIPTS=(konten-ueberblick konten-hinweise konten-kontenuebersicht)
for script in "${SCRIPTS[@]}"; do
  echo "--- $script"
  python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script "$script" --out-dir storage/media/training/konten
done

echo "Fertig: $KDIR/konten-*.mp4"
