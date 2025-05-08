<?php
// Start session and connect to SQLite database
if (session_status() === PHP_SESSION_NONE) session_start();
$db = new SQLite3('Currenzy.db');

// Function to fetch currencies with error handling
function fetch_currencies() {
    global $db;
    try {
        $query = $db->query("
            SELECT c.Currency_ID, c.Currency_code, c.Currency_name, c.Symbol, c.Country 
            FROM Currency c
        ");
        
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

// Handle exchange rate requests
if(isset($_GET['get_rate'])) {
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
                WHERE Currency_ID_from = :from 
                AND Currency_ID_to = :to 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currenzy - Money Transfers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                        url('https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            color: #f8f9fa;
        }

        .hero-section {
            padding: 4rem 0;
            background-color: rgba(45, 55, 72, 0.9);
        }

        .country-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .country-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }

        .flag-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .transfer-widget {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar {
            background-color: rgba(45, 55, 72, 0.9) !important;
            backdrop-filter: blur(10px);
        }

        .btn-primary {
            background-color: #4a5568;
            border-color: #4a5568;
        }

        .btn-primary:hover {
            background-color: #2d3748;
            border-color: #2d3748;
        }

        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f8f9fa;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
            color: #f8f9fa;
            box-shadow: none;
        }

        .amount-buttons button {
            margin: 5px 3px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="#">Currenzy</a>
        <div class="d-flex">
            <a href="login.php" class="btn btn-light me-2">Login</a>
            <a href="signup.php" class="btn btn-outline-light">Sign Up</a>
        </div>
    </div>
</nav>

<main class="hero-section">
    <div class="container text-center">
        <div class="transfer-widget mb-5">
            <h3 class="mb-4">Start Your Transfer</h3>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label fw-bold">Sending from</label>
                        <select class="form-select py-3" id="currencyFrom" onchange="updateRate()">
                            <?= fetch_currencies() ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label fw-bold">Sending to</label>
                        <select class="form-select py-3" id="currencyTo" onchange="updateRate()">
                            <?= fetch_currencies() ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label fw-bold">You send</label>
                        <input type="number" class="form-control py-3" id="sendAmount" 
                               value="100.00" step="0.01" oninput="calculate()">
                        <div class="amount-buttons mt-2">
                            <button type="button" class="btn btn-outline-light" onclick="setAmount(200)">200</button>
                            <button type="button" class="btn btn-outline-light" onclick="setAmount(500)">500</button>
                            <button type="button" class="btn btn-outline-light" onclick="setAmount(1000)">1000</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label fw-bold">They receive</label>
                        <input type="text" class="form-control py-3" id="receiveAmount" readonly>
                    </div>
                </div>

                <div class="col-12">
                    <div class="fee-info p-3 rounded">
                        <div class="d-flex justify-content-between">
                            <span>Fee (0.5%):</span>
                            <span id="feeAmount">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Cost:</span>
                            <span id="totalCost">0.00</span>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="rate-info text-muted" id="exchangeRateDisplay"></div>
                </div>
            </div>

            <button type="button" onclick="sendMoney()" class="btn btn-primary w-100 py-3">
                Send Now
            </button>
        </div>

        <div class="popular-countries mt-5">
            <h4 class="mb-4">Popular Destinations</h4>
            <div class="row g-2 g-md-3 justify-content-center">
                <?php
                try {
                    $result = $db->query("
                        SELECT Currency_ID, Currency_code, Country 
                        FROM Currency 
                        ORDER BY RANDOM() LIMIT 6
                    ");
                    
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        echo '
                        <div class="col-4 col-sm-3 col-md-2">
                            <div class="country-card">
                                <div class="flag-icon">'.substr($row['Country'], 0, 2).'</div>
                                <div class="country-name small">'.$row['Country'].'</div>
                            </div>
                        </div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="text-danger">Error loading popular destinations</div>';
                }
                ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let exchangeRate = 0;
    const FEE_PERCENT = 0.005;

    function setAmount(amount) {
        document.getElementById('sendAmount').value = amount;
        calculate();
    }

    async function updateRate() {
        const from = document.getElementById('currencyFrom').value;
        const to = document.getElementById('currencyTo').value;
        
        try {
            const response = await fetch(`?get_rate=1&from=${from}&to=${to}`);
            const data = await response.json();
            
            if(data.error) throw new Error(data.error);
            
            exchangeRate = data.Rate;
            const fromCurrency = document.querySelector('#currencyFrom option:checked').dataset.code;
            const toCurrency = document.querySelector('#currencyTo option:checked').dataset.code;
            
            document.getElementById('exchangeRateDisplay').innerHTML = 
                `1 ${fromCurrency} = ${exchangeRate.toFixed(4)} ${toCurrency}`;
            calculate();
        } catch (error) {
            console.error('Error:', error);
            alert('Error fetching exchange rate: ' + error.message);
        }
    }

    function calculate() {
        const sendAmount = parseFloat(document.getElementById('sendAmount').value) || 0;
        const fee = sendAmount * FEE_PERCENT;
        const totalCost = sendAmount + fee;
        const receiveAmount = sendAmount * exchangeRate;

        document.getElementById('feeAmount').textContent = fee.toFixed(2);
        document.getElementById('totalCost').textContent = totalCost.toFixed(2);
        document.getElementById('receiveAmount').value = receiveAmount.toFixed(2);
    }

    function sendMoney() {
        const from = document.getElementById('currencyFrom').value;
        const to = document.getElementById('currencyTo').value;
        const amount = parseFloat(document.getElementById('sendAmount').value);

        if (!from || !to || isNaN(amount) || amount <= 0) {
            alert('Please select both currencies and enter a valid amount.');
            return;
        }

        window.location.href = 'signup.php';
    }

    updateRate();
</script>
</body>
</html>