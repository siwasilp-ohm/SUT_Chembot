<?php
// Debug script to test the fixed drillStore logic
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: text/plain; charset=utf-8');

$storeId = 4; // โรงเครื่องมือกล

echo "=== Testing Fixed drillStore Logic for Store ID $storeId ===\n\n";

// 1. Store info
$store = Database::fetch("SELECT * FROM lab_stores WHERE id = :id AND is_active = 1", [':id' => $storeId]);
echo "Store: {$store['store_name']}\n";
echo "Division: {$store['division_name']}\n";
echo "Section: {$store['section_name']}\n";
echo "Static counts: bottles={$store['bottle_count']}, chems={$store['chemical_count']}, kg={$store['total_weight_kg']}\n\n";

// 2. Live stats (store_id based)
$liveStats = Database::fetch("
    SELECT COUNT(*) as bottle_count,
           COUNT(DISTINCT cs.chemical_name) as chemical_count,
           COALESCE(ROUND(SUM(cs.remaining_qty *
               CASE WHEN cs.unit IN ('kg','Kg','KG') THEN 1
                    WHEN cs.unit IN ('g','G') THEN 0.001
                    WHEN cs.unit IN ('mg') THEN 0.000001
                    WHEN cs.unit IN ('L','l') THEN 1
                    WHEN cs.unit IN ('mL','ml','ML') THEN 0.001
                    ELSE 0.001 END
           ), 4), 0) as total_weight_kg
    FROM chemical_stock cs
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status IN ('active','low') AND u.store_id = :sid",
    [':sid' => $storeId]);
echo "Live stats (via store_id): bottles={$liveStats['bottle_count']}, chems={$liveStats['chemical_count']}, kg={$liveStats['total_weight_kg']}\n\n";

// 3. Holder search with new combined logic
$div   = $store['division_name'];
$sec   = $store['section_name'];
$sName = $store['store_name'];

$conditions = [];
$holderParams = [];

// PRIMARY: store_id
$conditions[] = '(u.store_id = :storeId)';
$holderParams[':storeId'] = $storeId;

// FALLBACK patterns
$conditions[] = '(u.department = :divA AND u.position = :secA)';
$holderParams[':divA'] = $div;
$holderParams[':secA'] = $sec;
$conditions[] = '(u.department = :secB AND u.position = :storeB)';
$holderParams[':secB'] = $sec;
$holderParams[':storeB'] = $sName;
$conditions[] = '(u.department = :secC)';
$holderParams[':secC'] = $sec;

$condSQL = implode(' OR ', $conditions);

$holders = Database::fetchAll("
    SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as full_name,
           u.department, u.position as section_name, u.store_id,
           COUNT(*) as bottle_count,
           COUNT(DISTINCT cs.chemical_name) as chemical_count,
           ROUND(SUM(cs.remaining_qty),2) as total_qty
    FROM chemical_stock cs
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status = 'active' AND ({$condSQL})
    GROUP BY u.id ORDER BY bottle_count DESC",
    $holderParams);

echo "Holders found: " . count($holders) . "\n";
foreach($holders as $h) {
    echo "  #{$h['user_id']}: {$h['full_name']} | bottles={$h['bottle_count']}, chems={$h['chemical_count']}, qty={$h['total_qty']} | store_id={$h['store_id']}\n";
}

$noHolderWarning = empty($holders) && ((int)$store['bottle_count'] > 0 || (float)$store['total_weight_kg'] > 0);
echo "\nNo holder warning: " . ($noHolderWarning ? 'YES' : 'NO') . "\n";

echo "\n=== CONCLUSION ===\n";
if (empty($holders)) {
    echo "No holders assigned to this store. The static data (1 bottle, 6 KG) is orphaned CSV data.\n";
    echo "To fix: Assign users to this store via users.store_id = $storeId\n";
} else {
    echo "Holders found! The fix is working.\n";
}
