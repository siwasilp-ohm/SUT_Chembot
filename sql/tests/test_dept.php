<?php
require_once __DIR__ . '/../includes/database.php';
// Test departments query
$rows = Database::fetchAll("SELECT id, name, level, level_label, parent_id FROM departments WHERE is_active = 1 AND level = 1 ORDER BY sort_order, name");
echo "Level 1 departments: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  ID={$r['id']} Name={$r['name']}\n";
}

echo "\n";
$rows2 = Database::fetchAll("SELECT id, name, level, level_label, parent_id FROM departments WHERE is_active = 1 AND level = 2 AND parent_id = 1 ORDER BY sort_order, name LIMIT 5");
echo "Level 2 under parent 1: " . count($rows2) . "\n";
foreach ($rows2 as $r) {
    echo "  ID={$r['id']} Name={$r['name']} Label={$r['level_label']}\n";
}

echo "\n";
$rows3 = Database::fetchAll("SELECT id, name, level, level_label, parent_id FROM departments WHERE is_active = 1 AND level = 3 AND parent_id = 10 ORDER BY sort_order, name LIMIT 5");
echo "Level 3 under parent 10: " . count($rows3) . "\n";
foreach ($rows3 as $r) {
    echo "  ID={$r['id']} Name={$r['name']}\n";
}
