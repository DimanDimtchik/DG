#!/usr/bin/env bash
# Recruiting Überblick: HTML-Export, Screenshots, Video + VTT.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-om.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
REMOTE="${REMOTE:-allinkl-ganzom}"
REMOTE_DIR="${REMOTE_DIR:-/www/htdocs/w0217246/ganz-om.de}"
TDIR="$ROOT/storage/media/training/recruiting"
DASH_DIR="$ROOT/storage/media/training/allgemein"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  if [[ -n "${PYTHON:-}" ]]; then
    "$PYTHON" bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
  elif command -v python >/dev/null 2>&1 && python -c "import playwright" 2>/dev/null; then
    python bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
  else
    python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
  fi
}

echo "==> 1/3 HTML exportieren (${BASE_URL})"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-recruiting-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-recruiting-html.php "$REMOTE:$REMOTE_DIR/bin/"
  # Views/Repos für Export mitziehen, falls Server noch älter ist
  scp views/modules/hr-stellen.php views/modules/hr-stelle-form.php \
      views/modules/hr-bewerber.php views/modules/hr-bewerber-form.php \
      "$REMOTE:$REMOTE_DIR/views/modules/"
  scp src/Recruiting/JobApplicationRepository.php src/Recruiting/JobPostingRepository.php \
      "$REMOTE:$REMOTE_DIR/src/Recruiting/"
  ssh "$REMOTE" "cd $REMOTE_DIR && php bin/academy-export-recruiting-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p "$TDIR"
  scp "$REMOTE:$REMOTE_DIR/storage/media/training/recruiting/hr-"*.html \
      "$REMOTE:$REMOTE_DIR/storage/media/training/recruiting/recruiting-export.meta.json" \
      "$TDIR/"
fi

if [[ ! -f "$DASH_DIR/dashboard-capture.png" ]]; then
  echo "Warnung: Dashboard-Screenshot fehlt ($DASH_DIR/dashboard-capture.png)" >&2
fi

echo "==> 2/3 Screenshots"
mkdir -p "$TDIR"

capture "$TDIR/hr-stellen.html" "$TDIR/hr-stellen.png" "$TDIR/hr-stellen-regions.json" \
  list='.dg-table-wrap' \
  toolbar='.dg-page-header__actions'

capture "$TDIR/hr-stelle-form.html" "$TDIR/hr-stelle-form.png" "$TDIR/hr-stelle-form-regions.json" \
  form='form.dg-form' \
  export='.dg-form-section:nth-of-type(2)'

capture "$TDIR/hr-bewerber.html" "$TDIR/hr-bewerber.png" "$TDIR/hr-bewerber-regions.json" \
  list='.dg-table-wrap'

capture "$TDIR/hr-bewerber-form.html" "$TDIR/hr-bewerber-form.png" "$TDIR/hr-bewerber-form-regions.json" \
  form='form.dg-form' \
  status='label.dg-field:has(select[name="status"])'

echo "==> 3/3 Video rendern"
if [[ -n "${PYTHON:-}" ]]; then
  "$PYTHON" bin/academy-generate-scene-video.py --locale "$LOCALE" --script recruiting-ueberblick --out-dir storage/media/training/recruiting
elif command -v python >/dev/null 2>&1; then
  python bin/academy-generate-scene-video.py --locale "$LOCALE" --script recruiting-ueberblick --out-dir storage/media/training/recruiting
else
  python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script recruiting-ueberblick --out-dir storage/media/training/recruiting
fi

php bin/academy-build-training-catalog.php

echo "Fertig: $TDIR/recruiting-ueberblick.mp4 (+ .vtt)"
