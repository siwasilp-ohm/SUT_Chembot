<?php
require_once 'C:/xampp/htdocs/v1/includes/config.php';
require_once 'C:/xampp/htdocs/v1/includes/database.php';

try {
    $pdo = Database::getInstance();
    
    // Get containers columns
    $stmt = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='chem_inventory_db' AND TABLE_NAME='containers' ORDER BY ORDINAL_POSITION");
    echo "=== CONTAINERS TABLE ===\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $null_str = $row['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
        $def = $row['COLUMN_DEFAULT'] !== null ? " DEFAULT " . $row['COLUMN_DEFAULT'] : '';
        echo "{$row['COLUMN_NAME']}: {$row['DATA_TYPE']} {$null_str}{$def}\n";
    }
    
    echo "\n=== CHEMICAL_STOCK TABLE ===\n";
    $stmt = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='chem_inventory_db' AND TABLE_NAME='chemical_stock' ORDER BY ORDINAL_POSITION");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $null_str = $row['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
        $def = $row['COLUMN_DEFAULT'] !== null ? " DEFAULT " . $row['COLUMN_DEFAULT'] : '';
        echo "{$row['COLUMN_NAME']}: {$row['DATA_TYPE']} {$null_str}{$def}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
