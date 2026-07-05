@echo off
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/dg.ganz-om.de
cd /d "%~dp0"

call deploy.bat
ssh -o BatchMode=yes -i "%KEY%" %HOST% "cd %DEST% && php bin/db-check-contacts.php"
