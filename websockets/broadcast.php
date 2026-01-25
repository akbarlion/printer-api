<?php
// Simple WebSocket broadcaster
function broadcast_alert($printer_id, $message, $status) {
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'action' => 'broadcast',
                    'type' => 'printer_alert',
                    'printer_id' => $printer_id,
                    'message' => $message,
                    'status' => $status
                ]),
                'timeout' => 5
            ]
        ]);
        
        // Send to WebSocket broadcast endpoint
        $result = @file_get_contents('http://localhost:8081/broadcast', false, $context);
        return $result !== false;
        
    } catch (Exception $e) {
        return false;
    }
}

// Test broadcast
if (isset($argv[1])) {
    $result = broadcast_alert('test-printer', 'Test alert from PHP', 'offline');
    echo $result ? "Alert sent successfully\n" : "Failed to send alert\n";
}