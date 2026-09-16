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

echo "==> 4/6 Video: Neuer Termin"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script terminkalender-neuer-termin --out-dir storage/media/training/terminkalender

echo "==> 5/6 HTML exportieren (Online-Buchung)"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-terminkalender-online-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-terminkalender-online-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-terminkalender-online-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-embed.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-public-*.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-email-confirmation.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-online-export.meta.json \
      "$TDIR/" 2>/dev/null || true
fi

EMBED_FIELDS=(
  public_url='input.dg-input-copy[readonly]'
  qrcode='#dg-booking-qrcode'
)

PUBLIC_STEP1_FIELDS=(
  service='.tk-book__service'
)

PUBLIC_STEP2_FIELDS=(
  slots='.tk-book__slots'
)

PUBLIC_STEP3_FIELDS=(
  customer_name='input[name="customer_name"]'
  customer_email='input[name="customer_email"]'
  customer_phone='input[name="customer_phone"]'
  submit='#tk-book-submit'
)

EMAIL_FIELDS=(
  email_body='#dg-academy-email-frame'
)

echo "==> 6/6 Screenshots + Video: Online-Buchung"
capture "$TDIR/terminkalender-embed.html" "$TDIR/terminkalender-embed.png" "$TDIR/terminkalender-embed-regions.json" "${EMBED_FIELDS[@]}"
capture "$TDIR/terminkalender-public-step1.html" "$TDIR/terminkalender-public-step1.png" "$TDIR/terminkalender-public-step1-regions.json" "${PUBLIC_STEP1_FIELDS[@]}"
capture "$TDIR/terminkalender-public-step2.html" "$TDIR/terminkalender-public-step2.png" "$TDIR/terminkalender-public-step2-regions.json" "${PUBLIC_STEP2_FIELDS[@]}"
capture "$TDIR/terminkalender-public-step3.html" "$TDIR/terminkalender-public-step3.png" "$TDIR/terminkalender-public-step3-regions.json" "${PUBLIC_STEP3_FIELDS[@]}"
capture "$TDIR/terminkalender-public-success.html" "$TDIR/terminkalender-public-success.png"
capture "$TDIR/terminkalender-email-confirmation.html" "$TDIR/terminkalender-email-confirmation.png" "$TDIR/terminkalender-email-confirmation-regions.json" "${EMAIL_FIELDS[@]}"

python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script terminkalender-online-buchung --out-dir storage/media/training/terminkalender

echo "Fertig: $TDIR/terminkalender-ueberblick.mp4, terminkalender-neuer-termin.mp4, terminkalender-online-buchung.mp4"
