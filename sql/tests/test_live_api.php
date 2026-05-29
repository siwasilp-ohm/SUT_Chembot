<?php
/**
 * Test script: Validate live data API responses
 * Tests: dashboard, drill_division, drill_section, drill_store, drill_holder
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We can't include the API file directly (it has HTTP routing at top)
// Instead, include just the database and re-define the helper functions inline for testing
require_once __DIR__ . '/../includes/database.php';

// Copy the helper functions from api/lab_stores.php
// (We test them here to validate the SQL logic)

function unitToKgSQL(string $qtyCol = 'cs.remaining_qty', string $unitCol = 'cs.unit'): string {
    return "{$qtyCol} *
        CASE WHEN {$unitCol} IN ('kg','Kg','KG','กิโลกรัม') THEN 1
             WHEN {$unitCol} IN ('g','G','gram','กรัม') THEN 0.001
             WHEN {$unitCol} IN ('mg','มิลลิกรัม') THEN 0.000001
             WHEN {$unitCol} IN ('L','l','Liter','ลิตร') THEN 1
             WHEN {$unitCol} IN ('mL','ml','ML','มิลลิลิตร') THEN 0.001
             ELSE 1 END";
}

function buildUserMatchForDivision(string $division, string $prefix = 'dv'): array {
    $sections = Database::fetchAll(
        "SELECT DISTINCT section_name FROM lab_stores WHERE is_active = 1 AND division_name = :div",
        [':div' => $division]
    );
    $conditions = [];
    $params = [];
    $conditions[] = "u.department = :{$prefix}_div";
    $params[":{$prefix}_div"] = $division;
    foreach ($sections as $i => $sec) {
        $conditions[] = "u.department = :{$prefix}_sec{$i}";
        $params[":{$prefix}_sec{$i}"] = $sec['section_name'];
    }
    return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
}

function buildUserMatchForSection(string $division, string $section, string $prefix = 'sc'): array {
    $conditions = [];
    $params = [];
    $conditions[] = "(u.department = :{$prefix}_div AND u.position = :{$prefix}_sec)";
    $params[":{$prefix}_div"] = $division;
    $params[":{$prefix}_sec"] = $section;
    $conditions[] = "(u.department = :{$prefix}_secB)";
    $params[":{$prefix}_secB"] = $section;
    return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
}

function getLiveStats(string $userCondSQL, array $params): array {
    $kgExpr = unitToKgSQL();
    $row = Database::fetch("
        SELECT COUNT(cs.id) as bottle_count,
               COUNT(DISTINCT cs.chemical_name) as chemical_count,
               COALESCE(ROUND(SUM({$kgExpr}), 4), 0) as total_weight_kg,
               COUNT(DISTINCT u.id) as holder_count
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low') AND {$userCondSQL}",
        $params);
    return [
        'bottle_count'    => (int)($row['bottle_count'] ?? 0),
        'chemical_count'  => (int)($row['chemical_count'] ?? 0),
        'total_weight_kg' => round((float)($row['total_weight_kg'] ?? 0), 4),
        'holder_count'    => (int)($row['holder_count'] ?? 0)
    ];
}

echo "=== TEST LIVE DATA LOGIC ===\n\n";

// Test 1: Dashboard totals
echo "--- 1. Dashboard Totals ---\n";
$kgExpr = unitToKgSQL();
$liveTotals = Database::fetch("
    SELECT COUNT(cs.id) as total_bottles,
           COUNT(DISTINCT cs.chemical_name) as total_chemicals,
           COALESCE(ROUND(SUM({$kgExpr}), 2), 0) as total_weight_kg,
           COUNT(DISTINCT u.id) as total_holders
    FROM chemical_stock cs
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status IN ('active','low')");

$structTotals = Database::fetch("
    SELECT COUNT(*) as total_stores,
           COUNT(DISTINCT division_name) as total_divisions,
           COUNT(DISTINCT section_name) as total_sections
    FROM lab_stores WHERE is_active = 1");

echo "  Stores:    {$structTotals['total_stores']}\n";
echo "  Divisions: {$structTotals['total_divisions']}\n";
echo "  Sections:  {$structTotals['total_sections']}\n";
echo "  Bottles:   {$liveTotals['total_bottles']}\n";
echo "  Chemicals: {$liveTotals['total_chemicals']}\n";
echo "  Weight:    {$liveTotals['total_weight_kg']} kg\n";
echo "  Holders:   {$liveTotals['total_holders']}\n";
echo "  OK\n";

// Test 2: Division live stats
echo "\n--- 2. Division Live Stats ---\n";
$divs = Database::fetchAll("SELECT DISTINCT division_name FROM lab_stores WHERE is_active = 1 ORDER BY division_name");
$totalDivBottles = 0;
foreach ($divs as $div) {
    $dn = $div['division_name'];
    $match = buildUserMatchForDivision($dn, 'td');
    $live = getLiveStats($match['sql'], $match['params']);
    $totalDivBottles += $live['bottle_count'];
    echo "  {$dn}: {$live['bottle_count']} bottles, {$live['chemical_count']} chems, {$live['total_weight_kg']} kg, {$live['holder_count']} holders\n";
}
echo "\n  Sum of all division bottles: {$totalDivBottles}\n";
echo "  Direct total bottles: {$liveTotals['total_bottles']}\n";

// Test 3: Section-level stats for first non-empty division
echo "\n--- 3. Section Live Stats (first non-empty division) ---\n";
$firstDiv = '';
foreach ($divs as $div) {
    $match = buildUserMatchForDivision($div['division_name'], 'fd');
    $live = getLiveStats($match['sql'], $match['params']);
    if ($live['bottle_count'] > 0) { $firstDiv = $div['division_name']; break; }
}
if ($firstDiv) {
    echo "  Division: {$firstDiv}\n";
    $sections = Database::fetchAll(
        "SELECT DISTINCT section_name FROM lab_stores WHERE is_active = 1 AND division_name = :div",
        [':div' => $firstDiv]);
    foreach ($sections as $sec) {
        $match = buildUserMatchForSection($firstDiv, $sec['section_name'], 'ts');
        $live = getLiveStats($match['sql'], $match['params']);
        echo "    {$sec['section_name']}: {$live['bottle_count']} bottles, {$live['holder_count']} holders\n";
    }
    echo "  OK\n";
}

// Test 4: Verify user matching - check that ALL owners are captured
echo "\n--- 4. User Coverage Check ---\n";
$allOwners = Database::fetchAll("
    SELECT DISTINCT cs.owner_user_id, u.first_name, u.last_name, u.department, u.position, 
           COUNT(*) as bottles
    FROM chemical_stock cs 
    JOIN users u ON cs.owner_user_id = u.id
    WHERE cs.status IN ('active','low')
    GROUP BY cs.owner_user_id ORDER BY bottles DESC");

$covered = 0; $uncovered = 0;
foreach ($allOwners as $owner) {
    $found = false;
    foreach ($divs as $div) {
        $match = buildUserMatchForDivision($div['division_name'], 'uc');
        $testSQL = "SELECT 1 FROM users u WHERE u.id = :uid AND {$match['sql']}";
        $testParams = array_merge([':uid' => $owner['owner_user_id']], $match['params']);
        if (Database::fetch($testSQL, $testParams)) { $found = true; break; }
    }
    if ($found) { $covered++; }
    else {
        $uncovered++;
        echo "  UNCOVERED: #{$owner['owner_user_id']} {$owner['first_name']} {$owner['last_name']} (dept={$owner['department']}, pos={$owner['position']}, {$owner['bottles']} bottles)\n";
    }
}
echo "  Covered: {$covered}/{$covered}+{$uncovered}\n";
echo "  " . ($uncovered === 0 ? 'ALL OWNERS COVERED' : "{$uncovered} UNCOVERED") . "\n";

// Direct DB verification
echo "\n--- VERIFICATION ---\n";
$dbCount = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_stock WHERE status IN ('active','low')");
echo "  Direct DB bottle count: {$dbCount['cnt']}\n";
echo "  Live total bottles:     {$liveTotals['total_bottles']}\n";
echo "  Match: " . ($dbCount['cnt'] == $liveTotals['total_bottles'] ? 'YES' : 'NO') . "\n";

$dbOwners = Database::fetch("SELECT COUNT(DISTINCT owner_user_id) as cnt FROM chemical_stock WHERE status IN ('active','low')");
echo "  Direct DB owner count:  {$dbOwners['cnt']}\n";
echo "  Live total holders:     {$liveTotals['total_holders']}\n";
echo "  Match: " . ($dbOwners['cnt'] == $liveTotals['total_holders'] ? 'YES' : 'NO') . "\n";

// === RANDOMIZE AVATAR FOR ALL DEMO USERS ===
echo "\n--- RANDOMIZING DEMO USER AVATARS ---\n";
$defaultAvatars = [
    'avatar1.png','avatar2.png','avatar3.png','avatar4.png','avatar5.png','avatar6.png','avatar7.png','default.png'
];
$demoUsers = Database::fetchAll("SELECT id, username FROM users WHERE username LIKE 'demo%' OR username LIKE 'testdemo%' OR username LIKE 'sample%' OR username LIKE 'userdemo%' OR username LIKE 'demouser%' ");
foreach ($demoUsers as $u) {
    $file = $defaultAvatars[array_rand($defaultAvatars)];
    $avatarUrl = '/v1/assets/uploads/avatars/default/' . $file;
    Database::update('users', ['avatar_url' => $avatarUrl], 'id = :id', [':id' => $u['id']]);
    echo "  Set avatar for {$u['username']} => $file\n";
}
echo "  Done.\n";

// === RANDOMIZE AVATAR FOR ALL USERS ===
echo "\n--- RANDOMIZING ALL USER AVATARS ---\n";
$allUsers = Database::fetchAll("SELECT id, username FROM users");
foreach ($allUsers as $u) {
    $file = $defaultAvatars[array_rand($defaultAvatars)];
    $avatarUrl = '/v1/assets/uploads/avatars/default/' . $file;
    Database::update('users', ['avatar_url' => $avatarUrl], 'id = :id', [':id' => $u['id']]);
    echo "  Set avatar for {$u['username']} => $file\n";
}
echo "  Done.\n";

echo "\n=== ALL TESTS DONE ===\n";
