@echo off
rem Deploy shop.ganz-soft.de (preserves config/*.local.php on server)
setlocal
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/shop.ganz-soft.de
cd /d "%~dp0"

echo Deploying shop to shop.ganz-soft.de ...

scp -o BatchMode=yes -i "%KEY%" shop\.htaccess shop\index.php shop\bootstrap.php %HOST%:%DEST%/
if errorlevel 1 goto fail

for %%I in (assets src views) do (
    scp -o BatchMode=yes -i "%KEY%" -r shop\%%I %HOST%:%DEST%/
    if errorlevel 1 goto fail
)

scp -o BatchMode=yes -i "%KEY%" shop\config\app.php shop\config\plans.php shop\config\legal.php shop\config\database.php shop\config\maintenance.php shop\config\stripe.example.php shop\config\admin.local.php.example %HOST%:%DEST%/config/
if errorlevel 1 goto fail

ssh -o BatchMode=yes -i "%KEY%" %HOST% "mkdir -p %DEST%/storage && chmod 750 %DEST%/storage && echo Deny from all > %DEST%/storage/.htaccess"
if errorlevel 1 goto fail

echo Shop upload complete.
exit /b 0

:fail
echo Shop deploy failed.
exit /b 1
