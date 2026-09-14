#!/usr/bin/env bash
# Dashboard-Schulungsvideo aus echtem CRM-Screenshot (1:1).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${1:-https://ganz-soft.de}"
USER_ID="${2:-9}"

echo "==> 1/3 Dashboard-HTML vom Live-CRM exportieren (${BASE_URL})"
if [[ -n "${SKIP_SSH:-}" ]]; then
  php bin/academy-export-dashboard-html.php --base="${BASE_URL}/" --user-id="${USER_ID}"
else
  scp bin/academy-export-dashboard-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
  ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-dashboard-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
  mkdir -p storage/media/training/allgemein
  scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/allgemein/dashboard-capture.html \
      allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/allgemein/dashboard-capture.meta.json \
      storage/media/training/allgemein/
fi

echo "==> 2/3 Screenshot + Kachel-Positionen"
python3 bin/academy-capture-dashboard.py

echo "==> 3/3 Video (Stimme + Kamera-Fokus)"
python3 bin/academy-generate-dashboard-video.py

echo "Fertig: storage/media/training/allgemein/dashboard-ueberblick.mp4"
