@echo off
cd /d C:\xampp\htdocs\generallink
set /p agentname="Type the name to search: "
php artisan agents:lookup "%agentname%"
pause
