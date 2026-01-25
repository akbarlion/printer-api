@echo off
echo Starting Printer Monitoring System...
echo.

echo Starting WebSocket Server...
start "WebSocket Server" cmd /k "cd /d d:\API\www\printer-api\websockets && php server.php"

timeout /t 3 /nobreak > nul

echo Starting Auto Monitoring Service...
start "Auto Monitor" cmd /k "cd /d d:\API\www\printer-api\services && php auto_monitor.php"

echo.
echo Both services are now running in separate windows.
echo Close this window or press any key to exit.
pause