<?php
require_once __DIR__ . '/../includes/database.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== MAPPING: user.department → lab_stores hierarchy ===\n\n";

// We know users have 2 patterns:
// Pattern 1: dept = division_name (ฝ่าย...), pos = section_name (งาน...)
// Pattern 2: dept = section_name (งาน...), pos = store_name (ห้อง...)

echo "--- PATTERN 1: user.dept matches division_name ---\n";
$p1 = Database::fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.department, u.position,
           ls.id as store_id, ls.division_name, ls.section_name, ls.store_name,
           COUNT(cs.id) as bottle_count
    FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.division_name AND u.position = ls.section_name
    LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
    WHERE u.department IS NOT NULL AND u.department != ''
    AND ls.is_active = 1
    GROUP BY u.id, ls.id
    ORDER BY u.department, u.position
");
echo "  Matched: " . count($p1) . " user-store pairs\n";
foreach($p1 as $r) {
    echo "  User #{$r['id']} ({$r['first_name']}) | dept={$r['department']} | pos={$r['position']} → Store #{$r['store_id']} '{$r['store_name']}' | bottles={$r['bottle_count']}\n";
}

echo "\n--- PATTERN 2: user.dept matches section_name ---\n";
$p2 = Database::fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.department, u.position,
           ls.id as store_id, ls.division_name, ls.section_name, ls.store_name,
           COUNT(cs.id) as bottle_count
    FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.section_name
    LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
    WHERE u.department IS NOT NULL AND u.department != ''
    AND ls.is_active = 1
    GROUP BY u.id, ls.id
    ORDER BY u.department, u.position
");
echo "  Matched: " . count($p2) . " user-store pairs\n";
foreach($p2 as $r) {
    echo "  User #{$r['id']} ({$r['first_name']}) | dept={$r['department']} | pos={$r['position']} → Store #{$r['store_id']} '{$r['store_name']}' | bottles={$r['bottle_count']}\n";
}

echo "\n--- PATTERN 2b: user.dept matches section_name AND user.pos matches store_name ---\n";
$p2b = Database::fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.department, u.position,
           ls.id as store_id, ls.division_name, ls.section_name, ls.store_name,
           COUNT(cs.id) as bottle_count
    FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.section_name AND u.position = ls.store_name
    LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
    WHERE u.department IS NOT NULL AND u.department != ''
    AND ls.is_active = 1
    GROUP BY u.id, ls.id
    ORDER BY u.department, u.position
");
echo "  Matched: " . count($p2b) . " user-store pairs\n";
foreach($p2b as $r) {
    echo "  User #{$r['id']} ({$r['first_name']}) | dept={$r['department']} | pos={$r['position']} → Store #{$r['store_id']} '{$r['store_name']}' | bottles={$r['bottle_count']}\n";
}

echo "\n--- UNMAPPED USERS (no match to any lab_store) ---\n";
$unmatched = Database::fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.department, u.position,
           COUNT(cs.id) as bottle_count
    FROM users u
    LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
    WHERE u.department IS NOT NULL AND u.department != ''
    AND u.department NOT IN (SELECT division_name FROM lab_stores WHERE is_active=1)
    AND u.department NOT IN (SELECT section_name FROM lab_stores WHERE is_active=1)
    GROUP BY u.id
    ORDER BY u.department
");
echo "  Unmapped users: " . count($unmatched) . "\n";
foreach($unmatched as $r) {
    echo "  User #{$r['id']} ({$r['first_name']}) | dept={$r['department']} | pos={$r['position']} | bottles={$r['bottle_count']}\n";
}

