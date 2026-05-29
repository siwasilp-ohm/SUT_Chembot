<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    echo "PHP DB_NAME: " . DB_NAME . "\n";
    
    $stmt = $db->query("SELECT DATABASE()");
    echo "Active Database: " . $stmt->fetchColumn() . "\n";

    echo "\nAll Tables in current DB:\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        echo " - $t\n";
    }

    echo "\nSearching for 'system_settings' in all databases:\n";
    $stmt = $db->query("SELECT table_schema FROM information_schema.tables WHERE table_name = 'system_settings'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo " - Found in: " . $row['table_schema'] . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
