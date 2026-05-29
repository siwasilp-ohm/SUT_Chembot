<?php
/**
 * Create new tables for SDS/GHS/Files system + Import CSV chemical master data
 * Adds: chemical_sds_files, chemical_ghs_data
 * Imports: ~7,392 chemicals from CSV
 */

require_once __DIR__ . '/../includes/database.php';

$pdo = Database::getInstance();
echo "=== Chemical Master Data Setup ===\n\n";

// ─────── 1. Create chemical_sds_files table ───────
echo "1. Creating chemical_sds_files table...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS chemical_sds_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL,
    file_type ENUM('sds','datasheet','msds','certificate','other') DEFAULT 'sds',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_url VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    language VARCHAR(10) DEFAULT 'en',
    version VARCHAR(50),
    issue_date DATE,
    expiry_date DATE,
    uploaded_by INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_chem (chemical_id),
    INDEX idx_type (file_type),
    INDEX idx_uploader (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "   ✓ chemical_sds_files created\n";

// ─────── 2. Create chemical_ghs_data table ───────
echo "2. Creating chemical_ghs_data table...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS chemical_ghs_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL UNIQUE,
    
    -- GHS Classification
    ghs_pictograms JSON COMMENT 'Array: GHS01-GHS09',
    signal_word ENUM('Danger','Warning','None') DEFAULT 'None',
    
    -- Hazard Statements (H-codes)
    h_statements JSON COMMENT 'Array of H-codes like H200,H301',
    h_statements_text TEXT COMMENT 'Full text of H statements',
    
    -- Precautionary Statements (P-codes) 
    p_statements JSON COMMENT 'Array of P-codes',
    p_statements_text TEXT COMMENT 'Full text of P statements',
    
    -- UN/Transport Classification
    un_number VARCHAR(10),
    un_proper_shipping_name VARCHAR(255),
    transport_hazard_class VARCHAR(50),
    packing_group VARCHAR(10),
    
    -- Safety Summary
    safety_summary TEXT,
    handling_precautions TEXT,
    storage_instructions TEXT,
    disposal_instructions TEXT,
    
    -- First Aid
    first_aid_inhalation TEXT,
    first_aid_skin TEXT,
    first_aid_eye TEXT,
    first_aid_ingestion TEXT,
    
    -- Fire Fighting
    suitable_extinguishing TEXT,
    unsuitable_extinguishing TEXT,
    special_fire_hazards TEXT,
    
    -- Exposure Controls
    exposure_limits TEXT,
    engineering_controls TEXT,
    ppe_required JSON COMMENT 'PPE items array',
    
    -- Ecological / Toxicological
    ld50 VARCHAR(255),
    lc50 VARCHAR(255),
    ecotoxicity TEXT,
    
    -- Metadata
    source VARCHAR(100) COMMENT 'Source of GHS data',
    last_reviewed DATE,
    reviewed_by INT,
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_chem (chemical_id),
    INDEX idx_signal (signal_word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "   ✓ chemical_ghs_data created\n";

// ─────── 3. Add columns to chemicals if missing ───────
echo "3. Checking chemicals columns...\n";
$existingCols = [];
$cols = $pdo->query("SHOW COLUMNS FROM chemicals")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) $existingCols[] = $c;

$addCols = [
    'added_by_name' => "VARCHAR(255) AFTER created_by",
    'date_added' => "DATE AFTER added_by_name",
];
foreach ($addCols as $col => $def) {
    if (!in_array($col, $existingCols)) {
        $pdo->exec("ALTER TABLE chemicals ADD COLUMN `$col` $def");
        echo "   + Added column: $col\n";
    } else {
        echo "   ✓ Column exists: $col\n";
    }
}

// ─────── 4. Import CSV chemicals ───────
echo "\n4. Importing CSV chemical master data...\n";

$file = glob(__DIR__ . '/../data/2.*')[0];
$f = fopen($file, 'r');
$bom = fread($f, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($f);

fgetcsv($f); // Skip title row
fgetcsv($f); // Skip header row

// Prepare statements
$checkStmt = $pdo->prepare("SELECT id FROM chemicals WHERE name = :name AND COALESCE(cas_number,'') = :cas");
$insertStmt = $pdo->prepare("
    INSERT INTO chemicals (name, cas_number, manufacturer_id, catalogue_number, physical_state, 
                           substance_type, substance_category, added_by_name, date_added, created_by, is_active, verified)
    VALUES (:name, :cas, :mfr_id, :catalogue, :state, :type, :category, :added_by, :date_added, 1, 1, 0)
");

// Cache manufacturer IDs
$manufacturers = [];
$mfrs = $pdo->query("SELECT id, name FROM manufacturers")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($mfrs as $id => $name) $manufacturers[mb_strtolower($name)] = $id;

$imported = 0;
$skipped = 0;
$errors = 0;
$newMfrs = 0;

$pdo->beginTransaction();
try {
    while (($row = fgetcsv($f)) !== false) {
        $name = trim($row[0] ?? '');
        $cas = trim($row[1] ?? '');
        $manufacturer = trim($row[2] ?? '');
        $catalogue = trim($row[3] ?? '');
        $state = trim($row[4] ?? '');
        $type = trim($row[5] ?? '');
        $category = trim($row[6] ?? '');
        $addedBy = trim($row[7] ?? '');
        $dateRaw = trim($row[8] ?? '');

        if (empty($name) || $name === 'ชื่อ') continue;

        // Check duplicate
        $checkStmt->execute([':name' => $name, ':cas' => $cas ?: '']);
        if ($checkStmt->fetch()) { $skipped++; continue; }

        // Map state
        $stateMap = ['solid' => 'solid', 'liquid' => 'liquid', 'gas' => 'gas'];
        $dbState = $stateMap[strtolower($state)] ?? 'solid';

        // Get/create manufacturer
        $mfrId = null;
        if (!empty($manufacturer)) {
            $mfrKey = mb_strtolower($manufacturer);
            if (isset($manufacturers[$mfrKey])) {
                $mfrId = $manufacturers[$mfrKey];
            } else {
                try {
                    $pdo->prepare("INSERT INTO manufacturers (name) VALUES (:name)")->execute([':name' => $manufacturer]);
                    $mfrId = (int)$pdo->lastInsertId();
                } catch (Exception $me) {
                    // Already exists (race or encoding variant)
                    $existing = $pdo->prepare("SELECT id FROM manufacturers WHERE name = :name");
                    $existing->execute([':name' => $manufacturer]);
                    $row2 = $existing->fetch();
                    $mfrId = $row2 ? (int)$row2['id'] : null;
                }
                if ($mfrId) {
                    $manufacturers[$mfrKey] = $mfrId;
                    $newMfrs++;
                }
            }
        }

        // Parse date (Excel serial number)
        $dateAdded = null;
        if (!empty($dateRaw) && is_numeric($dateRaw)) {
            $dateAdded = date('Y-m-d', mktime(0, 0, 0, 1, (int)$dateRaw - 1, 1900));
        }

        try {
            $insertStmt->execute([
                ':name' => $name,
                ':cas' => $cas ?: null,
                ':mfr_id' => $mfrId,
                ':catalogue' => $catalogue ?: null,
                ':state' => $dbState,
                ':type' => $type ?: null,
                ':category' => $category ?: null,
                ':added_by' => $addedBy ?: null,
                ':date_added' => $dateAdded,
            ]);
            $imported++;
        } catch (Exception $e) {
            $errors++;
            if ($errors <= 5) echo "   ! Error: {$e->getMessage()} (name: $name)\n";
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    echo "   FATAL: {$e->getMessage()}\n";
    exit(1);
}
fclose($f);

echo "   ✓ Imported: $imported\n";
echo "   ✗ Skipped (duplicate): $skipped\n";
echo "   ! Errors: $errors\n";
echo "   + New manufacturers: $newMfrs\n";

$totalChem = $pdo->query("SELECT COUNT(*) FROM chemicals")->fetchColumn();
$totalMfr = $pdo->query("SELECT COUNT(*) FROM manufacturers")->fetchColumn();
echo "\n=== SUMMARY ===\n";
echo "Total chemicals in DB: $totalChem\n";
echo "Total manufacturers in DB: $totalMfr\n";

// Show state distribution
echo "\nState distribution:\n";
$states = $pdo->query("SELECT physical_state, COUNT(*) as c FROM chemicals GROUP BY physical_state ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($states as $s) echo "  {$s['physical_state']}: {$s['c']}\n";

// Show type distribution
echo "\nSubstance type distribution:\n";
$types = $pdo->query("SELECT COALESCE(substance_type,'(none)') as t, COUNT(*) as c FROM chemicals GROUP BY substance_type ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($types as $t) echo "  {$t['t']}: {$t['c']}\n";

echo "\n✅ Setup complete!\n";
