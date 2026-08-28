#!/bin/bash
# Platzhalter/Wartungsmodus nach ganz-om.de deployen.
# Voraussetzung: SSH-Alias allinkl-ganzom, Master-Code auf dg.ganz-om.de aktuell.
set -euo pipefail

ROOT="${CRM_ROOT:-/www/htdocs/w0217246}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$ROOT/ganz-om.de"

echo "Deploy ganz-om.de Platzhalter → $TARGET"

ssh allinkl-ganzom "mkdir -p '$TARGET/config'"

scp "$SRC/sites/ganz-om.de/index.php" \
    "$SRC/sites/ganz-om.de/bootstrap.php" \
    "$SRC/sites/ganz-om.de/.htaccess" \
    allinkl-ganzom:"$TARGET/"

scp "$SRC/sites/ganz-om.de/config/site-maintenance.php" \
    allinkl-ganzom:"$TARGET/config/"

echo "OK — prüfen: curl -sI https://ganz-om.de/login | head -5"
