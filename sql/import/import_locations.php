<?php
/**
 * Import Buildings & Rooms from CSV files
 * Imports: data/4.อาคารจัดเก็บสาร.csv and data/5.ชื่อห้อง หมายเลข ที่เก็บสาร.csv
 */

$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

echo "=== Location Data Import ===\n\n";

// ─── Step 1: Clear existing sample data ───
echo "1. Clearing existing sample data...\n";
// Remove containers referencing slots first (if any exist from sample data)
$pdo->exec("UPDATE containers SET location_slot_id = NULL WHERE location_slot_id IS NOT NULL");
$pdo->exec("DELETE FROM slots");
$pdo->exec("DELETE FROM shelves");
$pdo->exec("DELETE FROM cabinets");
$pdo->exec("DELETE FROM rooms");
$pdo->exec("DELETE FROM buildings");
// Reset auto increment
$pdo->exec("ALTER TABLE buildings AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE rooms AUTO_INCREMENT = 1");
echo "   ✓ Cleared\n\n";

// ─── Step 2: Import Buildings ───
echo "2. Importing buildings from CSV...\n";
$buildingsCsv = __DIR__ . '/../data/4.อาคารจัดเก็บสาร.csv';
$handle = fopen($buildingsCsv, 'r');
if (!$handle) die("Cannot open buildings CSV\n");

// Read header
$header = fgetcsv($handle);
// Columns: id, name_th, name_eng, shortname

$buildingStmt = $pdo->prepare("INSERT INTO buildings (id, organization_id, name, name_en, code, shortname) VALUES (?, 1, ?, ?, ?, ?)");
$buildingCount = 0;
$buildingIdMap = []; // csv_id => db_id

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 4) continue;
    $csvId = (int)$row[0];
    $nameTh = trim($row[1]);
    $nameEn = trim($row[2]);
    $shortname = trim($row[3]);
    
    if (empty($nameTh)) continue;
    
    // Use shortname as code (e.g. F1, F2...)
    $code = $shortname;
    
    $buildingStmt->execute([$csvId, $nameTh, $nameEn ?: null, $code, $shortname]);
    $buildingIdMap[$csvId] = $csvId; // Same ID
    $buildingCount++;
}
fclose($handle);
echo "   ✓ Imported $buildingCount buildings\n\n";

// ─── Step 3: Import Rooms ───
echo "3. Importing rooms from CSV...\n";
$roomsCsv = __DIR__ . '/../data/5.ชื่อห้อง หมายเลข ที่เก็บสาร.csv';
$handle = fopen($roomsCsv, 'r');
if (!$handle) die("Cannot open rooms CSV\n");

// Read header
$header = fgetcsv($handle);
// Columns: id, ชื่อภาษาไทย, ชื่อภาษาอังกฤษ, หมายเลขห้อง, ประเภทห้อง Lab, พื้นที่, ความจุ(คน), division, unit, ชั้น, id อาคาร, ชื่ออาคาร, sid, responsibility_person, structure, status, booking, dateadd

$roomStmt = $pdo->prepare("INSERT INTO rooms (id, building_id, name, name_en, code, room_number, room_type, area_sqm, capacity_persons, floor, responsibility_person, status_text, bookable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$roomCount = 0;
$skipped = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 11) continue;
    
    $roomId = (int)$row[0];
    $nameTh = trim($row[1]);
    $nameEn = trim($row[2]);
    $roomNumber = trim($row[3]); // e.g. F01101
    $roomType = trim($row[4]);
    $areaSqm = is_numeric(str_replace(',','',$row[5])) ? (float)str_replace(',','',$row[5]) : null;
    $capacity = is_numeric(str_replace(',','',$row[6])) ? (int)str_replace(',','',$row[6]) : null;
    // $row[7] = division, $row[8] = unit (usually NULL)
    $floor = is_numeric($row[9]) ? (int)$row[9] : 1;
    $buildingId = (int)$row[10];
    // $row[11] = building name (skip, we already have it)
    // $row[12] = sid (NULL)
    $responsibilityPerson = ($row[13] ?? '') !== 'NULL' ? trim($row[13] ?? '') : null;
    // $row[14] = structure (NULL)
    $statusText = trim($row[15] ?? '');
    if ($statusText === 'NULL' || empty($statusText)) $statusText = null;
    $bookable = isset($row[16]) && $row[16] !== 'NULL' ? 1 : 0;
    
    // Check building exists
    if ($buildingId <= 0) {
        $skipped++;
        continue;
    }
    
    // Use room display name - fallback to room_type if name is empty
    if (empty($nameTh) && !empty($roomType)) $nameTh = $roomType;
    if (empty($nameTh) && !empty($roomNumber)) $nameTh = $roomNumber;
    if (empty($nameTh)) {
        $nameTh = 'ห้อง ' . $roomId;
    }
    
    // Clean up name_en 
    if ($nameEn === '-' || $nameEn === 'NULL') $nameEn = null;
    if ($responsibilityPerson === '' || $responsibilityPerson === '-') $responsibilityPerson = null;
    
    try {
        $roomStmt->execute([
            $roomId,
            $buildingId,
            $nameTh,
            $nameEn ?: null,
            $roomNumber ?: null,   // code = room_number (e.g. F01101)
            $roomNumber ?: null,   // room_number
            $roomType ?: null,
            $areaSqm,
            $capacity,
            $floor,
            $responsibilityPerson,
            $statusText,
            $bookable
        ]);
        $roomCount++;
    } catch (PDOException $e) {
        // Duplicate key or FK error - skip
        echo "   ⚠ Room $roomId skipped: " . $e->getMessage() . "\n";
        $skipped++;
    }
}
fclose($handle);
echo "   ✓ Imported $roomCount rooms (skipped: $skipped)\n\n";

// ─── Step 4: Verify ───
echo "4. Verification:\n";
$bCount = $pdo->query("SELECT COUNT(*) FROM buildings")->fetchColumn();
$rCount = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
echo "   Buildings: $bCount\n";
echo "   Rooms: $rCount\n";

// Show building summary
echo "\n5. Building Summary:\n";
$summary = $pdo->query("
    SELECT b.id, b.shortname, b.name, b.name_en, COUNT(r.id) as room_count
    FROM buildings b
    LEFT JOIN rooms r ON r.building_id = b.id
    GROUP BY b.id
    ORDER BY b.id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($summary as $s) {
    echo "   [{$s['shortname']}] {$s['name']} ({$s['name_en']}) — {$s['room_count']} rooms\n";
}

// Show floor summary for each building
echo "\n6. Floor distribution:\n";
$floors = $pdo->query("
    SELECT b.shortname, r.floor, COUNT(r.id) as cnt
    FROM rooms r
    JOIN buildings b ON r.building_id = b.id
    GROUP BY b.id, r.floor
    ORDER BY b.id, r.floor
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($floors as $f) {
    echo "   {$f['shortname']} ชั้น {$f['floor']}: {$f['cnt']} rooms\n";
}

echo "\n=== Import Complete! ===\n";
