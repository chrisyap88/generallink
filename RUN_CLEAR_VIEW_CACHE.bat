@echo off
cd /d C:\xampp\htdocs\generallink
echo Clearing view + config cache so the new pagination fix takes effect...
php artisan view:clear
php artisan config:clear
echo.
echo Done. Go back to your browser, hard-refresh (Ctrl+F5) the PVATM
echo Cawangan page, and the black shape + missing rows should be gone.
pause
