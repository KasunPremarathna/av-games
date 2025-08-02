<?php
session_start();
include 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user balance
$stmt = $conn->prepare("SELECT balance FROM balances WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['balance'];

// Fetch win history
$stmt = $conn->prepare("SELECT score, bet_amount, multiplier, game_date FROM game_results WHERE user_id = ? ORDER BY game_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Panel - Aviator Paper Bet</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .logout { float: right; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <a href="logout.php" class="logout">Logout</a>
        <h3>Account Balance: $<?php echo number_format($balance, 2); ?></h3>
        
        <h3>Top Up Account</h3>
        <form id="topupForm">
            <input type="number" id="amount" placeholder="Amount (LKR)" step="0.01" required>
            <button type="submit">Top Up</button>
        </form>

        <h3>Win History</h3>
        <table>
            <tr>
                <th>Score</th>
                <th>Bet Amount</th>
                <th>Multiplier</th>
                <th>Date</th>
            </tr>
            <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo $result['score']; ?></td>
                <td><?php echo number_format($result['bet_amount'], 2); ?></td>
                <td><?php echo number_format($result['multiplier'], 2); ?>x</td>
                <td><?php echo $result['game_date']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <script>
        document.getElementById('topupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const amount = document.getElementById('amount').value;

            try {
                const response = await fetch('topup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `amount=${encodeURIComponent(amount)}`
                });
                const result = await response.json();
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    alert(result.message || result.error);
                    if (result.message) {
                        location.reload(); // Refresh to update balance
                    }
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    </script>
</body>
</html>