#!/usr/bin/env bash
# Configure SSH for All-Inkl (ganz-om) from Cursor Cloud Agent secrets.
# Required env (Cursor Dashboard → Cloud Agents → Secrets):
#   DG_ALLINKL_SSH_PRIVATE_KEY  (Runtime Secret, full PEM / OpenSSH private key)
#   DG_ALLINKL_SSH_USER         (default: [REDACTED])
#   DG_ALLINKL_SSH_HOST         (default: [REDACTED])
set -euo pipefail

KEY_RAW="${DG_ALLINKL_SSH_PRIVATE_KEY:-}"
USER_NAME="${DG_ALLINKL_SSH_USER:-[REDACTED]}"
HOST_NAME="${DG_ALLINKL_SSH_HOST:-[REDACTED]}"

if [[ ! "$USER_NAME" =~ ^ssh- ]]; then
  echo "cloud-agent-ssh-setup: DG_ALLINKL_SSH_USER must start with 'ssh-' (SSH login from KAS → Tools → SSH-Zugänge)." >&2
  echo "Got length ${#USER_NAME} — often the KAS web login was used by mistake." >&2
  echo "See docs/CLOUD-AGENT-ACCESS.md section 4." >&2
  exit 1
fi

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

# Upsert DG-Host-Block — andere Hosts (z. B. allinkl-iqstrom) nicht überschreiben.
MARKER="# cloud-agent-ssh-setup"
CONFIG="$HOME/.ssh/config"
touch "$CONFIG"
chmod 600 "$CONFIG"
python3 - "$CONFIG" "$MARKER" "$HOST_NAME" "$USER_NAME" "$KEY_FILE" <<'PY'
from pathlib import Path
import re, sys
cfg_path, marker, host, user, key = sys.argv[1:6]
text = Path(cfg_path).read_text() if Path(cfg_path).exists() else ""
block = (
    f"{marker}\n"
    f"Host allinkl-ganzom dg.ganz-om.de\n"
    f"  HostName {host}\n"
    f"  User {user}\n"
    f"  IdentityFile {key}\n"
    f"  IdentitiesOnly yes\n"
    f"  StrictHostKeyChecking accept-new\n"
)
# Entferne alten Marker-Block und/oder nackten DG-Host-Block
text = re.sub(
    r"(?ms)^# cloud-agent-ssh-setup\nHost allinkl-ganzom[\s\S]*?(?=^# |\Z)",
    "",
    text,
)
text = re.sub(
    r"(?ms)^Host allinkl-ganzom dg\.ganz-om\.de\n(?:^[ \t].*\n)*",
    "",
    text,
)
text = text.rstrip() + "\n\n" + block
Path(cfg_path).write_text(text if text.endswith("\n") else text + "\n")
PY

ssh -o BatchMode=yes -o ConnectTimeout=15 allinkl-ganzom 'echo SSH_OK; hostname; pwd'
echo "cloud-agent-ssh-setup: OK → ssh allinkl-ganzom"
