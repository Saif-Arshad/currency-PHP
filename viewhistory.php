<?php
session_start();
$db = new SQLite3('Currenzy.db');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user's account ID
$userId = $_SESSION['user_id'];
$accountQuery = $db->prepare("SELECT Account_ID FROM Account WHERE User_ID = :user_id");
$accountQuery->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$accountResult = $accountQuery->execute()->fetchArray(SQLITE3_ASSOC);

if (!$accountResult) {
    die("Account not found.");
}

$accountId = $accountResult['Account_ID'];

// Fetch transactions involving the user (sent or received)
$transactions = $db->query("
    SELECT 
        t.Transaction_ID,
        t.Sender_account_ID,
        t.Receiver_account_ID,
        t.Currency_ID_from,
        t.Currency_ID_to,
        t.Exchange_rate,
        t.Fee,
        t.Amount,
        t.Time,
        t.Status,
        cs.Symbol AS FromSymbol,
        ct.Symbol AS ToSymbol,
        u1.First_name || ' ' || u1.Last_name AS SenderName,
        u2.First_name || ' ' || u2.Last_name AS ReceiverName
    FROM Transactions t
    JOIN Account a1 ON t.Sender_account_ID = a1.Account_ID
    JOIN Account a2 ON t.Receiver_account_ID = a2.Account_ID
    JOIN User u1 ON a1.User_ID = u1.User_ID
    JOIN User u2 ON a2.User_ID = u2.User_ID
    JOIN Currency cs ON t.Currency_ID_from = cs.Currency_ID
    JOIN Currency ct ON t.Currency_ID_to = ct.Currency_ID
    WHERE t.Sender_account_ID = $accountId OR t.Receiver_account_ID = $accountId
    ORDER BY t.Time DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Transaction History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0f172a;
            color: #f1f5f9;
            padding: 40px;
        }
        .table {
            background-color: rgba(255,255,255,0.05);
        }
        th, td {
            vertical-align: middle;
        }
        .table th {
            color: #facc15;
        }
        .status-success {
            color: #22c55e;
        }
        .status-failed {
            color: #ef4444;
        }
        .status-pending {
            color: #facc15;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4 text-center">Your Transaction History</h2>
        <div class="table-responsive">
            <table class="table table-bordered text-white">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Sender</th>
                        <th>Receiver</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Exchange Rate</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($txn = $transactions->fetchArray(SQLITE3_ASSOC)) : ?>
                        <tr>
                            <td><?= $txn['Transaction_ID'] ?></td>
                            <td><?= htmlspecialchars($txn['SenderName']) ?></td>
                            <td><?= htmlspecialchars($txn['ReceiverName']) ?></td>
                            <td><?= $txn['FromSymbol'] . number_format($txn['Amount'], 2) ?> → <?= $txn['ToSymbol'] ?></td>
                            <td><?= $txn['FromSymbol'] . number_format($txn['Fee'], 2) ?></td>
                            <td><?= number_format($txn['Exchange_rate'], 4) ?></td>
                            <td><?= $txn['Time'] ?></td>
                            <td class="<?= $txn['Status'] === 'Success' ? 'status-success' : ($txn['Status'] === 'Pending' ? 'status-pending' : 'status-failed') ?>">
                                <?= $txn['Status'] ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
