<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Money - SecureFX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $db = new SQLite3('SecureFX.db');

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $senderAccount = $db->querySingle(
        "SELECT a.Account_ID, a.Currency_ID, c.Currency_code, c.Currency_name, c.Symbol, a.Balance
         FROM Account a
         JOIN Currency c ON a.Currency_ID = c.Currency_ID
         WHERE a.User_ID = {$_SESSION['user_id']} LIMIT 1",
        true
    );

    if (!$senderAccount) {
        die("Sender account not found.");
    }

    $senderCurrencyID   = $senderAccount['Currency_ID'];
    $senderCurrencyCode = $senderAccount['Currency_code'];
    $senderCurrencySymbol = $senderAccount['Symbol'];
    $senderCurrencyName = $senderAccount['Currency_name'];
    $senderAccountID    = $senderAccount['Account_ID'];
    $senderBalance      = $senderAccount['Balance'];

    function fetch_currencies($exclude_currency_id) {
        global $db;
        try {
            $query = $db->query("SELECT Currency_ID, Currency_code, Currency_name, Symbol, Country FROM Currency WHERE Currency_ID != $exclude_currency_id");
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

    // Handle AJAX get_rate with cross-rate fallback
    if (isset($_GET['get_rate'])) {
        header('Content-Type: application/json');
        try {
            $from = (int)$_GET['from'];
            $to   = (int)$_GET['to'];
            $base = 1; // USD ID

            // 1) Direct lookup
            $stmt = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
            $stmt->bindValue(':from', $from, SQLITE3_INTEGER);
            $stmt->bindValue(':to',   $to,   SQLITE3_INTEGER);
            $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            // 2) Fallback via USD if no direct rate
            if (!$res && $from !== $base && $to !== $base) {
                // A → USD
                $stmt1 = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :base ORDER BY Date_updated DESC LIMIT 1");
                $stmt1->bindValue(':from', $from, SQLITE3_INTEGER);
                $stmt1->bindValue(':base', $base, SQLITE3_INTEGER);
                $r1 = $stmt1->execute()->fetchArray(SQLITE3_ASSOC);

                // USD → B
                $stmt2 = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :base AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
                $stmt2->bindValue(':base', $base, SQLITE3_INTEGER);
                $stmt2->bindValue(':to',   $to,   SQLITE3_INTEGER);
                $r2 = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);

                if ($r1 && $r2) {
                    $cross = $r1['Rate'] * $r2['Rate'];
                    $res   = ['Rate' => round($cross, 4)];
                }
            }

            if ($res) {
                echo json_encode($res);
            } else {
                echo json_encode(['error' => 'Rate not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $toCurrencyID = $_POST['toCurrency'];
        $amount       = floatval($_POST['amount']);
        $recipientName  = trim($_POST['recipientName']);
        $accountNumber  = trim($_POST['accountNumber']);
        $fee             = $amount * 0.005;
        $total           = $amount + $fee;

        if ($amount <= 0 || !$accountNumber || !$recipientName) {
            echo "<script>alert('Invalid input'); window.history.back();</script>";
            exit;
        }

        if ($senderBalance < $total) {
            echo "<script>alert('Insufficient balance'); window.history.back();</script>";
            exit;
        }



        // Find recipient
        $recipientStmt = $db->prepare("SELECT Account_ID FROM Account WHERE Account_number = :acc AND Currency_ID = :to_currency LIMIT 1");
        $recipientStmt->bindValue(':acc',          $accountNumber);
        $recipientStmt->bindValue(':to_currency',  $toCurrencyID);
        $recipient = $recipientStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$recipient) {
            echo "<script>alert('Recipient account not found or currency mismatch'); window.history.back();</script>";
            exit;
        }
        $recipientAccountID = $recipient['Account_ID'];

        // Re-fetch rate for transaction
        $rateStmt = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
        $rateStmt->bindValue(':from', $senderCurrencyID, SQLITE3_INTEGER);
        $rateStmt->bindValue(':to',   $toCurrencyID,     SQLITE3_INTEGER);
        $rateRow = $rateStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$rateRow) {
            echo "<script>alert('Exchange rate not found'); window.history.back();</script>";
            exit;
        }

        $convertedAmount = $amount * $rateRow['Rate'];

        // Transaction block
        $db->exec("BEGIN TRANSACTION");
        try {
            if (!$db->exec("UPDATE Account SET Balance = Balance - $total WHERE Account_ID = $senderAccountID")) {
                throw new Exception("Failed to update sender's balance.");
            }
            if (!$db->exec("UPDATE Account SET Balance = Balance + $convertedAmount WHERE Account_ID = $recipientAccountID")) {
                throw new Exception("Failed to update recipient's balance.");
            }

            $txn = $db->prepare("INSERT INTO Transactions (Transaction_ID, Sender_account_ID, Receiver_account_ID, Currency_ID_from, Currency_ID_to, Exchange_rate, Fee, Amount, Time, Status, Suspicious_flag) VALUES (NULL, :sender, :receiver, :from, :to, :rate, :fee, :amount, :time, 'completed', 0)");
            $txn->bindValue(':sender',   $senderAccountID);
            $txn->bindValue(':receiver', $recipientAccountID);
            $txn->bindValue(':from',     $senderCurrencyID);
            $txn->bindValue(':to',       $toCurrencyID);
            $txn->bindValue(':rate',     $rateRow['Rate']);
            $txn->bindValue(':fee',      $fee);
            $txn->bindValue(':amount',   $amount);
            $txn->bindValue(':time',     date('Y-m-d H:i:s')); 
            if (!$txn->execute()) {
                throw new Exception("Failed to insert transaction record.");
            }

            $db->exec("COMMIT");
            echo "<script>alert('Money sent successfully!');window.location.href='sendmoney.php';</script>";
            exit;
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            echo "<script>alert('Transaction failed: {$e->getMessage()}');window.history.back();</script>";
            exit;
        }
    }
    ?>

    <div class="max-w-md w-full">
        <!-- Card Header -->
        <div class="bg-blue-600 rounded-t-xl p-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-paper-plane text-white text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">Send Money</h1>
            </div>
            <div class="text-xs bg-blue-500 px-3 py-1 rounded-full text-blue-100">
                Balance: <?= $senderCurrencySymbol ?><?= number_format($senderBalance, 2) ?>
            </div>
        </div>

        <!-- Card Body -->
        <div class="bg-slate-800 p-6 rounded-b-xl shadow-xl">


            <form id="sendMoneyForm" method="POST" class="space-y-5">
                <!-- From Currency -->
                <div class="relative">
                    <label class="block text-xs text-slate-400 mb-1">From Currency</label>
                    <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                        <div class="mr-3 text-blue-400">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <input type="text" class="bg-transparent border-none outline-none flex-1 text-white" 
                               id="fromCurrencyDisplay" value="<?= $senderCurrencyName ?> (<?= $senderCurrencyCode ?>)" readonly>
                        <input type="hidden" id="fromCurrency" value="<?= $senderCurrencyID ?>">
                    </div>
                </div>

                <!-- To Currency -->
                <div class="relative">
                    <label class="block text-xs text-slate-400 mb-1">To Currency</label>
                    <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                        <div class="mr-3 text-blue-400">
                            <i class="fas fa-globe"></i>
                        </div>
                        <select id="toCurrency" name="toCurrency" 
                                class="bg-slate-700 border-none outline-none flex-1 text-white appearance-none cursor-pointer"
                                onchange="()">
                            <option value="" disabled selected class="bg-slate-800">Select currency</option>
                            <?= fetch_currencies($senderCurrencyID) ?>
                        </select>
                        <div class="text-slate-400">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </div>

                <!-- Send Amount -->
                <div class="relative">
                    <label class="block text-xs text-slate-400 mb-1">Send Amount</label>
                    <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                        <div class="mr-3 text-blue-400">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <input type="number" class="bg-transparent border-none outline-none flex-1 text-white" 
                               id="amount" name="amount" step="0.01" value="100.00" oninput="calculate()" required>
                        <div class="text-slate-400">
                            <?= $senderCurrencyCode ?>
                        </div>
                    </div>
                </div>

                <!-- Recipient Account Number -->
                <div class="relative">
                    <label class="block text-xs text-slate-400 mb-1">Recipient Account Number</label>
                    <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                        <div class="mr-3 text-blue-400">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <input type="text" class="bg-transparent border-none outline-none flex-1 text-white" 
                               id="accountNumber" name="accountNumber" required>
                    </div>
                </div>

                <!-- Account Holder Name -->
                <div class="relative">
                    <label class="block text-xs text-slate-400 mb-1">Account Holder Name</label>
                    <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                        <div class="mr-3 text-blue-400">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" class="bg-transparent border-none outline-none flex-1 text-white" 
                               id="recipientName" name="recipientName" required>
                    </div>
                </div>

                <!-- Exchange Summary -->
                <div class="bg-slate-700/50 rounded-lg p-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">They Receive:</span>
                        <div class="flex items-center">
                            <span id="receiveSymbol" class="text-slate-300 mr-1"></span>
                            <input type="text" id="receiveAmount" 
                                  class="bg-transparent border-none outline-none text-right text-green-400 font-medium" 
                                  value="0.00" readonly>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Fee (0.5%):</span>
                        <span id="feeDisplay" class="text-slate-300">0.00</span>
                    </div>
                    <div class="h-px bg-slate-600 my-2"></div>
                    <div class="flex justify-between text-sm font-medium">
                        <span class="text-slate-300">Total Cost:</span>
                        <span id="totalDisplay" class="text-white">0.00</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg 
                                            transition-all font-medium flex items-center justify-center">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Money
                </button>
            </form>
        </div>
    </div>

    <script>
        let selectedCurrencySymbol = '';
        let selectedCurrencyCode = '';

        function calculate() {
            let amount = parseFloat(document.getElementById("amount").value) || 0;
            let fee = amount * 0.005;
            let total = amount + fee;
            
            document.getElementById("feeDisplay").textContent = fee.toFixed(2);
            document.getElementById("totalDisplay").textContent = `${total.toFixed(2)} <?= $senderCurrencyCode ?>`;
            
            updateRate();
        }

        function updateRate() {
            let fromCurrency = document.getElementById("fromCurrency").value;
            let toCurrencySelect = document.getElementById("toCurrency");
            let toCurrency = toCurrencySelect.value;
            
            if (toCurrency) {
                // Get selected currency symbol and code
                let selectedOption = toCurrencySelect.options[toCurrencySelect.selectedIndex];
                selectedCurrencySymbol = selectedOption.getAttribute('data-symbol') || '';
                selectedCurrencyCode = selectedOption.getAttribute('data-code') || '';
                
                document.getElementById("receiveSymbol").textContent = selectedCurrencySymbol;
                
                fetch(`sendmoney.php?get_rate=true&from=${fromCurrency}&to=${toCurrency}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.Rate) {
                            let rate = data.Rate;
                            let amount = parseFloat(document.getElementById("amount").value) || 0;
                            document.getElementById("receiveAmount").value = (amount * rate).toFixed(2);
                        } else {
                            alert('Exchange rate not found');
                            document.getElementById("receiveAmount").value = "0.00";
                        }
                    })
                    .catch(error => {
                        console.error("Error fetching exchange rate:", error);
                        document.getElementById("receiveAmount").value = "0.00";
                    });
            } else {
                document.getElementById("receiveAmount").value = "0.00";
                document.getElementById("receiveSymbol").textContent = "";
            }
        }

        document.getElementById("sendMoneyForm").onsubmit = function(event) {
            event.preventDefault();
            calculate();
            this.submit();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculate();
        });
    </script>
</body>
</html>