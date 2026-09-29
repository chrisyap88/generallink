@echo off
title Remove Laravel Scheduler Task
color 0C
echo ================================================
echo   Removing the "Laravel Scheduler" scheduled task
echo ================================================
echo.

schtasks /query /tn "Laravel Scheduler" >nul 2>&1
if %ERRORLEVEL%==0 (
    schtasks /delete /tn "Laravel Scheduler" /f
    echo.
    echo Done — the task has been removed.
) else (
    echo No "Laravel Scheduler" task was found. Nothing to remove.
)

echo.
pause
