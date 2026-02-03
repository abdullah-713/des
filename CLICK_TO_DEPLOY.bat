@echo off
mode con: cols=100 lines=35
color 0A
title Deploy Sarh System

echo.
echo   ==========================================
echo        SARH DEPLOYMENT - FILES ONLY
echo   ==========================================
echo   Database: Import manually via phpMyAdmin
echo   ==========================================
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1"

echo.
pause
