@echo off
echo Fixing GeneralLink database setup...
echo.

cd C:\xampp\htdocs\generallink

echo Step 1: Removing old Laravel default migrations...
del "database\migrations\0001_01_01_000000_create_users_table.php" 2>nul
del "database\migrations\0001_01_01_000001_create_cache_table.php" 2>nul
del "database\migrations\0001_01_01_000002_create_jobs_table.php" 2>nul
echo Done.

echo.
echo Step 2: Running fresh migrations...
php artisan migrate:fresh --seed --force

echo.
echo Step 3: Starting server...
echo Open browser and go to: http://127.0.0.1:8000
echo.
echo Login: admin@generallink.my / Admin@12345
echo.
php artisan serve
pause
