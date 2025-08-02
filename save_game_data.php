<?php
header('Content-Type: application/json');
include 'db_config.php'; // Database connection file

// Get data from POST request
$player_id = isset($_POST['player_id']) ? $_POST['player_id'] : '';
$score = isset($_POST['score']) ? (int)$_POST['score'] : 0;
$bet_amount = isset($_POST['bet_amount']) ? (float)$_POST['bet_amount'] : 0.00;
$multiplier = isset($_POST['multiplier']) ? (float)$_POST['multiplier'] : 1.00;

// Validate input
if (empty($player_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Player ID is required']);
    exit;
}

// Prepare and execute SQL query
$stmt = $conn->prepare("INSERT INTO game_results (player_id, score, bet_amount, multiplier) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sidd", $player_id, $score, $bet_amount, $multiplier);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'Game data saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save game data']);
}

$stmt->close();
$conn->close();
?>