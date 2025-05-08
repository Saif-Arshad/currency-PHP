<?php
// ─── session & DB ─────────────────────────────────────────────────────────────
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

// ─── fetch user, accounts, transactions ───────────────────────────────────────
$user_id = $_SESSION['user_id'];

$uStmt = $conn->prepare('SELECT First_name FROM User WHERE User_ID = ?');
$uStmt->execute([$user_id]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);

$accStmt = $conn->prepare(
    'SELECT a.Account_ID, a.Account_number, a.Balance,
            c.Currency_code, c.Symbol, c.Country
       FROM Account a
       JOIN Currency c ON a.Currency_ID = c.Currency_ID
      WHERE a.User_ID = ?'
);
$accStmt->execute([$user_id]);
$accounts = $accStmt->fetchAll(PDO::FETCH_ASSOC);

$txStmt = $conn->prepare(
    'SELECT Amount, Time, Status,
            (SELECT Currency_code
               FROM Currency
              WHERE Currency_ID = t.Currency_ID_from) AS Code
       FROM Transactions t
      WHERE t.Sender_account_ID IN
            (SELECT Account_ID FROM Account WHERE User_ID = ?)
   ORDER BY t.Time DESC LIMIT 5'
);
$txStmt->execute([$user_id]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);


// ─── helpers ──────────────────────────────────────────────────────────────────
function currencySymbol(string $code, ?string $dbSymbol = null): string
{
    static $map = [
        'GBP' => '£', 'USD' => '$', 'EUR' => '€',
        'PKR' => '₨', 'AUD' => '$', 'CAD' => '$'
    ];
    if ($dbSymbol && mb_strlen($dbSymbol) === 1) {
        return $dbSymbol;            // already a glyph
    }
    return $map[$code] ?? $code;     // glyph or fallback to code
}

// ─── dynamic FX rate for first account ────────────────────────────────────────
$local = $accounts[0]['Currency_code'] ?? 'GBP';
$rate = null;
$url  = "https://api.exchangerate.host/latest?base=GBP&symbols=" . urlencode($local);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

if ($res) {
    $j = json_decode($res, true);
    if (isset($j['rates'][$local])) {
        $rate = $j['rates'][$local];
    }
}
?>
<!DOCTYPE html>
<html lang="en" x-data="{sidebar:false, dark:localStorage.theme==='dark'}" :class="dark?'dark':''">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard – SecureFX</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            background:{DEFAULT:'#fff',dark:'#1f2937'},
            brand:{DEFAULT:'#3b82f6',dark:'#60a5fa'}
          },
          backgroundImage:{
            'card-gradient':'linear-gradient(135deg,theme("colors.brand.500") 0%,theme("colors.brand.300") 100%)'
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/feather-icons"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background dark:bg-background-dark text-gray-800 dark:text-gray-100 selection:bg-brand/30">
  <!-- top nav omitted for brevity… -->
  <header class="fixed inset-x-0 top-0 z-30 backdrop-blur bg-white/80 dark:bg-gray-900/80 shadow-sm">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-4 py-3">
        <a href="/" class="flex items-center space-x-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.343-3 3v5h6v-5c0-1.657-1.343-3-3-3z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a9 9 0 10-14.8 0" />
          </svg>
          <span class="text-2xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-purple-500 to-indigo-600">SecureFX</span>
        </a>

      <nav class="hidden lg:flex items-center gap-8 font-medium">
        <a href="sendmoney.php" class="hover:text-brand transition">Send Money</a>
        <a href="./addFunds.php" class="hover:text-brand">Add Funds</a>

        <a href="viewhistory.php" class="hover:text-brand transition">History</a>
      </nav>
      <div class="flex items-center gap-5">
           <button id="themeToggle" aria-label="Toggle theme" class="p-2 rounded-lg focus:outline-none bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors">
            <i data-feather="sun" class="w-5 h-5 text-yellow-500 dark:hidden"></i>
            <i data-feather="moon" class="w-5 h-5 text-gray-200 hidden dark:block"></i>
          </button>

      <div class="relative" x-data="{open:false}">
        <button @click="open=!open" class="flex items-center gap-2 rounded-full focus:outline-none focus:ring-2 focus:ring-brand p-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" @click.outside="open=false" x-transition class="absolute right-0 mt-2 w-40 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 p-1">
          <a href="profile.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">Profile</a>
          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
          <a href="logout.php" class="block px-4 py-2 rounded-md text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">Logout</a>
        </div>
      </div>
      </div>
    </div>
  </header>
  <main class="pt-20 pb-12">
    <div class="grid sm:grid-cols-3 gap-6 px-10 mb-20">

      <!-- 1) DYNAMIC exchange‐rate card -->
      <div class="rounded-3xl shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 bg-white/70 dark:bg-gray-800/70 backdrop-blur p-6 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">Exchange rate</p>
     <h3 class="text-3xl font-bold my-4">
    1
    <?php foreach ($accounts as $a): ?>
        <?= currencySymbol(
              strtoupper($a['Currency_code']),
              $a['Symbol']
           ); ?>
    <?php endforeach; ?>

    = ??
