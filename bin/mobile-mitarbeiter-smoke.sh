#!/usr/bin/env bash
# MA3f: Mitarbeiter Flutter-Smoke (analyze + widget test + optional Web-UI).
# Usage: bash bin/mobile-mitarbeiter-smoke.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/apps/mitarbeiter"
LOG_DIR="${MA3F_LOG_DIR:-/opt/cursor/artifacts}"
mkdir -p "$LOG_DIR"

export PATH="${HOME}/sdk/flutter/bin:${PATH}"

if ! command -v flutter >/dev/null 2>&1; then
  echo "FEHLER: flutter nicht im PATH (erwartet z. B. \$HOME/sdk/flutter/bin)."
  exit 1
fi

cd "$APP"
echo "== flutter pub get =="
flutter pub get | tee "$LOG_DIR/ma3f-flutter-pubget.log"

echo "== flutter analyze =="
# Nur Fehler/Warnings hart; Flutter-3.5x Material-Deprecation infos ok.
set +e
flutter analyze --no-fatal-infos 2>&1 | tee "$LOG_DIR/ma3f-flutter-analyze.log"
ANALYZE_RC=${PIPESTATUS[0]}
set -e
if [[ "$ANALYZE_RC" -ne 0 ]]; then
  echo "FEHLER: flutter analyze exit $ANALYZE_RC"
  exit "$ANALYZE_RC"
fi

echo "== flutter test =="
flutter test 2>&1 | tee "$LOG_DIR/ma3f-flutter-test.log"

echo "== Geräte =="
flutter devices 2>&1 | tee "$LOG_DIR/ma3f-flutter-devices.log"

echo "MA3f Flutter-Smoke OK (analyze + widget test)."
echo "Hinweis: Android-Emulator optional; Web/Chrome reicht für Cloud-Agent-Smoke."
exit 0
