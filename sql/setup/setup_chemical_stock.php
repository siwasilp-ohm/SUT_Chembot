<?php
/**
 * Chemical Stock (ขวดสารเคมีในคลัง) - Setup & Import Script
 * 
 * Creates the chemical_stock table and imports from CSV
 * Links: chemical_stock → users (owner), chemicals (CAS lookup)
 * 
 * Role-based visibility:
 *   admin    → sees ALL, full CRUD, import/export
 *   ceo      → sees ALL (read + export)
 *   lab_manager → sees own + team's stock
 *   user     → sees/manages own stock only
 */

require_once __DIR__ . '/../includes/database.php';

$pdo = Database::getInstance();

echo "═══════════════════════════════════════════\n";
echo " Chemical Stock — Setup & Import\n";
echo "═══════════════════════════════════════════\n\n";

// ── Step 1: Create Table ──
echo "[1/4] Creating chemical_stock table...\n";

$pdo->exec("DROP TABLE IF EXISTS chemical_stock");

$pdo->exec("
CREATE TABLE chemical_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bottle_code VARCHAR(50) NOT NULL COMMENT 'รหัสขวด e.g. 320F6600000001',
    chemical_name VARCHAR(500) NOT NULL COMMENT 'ชื่อสารเคมี',
    cas_no VARCHAR(100) DEFAULT NULL COMMENT 'CAS / Catalogue No.',
    grade VARCHAR(500) DEFAULT NULL COMMENT 'เกรด',
    package_size DECIMAL(14,4) DEFAULT NULL COMMENT 'ขนาดบรรจุ',
    remaining_qty DECIMAL(14,4) DEFAULT NULL COMMENT 'ปริมาณคงเหลือ',
    unit VARCHAR(50) DEFAULT NULL COMMENT 'หน่วยบรรจุ',
    
    -- Owner linkage
    owner_name VARCHAR(255) DEFAULT NULL COMMENT 'ชื่อผู้เพิ่มขวด (original text)',
    owner_user_id INT DEFAULT NULL COMMENT 'FK → users.id (resolved)',
    
    -- Location
    storage_location VARCHAR(255) DEFAULT NULL COMMENT 'สถานที่จัดเก็บ',
    
    -- Chemical linkage
    chemical_id INT DEFAULT NULL COMMENT 'FK → chemicals.id (resolved via CAS)',
    
    -- Status
    status ENUM('active','low','empty','expired','disposed') DEFAULT 'active',
    
    -- Timestamps
    added_at DATETIME DEFAULT NULL COMMENT 'เวลาเพิ่มขวด (from CSV)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'who imported/created this record',
    
    -- Percentage
    remaining_pct DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE WHEN package_size > 0 THEN (remaining_qty / package_size) * 100 ELSE 0 END
    ) STORED,
    
    -- Indexes
    INDEX idx_bottle_code (bottle_code),
    INDEX idx_cas_no (cas_no),
    INDEX idx_chemical_name (chemical_name(100)),
    INDEX idx_owner_user_id (owner_user_id),
    INDEX idx_chemical_id (chemical_id),
    INDEX idx_status (status),
    INDEX idx_unit (unit),
    INDEX idx_storage_location (storage_location),
    INDEX idx_added_at (added_at),
    
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ขวดสารเคมีที่มีอยู่ในคลัง - Chemical Bottle Stock';
");

echo "   ✅ Table created\n\n";

// ── Step 2: Build user name → ID map ──
echo "[2/4] Building owner name map...\n";

$users = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as fullname FROM users")->fetchAll(PDO::FETCH_ASSOC);
$nameMap = [];
foreach ($users as $u) {
    // Map without prefix (นาย, นาง, นางสาว, ดร.)
    $clean = preg_replace('/^(นาย|นางสาว|นาง|ดร\\.)\s*/u', '', $u['fullname']);
    $nameMap[mb_strtolower($clean, 'UTF-8')] = $u['id'];
    $nameMap[mb_strtolower($u['fullname'], 'UTF-8')] = $u['id'];
}
echo "   ✅ Mapped " . count($users) . " users\n\n";

// ── Step 3: Build CAS → chemical_id map ──
echo "[3/4] Building CAS → chemical_id map...\n";

$chemicals = $pdo->query("SELECT id, cas_number FROM chemicals WHERE cas_number IS NOT NULL AND cas_number != ''")->fetchAll(PDO::FETCH_ASSOC);
$casMap = [];
foreach ($chemicals as $c) {
    $casMap[trim($c['cas_number'])] = $c['id'];
}
echo "   ✅ Mapped " . count($casMap) . " CAS numbers\n\n";

// ── Step 4: Import CSV ──
echo "[4/4] Importing CSV data...\n";

$csvPath = __DIR__ . '/../data/6.สารเคมีที่มีอยู่ในคลังฯ.csv';
if (!file_exists($csvPath)) {
    die("   ❌ CSV file not found: $csvPath\n");
}

$f = fopen($csvPath, 'r');
$row = 0;
$imported = 0;
$skipped = 0;
$ownerMatched = 0;
$casMatched = 0;