echo "\n--- Multi-store mapping check (P1 users with multiple section matches) ---\n";
$multi = Database::fetchAll("
    SELECT u.id, u.first_name, u.department, u.position, COUNT(DISTINCT ls.id) as store_count
    FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.division_name AND u.position = ls.section_name
    WHERE ls.is_active = 1
    GROUP BY u.id
    HAVING store_count > 1
");
echo "  Users matching multiple stores: " . count($multi) . "\n";
foreach($multi as $r) {
    echo "  User #{$r['id']} ({$r['first_name']}) matches {$r['store_count']} stores\n";
}

echo "\n--- SECTION has multiple stores check ---\n";
$multiStore = Database::fetchAll("
    SELECT section_name, COUNT(*) as cnt, GROUP_CONCAT(store_name SEPARATOR ' | ') as stores
    FROM lab_stores WHERE is_active=1
    GROUP BY section_name
    HAVING cnt > 1
    ORDER BY cnt DESC
");
echo "  Sections with multiple stores: " . count($multiStore) . "\n";
foreach($multiStore as $r) {
    echo "  '{$r['section_name']}' ({$r['cnt']} stores): {$r['stores']}\n";
}

echo "\n--- LIVE TOTALS: Per division (from chemical_stock via user.dept) ---\n";
$divTotals = Database::fetchAll("
    SELECT u.department as division_name,
           COUNT(cs.id) as bottle_count,
           COUNT(DISTINCT cs.chemical_name) as chemical_count,
           COUNT(DISTINCT u.id) as holder_count,
           ROUND(SUM(cs.remaining_qty), 2) as total_qty
    FROM chemical_stock cs
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status IN ('active','low')
    AND u.department IN (SELECT DISTINCT division_name FROM lab_stores WHERE is_active=1)
    GROUP BY u.department
    ORDER BY bottle_count DESC
");
echo "  Divisions with live data: " . count($divTotals) . "\n";
foreach($divTotals as $r) {
    echo "  {$r['division_name']} | bottles={$r['bottle_count']} | chems={$r['chemical_count']} | holders={$r['holder_count']} | qty={$r['total_qty']}\n";
}

echo "\n--- LIVE TOTALS: Users with dept=section_name ---\n";
$secDirect = Database::fetchAll("
    SELECT u.department as section_name,
           COUNT(cs.id) as bottle_count,
           COUNT(DISTINCT cs.chemical_name) as chemical_count,
           COUNT(DISTINCT u.id) as holder_count,
           ROUND(SUM(cs.remaining_qty), 2) as total_qty
    FROM chemical_stock cs
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status IN ('active','low')
    AND u.department IN (SELECT DISTINCT section_name FROM lab_stores WHERE is_active=1)
    GROUP BY u.department
    ORDER BY bottle_count DESC
");
echo "  Sections with live data (dept=section): " . count($secDirect) . "\n";
foreach($secDirect as $r) {
    echo "  {$r['section_name']} | bottles={$r['bottle_count']} | chems={$r['chemical_count']} | holders={$r['holder_count']} | qty={$r['total_qty']}\n";
}

echo "\n=== TOTAL COVERAGE ===\n";
$allOwners = Database::fetch("SELECT COUNT(DISTINCT owner_user_id) as cnt FROM chemical_stock WHERE status IN ('active','low')");
echo "Total unique stock owners: {$allOwners['cnt']}\n";

$p1owners = Database::fetch("
    SELECT COUNT(DISTINCT u.id) as cnt FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.division_name AND u.position = ls.section_name
    WHERE ls.is_active=1
");
echo "P1 matched users (dept=div, pos=sec): {$p1owners['cnt']}\n";

$p2owners = Database::fetch("
    SELECT COUNT(DISTINCT u.id) as cnt FROM users u
    INNER JOIN lab_stores ls ON u.department = ls.section_name
    WHERE ls.is_active=1
");
echo "P2 matched users (dept=section): {$p2owners['cnt']}\n";

$combined = Database::fetch("
    SELECT COUNT(DISTINCT u.id) as cnt FROM users u
    WHERE u.department IS NOT NULL AND u.department != ''
    AND (u.department IN (SELECT division_name FROM lab_stores WHERE is_active=1)
         OR u.department IN (SELECT section_name FROM lab_stores WHERE is_active=1))
");
echo "Combined matched users: {$combined['cnt']}\n";
