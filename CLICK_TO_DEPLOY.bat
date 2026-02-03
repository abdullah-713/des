@echo off
mode con: cols=140 lines=50
color 0A
chcp 65001 > nul
echo ==========================================
echo      Starting Deployment to Sarh.io
echo ==========================================
echo.
powershell -ExecutionPolicy Bypass -File "deploy.ps1"
echo.
echo ==========================================
echo      Deployment Process Finished
echo ==========================================
pause
