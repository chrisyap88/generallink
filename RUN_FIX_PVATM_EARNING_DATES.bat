@echo off
echo Fixing PVATM's Earning Income dates (recalculated rows were dated
echo "today" instead of the original sale month)...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\fix_pvatm_earning_dates.sql"
echo.
echo Done. Refresh the KPI Dashboard for June 2026 - Earning Income
echo should now show a real number instead of RM 0.00.
echo.
echo The last two things printed above are about the Takaful vendor -
echo copy them and send back to me so I can tell you exactly why it
echo isn't ranking as Top Vendor yet.
pause
