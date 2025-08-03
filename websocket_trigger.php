<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use Ratchet\Client as WsClient;

while (true) {
    if (file_exists('websocket_trigger.txt')) {
        $lines = file('websocket_trigger.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        file_put_contents('websocket_trigger.txt', ''); // Clear file
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && isset($data['game_id'], $data['type'], $data['data'])) {
                WsClient\connect('ws://localhost:8080')->then(function($conn) use ($data) {
                    $conn->send(json_encode([
                        'type' => $data['type'],
                        'game_id' => $data['game_id'],
                        'data' => $data['data']
                    ]));
                    $conn->close();
                }, function($e) {
                    error_log("WebSocket trigger error: {$e->getMessage()}");
                });
            }
        }
    }
    sleep(1);
}
?>