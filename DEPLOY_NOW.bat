@echo off
mode con: cols=120 lines=40
color 0B
chcp 65001 > nul
cls
echo ============================================================
echo     🚀 نشر نظام صرح انضباط إلى Hostinger
echo ============================================================
echo.
echo جاري تشغيل سكريبت النشر...
echo.
powershell -ExecutionPolicy Bypass -File "deploy_complete.ps1"
echo.
echo ============================================================
echo     ✅ اكتمل عملية النشر
echo ============================================================
echo.
pause
