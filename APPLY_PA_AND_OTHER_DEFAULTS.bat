@echo off
cd /d C:\xampp\htdocs\generallink
php artisan commission:apply-defaults "MOTOR=10,2.5,2.5,5" "FIRE=12,3,3,6" "PERSONAL_ACCIDENT=25,6,6,13" "OTHER=25,6,6,13"
pause
