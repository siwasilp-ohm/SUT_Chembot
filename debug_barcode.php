<?php
require_once __DIR__ . '/includes/database.php';
$pdo = Database::getInstance();

echo "=== Barcode Format Analysis ===\n";
$bc = 'F02212A6000028';
echo "Example: $bc (len=" . strlen($bc) . ")\n";
echo "  [0-2] Building prefix = '" . substr($bc, 0, 3) . "'\n";
echo "  [0-5] Room code       = '" . substr($bc, 0, 6) . "'\n";
echo "  [6]   Section         = '" . substr($bc, 6, 1) . "'\n";
echo "  [7-8] Fiscal Year     = '" . substr($bc, 7, 2) . "' → TH 60 = 2560 = AD 2017\n";
echo "  [9-13] Serial         = '" . substr($bc, 9, 5) . "'\n\n";

echo "=== Buildings ===\n";
$rows = $pdo->query("SELECT id, code, name FROM buildings ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} code={$r['code']} name={$r['name']}\n";
}

echo "\n=== Rooms ===\n";
$rows = $pdo->query("SELECT r.id, r.code, r.name, r.building_id, b.code as bcode FROM rooms r LEFT JOIN buildings b ON r.building_id=b.id ORDER BY b.code, r.code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  room_id={$r['id']} bldg={$r['bcode']} room_code={$r['code']} name={$r['name']}\n";
}

echo "\n=== Sample containers with bottle_code ===\n";
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code, cn.building_id, cn.room_id,
           b.code as bcode, b.name as bname,
           rm.code as rcode, rm.name as rname
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id = b.id
    LEFT JOIN rooms rm ON cn.room_id = rm.id
    ORDER BY cn.bottle_code
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} bc={$r['bottle_code']} bldg={$r['bcode']}({$r['building_id']}) room={$r['rcode']}({$r['room_id']})\n";
}

echo "\n=== Containers where bottle_code starts with F02212 ===\n";
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code, cn.building_id, cn.room_id,
           b.code as bcode, rm.code as rcode
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id = b.id
    LEFT JOIN rooms rm ON cn.room_id = rm.id
    WHERE cn.bottle_code LIKE 'F02212%'
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} bc={$r['bottle_code']} bldg={$r['bcode']} room={$r['rcode']}\n";
}

echo "\n=== Building code distribution from bottle_codes ===\n";
// Extract prefix from bottle_code and compare with actual building_id
$rows = $pdo->query("
    SELECT 
        LEFT(cn.bottle_code, 3) as bc_bldg,
        b.code as actual_bldg,
        COUNT(*) as cnt
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id = b.id
    WHERE cn.bottle_code IS NOT NULL AND cn.bottle_code != ''
    GROUP BY LEFT(cn.bottle_code, 3), b.code
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $match = ($r['bc_bldg'] === $r['actual_bldg']) ? '✓' : '✗ MISMATCH';
    echo "  barcode_bldg={$r['bc_bldg']} actual_bldg={$r['actual_bldg']} cnt={$r['cnt']} $match\n";
}

echo "\n=== Seed seeder logic check ===\n";
// Show what room codes look like for F02
$rows = $pdo->query("
    SELECT r.id, r.code, r.name, b.code as bcode
    FROM rooms r
    LEFT JOIN buildings b ON r.building_id = b.id
    WHERE b.code LIKE 'F02%' OR r.code LIKE 'F02%'
    ORDER BY r.code
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  room_id={$r['id']} bcode={$r['bcode']} rcode={$r['code']} rname={$r['name']}\n";
}
