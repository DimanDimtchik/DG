#!/usr/bin/env bash
# Deploy DG CRM per FTP (Port 21) — optionaler Fallback, braucht DG_KAS_LOGIN + DG_KAS_AUTH_DATA.
# KlarWin nutzte SFTP + SSH-Key (deploy-via-sftp.sh), nicht dieses Skript.
# Spiegelt deploy.bat: Master dg.ganz-om.de, optional weitere Instanzen.
#
# Voraussetzung: bash bin/cloud-agent-ftp-setup.sh → OK
#
# Usage:
#   bash bin/deploy-via-ftp.sh              # nur Master
#   bash bin/deploy-via-ftp.sh --all        # Master + ganz-soft + kontur + ganz-om
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FTP_USER="${DG_FTP_USER:-${DG_KAS_LOGIN:-}}"
FTP_PASS="${DG_FTP_PASSWORD:-${DG_KAS_AUTH_DATA:-}}"
FTP_HOST="${DG_FTP_HOST:-}"
if [[ -z "$FTP_HOST" && -n "$FTP_USER" ]]; then
  FTP_HOST="${FTP_USER}.kasserver.com"
fi

ALL=false
if [[ "${1:-}" == "--all" ]]; then
  ALL=true
fi

if [[ -z "$FTP_USER" || -z "$FTP_HOST" ]]; then
  echo "deploy-via-ftp: DG_KAS_LOGIN or DG_FTP_USER required." >&2
  exit 1
fi

if [[ -z "$FTP_PASS" ]]; then
  echo "deploy-via-ftp: DG_KAS_AUTH_DATA or DG_FTP_PASSWORD missing." >&2
  echo "Optional: DG_KAS_LOGIN + DG_KAS_AUTH_DATA setzen — oder deploy-via-sftp.sh (SSH-Key)." >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "deploy-via-ftp: lftp required." >&2
  exit 1
fi

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
  echo "=== FTP upload → $label ($remote_dir) ==="
  lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "
set ftp:ssl-allow no;
set net:timeout 30;
set net:max-retries 2;
lcd $ROOT;
cd $remote_dir;
mirror -R -v ${LFTP_EXCLUDES[*]} . .;
bye
"
  echo "OK: $label"
}

echo "Deploying DG CRM via FTP to $FTP_HOST (user $FTP_USER) ..."
upload_target "dg.ganz-om.de" "Master dg.ganz-om.de"

if $ALL; then
  upload_target "ganz-soft.de" "Live ganz-soft.de"
  upload_target "kontur-cosmetics.de" "kontur-cosmetics.de"
  upload_target "ganz-om.de" "Platzhalter ganz-om.de"
fi

echo
echo "FTP deploy complete."
if ! $ALL; then
  echo "Hinweis: Live-Instanzen mit --all syncen oder auf dem Server: bash bin/sync-crm-from-master.sh (wenn SSH wieder geht)."
fi
