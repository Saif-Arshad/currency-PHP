<?php
// start output buffering to prevent header issues
ob_start();
session_start();

// DB connection (use absolute path)
$database = __DIR__ . '/SecureFX.db';
try {
    $conn = new PDO("sqlite:$database");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']   ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT User_ID, Password, User_type FROM User WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['Password'])) {
        $_SESSION['user_id']   = $user['User_ID'];
        $_SESSION['user_type'] = $user['User_type'];

        // Redirect based on user type
        if (strtolower($user['User_type']) === 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: customer_dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid email or password!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <script>
    (function() {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <title>Login – SecureFX</title>

  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] },
          colors: { primary: { DEFAULT:'#2563eb',600:'#1d4ed8' } },
          dropShadow: { glow: '0 0 8px rgba(59,130,246,0.45)' }
        }
      }
    };
     (function() {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>

  <script src="https://unpkg.com/feather-icons"></script>

  <style>
    .glass {
      @apply bg-white dark:bg-[rgba(17,24,39,0.6)];
      border: 1px solid rgba(255,255,255,0.08);
      backdrop-filter: blur(14px);
    }
  </style>
</head>
<body class="min-h-screen bg-white dark:bg-gradient-to-br dark:from-black dark:via-zinc-900 dark:to-neutral-900 text-gray-900 dark:text-gray-100 flex items-center justify-center">


  <div class="mx-auto flex w-full max-w-6xl shadow-lg rounded-3xl overflow-hidden">
    <div class="w-full lg:w-1/2 p-8 sm:p-12 md:p-16 lg:p-20 glass text-gray-900 dark:text-gray-100">
      <header class="mb-10 text-start">
        <h1 class="text-3xl font-extrabold tracking-tight drop-shadow-glow">Login to your account</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400 text-sm">Welcome Back</p>
      </header>

      <form id="loginForm" method="post" class="space-y-6">
        <div>
          <label for="email" class="block text-sm font-medium mb-1">Email</label>
          <div class="relative">
            <input type="email" name="email" id="email" required placeholder="jane@example.com"
                   class="peer w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/70 py-3 pl-10 pr-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition">
            <i data-feather="mail" class="absolute top-1/2 left-3 -translate-y-1/2 text-gray-500 peer-focus:text-primary"></i>
          </div>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium mb-1">Password</label>
          <div class="relative">
            <input type="password" name="password" id="password" required placeholder="••••••••"
                   class="peer w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/70 py-3 pl-10 pr-4 text-sm placeholder-gray-500 focus:border-primary focus:ring-primary/40 focus:outline-none transition">
            <i data-feather="lock" class="absolute top-1/2 left-3 -translate-y-1/2 text-gray-500 peer-focus:text-primary"></i>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="text-red-600 text-sm"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>

        <button id="submitBtn" type="submit" class="w-full flex items-center justify-center gap-2 py-3 rounded-lg bg-[#9D7BED] hover:bg-primary-600 transition focus:outline-none focus:ring-2 focus:ring-primary/50 font-semibold">
          <span id="btnText">Sign In</span>
          <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
        </button>
      </form>
        <p class="text-center text-sm text-gray-400 dark:text-gray-500">
          Don't have an account? <a href="./signup.php" class="font-medium text-primary hover:underline">Sign in</a>
        </p>
    </div>

    <div class="hidden lg:block w-1/2">
      <img src="./Assets/login.jpg" alt="Student practicing math on a tablet" class="h-full w-full object-cover">
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
