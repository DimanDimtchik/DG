#!/bin/bash
set -e
ROOT=/www/htdocs/w0217246
MASTER="$ROOT/dg.ganz-om.de"
TAR="$ROOT/cursor-transfer/dg-crm-release.tar.gz"
STAGE="$ROOT/cursor-transfer/crm-stage-$$"

echo "=== Backup master local configs ==="
mkdir -p "$STAGE/cfg-backup"
for f in database.local.php app.local.php users.php license.php mail.local.php kas.local.php cron.local.php company.local.php; do
  if [ -f "$MASTER/config/$f" ]; then
    cp -a "$MASTER/config/$f" "$STAGE/cfg-backup/$f"
    echo "backed up $f"
  fi
done

echo "=== Extract release into master ==="
mkdir -p "$STAGE/extract"
tar -xzf "$TAR" -C "$STAGE/extract"

# Copy code over master (do not delete storage / local configs via rsync without --delete yet)
rsync -a \
  --exclude 'config/database.local.php' \
  --exclude 'config/app.local.php' \
  --exclude 'config/users.php' \
  --exclude 'config/license.php' \
  --exclude 'config/mail.local.php' \
  --exclude 'config/kas.local.php' \
  --exclude 'config/cron.local.php' \
  --exclude 'config/company.local.php' \
  --exclude 'storage/' \
  --exclude '.git/' \
  "$STAGE/extract/" "$MASTER/"

# Restore local configs
for f in "$STAGE/cfg-backup"/*; do
  [ -f "$f" ] || continue
  cp -a "$f" "$MASTER/config/$(basename "$f")"
  echo "restored $(basename "$f")"
done

echo "=== Master version ==="
php -r "echo require '$MASTER/config/version.php'; echo PHP_EOL;"

# Cleanup stage extract (keep cfg backup briefly)
rm -rf "$STAGE/extract"
echo "MASTER_READY"
