#!/usr/bin/env bash
# Bereiche + Mitglieder: Export, Screenshots, beide Videos.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
TDIR="$ROOT/storage/media/training/einstellungen"
py() {
  if [[ -n "${PYTHON:-}" ]]; then "$PYTHON" "$@"
  elif command -v python >/dev/null 2>&1 && python -c "import playwright" 2>/dev/null; then python "$@"
  else python3 "$@"; fi
}
capture() { local h="$1" p="$2" r="$3"; shift 3; py bin/academy-capture-page.py "$h" "$p" "$r" "$@"; }

echo "==> Export"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-einstellungen-kalender-team-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-einstellungen-kalender-team-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-einstellungen-kalender-team-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p "$TDIR"
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-kalender-bereiche.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-kalender-mitglieder.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/einstellungen/einstellungen-kalender-team-export.meta.json \
      "$TDIR/"
fi

mkdir -p "$TDIR"
echo "==> Capture Bereiche"
capture "$TDIR/einstellungen-kalender-bereiche.html" "$TDIR/einstellungen-kalender-bereiche.png" "$TDIR/einstellungen-kalender-bereiche-regions.json" \
  list='.dg-cal-staff-table' form='#dg-calendar-area-form' \
  name='label.dg-field:has(#dg_area_name)' department_id='label.dg-field:has(#dg_area_department)' \
  sort_order='label.dg-field:has(#dg_area_sort)' is_active='label.dg-field:has(#dg_area_active)' \
  submit='#dg-area-submit'

echo "==> Capture Mitglieder"
capture "$TDIR/einstellungen-kalender-mitglieder.html" "$TDIR/einstellungen-kalender-mitglieder.png" "$TDIR/einstellungen-kalender-mitglieder-regions.json" \
  list='.dg-cal-staff-table' form='#dg-calendar-employee-form' \
  contact_id='label.dg-field:has(#dg_employee_contact)' name='label.dg-field:has(#dg_employee_name)' \
  area_ids='.dg-cal-area-checkboxes' sort_order='label.dg-field:has(#dg_employee_sort)' \
  user_id='label.dg-field:has(#dg_employee_user)' supervisor_id='label.dg-field:has(#dg_employee_supervisor)' \
  is_active='label.dg-field:has(#dg_employee_active)' submit='#dg-employee-submit'

# absences selector may be weak — also capture section via page sections JS
echo "==> Render"
for slug in einstellungen-kalender-bereiche einstellungen-kalender-mitglieder; do
  py bin/academy-generate-scene-video.py --locale "$LOCALE" --script "$slug" --out-dir storage/media/training/einstellungen
done
echo "Fertig."
