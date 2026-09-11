#!/usr/bin/env bash
# Configure SSH for All-Inkl IQ-Strom account from Cursor Cloud Agent secrets.
# Required env (Cursor Dashboard → Cloud Agents → Secrets):
#   IQ_ALLINKL_SSH_PRIVATE_KEY  (Runtime Secret, full PEM / OpenSSH private key)
#   IQ_ALLINKL_SSH_USER         (SSH login ssh-… from KAS, account w01f1176)
#   IQ_ALLINKL_SSH_HOST         ([login].kasserver.com)
#
# Domains on this account include: ma.iqstrom.com, iqstrom.com, iq-strom.de, cloud.iqstrom.com
set -euo pipefail

KEY_RAW="${IQ_ALLINKL_SSH_PRIVATE_KEY:-}"
USER_NAME="${IQ_ALLINKL_SSH_USER:-}"
HOST_NAME="${IQ_ALLINKL_SSH_HOST:-}"

if [[ -z "$HOST_NAME" ]]; then
  echo "cloud-agent-iq-ssh-setup: IQ_ALLINKL_SSH_HOST is not set." >&2
  exit 1
fi

if [[ -z "$USER_NAME" ]] || [[ ! "$USER_NAME" =~ ^ssh- ]]; then
  echo "cloud-agent-iq-ssh-setup: IQ_ALLINKL_SSH_USER must start with 'ssh-' (KAS → Tools → SSH-Zugänge)." >&2
  exit 1
fi

if [[ -z "$KEY_RAW" ]]; then
  echo "cloud-agent-iq-ssh-setup: IQ_ALLINKL_SSH_PRIVATE_KEY is not set." >&2
  exit 1
fi

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

KEY_FILE="$HOME/.ssh/id_ed25519_iqstrom"
python3 - "$KEY_FILE" <<'PY'
import os, re, sys
from pathlib import Path

raw = os.environ.get("IQ_ALLINKL_SSH_PRIVATE_KEY", "").strip().replace("\\n", "\n")
if not raw:
    sys.exit("missing IQ_ALLINKL_SSH_PRIVATE_KEY")

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

MARKER="# cloud-agent-iq-ssh-setup"
CONFIG="$HOME/.ssh/config"
touch "$CONFIG"
chmod 600 "$CONFIG"
if ! grep -q "$MARKER" "$CONFIG" 2>/dev/null; then
  cat >> "$CONFIG" <<EOF

$MARKER
Host allinkl-iqstrom ma.iqstrom.com iqstrom.com
  HostName ${HOST_NAME}
  User ${USER_NAME}
  IdentityFile ${KEY_FILE}
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
EOF
fi

ssh -o BatchMode=yes -o ConnectTimeout=15 allinkl-iqstrom 'echo SSH_OK; hostname; ls www/htdocs/w01f1176/ma.iqstrom.com/wp-content/plugins/openstage-telefonbuch/openstage-telefonbuch.php 2>/dev/null || true'
echo "cloud-agent-iq-ssh-setup: OK → ssh allinkl-iqstrom"
