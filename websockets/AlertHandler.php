<?php
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class AlertHandler implements MessageComponentInterface {
    protected $clients;
    private $lastCheck = 0;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        
        // Send any pending alerts
        $this->checkForNewAlerts();
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if ($data['type'] === 'printer_alert') {
            // Broadcast alert to all connected clients
            $this->broadcastAlert($data);
        }
    }
    
    public function checkForNewAlerts() {
        $alertFile = __DIR__ . '/alerts.json';
        
        if (file_exists($alertFile)) {
            $alerts = json_decode(file_get_contents($alertFile), true) ?: [];
            
            foreach ($alerts as $alert) {
                $this->broadcastAlert($alert);
            }
            
            // Clear processed alerts
            unlink($alertFile);
        }
    }
    
    private function broadcastAlert($alert) {
        $message = json_encode($alert);
        
        foreach ($this->clients as $client) {
            $client->send($message);
        }
        
        echo "Alert broadcasted: {$alert['message']}\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}