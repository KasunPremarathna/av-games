<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$db = 'kasunpre_av';
$user = 'kasunpre_av';
$pass = 'Kasun2052';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            if ($username && $password) {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, balance) VALUES (?, ?, 1000.00)');
                    $stmt->execute([$username, $hashedPassword]);
                    echo json_encode(['message' => 'User registered', 'user_id' => $pdo->lastInsertId()]);
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    echo json_encode(['error' => 'Username already exists']);
                }
            } else {
                echo json_encode(['error' => 'Invalid username or password']);
            }
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                echo json_encode(['message' => 'Login successful', 'user_id' => $user['id']]);
            } else {
                echo json_encode(['error' => 'Invalid credentials']);
            }
        }
        break;

    case 'admin_login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = ?');
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin && password_verify($password, $admin['password'])) {
                echo json_encode(['message' => 'Admin logged in', 'admin_id' => $admin['id']]);
            } else {
                echo json_encode(['error' => 'Invalid credentials']);
            }
        }
        break;

    case 'set_crash_points':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $admin_id = $data['admin_id'] ?? 0;
            $crash_points = $data['crash_points'] ?? [];

            error_log("set_crash_points input: admin_id=$admin_id, crash_points=" . json_encode($crash_points));

            $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
            $stmt->execute([$admin_id]);
            if (!$stmt->fetch()) {
                error_log("Invalid admin_id: $admin_id");
                echo json_encode(['error' => 'Invalid admin ID']);
                exit;
            }

            if (empty($crash_points)) {
                error_log("No crash points provided");
                echo json_encode(['error' => 'No crash points provided']);
                exit;
            }

            foreach ($crash_points as $point) {
                $crash_point = floatval($point['crash_point'] ?? 0);
                $win_rate = floatval($point['win_rate'] ?? 0);
                if ($crash_point < 1 || $win_rate < 0 || $win_rate > 100) {
                    error_log("Invalid crash point or win rate: crash_point=$crash_point, win_rate=$win_rate");
                    echo json_encode(['error' => "Invalid crash point ($crash_point) or win rate ($win_rate)"]);
                    exit;
                }
                $stmt = $pdo->prepare('INSERT INTO games (crash_point, win_rate, set_by_admin_id, is_active) VALUES (?, ?, ?, FALSE)');
                $stmt->execute([$crash_point, $win_rate, $admin_id]);
            }
            echo json_encode(['message' => 'Crash points set']);
        }
        break;

    case 'get_next_game':
        if ($method === 'GET') {
            $game_id = $_GET['game_id'] ?? null;
            if ($game_id) {
                $stmt = $pdo->prepare('SELECT id, crash_point FROM games WHERE id = ? AND is_active = TRUE');
                $stmt->execute([$game_id]);
            } else {
                $stmt = $pdo->prepare('SELECT id, crash_point FROM games WHERE is_active = FALSE ORDER BY created_at ASC LIMIT 1');
                $stmt->execute();
            }
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($game) {
                if (!$game_id) {
                    $stmt = $pdo->prepare('UPDATE games SET is_active = TRUE WHERE id = ?');
                    $stmt->execute([$game['id']]);
                }
                echo json_encode(['game_id' => $game['id'], 'crash_point' => floatval($game['crash_point'])]);
            } else {
                echo json_encode(['error' => 'No games available']);
            }
        }
        break;

    case 'place_bet':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $bet_amount = floatval($data['bet_amount'] ?? 0);
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
            $balance = floatval($user['balance']);
            if ($balance < $bet_amount || $bet_amount <= 0) {
                error_log("Invalid balance or input: balance=$balance, bet_amount=$bet_amount");
                echo json_encode(['error' => 'Insufficient balance or invalid input']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT id FROM games WHERE id = ? AND is_active = TRUE');
            $stmt->execute([$game_id]);
            if (!$stmt->fetch()) {
                error_log("Invalid or inactive game_id: $game_id");
                echo json_encode(['error' => 'Invalid or inactive game ID']);
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
            $multiplier = floatval($data['multiplier'] ?? 0);
            $game_id = $data['game_id'] ?? 0;

            error_log("cashout input: " . print_r($data, true));

            if ($multiplier <= 0) {
                error_log("Invalid multiplier: $multiplier");
                echo json_encode(['error' => 'Invalid multiplier']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT crash_point FROM games WHERE id = ?');
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$game) {
                error_log("Game not found: game_id=$game_id");
                echo json_encode(['error' => 'Game not found']);
                exit;
            }
            $crash_point = floatval($game['crash_point']);
            if ($multiplier > $crash_point) {
                error_log("Multiplier exceeds crash point: multiplier=$multiplier, crash_point=$crash_point");
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

            $win_amount = floatval($bet['bet_amount']) * $multiplier;
            $stmt = $pdo->prepare('UPDATE bets SET cashout_multiplier = ?, win_amount = ?, cashout_status = ? WHERE id = ?');
            $stmt->execute([$multiplier, $win_amount, 'pending', $bet_id]);
            echo json_encode(['message' => 'Cashout request submitted']);
        }
        break;

    case 'approve_cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $admin_id = $data['admin_id'] ?? 0;

            $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
            $stmt->execute([$admin_id]);
            if (!$stmt->fetch()) {
                error_log("Invalid admin_id for approve_cashout: $admin_id");
                echo json_encode(['error' => 'Invalid admin ID']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT user_id, win_amount FROM bets WHERE id = ? AND cashout_status = ?');
            $stmt->execute([$bet_id, 'pending']);
            $bet = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bet) {
                echo json_encode(['error' => 'Invalid or already processed bet']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE bets SET cashout_status = ? WHERE id = ?');
            $stmt->execute(['approved', $bet_id]);

            $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([floatval($bet['win_amount']), $bet['user_id']]);
            echo json_encode(['message' => 'Cashout approved']);
        }
        break;

    case 'reject_cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $stmt = $pdo->prepare('UPDATE bets SET cashout_status = ? WHERE id = ? AND cashout_status = ?');
            $stmt->execute(['rejected', $bet_id, 'pending']);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Cashout rejected']);
            } else {
                echo json_encode(['error' => 'Invalid or already processed bet']);
            }
        }
        break;

    case 'topup_request':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $amount = floatval($data['amount'] ?? 0);
            if ($amount <= 0 || !$user_id) {
                echo json_encode(['error' => 'Invalid amount or user ID']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO topup_requests (user_id, amount) VALUES (?, ?)');
            $stmt->execute([$user_id, $amount]);
            echo json_encode(['message' => 'Top-up request submitted']);
        }
        break;

    case 'approve_topup':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $request_id = $data['request_id'] ?? 0;
            $stmt = $pdo->prepare('SELECT user_id, amount FROM topup_requests WHERE id = ? AND status = ?');
            $stmt->execute([$request_id, 'pending']);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$request) {
                echo json_encode(['error' => 'Invalid or already processed request']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE topup_requests SET status = ? WHERE id = ?');
            $stmt->execute(['approved', $request_id]);

            $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([floatval($request['amount']), $request['user_id']]);
            echo json_encode(['message' => 'Top-up approved']);
        }
        break;

    case 'reject_topup':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $request_id = $data['request_id'] ?? 0;
            $stmt = $pdo->prepare('UPDATE topup_requests SET status = ? WHERE id = ? AND status = ?');
            $stmt->execute(['rejected', $request_id, 'pending']);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Top-up rejected']);
            } else {
                echo json_encode(['error' => 'Invalid or already processed request']);
            }
        }
        break;

    case 'get_user':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            error_log("get_user input: user_id=$user_id");
            if ($user_id <= 0) {
                error_log("Invalid user_id: $user_id");
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            try {
                $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $user['balance'] = floatval($user['balance']); // Ensure balance is a float
                    error_log("get_user success: user_id=$user_id, username={$user['username']}, balance={$user['balance']}");
                    echo json_encode($user);
                } else {
                    error_log("User not found: user_id=$user_id");
                    echo json_encode(['error' => 'User not found']);
                }
            } catch (PDOException $e) {
                error_log("get_user error: " . $e->getMessage());
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_history':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            $stmt = $pdo->prepare('SELECT b.*, g.crash_point FROM bets b JOIN games g ON b.game_id = g.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($history as &$bet) {
                $bet['bet_amount'] = floatval($bet['bet_amount']);
                $bet['cashout_multiplier'] = floatval($bet['cashout_multiplier']);
                $bet['win_amount'] = floatval($bet['win_amount']);
                $bet['crash_point'] = floatval($bet['crash_point']);
            }
            echo json_encode($history);
        }
        break;

    case 'get_pending_cashouts':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT b.id, b.user_id, b.bet_amount, b.cashout_multiplier, b.win_amount, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.cashout_status = "pending"');
            $cashouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cashouts as &$cashout) {
                $cashout['bet_amount'] = floatval($cashout['bet_amount']);
                $cashout['cashout_multiplier'] = floatval($cashout['cashout_multiplier']);
                $cashout['win_amount'] = floatval($cashout['win_amount']);
            }
            echo json_encode($cashouts);
        }
        break;

    case 'get_pending_topups':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT t.id, t.user_id, t.amount, u.username FROM topup_requests t JOIN users u ON t.user_id = u.id WHERE t.status = "pending"');
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($requests as &$request) {
                $request['amount'] = floatval($request['amount']);
            }
            echo json_encode($requests);
        }
        break;
}
?>