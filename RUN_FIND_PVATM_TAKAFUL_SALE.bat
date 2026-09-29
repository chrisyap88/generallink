@echo off
echo Looking up PVATM's Takaful / Motor Comprehensive sale(s)...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\find_pvatm_takaful_sale.sql"
echo.
echo Done. Copy everything printed above and send it back to me so I can
echo tell you exactly what to fix (wrong month, wrong vendor link, etc.).
pause
