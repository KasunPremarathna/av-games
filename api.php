<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$db = 'kasunpre_av';
$user = 'kasunpre_av'; // Replace with your MySQL username
$pass = 'Kasun2052'; // Replace with your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            if ($username) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO users (username) VALUES (?)');
                    $stmt->execute([$username]);
                    echo json_encode(['message' => 'User registered', 'user_id' => $pdo->lastInsertId()]);
                } catch (PDOException $e) {
                    echo json_encode(['error' => 'Username already exists']);
                }
            } else {
                echo json_encode(['error' => 'Invalid username']);
            }
        }
        break;

    case 'place_bet':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $bet_amount = $data['bet_amount'] ?? 0;
            $game_id = $data['game_id'] ?? 0;

            error_log("place_bet input: " . print_r($data, true));

            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                error_log("User not found: user_id=$user_id");
                echo json_encode(['error' => 'User not found']);
                exit;
            }
            if ($user['balance'] < $bet_amount || $bet_amount <= 0) {
                error_log("Invalid balance or input: balance={$user['balance']}, bet_amount=$bet_amount");
                echo json_encode(['error' => 'Insufficient balance or invalid input']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT id FROM games WHERE id = ?');
            $stmt->execute([$game_id]);
            if (!$stmt->fetch()) {
                error_log("Invalid game_id: $game_id");
                echo json_encode(['error' => 'Invalid game ID']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$bet_amount, $user_id]);

            $stmt = $pdo->prepare('INSERT INTO bets (user_id, game_id, bet_amount) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, $game_id, $bet_amount]);
            echo json_encode(['message' => 'Bet placed', 'bet_id' => $pdo->lastInsertId()]);
        }
        break;

    case 'cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $multiplier = $data['multiplier'] ?? 0;
            $game_id = $data['game_id'] ?? 0;

            error_log("cashout input: " . print_r($data, true));

            $stmt = $pdo->prepare('SELECT crash_point FROM games WHERE id = ?');
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$game) {
                error_log("Game not found: game_id=$game_id");
                echo json_encode(['error' => 'Game not found']);
                exit;
            }
            if ($multiplier > $game['crash_point']) {
                error_log("Multiplier exceeds crash point: multiplier=$multiplier, crash_point={$game['crash_point']}");
                echo json_encode(['error' => 'Game crashed']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT user_id, bet_amount FROM bets WHERE id = ?');
            $stmt->execute([$bet_id]);
            $bet = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bet) {
                error_log("Bet not found: bet_id=$bet_id");
                echo json_encode(['error' => 'Invalid bet']);
                exit;
            }

            $win_amount = $bet['bet_amount'] * $multiplier;
            $stmt = $pdo->prepare('UPDATE bets SET cashout_multiplier = ?, win_amount = ? WHERE id = ?');
            $stmt->execute([$multiplier, $win_amount, $bet_id]);

            $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([$win_amount, $bet['user_id']]);
            echo json_encode(['message' => 'Cashout successful', 'win_amount' => $win_amount]);
        }
        break;

    case 'start_game':
        if ($method === 'POST') {
            $crash_point = round(mt_rand(100, 1000) / 100, 2);
            $stmt = $pdo->prepare('INSERT INTO games (crash_point) VALUES (?)');
            $stmt->execute([$crash_point]);
            echo json_encode(['game_id' => $pdo->lastInsertId(), 'crash_point' => $crash_point]);
        } elseif ($method === 'GET' && isset($_GET['game_id'])) {
            $stmt = $pdo->prepare('SELECT crash_point FROM games WHERE id = ?');
            $stmt->execute([$_GET['game_id']]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($game ?: ['error' => 'Game not found']);
        }
        break;

    case 'get_user':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($user ?: ['error' => 'User not found']);
        }
        break;

    case 'get_history':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            $stmt = $pdo->prepare('SELECT b.*, g.crash_point FROM bets b JOIN games g ON b.game_id = g.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($history);
        }
        break;
}
?>