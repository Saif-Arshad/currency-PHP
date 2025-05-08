<?php
session_start();

// Restrict access to admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Connect to database
$database = "SecureFX.db";
try {
    $conn = new PDO("sqlite:" . $database);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch all transactions
$transactions = $conn->query("SELECT t.Transaction_ID, u1.Email AS Sender, u2.Email AS Receiver, t.Amount, t.Status, t.Time FROM Transactions t JOIN Account a1 ON t.Sender_account_ID = a1.Account_ID JOIN User u1 ON a1.User_ID = u1.User_ID JOIN Account a2 ON t.Receiver_account_ID = a2.Account_ID JOIN User u2 ON a2.User_ID = u2.User_ID ORDER BY t.Time DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">
      <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
            <a href="admin_dashboard.php" class="font-semibold text-lg">SecureFX Admin</a>
            <div class="flex space-x-6 text-sm font-medium">
                <a href="admin_dashboard.php" class="hover:text-teal-300">Dashboard</a>
            <a href="./transection.php" class="hover:text-teal-300">All Transactions </a>
                <a href="manage_users.php" class="hover:text-teal-300">Manage Users</a>
                <a href="logout.php" class="hover:text-red-400">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">All Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-left text-sm text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2">Transaction ID</th>
                            <th class="px-4 py-2">Sender</th>
                            <th class="px-4 py-2">Receiver</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['Transaction_ID']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['Sender']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['Receiver']) ?></td>
                            <td class="px-4 py-2">&pound;<?= number_format($tx['Amount'], 2) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['Status']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['Time']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)) echo "<tr><td colspan='6' class='px-4 py-2 text-center text-gray-500'>No transactions found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
