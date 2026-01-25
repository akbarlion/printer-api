<?php
// Background monitoring service
set_time_limit(0);
ini_set('memory_limit', '256M');

echo "Starting automatic printer monitoring...\n";
echo "Monitoring interval: 30 seconds\n";
echo "Press Ctrl+C to stop\n\n";

while (true) {
    try {
        echo "[" . date('Y-m-d H:i:s') . "] Checking all printers...\n";
        
        // Call monitoring API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/printer-api/api/monitor/check');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "✓ Monitoring completed successfully\n";
        } else {
            echo "✗ Monitoring failed (HTTP $httpCode)\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "Waiting 30 seconds...\n\n";
    sleep(30); // Check every 30 seconds
}