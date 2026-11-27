#!/usr/bin/env bash
# MA4b: Flutter Mindeststand — analyze + tests für beide Apps.
# Usage: bash bin/mobile-flutter-minstand.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="${MA4B_LOG_DIR:-/opt/cursor/artifacts}"
mkdir -p "$LOG_DIR"

export PATH="${HOME}/sdk/flutter/bin:${PATH}"

if ! command -v flutter >/dev/null 2>&1; then
  echo "FEHLER: flutter nicht im PATH (erwartet z. B. \$HOME/sdk/flutter/bin)."
  exit 1
fi

run_app() {
  local name="$1"
  local app="$ROOT/apps/$name"
  echo "== $name: flutter pub get =="
  (cd "$app" && flutter pub get) | tee "$LOG_DIR/ma4b-${name}-pubget.log"

  echo "== $name: flutter analyze =="
  set +e
  (cd "$app" && flutter analyze --no-fatal-infos) 2>&1 | tee "$LOG_DIR/ma4b-${name}-analyze.log"
  local rc=${PIPESTATUS[0]}
  set -e
  if [[ "$rc" -ne 0 ]]; then
    echo "FEHLER: flutter analyze ($name) exit $rc"
    exit "$rc"
  fi

  echo "== $name: flutter test =="
  (cd "$app" && flutter test) 2>&1 | tee "$LOG_DIR/ma4b-${name}-test.log"
}

run_app kalender
run_app mitarbeiter

echo "MA4b Flutter-Mindeststand OK (analyze + tests beide Apps)."
exit 0
