<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

$csvPath = __DIR__ . '/../data/7.คลัง.csv';
$handle = fopen($csvPath, 'r');
if (!$handle) { echo 'Cannot open CSV'; exit(1); }

Database::query('DELETE FROM lab_stores WHERE 1=1');

$inserted = 0; $row = 0;
while (($line = fgetcsv($handle)) !== false) {
    $row++;
    if ($row <= 3) continue;
    if (count($line) < 7) continue;
    $center  = trim($line[0]);
    $div     = trim($line[1]);
    $section = trim($line[2]);
    $store   = trim($line[3]);
    $bottles = (int)$line[4];
    $chems   = (int)$line[5];
    $weight  = (float)$line[6];
    if (!$center || !$div || !$section || !$store) continue;
    Database::insert('lab_stores', [
        'center_name'=>$center, 'division_name'=>$div, 'section_name'=>$section,
        'store_name'=>$store, 'bottle_count'=>$bottles, 'chemical_count'=>$chems,
        'total_weight_kg'=>$weight, 'is_active'=>1
    ]);
    $inserted++;
}
fclose($handle);
echo "Imported: $inserted records\n";
