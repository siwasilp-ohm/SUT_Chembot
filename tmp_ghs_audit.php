<?php
$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// All chemicals that appear in containers, missing GHS data
$stmt = $pdo->query("
    SELECT DISTINCT ch.id, ch.cas_number, ch.name, ch.physical_state,
           ch.hazard_statements, ch.hazard_pictograms,
           ghs.id AS ghs_id, ghs.ghs_pictograms, ghs.h_statements_text
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id = ch.id AND ct.is_active=1 AND ct.current_quantity>0
    LEFT JOIN chemical_ghs_data ghs ON ghs.chemical_id = ch.id
    ORDER BY ch.id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$hasCas = 0; $noGhsAtAll = 0; $hasHstmt = 0; $hasGhsRecord = 0;
foreach ($rows as $r) {
    if ($r['cas_number']) $hasCas++;
    if ($r['ghs_id']) $hasGhsRecord++;
    if ($r['h_statements_text'] && strlen($r['h_statements_text']) > 5) $hasHstmt++;
    $noData = !$r['hazard_statements'] || $r['hazard_statements'] === '[]';
    if ($noData && !$r['ghs_id']) $noGhsAtAll++;
}

echo "Total distinct chemicals in containers: $total\n";
echo "Have CAS number: $hasCas\n";
echo "Have GHS record (chemical_ghs_data): $hasGhsRecord\n";
echo "Have h_statements_text: $hasHstmt\n";
echo "No GHS data at all: $noGhsAtAll\n\n";

// Sample some with CAS numbers but no GHS
$stmt2 = $pdo->query("
    SELECT DISTINCT ch.id, ch.cas_number, ch.name
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
    LEFT JOIN chemical_ghs_data ghs ON ghs.chemical_id=ch.id
    WHERE ch.cas_number IS NOT NULL AND ch.cas_number != ''
      AND (ch.hazard_statements IS NULL OR ch.hazard_statements = '[]' OR ch.hazard_statements = '')
      AND ghs.id IS NULL
    ORDER BY ch.id
    LIMIT 20
");
echo "--- Chemicals with CAS but no GHS (first 20) ---\n";
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "id={$r['id']} cas={$r['cas_number']} name={$r['name']}\n";
}

// Count unique chemicals needing update
$stmt3 = $pdo->query("
    SELECT COUNT(DISTINCT ch.id) as n
    FROM chemicals ch
    JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
    LEFT JOIN chemical_ghs_data ghs ON ghs.chemical_id=ch.id
    WHERE ch.cas_number IS NOT NULL AND ch.cas_number != ''
      AND (ch.hazard_statements IS NULL OR ch.hazard_statements IN ('[]','','null'))
      AND ghs.id IS NULL
");
echo "\nChemicals with CAS, no GHS at all: " . $stmt3->fetch()['n'] . "\n";
