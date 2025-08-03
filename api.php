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
                $stmt = $pdo->prepare('INSERT INTO users (username) VALUES (?)');
                $stmt->execute([$username]);
                echo json_encode(['message' => 'User registered', 'user_id' => $pdo->lastInsertId()]);
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

            // Verify user balance
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['balance'] >= $bet_amount && $bet_amount > 0) {
                // Deduct balance
                $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$bet_amount, $user_id]);

                // Record bet
                $stmt = $pdo->prepare('INSERT INTO bets (user_id, game_id, bet_amount) VALUES (?, ?, ?)');
                $stmt->execute([$user_id, $game_id, $bet_amount]);
                echo json_encode(['message' => 'Bet placed', 'bet_id' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['error' => 'Insufficient balance or invalid input']);
            }
        }
        break;

    case 'cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $multiplier = $data['multiplier'] ?? 0;
            $game_id = $data['game_id'] ?? 0;

            // Verify bet and game
            $stmt = $pdo->prepare('SELECT crash_point FROM games WHERE id = ?');
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($game && $multiplier <= $game['crash_point']) {
                $stmt = $pdo->prepare('SELECT user_id, bet_amount FROM bets WHERE id = ?');
                $stmt->execute([$bet_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bet) {
                    $win_amount = $bet['bet_amount'] * $multiplier;
                    $stmt = $pdo->prepare('UPDATE bets SET cashout_multiplier = ?, win_amount = ? WHERE id = ?');
                    $stmt->execute([$multiplier, $win_amount, $bet_id]);

                    // Update user balance
                    $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                    $stmt->execute([$win_amount, $bet['user_id']]);
                    echo json_encode(['message' => 'Cashout successful', 'win_amount' => $win_amount]);
                } else {
                    echo json_encode(['error' => 'Invalid bet']);
                }
            } else {
                echo json_encode(['error' => 'Game crashed or invalid multiplier']);
            }
        }
        break;

    case 'start_game':
        if ($method === 'POST') {
            // Generate crash point (simple random for demo, use provably fair in production)
            $crash_point = round(mt_rand(100, 1000) / 100, 2); // 1.00 to 10.00
            $stmt = $pdo->prepare('INSERT INTO games (crash_point) VALUES (?)');
            $stmt->execute([$crash_point]);
            echo json_encode(['game_id' => $pdo->lastInsertId(), 'crash_point' => $crash_point]);
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