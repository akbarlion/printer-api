@echo off
echo Starting Printer Auto Monitoring Service...
echo.
cd /d "d:\API\www\printer-api\services"
php auto_monitor.php
pause