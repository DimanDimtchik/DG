#!/usr/bin/env bash
# Test All-Inkl FTP (Fallback wenn SSH Port 22 timeout).
# Secrets (Cursor Dashboard → Cloud Agents):
#   DG_KAS_LOGIN or DG_FTP_USER     (FTP-Hauptbenutzer, nicht ssh-…)
#   DG_KAS_AUTH_DATA or DG_FTP_PASSWORD  (KAS-/FTP-Passwort)
#   DG_FTP_HOST                     (optional, sonst LOGIN.kasserver.com)
set -euo pipefail

FTP_USER="${DG_FTP_USER:-${DG_KAS_LOGIN:-}}"
FTP_PASS="${DG_FTP_PASSWORD:-${DG_KAS_AUTH_DATA:-}}"
FTP_HOST="${DG_FTP_HOST:-}"
if [[ -z "$FTP_HOST" && -n "$FTP_USER" ]]; then
  FTP_HOST="${FTP_USER}.kasserver.com"
fi

if [[ -z "$FTP_USER" || -z "$FTP_HOST" ]]; then
  echo "cloud-agent-ftp-setup: DG_KAS_LOGIN or DG_FTP_USER required." >&2
  exit 1
fi

if [[ -z "$FTP_PASS" ]]; then
  echo "cloud-agent-ftp-setup: DG_KAS_AUTH_DATA or DG_FTP_PASSWORD is not set." >&2
  echo "All-Inkl: FTP-Passwort = KAS-Hauptaccount-Passwort (nicht SSH-Key)." >&2
  echo "Optional — KlarWin nutzte SFTP+SSH-Key (cloud-agent-sftp-setup.sh), kein KAS-Passwort." >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "cloud-agent-ftp-setup: lftp not installed." >&2
  exit 1
fi

lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" <<EOF
set ftp:ssl-allow no
set net:timeout 20
set net:max-retries 2
pwd
ls -la
bye
EOF

echo "cloud-agent-ftp-setup: OK → bash bin/deploy-via-ftp.sh"
