#!/bin/sh
# Jaehrliche Bereinigung abgelaufener Mitarbeiterdaten (01.01. 01:00 via KAS-Cronjob)
APP_DIR="/www/htdocs/w0217246/dg.ganz-om.de"
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/cron-purge.log"

mkdir -p "$LOG_DIR"
cd "$APP_DIR" || exit 1

/usr/bin/php bin/db-purge-expired-employees.php >> "$LOG_FILE" 2>&1