</h3>

        <a href="sendmoney.php" class="inline-block bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 text-white font-medium rounded-full px-6 py-2">
          Send Money
        </a>
      </div>

      <!-- 2) ACCOUNT details + user name -->
      <?php foreach ($accounts as $a): ?>
        <article class="relative overflow-hidden rounded-3xl shadow-lg ring-1 ring-brand/20 bg-card-gradient text-black dark:text-white">
          <div class="p-6 space-y-4 text-start">
            <h3 class="uppercase text-xs tracking-widest opacity-90">
              <?= htmlspecialchars(strtoupper($a['Currency_code'])); ?> Account
            </h3>
            <p class="text-lg font-semibold"><?= htmlspecialchars($user['First_name']); ?></p>
            <div class="text-sm opacity-80">Acc. No.</div>
            <div class="font-mono text-lg tracking-wide break-all">
              <?= htmlspecialchars($a['Account_number']); ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <!-- 3) BALANCES with proper symbol -->
      <?php foreach ($accounts as $a): ?>
        <article class="relative overflow-hidden rounded-3xl shadow-lg ring-1 ring-brand/20 bg-card-gradient text-black dark:text-white">
          <div class="p-6 space-y-4 text-center">
            <div class="opacity-80 text-sm"><?= htmlspecialchars($a['Country']); ?> Balance</div>
            <div class="text-3xl font-extrabold">
              <?= currencySymbol(strtoupper($a['Currency_code']), $a['Symbol']) ?>
              <?= number_format($a['Balance'], 2) ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

    </div>

    <section class="mx-auto px-10">
    <div class="rounded-3xl shadow-lg ring-1 ring-gray-200 dark:ring-gray-700
                bg-white/70 dark:bg-gray-800/70 backdrop-blur p-6">
      <h2 class="text-lg font-semibold mb-4">Recent Transactions</h2>
      <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
        <?php if ($transactions): ?>
          <?php foreach ($transactions as $t): ?>
            <li class="py-3 flex justify-between">
              <span class="font-medium">
                <?= htmlspecialchars(strtoupper($t['Code'])); ?>
                <?= number_format($t['Amount'], 2); ?>
              </span>
              <span class="text-gray-500 dark:text-gray-400">
                <?= date('d M Y', strtotime($t['Time'])); ?> • <?= htmlspecialchars($t['Status']); ?>
              </span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="py-4 text-center text-gray-500 dark:text-gray-400">
            No transactions.
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </section>
  </main>

  <script>
    feather.replace();
    document.getElementById('themeToggle').addEventListener('click', () => {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', isDark?'dark':'light');
    });
  </script>
</body>
</html>
