<?php
/**
 * Create chemical_warehouses table and import data from 7.คลัง.csv
 * 
 * Usage: php sql/setup_warehouses.php [--dry-run]
 */

require_once __DIR__ . '/../includes/config.php';

$dryRun = in_array('--dry-run', $argv ?? []);

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   Setup Chemical Warehouses                         ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

if ($dryRun) echo "⚠  DRY RUN MODE — No changes will be made\n\n";

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo "✅ Database connected: " . DB_NAME . "\n\n";

// ─── 1. Create Table ───
echo "📋 Creating chemical_warehouses table...\n";

$createSql = "
CREATE TABLE IF NOT EXISTS chemical_warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL DEFAULT 1,
    
    -- Hierarchy links
    department_id INT COMMENT 'Link to departments (level 3 = งาน)',
    division_id INT COMMENT 'Link to departments (level 2 = ฝ่าย)',
    center_id INT COMMENT 'Link to departments (level 1 = ศูนย์)',
    building_id INT COMMENT 'Link to buildings table',
    room_id INT COMMENT 'Link to rooms table',
    
    -- Warehouse info
    name VARCHAR(500) NOT NULL COMMENT 'ชื่อคลังสารเคมี',
    code VARCHAR(50) COMMENT 'Warehouse code for quick reference',
    description TEXT,
    
    -- Source hierarchy text (from CSV, for reference)
    center_name VARCHAR(500) COMMENT 'ศูนย์ / สำนักวิชา',
    division_name VARCHAR(500) COMMENT 'ฝ่าย / สาขาวิชา',
    unit_name VARCHAR(500) COMMENT 'งาน',
    
    -- Inventory summary (snapshot from CSV, updated periodically)
    total_bottles INT DEFAULT 0 COMMENT 'จำนวนขวดสารเคมี',
    total_chemicals INT DEFAULT 0 COMMENT 'จำนวนสารเคมี (ชนิด)',
    total_weight_kg DECIMAL(12,2) DEFAULT 0 COMMENT 'ปริมาณรวม (kg)',
    
    -- Location details
    floor VARCHAR(10) COMMENT 'ชั้นที่',
    zone VARCHAR(100) COMMENT 'โซน/พื้นที่',
    gps_lat DECIMAL(10,8) COMMENT 'GPS Latitude',
    gps_lng DECIMAL(11,8) COMMENT 'GPS Longitude',
    
    -- Manager
    manager_user_id INT COMMENT 'ผู้รับผิดชอบคลัง',
    
    -- Status
    status ENUM('active','inactive','maintenance') DEFAULT 'active',
    last_audit_date DATE COMMENT 'วันที่ตรวจนับล่าสุด',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_dept (department_id),
    INDEX idx_division (division_id),
    INDEX idx_building (building_id),
    INDEX idx_status (status),
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='คลังสารเคมี — Chemical Warehouses mapped to departments and buildings';
";

if (!$dryRun) {
    $pdo->exec($createSql);
    echo "   ✓ Table created/verified\n";
} else {
    echo "   ✓ Would create table chemical_warehouses\n";
}

// ─── 2. Load lookup data ───
echo "\n📋 Loading lookup data...\n";

// Departments
$departments = [];
$stmt = $pdo->query("SELECT id, name, parent_id, level FROM departments ORDER BY level, id");
foreach ($stmt as $r) {
    $departments[$r['id']] = $r;
    $departments['name:' . $r['name'] . ':' . $r['level']] = $r;
}
echo "   ✓ Departments: " . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . " (already iterated)\n";

// Reload for proper counting
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
echo "   ✓ Departments loaded: $deptCount\n";

// Buildings
$buildings = [];
$stmt = $pdo->query("SELECT id, name, shortname FROM buildings ORDER BY id");
foreach ($stmt as $r) {
    $buildings[$r['name']] = $r;
    if ($r['shortname']) $buildings[$r['shortname']] = $r;
}
$bldCount = count($buildings);
echo "   ✓ Buildings loaded: $bldCount entries\n";

// Rooms
$rooms = [];
$stmt = $pdo->query("SELECT id, name FROM rooms WHERE name IS NOT NULL AND name != '' LIMIT 2000");
foreach ($stmt as $r) {
    $rooms[$r['name']] = $r;
}
$rmCount = count($rooms);
echo "   ✓ Rooms loaded: $rmCount entries\n";

// ─── 3. Parse CSV ───
echo "\n📄 Parsing CSV: data/7.คลัง.csv\n";

$csvPath = __DIR__ . '/../data/7.คลัง.csv';
$content = file_get_contents($csvPath);
$content = preg_replace('/^\x{FEFF}/u', '', $content);

// File is already UTF-8 (verified by BOM check)
// No encoding conversion needed

$lines = explode("\n", $content);
$dataStarted = false;
$headers = [];
$rows = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    $fields = str_getcsv($line);
    
    // Skip title rows — look for header row
    if (!$dataStarted) {
        $joined = implode('', $fields);
        if (mb_strpos($joined, 'ชื่อคลังสารเคมี') !== false) {
            $headers = array_map('trim', $fields);
            $dataStarted = true;
            continue;
        }
        continue;
    }
    
    // Skip summary row
    if (mb_strpos($fields[3] ?? '', 'รวมทั้งสิ้น') !== false) continue;
    
    // Skip empty rows
    if (empty(trim($fields[3] ?? ''))) continue;
    
    $rows[] = [
        'center'     => trim($fields[0] ?? ''),
        'division'   => trim($fields[1] ?? ''),
        'unit'       => trim($fields[2] ?? ''),
        'name'       => trim($fields[3] ?? ''),
        'bottles'    => (int) str_replace(',', '', trim($fields[4] ?? '0')),
        'chemicals'  => (int) str_replace(',', '', trim($fields[5] ?? '0')),
        'weight_kg'  => (float) str_replace(',', '', trim($fields[6] ?? '0')),
    ];
}

