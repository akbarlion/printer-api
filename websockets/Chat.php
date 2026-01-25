<?php
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if ($data['type'] === 'printer_alert') {
            // Broadcast alert to all connected clients
            foreach ($this->clients as $client) {
                if ($from !== $client) {
                    $client->send(json_encode([
                        'type' => 'printer_alert',
                        'printer_id' => $data['printer_id'],
                        'message' => $data['message'],
                        'status' => $data['status'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
                }
            }
        }
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