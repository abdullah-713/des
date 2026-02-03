@echo off
mode con: cols=120 lines=40
color 0B
cls
echo ============================================================
echo     SARH HOSTINGER DEPLOYMENT
echo ============================================================
echo.
echo Running deployment script...
echo.
powershell -ExecutionPolicy Bypass -File "deploy_complete.ps1"
echo.
echo ============================================================
echo     DEPLOYMENT COMPLETE
echo ============================================================
echo.
pause
