#!/usr/bin/env bash
# Terminkalender-Schulungsvideos: Screenshots + Render (Überblick + Neuer Termin).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
TDIR="$ROOT/storage/media/training/terminkalender"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

LIST_FIELDS=(
  search='#tk-search'
)

NEW_FIELDS=(
  article='#dg-booking-article'
  employee='#dg-booking-employee'
  slot_date='#dg-booking-slot-date'
  slot_time='#dg-booking-slot-time'
  status='#dg-booking-status'
  customer='#dg-booking-customer-search'
  customer_email='#dg-booking-customer-email'
  customer_phone='#dg-booking-customer-phone'
  admin_notes='textarea[name="admin_notes"]'
)

echo "==> 1/4 HTML exportieren (${BASE_URL})"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-terminkalender-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-terminkalender-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-terminkalender-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p "$TDIR"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/*.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-export.meta.json \
      "$TDIR/"
fi

echo "==> 2/4 Screenshots"
mkdir -p "$TDIR"
capture "$TDIR/terminkalender-list.html" "$TDIR/terminkalender-list.png" "$TDIR/terminkalender-list-regions.json" "${LIST_FIELDS[@]}"
capture "$TDIR/terminkalender-new.html" "$TDIR/terminkalender-new.png" "$TDIR/terminkalender-new-regions.json" "${NEW_FIELDS[@]}"

echo "==> 3/4 Video: Überblick"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script terminkalender-ueberblick --out-dir storage/media/training/terminkalender

echo "==> 4/4 Video: Neuer Termin"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script terminkalender-neuer-termin --out-dir storage/media/training/terminkalender

echo "Fertig: $TDIR/terminkalender-ueberblick.mp4 und terminkalender-neuer-termin.mp4"
