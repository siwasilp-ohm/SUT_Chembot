<?php
require_once __DIR__ . '/../includes/database.php';

echo "=== chemical_stock COLUMNS ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM chemical_stock");
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']}) {$c['Key']}\n";
}

echo "\n=== chemical_transactions COLUMNS ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM chemical_transactions");
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']}) {$c['Key']}\n";
}

echo "\n=== Sample transactions (5) ===\n";
$txns = Database::fetchAll("SELECT * FROM chemical_transactions ORDER BY created_at DESC LIMIT 5");
foreach ($txns as $t) {
    echo "  ID={$t['id']} type={$t['txn_type']} status={$t['status']} barcode={$t['barcode']} from={$t['from_user_id']} to={$t['to_user_id']} src_type={$t['source_type']} src_id={$t['source_id']} qty={$t['quantity']} created={$t['created_at']}\n";
}

echo "\n=== Transaction types ===\n";
$types = Database::fetchAll("SELECT txn_type, COUNT(*) as cnt FROM chemical_transactions GROUP BY txn_type");
foreach ($types as $t) {
    echo "  {$t['txn_type']}: {$t['cnt']}\n";
}

echo "\n=== Transaction statuses ===\n";
$stats = Database::fetchAll("SELECT status, COUNT(*) as cnt FROM chemical_transactions GROUP BY status");
foreach ($stats as $s) {
    echo "  {$s['status']}: {$s['cnt']}\n";
}

echo "\n=== Sample stock (1 row full detail) ===\n";
$row = Database::fetch("SELECT * FROM chemical_stock WHERE status = 'active' LIMIT 1");
foreach ($row as $k => $v) {
    echo "  {$k}: {$v}\n";
}

// Check if chemicals table exists (separate from chemical_stock)
echo "\n=== chemicals table? ===\n";
try {
    $cols = Database::fetchAll("SHOW COLUMNS FROM chemicals");
    foreach ($cols as $c) {
        echo "  {$c['Field']} ({$c['Type']}) {$c['Key']}\n";
    }
} catch (Exception $e) {
    echo "  Not found: " . $e->getMessage() . "\n";
}

// Check for transactions linked to a specific stock item
echo "\n=== Transactions for stock_id=1 ===\n";
$txns = Database::fetchAll("SELECT * FROM chemical_transactions WHERE source_id = 1 AND source_type = 'stock' ORDER BY created_at DESC LIMIT 10");
if (empty($txns)) {
    // Try broader search
    $txns = Database::fetchAll("SELECT * FROM chemical_transactions WHERE barcode = (SELECT bottle_code FROM chemical_stock WHERE id = 1) ORDER BY created_at DESC LIMIT 10");
}
foreach ($txns as $t) {
    echo "  ID={$t['id']} type={$t['txn_type']} status={$t['status']} qty={$t['quantity']} purpose=" . ($t['purpose'] ?? '') . " created={$t['created_at']}\n";
}

echo "\n=== Total transactions count ===\n";
$cnt = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_transactions");
echo "  {$cnt['cnt']} transactions total\n";
