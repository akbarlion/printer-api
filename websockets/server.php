<?php
// Suppress deprecated warnings
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/AlertHandler.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\AlertHandler;

$alertHandler = new AlertHandler();

$server = IoServer::factory(
    new HttpServer(
        new WsServer($alertHandler)
    ),
    8080
);

// Periodic check for new alerts every 5 seconds
$server->loop->addPeriodicTimer(5, function() use ($alertHandler) {
    $alertHandler->checkForNewAlerts();
});

echo "WebSocket server started on port 8080\n";
echo "Checking for alerts every 5 seconds\n";

$server->run();