<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new SQLite3('SecureFX.db');

// Get user details
$userStmt = $db->prepare(
    "SELECT u.*, c.Country FROM User u
     JOIN Account a ON u.User_ID = a.User_ID
     JOIN Currency c ON a.Currency_ID = c.Currency_ID
     WHERE u.User_ID = :user_id"
);
$userStmt->bindValue(':user_id', $_SESSION['user_id']);
$userResult = $userStmt->execute();
$user = $userResult->fetchArray(SQLITE3_ASSOC);
if (!$user) {
    die("User not found");
}

// Get account details
$accountStmt = $db->prepare(
    "SELECT a.*, c.Currency_code, c.Symbol FROM Account a
     JOIN Currency c ON a.Currency_ID = c.Currency_ID
     WHERE a.User_ID = :user_id"
);
$accountStmt->bindValue(':user_id', $_SESSION['user_id']);
$accountResult = $accountStmt->execute();
$account = $accountResult->fetchArray(SQLITE3_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SecureFX</title>
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
  <div class="fixed top-4 right-4">
    <button id="themeToggle" class="p-2 rounded-lg bg-white dark:bg-gray-800 shadow focus:outline-none">
      <i data-feather="sun" class="w-5 h-5 text-yellow-400 hidden dark:block"></i>
      <i data-feather="moon" class="w-5 h-5 text-gray-800 dark:hidden"></i>
    </button>
  </div>

  <div class="max-w-4xl mx-auto py-12 px-4">
    <!-- Header -->
    <div class="flex items-center space-x-6 mb-10">
        <div>
            <div class="w-24 h-24 rounded-full bg-brand dark:bg-brand-dark text-white flex items-center justify-center text-4xl font-bold">
                <?= htmlspecialchars(substr($user['First_name'], 0, 1)) ?>
            </div>
        </div>
      <!-- <img
        src="<?= htmlspecialchars($user['Avatar'] ?? '/assets/default-avatar.png') ?>"
        alt="Avatar"
        class="w-24 h-24 rounded-full ring-4 ring-brand dark:ring-brand-dark"
      /> -->
      <div>
        <h1 class="text-3xl font-extrabold"><?= htmlspecialchars($user['First_name'] . ' ' . $user['Last_name']) ?></h1>
        <span class="inline-block mt-2 px-4 py-1 bg-brand text-white rounded-full text-sm">
          <?= htmlspecialchars($user['User_type']) ?>
        </span>
      </div>
    </div>

    <!-- Main Grid -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Personal Info -->
      <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow p-6 space-y-4">
        <h2 class="text-2xl font-semibold">Personal Information</h2>
        <dl class="grid sm:grid-cols-2 gap-4">
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($user['Email']) ?></dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($user['Phone']) ?></dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date of Birth</dt>
            <dd class="mt-1 text-lg font-medium"><?= date('F j, Y', strtotime($user['DOB'])) ?></dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($user['Address']) ?></dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Country</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($user['Country']) ?></dd>
          </div>
        </dl>
      </div>

      <!-- Account Info -->
      <!-- <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-6 space-y-4">
        <h2 class="text-2xl font-semibold">Account Information</h2>
        <dl class="space-y-4">
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($account['Account_number']) ?></dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Currency</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($account['Currency_code'] . ' (' . $account['Symbol'] . ')') ?></dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Balance</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($account['Symbol'] . number_format($account['Balance'], 2)) ?></dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Limit</dt>
            <dd class="mt-1 text-lg font-medium"><?= htmlspecialchars($account['Symbol'] . number_format($account['Transaction_limit'], 2)) ?></dd>
          </div>
        </dl>
      </div> -->
    </div>
  </div>

  <script>
    // Initialize Feather icons
    feather.replace();
    // Theme toggle logic
    const toggle = document.getElementById('themeToggle');
    toggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      localStorage.setItem('theme', theme);
    });
    // Apply saved theme
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  </script>
</body>
</html>