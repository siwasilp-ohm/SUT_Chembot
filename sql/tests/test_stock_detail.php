<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/database.php';

// Pick a stock item that has transactions
$hasTxn = Database::fetch("
    SELECT cs.id, cs.bottle_code, cs.chemical_name 
    FROM chemical_stock cs
    WHERE EXISTS (
        SELECT 1 FROM chemical_transactions ct 
        WHERE (ct.source_type = 'stock' AND ct.source_id = cs.id) OR ct.barcode = cs.bottle_code
    ) LIMIT 1");

if ($hasTxn) {
    echo "=== Stock with transactions ===\n";
    echo "  ID: {$hasTxn['id']}, Code: {$hasTxn['bottle_code']}, Name: {$hasTxn['chemical_name']}\n";
} else {
    echo "=== No stock with transactions, using random stock ===\n";
    $hasTxn = Database::fetch("SELECT id, bottle_code, chemical_name FROM chemical_stock WHERE status = 'active' LIMIT 1");
    echo "  ID: {$hasTxn['id']}, Code: {$hasTxn['bottle_code']}, Name: {$hasTxn['chemical_name']}\n";
}

// Test the stock_detail SQL
$stockId = $hasTxn['id'];
$stock = Database::fetch("
    SELECT cs.*,
           CONCAT(u.first_name, ' ', u.last_name) as owner_full_name,
           u.department as owner_department, u.position as owner_position,
           c.name as chem_name, c.cas_number as chem_cas, c.molecular_formula, c.molecular_weight,
           c.physical_state, c.signal_word, c.hazard_pictograms, c.ghs_classifications,
           c.substance_type, c.un_class, c.ghs_hazard_text,
           c.sds_url
    FROM chemical_stock cs
    LEFT JOIN users u ON cs.owner_user_id = u.id
    LEFT JOIN chemicals c ON cs.chemical_id = c.id
    WHERE cs.id = :id",
    [':id' => $stockId]);

echo "\n=== Stock Detail ===\n";
echo "  Name: {$stock['chemical_name']}\n";
echo "  CAS: {$stock['cas_no']}\n";
echo "  Owner: {$stock['owner_full_name']}\n";
echo "  Dept: {$stock['owner_department']}\n";
echo "  Formula: {$stock['molecular_formula']}\n";
echo "  Signal: {$stock['signal_word']}\n";
echo "  GHS: {$stock['ghs_hazard_text']}\n";
echo "  Remaining: {$stock['remaining_qty']} {$stock['unit']} ({$stock['remaining_pct']}%)\n";

// Check transactions
$hist = Database::fetchAll("
    SELECT ct.id, ct.txn_number, ct.txn_type, ct.status as txn_status,
           ct.quantity, ct.unit, ct.purpose,
           ct.created_at,
           CONCAT(fu.first_name, ' ', fu.last_name) as from_user_name,
           CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name
    FROM chemical_transactions ct
    LEFT JOIN users fu ON ct.from_user_id = fu.id
    LEFT JOIN users tu ON ct.to_user_id = tu.id
    WHERE (ct.source_type = 'stock' AND ct.source_id = :sid)
       OR ct.barcode = :bc
    ORDER BY ct.created_at DESC",
    [':sid' => $stockId, ':bc' => $stock['bottle_code']]);

echo "\n=== Transaction History (" . count($hist) . " records) ===\n";
foreach ($hist as $h) {
    echo "  [{$h['txn_type']}] {$h['txn_status']} | qty={$h['quantity']} {$h['unit']} | from={$h['from_user_name']} to={$h['to_user_name']} | {$h['created_at']}\n";
    if ($h['purpose']) echo "    Purpose: {$h['purpose']}\n";
}

// Also test stock #3 and #5 which should have transactions
echo "\n=== Testing stocks with known transactions ===\n";
foreach ([3, 5] as $sid) {
    $s = Database::fetch("SELECT id, bottle_code, chemical_name FROM chemical_stock WHERE id = :id", [':id' => $sid]);
    if (!$s) { echo "  Stock #{$sid}: NOT FOUND\n"; continue; }
    $cnt = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions ct 
        WHERE (ct.source_type = 'stock' AND ct.source_id = :sid) OR ct.barcode = :bc",
        [':sid' => $sid, ':bc' => $s['bottle_code']]);
    echo "  Stock #{$sid} ({$s['chemical_name']}): {$cnt['cnt']} transactions\n";
}

echo "\n=== DONE ===\n";
