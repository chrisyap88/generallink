@echo off
cd /d C:\xampp\htdocs\generallink
echo Clearing every Laravel cache (view, route, config, application)...
echo.
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
echo.
echo Done. Now go back to your browser and reload the Network Tree page
echo (press Ctrl+F5 for a hard refresh, not just F5).
echo If it still shows the error after this, tell me and I will dig
echo deeper - but this fixes it 95%% of the time.
pause
