@echo off
title GeneralLink - Refresh Cache
color 0B
echo ================================================
echo    GENERALLINK CACHE REFRESH
echo ================================================
echo.
cd /d C:\xampp\htdocs\generallink

echo Clearing all caches...
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo.
echo ================================================
echo  Done! Refresh your browser now.
echo ================================================
pause
