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
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function generateCrashPoint() {
    $rand = mt_rand(0, 10000) / 100; // 0 to 100
    if ($rand < 50) { // 50% chance for 1.10–2.00
        return round(1.10 + (mt_rand(0, 90) / 100), 2); // 1.10 to 2.00
    } elseif ($rand < 80) { // 30% chance for 2.01–4.00
        return round(2.01 + (mt_rand(0, 199) / 100), 2); // 2.01 to 4.00
    } elseif ($rand < 95) { // 15% chance for 4.01–7.00
        return round(4.01 + (mt_rand(0, 299) / 100), 2); // 4.01 to 7.00
    } else { // 5% chance for 7.01–20.00
        return round(7.01 + (mt_rand(0, 1299) / 100), 2); // 7.01 to 20.00
    }
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
                    error_log("User registered: username=$username, user_id=" . $pdo->lastInsertId());
                    echo json_encode(['message' => 'User registered', 'user_id' => $pdo->lastInsertId()]);
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    http_response_code(400);
                    echo json_encode(['error' => 'Username already exists']);
                }
            } else {
                error_log("Invalid registration input: username=$username");
                http_response_code(400);
                echo json_encode(['error' => 'Invalid username or password']);
            }
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            try {
                $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ?');
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($password, $user['password'])) {
                    error_log("Login successful: username=$username, user_id={$user['id']}");
                    echo json_encode(['message' => 'Login successful', 'user_id' => $user['id']]);
                } else {
                    error_log("Login failed: username=$username");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid credentials']);
                }
            } catch (PDOException $e) {
                error_log("Login PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'admin_login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            try {
                $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = ?');
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($admin && password_verify($password, $admin['password'])) {
                    error_log("Admin login successful: username=$username, admin_id={$admin['id']}");
                    echo json_encode(['message' => 'Admin logged in', 'admin_id' => $admin['id']]);
                } else {
                    error_log("Admin login failed: username=$username");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid credentials']);
                }
            } catch (PDOException $e) {
                error_log("Admin login PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'set_crash_points':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $admin_id = $data['admin_id'] ?? 0;
            $crash_points = $data['crash_points'] ?? [];

            error_log("set_crash_points input: admin_id=$admin_id, crash_points=" . json_encode($crash_points));

            try {
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
                $stmt->execute([$admin_id]);
                if (!$stmt->fetch()) {
                    error_log("Invalid admin_id: $admin_id");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid admin ID']);
                    exit;
                }

                if (empty($crash_points)) {
                    error_log("No crash points provided");
                    http_response_code(400);
                    echo json_encode(['error' => 'No crash points provided']);
                    exit;
                }

                foreach ($crash_points as $point) {
                    $crash_point = floatval($point['crash_point'] ?? 0);
                    $win_rate = floatval($point['win_rate'] ?? 0);
                    if ($crash_point < 1 || $win_rate < 0 || $win_rate > 100) {
                        error_log("Invalid crash point or win rate: crash_point=$crash_point, win_rate=$win_rate");
                        http_response_code(400);
                        echo json_encode(['error' => "Invalid crash point ($crash_point) or win rate ($win_rate)"]);
                        exit;
                    }
                    $stmt = $pdo->prepare('INSERT INTO games (crash_point, win_rate, set_by_admin_id, is_active, phase) VALUES (?, ?, ?, FALSE, ?)');
                    $stmt->execute([$crash_point, $win_rate, $admin_id, 'betting']);
                }
                error_log("Crash points set by admin_id=$admin_id");
                echo json_encode(['message' => 'Crash points set']);
            } catch (PDOException $e) {
                error_log("set_crash_points PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'get_game_state':
        if ($method === 'GET') {
            try {
                $game_id = $_GET['game_id'] ?? 0;
                if ($game_id > 0) {
                    $stmt = $pdo->prepare('SELECT id, crash_point, phase, start_time FROM games WHERE id = ?');
                    $stmt->execute([$game_id]);
                } else {
                    $stmt = $pdo->prepare('SELECT id, crash_point, phase, start_time FROM games WHERE is_active = TRUE AND phase != ? ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute(['crashed']);
                }
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($game) {
                    $game['crash_point'] = floatval($game['crash_point']);
                    $game['start_time'] = $game['start_time'] ? strtotime($game['start_time']) : null;
                    $current_time = time();
                    $elapsed = $game['start_time'] ? $current_time - $game['start_time'] : 0;
                    $game['elapsed'] = $elapsed;

                    if ($game['phase'] === 'betting' && $elapsed >= 20) {
                        $stmt = $pdo->prepare('UPDATE games SET phase = ?, start_time = NOW() WHERE id = ?');
                        $stmt->execute(['running', $game['id']]);
                        $game['phase'] = 'running';
                        $game['start_time'] = $current_time;
                        $game['elapsed'] = 0;
                    } elseif ($game['phase'] === 'running') {
                        $multiplier = 1 + ($elapsed * 0.5);
                        if ($multiplier >= $game['crash_point']) {
                            $stmt = $pdo->prepare('UPDATE games SET phase = ?, is_active = FALSE WHERE id = ?');
                            $stmt->execute(['crashed', $game['id']]);
                            $game['phase'] = 'crashed';
                        }
                        $game['multiplier'] = round($multiplier, 2);
                    } else {
                        $game['multiplier'] = 1.0;
                    }

                    error_log("get_game_state: game_id={$game['id']}, phase={$game['phase']}, multiplier=" . ($game['multiplier'] ?? 1.0));
                    echo json_encode($game);
                } else {
                    $crash_point = generateCrashPoint();
                    $win_rate = round(mt_rand(10, 90), 2);
                    $stmt = $pdo->prepare('INSERT INTO games (crash_point, win_rate, set_by_admin_id, is_active, phase, start_time) VALUES (?, ?, NULL, TRUE, ?, NOW())');
                    $stmt->execute([$crash_point, $win_rate, 'betting']);
                    $new_game_id = $pdo->lastInsertId();
                    error_log("get_game_state: Created new game_id=$new_game_id, crash_point=$crash_point");
                    echo json_encode([
                        'game_id' => $new_game_id,
                        'crash_point' => $crash_point,
                        'phase' => 'betting',
                        'start_time' => time(),
                        'elapsed' => 0,
                        'multiplier' => 1.0
                    ]);
                }
            } catch (PDOException $e) {
                error_log("get_game_state PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'reset_game':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $game_id = $data['game_id'] ?? 0;
            if ($game_id <= 0) {
                error_log("Invalid game_id for reset_game: $game_id");
                http_response_code(400);
                echo json_encode(['error' => 'Invalid game ID']);
                exit;
            }
            try {
                $stmt = $pdo->prepare('UPDATE games SET is_active = FALSE, phase = ? WHERE id = ?');
                $stmt->execute(['crashed', $game_id]);
                $crash_point = generateCrashPoint();
                $win_rate = round(mt_rand(10, 90), 2);
                $stmt = $pdo->prepare('INSERT INTO games (crash_point, win_rate, set_by_admin_id, is_active, phase, start_time) VALUES (?, ?, NULL, TRUE, ?, NOW())');
                $stmt->execute([$crash_point, $win_rate, 'betting']);
                $new_game_id = $pdo->lastInsertId();
                error_log("Game reset: game_id=$game_id, new game created: game_id=$new_game_id, crash_point=$crash_point");
                echo json_encode(['message' => 'Game reset, new game created', 'new_game_id' => $new_game_id]);
            } catch (PDOException $e) {
                error_log("reset_game PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
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

            try {
                $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    error_log("User not found: user_id=$user_id");
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                $balance = floatval($user['balance']);
                if ($balance < $bet_amount || $bet_amount <= 0) {
                    error_log("Invalid balance or input: balance=$balance, bet_amount=$bet_amount");
                    http_response_code(400);
                    echo json_encode(['error' => 'Insufficient balance or invalid input']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT id, phase FROM games WHERE id = ? AND is_active = TRUE');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$game || $game['phase'] !== 'betting') {
                    error_log("Invalid or inactive game_id: $game_id, phase=" . ($game['phase'] ?? 'none'));
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid or inactive game ID']);
                    exit;
                }

                $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$bet_amount, $user_id]);

                $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare('INSERT INTO bets (user_id, game_id, bet_amount) VALUES (?, ?, ?)');
                $stmt->execute([$user_id, $game_id, $bet_amount]);
                $bet_id = $pdo->lastInsertId();
                error_log("Bet placed: user_id=$user_id, game_id=$game_id, bet_amount=$bet_amount");
                echo json_encode(['message' => 'Bet placed', 'bet_id' => $bet_id]);
            } catch (PDOException $e) {
                error_log("place_bet PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $multiplier = floatval($data['multiplier'] ?? 0);
            $game_id = $data['game_id'] ?? 0;

            error_log("cashout input: " . print_r($data, true));

            try {
                if ($multiplier <= 0) {
                    error_log("Invalid multiplier: $multiplier");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid multiplier']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT crash_point, phase FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$game || $game['phase'] !== 'running') {
                    error_log("Game not found or not running: game_id=$game_id, phase=" . ($game['phase'] ?? 'none'));
                    http_response_code(404);
                    echo json_encode(['error' => 'Game not found or not running']);
                    exit;
                }
                $crash_point = floatval($game['crash_point']);
                if ($multiplier > $crash_point) {
                    error_log("Multiplier exceeds crash point: multiplier=$multiplier, crash_point=$crash_point");
                    http_response_code(400);
                    echo json_encode(['error' => 'Game crashed']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT user_id, bet_amount FROM bets WHERE id = ?');
                $stmt->execute([$bet_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$bet) {
                    error_log("Bet not found: bet_id=$bet_id");
                    http_response_code(404);
                    echo json_encode(['error' => 'Invalid bet']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $stmt->execute([$bet['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $win_amount = floatval($bet['bet_amount']) * $multiplier;
                $stmt = $pdo->prepare('UPDATE bets SET cashout_multiplier = ?, win_amount = ?, cashout_status = ? WHERE id = ?');
                $stmt->execute([$multiplier, $win_amount, 'approved', $bet_id]);

                $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$win_amount, $bet['user_id']]);
                error_log("Cashout successful: bet_id=$bet_id, user_id={$bet['user_id']}, multiplier=$multiplier, win_amount=$win_amount");
                echo json_encode(['message' => 'Cashout successful', 'win_amount' => $win_amount]);
            } catch (PDOException $e) {
                error_log("cashout PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'get_active_bets':
        if ($method === 'GET') {
            try {
                $game_id = $_GET['game_id'] ?? 0;
                $stmt = $pdo->prepare('SELECT b.bet_amount, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.game_id = ? AND b.cashout_status IS NULL');
                $stmt->execute([$game_id]);
                $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($bets as &$bet) {
                    $bet['bet_amount'] = floatval($bet['bet_amount']);
                }
                error_log("get_active_bets: game_id=$game_id, count=" . count($bets));
                echo json_encode($bets);
            } catch (PDOException $e) {
                error_log("get_active_bets PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'request_transaction':
        if ($method === 'POST') {
            try {
                $raw_input = file_get_contents('php://input');
                error_log("request_transaction raw input: " . $raw_input);
                $data = json_decode($raw_input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("request_transaction JSON parse error: " . json_last_error_msg());
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid JSON input: ' . json_last_error_msg()]);
                    exit;
                }

                $user_id = $data['user_id'] ?? 0;
                $amount = floatval($data['amount'] ?? 0);
                $type = $data['type'] ?? 'topup';

                error_log("request_transaction input: user_id=$user_id, amount=$amount, type=$type");

                if ($amount <= 0 || !$user_id) {
                    error_log("Invalid transaction request: user_id=$user_id, amount=$amount, type=$type");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid amount or user ID']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    error_log("User not found: user_id=$user_id");
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }

                if ($type === 'withdraw') {
                    $balance = floatval($user['balance']);
                    error_log("Withdraw balance check: user_id=$user_id, balance=$balance, requested_amount=$amount");
                    if ($balance < $amount) {
                        error_log("Insufficient balance for withdraw: user_id=$user_id, balance=$balance, amount=$amount");
                        http_response_code(400);
                        echo json_encode(['error' => 'Insufficient balance']);
                        exit;
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO topup_requests (user_id, amount, type, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user_id, $amount, $type, 'pending']);
                $request_id = $pdo->lastInsertId();
                error_log("Transaction request submitted: user_id=$user_id, amount=$amount, type=$type, request_id=$request_id");
                echo json_encode(['message' => ucfirst($type) . ' request submitted', 'request_id' => $request_id]);
            } catch (PDOException $e) {
                error_log("request_transaction PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                exit;
            } catch (Exception $e) {
                error_log("request_transaction error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
                exit;
            }
        }
        break;

    case 'get_pending_transactions':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->prepare('SELECT t.id, t.user_id, t.amount, t.type, u.username FROM topup_requests t JOIN users u ON t.user_id = u.id WHERE t.status = ?');
                $stmt->execute(['pending']);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($requests as &$request) {
                    $request['amount'] = floatval($request['amount']);
                }
                error_log("get_pending_transactions: count=" . count($requests));
                echo json_encode($requests);
            } catch (PDOException $e) {
                error_log("get_pending_transactions PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'approve_transaction':
        if ($method === 'POST') {
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $request_id = $data['request_id'] ?? 0;
                $stmt = $pdo->prepare('SELECT user_id, amount, type FROM topup_requests WHERE id = ? AND status = ?');
                $stmt->execute([$request_id, 'pending']);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$request) {
                    error_log("Invalid or already processed transaction request: request_id=$request_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid or already processed request']);
                    exit;
                }

                if ($request['type'] === 'withdraw') {
                    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
                    $stmt->execute([$request['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user || floatval($user['balance']) < floatval($request['amount'])) {
                        error_log("Insufficient balance for withdraw approval: user_id={$request['user_id']}, balance=" . ($user['balance'] ?? 'N/A') . ", amount={$request['amount']}");
                        http_response_code(400);
                        echo json_encode(['error' => 'Insufficient balance']);
                        exit;
                    }
                    $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                    $stmt->execute([floatval($request['amount']), $request['user_id']]);
                } elseif ($request['type'] === 'topup') {
                    $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                    $stmt->execute([floatval($request['amount']), $request['user_id']]);
                }

                $stmt = $pdo->prepare('UPDATE topup_requests SET status = ? WHERE id = ?');
                $stmt->execute(['approved', $request_id]);
                error_log("Transaction approved: request_id=$request_id, user_id={$request['user_id']}, amount={$request['amount']}, type={$request['type']}");
                echo json_encode(['message' => ucfirst($request['type']) . ' approved']);
            } catch (PDOException $e) {
                error_log("approve_transaction PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'reject_transaction':
        if ($method === 'POST') {
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $request_id = $data['request_id'] ?? 0;
                $stmt = $pdo->prepare('UPDATE topup_requests SET status = ? WHERE id = ? AND status = ?');
                $stmt->execute(['rejected', $request_id, 'pending']);
                if ($stmt->rowCount() > 0) {
                    error_log("Transaction rejected: request_id=$request_id");
                    echo json_encode(['message' => 'Transaction rejected']);
                } else {
                    error_log("Invalid or already processed transaction request: request_id=$request_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid or already processed request']);
                }
            } catch (PDOException $e) {
                error_log("reject_transaction PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'get_crash_history':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->prepare('SELECT id, crash_point, created_at FROM games ORDER BY created_at DESC');
                $stmt->execute();
                $crashes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($crashes as &$crash) {
                    $crash['crash_point'] = floatval($crash['crash_point']);
                }
                error_log("get_crash_history: count=" . count($crashes));
                echo json_encode($crashes);
            } catch (PDOException $e) {
                error_log("get_crash_history PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'get_user':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            error_log("get_user input: user_id=$user_id");
            if ($user_id <= 0) {
                error_log("Invalid user_id: $user_id");
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            try {
                $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $user['balance'] = floatval($user['balance']);
                    error_log("get_user success: user_id=$user_id, username={$user['username']}, balance={$user['balance']}, type=" . gettype($user['balance']));
                    echo json_encode($user);
                } else {
                    error_log("User not found: user_id=$user_id");
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } catch (PDOException $e) {
                error_log("get_user PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'get_history':
        if ($method === 'GET') {
            try {
                $user_id = $_GET['user_id'] ?? 0;
                $stmt = $pdo->prepare('SELECT b.*, g.crash_point FROM bets b JOIN games g ON b.game_id = g.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
                $stmt->execute([$user_id]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($history as &$bet) {
                    $bet['bet_amount'] = floatval($bet['bet_amount']);
                    $bet['cashout_multiplier'] = floatval($bet['cashout_multiplier'] ?? 0);
                    $bet['win_amount'] = floatval($bet['win_amount'] ?? 0);
                    $bet['crash_point'] = floatval($bet['crash_point']);
                }
                error_log("get_history: user_id=$user_id, history_count=" . count($history));
                echo json_encode($history);
            } catch (PDOException $e) {
                error_log("get_history PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'debug':
        if ($method === 'GET') {
            error_log("Debug endpoint called");
            echo json_encode(['status' => 'OK', 'timestamp' => date('Y-m-d H:i:s'), 'php_version' => phpversion(), 'pdo_enabled' => extension_loaded('pdo_mysql')]);
        }
        break;

    default:
        error_log("Invalid action: $action");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>