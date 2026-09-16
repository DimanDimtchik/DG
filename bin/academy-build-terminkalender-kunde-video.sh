#!/usr/bin/env bash
# Endkunden-Video: nur öffentliche Online-Terminbuchung (ohne Admin/Einstellungen).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
LOCALE="${LOCALE:-de}"
TDIR="$ROOT/storage/media/training/terminkalender"

capture() {
  local html="$1" png="$2" regions="${3:-}"
  if [[ $# -ge 3 ]]; then shift 3; else shift 2; fi
  if [[ -n "$regions" ]]; then
    python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
  else
    python3 bin/academy-capture-page.py "$html" "$png" "$@"
  fi
}

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

echo "==> 1/3 HTML exportieren (Endkunden-Ansicht, ${BASE_URL})"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-terminkalender-kunde-html.php --base="${BASE_URL}/"
else
  scp bin/academy-export-terminkalender-kunde-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-terminkalender-kunde-html.php --base=${BASE_URL}/"
  mkdir -p "$TDIR"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-public-*.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-email-confirmation.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/terminkalender/terminkalender-kunde-export.meta.json \
      "$TDIR/" 2>/dev/null || true
fi

echo "==> 2/3 Screenshots (öffentliche Buchung + E-Mail)"
mkdir -p "$TDIR"
capture "$TDIR/terminkalender-public-step1.html" "$TDIR/terminkalender-public-step1.png" "$TDIR/terminkalender-public-step1-regions.json" "${PUBLIC_STEP1_FIELDS[@]}"
capture "$TDIR/terminkalender-public-step2.html" "$TDIR/terminkalender-public-step2.png" "$TDIR/terminkalender-public-step2-regions.json" "${PUBLIC_STEP2_FIELDS[@]}"
capture "$TDIR/terminkalender-public-step3.html" "$TDIR/terminkalender-public-step3.png" "$TDIR/terminkalender-public-step3-regions.json" "${PUBLIC_STEP3_FIELDS[@]}"
capture "$TDIR/terminkalender-public-success.html" "$TDIR/terminkalender-public-success.png"
capture "$TDIR/terminkalender-email-confirmation.html" "$TDIR/terminkalender-email-confirmation.png" "$TDIR/terminkalender-email-confirmation-regions.json" "${EMAIL_FIELDS[@]}"

echo "==> 3/3 Video rendern: terminkalender-online-kunde"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script terminkalender-online-kunde --out-dir storage/media/training/terminkalender

echo "Fertig: $TDIR/terminkalender-online-kunde.mp4 (+ .vtt)"
