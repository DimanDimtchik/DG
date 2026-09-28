#!/usr/bin/env bash
# Einstellungen → Termine → Arbeitszeiten: Export, Screenshots, Video + VTT.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
TDIR="$ROOT/storage/media/training/einstellungen"
DASH_DIR="$ROOT/storage/media/training/allgemein"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  # Windows/Git-Bash: python3 oft Store-Stub — bevorzugt PYTHON/python
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
  php bin/academy-export-einstellungen-arbeitszeiten-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-einstellungen-arbeitszeiten-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-einstellungen-arbeitszeiten-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p "$TDIR"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-arbeitszeiten*.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-arbeitszeiten-export.meta.json \
      "$TDIR/"
fi

if [[ ! -f "$DASH_DIR/dashboard-capture.png" ]]; then
  echo "Warnung: Dashboard-Screenshot fehlt ($DASH_DIR/dashboard-capture.png) — Intro/Navigation brauchen ihn." >&2
fi

echo "==> 2/3 Screenshots"
mkdir -p "$TDIR"

LIST_FIELDS=(
  list='.dg-settings-main__body .dg-table-wrap'
  nav_tab='.dg-settings-nav__link.is-active'
)

FORM_FIELDS=(
  start_date='label.dg-field:has(input[name="start_date"])'
  start_time='label.dg-field:has(select[name="start_time"])'
  end_time='label.dg-field:has(select[name="end_time"])'
  weekdays='fieldset.dg-working-hours-weekdays'
  submit='button[name="working_hours_save"]'
)

capture "$TDIR/einstellungen-arbeitszeiten.html" \
  "$TDIR/einstellungen-arbeitszeiten.png" \
  "$TDIR/einstellungen-arbeitszeiten-regions.json" \
  "${LIST_FIELDS[@]}"

capture "$TDIR/einstellungen-arbeitszeiten-neu.html" \
  "$TDIR/einstellungen-arbeitszeiten-neu.png" \
  "$TDIR/einstellungen-arbeitszeiten-neu-regions.json" \
  "${FORM_FIELDS[@]}"

echo "==> 3/3 Video rendern"
if [[ -n "${PYTHON:-}" ]]; then
  "$PYTHON" bin/academy-generate-scene-video.py --locale "$LOCALE" --script einstellungen-arbeitszeiten --out-dir storage/media/training/einstellungen
elif command -v python >/dev/null 2>&1; then
  python bin/academy-generate-scene-video.py --locale "$LOCALE" --script einstellungen-arbeitszeiten --out-dir storage/media/training/einstellungen
else
  python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script einstellungen-arbeitszeiten --out-dir storage/media/training/einstellungen
fi

echo "Fertig: $TDIR/einstellungen-arbeitszeiten.mp4 (+ .vtt)"
