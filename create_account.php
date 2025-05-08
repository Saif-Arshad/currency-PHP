<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $conn = new PDO("sqlite:Currenzy.db");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

$currencies = $conn->query("SELECT * FROM Currency")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency_id = $_POST['currency_id'] ?? 0;

    $stmt = $conn->prepare("SELECT Account_ID FROM Account WHERE User_ID = ? AND Currency_ID = ?");
    $stmt->execute([$_SESSION['user_id'], $currency_id]);
    if ($stmt->fetch()) {
        $error = "You already have an account in this currency.";
    } else {
        $account_number = uniqid();
        $insert = $conn->prepare("INSERT INTO Account (User_ID, Currency_ID, Account_number, Balance, Transaction_limit) VALUES (?, ?, ?, 0.00, 1000.00)");
        $insert->execute([$_SESSION['user_id'], $currency_id, $account_number]);
        $_SESSION['active_account_id'] = $conn->lastInsertId();
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Account - Currenzy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-rem">
    <!-- ... (same navbar as dashboard) ... -->
</nav>

<div class="container my-4">
    <h2>Create New Account</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Select Currency</label>
            <select name="currency_id" class="form-select" required>
                <?php foreach ($currencies as $currency): ?>
                    <option value="<?= $currency['Currency_ID'] ?>">
                        <?= $currency['Currency_code'] ?> - <?= $currency['Currency_name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
</div>
</body>
</html>