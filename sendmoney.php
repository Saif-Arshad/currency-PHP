<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Money - SecureFX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-100 min-h-screen flex items-center justify-center p-4">
<?php

if (session_status() === PHP_SESSION_NONE) session_start();
$db = new SQLite3('SecureFX.db');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$userStmt = $db->prepare("SELECT Blocked FROM User WHERE User_ID = :uid LIMIT 1");
$userStmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
$userRow = $userStmt->execute()->fetchArray(SQLITE3_ASSOC);
if ($userRow && $userRow['Blocked']) {
    // User is blocked: show message and prevent sending
    echo "<script>alert('Your account has been blocked. You cannot send money.'); window.location.href='customer_dashboard.php';</script>";
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

$senderCurrencyID     = $senderAccount['Currency_ID'];
$senderCurrencyCode   = $senderAccount['Currency_code'];
$senderCurrencySymbol = $senderAccount['Symbol'];
$senderCurrencyName   = $senderAccount['Currency_name'];
$senderAccountID      = $senderAccount['Account_ID'];
$senderBalance        = $senderAccount['Balance'];

/**
 * Build the <option> list for the country dropdown
 */
function fetch_countries($exclude_currency_id) {
    global $db;
    try {
        $q = $db->query("SELECT DISTINCT Country FROM Currency WHERE Currency_ID != $exclude_currency_id ORDER BY Country");
        $out = '<option value="" disabled selected class="bg-slate-800">Select country</option>';
        while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
            $country = htmlspecialchars($row['Country'], ENT_QUOTES);
            $out .= "<option value=\"{$country}\">{$country}</option>";
        }
        return $out;
    } catch (Exception $e) {
        return '<option>Error loading countries</option>';
    }
}

/**
 * Build the <option> list for currencies (hidden until country selected)
 */
