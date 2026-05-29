<?php
/**
 * Fix owner_user_id mapping in chemical_stock
 * Problem: "นายนพดล พริ้งเพราะ" has two user accounts (admin1=id1, lab0=id15)
 *          Stock import mapped all 3521 bottles to id=15 (lab0) instead of id=1 (admin1)
 * 
 * This script:
 *   1) Re-maps ALL owner_user_id by matching owner_name → users (via name stripping)
 *   2) Handles duplicate names by preferring the user with admin/higher role
 *   3) Reports changes made
 */
require_once __DIR__ . '/../includes/database.php';

$pdo = Database::getInstance();
echo "========================================\n";
echo " Fix Chemical Stock Owner Mapping\n";
echo "========================================\n\n";

// Step 1: Build name → user_id map (prefer higher-role users for duplicates)
$users = $pdo->query("
    SELECT u.id, u.username, u.first_name, u.last_name, u.full_name_th, r.level as role_level
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY r.level DESC
")->fetchAll(PDO::FETCH_ASSOC);

$nameMap = []; // cleaned name → [user_id, username, role_level]
foreach ($users as $u) {
    $clean = trim(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $u['full_name_th'] ?? ''));
    $concat = trim($u['first_name'] . ' ' . $u['last_name']);
    $full = $u['full_name_th'] ?? '';
    
    // For each name variant, only store if no higher-role user already claimed it
    foreach ([$clean, $concat, $full] as $key) {
        $key = mb_strtolower(trim($key), 'UTF-8');
        if ($key === '') continue;
        if (!isset($nameMap[$key]) || $u['role_level'] > $nameMap[$key]['level']) {
            $nameMap[$key] = ['id' => $u['id'], 'username' => $u['username'], 'level' => $u['role_level']];
        }
    }
}

echo "Built name map with " . count($nameMap) . " entries\n\n";

// Step 2: Get all distinct owner_names
$owners = $pdo->query("SELECT DISTINCT owner_user_id, owner_name FROM chemical_stock WHERE owner_name IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$fixes = [];
$ok = 0;
foreach ($owners as $o) {
    $name = $o['owner_name'];
    $currentId = (int)$o['owner_user_id'];
    
    // Try matching
    $clean = trim(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $name));
    $key = mb_strtolower($clean, 'UTF-8');
    $match = $nameMap[$key] ?? $nameMap[mb_strtolower($name, 'UTF-8')] ?? null;
    
    if ($match && $match['id'] !== $currentId) {
        $fixes[] = ['name' => $name, 'from' => $currentId, 'to' => $match['id'], 'to_user' => $match['username']];
    } else {
        $ok++;
    }
}

echo "OK (no change needed): $ok owners\n";
echo "Fixes needed: " . count($fixes) . " owners\n\n";

if (count($fixes) === 0) {
    echo "✅ All owner mappings are correct!\n";
    exit;
}

// Step 3: Apply fixes
$totalRows = 0;
foreach ($fixes as $f) {
    echo "  FIX: \"{$f['name']}\" → owner_user_id {$f['from']} → {$f['to']} ({$f['to_user']})\n";
    $stmt = $pdo->prepare("UPDATE chemical_stock SET owner_user_id = :new_id WHERE owner_user_id = :old_id AND owner_name = :name");
    $stmt->execute([':new_id' => $f['to'], ':old_id' => $f['from'], ':name' => $f['name']]);
    $cnt = $stmt->rowCount();
    echo "       → Updated $cnt rows\n";
    $totalRows += $cnt;
}

echo "\n========================================\n";
echo " Total rows updated: $totalRows\n";
echo "========================================\n";

// Step 4: Verify
echo "\n=== Verification ===\n";
$r = $pdo->query("
    SELECT DISTINCT cs.owner_user_id, cs.owner_name, u.username, COUNT(*) as cnt
    FROM chemical_stock cs
    LEFT JOIN users u ON cs.owner_user_id = u.id
    GROUP BY cs.owner_user_id, cs.owner_name
    ORDER BY cnt DESC
    LIMIT 10
");
foreach ($r as $row) {
    echo "  uid={$row['owner_user_id']} | user={$row['username']} | name={$row['owner_name']} | bottles={$row['cnt']}\n";
}

// Verify admin1 specifically
$cnt = $pdo->query("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = 1")->fetchColumn();
echo "\n✅ admin1 (id=1) now has $cnt bottles in stock\n";
