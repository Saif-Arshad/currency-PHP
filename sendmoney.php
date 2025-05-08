<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$db = new SQLite3('Currenzy.db');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$senderAccount = $db->querySingle("
    SELECT a.Account_ID, a.Currency_ID, c.Currency_code, c.Currency_name, c.Symbol, a.Balance
    FROM Account a 
    JOIN Currency c ON a.Currency_ID = c.Currency_ID 
    WHERE a.User_ID = {$_SESSION['user_id']} 
    LIMIT 1", true);

if (!$senderAccount) {
    die("Sender account not found.");
}

$senderCurrencyID = $senderAccount['Currency_ID'];
$senderCurrencyCode = $senderAccount['Currency_code'];
$senderCurrencySymbol = $senderAccount['Symbol'];
$senderCurrencyName = $senderAccount['Currency_name'];
$senderAccountID = $senderAccount['Account_ID'];
$senderBalance = $senderAccount['Balance'];

function fetch_currencies($exclude_currency_id) {
    global $db;
    try {
        $query = $db->query("SELECT Currency_ID, Currency_code, Currency_name, Symbol, Country 
                             FROM Currency WHERE Currency_ID != $exclude_currency_id");
        $options = '';
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $options .= sprintf(
                '<option value="%d" data-code="%s" data-symbol="%s">%s (%s)</option>',
                $row['Currency_ID'],
                $row['Currency_code'],
                $row['Symbol'],
                $row['Country'],
                $row['Currency_code']
            );
        }
        return $options;
    } catch (Exception $e) {
        return '<option>Error loading currencies</option>';
    }
}

if (isset($_GET['get_rate'])) {
    header('Content-Type: application/json');
    try {
        $from = $_GET['from'];
        $to = $_GET['to'];

        if ($from == $to) {
            echo json_encode(['Rate' => 1]);
        } else {
            $stmt = $db->prepare("
                SELECT Rate 
                FROM Exchange_rate 
                WHERE Currency_ID_from = :from AND Currency_ID_to = :to 
                ORDER BY Date_updated DESC 
                LIMIT 1
            ");
            $stmt->bindValue(':from', $from, SQLITE3_INTEGER);
            $stmt->bindValue(':to', $to, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $rate = $result->fetchArray(SQLITE3_ASSOC);

            echo json_encode($rate ?: ['error' => 'Rate not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toCurrencyID = $_POST['toCurrency'];
    $amount = floatval($_POST['amount']);
    $recipientName = trim($_POST['recipientName']);
    $accountNumber = trim($_POST['accountNumber']);
    $fee = $amount * 0.005;
    $total = $amount + $fee;

    if ($amount <= 0 || !$accountNumber || !$recipientName) {
        echo "<script>alert('Invalid input'); window.history.back();</script>";
        exit;
    }

    if ($senderBalance < $total) {
        echo "<script>alert('Insufficient balance'); window.history.back();</script>";
        exit;
    }

    $recipientStmt = $db->prepare("
        SELECT Account_ID 
        FROM Account 
        WHERE Account_number = :acc AND Currency_ID = :to_currency
        LIMIT 1
    ");
    $recipientStmt->bindValue(':acc', $accountNumber);
    $recipientStmt->bindValue(':to_currency', $toCurrencyID);
    $recipient = $recipientStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$recipient) {
        echo "<script>alert('Recipient account not found or currency mismatch'); window.history.back();</script>";
        exit;
    }

    $recipientAccountID = $recipient['Account_ID'];

    $rateStmt = $db->prepare("
        SELECT Rate 
        FROM Exchange_rate 
        WHERE Currency_ID_from = :from AND Currency_ID_to = :to 
        ORDER BY Date_updated DESC LIMIT 1
    ");
    $rateStmt->bindValue(':from', $senderCurrencyID, SQLITE3_INTEGER);
    $rateStmt->bindValue(':to', $toCurrencyID, SQLITE3_INTEGER);
    $rate = $rateStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$rate) {
        echo "<script>alert('Exchange rate not found'); window.history.back();</script>";
        exit;
    }

    $convertedAmount = $amount * $rate['Rate'];

    // Start transaction block
    $db->exec("BEGIN TRANSACTION");

    try {
        $updateSender = $db->exec("UPDATE Account SET Balance = Balance - $total WHERE Account_ID = $senderAccountID");
        if (!$updateSender) {
            throw new Exception("Failed to update sender's balance.");
        }

        $updateRecipient = $db->exec("UPDATE Account SET Balance = Balance + $convertedAmount WHERE Account_ID = $recipientAccountID");
        if (!$updateRecipient) {
            throw new Exception("Failed to update recipient's balance.");
        }

        $stmt = $db->prepare("
            INSERT INTO Transactions (
                Transaction_ID, Sender_account_ID, Receiver_account_ID, Currency_ID_from, Currency_ID_to, 
                Exchange_rate, Fee, Amount, Time, Status, Suspicious_flag
            ) VALUES (
                NULL, :sender, :receiver, :from, :to, :rate, :fee, :amount, :time, 'completed', 0
            )
        ");
        $stmt->bindValue(':sender', $senderAccountID);
        $stmt->bindValue(':receiver', $recipientAccountID);
        $stmt->bindValue(':from', $senderCurrencyID);
        $stmt->bindValue(':to', $toCurrencyID);
        $stmt->bindValue(':rate', $rate['Rate']);
        $stmt->bindValue(':fee', $fee);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':time', date('Y-m-d H:i:s'));
        $insertTransaction = $stmt->execute();
        if (!$insertTransaction) {
            throw new Exception("Failed to insert transaction record.");
        }

        // Commit the transaction
        $db->exec("COMMIT");

        echo "<script>alert('Money sent successfully!'); window.location.href='sendmoney.php';</script>";
        exit;
    } catch (Exception $e) {
        // Rollback in case of error
        $db->exec("ROLLBACK");
        echo "<script>alert('Transaction failed: " . $e->getMessage() . "'); window.history.back();</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Money - Currenzy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #0f172a;
            color: #f1f5f9;
            padding-top: 60px;
        }
        .send-container {
            max-width: 800px;
            margin: auto;
            background: rgba(255,255,255,0.05);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        .form-control, .form-select {
            background-color: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.2);
            color: #f1f5f9;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255,255,255,0.1);
            color: #f1f5f9;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #3b82f6;
            border: none;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .rate-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container send-container">
    <h2 class="text-center mb-4">Send Money</h2>
    <form id="sendMoneyForm" method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">From Currency</label>
                <input type="text" class="form-control" id="fromCurrencyDisplay"
                       value="<?= $senderCurrencyName ?> (<?= $senderCurrencyCode ?>)" readonly>
                <input type="hidden" id="fromCurrency" value="<?= $senderCurrencyID ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">To Currency</label>
                <select id="toCurrency" name="toCurrency" class="form-select" onchange="updateRate()">
                    <option value="" disabled selected>No selection</option>
                    <?= fetch_currencies($senderCurrencyID) ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Send Amount</label>
                <input type="number" class="form-control" id="amount" name="amount" step="0.01" value="100.00" oninput="calculate()">
            </div>

            <div class="col-md-6">
                <label class="form-label">Recipient Account Number</label>
                <input type="text" class="form-control" id="accountNumber" name="accountNumber" required>
            </div>

            <div class="col-md-12">
                <label class="form-label">Account Holder Name</label>
                <input type="text" class="form-control" id="recipientName" name="recipientName" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">They Receive</label>
                <input type="text" id="receiveAmount" class="form-control" readonly>
            </div>

            <div class="col-md-6">
                <div class="rate-box mt-4">
                    <p class="mb-1">Fee (0.5%): <span id="feeDisplay">0.00</span></p>
                    <p>Total Cost: <span id="totalDisplay">0.00</span></p>
                </div>
            </div>

            <div class="col-md-12">
                <button type="submit" class="btn btn-primary w-100">Send</button>
            </div>
        </div>
    </form>
</div>

<script>
    function calculate() {
        let amount = parseFloat(document.getElementById("amount").value);
        let fee = amount * 0.005;
        let total = amount + fee;
        document.getElementById("feeDisplay").textContent = fee.toFixed(2);
        document.getElementById("totalDisplay").textContent = total.toFixed(2);
    }

    function updateRate() {
        let fromCurrency = document.getElementById("fromCurrency").value;
        let toCurrency = document.getElementById("toCurrency").value;
        if (toCurrency) {
            fetch(`sendmoney.php?get_rate=true&from=${fromCurrency}&to=${toCurrency}`)
                .then(response => response.json())
                .then(data => {
                    if (data.Rate) {
                        let rate = data.Rate;
                        let amount = parseFloat(document.getElementById("amount").value);
                        document.getElementById("receiveAmount").value = (amount * rate).toFixed(2);
                    } else {
                        alert('Exchange rate not found');
                    }
                });
        }
    }

    document.getElementById("sendMoneyForm").onsubmit = function(event) {
        event.preventDefault();
        calculate();
        this.submit();
    }
</script>

</body>
</html>