<?php
/**
 * Generate demo packaging data — 3 sizes per chemical
 * Run: php sql/seed_packaging.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "=== Seed Chemical Packaging Demo Data ===\n";

// Clear existing demo data
Database::delete('chemical_packaging', '1=1', []);
echo "Cleared existing packaging data.\n";

// Packaging templates by physical state
$templates = [
    'solid' => [
        ['container_type'=>'bottle','material'=>'plastic','capacity'=>25,'unit'=>'g','label'=>'ขวดพลาสติก 25 g','icon'=>'bottle_plastic_25g.jpg'],
        ['container_type'=>'bottle','material'=>'glass','capacity'=>100,'unit'=>'g','label'=>'ขวดแก้ว 100 g','icon'=>'bottle_glass_100g.jpg'],
        ['container_type'=>'bottle','material'=>'plastic','capacity'=>500,'unit'=>'g','label'=>'ขวดพลาสติก 500 g','icon'=>'bottle_plastic_500g.jpg'],
    ],
    'liquid' => [
        ['container_type'=>'bottle','material'=>'glass','capacity'=>500,'unit'=>'mL','label'=>'ขวดแก้ว 500 mL','icon'=>'bottle_glass_500ml.jpg'],
        ['container_type'=>'bottle','material'=>'amber_glass','capacity'=>2.5,'unit'=>'L','label'=>'ขวดแก้วสีชา 2.5 L','icon'=>'bottle_amber_2500ml.jpg'],
        ['container_type'=>'gallon','material'=>'hdpe','capacity'=>10,'unit'=>'L','label'=>'แกลลอน HDPE 10 L','icon'=>'gallon_hdpe_10l.jpg'],
    ],
    'gas' => [
        ['container_type'=>'cylinder','material'=>'metal','capacity'=>5,'unit'=>'L','label'=>'ถังแก๊สขนาดเล็ก 5 L','icon'=>'cylinder_5l.jpg'],
        ['container_type'=>'cylinder','material'=>'metal','capacity'=>10,'unit'=>'L','label'=>'ถังแก๊สขนาดกลาง 10 L','icon'=>'cylinder_10l.jpg'],
        ['container_type'=>'cylinder','material'=>'metal','capacity'=>50,'unit'=>'L','label'=>'ถังแก๊สขนาดใหญ่ 50 L','icon'=>'cylinder_50l.jpg'],
    ],
];

// Some common supplier names
$suppliers = ['Sigma-Aldrich', 'Merck', 'TCI', 'Alfa Aesar', 'Fisher Scientific', 'Fluka', 'Acros Organics', 'FUJIFILM Wako'];

// Demo image URLs (using placeholder approach from Unsplash-like generic images)
$imageUrls = [
    'solid' => [
        'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1616711892671-30ea7f01b127?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1583912268183-bfce2ec44d60?w=300&h=300&fit=crop',
    ],
    'liquid' => [
        'https://images.unsplash.com/photo-1603126857599-f6e157fa2fe6?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1585435557343-3b092031a831?w=300&h=300&fit=crop',
    ],
    'gas' => [
        'https://images.unsplash.com/photo-1612690669207-fed642192c40?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1612690669207-fed642192c40?w=300&h=300&fit=crop',
        'https://images.unsplash.com/photo-1612690669207-fed642192c40?w=300&h=300&fit=crop',
    ],
];

// Price ranges
$priceRanges = [
    'solid' => [[80,250],[300,800],[600,2500]],
    'liquid' => [[150,500],[400,1500],[800,4000]],
    'gas' => [[1200,3000],[2500,6000],[5000,15000]],
];

// Get all chemicals
$chemicals = Database::fetchAll("SELECT id, cas_number, physical_state, catalogue_number FROM chemicals WHERE is_active = 1 ORDER BY id");
$total = count($chemicals);
echo "Found {$total} chemicals.\n";

$pdo = Database::getInstance();
$batchSize = 500;
$inserted = 0;
$batch = [];

$sql = "INSERT INTO chemical_packaging 
    (chemical_id, container_type, container_material, capacity, capacity_unit, label, description, image_url, supplier_name, catalogue_number, unit_price, currency, is_default, sort_order, created_by, is_active) 
    VALUES ";

foreach ($chemicals as $idx => $chem) {
    $state = $chem['physical_state'] ?: 'solid';
    if (!isset($templates[$state])) $state = 'solid';
    
    $tpls = $templates[$state];
    $imgs = $imageUrls[$state];
    $prices = $priceRanges[$state];
    $supplier = $suppliers[array_rand($suppliers)];
    $catBase = $chem['catalogue_number'] ?: ('CAT-' . $chem['id']);

    foreach ($tpls as $i => $t) {
        $price = rand($prices[$i][0], $prices[$i][1]);
        $isDefault = ($i === 0) ? 1 : 0;
        $desc = "{$t['label']} สำหรับจัดเก็บสารเคมี";
        $catNum = $catBase . '-' . strtoupper(substr($t['container_type'],0,3)) . '-' . ($i+1);
        $imgUrl = $imgs[$i];

        $batch[] = sprintf(
            "(%d, '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', %s, 'THB', %d, %d, 1, 1)",
            $chem['id'],
            $t['container_type'],
            $t['material'],
            $t['capacity'],
            $t['unit'],
            addslashes($t['label']),
            addslashes($desc),
            addslashes($imgUrl),
            addslashes($supplier),
            addslashes($catNum),
            $price,
            $isDefault,
            $i
        );
        $inserted++;
    }

    // Flush batch
    if (count($batch) >= $batchSize) {
        $pdo->exec($sql . implode(',', $batch));
        $batch = [];
        $done = $idx + 1;
        $pct = round($done / $total * 100);
        echo "\r  Progress: {$done}/{$total} ({$pct}%) — {$inserted} rows inserted";
    }
}

// Final flush
if (!empty($batch)) {
    $pdo->exec($sql . implode(',', $batch));
}

echo "\n\n✅ Done! Inserted {$inserted} packaging records for {$total} chemicals (3 per chemical).\n";

// Quick summary
$summary = Database::fetchAll("
    SELECT container_type, COUNT(*) as cnt, 
           COUNT(DISTINCT chemical_id) as chems
    FROM chemical_packaging 
    WHERE is_active = 1 
    GROUP BY container_type 
    ORDER BY cnt DESC
");
echo "\n--- Summary ---\n";
foreach ($summary as $row) {
    printf("  %-12s : %5d records (%d chemicals)\n", $row['container_type'], $row['cnt'], $row['chems']);
}
echo "\n";
