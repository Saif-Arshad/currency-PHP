<?php
$db = new SQLite3('Currenzy.db');
$base_currency_id = 1; // Assuming USD is ID 1
$date = '2025-02-05';

// Get all currencies except USD
$currencies = $db->query("SELECT Currency_ID FROM Currency WHERE Currency_ID != $base_currency_id");

$insert_values = [];

while ($row = $currencies->fetchArray(SQLITE3_ASSOC)) {
    $target_id = $row['Currency_ID'];
    
    // Generate realistic random rate (0.1 to 200 range)
    $rate_to = mt_rand(1, 20000) / 100;  // Random rate between 0.01 and 200.00
    $rate_from = round(1/$rate_to, 4);

    // Add direct rate (USD -> Target)
    $insert_values[] = sprintf("(%d, %d, %.4f, '%s')", 
        $base_currency_id, 
        $target_id, 
        $rate_to, 
        $date
    );

    // Add inverse rate (Target -> USD)
    $insert_values[] = sprintf("(%d, %d, %.4f, '%s')", 
        $target_id, 
        $base_currency_id, 
        $rate_from, 
        $date
    );
}

// Split into batches of 50 for SQLite
$batches = array_chunk($insert_values, 50);

foreach ($batches as $batch) {
    $query = "INSERT INTO Exchange_rate 
              (Currency_ID_from, Currency_ID_to, Rate, Date_updated)
              VALUES " . implode(',', $batch);
    
    $db->exec($query);
}

echo "Generated " . count($insert_values) . " exchange rates!";
?>