echo "   ✓ Parsed " . count($rows) . " warehouse entries\n";
echo "   Headers: " . implode(' | ', $headers) . "\n";

// ─── 4. Match and Import ───
echo "\n" . str_repeat('─', 100) . "\n";
echo str_pad('#', 4) . str_pad('Warehouse Name', 45) . str_pad('Dept', 8) . str_pad('Div', 8) . str_pad('Bld', 8) . str_pad('Bottles', 8) . str_pad('Chems', 7) . str_pad('kg', 10) . "Status\n";
echo str_repeat('─', 100) . "\n";

// Helper: find department ID by name and level
function findDeptByName($name, $level, $pdo) {
    static $cache = [];
    $key = "$name:$level";
    if (isset($cache[$key])) return $cache[$key];
    
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = :n AND level = :l");
    $stmt->execute([':n' => $name, ':l' => $level]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $cache[$key] = $r ? (int)$r['id'] : null;
    
    // Fuzzy match
    if (!$cache[$key]) {
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE level = :l AND (name LIKE :p1 OR :n2 LIKE CONCAT('%', name, '%'))");
        $stmt->execute([':l' => $level, ':p1' => "%$name%", ':n2' => $name]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$key] = $r ? (int)$r['id'] : null;
    }
    
    return $cache[$key];
}

// Helper: find building by warehouse name (extract room code like F02215A)
function findBuildingByName($whName, $buildings) {
    // Try to extract room code pattern (e.g., F02215A, F04110A)
    if (preg_match('/[F]\d{2,5}[A-Z]?/i', $whName, $m)) {
        $code = strtoupper($m[0]);
        // Building is first 3 chars of room code (F01 = building 1, F02 = building 2)
        $bldCode = substr($code, 0, 3);
        $bldNum = (int) substr($bldCode, 1);
        foreach ($buildings as $name => $b) {
            if (isset($b['shortname']) && $b['shortname'] === "F$bldNum") return $b;
            if (stripos($b['name'] ?? '', "เครื่องมือ $bldNum") !== false) return $b;
        }
    }
    
    // Try matching building name directly
    foreach ($buildings as $name => $b) {
        if (is_string($name) && mb_strpos($whName, $name) !== false) return $b;
    }
    
    return null;
}

$stats = ['inserted' => 0, 'errors' => 0];
$insertStmt = null;
if (!$dryRun) {
    $insertStmt = $pdo->prepare("
        INSERT INTO chemical_warehouses 
        (organization_id, department_id, division_id, center_id, building_id,
         name, center_name, division_name, unit_name,
         total_bottles, total_chemicals, total_weight_kg, status)
        VALUES (1, :dept_id, :div_id, :center_id, :bld_id,
                :name, :center, :division, :unit,
                :bottles, :chems, :weight, 'active')
    ");
}

foreach ($rows as $i => $row) {
    // Find dept IDs
    $centerId = findDeptByName($row['center'], 1, $pdo);
    $divId    = findDeptByName($row['division'], 2, $pdo);
    $unitId   = findDeptByName($row['unit'], 3, $pdo);
    
    // Find building
    $building = findBuildingByName($row['name'], $buildings);
    $bldId = $building ? (int)$building['id'] : null;
    
    $deptStr = $unitId ? "#$unitId" : ($divId ? "~$divId" : '-');
    $divStr  = $divId ? "#$divId" : '-';
    $bldStr  = $bldId ? "#$bldId" : '-';
    
    $status = '✅';
    if (!$dryRun) {
        try {
            $insertStmt->execute([
                ':dept_id'  => $unitId,
                ':div_id'   => $divId,
                ':center_id'=> $centerId,
                ':bld_id'   => $bldId,
                ':name'     => $row['name'],
                ':center'   => $row['center'],
                ':division' => $row['division'],
                ':unit'     => $row['unit'],
                ':bottles'  => $row['bottles'],
                ':chems'    => $row['chemicals'],
                ':weight'   => $row['weight_kg'],
            ]);
            $stats['inserted']++;
        } catch (Exception $e) {
            $status = '❌ ' . $e->getMessage();
            $stats['errors']++;
        }
    } else {
        $stats['inserted']++;
    }
    
    $nameShort = mb_substr($row['name'], 0, 28, 'UTF-8');
    echo str_pad($i + 1, 4)
       . str_pad($nameShort, 45)
       . str_pad($deptStr, 8)
       . str_pad($divStr, 8)
       . str_pad($bldStr, 8)
       . str_pad(number_format($row['bottles']), 8)
       . str_pad(number_format($row['chemicals']), 7)
       . str_pad(number_format($row['weight_kg'], 2), 10)
       . $status . "\n";
}

echo str_repeat('─', 100) . "\n";
echo "\n╔══════════════════════════════════════╗\n";
echo "║  Summary                            ║\n";
echo "╠══════════════════════════════════════╣\n";
echo "║  ✅ " . ($dryRun ? 'Would insert' : 'Inserted') . ":  " . str_pad($stats['inserted'], 18) . " ║\n";
echo "║  ❌ Errors:     " . str_pad($stats['errors'], 18) . " ║\n";
echo "║  📊 Total:      " . str_pad(count($rows), 18) . " ║\n";
echo "╚══════════════════════════════════════╝\n";
