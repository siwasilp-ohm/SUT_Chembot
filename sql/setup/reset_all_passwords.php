<?php
$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$hash = password_hash('123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL");
$stmt->execute([$hash]);
echo "Reset " . $stmt->rowCount() . " users to password '123'\n";
