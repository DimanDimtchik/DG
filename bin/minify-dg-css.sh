#!/usr/bin/env bash
# Minify assets/css/dg.css → assets/css/dg.app.min.css (CRM App).
# Does NOT touch assets/css/dg.min.css (Offline-Stub für öffentliche Offline-Seite).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/assets/css/dg.css"
OUT="$ROOT/assets/css/dg.app.min.css"

if [[ ! -f "$SRC" ]]; then
  echo "minify-dg-css: missing $SRC" >&2
  exit 1
fi

if ! command -v npx >/dev/null 2>&1; then
  echo "minify-dg-css: npx required" >&2
  exit 1
fi

echo "Minifying dg.css → dg.app.min.css ..."
npx --yes clean-css-cli@5.6.3 -o "$OUT" "$SRC"
# stamp so Asset prefers min when source was just built
touch -r "$SRC" "$OUT" 2>/dev/null || true
# ensure min is not older than source (some FS round timestamps)
touch "$OUT"

SRC_B=$(wc -c < "$SRC" | tr -d ' ')
OUT_B=$(wc -c < "$OUT" | tr -d ' ')
echo "OK: $OUT ($OUT_B bytes, source $SRC_B)"
if [[ "$OUT_B" -ge "$SRC_B" ]]; then
  echo "minify-dg-css: WARN min not smaller than source" >&2
fi
