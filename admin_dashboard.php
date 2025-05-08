<?php
session_start();

// Restrict access to admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Connect to database
$database = "Currenzy.db";
try {
    $conn = new PDO("sqlite:" . $database);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch users
$users = $conn->query("SELECT User_ID, First_name, Last_name, Email, User_type FROM User")->fetchAll(PDO::FETCH_ASSOC);

// Fetch suspicious transactions
$suspicious = $conn->query("
    SELECT t.Transaction_ID, u.Email AS Sender, t.Amount, t.Status, t.Suspicious_flag 
    FROM Transactions t
    JOIN Account a ON t.Sender_account_ID = a.Account_ID
    JOIN User u ON a.User_ID = u.User_ID
    WHERE t.Suspicious_flag = 1
    ORDER BY t.Time DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Currenzy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f6f8; font-family: Arial, sans-serif; }
        .container { margin-top: 40px; }
        h2 { margin-bottom: 20px; }
        .card { margin-bottom: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .table thead { background-color: #e9ecef; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center">Admin Dashboard</h2>

    <div class="card p-3">
        <h4>All Registered Users</h4>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>User Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['User_ID'] ?></td>
                    <td><?= $user['First_name'] . ' ' . $user['Last_name'] ?></td>
                    <td><?= $user['Email'] ?></td>
                    <td><?= ucfirst($user['User_type']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card p-3">
        <h4>Suspicious Transactions</h4>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Sender Email</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Flagged</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suspicious as $tx): ?>
                <tr class="table-danger">
                    <td><?= $tx['Transaction_ID'] ?></td>
                    <td><?= $tx['Sender'] ?></td>
                    <td>£<?= number_format($tx['Amount'], 2) ?></td>
                    <td><?= ucfirst($tx['Status']) ?></td>
                    <td><?= $tx['Suspicious_flag'] ? 'Yes' : 'No' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($suspicious)) echo "<tr><td colspan='5' class='text-center'>No suspicious transactions.</td></tr>"; ?>
            </tbody>
        </table>
    </div>

    <div class="text-center mt-4">
        <a href="logout.php" class="btn btn-outline-danger">Log out</a>
    </div>
</div>
</body>
</html>