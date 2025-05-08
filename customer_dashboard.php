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
    die("DB error: ".$e->getMessage());
}

function flagIso(string $country): string {
    static $map = [
        'United Kingdom'=>'gb', 'Eurozone'=>'eu', 'United States'=>'us',
        'Pakistan'=>'pk', 'India'=>'in', 'China'=>'cn', 'Japan'=>'jp',
    ];
    return $map[$country] ?? 'un';
}

$user_id = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT First_name FROM User WHERE User_ID=?");
$uStmt->execute([$user_id]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);

$accStmt = $conn->prepare("
  SELECT a.Account_ID,
         a.Account_number,
         a.Balance,
         c.Currency_code,
         c.Currency_name,
         c.Symbol,
         c.Country
  FROM Account a
  JOIN Currency c ON a.Currency_ID = c.Currency_ID
  WHERE a.User_ID = ?
  ORDER BY a.Account_ID
");
$accStmt->execute([$user_id]);
$accounts = $accStmt->fetchAll(PDO::FETCH_ASSOC);

if ($accounts) {
    $primary   = $accounts[0];
    $primCode  = $primary['Currency_code'];
    $symbol    = $primary['Symbol'];
    $destName  = $primary['Country'];
    $flagIso   = flagIso($destName);
} else {
    $primCode = 'GBP';  $symbol = '£';  $destName = 'United Kingdom';  $flagIso='gb';
}

$txStmt = $conn->prepare("
  SELECT t.Amount, t.Time, c.Currency_code, t.Status
  FROM Transactions t
  JOIN Currency c ON t.Currency_ID_from = c.Currency_ID
  WHERE t.Sender_account_ID IN (SELECT Account_ID FROM Account WHERE User_ID=?)
  ORDER BY t.Time DESC LIMIT 5");
$txStmt->execute([$user_id]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$fxRateText = "1 {$primCode} = ??";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – Currenzy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: system-ui, -apple-system, "Segoe UI", Inter, sans-serif;
    background: #f5f6fa;
    margin-left: 250px; /* Adjust for sidebar width */
}
.dest-strip {
    background: #06336d;
    color: #fff;
    margin-left: -250px; /* Extend to full width */
}
.card {
    border-radius: 8px;
}
@media (max-width: 768px) {
    body {
        margin-left: 0;
    }
    .dest-strip {
        margin-left: 0;
    }
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="dest-strip py-2 text-center">
  <?=$destName;?>
</div>

<div class="container my-4">
 <div class="row g-3">
  <div class="col-lg-8">
   <div class="row g-3">
    <?php foreach($accounts as $a):?>
      <div class="col-md-4">
        <div class="card p-3">
          <h6 class="mb-1"><?=htmlspecialchars($a['Currency_code']);?> Account</h6>
          <p class="mb-0">Account Holder: <strong><?=htmlspecialchars($user['First_name']);?></strong></p>
          <p class="mb-0">Account Number: <strong><?=htmlspecialchars($a['Account_number']);?></strong></p>
          <p class="mb-0">Balance: <strong><?=number_format($a['Balance'],2);?></strong></p>
        </div>
      </div>
    <?php endforeach;?>
   </div>
   <div class="card p-3 mt-3">
     <h5>Recent Transactions</h5>
     <ul class="list-group">
       <?php foreach($transactions as $t):?>
         <li class="list-group-item d-flex justify-content-between">
           <span><?=$t['Currency_code'].' '.number_format($t['Amount'],2);?></span>
           <span class="text-muted"><?=date('d M Y',strtotime($t['Time']));?> – <?=$t['Status'];?></span>
         </li>
       <?php endforeach;?>
       <?php if(!$transactions):?>
         <li class="list-group-item text-muted">No transactions.</li>
       <?php endif;?>
     </ul>
   </div>
  </div>
  <div class="col-lg-4">
   <div class="card p-3">
     <h6 class="text-muted">Exchange rate</h6>
     <p class="mb-1">Sending to <?=$destName;?></p>
     <h4 class="fw-bold"><?=$fxRateText;?></h4>
     <a href="send.php" class="btn btn-primary w-100 mt-2">Send Money</a>
   </div>
   <div class="card p-3 mt-3">
     <h5>Add Funds</h5>
     <form action="add_funds.php" method="post">
       <select name="account_id" class="form-select mb-2">
         <?php foreach($accounts as $a):?>
           <option value="<?=$a['Account_ID'];?>"><?=$a['Currency_code'];?> (<?=number_format($a['Balance'],2);?>)</option>
         <?php endforeach;?>
       </select>
       <input name="amount" type="number" step="0.01" class="form-control mb-3" placeholder="Amount" required>
       <button class="btn btn-success w-100">Add Funds</button>
     </form>
   </div>
  </div>
 </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>