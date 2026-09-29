@echo off
title GeneralLink Installation
color 0B
echo.
echo ============================================================
echo   GeneralLink Digital Ecosystem - Auto Installer
echo ============================================================
echo.

:: Check if we are in the right place
if not exist "composer.json" (
    echo ERROR: Please run this from inside the generallink folder.
    echo The folder should contain composer.json
    pause
    exit /b 1
)

echo [1/7] Checking PHP...
php -v >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP not found. Make sure XAMPP is installed and PHP is in your PATH.
    echo Add C:\xampp\php to your Windows PATH environment variable.
    pause
    exit /b 1
)
echo     PHP found OK

echo.
echo [2/7] Checking Composer...
composer -V >nul 2>&1
if errorlevel 1 (
    echo ERROR: Composer not found. Download from https://getcomposer.org
    pause
    exit /b 1
)
echo     Composer found OK

echo.
echo [3/7] Installing PHP packages (this takes 2-5 minutes)...
composer install --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 (
    echo ERROR: Composer install failed. Check your internet connection.
    pause
    exit /b 1
)
echo     Packages installed OK

echo.
echo [4/7] Setting up environment file...
if not exist ".env" (
    copy .env.example .env >nul
    echo     Created .env file
) else (
    echo     .env already exists, skipping
)

echo.
echo [5/7] Generating application key...
php artisan key:generate --force
echo     App key generated OK

echo.
echo [6/7] Creating storage link...
php artisan storage:link >nul 2>&1
echo     Storage link created OK

echo.
echo ============================================================
echo   IMPORTANT: Database Setup Required
echo ============================================================
echo.
echo   Before running migrations, you must:
echo.
echo   1. Open XAMPP Control Panel
echo   2. Start Apache and MySQL
echo   3. Open http://localhost/phpmyadmin
echo   4. Click "New" on the left sidebar
echo   5. Database name: generallink
echo   6. Collation: utf8mb4_unicode_ci
echo   7. Click Create
echo.
echo   Then open your .env file and set:
echo   MAIL_USERNAME=your_mailtrap_username
echo   MAIL_PASSWORD=your_mailtrap_password
echo.
set /p CONTINUE="Have you created the database? (yes/no): "
if /i "%CONTINUE%" neq "yes" (
    echo Please create the database first, then run INSTALL.bat again.
    pause
    exit /b 0
)

echo.
echo [7/7] Running database migrations and seeding...
php artisan migrate:fresh --force
if errorlevel 1 (
    echo ERROR: Migration failed. Check your database connection in .env
    pause
    exit /b 1
)

php artisan db:seed --force
if errorlevel 1 (
    echo ERROR: Seeding failed.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo   INSTALLATION COMPLETE!
echo ============================================================
echo.
echo   Starting development server...
echo   Open your browser and go to: http://127.0.0.1:8000
echo.
echo   LOGIN CREDENTIALS:
echo   ------------------
echo   Admin:        admin@generallink.my      / Admin@12345
echo   Group Leader: chrisyap@generallink.my   / Password@123
echo   Team Leader:  ahmad.razif@generallink.my / Password@123
echo.
echo   NOTE: First login will ask you to set a security phrase.
echo   Type anything you can remember (e.g. "myfirstlogin")
echo.
echo ============================================================
echo.
php artisan serve

pause
