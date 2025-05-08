<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $conn = new PDO("sqlite:SecureFX.db");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT First_name FROM User WHERE User_ID = ?");
$uStmt->execute([$user_id]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Link to styles.css -->
<link rel="stylesheet" href="styles.css">

<!-- Sidebar -->
<div class="sidebar bg-dark text-white d-flex flex-column vh-100 p-3" style="width: 250px; position: fixed; top: 0; left: 0;">
    <!-- Logo -->
    <div class="mb-4 d-flex align-items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="white" class="me-2" viewBox="0 0 16 16">
            <path d="M7.063 1.5a2 2 0 0 0-2 2V7h-.563a.5.5 0 0 0-.354.146L.5 10.793l.354.354 3.146-3.147H5v4.207l2.146 2.147.708-.708L5.707 11H8V3.5a2 2 0 0 0-2-2Zm1.874 0a2 2 0 0 1 2 2V7h.563a.5.5 0 0 1 .354.146l3.646 3.647-.354.354L12 8.353V12.5a2 2 0 0 1-2 2H8v-1h2a1 1 0 0 0 1-1V3.5a1 1 0 0 0-1-1H8.937Z"/>
        </svg>
        <span class="fw-bold fs-5">SecureFX</span>
    </div>

    <!-- Navigation -->
    <ul class="nav flex-column mb-auto">
        <li class="nav-item mb-2">
            <a class="nav-link text-white d-flex align-items-center rounded py-2 px-3" href="sendmoney.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" class="me-2" viewBox="0 0 16 16">
                    <path d="M4 8h8M8 4v8" stroke="white" stroke-width="2"/>
                </svg>
                Send Money
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link text-white d-flex align-items-center rounded py-2 px-3" href="viewhistory.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" class="me-2" viewBox="0 0 16 16">
                    <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm0 2a6 6 0 1 1 0 12A6 6 0 0 1 8 2zm0 2v4l3 2" fill="none" stroke="white" stroke-width="1"/>
                </svg>
                Transfer History
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link text-white d-flex align-items-center rounded py-2 px-3" href="help.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" class="me-2" viewBox="0 0 16 16">
                    <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm0 2a6 6 0 1 1 0 12A6 6 0 0 1 8 2zm0 3a1 1 0 0 0-1 1v1H6v2h1v1a1 1 0 0 0 2 0V9h1V7H9V6a1 1 0 0 0-1-1z" fill="white"/>
                </svg>
                Help
            </a>
        </li>
    </ul>

    <!-- User Dropdown -->
    <div class="mt-auto pt-4 border-top">
        <div class="dropdown">
            <a class="text-white dropdown-toggle d-flex align-items-center rounded py-2 px-3" href="#" role="button" data-bs-toggle="dropdown">
                <img src="https://via.placeholder.com/30" alt="User Avatar" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                Welcome, <?= htmlspecialchars($user['First_name']); ?>
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</div>