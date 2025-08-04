<?php
header('Content-Type: application/json');

$db_config = [
    'host' => 'localhost',
    'dbname' => 'kasunpre_av',
    'user' => 'kasunpre_av',
    'password' => 'Kasun2052'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['user'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = sanitize($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $role = sanitize($data['role'] ?? '');

            if (empty($username) || empty($password) || empty($role)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username, password, and role are required']);
                exit;
            }

            if (strlen($username) < 3 || strlen($username) > 50) {
                http_response_code(400);
                echo json_encode(['error' => 'Username must be between 3 and 50 characters']);
                exit;
            }

            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'Password must be at least 6 characters']);
                exit;
            }

            if ($role !== 'user' && $role !== 'admin') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role']);
                exit;
            }

            try {
                // Check if username exists
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Username already exists']);
                    exit;
                }

                // Insert into users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password, balance) VALUES (?, ?, 1000.00)');
                $stmt->execute([$username, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                // If admin, insert into admins table
                if ($role === 'admin') {
                    $stmt = $pdo->prepare('INSERT INTO admins (id, username, password) VALUES (?, ?, ?)');
                    $stmt->execute([$user_id, $username, $hashed_password]);
                }

                error_log("register: user_id=$user_id, username=$username, role=$role");
                echo json_encode(['user_id' => $user_id, 'message' => 'Registration successful']);
            } catch (PDOException $e) {
                error_log("register PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error during registration']);
            }
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = sanitize($data['username'] ?? '');
            $password = $data['password'] ?? '';

            try {
                $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    error_log("login: user_id={$user['id']}, username=$username");
                    echo json_encode(['user_id' => $user['id']]);
                } else {
                    error_log("login failed: username=$username");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid username or password']);
                }
            } catch (PDOException $e) {
                error_log("login PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'admin_login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = sanitize($data['username'] ?? '');
            $password = $data['password'] ?? '';

            try {
                $stmt = $pdo->prepare('SELECT a.id, a.username, a.password FROM admins a JOIN users u ON a.id = u.id WHERE a.username = ?');
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    error_log("admin_login: admin_id={$admin['id']}, username=$username");
                    echo json_encode(['admin_id' => $admin['id']]);
                } else {
                    error_log("admin_login failed: username=$username");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid admin username or password']);
                }
            } catch (PDOException $e) {
                error_log("admin_login PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_user':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            try {
                $stmt = $pdo->prepare('SELECT u.id, u.username, u.balance, COUNT(a.id) as is_admin FROM users u LEFT JOIN admins a ON u.id = a.id WHERE u.id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $user['balance'] = floatval($user['balance']);
                    $user['is_admin'] = $user['is_admin'] > 0;
                    unset($user['password']);
                    error_log("get_user: user_id=$user_id, username={$user['username']}, is_admin={$user['is_admin']}");
                    echo json_encode($user);
                } else {
                    error_log("get_user: User not found, user_id=$user_id");
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } catch (PDOException $e) {
                error_log("get_user PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_game_state':
        if ($method === 'GET') {
            $game_id = $_GET['game_id'] ?? 0;
            try {
                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);

                $betting_duration = floatval($settings['betting_duration'] ?? 10);
                $running_duration = floatval($settings['running_duration'] ?? 60);

                $game_id = max(1, intval($game_id));
                $stmt = $pdo->prepare('SELECT crash_point, created_at FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$game) {
                    $stmt = $pdo->prepare('INSERT INTO games (crash_point) VALUES (NULL)');
                    $stmt->execute();
                    $game_id = $pdo->lastInsertId();
                    $game = ['crash_point' => null, 'created_at' => date('Y-m-d H:i:s')];
                }

                $elapsed = $game['created_at'] ? (time() - strtotime($game['created_at'])) : 0;
                $phase = 'betting';
                $multiplier = 1.0;

                if ($game['crash_point']) {
                    $phase = 'crashed';
                    $multiplier = floatval($game['crash_point']);
                } elseif ($elapsed >= $betting_duration) {
                    $phase = 'running';
                    $multiplier = 1.0 + ($elapsed - $betting_duration) / $running_duration * 9.0;
                    if ($multiplier >= 10.0 || $elapsed >= ($betting_duration + $running_duration)) {
                        $crash_point = rand(100, 1000) / 100;
                        $stmt = $pdo->prepare('UPDATE games SET crash_point = ? WHERE id = ?');
                        $stmt->execute([$crash_point, $game_id]);
                        $phase = 'crashed';
                        $multiplier = $crash_point;
                    }
                }

                echo json_encode([
                    'game_id' => $game_id,
                    'phase' => $phase,
                    'multiplier' => round($multiplier, 2),
                    'crash_point' => $game['crash_point'] ? floatval($game['crash_point']) : null,
                    'elapsed' => $elapsed,
                    'betting_duration' => $betting_duration,
                    'running_duration' => $running_duration
                ]);
            } catch (PDOException $e) {
                error_log("get_game_state PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'place_bet':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $bet_amount = floatval($data['bet_amount'] ?? 0);
            $game_id = $data['game_id'] ?? 0;

            if ($bet_amount <= 0 || $user_id <= 0 || $game_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid bet amount, user ID, or game ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user || $user['balance'] < $bet_amount) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Insufficient balance or user not found']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT crash_point, created_at FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$game || $game['crash_point']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No active game available']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT betting_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $betting_duration = floatval($settings['betting_duration'] ?? 10);

                $elapsed = time() - strtotime($game['created_at']);
                if ($elapsed >= $betting_duration) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Betting phase has ended']);
                    exit;
                }

                $stmt = $pdo->prepare('INSERT INTO bets (user_id, game_id, bet_amount) VALUES (?, ?, ?)');
                $stmt->execute([$user_id, $game_id, $bet_amount]);
                $bet_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$bet_amount, $user_id]);

                error_log("place_bet: bet_id=$bet_id, user_id=$user_id, game_id=$game_id, bet_amount=$bet_amount");
                echo json_encode(['bet_id' => $bet_id, 'game_id' => $game_id]);
            } catch (PDOException $e) {
                error_log("place_bet PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'validate_bet':
        if ($method === 'GET') {
            $bet_id = $_GET['bet_id'] ?? 0;
            $game_id = $_GET['game_id'] ?? 0;
            $user_id = $_GET['user_id'] ?? 0;

            try {
                $stmt = $pdo->prepare('SELECT id, cashout_status FROM bets WHERE id = ? AND game_id = ? AND user_id = ?');
                $stmt->execute([$bet_id, $game_id, $user_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$bet) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bet']);
                    exit;
                }
                if ($bet['cashout_status'] === 'approved') {
                    http_response_code(400);
                    echo json_encode(['error' => 'Bet already cashed out']);
                    exit;
                }
                echo json_encode(['valid' => true]);
            } catch (PDOException $e) {
                error_log("validate_bet PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'cashout':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $bet_id = $data['bet_id'] ?? 0;
            $multiplier = floatval($data['multiplier'] ?? 0);
            $game_id = $data['game_id'] ?? 0;

            if ($multiplier <= 0 || $bet_id <= 0 || $game_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid bet ID, game ID, or multiplier']);
                exit;
            }

            try {
                $stmt = $pdo->prepare('SELECT user_id, bet_amount FROM bets WHERE id = ? AND game_id = ? AND cashout_status IS NULL');
                $stmt->execute([$bet_id, $game_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$bet) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bet or already cashed out']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT crash_point, created_at FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$game || $game['crash_point']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Game crashed or not found', 'crash_point' => $game['crash_point'] ?? 1.0]);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $betting_duration = floatval($settings['betting_duration'] ?? 10);
                $running_duration = floatval($settings['running_duration'] ?? 60);

                $elapsed = time() - strtotime($game['created_at']);
                if ($elapsed < $betting_duration || $elapsed >= ($betting_duration + $running_duration)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Cannot cash out: Game not in running phase']);
                    exit;
                }

                $win_amount = $bet['bet_amount'] * $multiplier;
                $stmt = $pdo->prepare('UPDATE bets SET cashout_status = "approved", cashout_multiplier = ?, win_amount = ? WHERE id = ?');
                $stmt->execute([$multiplier, $win_amount, $bet_id]);

                $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$win_amount, $bet['user_id']]);

                error_log("cashout: bet_id=$bet_id, user_id={$bet['user_id']}, multiplier=$multiplier, win_amount=$win_amount");
                echo json_encode(['multiplier' => $multiplier, 'win_amount' => $win_amount]);
            } catch (PDOException $e) {
                error_log("cashout PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_active_bets':
        if ($method === 'GET') {
            $game_id = $_GET['game_id'] ?? 0;
            try {
                $stmt = $pdo->prepare('SELECT b.id, b.bet_amount, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.game_id = ? AND b.cashout_status IS NULL');
                $stmt->execute([$game_id]);
                $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($bets);
            } catch (PDOException $e) {
                error_log("get_active_bets PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_history':
        if ($method === 'GET') {
            $user_id = $_GET['user_id'] ?? 0;
            try {
                $stmt = $pdo->prepare('SELECT b.id, b.game_id, b.bet_amount, b.cashout_status, b.cashout_multiplier, b.win_amount, b.created_at, g.crash_point, u.username 
                                      FROM bets b 
                                      JOIN users u ON b.user_id = u.id 
                                      LEFT JOIN games g ON b.game_id = g.id 
                                      WHERE b.user_id = ? 
                                      ORDER BY b.created_at DESC');
                $stmt->execute([$user_id]);
                $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($bets);
            } catch (PDOException $e) {
                error_log("get_history PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_crash_history':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->prepare('SELECT id, crash_point, created_at FROM games WHERE crash_point IS NOT NULL ORDER BY created_at DESC LIMIT 10');
                $stmt->execute();
                $crashes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($crashes);
            } catch (PDOException $e) {
                error_log("get_crash_history PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'request_transaction':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $amount = floatval($data['amount'] ?? 0);
            $type = $data['type'] ?? '';

            if ($amount <= 0 || $user_id <= 0 || !in_array($type, ['topup', 'withdraw'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid amount, user ID, or transaction type']);
                exit;
            }

            try {
                if ($type === 'withdraw') {
                    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user || $user['balance'] < $amount) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Insufficient balance or user not found']);
                        exit;
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, amount, type, status) VALUES (?, ?, ?, "pending")');
                $stmt->execute([$user_id, $amount, $type]);
                error_log("$type request: user_id=$user_id, amount=$amount");
                echo json_encode(['message' => 'Transaction request submitted']);
            } catch (PDOException $e) {
                error_log("request_transaction PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'get_game_settings':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings) {
                    $settings['betting_duration'] = floatval($settings['betting_duration']);
                    $settings['running_duration'] = floatval($settings['running_duration']);
                    echo json_encode($settings);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'No game settings found']);
                }
            } catch (PDOException $e) {
                error_log("get_game_settings PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'set_game_settings':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $admin_id = $data['admin_id'] ?? 0;
            $betting_duration = floatval($data['betting_duration'] ?? 0);
            $running_duration = floatval($data['running_duration'] ?? 0);

            if ($betting_duration < 5 || $betting_duration > 30 || $running_duration < 10 || $running_duration > 120 || $admin_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid durations or admin ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
                $stmt->execute([$admin_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized: Admin access required']);
                    exit;
                }

                $stmt = $pdo->prepare('INSERT INTO game_settings (betting_duration, running_duration, set_by_admin_id) VALUES (?, ?, ?)');
                $stmt->execute([$betting_duration, $running_duration, $admin_id]);
                error_log("set_game_settings: admin_id=$admin_id, betting_duration=$betting_duration, running_duration=$running_duration");
                echo json_encode(['message' => 'Game settings updated']);
            } catch (PDOException $e) {
                error_log("set_game_settings PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    case 'reset_game':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $game_id = $data['game_id'] ?? 0;

            try {
                $stmt = $pdo->prepare('SELECT crash_point FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($game && !$game['crash_point']) {
                    $crash_point = rand(100, 1000) / 100;
                    $stmt = $pdo->prepare('UPDATE games SET crash_point = ? WHERE id = ?');
                    $stmt->execute([$crash_point, $game_id]);
                }

                $stmt = $pdo->prepare('INSERT INTO games (crash_point) VALUES (NULL)');
                $stmt->execute();
                $new_game_id = $pdo->lastInsertId();

                error_log("reset_game: old_game_id=$game_id, new_game_id=$new_game_id");
                echo json_encode(['new_game_id' => $new_game_id]);
            } catch (PDOException $e) {
                error_log("reset_game PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>