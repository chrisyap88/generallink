@echo off
cd /d C:\xampp\htdocs\generallink
php artisan vendors:audit-duplicates AIA
pause
