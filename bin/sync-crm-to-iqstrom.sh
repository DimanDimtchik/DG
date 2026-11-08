#!/usr/bin/env bash
# Sync CRM code FROM code-kern (dg.ganz-soft.de on w0217246) TO IQ-Instanz
# office.iqstrom.com (All-Inkl w01f1176). Preserves instance local config + storage.
#
# Cloud-Agent:
#   bash bin/cloud-agent-ssh-setup.sh
#   bash bin/cloud-agent-iq-ssh-setup.sh
#   bash bin/sync-crm-to-iqstrom.sh
#
# Requires both SSH aliases. Does NOT create MySQL / database.local.php.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="${TMPDIR:-/tmp}/dg-crm-iqstrom-stage"
MASTER_REMOTE="allinkl-ganzom:/www/htdocs/w0217246/dg.ganz-soft.de/"
TARGET_REMOTE="allinkl-iqstrom:/www/htdocs/w01f1176/office.iqstrom.com/"
TARGET_SSH_PATH="/www/htdocs/w01f1176/office.iqstrom.com"

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
  --exclude 'klarwin/'
  --exclude 'shop/'
  --exclude 'apps/'
  --exclude 'license-server/'
  --exclude 'www/'
  --exclude 'index.htm.kas-placeholder'
)

if ! ssh -o BatchMode=yes -o ConnectTimeout=15 allinkl-ganzom 'test -f /www/htdocs/w0217246/dg.ganz-soft.de/index.php'; then
  echo "sync-crm-to-iqstrom: Code-Kern unreachable (allinkl-ganzom)." >&2
  exit 1
fi
if ! ssh -o BatchMode=yes -o ConnectTimeout=15 allinkl-iqstrom 'test -d /www/htdocs/w01f1176'; then
  echo "sync-crm-to-iqstrom: IQ-Account unreachable (allinkl-iqstrom)." >&2
  exit 1
fi

rm -rf "$STAGE"
mkdir -p "$STAGE"
echo "=== Stage from Code-Kern ==="
rsync -a -e "ssh -o BatchMode=yes" "${EXCLUDES[@]}" "$MASTER_REMOTE" "$STAGE/"
test -f "$STAGE/bootstrap.php" && test -d "$STAGE/src"

ssh allinkl-iqstrom "mkdir -p '$TARGET_SSH_PATH'/{config,storage,logs,tmp-upload}; if [ -f '$TARGET_SSH_PATH/index.htm' ]; then mv -f '$TARGET_SSH_PATH/index.htm' '$TARGET_SSH_PATH/index.htm.kas-placeholder'; fi"

echo "=== Sync -> office.iqstrom.com ==="
rsync -a --delete -e "ssh -o BatchMode=yes" "${EXCLUDES[@]}" "$STAGE/" "$TARGET_REMOTE"

KERN_MD5=$(ssh allinkl-ganzom 'md5sum /www/htdocs/w0217246/dg.ganz-soft.de/index.php' | awk '{print $1}')
IQ_MD5=$(ssh allinkl-iqstrom "md5sum '$TARGET_SSH_PATH/index.php'" | awk '{print $1}')
VER=$(ssh allinkl-iqstrom "php82 -r 'echo include \"$TARGET_SSH_PATH/config/version.php\";'" 2>/dev/null || ssh allinkl-iqstrom "php -r 'echo include \"$TARGET_SSH_PATH/config/version.php\";'")

echo "version: $VER"
echo "index.md5 kern=$KERN_MD5 iq=$IQ_MD5"
if [[ "$KERN_MD5" != "$IQ_MD5" ]]; then
  echo "FAIL: index.php MD5 mismatch" >&2
  exit 1
fi

if ssh allinkl-iqstrom "test -f '$TARGET_SSH_PATH/config/database.local.php'"; then
  echo "database.local.php: vorhanden (Instanz)"
else
  echo "database.local.php: FEHLT — KAS-DB „DB Office IQ CRM“ anlegen + local.php setzen"
fi

echo "DONE → https://office.iqstrom.com/login"
rm -rf "$STAGE"
