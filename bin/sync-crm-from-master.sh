#!/bin/bash
# Sync CRM code FROM master (dg.ganz-om.de) TO other installs.
# Preserves each instance's local config and storage.
set -e
ROOT=/www/htdocs/w0217246
MASTER="$ROOT/dg.ganz-om.de"

EXCLUDES=(
  --exclude 'config/database.local.php'
  --exclude 'config/app.local.php'
  --exclude 'config/users.php'
  --exclude 'config/license.php'
  --exclude 'config/mail.local.php'
  --exclude 'config/kas.local.php'
  --exclude 'config/cron.local.php'
  --exclude 'config/company.local.php'
  --exclude 'storage/'
  --exclude 'logs/'
  --exclude 'tmp-upload/'
  --exclude 'cursor-transfer/'
  --exclude '.git/'
  --exclude 'probe*.txt'
  --exclude '*.bak'
)

sync_to() {
  local target="$1"
  local label="$2"
  echo "=== Sync -> $label ($target) ==="
  if [ ! -d "$target" ]; then
    echo "SKIP: missing $target"
    return
  fi
  if [ ! -f "$target/index.php" ]; then
    echo "SKIP: no CRM index.php in $target"
    return
  fi
  # Ensure config dir exists
  mkdir -p "$target/config" "$target/storage" "$target/bin"
  rsync -a --delete \
    "${EXCLUDES[@]}" \
    --exclude 'config/*.local.php' \
    "$MASTER/" "$target/"
  echo "OK: $label"
}

echo "Master: $MASTER"
php -r "echo 'version: '.require '$MASTER/config/version.php'.PHP_EOL;"

# Targets: live root (ganz-soft.de), subdir copy, kontur
sync_to "$ROOT" "__ROOT__ (ganz-soft.de live)"
sync_to "$ROOT/ganz-soft.de" "ganz-soft.de (subdir)"
sync_to "$ROOT/kontur-cosmetics.de" "kontur-cosmetics.de"

echo
echo "=== Post-sync MD5 check (index.php) ==="
md5sum "$MASTER/index.php" "$ROOT/index.php" "$ROOT/ganz-soft.de/index.php" "$ROOT/kontur-cosmetics.de/index.php"
echo DONE
