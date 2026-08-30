#!/usr/bin/env bash
# Diagnose + Reparatur CRM-Instanzen auf All-Inkl (Lizenz, Wartungsmodus-Hinweis).
# Auf dem Server ausführen: ssh allinkl-ganzom 'bash -s' < bin/repair-crm-instances.sh
# Oder nach SSH: cd .../dg.ganz-om.de && bash bin/repair-crm-instances.sh
set -euo pipefail

ROOT=/www/htdocs/w0217246
INSTANCES=(
  "dg.ganz-om.de|Master"
  "ganz-soft.de|Live ganz-soft"
  "kontur-cosmetics.de|Kontur"
  "ganz-om.de|Platzhalter ganz-om"
)
LICENSE_SERVER="$ROOT/dg-user.ganz-soft.de"
REFERENCE_LICENSE="$ROOT/ganz-soft.de/config/license.php"

echo "=== CRM-Instanzen Diagnose ($(date -Iseconds)) ==="

check_license_server() {
  echo
  echo "--- Lizenzserver dg-user.ganz-soft.de ---"
  if [ ! -d "$LICENSE_SERVER" ]; then
    echo "FEHLT: Verzeichnis $LICENSE_SERVER"
    return
  fi
  if [ ! -f "$LICENSE_SERVER/config.php" ]; then
    echo "FEHLT: $LICENSE_SERVER/config.php (DB-Zugang) — Ursache für HTTP 500 bei /check"
    echo "  → config.example.php kopieren, DB aus KAS eintragen (eigene DB für Lizenzserver)"
  else
    echo "OK: config.php vorhanden"
  fi
  code=$(curl -s -o /dev/null -w '%{http_code}' -m 10 -X POST \
    -H 'Content-Type: application/json' \
    -d '{"domain":"ganz-soft.de","license_key":"GS-TEST-TEST-TEST-TEST","version":"1.0.0"}' \
    https://dg-user.ganz-soft.de/check 2>/dev/null || echo "000")
  echo "HTTP /check (Test): $code (200/JSON erwartet, 500 = Server/DB kaputt)"
}

for entry in "${INSTANCES[@]}"; do
  dir="${entry%%|*}"
  label="${entry#*|}"
  path="$ROOT/$dir"
  echo
  echo "--- $label ($dir) ---"
  if [ ! -f "$path/index.php" ]; then
    echo "FEHLT: $path/index.php — Instanz unvollständig"
    continue
  fi
  if [ ! -f "$path/config/database.local.php" ]; then
    echo "WARN: keine database.local.php — Platzhalter ohne CRM-DB?"
  else
    echo "OK: database.local.php"
  fi
  if [ ! -f "$path/config/license.php" ]; then
    echo "FEHLT: config/license.php → Lizenzfehler im Browser"
    if [ -f "$REFERENCE_LICENSE" ] && [ "$dir" != "ganz-soft.de" ]; then
      echo "  Hinweis: Lizenzschlüssel ist pro Domain — nicht blind von ganz-soft kopieren!"
      echo "  Key aus KDV / license-server DB für domain=$dir eintragen."
    fi
  else
    key=$(php -r '$c=require $argv[1]; echo trim((string)($c["key"]??""));' "$path/config/license.php" 2>/dev/null || true)
    if [ -z "$key" ]; then
      echo "FEHLT: license.php ohne key"
    else
      echo "OK: license.php (Key-Länge ${#key})"
    fi
  fi
  if [ -f "$path/storage/license_state.json" ]; then
    echo "OK: storage/license_state.json"
  else
    echo "INFO: kein license_state.json (Grace-Period neu nach erstem Check)"
  fi
  pub=$(curl -s -o /dev/null -w '%{http_code}' -m 8 "https://$dir/" 2>/dev/null || echo "000")
  login=$(curl -s -o /dev/null -w '%{http_code}' -m 8 "https://$dir/login" 2>/dev/null || echo "000")
  echo "HTTP öffentlich: $pub | /login: $login (503+Wartung oder Lizenz = normal bei Störung)"
done

check_license_server

echo
echo "=== Kurz-Hinweise ==="
echo "• ganz-soft.de / ganz-om.de öffentlich 503 „Im Aufbau“ = Wartungsmodus AN (CRM unter /login oft OK)."
echo "• Wartung aus: CRM → Einstellungen → Website → Wartungsmodus deaktivieren (pro Instanz)."
echo "• kontur + Master: zuerst license.php + Lizenzserver /check reparieren."
echo "• Cloud-Restore (cloud.ganz-om.de) betrifft nur Nextcloud — CRM-Ordner separat prüfen."
echo "DONE"
