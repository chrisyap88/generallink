@echo off
echo Checking June 2026 PVATM policies for missing/reversed commission rows...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\check_june_pvatm_commissions.sql"
echo.
echo Done - copy everything printed above and send it back to me.
pause
