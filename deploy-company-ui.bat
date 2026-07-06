@echo off
rem Nur Firmendaten-Accordion (2 Dateien) – weniger scp-Aufrufe, Kaspersky-freundlicher.
setlocal
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/dg.ganz-om.de
cd /d "%~dp0"

echo Upload Firmendaten-UI (tab-company.php + settings-company.js) ...

scp -o BatchMode=yes -i "%KEY%" views/settings/tab-company.php %HOST%:%DEST%/views/settings/
if errorlevel 1 goto fail

scp -o BatchMode=yes -i "%KEY%" assets/js/settings-company.js %HOST%:%DEST%/assets/js/
if errorlevel 1 goto fail

echo OK – Firmendaten-Accordion ist live. Seite mit Shift+Strg+R neu laden.
exit /b 0

:fail
echo Upload fehlgeschlagen. Kaspersky: scp.exe freigeben oder Datei manuell hochladen.
exit /b 1
