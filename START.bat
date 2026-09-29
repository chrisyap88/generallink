@echo off
title GeneralLink - Starting Server
color 0A
echo.
echo ============================================================
echo   GeneralLink Digital Ecosystem
echo   Starting development server...
echo ============================================================
echo.
echo   Open your browser: http://127.0.0.1:8000
echo.
echo   Admin:        admin@generallink.my      / Admin@12345
echo   Group Leader: chrisyap@generallink.my   / Password@123
echo   Team Leader:  ahmad.razif@generallink.my / Password@123
echo.
echo   Press Ctrl+C to stop the server
echo ============================================================
echo.
php artisan serve
pause
