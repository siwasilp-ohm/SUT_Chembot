<?php
/**
 * Test lab_detail API endpoint
 */
require_once __DIR__ . '/../includes/database.php';

// First get all departments
$depts = Database::fetchAll(
    "SELECT u.department, COUNT(DISTINCT cs.id) as bottles, COUNT(DISTINCT u.id) as users
     FROM users u
     LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
     WHERE u.is_active = 1 AND u.department IS NOT NULL AND u.department != ''
     GROUP BY u.department
     ORDER BY bottles DESC
     LIMIT 5"
);

echo "=== Top Departments ===\n";
foreach ($depts as $d) {
    echo "  {$d['department']}: {$d['bottles']} bottles, {$d['users']} users\n";
}

// Test getLabDetail for the first department
if ($depts) {
    $testDept = $depts[0]['department'];
    echo "\n=== Testing lab_detail for: $testDept ===\n";
    
    // Summary
    $summary = Database::fetch(
        "SELECT COUNT(DISTINCT cs.id) as total_bottles,
                COUNT(DISTINCT CASE WHEN cs.status IN ('active','low') THEN cs.id END) as active_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'expired' THEN cs.id END) as expired_bottles,
                COUNT(DISTINCT cs.chemical_id) as unique_chemicals,
                COUNT(DISTINCT u.id) as user_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_remaining_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id
         WHERE u.is_active = 1 AND u.department = :dept",
        [':dept' => $testDept]
    );
    echo "Summary:\n";
    print_r($summary);
    
    // Members
    $members = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.position,
                COUNT(DISTINCT cs.id) as bottle_count,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
         WHERE u.is_active = 1 AND u.department = :dept
         GROUP BY u.id
         ORDER BY bottle_count DESC",
        [':dept' => $testDept]
    );
    echo "\nMembers: " . count($members) . "\n";
    foreach (array_slice($members, 0, 3) as $m) {
        echo "  {$m['first_name']} {$m['last_name']}: {$m['bottle_count']} bottles (avg {$m['avg_pct']}%)\n";
    }
    
    // Chemicals
    $chemicals = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name,
                COUNT(cs.id) as bottle_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct,
                cs.unit
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept AND cs.status IN ('active','low')
         GROUP BY COALESCE(cs.chemical_id, cs.id)
         ORDER BY bottle_count DESC
         LIMIT 5",
        [':dept' => $testDept]
    );
    echo "\nTop Chemicals: " . count($chemicals) . "\n";
    foreach ($chemicals as $c) {
        echo "  {$c['name']}: {$c['bottle_count']} bottles (avg {$c['avg_pct']}%)\n";
    }
    
    echo "\n=== ALL OK ===\n";
}
