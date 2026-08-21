#!/bin/bash
# Auf dem All-Inkl-Server ausführen (nach Code-Deploy):
#   bash bin/run-website-bootstrap-on-instances.sh
#
# Legt Pflichtseiten auf allen CRM-Instanzen an bzw. aktualisiert sie mit --overwrite.
set -euo pipefail

ROOT="${CRM_ROOT:-/www/htdocs/[REDACTED]}"
OVERWRITE=""
if [ "${1:-}" = "--overwrite" ]; then
  OVERWRITE="--overwrite"
fi

run_one() {
  local dir="$1"
  local label="$2"
  if [ ! -f "$dir/index.php" ] || [ ! -f "$dir/bin/seed-website-defaults.php" ]; then
    echo "SKIP $label (kein CRM oder seed-Skript fehlt)"
    return
  fi
  echo "=== $label ($dir) ==="
  (cd "$dir" && php bin/seed-website-defaults.php $OVERWRITE)
  echo
}

echo "CRM-Root: $ROOT"
echo

# Live ganz-soft.de (Document-Root)
run_one "$ROOT" "ganz-soft.de (Live-Root)"

# Weitere Instanzen
run_one "$ROOT/dg.ganz-om.de" "dg.ganz-om.de (Master)"
run_one "$ROOT/kontur-cosmetics.de" "kontur-cosmetics.de"

echo "Fertig."
