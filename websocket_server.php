<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
require dirname(__DIR__) . '/vendor/autoload.php';

class GameServer implements MessageComponentInterface {
    protected $clients;
    protected $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $host = 'localhost';
$db = 'kasunpre_av';
$user = 'kasunpre_av'; // Replace with your MySQL username
$pass = 'Kasun2052';
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("WebSocket: Database connection successful");
        } catch (PDOException $e) {
            error_log("WebSocket: Connection failed: " . $e->getMessage());
            exit;
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        error_log("WebSocket: New connection! ID: {$conn->resourceId}");
        $conn->send(json_encode(['type' => 'welcome', 'message' => 'Connected to game server']));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        error_log("WebSocket: Received message: " . $msg);
        if (!$data || !isset($data['type'])) return;

        switch ($data['type']) {
            case 'subscribe_game':
                $game_id = $data['game_id'] ?? 0;
                if ($game_id > 0) {
                    $from->game_id = $game_id;
                    $this->sendActiveBets($game_id);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        error_log("WebSocket: Connection closed! ID: {$conn->resourceId}");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("WebSocket: Error: {$e->getMessage()}");
        $conn->close();
    }

    public function broadcast($game_id, $message) {
        foreach ($this->clients as $client) {
            if (isset($client->game_id) && $client->game_id == $game_id) {
                $client->send(json_encode($message));
            }
        }
        error_log("WebSocket: Broadcast to game_id=$game_id: " . json_encode($message));
    }

    public function sendActiveBets($game_id) {
        try {
            $stmt = $this->pdo->prepare('SELECT b.bet_amount, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.game_id = ? AND b.cashout_status IS NULL');
            $stmt->execute([$game_id]);
            $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bets as &$bet) {
                $bet['bet_amount'] = floatval($bet['bet_amount']);
            }
            $this->broadcast($game_id, ['type' => 'active_bets', 'bets' => $bets]);
        } catch (PDOException $e) {
            error_log("WebSocket: sendActiveBets PDO error: " . $e->getMessage());
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new GameServer()
        )
    ),
    8080
);
error_log("WebSocket server started on port 8080");
$server->run();
?>