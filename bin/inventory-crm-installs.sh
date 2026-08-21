#!/bin/bash
# Inventory CRM installs and compare key files
set -e
ROOT=/www/htdocs/w0217246
FILES="index.php bootstrap.php views/website-public.php views/modules/website-menu.php views/modules/website-seite-form.php assets/js/website-builder.js src/Website/WebsitePageRepository.php src/Security/LicenseGuard.php config/version.php"

echo "=== CRM candidates ==="
for d in "$ROOT"/*/; do
  name=$(basename "$d")
  if [ -f "${d}index.php" ] && [ -d "${d}src" ] && [ -d "${d}views" ]; then
    ver="?"
    if [ -f "${d}config/version.php" ]; then
      ver=$(php -r "echo @include '${d}config/version.php';" 2>/dev/null || echo "?")
    fi
    echo "INSTALL|$name|$ver"
  fi
done

# Also check root itself
if [ -f "$ROOT/index.php" ] && [ -d "$ROOT/src" ]; then
  ver=$(php -r "echo @include '$ROOT/config/version.php';" 2>/dev/null || echo "?")
  echo "INSTALL|__ROOT__|$ver"
fi

echo
echo "=== MD5 of key files ==="
compare_one() {
  local label="$1"
  local base="$2"
  echo "--- $label ---"
  for f in $FILES; do
    if [ -f "$base/$f" ]; then
      md5sum "$base/$f" | awk -v f="$f" '{print $1"  "f}'
    else
      echo "MISSING  $f"
    fi
  done
}

compare_one "dg.ganz-om.de" "$ROOT/dg.ganz-om.de"
compare_one "__ROOT__ (ganz-soft.de live)" "$ROOT"
compare_one "ganz-soft.de (subdir)" "$ROOT/ganz-soft.de"
compare_one "kontur-cosmetics.de" "$ROOT/kontur-cosmetics.de"
