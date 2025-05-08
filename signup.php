<?php

try {
    $db = new PDO('sqlite:SecureFX.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode = WAL');
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ─────── Collect & Sanitize Inputs ───────
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone           = trim($_POST['phone'] ?? '');
    $dob             = trim($_POST['dob'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $currencyId      = $_POST['currency_id'] ?? '';
    $userType        = 'Customer';

    // ─────── Validate Inputs ───────
    if (
        !$firstName || !$lastName || !$email || !$password || !$confirmPassword ||
        !$phone || !$dob || !$address || !$currencyId
    ) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match!';
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $db->beginTransaction();

            // ─────── Check Email Uniqueness ───────
            $stmt = $db->prepare('SELECT COUNT(*) FROM User WHERE Email = :email');
            $stmt->execute([':email' => $email]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('Email already registered!');
            }

            // ─────── Insert User ───────
            $stmt = $db->prepare(
                'INSERT INTO User
                 (First_name, Last_name, Email, Password, Phone, DOB, Address, User_type)
                 VALUES
                 (:first_name, :last_name, :email, :password, :phone, :dob, :address, :user_type)'
            );
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':email'      => $email,
                ':password'   => $hashedPassword,
                ':phone'      => $phone,
                ':dob'        => $dob,
                ':address'    => $address,
                ':user_type'  => $userType,
            ]);

            $userId = (int)$db->lastInsertId();

            // ─────── Create Initial Account ───────
            $accountNumber = str_pad($userId, 8, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare(
                'INSERT INTO Account
                 (User_ID, Currency_ID, Account_number, Balance, Transaction_limit)
                 VALUES
                 (:user_id, :currency_id, :acc_num, 0.00, 10000.00)'
            );
            $stmt->execute([
                ':user_id'     => $userId,
                ':currency_id' => $currencyId,
                ':acc_num'     => $accountNumber
            ]);

            $db->commit();
            $success = true;
        } catch (Exception|PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ─────── Fetch Currencies for Dropdown ───────
$currencies = $db->query(
    'SELECT Currency_ID, Currency_code || " – " || Currency_name AS label
     FROM Currency ORDER BY Currency_code'
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Signup – SecureFX</title>

<!-- Tailwind (stand‑alone CDN build) -->
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
        colors: { primary: { DEFAULT: '#9D7BED', 600: '#7951B2' } },
        dropShadow: { glow: '0 0 8px rgba(157, 123, 237, 0.45)' }
      }
    }
  };
    (function() {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
    })();
</script>

<!-- Heroicons -->
<script src="https://unpkg.com/feather-icons"></script>


</head>
<body class="min-h-screen bg-gradient-to-br dark:from-black dark:via-zinc-900 dark:to-neutral-900 from-white via-gray-100 to-gray-200 text-gray-900 dark:text-gray-100 flex items-center justify-center">

<div class="container bg-white dark:bg-black mx-auto">
  <div class="mx-auto flex w-full max-w-6xl shadow-lg rounded-3xl overflow-hidden">
    <div class="hidden lg:block w-1/2">
      <img src="./Assets/register.jpg" alt="Student practicing math on a tablet" class="h-full w-full object-cover" />
    </div>
    <div class="w-full lg:w-1/2 p-8 sm:p-12 md:p-16 lg:p-20 glass">
      <header class="mb-6 text-start">
        <h1 class="text-3xl font-extrabold tracking-tight drop-shadow-glow">Create Customer Account</h1>
        <p class="mt-1 text-gray-400 text-sm">Join SecureFX today!</p>
      </header>

      <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 rounded">
          Registration successful! <a href="login.php" class="underline">Login now</a>
        </div>
      <?php elseif ($error): ?>
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-800 rounded">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <input type="text" name="first_name" placeholder="First Name" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
          <input type="text" name="last_name" placeholder="Last Name" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
        </div>
        <input type="email" name="email" placeholder="Email Address" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
          <input type="password" name="password" placeholder="Password" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
          <input type="password" name="confirm_password" placeholder="Confirm Password" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
          <input type="tel" name="phone" placeholder="Phone Number" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
          <input type="date" name="dob" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
        <input type="text" name="address" placeholder="Full Address" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition" />
        <select name="currency_id" required class="w-full rounded-lg border border-neutral-700 bg-neutral-800/10 dark:bg-neutral-800/70 py-3 px-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition">
          <option value="">Select Account Currency</option>
          <?php foreach ($currencies as $id => $label): ?>
            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 rounded-lg bg-primary hover:bg-[#7951B2] transition focus:outline-none focus:ring-2 focus:ring-primary/50 font-semibold text-[#0A0A0D]">
          Create Account
        </button>
        <p class="text-center text-sm text-gray-400 dark:text-gray-500">
          Already have an account? <a href="./login.php" class="font-medium text-primary hover:underline">Sign in</a>
        </p>
      </form>
    </div>
  </div>
</div>

  <script>
    feather.replace();

    document.getElementById('themeToggle').addEventListener('click', () => {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
  </script>
</body>
</html>
