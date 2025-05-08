<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new SQLite3('SecureFX.db');

// Get user's account ID
$userId = $_SESSION['user_id'];
$accountQuery = $db->prepare("SELECT Account_ID FROM Account WHERE User_ID = :user_id");
$accountQuery->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$accountResult = $accountQuery->execute()->fetchArray(SQLITE3_ASSOC);
if (!$accountResult) {
    die("Account not found.");
}
$accountId = $accountResult['Account_ID'];

// Fetch transactions
$txnStmt = $db->prepare(
    "SELECT 
        t.Transaction_ID,
        t.Sender_account_ID,
        t.Receiver_account_ID,
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
     WHERE t.Sender_account_ID = :acct OR t.Receiver_account_ID = :acct
     ORDER BY t.Time DESC"
);
$txnStmt->bindValue(':acct', $accountId, SQLITE3_INTEGER);
$transactions = $txnStmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction History - SecureFX</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: '#3b82f6',
            'brand-dark': '#60a5fa'
          }
        }
      }
    };
  </script>
  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 transition-colors">
  <!-- Theme Toggle -->
  <div class="fixed top-4 right-4 z-10">
    <button id="themeToggle" class="p-2 rounded-lg bg-white dark:bg-gray-800 shadow focus:outline-none">
      <i data-feather="sun" class="w-5 h-5 text-yellow-400 hidden dark:block"></i>
      <i data-feather="moon" class="w-5 h-5 text-gray-800 dark:hidden"></i>
    </button>
  </div>

  <div class="max-w-6xl mx-auto py-12 px-4">
    <h1 class="text-3xl font-extrabold mb-8 text-center">Your Transaction History</h1>
    <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-2xl shadow">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-100 dark:bg-gray-700">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold uppercase">ID</th>
            <th class="px-4 py-3 text-left text-sm font-semibold uppercase">Sender</th>
            <th class="px-4 py-3 text-left text-sm font-semibold uppercase">Receiver</th>
            <th class="px-4 py-3 text-right text-sm font-semibold uppercase">Amount</th>
            <th class="px-4 py-3 text-right text-sm font-semibold uppercase">Fee</th>
            <th class="px-4 py-3 text-right text-sm font-semibold uppercase">Time</th>
            <th class="px-4 py-3 text-center text-sm font-semibold uppercase">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <?php while ($tx = $transactions->fetchArray(SQLITE3_ASSOC)): ?>
            <?php
              $statusClass = $tx['Status'] === 'Success'
                ? 'text-green-500'
                : ($tx['Status'] === 'Pending' ? 'text-yellow-400' : 'text-red-500');
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-4 py-3 text-sm"><?= htmlspecialchars($tx['Transaction_ID']) ?></td>
              <td class="px-4 py-3 text-sm"><?= htmlspecialchars($tx['SenderName']) ?></td>
              <td class="px-4 py-3 text-sm"><?= htmlspecialchars($tx['ReceiverName']) ?></td>
              <td class="px-4 py-3 text-sm text-right">
                <?= $tx['FromSymbol'] . number_format($tx['Amount'],2) ?>
                → <?= $tx['ToSymbol'] ?>
              </td>
              <td class="px-4 py-3 text-sm text-right"><?=  number_format($tx['Fee'],2) ?></td>
              <td class="px-4 py-3 text-sm text-right"><?= date('M j, Y H:i', strtotime($tx['Time'])) ?></td>
              <td class="px-4 py-3 text-center text-sm font-semibold <?= $statusClass ?>">
                <?= htmlspecialchars($tx['Status']) ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    feather.replace();
    const toggle = document.getElementById('themeToggle');
    toggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
  </script>
</body>
</html>
