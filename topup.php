<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

    // Validate amount
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid amount']);
        http_response_code(400);
        exit;
    }

    // Mock payment processing (replace with Stripe/PayPal integration)
    // Assume payment is successful
    $stmt = $conn->prepare("UPDATE balances SET balance = balance + ?, last_updated = NOW() WHERE user_id = ?");
    $stmt->bind_param("di", $amount, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Top-up successful']);
        http_response_code(200);
    } else {
        echo json_encode(['error' => 'Top-up failed']);
        http_response_code(500);
    }

    $stmt->close();
    $conn->close();
}
?>