@echo off
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/dg.ganz-om.de
cd /d "%~dp0"

echo [%date% %time%] db-setup start > db-setup-log.txt

call deploy.bat >> db-setup-log.txt 2>&1
if errorlevel 1 goto fail

scp -o BatchMode=yes -i "%KEY%" config\database.local.php %HOST%:%DEST%/config/ >> db-setup-log.txt 2>&1
if errorlevel 1 goto fail

ssh -o BatchMode=yes -i "%KEY%" %HOST% "cd %DEST% && php bin/db-migrate.php && php bin/db-ensure-admin.php && php bin/db-add-kunde-demo.php" >> db-setup-log.txt 2>&1
if errorlevel 1 goto fail

echo [%date% %time%] db-setup ok >> db-setup-log.txt
exit /b 0

:fail
echo [%date% %time%] db-setup failed >> db-setup-log.txt
exit /b 1
