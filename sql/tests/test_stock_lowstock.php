<?php
require_once __DIR__ . '/../includes/database.php';

// Find low stock items
$lowStock = Database::fetchAll(
    "SELECT cs.id, cs.bottle_code, cs.chemical_name, cs.remaining_pct, cs.remaining_qty, cs.unit
     FROM chemical_stock cs
     WHERE cs.status IN ('active','low') AND cs.remaining_pct <= 20
     ORDER BY cs.remaining_pct ASC
     LIMIT 5"
);

echo "=== Low Stock Items ===\n";
foreach ($lowStock as $ls) {
    echo "  ID:{$ls['id']} {$ls['chemical_name']} — {$ls['remaining_pct']}% ({$ls['remaining_qty']} {$ls['unit']})\n";
}

if ($lowStock) {
    $testId = $lowStock[0]['id'];
    echo "\n=== Detail for ID:$testId ===\n";
    
    $row = Database::fetch(
        "SELECT s.*, 
                u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last,
                u.department as owner_department,
                c.name as linked_chem_name, c.cas_number as linked_cas, c.molecular_formula,
                c.signal_word, c.hazard_pictograms, c.ghs_classifications,
                c.physical_state, c.sds_url
         FROM chemical_stock s
         LEFT JOIN users u ON s.owner_user_id = u.id
         LEFT JOIN chemicals c ON s.chemical_id = c.id
         WHERE s.id = :id",
        [':id' => $testId]
    );
    
    if ($row) {
        echo "  Name: {$row['chemical_name']}\n";
        echo "  Linked: {$row['linked_chem_name']}\n";
        echo "  Owner: {$row['owner_first']} {$row['owner_last']} ({$row['owner_department']})\n";
        echo "  Remaining: {$row['remaining_qty']} / {$row['package_size']} {$row['unit']} ({$row['remaining_pct']}%)\n";
        echo "  Grade: {$row['grade']}\n";
        echo "  Formula: {$row['molecular_formula']}\n";
        echo "  Signal: {$row['signal_word']}\n";
        echo "  Status: {$row['status']}\n";
        echo "\n=== OK ===\n";
    } else {
        echo "  NOT FOUND\n";
    }
}
