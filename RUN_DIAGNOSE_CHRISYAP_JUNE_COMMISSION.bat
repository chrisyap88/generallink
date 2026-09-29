@echo off
cd /d C:\xampp\htdocs\generallink
echo Checking commission_transactions for Chris Yap's Team Leaders...
echo.
C:\xampp\mysql\bin\mysql -u root generallink < diagnose_chrisyap_june_commission.sql
echo.
echo Copy everything above and paste it back to Claude.
pause
