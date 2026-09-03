#!/usr/bin/env bash
# Deploy DG CRM via rsync over SSH (same key as deploy.bat / cloud-agent-ssh-setup).
#
# Usage:
#   bash bin/deploy-via-rsync.sh           # Master dg.ganz-om.de
#   bash bin/deploy-via-rsync.sh --all     # + ganz-soft, kontur, ganz-om
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

KEY_FILE="$HOME/.ssh/id_ed25519_ganzom"
USER_NAME="${DG_ALLINKL_SSH_USER:-}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-}"
REMOTE_BASE="www/htdocs/w0217246"

ALL=false
if [[ "${1:-}" == "--all" ]]; then
  ALL=true
fi

if [[ ! -f "$KEY_FILE" ]]; then
  bash "$(dirname "$0")/cloud-agent-ssh-setup.sh"
fi

if [[ -z "$USER_NAME" || -z "$HOST_NAME" ]]; then
  echo "deploy-via-rsync: DG_ALLINKL_SSH_* required." >&2
  exit 1
fi

RSYNC_SSH="ssh -a -x -i ${KEY_FILE} -o BatchMode=yes -o ConnectTimeout=30 -o StrictHostKeyChecking=accept-new"

RSYNC_EXCLUDES=(
  --exclude 'config/*.local.php'
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
  --exclude '.git/'
  --exclude 'cursor-transfer/'
  --exclude 'klarwin/'
  --exclude 'shop/'
  --exclude 'probe*.txt'
  --exclude '*.bak'
)

upload_target() {
  local remote_dir="$1"
  local label="$2"
  echo "=== rsync → $label ($remote_dir) ==="
  rsync -az --delete \
    "${RSYNC_EXCLUDES[@]}" \
    -e "$RSYNC_SSH" \
    "$ROOT/" "${USER_NAME}@${HOST_NAME}:${REMOTE_BASE}/${remote_dir}/"
  echo "OK: $label"
}

echo "Deploying DG CRM via rsync (user ${USER_NAME}) ..."
upload_target "dg.ganz-om.de" "Master dg.ganz-om.de"

if $ALL; then
  upload_target "ganz-soft.de" "Live ganz-soft.de"
  upload_target "kontur-cosmetics.de" "kontur-cosmetics.de"
  upload_target "ganz-om.de" "Platzhalter ganz-om.de"
fi

echo
echo "rsync deploy complete."
