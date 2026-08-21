#!/usr/bin/env bash
# Configure SSH for All-Inkl (ganz-om) from Cursor Cloud Agent secrets.
# Required env (Cursor Dashboard → Cloud Agents → Secrets):
#   DG_ALLINKL_SSH_PRIVATE_KEY  (Runtime Secret, full PEM / OpenSSH private key)
#   DG_ALLINKL_SSH_USER         (default: ssh-w0217246)
#   DG_ALLINKL_SSH_HOST         (default: w0217246.kasserver.com)
set -euo pipefail

KEY_RAW="${DG_ALLINKL_SSH_PRIVATE_KEY:-}"
USER_NAME="${DG_ALLINKL_SSH_USER:-ssh-w0217246}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-w0217246.kasserver.com}"

if [[ -z "$KEY_RAW" ]]; then
  echo "cloud-agent-ssh-setup: DG_ALLINKL_SSH_PRIVATE_KEY is not set." >&2
  echo "Add it under https://cursor.com/dashboard/cloud-agents (Secrets)." >&2
  exit 1
fi

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

KEY_FILE="$HOME/.ssh/id_ed25519_ganzom"
# Support both literal newlines and \\n-escaped secrets
printf '%s\n' "$KEY_RAW" | sed 's/\r$//' | sed 's/\\n/\n/g' > "$KEY_FILE"
chmod 600 "$KEY_FILE"

cat > "$HOME/.ssh/config" <<EOF
Host allinkl-ganzom dg.ganz-om.de
  HostName ${HOST_NAME}
  User ${USER_NAME}
  IdentityFile ${KEY_FILE}
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
EOF
chmod 600 "$HOME/.ssh/config"

echo "cloud-agent-ssh-setup: OK → ssh allinkl-ganzom"
ssh -o BatchMode=yes -o ConnectTimeout=15 allinkl-ganzom 'echo SSH_OK; hostname; pwd'