$stmt = $pdo->prepare("
    INSERT INTO chemical_stock 
        (bottle_code, chemical_name, cas_no, grade, package_size, remaining_qty, unit,
         owner_name, owner_user_id, storage_location, chemical_id, added_at, created_by, status)
    VALUES
        (:bottle_code, :chemical_name, :cas_no, :grade, :package_size, :remaining_qty, :unit,
         :owner_name, :owner_user_id, :storage_location, :chemical_id, :added_at, :created_by, :status)
");

$pdo->beginTransaction();

while (($line = fgets($f)) !== false) {
    $row++;
    if ($row <= 2) continue; // Skip header rows
    
    $cols = str_getcsv($line);
    $bottleCode = trim($cols[0] ?? '');
    
    if (empty($bottleCode) || $bottleCode === 'รหัสขวด') {
        $skipped++;
        continue;
    }
    
    $chemName = trim($cols[1] ?? '');
    $casNo = trim($cols[2] ?? '');
    $grade = trim($cols[3] ?? '');
    $packageSize = str_replace(',', '', trim($cols[4] ?? ''));
    $remainingQty = str_replace(',', '', trim($cols[5] ?? ''));
    $unit = trim($cols[6] ?? '');
    $ownerName = trim($cols[7] ?? '');
    $addedTime = trim($cols[8] ?? '');
    $storageLocation = trim($cols[32] ?? '');
    
    // Resolve owner
    $ownerClean = preg_replace('/^(นาย|นางสาว|นาง|ดร\\.)\s*/u', '', $ownerName);
    $ownerId = $nameMap[mb_strtolower($ownerClean, 'UTF-8')] ?? $nameMap[mb_strtolower($ownerName, 'UTF-8')] ?? null;
    if ($ownerId) $ownerMatched++;
    
    // Resolve CAS
    $chemId = $casMap[$casNo] ?? null;
    if ($chemId) $casMatched++;
    
    // Parse date: "27/3/2023 21:45" → "2023-03-27 21:45:00"
    $addedAt = null;
    if (!empty($addedTime)) {
        $parts = preg_split('/[\s\/]/', $addedTime);
        if (count($parts) >= 3) {
            $d = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $y = $parts[2];
            $t = $parts[3] ?? '00:00';
            $addedAt = "$y-$m-$d $t:00";
        }
    }
    
    // Parse numbers
    $pkgSize = is_numeric($packageSize) ? floatval($packageSize) : null;
    $remQty = is_numeric($remainingQty) ? floatval($remainingQty) : null;
    
    // Determine status
    $status = 'active';
    if ($pkgSize !== null && $remQty !== null) {
        if ($remQty <= 0) $status = 'empty';
        elseif ($pkgSize > 0 && ($remQty / $pkgSize) < 0.1) $status = 'low';
    }
    
    $stmt->execute([
        ':bottle_code' => $bottleCode,
        ':chemical_name' => $chemName,
        ':cas_no' => $casNo ?: null,
        ':grade' => $grade ?: null,
        ':package_size' => $pkgSize,
        ':remaining_qty' => $remQty,
        ':unit' => $unit ?: null,
        ':owner_name' => $ownerName ?: null,
        ':owner_user_id' => $ownerId,
        ':storage_location' => $storageLocation ?: null,
        ':chemical_id' => $chemId,
        ':added_at' => $addedAt,
        ':created_by' => 1, // admin1
        ':status' => $status
    ]);
    
    $imported++;
    
    if ($imported % 500 === 0) {
        echo "   ... imported $imported rows\n";
    }
}

$pdo->commit();
fclose($f);

echo "\n   ✅ Import complete!\n";
echo "   ────────────────────────────\n";
echo "   Total rows processed: " . ($row - 2) . "\n";
echo "   Imported: $imported\n";
echo "   Skipped: $skipped\n";
echo "   Owner matched: $ownerMatched / $imported (" . round($ownerMatched / max($imported, 1) * 100, 1) . "%)\n";
echo "   CAS matched: $casMatched / $imported (" . round($casMatched / max($imported, 1) * 100, 1) . "%)\n";

// ── Verification ──
echo "\n═══ Verification ═══\n";
$total = $pdo->query("SELECT COUNT(*) FROM chemical_stock")->fetchColumn();
$withOwner = $pdo->query("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id IS NOT NULL")->fetchColumn();
$withChem = $pdo->query("SELECT COUNT(*) FROM chemical_stock WHERE chemical_id IS NOT NULL")->fetchColumn();
$byStatus = $pdo->query("SELECT status, COUNT(*) as cnt FROM chemical_stock GROUP BY status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
$byUnit = $pdo->query("SELECT unit, COUNT(*) as cnt FROM chemical_stock GROUP BY unit ORDER BY cnt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

echo "Total records: $total\n";
echo "With owner linked: $withOwner\n";
echo "With chemical linked: $withChem\n";
echo "\nBy Status:\n";
foreach ($byStatus as $s) echo "  {$s['status']}: {$s['cnt']}\n";
echo "\nBy Unit (top 10):\n";
foreach ($byUnit as $u) echo "  {$u['unit']}: {$u['cnt']}\n";

echo "\n✅ All done!\n";
