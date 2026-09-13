#!/usr/bin/env bash
# Kichel-Dateien auf Live (Account-Root + ganz-soft.de Subfolder) deployen.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEY="$HOME/.ssh/id_ed25519_ganzom"
U="${DG_ALLINKL_SSH_USER:-}"
H="${DG_ALLINKL_SSH_HOST:-}"
BASE="/www/htdocs/w0217246"

if [[ -z "$U" || -z "$H" ]]; then
  echo "DG_ALLINKL_SSH_* required." >&2
  exit 1
fi

bash "$ROOT/bin/cloud-agent-ssh-setup.sh" >/dev/null

FILES=(
  assets/js/kichel.js
  assets/css/kichel.css
  views/partials/kichel-widget.php
  views/modules/website-seiten.php
  index.php
  src/Kichel/KichelAssistant.php
  src/Kichel/KichelKnowledge.php
  src/Kichel/KichelLegalPages.php
  src/Kichel/data/knowledge.php
  src/Legal/LegalPageGenerator.php
)

for rel in "${FILES[@]}"; do
  src="$ROOT/$rel"
  ssh -i "$KEY" -o BatchMode=yes "${U}@${H}" "mkdir -p '$BASE/$(dirname "$rel")' '$BASE/ganz-soft.de/$(dirname "$rel")'"
  scp -i "$KEY" -o BatchMode=yes "$src" "${U}@${H}:$BASE/$rel"
  scp -i "$KEY" -o BatchMode=yes "$src" "${U}@${H}:$BASE/ganz-soft.de/$rel"
done

echo "KICHEL_DEPLOY_OK (root + ganz-soft.de)"
