#!/usr/bin/env bash
# Kichel-Dateien auf Live (Account-Root + ganz-soft.de Subfolder) deployen.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEY="$HOME/.ssh/id_ed25519_ganzom"
U="${DG_ALLINKL_SSH_USER:-}"
H="${DG_ALLINKL_SSH_HOST:-}"
REMOTE="/www/htdocs/w0217246"

if [[ -z "$U" || -z "$H" ]]; then
  echo "DG_ALLINKL_SSH_* required." >&2
  exit 1
fi

bash "$ROOT/bin/cloud-agent-ssh-setup.sh" >/dev/null

FILES=(
  assets/js/kichel.js
  assets/css/kichel.css
  views/partials/kichel-widget.php
  src/Kichel/KichelAssistant.php
  src/Kichel/KichelLegalPages.php
  src/Kichel/data/knowledge.php
)

tar czf - -C "$ROOT" "${FILES[@]}" | ssh -i "$KEY" -o BatchMode=yes "${U}@${H}" "
  cd '$REMOTE' && tar xzf - &&
  for f in ${FILES[*]}; do
    if [[ -f \"\$f\" ]]; then
      cp \"\$f\" \"ganz-soft.de/\$f\" 2>/dev/null || true
    fi
  done
  echo KICHEL_DEPLOY_OK
"
