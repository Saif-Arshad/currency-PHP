<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $conn = new PDO('sqlite:SecureFX.db');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB error: ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Fetch user's accounts
$accStmt = $conn->prepare(
    'SELECT a.Account_ID, a.Account_number, a.Balance,
            c.Currency_code, c.Symbol, c.Country
       FROM Account a
       JOIN Currency c ON a.Currency_ID = c.Currency_ID
      WHERE a.User_ID = ?'
);
$accStmt->execute([$user_id]);
$accounts = $accStmt->fetchAll(PDO::FETCH_ASSOC);

$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // New fields
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name  = isset($_POST['last_name'])  ? trim($_POST['last_name'])  : '';
    $sort_code  = isset($_POST['sort_code'])  ? trim($_POST['sort_code'])  : '';

    // Existing fields
    $account_id  = isset($_POST['account_id'])  ? (int)$_POST['account_id'] : 0;
    $amount      = isset($_POST['amount'])      ? floatval($_POST['amount']) : 0;
    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';

    // Simple non-empty checks only
    if ($first_name === '' || $last_name === '' || $sort_code === '' ||
        $account_id <= 0 || $amount <= 0 || $card_number === ''
    ) {
        $error = 'Please fill in all fields.';
    } else {
        // Verify account belongs to user
        $stmt = $conn->prepare(
            'SELECT a.Balance, c.Currency_code
               FROM Account a
               JOIN Currency c ON a.Currency_ID = c.Currency_ID
              WHERE a.Account_ID = ? AND a.User_ID = ?'
        );
        $stmt->execute([$account_id, $user_id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            // Update balance
            $new_balance = $account['Balance'] + $amount;
            $updateStmt = $conn->prepare('UPDATE Account SET Balance = ? WHERE Account_ID = ?');
            $updateStmt->execute([$new_balance, $account_id]);

            // Log transaction
            $logStmt = $conn->prepare(
                'INSERT INTO Transactions (Sender_account_ID, Currency_ID_from, Amount, Time, Status, Suspicious_flag)
                 VALUES (?, (SELECT Currency_ID FROM Account WHERE Account_ID = ?), ?, ?, "completed", 0)'
            );
            $logStmt->execute([$account_id, $account_id, $amount, date('Y-m-d H:i:s')]);

            $success = "Successfully added $amount {$account['Currency_code']} to your account!";
        } else {
            $error = 'Selected account not found or unauthorized.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Funds - SecureFX</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { background: #f1f5f9; }
        .dark body { background: #0f172a; }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-end mb-4">
        <button id="themeToggle" class="p-2 rounded-full bg-gray-200 dark:bg-gray-700">
            <i data-feather="moon" class="h-5 w-5 text-gray-800 dark:text-white hidden dark:block"></i>
            <i data-feather="sun"  class="h-5 w-5 text-gray-800 dark:text-white block dark:hidden"></i>
        </button>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 max-w-md mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800 dark:text-white">Add Funds</h2>

        <?php if ($success): ?>
            <p class="text-green-500 text-center mb-4"><?= htmlspecialchars($success) ?></p>
        <?php elseif ($error): ?>
            <p class="text-red-500 text-center mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <!-- First Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                <input name="first_name" type="text" placeholder="Enter first name" required
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
            </div>

            <!-- Last Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                <input name="last_name" type="text" placeholder="Enter last name" required
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
            </div>

            <!-- Sort Code -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Code</label>
                <input name="sort_code" type="text"  maxlength="7"  placeholder="Enter sort code" required
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
            </div>

            <!-- Select Account -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Account</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-feather="credit-card" class="h-5 w-5 text-gray-400"></i>
                    </div>
                    <select name="account_id" required
                            class="pl-10 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="" disabled selected>Choose an account</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['Account_ID'] ?>">
                                <?= htmlspecialchars($a['Currency_code'] . ' (' . number_format($a['Balance'], 2) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Card Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Number</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-feather="credit-card" class="h-5 w-5 text-gray-400"></i>
                    </div>
                    <input name="card_number" type="text"  maxlength="11"  placeholder="Enter card number" required
                           class="pl-10 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                </div>
            </div>

            <!-- Amount -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Amount</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-feather="dollar-sign" class="h-5 w-5 text-gray-400"></i>
                    </div>
                    <input name="amount" type="number" step="0.01" placeholder="Enter amount" required
                           class="pl-10 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                </div>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600">
                    <i data-feather="plus-circle" class="h-5 w-5 mr-2"></i> Add Funds
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    feather.replace();
    const toggle = document.getElementById('themeToggle');
    toggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
</script>
</body>
</html>
