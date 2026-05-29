<?php
header('Content-Type: text/plain');
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
    echo "PHP PDO Connection Success\n";
    
    $vars = [
        'version',
        'version_comment',
        'hostname',
        'port',
        'socket',
        'datadir',
        'uptime'
    ];
    
    foreach ($vars as $v) {
        $stmt = $pdo->query("SHOW VARIABLES LIKE '$v'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "$v: " . ($row ? $row['Value'] : 'N/A') . "\n";
    }
    
    echo "\nCurrent User: " . $pdo->query("SELECT CURRENT_USER()")->fetchColumn() . "\n";
    
} catch (Exception $e) {
    echo "PHP PDO Error: " . $e->getMessage() . "\n";
}
echo "\n--- PHP ENV ---\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
