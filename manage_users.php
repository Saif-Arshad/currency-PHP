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

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];

    // Prevent deleting other admins or self
    $typeStmt = $conn->prepare('SELECT User_type FROM User WHERE User_ID = :id');
    $typeStmt->execute([':id' => $deleteId]);
    $row = $typeStmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['User_type'] !== 'Admin' && $deleteId !== (int)($_SESSION['user_id'] ?? 0)) {
        $delStmt = $conn->prepare('DELETE FROM User WHERE User_ID = :id');
        $delStmt->execute([':id' => $deleteId]);
    }
}

// Fetch all users
$users = $conn->query('SELECT User_ID, First_name, Last_name, Email, User_type FROM User ORDER BY User_ID ASC')
             ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users · SecureFX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-700">All Users</h2>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3 text-left">ID</th>
                            <th class="px-6 py-3 text-left">Name</th>
                            <th class="px-6 py-3 text-left">Email</th>
                            <th class="px-6 py-3 text-left">Type</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['User_ID']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['First_name'] . ' ' . $user['Last_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['Email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= ucfirst(htmlspecialchars($user['User_type'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['User_type'] !== 'Admin'): ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['Email']) ?>?');">
                                            <input type="hidden" name="delete_id" value="<?= $user['User_ID'] ?>">
                                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">‑</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
