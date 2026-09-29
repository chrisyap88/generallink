@echo off
echo Checking why "Tan Ah Kow" shows as Top TL under Chris Yap's group
echo instead of "Amy Tan"...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\diagnose_prihatin2u_top_tl.sql"
echo.
echo Copy everything above and send it back to me.
pause
