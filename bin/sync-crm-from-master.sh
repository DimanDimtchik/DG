#!/bin/bash
# Sync CRM code FROM master (dg.ganz-om.de) TO live instances.
# Preserves each instance's local config and storage.
# NEVER use --delete against the account root (would wipe sibling domains).
set -euo pipefail
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

# Live ganz-soft.de: Domain zeigt auf /ganz-soft.de/ (KAS Webspace, nicht Account-Root).
sync_to "$ROOT/ganz-soft.de" "ganz-soft.de (Live)" yes
sync_to "$ROOT/kontur-cosmetics.de" "kontur-cosmetics.de" yes

# Cleanup accidental top-level copies from bad scp
rm -rf "$ROOT/Install" "$ROOT/Website" 2>/dev/null || true

echo
echo "=== Post-sync MD5 check (index.php) ==="
md5sum "$MASTER/index.php" "$ROOT/ganz-soft.de/index.php" "$ROOT/kontur-cosmetics.de/index.php"
echo DONE
