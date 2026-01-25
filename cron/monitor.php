<?php
// Script untuk monitoring berkala (bisa dipanggil via cron)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/printer-api/api/monitor/check');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo "Monitoring completed: " . date('Y-m-d H:i:s') . "\n";
echo $response . "\n";