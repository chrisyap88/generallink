@echo off
cd /d C:\xampp\htdocs\generallink
php artisan hierarchy:detect-cycles
pause
