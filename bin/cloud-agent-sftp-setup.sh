#!/usr/bin/env bash
# Test SFTP with SSH key (All-Inkl: „FTP“ = oft SFTP, Passwort leer, Key aus Secrets).
# Uses same secrets as cloud-agent-ssh-setup.sh — no KAS password needed.
set -euo pipefail

KEY_FILE="$HOME/.ssh/id_ed25519_ganzom"
USER_NAME="${DG_ALLINKL_SSH_USER:-}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-}"

if [[ ! -f "$KEY_FILE" ]]; then
  bash "$(dirname "$0")/cloud-agent-ssh-setup.sh" >/dev/null 2>&1 || true
fi

if [[ ! -f "$KEY_FILE" ]] || [[ -z "$USER_NAME" ]] || [[ -z "$HOST_NAME" ]]; then
  echo "cloud-agent-sftp-setup: run after cloud-agent-ssh-setup (DG_ALLINKL_SSH_* secrets)." >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "cloud-agent-sftp-setup: lftp not installed." >&2
  exit 1
fi

SSH_CMD="ssh -a -x -i ${KEY_FILE} -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new"

lftp -e "
set cmd:fail-exit yes;
set sftp:connect-program \"${SSH_CMD}\";
open sftp://${USER_NAME}@${HOST_NAME};
pwd;
ls -la;
bye
"

echo "cloud-agent-sftp-setup: OK → bash bin/deploy-via-sftp.sh"
