<?php
session_start();

// DB connection
$database = "Currenzy.db";
try {
    $conn = new PDO("sqlite:" . $database);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT User_ID, Password, User_type FROM User WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["Password"])) {
        $_SESSION["user_id"] = $user["User_ID"];
        $_SESSION["user_type"] = $user["User_type"];

        // Debug output (remove in production)
        // header('Content-Type: text/plain');
        // print_r($user);
        // exit();

        // Redirect with case-insensitive check
        if (strtolower($user["User_type"]) === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: customer_dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Currenzy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: 50px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); }
        .btn-social { display: flex; align-items: center; justify-content: center; width: 100%; border: 1px solid #ccc; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .btn-social img { width: 20px; margin-right: 10px; }
    </style>
</head>
<body>

<div class="login-container">
    <h2 class="text-center">Welcome Back</h2>

    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                    <img src="eye-icon.png" alt="Show" style="width: 20px;">
                </button>
            </div>
        </div>
        <a href="#" class="d-block text-primary text-end">Forgot password?</a>
        <button type="submit" class="btn btn-primary w-100 mt-3">Log in</button>
    </form>

    <div class="text-center my-3">or</div>

    <button class="btn-social">
        <img src="google-icon.png" alt="Google"> Continue with Google
    </button>
    <button class="btn-social">
        <img src="apple-icon.png" alt="Apple"> Continue with Apple
    </button>

    <div class="text-center mt-3">
        Need a profile? <a href="signup.php">Sign up</a>
    </div>
</div>

<script>
    function togglePassword() {
        var passwordInput = document.getElementById("password");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
        } else {
            passwordInput.type = "password";
        }
    }
</script>

</body>
</html>