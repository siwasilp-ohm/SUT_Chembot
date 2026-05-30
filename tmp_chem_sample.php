<?php
$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Show chemicals in containers ordered by how many containers they have (most-used first)
$stmt = $pdo->query("
    SELECT ch.id, ch.cas_number, ch.name, ch.physical_state,
           COUNT(ct.id) AS container_count,
           SUM(ct.current_quantity) AS total_qty, ct.quantity_unit,
           ch.hazard_statements, ch.hazard_pictograms
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
    WHERE ch.cas_number IS NOT NULL AND ch.cas_number != ''
      AND (ch.hazard_statements IS NULL OR ch.hazard_statements IN ('[]','','null'))
    GROUP BY ch.id
    ORDER BY container_count DESC
    LIMIT 30
");
echo "--- Most-used chemicals with CAS but no GHS (top 30) ---\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "id={$r['id']} cas={$r['cas_number']} containers={$r['container_count']} name={$r['name']}\n";
}

// Also count by category (from name patterns)
echo "\n--- Total stats ---\n";
$r2 = $pdo->query("
    SELECT COUNT(DISTINCT ch.id) as n
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
    WHERE ch.cas_number IS NOT NULL AND ch.cas_number != ''
      AND (ch.hazard_statements IS NULL OR ch.hazard_statements IN ('[]','','null'))
");
echo "Chemicals with CAS needing update: " . $r2->fetch()['n'] . "\n";

// How many of those have CAS that look like standard format (not mixture CAS)
$r3 = $pdo->query("
    SELECT COUNT(DISTINCT ch.id) as n
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
    WHERE ch.cas_number REGEXP '^[0-9]+-[0-9]+-[0-9]$'
      AND (ch.hazard_statements IS NULL OR ch.hazard_statements IN ('[]','','null'))
");
echo "Chemicals with proper CAS format: " . $r3->fetch()['n'] . "\n";
