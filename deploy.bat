@echo off
rem DG CRM deploy (scp/ssh). Bei Kaspersky-Fehlalarm: dieses .bat statt deploy.ps1 nutzen.
setlocal
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/dg.ganz-om.de
cd /d "%~dp0"

echo Deploying DG CRM to dg.ganz-om.de ...

scp -o BatchMode=yes -i "%KEY%" index.php bootstrap.php .htaccess cron.php %HOST%:%DEST%/
if errorlevel 1 goto fail

for %%I in (assets config src views database bin) do (
    scp -o BatchMode=yes -i "%KEY%" -r %%I %HOST%:%DEST%/
    if errorlevel 1 goto fail
)

ssh -o BatchMode=yes -i "%KEY%" %HOST% "chmod -R a+rX %DEST%/assets"
if errorlevel 1 goto fail

ssh -o BatchMode=yes -i "%KEY%" %HOST% "mkdir -p %DEST%/storage/contacts %DEST%/storage/logs %DEST%/storage/mail/sent %DEST%/storage/media && chmod +x %DEST%/bin/run-cron-purge-expired-employees.sh && sed -i 's/\\r$//' %DEST%/bin/run-cron-purge-expired-employees.sh"
if errorlevel 1 goto fail

scp -o BatchMode=yes -i "%KEY%" storage/.htaccess %HOST%:%DEST%/storage/
if errorlevel 1 goto fail

ssh -o BatchMode=yes -i "%KEY%" %HOST% "chmod -R a+rX %DEST%/assets && rm -f %DEST%/index.html %DEST%/assets/css/index.php && chmod -R 750 %DEST%/storage && find %DEST%/storage/media -type d -exec chmod 755 {} + && find %DEST%/storage/media -type f -exec chmod 644 {} +"
if errorlevel 1 goto fail

echo CRM upload complete.
exit /b 0

:fail
echo Deploy failed. Check SSH key and network.
exit /b 1
