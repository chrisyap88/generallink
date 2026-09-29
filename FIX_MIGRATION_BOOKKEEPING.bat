@echo off
cd /d C:\xampp\htdocs\generallink
php artisan migrations:backfill-verified
pause
