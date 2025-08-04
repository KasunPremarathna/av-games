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
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function generateCrashPoint() {
    $rand = mt_rand(0, 10000) / 100;
    if ($rand < 50) {
        return round(1.10 + (mt_rand(0, 90) / 100), 2);
    } elseif ($rand < 80) {
        return round(2.01 + (mt_rand(0, 199) / 100), 2);
    } elseif ($rand < 95) {
        return round(4.01 + (mt_rand(0, 299) / 100), 2);
    } else {
        return round(7.01 + (mt_rand(0, 1299) / 100), 2);
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

    case 'get_game_settings':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings) {
                    $settings['betting_duration'] = floatval($settings['betting_duration']);
                    $settings['running_duration'] = floatval($settings['running_duration']);
                    error_log("get_game_settings: betting_duration={$settings['betting_duration']}, running_duration={$settings['running_duration']}");
                    echo json_encode($settings);
                } else {
                    error_log("get_game_settings: No settings found");
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
            $betting_duration = floatval($data['betting_duration'] ?? 10);
            $running_duration = floatval($data['running_duration'] ?? 60);

            error_log("set_game_settings input: admin_id=$admin_id, betting_duration=$betting_duration, running_duration=$running_duration");

            if ($betting_duration < 5 || $betting_duration > 30 || $running_duration < 10 || $running_duration > 120) {
                error_log("Invalid durations: betting_duration=$betting_duration, running_duration=$running_duration");
                http_response_code(400);
                echo json_encode(['error' => 'Invalid durations: Betting (5-30s), Running (10-120s)']);
                exit;
            }

            try {
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
                $stmt->execute([$admin_id]);
                if (!$stmt->fetch()) {
                    error_log("Invalid admin_id: $admin_id");
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid admin ID']);
                    exit;
                }

                $stmt = $pdo->prepare('INSERT INTO game_settings (betting_duration, running_duration, set_by_admin_id) VALUES (?, ?, ?)');
                $stmt->execute([$betting_duration, $running_duration, $admin_id]);
                error_log("Game settings updated: betting_duration=$betting_duration, running_duration=$running_duration, admin_id=$admin_id");
                echo json_encode(['message' => 'Game settings updated']);
            } catch (PDOException $e) {
                error_log("set_game_settings PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
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
                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $betting_duration = $settings ? floatval($settings['betting_duration']) : 10.0;
                $running_duration = $settings ? floatval($settings['running_duration']) : 60.0;

                $game_id = $_GET['game_id'] ?? 0;
                if ($game_id > 0) {
                    $stmt = $pdo->prepare('SELECT id, crash_point, phase, start_time FROM games WHERE id = ? AND is_active = TRUE');
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
                    $game['betting_duration'] = $betting_duration;
                    $game['running_duration'] = $running_duration;

                    if ($game['phase'] === 'betting' && $elapsed > $betting_duration * 2) {
                        error_log("Stale betting phase detected: game_id={$game['id']}, elapsed=$elapsed. Resetting game.");
                        $stmt = $pdo->prepare('UPDATE games SET phase = ?, is_active = FALSE WHERE id = ?');
                        $stmt->execute(['crashed', $game['id']]);
                        $game = null;
                    } elseif ($game['phase'] === 'betting' && $elapsed >= $betting_duration) {
                        $stmt = $pdo->prepare('UPDATE games SET phase = ?, start_time = NOW() WHERE id = ?');
                        $stmt->execute(['running', $game['id']]);
                        $game['phase'] = 'running';
                        $game['start_time'] = $current_time;
                        $game['elapsed'] = 0;
                    } elseif ($game['phase'] === 'running' && $elapsed >= $running_duration) {
                        $stmt = $pdo->prepare('UPDATE games SET phase = ?, is_active = FALSE WHERE id = ?');
                        $stmt->execute(['crashed', $game['id']]);
                        $game['phase'] = 'crashed';
                        $game['multiplier'] = $game['crash_point'];
                    } elseif ($game['phase'] === 'running') {
                        $progress = $elapsed / $running_duration;
                        $multiplier = 1 + ($progress * ($game['crash_point'] - 1));
                        $game['multiplier'] = round($multiplier, 2);
                    } else {
                        $game['multiplier'] = 1.0;
                    }

                    if ($game) {
                        error_log("get_game_state: game_id={$game['id']}, phase={$game['phase']}, multiplier=" . ($game['multiplier'] ?? 1.0) . ", elapsed=$elapsed");
                        echo json_encode($game);
                    }
                }

                if (!$game) {
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
                        'multiplier' => 1.0,
                        'betting_duration' => $betting_duration,
                        'running_duration' => $running_duration
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

            error_log("place_bet input: user_id=$user_id, bet_amount=$bet_amount, game_id=$game_id");

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
                    error_log("Invalid or inactive game_id: $game_id, phase=" . ($game['phase'] ?? 'none') . ". Fetching latest active game.");
                    $stmt = $pdo->prepare('SELECT id, phase FROM games WHERE is_active = TRUE AND phase = ? ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute(['betting']);
                    $game = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$game) {
                        error_log("No active betting game found for user_id=$user_id");
                        http_response_code(400);
                        echo json_encode(['error' => 'No active game available']);
                        exit;
                    }
                    $game_id = $game['id'];
                    error_log("Using fallback game_id: $game_id");
                }

                $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$bet_amount, $user_id]);

                $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare('INSERT INTO bets (user_id, game_id, bet_amount) VALUES (?, ?, ?)');
                $stmt->execute([$user_id, $game_id, $bet_amount]);
                $bet_id = $pdo->lastInsertId();
                error_log("Bet placed: user_id=$user_id, game_id=$game_id, bet_amount=$bet_amount, bet_id=$bet_id");
                echo json_encode(['message' => 'Bet placed', 'bet_id' => $bet_id, 'game_id' => $game_id]);
            } catch (PDOException $e) {
                error_log("place_bet PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    case 'validate_bet':
        if ($method === 'GET') {
            $bet_id = $_GET['bet_id'] ?? 0;
            $game_id = $_GET['game_id'] ?? 0;
            $user_id = $_GET['user_id'] ?? 0;
            error_log("validate_bet input: bet_id=$bet_id, game_id=$game_id, user_id=$user_id");
            try {
                $stmt = $pdo->prepare('SELECT b.id, b.game_id, b.user_id, b.cashout_status, g.phase, g.is_active FROM bets b JOIN games g ON b.game_id = g.id WHERE b.id = ? AND b.game_id = ? AND b.user_id = ?');
                $stmt->execute([$bet_id, $game_id, $user_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$bet) {
                    error_log("validate_bet: Bet not found for bet_id=$bet_id, game_id=$game_id, user_id=$user_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bet']);
                    exit;
                }
                if ($bet['cashout_status'] !== null) {
                    error_log("validate_bet: Bet already cashed out, bet_id=$bet_id, cashout_status={$bet['cashout_status']}");
                    http_response_code(400);
                    echo json_encode(['error' => 'Bet already cashed out']);
                    exit;
                }
                if (!$bet['is_active'] || $bet['phase'] !== 'running') {
                    error_log("validate_bet: Game not in running phase, game_id=$game_id, phase={$bet['phase']}, is_active={$bet['is_active']}");
                    http_response_code(400);
                    echo json_encode(['error' => 'Game not in running phase']);
                    exit;
                }
                error_log("validate_bet: Valid bet, bet_id=$bet_id, game_id=$game_id, user_id=$user_id, phase={$bet['phase']}");
                echo json_encode(['message' => 'Bet is valid']);
            } catch (PDOException $e) {
                error_log("validate_bet PDO error: " . $e->getMessage());
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

            error_log("cashout input: bet_id=$bet_id, multiplier=$multiplier, game_id=$game_id");

            try {
                if ($bet_id <= 0 || $game_id <= 0) {
                    error_log("Invalid input: bet_id=$bet_id, game_id=$game_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bet or game ID']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT crash_point, phase, start_time FROM games WHERE id = ? AND is_active = TRUE');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$game) {
                    error_log("Game not found: game_id=$game_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Game not found']);
                    exit;
                }

                if ($game['phase'] !== 'running') {
                    error_log("Game not in running phase: game_id=$game_id, phase={$game['phase']}");
                    http_response_code(400);
                    echo json_encode(['error' => 'Game not in running phase', 'crash_point' => floatval($game['crash_point'])]);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT betting_duration, running_duration FROM game_settings ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                $running_duration = $settings ? floatval($settings['running_duration']) : 60.0;

                $elapsed = time() - strtotime($game['start_time']);
                $progress = $elapsed / $running_duration;
                $crash_point = floatval($game['crash_point']);
                $server_multiplier = round(1 + ($progress * ($crash_point - 1)), 2);

                if ($server_multiplier >= $crash_point) {
                    $stmt = $pdo->prepare('UPDATE games SET phase = ?, is_active = FALSE WHERE id = ?');
                    $stmt->execute(['crashed', $game_id]);
                    error_log("Game crashed: game_id=$game_id, server_multiplier=$server_multiplier, crash_point=$crash_point");
                    http_response_code(400);
                    echo json_encode(['error' => 'Game crashed', 'crash_point' => $crash_point]);
                    exit;
                }

                $final_multiplier = $multiplier > 0 && $multiplier <= $crash_point ? $multiplier : $server_multiplier;
                if ($final_multiplier <= 0 || $final_multiplier > $crash_point) {
                    error_log("Invalid multiplier: client_multiplier=$multiplier, server_multiplier=$server_multiplier, crash_point=$crash_point");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid multiplier', 'crash_point' => $crash_point]);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT user_id, bet_amount FROM bets WHERE id = ? AND game_id = ? AND cashout_status IS NULL');
                $stmt->execute([$bet_id, $game_id]);
                $bet = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$bet) {
                    error_log("Bet not found or already cashed out: bet_id=$bet_id, game_id=$game_id");
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bet or already cashed out']);
                    exit;
                }

                $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $stmt->execute([$bet['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $win_amount = floatval($bet['bet_amount']) * $final_multiplier;
                $stmt = $pdo->prepare('UPDATE bets SET cashout_multiplier = ?, win_amount = ?, cashout_status = ? WHERE id = ?');
                $stmt->execute([$final_multiplier, $win_amount, 'approved', $bet_id]);

                $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$win_amount, $bet['user_id']]);
                error_log("Cashout successful: bet_id=$bet_id, user_id={$bet['user_id']}, final_multiplier=$final_multiplier, win_amount=$win_amount, server_multiplier=$server_multiplier, phase={$game['phase']}");
                echo json_encode(['message' => 'Cashout successful', 'win_amount' => $win_amount, 'multiplier' => $final_multiplier]);
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

    case 'get_history':
        if ($method === 'GET') {
            try {
                $user_id = $_GET['user_id'] ?? 0;
                $stmt = $pdo->prepare('SELECT b.id, b.user_id, b.game_id, b.bet_amount, b.cashout_multiplier, b.win_amount, b.cashout_status, b.created_at, u.username, g.crash_point 
                                      FROM bets b 
                                      JOIN users u ON b.user_id = u.id 
                                      JOIN games g ON b.game_id = g.id 
                                      WHERE b.user_id = ? 
                                      ORDER BY b.created_at DESC');
                $stmt->execute([$user_id]);
                $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($bets as &$bet) {
                    $bet['bet_amount'] = floatval($bet['bet_amount']);
                    $bet['cashout_multiplier'] = floatval($bet['cashout_multiplier']);
                    $bet['win_amount'] = floatval($bet['win_amount']);
                    $bet['crash_point'] = floatval($bet['crash_point']);
                }
                error_log("get_history: user_id=$user_id, count=" . count($bets));
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
                $stmt = $pdo->prepare('SELECT id, crash_point, created_at FROM games WHERE phase = ? ORDER BY created_at DESC LIMIT 10');
                $stmt->execute(['crashed']);
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
                echo json_encode(['message' => ucfirst($type) . ' request submitted']);
            } catch (PDOException $e) {
                error_log("request_transaction PDO error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
        break;

    default:
        error_log("Invalid action: $action");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>