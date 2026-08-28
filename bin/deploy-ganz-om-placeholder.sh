#!/bin/bash
# Synonym: ganz-om.de bekommt denselben Code wie ganz-soft.de (aus dg.ganz-om.de).
# Voraussetzung: Master auf dg.ganz-om.de ist deployt.
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
exec bash "$DIR/sync-crm-from-master.sh"
