@echo off
title GeneralLink Shutdown
color 0C
echo ================================================
echo    GENERALLINK SYSTEM SHUTDOWN
echo ================================================
echo.

echo [1/3] Stopping Laravel Server...
taskkill /FI "WINDOWTITLE eq GeneralLink Startup" /F > nul 2>&1
taskkill /IM "php.exe" /F > nul 2>&1
echo Laravel stopped.
echo.

echo [2/3] Stopping MySQL...
taskkill /IM "mysqld.exe" /F > nul 2>&1
timeout /t 3 /nobreak > nul
echo MySQL stopped.
echo.

echo [3/3] Stopping Apache...
taskkill /IM "httpd.exe" /F > nul 2>&1
timeout /t 2 /nobreak > nul
echo Apache stopped.
echo.

echo Removing background scheduler...
schtasks /delete /tn "Laravel Scheduler" /f >nul 2>&1
echo Scheduler removed.
echo.

echo ================================================
echo  System safely shut down.
echo  You can now power off your notebook.
echo ================================================
echo.
pause