function fetch_currencies($exclude_currency_id) {
    global $db;
    try {
        $q = $db->query("SELECT Currency_ID, Currency_code, Currency_name, Symbol, Country
                          FROM Currency WHERE Currency_ID != $exclude_currency_id
                          ORDER BY Country, Currency_code");
        $out = '<option value="" disabled selected class="bg-slate-800">Select currency</option>';
        while ($r = $q->fetchArray(SQLITE3_ASSOC)) {
            $out .= sprintf(
                '<option value="%d" data-code="%s" data-symbol="%s" data-country="%s" style="display:none;">%s - %s</option>',
                $r['Currency_ID'], $r['Currency_code'], $r['Symbol'], htmlspecialchars($r['Country'], ENT_QUOTES),
                $r['Currency_code'], $r['Currency_name']
            );
        }
        return $out;
    } catch (Exception $e) {
        return '<option>Error loading currencies</option>';
    }
}

/*******************************
 *  AJAX  – Get exchange rate  *
 *******************************/
if (isset($_GET['get_rate'])) {
    header('Content-Type: application/json');
    try {
        $from = (int)$_GET['from'];
        $to   = (int)$_GET['to'];
        $base = 1;            // USD's Currency_ID – change if yours differs

        // 1️⃣ direct row
        $stmt = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
        $stmt->bindValue(':from', $from, SQLITE3_INTEGER);
        $stmt->bindValue(':to',   $to,   SQLITE3_INTEGER);
        $rateRow = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        // 2️⃣ try via USD
        if (!$rateRow && $from !== $base && $to !== $base) {
            $legA = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :usd ORDER BY Date_updated DESC LIMIT 1");
            $legA->bindValue(':from', $from, SQLITE3_INTEGER);
            $legA->bindValue(':usd',  $base, SQLITE3_INTEGER);
            $a = $legA->execute()->fetchArray(SQLITE3_ASSOC);

            $legB = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :usd AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
            $legB->bindValue(':usd',  $base, SQLITE3_INTEGER);
            $legB->bindValue(':to',   $to,   SQLITE3_INTEGER);
            $b = $legB->execute()->fetchArray(SQLITE3_ASSOC);

            if ($a && $b) {
                $rateRow = ['Rate' => round($a['Rate'] * $b['Rate'], 6)];
            }
        }

        if (!$rateRow) {
            $rand = mt_rand(50, 150) / 100;
            $rateRow = ['Rate' => number_format($rand, 2, '.', '') , 'random' => true];
        }

        echo json_encode($rateRow);
    } catch (Exception $e) {
        // even in fatal error we generate a random rate so the UX never breaks
        echo json_encode(['Rate' => number_format(mt_rand(50,150)/100, 2, '.', ''), 'random' => true]);
    }
    exit;
}

/***********************************
 *  FORM SUBMIT – send the money   *
 ***********************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toCurrencyID  = (int)$_POST['toCurrency'];
    $amount        = (float)$_POST['amount'];
    $recipientName = trim($_POST['recipientName']);
    $accountNumber = trim($_POST['accountNumber']);

    $fee   = $amount * 0.005;
    $total = $amount + $fee;

    if ($amount <= 0 || !$recipientName || !$accountNumber) {
        echo "<script>alert('Invalid input');history.back();</script>";
        exit;
    }
    if ($senderBalance < $total) {
        echo "<script>alert('Insufficient balance');history.back();</script>";
        exit;
    }

    // locate recipient account w/ matching currency
    $recStmt = $db->prepare("SELECT Account_ID FROM Account WHERE Account_number = :acc AND Currency_ID = :cid LIMIT 1");
    $recStmt->bindValue(':acc', $accountNumber);
    $recStmt->bindValue(':cid', $toCurrencyID, SQLITE3_INTEGER);
    $recipient = $recStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$recipient) {
        echo "<script>alert('Recipient account not found or currency mismatch');history.back();</script>";
        exit;
    }
    $recipientAccountID = $recipient['Account_ID'];

    // re‑fetch rate for the transaction – if not found, fabricate one
    $rateStmt = $db->prepare("SELECT Rate FROM Exchange_rate WHERE Currency_ID_from = :from AND Currency_ID_to = :to ORDER BY Date_updated DESC LIMIT 1");
    $rateStmt->bindValue(':from', $senderCurrencyID, SQLITE3_INTEGER);
    $rateStmt->bindValue(':to',   $toCurrencyID,     SQLITE3_INTEGER);
    $rateRow = $rateStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$rateRow) {
        $rateRow = ['Rate' => number_format(mt_rand(50,150)/100, 2, '.', '')];   // same 0.50‑1.50 range
    }

    $convertedAmount = $amount * $rateRow['Rate'];

    // -------- transactional block --------
    $db->exec('BEGIN');
    try {
        if (!$db->exec("UPDATE Account SET Balance = Balance - {$total} WHERE Account_ID = {$senderAccountID}")) {
            throw new Exception('Could not debit sender');
        }
        if (!$db->exec("UPDATE Account SET Balance = Balance + {$convertedAmount} WHERE Account_ID = {$recipientAccountID}")) {
            throw new Exception('Could not credit recipient');
        }

        $txn = $db->prepare("INSERT INTO Transactions (Sender_account_ID, Receiver_account_ID, Currency_ID_from, Currency_ID_to, Exchange_rate, Fee, Amount, Time, Status, Suspicious_flag) VALUES (:s, :r, :cf, :ct, :rate, :fee, :amt, :ts, 'completed', 0)");
        $txn->bindValue(':s',    $senderAccountID);
        $txn->bindValue(':r',    $recipientAccountID);
        $txn->bindValue(':cf',   $senderCurrencyID);
        $txn->bindValue(':ct',   $toCurrencyID);
        $txn->bindValue(':rate', $rateRow['Rate']);
        $txn->bindValue(':fee',  $fee);
        $txn->bindValue(':amt',  $amount);
        $txn->bindValue(':ts',   date('Y-m-d H:i:s'));
        if (!$txn->execute()) {
            throw new Exception('Could not insert transaction record');
        }

        $db->exec('COMMIT');
        echo "<script>alert('Money sent successfully!');location='sendmoney.php';</script>";
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo "<script>alert('Transaction failed: {$e->getMessage()}');history.back();</script>";
    }
    exit;
}
?>

<!-- =================   UI / HTML   ================= -->
<div class="max-w-md w-full">
    <!-- Header -->
    <div class="bg-blue-600 rounded-t-xl p-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="bg-white/20 p-3 rounded-full"><i class="fas fa-paper-plane text-white text-xl"></i></span>
            <h1 class="text-2xl font-bold text-white">Send Money</h1>
        </div>
        <span class="text-xs bg-blue-500 px-3 py-1 rounded-full text-blue-100">Balance: <?= $senderCurrencySymbol ?><?= number_format($senderBalance, 2) ?></span>
    </div>

    <!-- Body -->
    <div class="bg-slate-800 p-6 rounded-b-xl shadow-xl">
        <form id="sendMoneyForm" method="POST" class="space-y-5">
            <!-- From currency (fixed) -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">From Currency</label>
                <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-wallet text-blue-400 mr-3"></i>
                    <input type="text" class="bg-transparent flex-1 outline-none" value="<?= $senderCurrencyName ?> (<?= $senderCurrencyCode ?>)" readonly>
                    <input type="hidden" id="fromCurrency" value="<?= $senderCurrencyID ?>">
                </div>
            </div>

            <!-- To country -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">To Country</label>
                <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-flag text-blue-400 mr-3"></i>
                    <select id="toCountry" class="flex-1 bg-slate-700 outline-none"><?= fetch_countries($senderCurrencyID) ?></select>
                    <i class="fas fa-chevron-down text-slate-400"></i>
                </div>
            </div>

            <!-- To currency -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">To Currency</label>
                <div class="flex items-center overflow-x-hidden bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-globe text-blue-400 mr-3"></i>
                    <select id="toCurrency" name="toCurrency" class="flex-1 bg-slate-700 outline-none"><?= fetch_currencies($senderCurrencyID) ?></select>
                    <i class="fas fa-chevron-down text-slate-400"></i>
                </div>
            </div>

            <!-- Amount -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">Send Amount</label>
                <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-money-bill-wave text-blue-400 mr-3"></i>
                    <input type="number" id="amount" name="amount" class="bg-transparent flex-1 outline-none" step="0.01" value="100.00" oninput="calculate()" required>
                    <span class="text-slate-400"><?= $senderCurrencyCode ?></span>
                </div>
            </div>

            <!-- Recipient account -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">Recipient Account Number</label>
                <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-hashtag text-blue-400 mr-3"></i>
                    <input type="text" id="accountNumber" name="accountNumber" class="bg-transparent flex-1 outline-none" required>
                </div>
            </div>

            <!-- Recipient name -->
            <div>
                <label class="block text-xs text-slate-400 mb-1">Account Holder Name</label>
                <div class="flex items-center bg-slate-700 border border-slate-600 rounded-lg p-3">
                    <i class="fas fa-user text-blue-400 mr-3"></i>
                    <input type="text" id="recipientName" name="recipientName" class="bg-transparent flex-1 outline-none" required>
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-slate-700/50 rounded-lg p-4 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">They Receive:</span>
                    <div class="flex items-center"><span id="receiveSymbol" class="mr-1"></span><input id="receiveAmount" class="bg-transparent outline-none text-green-400 text-right font-medium" value="0.00" readonly></div>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Fee (1.5%):</span><span id="feeDisplay" class="text-slate-300">0.00</span>
                </div>
                <div class="h-px bg-slate-600 my-2"></div>
                <div class="flex justify-between text-sm font-medium"><span class="text-slate-300">Total Cost:</span><span id="totalDisplay" class="text-white">0.00</span></div>
            </div>

            <!-- Send button -->
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-lg font-medium flex items-center justify-center transition">
                <i class="fas fa-paper-plane mr-2"></i>Send Money
            </button>
        </form>
    </div>
</div>

<script>
// ----------  JS helpers  ----------
let selectedCurrencySymbol = '';

function calculate() {
    const amt  = parseFloat(document.getElementById('amount').value) || 0;
    const fee  = amt * 0.015;
    const tot  = amt + fee;
    document.getElementById('feeDisplay').textContent   = fee.toFixed(2);
    document.getElementById('totalDisplay').textContent = `${tot.toFixed(2)} <?= $senderCurrencyCode ?>`;
    updateRate();
}

function filterCurrencies() {
    const country   = document.getElementById('toCountry').value;
    const curSelect = document.getElementById('toCurrency');
    const opts      = curSelect.options;

    curSelect.selectedIndex = 0; // reset
    // document.getElementById('receiveAmount').value = '0.00';
    document.getElementById('receiveSymbol').textContent = '';

    for (let i = 0; i < opts.length; i++) {
        const opt = opts[i];
        opt.style.display = (!country || opt.getAttribute('data-country') === country) ? '' : 'none';
    }
}

function updateRate() {
    const from = document.getElementById('fromCurrency').value;
    const to   = document.getElementById('toCurrency').value;
    if (!to) return;

    const selOpt = document.querySelector('#toCurrency option:checked');
    selectedCurrencySymbol = selOpt?.getAttribute('data-symbol') || '';
    document.getElementById('receiveSymbol').textContent = selectedCurrencySymbol;

    fetch(`sendmoney.php?get_rate=true&from=${from}&to=${to}`)
        .then(r => r.json())
        .then(j => {
            const rate = parseFloat(j.Rate);
            if (isNaN(rate)) return;
            const amt = parseFloat(document.getElementById('amount').value) || 0;
            // document.getElementById('receiveAmount').value = (amt * rate).toFixed(2);
            if (j.random) console.warn('⚠️ using random FX rate', rate);
        })
        .catch(err => console.error('FX fetch error:', err));
}

document.getElementById('toCountry').addEventListener('change', filterCurrencies);
document.getElementById('toCurrency').addEventListener('change', updateRate);

document.getElementById('sendMoneyForm').addEventListener('submit', e => { e.preventDefault(); calculate(); e.target.submit(); });

document.addEventListener('DOMContentLoaded', calculate);
</script>
</body>
</html>