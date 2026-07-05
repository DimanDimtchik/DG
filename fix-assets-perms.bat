@echo off
setlocal
set KEY=%USERPROFILE%\.ssh\id_ed25519_ganzom
set HOST=ssh-w0217246@dg.ganz-om.de
set DEST=www/htdocs/w0217246/dg.ganz-om.de/assets
echo Fixing permissions on %DEST% ...
ssh -o BatchMode=yes -i "%KEY%" %HOST% "chmod -R a+rX %DEST%"
if errorlevel 1 (
  echo Failed.
  exit /b 1
)
echo Done. CSS should return HTTP 200.
exit /b 0
