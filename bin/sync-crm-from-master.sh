#!/bin/bash
# Sync CRM code FROM code-core (dg.ganz-soft.de) TO CRM instances on All-Inkl.
#
# Scope (SC7a / Shared Core):
#   - ALL-INKL ONLY — Full-Tree-Kopien unter /www/htdocs/w0217246/…
#   - GEX45 Shared Core: dieses Skript ist OBSOLET dort.
#     GEX-Deploy = nur /var/www/dg-crm/shared/ (siehe docs/SHARED-CORE-REST-PLAN.md SC4).
#     Instanz-Hüllen auf GEX werden nicht per sync-crm-from-master befüllt.
#
# Preserves each instance's local config and storage.
# NEVER use --delete against the account root (would wipe sibling domains).
# Tenant folders at domain root (e.g. klarwin/) are user content — never delete.
set -euo pipefail
ROOT=/www/htdocs/w0217246
MASTER="$ROOT/dg.ganz-soft.de"

EXCLUDES=(
  --exclude 'config/database.local.php'
  --exclude 'config/app.local.php'
  --exclude 'config/users.php'
  --exclude 'config/license.php'
  --exclude 'config/mail.local.php'
  --exclude 'config/kas.local.php'
  --exclude 'config/cron.local.php'
  --exclude 'config/company.local.php'
  --exclude 'config/*.local.php'
  --exclude 'storage/'
  --exclude 'logs/'
  --exclude 'tmp-upload/'
  --exclude 'cursor-transfer/'
  --exclude '.git/'
  --exclude 'probe*.txt'
  --exclude '*.bak'
  --exclude 'www/'
  --exclude 'wp-backup-archive/'
  --exclude 'klarwin/'
  --exclude 'apps/'
  --exclude 'license-server/'
)

sync_to() {
  local target="$1"
  local label="$2"
  local use_delete="${3:-yes}"
  echo "=== Sync -> $label ($target) ==="
  if [ ! -d "$target" ]; then
    echo "SKIP: missing $target"
    return
  fi
  if [ ! -f "$MASTER/index.php" ] || [ ! -d "$MASTER/src" ]; then
    echo "ABORT: master CRM incomplete at $MASTER"
    exit 1
  fi
  mkdir -p "$target/config" "$target/storage" "$target/bin"
  local extra=()
  if [ "$use_delete" = "yes" ]; then
    extra=(--delete)
  fi
  rsync -a "${extra[@]}" \
    "${EXCLUDES[@]}" \
    "$MASTER/" "$target/"
  if [ ! -f "$target/index.php" ]; then
    echo "FAIL: index.php missing after sync to $label"
    exit 1
  fi
  echo "OK: $label"
}

echo "Master: $MASTER"
if [ ! -f "$MASTER/config/version.php" ]; then
  echo "ABORT: missing $MASTER/config/version.php — deploy master first"
  exit 1
fi
VER=$(php -r 'echo include $argv[1];' "$MASTER/config/version.php")
echo "version: $VER"

# Dev
sync_to "$ROOT/ganz-om.de" "ganz-om.de (Dev)" yes
# Firma Ganz OM
sync_to "$ROOT/dg.ganz-om.de" "dg.ganz-om.de (Firma Ganz OM)" yes
# Künftig GmbH — wie Kunde
sync_to "$ROOT/ganz-soft.de" "ganz-soft.de (Kunde/künftig GmbH)" yes
sync_to "$ROOT/kontur-cosmetics.de" "kontur-cosmetics.de" yes
# office.iqstrom.com liegt auf Account w01f1176 — eigener Sync, nicht hier.

# Cleanup accidental top-level copies from bad scp
rm -rf "$ROOT/Install" "$ROOT/Website" 2>/dev/null || true

echo
echo "=== Post-sync MD5 check (index.php) ==="
md5sum "$MASTER/index.php" "$ROOT/ganz-om.de/index.php" "$ROOT/dg.ganz-om.de/index.php" "$ROOT/ganz-soft.de/index.php" "$ROOT/kontur-cosmetics.de/index.php"
echo DONE
