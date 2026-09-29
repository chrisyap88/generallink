@echo off
echo Running fill_pvatm_introducer_ranks.sql against the generallink database...
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root generallink < "C:\xampp\htdocs\generallink\fill_pvatm_introducer_ranks.sql"
echo.
echo Done. See the rank counts printed above to confirm it worked.
echo (If this failed with an access error, your MySQL root user has a
echo  password set — tell me and I'll adjust the command.)
pause
