<?php
// ─────────────────────────────────────────────────────────
//  customer-signup.php – Currenzy Currency Transfer App
//  Registers a customer + opens their first account
// ─────────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:Currenzy.db');
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
<title>Customer Signup – Currenzy</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, sans-serif;
        background: #f5f5f5;
        margin: 0;
    }
    .container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }
    form {
        background: #fff;
        padding: 24px 32px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .07);
        width: 100%;
        max-width: 400px;
    }
    h2 {
        margin: 0 0 20px;
        text-align: center;
        color: #333;
    }
    input, select, button {
        width: 100%;
        padding: 12px;
        margin-top: 8px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 16px;
    }
    button {
        background: #28a745;
        color: #fff;
        border: none;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    button:hover {
        background: #218838;
    }
    .message {
        margin: 15px 0;
        padding: 10px;
        border-radius: 4px;
        text-align: center;
    }
    .success { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
    a {
        color: #007bff;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>
<div class="container">
    <form method="POST" autocomplete="off">
        <h2>Create Customer Account</h2>

        <?php if ($success): ?>
            <div class="message success">
                Registration successful!<br>
                <a href="login.php">Login now</a>
            </div>
        <?php elseif ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <input type="date" name="dob" required>
        <input type="text" name="address" placeholder="Full Address" required>

        <select name="currency_id" required>
            <option value="">Select Account Currency</option>
            <?php foreach ($currencies as $id => $label): ?>
                <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Create Account</button>
        
        <p style="text-align: center; margin-top: 15px;">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </form>
</div>
</body>
</html>