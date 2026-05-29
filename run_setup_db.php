<?php
/**
 * Database Setup Script - Web Version
 * Used to initialize the database when CLI access points to a different instance.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

header('Content-Type: text/plain');

$sqlFile = __DIR__ . '/sql/setup_database.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at $sqlFile");
}

echo "Starting Database Setup on " . DB_NAME . "...\n";

try {
    $db = Database::getInstance();
    
    // Read the SQL file
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolon (simplistic but often works for setup scripts)
    // We should be careful with triggers/procedures, but let's try a direct execute first
    // If it's too large, we might need a more robust parser
    
    $queries = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql);
    
    $count = 0;
    $success = 0;
    $errors = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        $count++;
        try {
            $db->exec($query);
            $success++;
        } catch (PDOException $e) {
            $errors++;
            echo "Error in query $count: " . $e->getMessage() . "\n";
            // echo "Query: " . substr($query, 0, 100) . "...\n";
        }
    }
    
    echo "\nSetup Complete!\n";
    echo "Total Queries: $count\n";
    echo "Successful: $success\n";
    echo "Errors: $errors\n";
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
