@echo off
cd /d C:\xampp\htdocs\generallink
echo Step 1: Dry run first, listing every PVATM policy that would be recalculated...
php artisan commission:recalculate-pvatm --dry-run
echo.
echo ============================================
echo Step 2: Now running for real. It will ask you to type YES to confirm.
php artisan commission:recalculate-pvatm
pause
