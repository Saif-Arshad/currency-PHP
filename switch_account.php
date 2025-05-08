<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$account_id = $_GET['account_id'] ?? 0;

try {
    $conn = new PDO("sqlite:SecureFX.db");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

$stmt = $conn->prepare("SELECT Account_ID FROM Account WHERE User_ID = ? AND Account_ID = ?");
$stmt->execute([$_SESSION['user_id'], $account_id]);
$account = $stmt->fetch();

if ($account) {
    $_SESSION['active_account_id'] = $account_id;
    header("Location: dashboard.php");
    exit();
} else {
    header("Location: dashboard.php?error=invalid_account");
    exit();
}
?>