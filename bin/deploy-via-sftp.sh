#!/usr/bin/env bash
# Deploy DG CRM via SFTP + SSH key (All-Inkl / WinSCP: SFTP, Passwort leer).
# Same secrets as SSH — no KAS/FTP password. Fallback when user says „FTP“.
#
# Usage:
#   bash bin/deploy-via-sftp.sh           # Master dg.ganz-om.de
#   bash bin/deploy-via-sftp.sh --all     # + ganz-soft, kontur, ganz-om
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

KEY_FILE="$HOME/.ssh/id_ed25519_ganzom"
USER_NAME="${DG_ALLINKL_SSH_USER:-}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-}"

ALL=false
if [[ "${1:-}" == "--all" ]]; then
  ALL=true
fi

if [[ ! -f "$KEY_FILE" ]] || [[ -z "$USER_NAME" ]] || [[ -z "$HOST_NAME" ]]; then
  bash "$(dirname "$0")/cloud-agent-ssh-setup.sh" >/dev/null 2>&1 || true
fi

if [[ ! -f "$KEY_FILE" ]] || [[ -z "$USER_NAME" ]] || [[ -z "$HOST_NAME" ]]; then
  echo "deploy-via-sftp: DG_ALLINKL_SSH_* secrets required." >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "deploy-via-sftp: lftp required." >&2
  exit 1
fi

SSH_CMD="ssh -a -x -i ${KEY_FILE} -o BatchMode=yes -o ConnectTimeout=30 -o StrictHostKeyChecking=accept-new"

LFTP_EXCLUDES=(
  -X 'config/*.local.php'
  -X 'config/database.local.php'
  -X 'config/app.local.php'
  -X 'config/users.php'
  -X 'config/license.php'
  -X 'config/mail.local.php'
  -X 'config/kas.local.php'
  -X 'config/cron.local.php'
  -X 'config/company.local.php'
  -X 'storage/'
  -X 'logs/'
  -X 'tmp-upload/'
  -X '.git/'
  -X 'cursor-transfer/'
  -X 'klarwin/'
  -X 'shop/'
  -X 'probe*.txt'
  -X '*.bak'
)

upload_target() {
  local remote_dir="$1"
  local label="$2"
  echo "=== SFTP upload → $label ($remote_dir) ==="
  lftp -e "
set cmd:fail-exit yes;
set sftp:connect-program \"${SSH_CMD}\";
open sftp://${USER_NAME}@${HOST_NAME};
lcd ${ROOT};
cd ${remote_dir};
mirror -R -v ${LFTP_EXCLUDES[*]} . .;
bye
"
  echo "OK: $label"
}

echo "Deploying DG CRM via SFTP (SSH key, user ${USER_NAME}) ..."
upload_target "dg.ganz-om.de" "Master dg.ganz-om.de"

if $ALL; then
  upload_target "ganz-soft.de" "Live ganz-soft.de"
  upload_target "kontur-cosmetics.de" "kontur-cosmetics.de"
  upload_target "ganz-om.de" "Platzhalter ganz-om.de"
fi

echo
echo "SFTP deploy complete."
