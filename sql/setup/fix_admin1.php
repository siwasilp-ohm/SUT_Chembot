<?php
$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$hash = password_hash('123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE username = 'admin1'");
$stmt->execute([$hash]);
echo "admin1 password reset to '123'\n";
echo "Hash: $hash\n";
echo "Rows affected: " . $stmt->rowCount() . "\n";

// Also reset lab0 just in case
$stmt2 = $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE username = 'lab0'");
$stmt2->execute([$hash]);
echo "lab0 password reset to '123'\n";

// Verify
$check = $pdo->query("SELECT id, username, login_attempts, locked_until FROM users WHERE username IN ('admin1','lab0')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($check as $u) {
    echo "{$u['username']}: attempts={$u['login_attempts']}, locked={$u['locked_until']}\n";
}
