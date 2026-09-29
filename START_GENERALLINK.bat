@echo off
title GeneralLink Startup
color 0A
echo ================================================
echo    GENERALLINK SYSTEM STARTUP
echo ================================================
echo.

echo Checking if MySQL is already running...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo MySQL is already running. Skipping MySQL start.
) else (
    echo [1/2] Starting MySQL...
    start "MySQL Server" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    echo Waiting for MySQL to be ready...
    timeout /t 5 /nobreak > nul
    echo MySQL ready.
)
echo.

echo [2/2] Starting Laravel Server...
cd /d C:\xampp\htdocs\generallink

REM NEW 17 Jul 2026 — the "Laravel Scheduler" auto-registration was
REM removed from here. It created a Windows Scheduled Task that ran
REM php.exe every minute, forever, surviving every restart until
REM manually deleted — that was the cause of the recurring php.exe
REM popup. This script no longer registers anything automatically.
REM If/when a real scheduled feature (like renewal reminders) is
REM built, it should register its own task deliberately at that time,
REM not silently every time this file happens to run.
echo.

echo ================================================
echo  System ready at: http://127.0.0.1:8000
echo.
echo  Admin Login:
echo  Email   : admin@generallink.my
echo  Password: admin123
echo ================================================
echo.
echo DO NOT CLOSE THIS WINDOW.
echo Press Ctrl+C to stop the server.
echo.

start "" "http://127.0.0.1:8000"
php artisan serve
