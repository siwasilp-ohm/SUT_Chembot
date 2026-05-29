<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    echo "Connected to: " . DB_NAME . "\n";
    
    echo "\nDatabases:\n";
    $stmt = $db->query("SHOW DATABASES");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo " - " . array_values($row)[0] . "\n";
    }

    echo "\nTables in " . DB_NAME . ":\n";
    $stmt = $db->query("SHOW TABLES");
    $found = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $found = true;
        echo " - " . array_values($row)[0] . "\n";
    }
    if (!$found) echo " - NO TABLES FOUND!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
