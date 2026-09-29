@echo off
echo Checking PVATM's Cawangan (Team Leader) records for bad data...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\diagnose_pvatm_tl_bad_data.sql"
echo.
echo Copy everything above and send it back to me.
pause
