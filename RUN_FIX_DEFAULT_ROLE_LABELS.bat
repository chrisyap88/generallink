@echo off
echo Fixing System Default role labels (removes leftover "Ibu Pejabat" row)...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\fix_default_role_labels.sql"
echo.
echo Clearing cached labels so the fix shows immediately...
cd /d C:\xampp\htdocs\generallink
php artisan cache:clear
echo.
echo Done. Refresh the Network Tree page (no group selected) - it should
echo now say "Group Leader" instead of "Ibu Pejabat". PVATM's own screens
echo (when you pick PVATM) still show "Ibu Pejabat" as before - untouched.
pause
