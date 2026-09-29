@echo off
echo Step 1: Moving the 4 real Takaful / Motor Comprehensive sales into June 2026
echo          with bigger premiums so Takaful ranks as Top Vendor...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\boost_pvatm_takaful_for_demo.sql"
echo.
echo ============================================
echo Step 2: Recalculating earning income for just those 4 policies so the
echo          dashboard shows a real, matching commission amount...
cd /d C:\xampp\htdocs\generallink
php artisan commission:recalculate-policies bec865be-6f5f-4495-814c-a54688a5b380 a10ad820-5aaa-46c4-b9ce-2d501289786d 24a1e65f-931a-4a65-a4be-791d80a10e5c fd17024b-f105-48c2-b79e-3fff2eb6c4c2
echo.
echo Done. Open the KPI Dashboard, set the filter to June 2026, and Top
echo Vendor should now show Takaful with Top Product Motor Comprehensive,
echo with a real calculated Earning Income amount.
pause
