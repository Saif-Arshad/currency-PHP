<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <!-- Tailwind + Feather Icons -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: { background: { DEFAULT: '#ffffff', dark: '#1f2937' } }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/feather-icons"></script>

  <script>
    (function() {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>

  <title>SecureFX – Money Transfers</title>
</head>
<body class="bg-white dark:bg-black text-gray-900 dark:text-gray-100">

  <header class=" dark:bg-neutral-800 sticky top-0 z-10 bg-background dark:bg-background">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">

        <!-- Logo -->
        <a href="/" class="flex items-center space-x-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.343-3 3v5h6v-5c0-1.657-1.343-3-3-3z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a9 9 0 10-14.8 0" />
          </svg>
          <span class="text-2xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-purple-500 to-indigo-600">SecureFX</span>
        </a>

        <div class="flex items-center space-x-4">

          <button id="themeToggle" aria-label="Toggle theme" class="p-2 rounded-lg focus:outline-none bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors">
            <i data-feather="sun" class="w-5 h-5 text-yellow-500 dark:hidden"></i>
            <i data-feather="moon" class="w-5 h-5 text-gray-200 hidden dark:block"></i>
          </button>

          <a href="./login.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors space-x-2">
            <i data-feather="log-in" class="w-5 h-5"></i>
            <span>Login</span>
          </a>

          <a href="./signup.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg hover:from-purple-600 hover:to-indigo-700 transition-colors space-x-2">
            <i data-feather="user-plus" class="w-5 h-5"></i>
            <span>Sign Up</span>
          </a>

        </div>
      </div>
    </div>
  </header>

   <!-- ===== Main ===== -->
  <main class="flex-1">

    <!-- Hero -->
    <section class="w-full py-12 md:py-24  bg-gradient-to-b from-background to-purple-50 dark:from-gray-800 dark:to-gray-700">
      <div class="container flex items-center justify-center  px-4 md:px-6">
          <div class="space-y-4 flex items-center justify-center flex-col">
            <div class="inline-block rounded-lg bg-purple-500 px-3 py-1 text-sm text-white">
              New Features Available
            </div>
            <h1 class="text-3xl font-bold text-center tracking-tighter sm:text-5xl xl:text-6xl">
              Global Currency <br> Transfers Made Simple 
            </h1>
            <p class="text-gray-600 text-center dark:text-gray-300 max-w-[600px] md:text-xl">
              SecureFX allows you to transfer money globally with the best exchange rates and low fees. Get started today!
            </p>
              <a href="./signup.php"
                 class="inline-block px-6 py-3 text-lg font-medium bg-purple-600 text-white rounded-full text-center hover:bg-purple-700 w-60  transition-colors">
                Get Started
              </a>
           
          </div>
        
        </div>
    </section>

    <section class="w-full py-12 md:py-24 lg:py-32 bg-white dark:bg-black">
      <div class="container mx-auto px-4 md:px-6">
        <div class="text-center space-y-4">
          <div class="inline-block rounded-lg bg-purple-500 px-3 py-1 text-sm text-white">
            Features
          </div>
          <h2 class="text-3xl font-bold tracking-tighter sm:text-5xl">
            Everything You Need to Transfer Money
          </h2>
          <p class="max-w-[900px] mx-auto text-gray-600 dark:text-gray-300 md:text-xl">
            Our platform provides a seamless experience for all your currency transfer needs.
          </p>
        </div>
        <div class="mt-12 grid lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
          <!-- Card 1 -->
          <div class="p-6 hover:shadow-lg transition-all duration-200 rounded-xl bg-background dark:bg-gray-800">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-900">
              <i data-feather="globe" class="h-6 w-6 text-purple-600"></i>
            </div>
            <h3 class="text-xl font-bold">Global Transfers</h3>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
              Send money to over 170 countries with competitive exchange rates.
            </p>
          </div>
          <!-- Card 2 -->
          <div class="p-6 hover:shadow-lg transition-all duration-200 rounded-xl bg-background dark:bg-gray-800">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-900">
              <i data-feather="refresh-cw" class="h-6 w-6 text-purple-600"></i>
            </div>
            <h3 class="text-xl font-bold">Currency Exchange</h3>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
              Exchange between multiple currencies at the best possible rates.
            </p>
          </div>
          <!-- Card 3 -->
          <div class="p-6 hover:shadow-lg transition-all duration-200 rounded-xl bg-background dark:bg-gray-800">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-900">
              <i data-feather="lock" class="h-6 w-6 text-purple-600"></i>
            </div>
            <h3 class="text-xl font-bold">Secure Platform</h3>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
              Advanced security features to keep your money and data safe.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="w-full py-12 md:py-24 lg:py-32 bg-purple-600">
      <div class="container mx-auto px-4 md:px-6 text-center text-white">
        <h2 class="text-3xl font-bold tracking-tighter sm:text-4xl md:text-5xl">
          Ready to Get Started?
        </h2>
        <p class="mt-4 max-w-[600px] mx-auto text-lg">
          Join thousands of customers who trust SecureFX with their global transfers.
        </p>
        <div class="mt-6 flex flex-col gap-2 sm:flex-row justify-center">
          <a href="./signup.php"
             class="inline-block px-6 py-3 text-lg font-medium bg-white text-purple-600 rounded-full w-60 text-center hover:bg-gray-100 transition-colors">
            Create Account
          </a>
       
        </div>
      </div>
    </section>

  </main>

  <footer class="border-t border-gray-200 dark:border-gray-600 py-6 bg-white dark:bg-black">
      <p class="text-sm text-center text-gray-600 dark:text-gray-400">
        © <span id="year"></span> SecureFX. All rights reserved.
      </p>
   
  </footer>
  <script>
    feather.replace();
    document.getElementById('themeToggle').addEventListener('click', () => {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
  </script>

</body>
</html>