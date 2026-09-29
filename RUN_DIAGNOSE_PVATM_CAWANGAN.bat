@echo off
echo Checking PVATM's Cawangan (Team Leader) label from the database...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\diagnose_pvatm_cawangan_label.sql"
echo.
pause
