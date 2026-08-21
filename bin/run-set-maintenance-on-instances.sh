#!/bin/bash
# Wartungsmodus auf CRM-Instanzen setzen.
#   bash bin/run-set-maintenance-on-instances.sh --off
#   bash bin/run-set-maintenance-on-instances.sh --on
set -euo pipefail

ROOT="${CRM_ROOT:-/www/htdocs/w0217246}"
FLAG="${1:---off}"

run_one() {
  local dir="$1"
  local label="$2"
  if [ ! -f "$dir/index.php" ] || [ ! -f "$dir/bin/set-website-maintenance.php" ]; then
    echo "SKIP $label"
    return
  fi
  echo "=== $label ==="
  (cd "$dir" && php bin/set-website-maintenance.php "$FLAG")
  echo
}

echo "Flag: $FLAG"
run_one "$ROOT" "ganz-soft.de (Live-Root)"
run_one "$ROOT/dg.ganz-om.de" "dg.ganz-om.de (Master)"
run_one "$ROOT/kontur-cosmetics.de" "kontur-cosmetics.de"
echo "Fertig."
