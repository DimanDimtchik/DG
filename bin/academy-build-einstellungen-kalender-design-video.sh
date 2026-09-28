#!/usr/bin/env bash
# Einstellungen → Termine → Kalender Design: Export, Screenshots, Video + VTT.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
TDIR="$ROOT/storage/media/training/einstellungen"
DASH_DIR="$ROOT/storage/media/training/allgemein"

py() {
  if [[ -n "${PYTHON:-}" ]]; then
    "$PYTHON" "$@"
  elif command -v python >/dev/null 2>&1 && python -c "import playwright" 2>/dev/null; then
    python "$@"
  else
    python3 "$@"
  fi
}

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  py bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

echo "==> 1/3 HTML exportieren (${BASE_URL})"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-einstellungen-kalender-design-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-einstellungen-kalender-design-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-einstellungen-kalender-design-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p "$TDIR"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-kalender-design.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-kalender-design-export.meta.json \
      "$TDIR/"
fi

if [[ ! -f "$DASH_DIR/dashboard-capture.png" ]]; then
  echo "Warnung: Dashboard-Screenshot fehlt ($DASH_DIR/dashboard-capture.png)" >&2
fi

echo "==> 2/3 Screenshots"
mkdir -p "$TDIR"

FIELDS=(
  presets='.dg-cal-color-presets'
  colors='.dg-cal-appearance-fields'
  primary_color='label.dg-field:has([data-color-key="primary_color"])'
  button_hover='label.dg-field:has([data-color-key="button_hover"])'
  slot_bg='label.dg-field:has([data-color-key="slot_bg"])'
  slot_hover='label.dg-field:has([data-color-key="slot_hover"])'
  slot_selected_bg='label.dg-field:has([data-color-key="slot_selected_bg"])'
  slot_selected_border='label.dg-field:has([data-color-key="slot_selected_border"])'
  booked_bg='label.dg-field:has([data-color-key="booked_bg"])'
  preview='#dg-cal-appearance-preview'
  submit='button[name="calendar_appearance_save"]'
)

capture "$TDIR/einstellungen-kalender-design.html" \
  "$TDIR/einstellungen-kalender-design.png" \
  "$TDIR/einstellungen-kalender-design-regions.json" \
  "${FIELDS[@]}"

echo "==> 3/3 Video rendern"
py bin/academy-generate-scene-video.py \
  --locale "$LOCALE" \
  --script einstellungen-kalender-design \
  --out-dir storage/media/training/einstellungen

echo "Fertig: $TDIR/einstellungen-kalender-design.mp4 (+ .vtt)"
