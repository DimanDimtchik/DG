#!/usr/bin/env bash
# Cloud-Agent: Kichel-Protokoll von ganz-soft.de (Live Account-Root) abrufen.
#
# Usage:
#   bash bin/cloud-agent-kichel-protocol.sh           # letzte 30 Einträge
#   bash bin/cloud-agent-kichel-protocol.sh 50        # letzte 50
#   bash bin/cloud-agent-kichel-protocol.sh 20 0      # limit offset
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LIMIT="${1:-30}"
OFFSET="${2:-0}"
REMOTE="/www/htdocs/w0217246"

bash "$ROOT/bin/cloud-agent-ssh-setup.sh" >/dev/null

KEY_FILE="$HOME/.ssh/id_ed25519_ganzom"
USER_NAME="${DG_ALLINKL_SSH_USER:-}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-}"

if [[ ! -f "$KEY_FILE" ]] || [[ -z "$USER_NAME" ]] || [[ -z "$HOST_NAME" ]]; then
  echo "cloud-agent-kichel-protocol: DG_ALLINKL_SSH_* required." >&2
  exit 1
fi

SSH_CMD=(ssh -a -x -i "$KEY_FILE" -o BatchMode=yes -o ConnectTimeout=30 -o StrictHostKeyChecking=accept-new
  "${USER_NAME}@${HOST_NAME}")

# Script ggf. erst deployen (kleines Update ohne Voll-Deploy)
if [[ -f "$ROOT/bin/kichel-protocol-list.php" ]]; then
  tar czf - -C "$ROOT" bin/kichel-protocol-list.php | "${SSH_CMD[@]}" "cd '$REMOTE' && tar xzf -"
fi

"${SSH_CMD[@]}" "cd '$REMOTE' && php bin/kichel-protocol-list.php '$LIMIT' '$OFFSET'"
