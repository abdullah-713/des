@echo off
mode con: cols=120 lines=40
color 0B
chcp 65001 > nul
cls
echo ============================================================
echo     Upload Files to sarh.online
echo ============================================================
echo.
echo Connection Details:
echo   Host: 145.223.119.139
echo   Port: 65002
echo   User: u850419603_sarh
echo   Path: /home/u850419603/domains/sarh.online/public_html
echo.
echo Password: Goolbx512!!
echo.
echo ============================================================
echo.
echo Choose upload method:
echo.
echo   1. Use SCP (requires password entry)
echo   2. Show manual upload instructions
echo   3. Exit
echo.
set /p choice="Enter choice (1-3): "

if "%choice%"=="1" goto scp
if "%choice%"=="2" goto manual
if "%choice%"=="3" goto end

:scp
echo.
echo Uploading files using SCP...
echo You will be prompted for password: Goolbx512!!
echo.
scp -P 65002 -r ./dist/* u850419603_sarh@145.223.119.139:/home/u850419603/domains/sarh.online/public_html
if %errorlevel%==0 (
    echo.
    echo ============================================================
    echo     Upload completed successfully!
    echo ============================================================
    echo.
    echo Website: https://sarh.online
) else (
    echo.
    echo ============================================================
    echo     Upload failed. Check connection and try again.
    echo ============================================================
)
goto end

:manual
echo.
echo ============================================================
echo     Manual Upload Instructions
echo ============================================================
echo.
echo Option 1: Use WinSCP (Recommended for Windows)
echo   1. Download WinSCP from: https://winscp.net
echo   2. Connect with these settings:
echo      - Protocol: SFTP
echo      - Host: 145.223.119.139
echo      - Port: 65002
echo      - User: u850419603_sarh
echo      - Password: Goolbx512!!
echo   3. Navigate to: /home/u850419603/domains/sarh.online/public_html
echo   4. Upload all files from: .\dist\ folder
echo.
echo Option 2: Use FileZilla
echo   1. Download FileZilla from: https://filezilla-project.org
echo   2. Use same connection details as above
echo   3. Upload files from .\dist\ folder
echo.
echo Option 3: Use cPanel File Manager
echo   1. Login to cPanel
echo   2. Open File Manager
echo   3. Navigate to public_html
echo   4. Upload files from .\dist\ folder
echo.
echo Files are ready in: .\dist\ folder
echo.
goto end

:end
echo.
pause
