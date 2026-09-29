@echo off
echo ============================================
echo Step 1: Stopping Apache (so no file is locked
echo         while we delete the cached copies)...
echo ============================================
if exist "C:\xampp\apache_stop.bat" (
    call "C:\xampp\apache_stop.bat"
    timeout /t 3 /nobreak >nul
) else (
    echo Could not find C:\xampp\apache_stop.bat automatically.
    echo Please open the XAMPP Control Panel now and click Stop
    echo next to Apache, then press any key here to continue.
    pause
)

echo.
echo ============================================
echo Step 2: Deleting every cached/compiled view file...
echo ============================================
del /q "C:\xampp\htdocs\generallink\storage\framework\views\*.php" 2>nul
cd /d C:\xampp\htdocs\generallink
php artisan route:clear
php artisan config:clear
php artisan cache:clear

echo.
echo ============================================
echo Step 3: THE REAL TEST. Forcing PHP to compile
echo         every single view right now, including
echo         by-gl.blade.php and by-tl.blade.php.
echo         If those files truly have a broken line,
echo         it WILL show up here as a clear error.
echo ============================================
php artisan view:cache
echo.
echo ^>^>^> IMPORTANT: read the message directly above this line. ^<^<^<
echo ^>^>^> If it says "Blade templates cached successfully"      ^<^<^<
echo ^>^>^> the files are 100%% fine - it really was caching.       ^<^<^<
echo ^>^>^> If it shows an error instead, STOP and copy that       ^<^<^<
echo ^>^>^> exact error message back to me - that is the real bug. ^<^<^<
echo.
pause

echo Cleaning up (returning to normal auto-compile mode)...
php artisan view:clear

echo.
echo ============================================
echo Step 4: Starting Apache back up...
echo ============================================
if exist "C:\xampp\apache_start.bat" (
    call "C:\xampp\apache_start.bat"
) else (
    echo Please open the XAMPP Control Panel and click Start next to Apache.
)

echo.
echo Done. Go back to your browser and reload the Network Tree page
echo (Ctrl+F5 for a hard refresh).
pause
