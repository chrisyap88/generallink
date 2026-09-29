@echo off
title GeneralLink - EspoCRM Connection Test
color 0B
echo ================================================
echo    ESPOCRM CONNECTION TEST
echo ================================================
echo.
cd /d C:\xampp\htdocs\generallink

php artisan espocrm:test

echo.
echo ================================================
pause
