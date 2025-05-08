<?php
session_start();

// ──────────────────────────────────────────────────────────────
//  Admin‑only page: list all transactions, allow admin to:
//    • mark / un‑mark a transaction as suspicious
//    • open a modal dialog to inspect full details
//  The script auto‑adds the Suspicious_flag column if the
//  live DB lacks it, so it works on old databases too.
// ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$database = 'SecureFX.db';
try {
    $conn = new PDO('sqlite:' . $database);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// ──────────────────────────────────────────────────────────────
//  Ensure `Suspicious_flag` exists. Adds it if missing.
// ──────────────────────────────────────────────────────────────
try {
    $cols = $conn->query('PRAGMA table_info(Transactions)')->fetchAll(PDO::FETCH_ASSOC);
    $hasFlag = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['name'], 'Suspicious_flag') === 0) { $hasFlag = true; break; }
    }
    if (!$hasFlag) {
        $conn->exec('ALTER TABLE Transactions ADD COLUMN Suspicious_flag INTEGER DEFAULT 0');
    }
} catch (Exception $e) {
    die('Schema check failed: ' . $e->getMessage());
}

// ──────────────────────────────────────────────────────────────
//  Toggle suspicious flag (POST) and adjust receiver balance
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_suspicious'])) {
    $txId = (int) $_POST['toggle_suspicious'];

    $conn->beginTransaction();
    try {
        // Fetch current flag, amount, and receiver account
        $cur = $conn->prepare('SELECT Suspicious_flag, Amount, Receiver_account_ID FROM Transactions WHERE Transaction_ID = :id LIMIT 1');
        $cur->execute([':id' => $txId]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newFlag = $row['Suspicious_flag'] ? 0 : 1;
            $amount = $row['Amount'];
            $receiverAcc = $row['Receiver_account_ID'];

            // Update the Suspicious_flag
            $updTxn = $conn->prepare('UPDATE Transactions SET Suspicious_flag = :flag WHERE Transaction_ID = :id');
            $updTxn->execute([':flag' => $newFlag, ':id' => $txId]);

            // Adjust receiver balance
            if ($newFlag === 1) {
                // Marking suspicious: subtract amount from receiver
                $updAcc = $conn->prepare('UPDATE Account SET Balance = Balance - :amt WHERE Account_ID = :acc');
                $updAcc->execute([':amt' => $amount, ':acc' => $receiverAcc]);
            } else {
                // Clearing flag: refund amount to receiver
                $updAcc = $conn->prepare('UPDATE Account SET Balance = Balance + :amt WHERE Account_ID = :acc');
                $updAcc->execute([':amt' => $amount, ':acc' => $receiverAcc]);
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        die('Update failed: ' . $e->getMessage());
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// ──────────────────────────────────────────────────────────────
//  Fetch transactions
// ──────────────────────────────────────────────────────────────
$sql = "SELECT t.Transaction_ID,
               u1.Email  AS Sender,
               u2.Email  AS Receiver,
               t.Amount,
               t.Status,
               COALESCE(t.Suspicious_flag,0) AS Suspicious_flag,
               t.Time
        FROM   Transactions t
               JOIN Account a1 ON t.Sender_account_ID   = a1.Account_ID
               JOIN User    u1 ON a1.User_ID            = u1.User_ID
               JOIN Account a2 ON t.Receiver_account_ID = a2.Account_ID
               JOIN User    u2 ON a2.User_ID            = u2.User_ID
        ORDER BY t.Time DESC";
$transactions = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
            <a href="admin_dashboard.php" class="font-semibold text-lg">SecureFX Admin</a>
            <div class="flex space-x-6 text-sm font-medium">
                <a href="admin_dashboard.php" class="hover:text-teal-300">Dashboard</a>
                <a href="./transection.php" class="hover:text-teal-300">All Transactions</a>
                <a href="manage_users.php" class="hover:text-teal-300">Manage Users</a>
                <a href="logout.php" class="hover:text-red-400">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">All Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-left text-sm text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2">Tx ID</th>
                            <th class="px-4 py-2">Sender</th>
                            <th class="px-4 py-2">Receiver</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Suspicious</th>
                            <th class="px-4 py-2">Time</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                                $details = [
                                    'Transaction ID' => $tx['Transaction_ID'],
                                    'Sender'         => $tx['Sender'],
                                    'Receiver'       => $tx['Receiver'],
                                    'Amount'         => number_format($tx['Amount'],2),
                                    'Status'         => ucfirst($tx['Status']),
                                    'Suspicious'     => $tx['Suspicious_flag'] ? 'Yes' : 'No',
                                    'Time'           => $tx['Time']
                                ];
                                $detailJson = htmlspecialchars(json_encode($details), ENT_QUOTES | ENT_SUBSTITUTE);
                            ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-4 py-2"><?= $tx['Transaction_ID'] ?></td>
                                <td class="px-4 py-2 truncate max-w-[160px]" title="<?= $tx['Sender'] ?>"><?= $tx['Sender'] ?></td>
                                <td class="px-4 py-2 truncate max-w-[160px]" title="<?= $tx['Receiver'] ?>"><?= $tx['Receiver'] ?></td>
                                <td class="px-4 py-2"><?= number_format($tx['Amount'],2) ?></td>
                                <td class="px-4 py-2 <?= $tx['Suspicious_flag']?'text-red-600 font-semibold':'' ?>"><?= ucfirst($tx['Status']) ?></td>
                                <td class="px-4 py-2 text-center"><?= $tx['Suspicious_flag'] ? '✓' : '✕' ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?= date('Y-m-d H:i', strtotime($tx['Time'])) ?></td>
                                <td class="px-4 py-2 space-x-2 whitespace-nowrap">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="toggle_suspicious" value="<?= $tx['Transaction_ID'] ?>">
                                        <button type="submit" class="text-<?= $tx['Suspicious_flag'] ? 'yellow' : 'red' ?>-600 hover:underline">
                                            <?= $tx['Suspicious_flag'] ? 'Clear Flag' : 'Mark Suspicious' ?>
                                        </button>
                                    </form>
                                    <button type="button" class="text-blue-600 hover:underline" data-details='<?= $detailJson ?>' onclick="showDetails(this)">Details</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="8" class="px-4 py-2 text-center text-gray-500">No transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="detailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Transaction Details</h3>
            <ul id="detailList" class="space-y-1 text-sm text-gray-700"></ul>
            <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
        </div>
    </div>

    <script>
        function showDetails(btn) {
            const data = JSON.parse(btn.getAttribute('data-details'));
            const list = document.getElementById('detailList');
            list.innerHTML = '';
            for (const [key, val] of Object.entries(data)) {
                const li = document.createElement('li');
                li.innerHTML = `<span class="font-medium">${key}:</span> ${val}`;
                list.appendChild(li);
            }
            document.getElementById('detailModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
