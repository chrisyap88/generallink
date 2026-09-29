@echo off
cd /d C:\xampp\htdocs\generallink
php artisan report:pvatm-agent-ranks
echo.
echo Excel file saved as PVATM_Agent_Rank_Report.xlsx in this same folder.
pause
