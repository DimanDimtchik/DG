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
# Support literal newlines, \\n-escaped secrets, and single-line pastes from Cursor Secrets.
python3 - "$KEY_FILE" <<'PY'
import os, re, sys
from pathlib import Path

raw = os.environ.get("DG_ALLINKL_SSH_PRIVATE_KEY", "").strip().replace("\\n", "\n")
if not raw:
    sys.exit("missing DG_ALLINKL_SSH_PRIVATE_KEY")

if "\n" not in raw:
    match = re.match(r"(-----BEGIN [^-]+-----)\s*(.+?)\s*(-----END [^-]+-----)$", raw)
    if not match:
        sys.exit("could not parse single-line SSH private key")
    begin, body, end = match.groups()
    body = re.sub(r"\s+", "", body)
    wrapped = "\n".join(body[i : i + 70] for i in range(0, len(body), 70))
    raw = f"{begin}\n{wrapped}\n{end}\n"

key_path = Path(sys.argv[1])
key_path.write_text(raw if raw.endswith("\n") else raw + "\n")
key_path.chmod(0o600)
PY
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
