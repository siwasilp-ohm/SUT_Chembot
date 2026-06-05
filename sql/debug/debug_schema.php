<?php
require_once __DIR__ . '/../includes/database.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== USERS TABLE STRUCTURE ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM users");
foreach($cols as $c) echo "  {$c['Field']} | {$c['Type']} | Null={$c['Null']} | Default={$c['Default']}\n";

echo "\n=== LAB_STORES TABLE STRUCTURE ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM lab_stores");
foreach($cols as $c) echo "  {$c['Field']} | {$c['Type']} | Null={$c['Null']} | Default={$c['Default']}\n";

echo "\n=== CHEMICAL_STOCK TABLE STRUCTURE ===\n";
$cols = Database::fetchAll("SHOW COLUMNS FROM chemical_stock");
foreach($cols as $c) echo "  {$c['Field']} | {$c['Type']} | Null={$c['Null']} | Default={$c['Default']}\n";

echo "\n=== SAMPLE: Users with non-empty department (first 10) ===\n";
$users = Database::fetchAll("SELECT id, username, first_name, last_name, department, position, store_id, role_id FROM users WHERE department IS NOT NULL AND department != '' LIMIT 10");
foreach($users as $u) {
    echo "  #{$u['id']}: {$u['first_name']} {$u['last_name']} | dept={$u['department']} | pos={$u['position']} | store_id={$u['store_id']} | role_id={$u['role_id']}\n";
}

echo "\n=== SAMPLE: Users with store_id set ===\n";
$users = Database::fetchAll("SELECT id, username, first_name, last_name, department, position, store_id FROM users WHERE store_id IS NOT NULL AND store_id > 0");
echo "  Count: " . count($users) . "\n";
foreach($users as $u) {
    echo "  #{$u['id']}: {$u['first_name']} {$u['last_name']} | dept={$u['department']} | pos={$u['position']} | store_id={$u['store_id']}\n";
}

echo "\n=== LAB_STORES: Distinct hierarchy levels ===\n";
$centers = Database::fetchAll("SELECT DISTINCT center_name FROM lab_stores WHERE is_active=1 AND center_name IS NOT NULL AND center_name != ''");
echo "  Centers: " . count($centers) . "\n";
foreach($centers as $c) echo "    - {$c['center_name']}\n";

$divs = Database::fetchAll("SELECT DISTINCT division_name FROM lab_stores WHERE is_active=1 AND division_name IS NOT NULL AND division_name != ''");
echo "  Divisions: " . count($divs) . "\n";
foreach($divs as $d) echo "    - {$d['division_name']}\n";

$secs = Database::fetchAll("SELECT DISTINCT section_name FROM lab_stores WHERE is_active=1 AND section_name IS NOT NULL AND section_name != ''");
echo "  Sections: " . count($secs) . "\n";
foreach($secs as $s) echo "    - {$s['section_name']}\n";

echo "\n=== CHEMICAL_STOCK: Status distribution ===\n";
$stats = Database::fetchAll("SELECT status, COUNT(*) as cnt FROM chemical_stock GROUP BY status");
foreach($stats as $s) echo "  {$s['status']}: {$s['cnt']}\n";

echo "\n=== CHEMICAL_STOCK: Sample with owner (first 10 active) ===\n";
$chems = Database::fetchAll("SELECT cs.id, cs.chemical_name, cs.remaining_qty, cs.unit, cs.status, cs.owner_user_id, u.first_name, u.last_name, u.department, u.position, u.store_id FROM chemical_stock cs LEFT JOIN users u ON cs.owner_user_id = u.id WHERE cs.status IN ('active','low') ORDER BY cs.remaining_qty DESC LIMIT 10");
foreach($chems as $c) {
    echo "  Stock#{$c['id']}: {$c['chemical_name']} | qty={$c['remaining_qty']} {$c['unit']} | owner=#{$c['owner_user_id']} {$c['first_name']} {$c['last_name']} | dept={$c['department']} | pos={$c['position']} | store_id={$c['store_id']}\n";
}

echo "\n=== CHEMICAL_STOCK: Distinct owner_user_ids ===\n";
$owners = Database::fetchAll("SELECT DISTINCT cs.owner_user_id, u.first_name, u.last_name, u.department, u.position, u.store_id FROM chemical_stock cs JOIN users u ON cs.owner_user_id = u.id WHERE cs.status IN ('active','low') ORDER BY cs.owner_user_id");
echo "  Distinct owners: " . count($owners) . "\n";
foreach($owners as $o) {
    echo "  Owner #{$o['owner_user_id']}: {$o['first_name']} {$o['last_name']} | dept={$o['department']} | pos={$o['position']} | store_id={$o['store_id']}\n";
}

