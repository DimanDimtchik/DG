#!/usr/bin/env bash
# Kontakte-Schulungsvideos: Screenshots + Render (Überblick + Felder).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"

FIELD_SELECTORS=(
  'input[name="login"]'
  'select[name="salutation"]'
  'input[name="first_name"]'
  'input[name="last_name"]'
  'input[name="display_name"]'
  'input[name="company_name"]'
  'select[name="contact_role"]'
  'input[name="customer_number"]'
  'input[name="supplier_number"]'
  'input[name="supplier_customer_number"]'
  'input[name="tax_number"]'
  'input[name="vat_id"]'
  'input[name="commercial_register"]'
  'input[name="weee_registration"]'
  'input[name="email"]'
  'input[name="email_2"]'
  'input[name="phone_1"]'
  'input[name="phone_2"]'
  'input[name="website"]'
  'input[name="address1_street"]'
  'input[name="address1_extra"]'
  'input[name="address1_city"]'
  'input[name="address1_postal"]'
  'input[name="address1_country"]'
)

echo "==> 1/5 Kontakte-HTML exportieren"
scp bin/academy-export-kontakte-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-kontakte-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p storage/media/training/kontakte
scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kontakte/*.html \
    allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kontakte/kontakte-export.meta.json \
    storage/media/training/kontakte/ 2>/dev/null || true

echo "==> 2/5 Screenshots"
KDIR="$ROOT/storage/media/training/kontakte"
python3 bin/academy-capture-page.py "$KDIR/kontakte-list.html" "$KDIR/kontakte-list.png"
python3 bin/academy-capture-page.py "$KDIR/kontakte-new.html" "$KDIR/kontakte-new.png"
python3 bin/academy-capture-page.py "$KDIR/kontakte-edit.html" "$KDIR/kontakte-edit.png" \
  "$KDIR/kontakte-edit-regions.json" "${FIELD_SELECTORS[@]}"

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt — bitte zuerst bin/academy-build-dashboard-video.sh (Schritt Capture) ausführen"
  exit 1
fi

echo "==> 3/5 Video: Kontakte Überblick"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script kontakte-ueberblick

echo "==> 4/5 Video: Kontakte Felder"
python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script kontakte-felder

echo "==> 5/5 Fertig — storage/media/training/kontakte/kontakte-ueberblick.mp4 + kontakte-felder.mp4"
