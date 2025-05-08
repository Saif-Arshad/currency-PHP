<?php
session_start();

// Restrict access to admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Connect to database
$database = __DIR__ . '/SecureFX.db';
try {
    $conn = new PDO('sqlite:' . $database);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Gather stats
$statsStmt = $conn->query(
    'SELECT
        (SELECT COUNT(*) FROM User) AS total_users,
        (SELECT COUNT(*) FROM Transactions) AS total_transactions,
        (SELECT COUNT(*) FROM Transactions WHERE Suspicious_flag = 1) AS suspicious_total'
);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch suspicious transactions (latest 50)
$suspiciousStmt = $conn->query(
    'SELECT t.Transaction_ID,
            u.Email AS Sender,
            t.Amount,
            t.Status,
            DATETIME(t.Time) AS Time
     FROM Transactions t
     JOIN Account a ON t.Sender_account_ID = a.Account_ID
     JOIN User u ON a.User_ID = u.User_ID
     WHERE t.Suspicious_flag = 1
     ORDER BY t.Time DESC
     LIMIT 50'
);
$suspicious = $suspiciousStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all transactions with LEFT JOIN to ensure no data is missed
$allTxStmt = $conn->query(
    'SELECT t.Transaction_ID, 
            u1.Email AS Sender, 
            u2.Email AS Receiver, 
            t.Amount, 
            t.Status, 
            DATETIME(t.Time) AS Time 
     FROM Transactions t 
     JOIN Account a1 ON t.Sender_account_ID = a1.Account_ID 
     JOIN User u1 ON a1.User_ID = u1.User_ID 
     LEFT JOIN Account a2 ON t.Receiver_account_ID = a2.Account_ID 
     LEFT JOIN User u2 ON a2.User_ID = u2.User_ID 
     ORDER BY t.Time DESC'
);
$transactions = $allTxStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Dashboard · SecureFX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body class="bg-gray-100 min-h-screen">
<!-- ===== Top​-bar / Navigation ===== -->
<nav class="bg-gray-800 text-white">
    <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
        <a href="admin_dashboard.php" class="font-semibold text-lg">SecureFX&nbsp;Admin</a>
        <div class="flex space-x-6 text-sm font-medium">
            <a href="admin_dashboard.php" class="hover:text-teal-300">Dashboard</a>
            <a href="./transection.php" class="hover:text-teal-300">All Transactions</a>
            <a href="manage_users.php" class="hover:text-teal-300">Manage&nbsp;Users</a>
            <a href="logout.php" class="hover:text-red-400">Logout</a>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- ===== Stat cards ===== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <div class="bg-white shadow rounded-lg p-6 text-center">
            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
            <p class="mt-2 text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_users']); ?></p>
        </div>

        <div class="bg-white shadow rounded-lg p-6 text-center">
            <h3 class="text-sm font-medium text-gray-500">Suspicious Transactions</h3>
            <p class="mt-2 text-3xl font-bold text-red-600"><?php echo number_format($stats['suspicious_total']); ?></p>
        </div>
    </div>

    <!-- ===== Suspicious transactions table ===== -->
    <div class="mt-10 bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-700">Suspicious Transactions</h2>
        </div>
        <div class="p-6 overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left">ID</th>
                    <th class="px-6 py-3 text-left">Sender Email</th>
                    <th class="px-6 py-3 text-left">Amount</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Date</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($suspicious)): ?>
                    <?php foreach ($suspicious as $tx): ?>
                        <tr class="bg-red-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($tx['Transaction_ID']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($tx['Sender']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">&pound;<?php echo number_format($tx['Amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo ucfirst(htmlspecialchars($tx['Status'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($tx['Time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No suspicious transactions.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