echo "\n=== CHEMICAL_STOCK: Total summary ===\n";
$summary = Database::fetch("SELECT COUNT(*) as total_bottles, COUNT(DISTINCT chemical_name) as unique_chems, COUNT(DISTINCT owner_user_id) as unique_owners, SUM(remaining_qty) as total_qty FROM chemical_stock WHERE status IN ('active','low')");
echo "  Total bottles: {$summary['total_bottles']}\n";
echo "  Unique chemicals: {$summary['unique_chems']}\n";
echo "  Unique owners: {$summary['unique_owners']}\n";
echo "  Total qty (raw): {$summary['total_qty']}\n";

echo "\n=== USERS: Distinct department values ===\n";
$depts = Database::fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
echo "  Count: " . count($depts) . "\n";
foreach($depts as $d) echo "    - '{$d['department']}'\n";

echo "\n=== USERS: Distinct position values ===\n";
$positions = Database::fetchAll("SELECT DISTINCT position FROM users WHERE position IS NOT NULL AND position != '' ORDER BY position");
echo "  Count: " . count($positions) . "\n";
foreach($positions as $p) echo "    - '{$p['position']}'\n";

echo "\n=== Cross-check: Do user departments match lab_store hierarchy? ===\n";
// Check if any user.department matches any division_name
$matchDiv = Database::fetchAll("SELECT DISTINCT u.department FROM users u INNER JOIN lab_stores ls ON u.department = ls.division_name WHERE u.department IS NOT NULL AND u.department != ''");
echo "  User departments matching division_name: " . count($matchDiv) . "\n";
foreach($matchDiv as $m) echo "    - '{$m['department']}'\n";

// Check if any user.department matches any section_name
$matchSec = Database::fetchAll("SELECT DISTINCT u.department FROM users u INNER JOIN lab_stores ls ON u.department = ls.section_name WHERE u.department IS NOT NULL AND u.department != ''");
echo "  User departments matching section_name: " . count($matchSec) . "\n";
foreach($matchSec as $m) echo "    - '{$m['department']}'\n";

// Check if any user.department matches center_name
$matchCenter = Database::fetchAll("SELECT DISTINCT u.department FROM users u INNER JOIN lab_stores ls ON u.department = ls.center_name WHERE u.department IS NOT NULL AND u.department != ''");
echo "  User departments matching center_name: " . count($matchCenter) . "\n";
foreach($matchCenter as $m) echo "    - '{$m['department']}'\n";

// Check if any user.position matches section_name
$matchPosSec = Database::fetchAll("SELECT DISTINCT u.position FROM users u INNER JOIN lab_stores ls ON u.position = ls.section_name WHERE u.position IS NOT NULL AND u.position != ''");
echo "  User positions matching section_name: " . count($matchPosSec) . "\n";
foreach($matchPosSec as $m) echo "    - '{$m['position']}'\n";

// Check if any user.position matches store_name
$matchPosStore = Database::fetchAll("SELECT DISTINCT u.position FROM users u INNER JOIN lab_stores ls ON u.position = ls.store_name WHERE u.position IS NOT NULL AND u.position != ''");
echo "  User positions matching store_name: " . count($matchPosStore) . "\n";
foreach($matchPosStore as $m) echo "    - '{$m['position']}'\n";

echo "\n=== Does chemical_stock have any location/store reference? ===\n";
$csColNames = Database::fetchAll("SHOW COLUMNS FROM chemical_stock");
$locRelated = [];
foreach($csColNames as $c) {
    $name = strtolower($c['Field']);
    if (strpos($name, 'loc') !== false || strpos($name, 'store') !== false || strpos($name, 'warehouse') !== false || strpos($name, 'room') !== false || strpos($name, 'building') !== false || strpos($name, 'position') !== false || strpos($name, 'place') !== false || strpos($name, 'shelf') !== false || strpos($name, 'cabinet') !== false) {
        $locRelated[] = $c['Field'];
    }
}
echo "  Location-related columns: " . (empty($locRelated) ? 'NONE' : implode(', ', $locRelated)) . "\n";

echo "\n=== Does chemical_stock reference locations table? ===\n";
$tables = Database::fetchAll("SHOW TABLES");
echo "  All tables:\n";
foreach($tables as $t) {
    $name = array_values($t)[0];
    echo "    - {$name}\n";
}
