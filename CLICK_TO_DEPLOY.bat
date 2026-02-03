@echo off
mode con: cols=100 lines=35
color 0A
chcp 65001 >nul
title نشر صرح انضباط - Sarh.io

echo.
echo   ==========================================
echo        نشر صرح انضباط — رفع الملفات فقط
echo   ==========================================
echo   قاعدة البيانات: تستورد يدوياً من phpMyAdmin
echo   ==========================================
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1"

echo.
pause
