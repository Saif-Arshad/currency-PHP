<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new SQLite3('Currenzy.db');

// Get user details
$userStmt = $db->prepare("
    SELECT u.*, c.Country 
    FROM User u
    JOIN Account a ON u.User_ID = a.User_ID
    JOIN Currency c ON a.Currency_ID = c.Currency_ID
    WHERE u.User_ID = :user_id
");
$userStmt->bindValue(':user_id', $_SESSION['user_id']);
$userResult = $userStmt->execute();
$user = $userResult->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    die("User not found");
}

// Get account details
$accountStmt = $db->prepare("
    SELECT a.*, c.Currency_code, c.Symbol
    FROM Account a
    JOIN Currency c ON a.Currency_ID = c.Currency_ID
    WHERE a.User_ID = :user_id
");
$accountStmt->bindValue(':user_id', $_SESSION['user_id']);
$accountResult = $accountStmt->execute();
$account = $accountResult->fetchArray(SQLITE3_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Currenzy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .profile-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <div class="profile-header">
            <h2 class="mb-3">User Profile</h2>
            <div class="d-flex justify-content-between align-items-center">
                <h4><?= htmlspecialchars($user['First_name'] . ' ' . $user['Last_name']) ?></h4>
                <span class="badge bg-primary"><?= htmlspecialchars($user['User_type']) ?></span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h4 class="mb-4">Personal Information</h4>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($user['Email']) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?= htmlspecialchars($user['Phone']) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?= date('F j, Y', strtotime($user['DOB'])) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?= htmlspecialchars($user['Address']) ?></div>
                </div>
            </div>

            <div class="col-md-6">
                <h4 class="mb-4">Account Information</h4>
                <div class="info-item">
                    <div class="info-label">Account Number</div>
                    <div class="info-value"><?= htmlspecialchars($account['Account_number']) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Account Currency</div>
                    <div class="info-value">
                        <?= htmlspecialchars($account['Currency_code']) ?> 
                        (<?= htmlspecialchars($account['Symbol']) ?>)
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Account Balance</div>
                    <div class="info-value">
                        <?= htmlspecialchars($account['Symbol'] . number_format($account['Balance'], 2)) ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Transaction Limit</div>
                    <div class="info-value">
                        <?= htmlspecialchars($account['Symbol'] . number_format($account['Transaction_limit'], 2)) ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Country</div>
                    <div class="info-value"><?= htmlspecialchars($user['Country']) ?></div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
            <a href="change-password.php" class="btn btn-outline-secondary">Change Password</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
