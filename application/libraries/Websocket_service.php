<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Websocket_service
{
    public function send_alert($printer_id, $message, $status)
    {
        try {
            // Langsung broadcast ke semua client yang connect
            $this->broadcast_to_clients([
                'type' => 'printer_alert',
                'printer_id' => $printer_id,
                'message' => $message,
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function broadcast_to_clients($data)
    {
        // Simpan alert ke file temporary untuk dibaca WebSocket server
        $alertFile = __DIR__ . '/../../websockets/alerts.json';
        $alerts = [];
        
        if (file_exists($alertFile)) {
            $alerts = json_decode(file_get_contents($alertFile), true) ?: [];
        }
        
        $alerts[] = $data;
        
        // Keep only last 10 alerts
        $alerts = array_slice($alerts, -10);
        
        file_put_contents($alertFile, json_encode($alerts));
    }
}