<?php
/**
 * Demo Container Seeder — Barcode Mapping
 * =========================================
 * Barcode format: F11208A6400004
 *   F11   = Building shortname (e.g. F11 = อาคารเครื่องมือ 11)
 *   208A  = Room number suffix (combined → F11208A)
 *   64    = BE year imported (2564 → received_date 2021)
 *   00004 = Bottle sequence number 4
 *
 * Logic:
 *  1. Parse every barcode from CSV data/6.สารเคมีที่มีอยู่ในคลังฯ.csv
 *  2. Extract building code + room number from barcode
 *  3. Look up building_id and room_id in DB
 *  4. Insert container row, location = room level (no cabinet/shelf/slot)
 *  5. If building/room cannot be found → assign to Center Store (building_id=23, room F0)
 *
 * Run:  php sql/import/seed_demo_containers.php
 *       (outputs to sql/import/seed_containers_YYYYMMDD.sql)
 */

require_once __DIR__ . '/../../includes/database.php';

set_time_limit(300);
ini_set('memory_limit', '256M');

// ── helpers ──────────────────────────────────────────────────────────────────

function parseBarcodeLocation(string $barcode): array {
    /**
     * Try to split barcode into [buildingCode, roomSuffix, yearBE2, seqPart]
     *
     * Pattern A — NEW style: F11208A6400004
     *   Building = longest match of known building prefix (F11, F10, F9, F1..F7, etc.)
     *   Room suffix = next chars up to where the 2-digit year starts
     *   Year = 2 digits (BE century)
     *   Seq  = remaining digits
     *
     * Pattern B — OLD style: 320F6600000001 (building 320F → map to Center Store)
     *   These don't follow the Fxx prefix pattern → fallback
     */

    $raw = trim($barcode);

    // ── detect building prefix ───────────────────────────────────────────────
    // Ordered longest-first to avoid F1 matching F10/F11/F12/F14/F16
    // Also handle zero-padded forms: F01, F02 … F09 → map to F1…F9
    $knownBuildings = [
        'F16', 'F14', 'F12', 'F11', 'F10',
        'F6/1',
        // Zero-padded variants (common in old barcodes like F01121A…)
        'F09', 'F07', 'F06', 'F05', 'F04', 'F03', 'F02', 'F01',
        // Non-padded
        'F9', 'F7', 'F6', 'F5', 'F4', 'F3', 'F2', 'F1',
        '320F', // animal lab → Center Store
    ];

    $buildingCode = null;
    $rest         = '';

    foreach ($knownBuildings as $bc) {
        if (stripos($raw, $bc) === 0) {
            $buildingCode = strtoupper($bc);
            $rest         = substr($raw, strlen($bc));
            break;
        }
    }

    if ($buildingCode === null) {
        // Completely unknown prefix → Center Store
        return ['building' => null, 'room_number' => null, 'year_be2' => null, 'seq' => null];
    }

    // Normalize zero-padded codes: F01→F1, F02→F2 … F09→F9
    $normalizeMap = [
        'F01'=>'F1','F02'=>'F2','F03'=>'F3','F04'=>'F4',
        'F05'=>'F5','F06'=>'F6','F07'=>'F7','F09'=>'F9',
    ];
    $buildingCodeNorm = $normalizeMap[$buildingCode] ?? $buildingCode;
    // Keep original raw prefix for room_number construction (rooms use F01xxx)
    $buildingCodeForRoom = $buildingCode; // e.g. F01 for room lookup

    // Map 320F → Center Store
    if ($buildingCode === '320F') {
        return ['building' => 'F0', 'room_number' => 'CENTER_STORE', 'year_be2' => null, 'seq' => null];
    }

    /**
     * Now split `rest` into [roomSuffix, year2digit, seq]
     * Room suffix: alphanumeric, ends just before a run of digits that look
     * like year (2-digit number ≥ 55 which = BE 2555 = AD 2012 — reasonable range)
     * Followed by 5+ digit sequence.
     *
     * Strategy: scan for position where we have exactly 2 digits for year
     * and 5 digits for seq (total 7 trailing digits after room suffix)
     */

    // Find position where trailing 7 digits start (year2 + seq5)
    if (preg_match('/^([A-Z0-9\-]*)(\d{2})(\d{5,})$/i', $rest, $m)) {
        $roomSuffix = strtoupper($m[1]); // e.g. "208A" or "006A" or ""
        $year2      = $m[2];             // e.g. "64"
        $seqStr     = $m[3];             // e.g. "00004"

        // Build full room_number = original building prefix + roomSuffix (e.g. F01121A, F11208A)
        $roomNumber = $buildingCodeForRoom . $roomSuffix;

        return [
            'building'    => $buildingCodeNorm,  // normalized (F1, F11, etc.)
            'room_number' => $roomNumber,         // full room code (F01121A, F11208A, etc.)
            'year_be2'    => (int)$year2,
            'seq'         => (int)$seqStr,
        ];
    }

    // Fallback — can't decode room → use Center Store
    return ['building' => $buildingCodeNorm, 'room_number' => null, 'year_be2' => null, 'seq' => null];
}

function beYear2ToAD(int $y2): int {
    // 2-digit BE suffix to AD year
    // 55→2012, 64→2021, 65→2022, 66→2023, 67→2024, 68→2025
    return (int)('25' . str_pad((string)$y2, 2, '0', STR_PAD_LEFT)) - 543;
}

function unitToEnum(string $unit): string {
    $map = [
        'มิลลิลิตร' => 'mL',  'ml'  => 'mL',  'milliliter' => 'mL',
        'ลิตร'      => 'L',   'l'   => 'L',   'liter'      => 'L',
        'กรัม'      => 'g',   'g'   => 'g',   'gram'       => 'g',
        'กิโลกรัม'  => 'kg',  'kg'  => 'kg',  'kilogram'   => 'kg',
        'มิลลิกรัม' => 'mg',  'mg'  => 'mg',  'milligram'  => 'mg',
        'ไมโครกรัม' => 'mg',
        'ไมโครลิตร' => 'mL',
        'units'     => 'units','unit'=>'units',
    ];
    $lower = mb_strtolower(trim($unit), 'UTF-8');
    foreach ($map as $k => $v) {
        if (mb_strpos($lower, $k, 0, 'UTF-8') !== false) return $v;
    }
    return 'mL';
}

function parseQty(string $raw): float {
    // Remove commas and extract first numeric value
    $clean = str_replace([',', ' '], ['', ''], $raw);
    preg_match('/[\d.]+/', $clean, $m);
    return isset($m[0]) ? (float)$m[0] : 0.0;
}

// ── Load DB maps ─────────────────────────────────────────────────────────────

$pdo = Database::getInstance();

// Building code → building_id
$buildingMap = [];
$rows = $pdo->query("SELECT id, code FROM buildings WHERE code IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $buildingMap[strtoupper(trim($r['code']))] = (int)$r['id'];
}

// Room number → room_id (some rooms have same number in different buildings → take first match, prefer by building)
$roomMap = []; // room_number_upper → [['room_id', 'building_id'], ...]
$rows = $pdo->query("SELECT id, room_number, building_id FROM rooms WHERE room_number IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $key = strtoupper(trim($r['room_number']));
    if (!isset($roomMap[$key])) $roomMap[$key] = [];
    $roomMap[$key][] = ['room_id' => (int)$r['id'], 'building_id' => (int)$r['building_id']];
}

// Center Store fallback → building_id=23 (F0), room_id = id of room F0
$centerStoreBuildingId = $buildingMap['F0'] ?? 23;
$centerStoreRoomId     = null;
foreach (($roomMap['F0'] ?? []) as $rm) {
    if ($rm['building_id'] === $centerStoreBuildingId) {
        $centerStoreRoomId = $rm['room_id'];
        break;
    }
}
// if F0 room doesn't exist, use the first room in building 23
if (!$centerStoreRoomId) {
    $st = $pdo->prepare("SELECT id FROM rooms WHERE building_id=? LIMIT 1");
    $st->execute([$centerStoreBuildingId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $centerStoreRoomId = $r ? (int)$r['id'] : null;
}

// Chemical CAS → chemical_id lookup cache
$chemCache = [];
function getChemId(PDO $pdo, string $casOrCat, string $name, array &$cache): ?int {
    $key = $casOrCat . '|' . $name;
    if (isset($cache[$key])) return $cache[$key];

    // Try by CAS
    if (preg_match('/\d{2,7}-\d{2}-\d/', $casOrCat)) {
        $st = $pdo->prepare("SELECT id FROM chemicals WHERE cas_number=? AND is_active=1 LIMIT 1");
        $st->execute([$casOrCat]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) { $cache[$key] = (int)$r['id']; return $cache[$key]; }
    }

    // Try by name LIKE
    $st = $pdo->prepare("SELECT id FROM chemicals WHERE name LIKE ? AND is_active=1 ORDER BY id LIMIT 1");
    $st->execute(['%' . trim($name) . '%']);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) { $cache[$key] = (int)$r['id']; return $cache[$key]; }

    $cache[$key] = null;
    return null;
}

// Default owner_id and lab_id
$defaultOwner = 1;
$defaultLab   = 1;

// lab_id lookup by owner_id (use first lab as fallback)
$labId = $defaultLab;

// ── Parse CSV ────────────────────────────────────────────────────────────────

$csvFile = __DIR__ . '/../../data/6.สารเคมีที่มีอยู่ในคลังฯ.csv';
if (!file_exists($csvFile)) {
    die("CSV not found: $csvFile\n");
}

$handle = fopen($csvFile, 'r');
// Read BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);
else fseek($handle, 3);

// Skip first 2 header lines
fgetcsv($handle); // row 1: "ขวดสารเคมีที่มีอยู่ในคลังฯ"
fgetcsv($handle); // row 2: empty
$header = fgetcsv($handle); // row 3: column names

// Col indices
//  0=รหัสขวด, 1=ชื่อสารเคมี, 2=CAS, 3=เกรด, 4=ขนาดบรรจุ, 5=ปริมาณคงเหลือ, 6=หน่วยบรรจุ,
//  7=ชื่อผู้เพิ่มขวด, 8=เวลาเพิ่มขวด, ... 33=สถานที่จัดเก็บ (old), 34=owner

$inserts          = [];
$stats            = ['total' => 0, 'mapped' => 0, 'center_store' => 0, 'no_chem' => 0, 'skipped' => 0];
$seenBarcodes     = [];
$insertedChemIds  = []; // for de-dup reporting only

$today = date('Y-m-d');

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 7) continue;

    $barcode  = trim($row[0] ?? '');
    $chemName = trim($row[1] ?? '');
    $casRaw   = trim($row[2] ?? '');
    $grade    = trim($row[3] ?? '');
    $sizeRaw  = trim($row[4] ?? '');
    $qtyRaw   = trim($row[5] ?? '');
    $unitRaw  = trim($row[6] ?? '');
    $adderName= trim($row[7] ?? '');
    $addedAt  = trim($row[8] ?? '');

    if ($barcode === '' || $chemName === '') { $stats['skipped']++; continue; }
    if (isset($seenBarcodes[$barcode]))      { $stats['skipped']++; continue; }
    $seenBarcodes[$barcode] = true;
    $stats['total']++;

    // ── resolve chemical_id ──────────────────────────────────────────────────
    $chemId = getChemId($pdo, $casRaw, $chemName, $chemCache);
    if (!$chemId) {
        // Try with Catalogue No. treated as name fallback
        $chemId = getChemId($pdo, '', $chemName, $chemCache);
    }
    if (!$chemId) { $stats['no_chem']++; }

    // ── parse location from barcode ──────────────────────────────────────────
    $loc         = parseBarcodeLocation($barcode);
    $bCode       = $loc['building'];
    $rNumber     = $loc['room_number'];
    $year2       = $loc['year_be2'];

    // Resolve IDs
    $resolvedBuildingId = null;
    $resolvedRoomId     = null;

    if ($bCode && $bCode !== 'F0') {
        $resolvedBuildingId = $buildingMap[strtoupper($bCode)] ?? null;
    }

    if ($rNumber && $rNumber !== 'CENTER_STORE') {
        $rKey = strtoupper($rNumber);
        if (isset($roomMap[$rKey])) {
            // Prefer room in the correct building
            foreach ($roomMap[$rKey] as $rm) {
                if ($resolvedBuildingId && $rm['building_id'] === $resolvedBuildingId) {
                    $resolvedRoomId = $rm['room_id'];
                    break;
                }
            }
            // Any match fallback
            if (!$resolvedRoomId) $resolvedRoomId = $roomMap[$rKey][0]['room_id'];
        }
    }

    // Fallback to Center Store if building or room not found
    if (!$resolvedBuildingId || !$resolvedRoomId) {
        $resolvedBuildingId = $centerStoreBuildingId;
        $resolvedRoomId     = $centerStoreRoomId;
        $stats['center_store']++;
    } else {
        $stats['mapped']++;
    }

    // ── dates ────────────────────────────────────────────────────────────────
    $receivedDate = $today;
    if ($year2 !== null) {
        $adYear = beYear2ToAD($year2);
        $receivedDate = $adYear . '-01-01'; // approximate: first day of import year
    } elseif ($addedAt !== '') {
        // Parse Thai date format "2/6/2016 11:09" or "28/9/2015 14:30"
        if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $addedAt, $dm)) {
            $receivedDate = sprintf('%04d-%02d-%02d', (int)$dm[3], (int)$dm[2], (int)$dm[1]);
        }
    }

    // ── quantity ─────────────────────────────────────────────────────────────
    $qty    = parseQty($qtyRaw ?: $sizeRaw);
    $size   = parseQty($sizeRaw);
    $unit   = unitToEnum($unitRaw);

    $iqty = $size > 0 ? $size : max($qty, 1);
    $cqty = $qty > 0 ? $qty : $iqty;

    // container_type heuristic
    $ctype = 'bottle';
    if (in_array($unit, ['L', 'mL']) && $iqty >= 2000) $ctype = 'flask';
    if ($unit === 'kg' || ($unit === 'g' && $iqty >= 500)) $ctype = 'canister';
    if (mb_strpos($grade, 'Gas', 0, 'UTF-8') !== false || $unit === 'kg' && $iqty >= 10) $ctype = 'cylinder';

    // ── owner_id — map added_by_name to user_id (best-effort) ────────────────
    // Use admin1 (id=1) as default, simple lookup could be expanded
    $ownerId = $defaultOwner;

    // ── lab_id ───────────────────────────────────────────────────────────────
    $labId = $defaultLab;

    // ── Build PDO parameter array ─────────────────────────────────────────────
    $cGrade  = mb_substr($grade, 0, 255, 'UTF-8');
    $cAdder  = mb_substr($adderName, 0, 255, 'UTF-8');
    $chemIdFinal = $chemId ? (int)$chemId : 1; // fallback chem_id=1 if not found

    // Parameters match the prepared statement placeholders in order:
    // qr_code, bottle_code, chemical_id, owner_id, lab_id,
    // building_id, room_id,
    // container_type, grade,
    // initial_quantity, capacity_unit, current_quantity, quantity_unit,
    // received_date,
    // batch_number,
    // created_by, added_by_name
    $inserts[] = [
        $barcode, $barcode, $chemIdFinal, $ownerId, $labId,
        (int)$resolvedBuildingId, (int)$resolvedRoomId,
        $ctype, $cGrade,
        $iqty, $unit, $cqty, $unit,
        $receivedDate,
        $barcode, // batch_number = barcode
        $ownerId, $cAdder,
    ];
}

fclose($handle);

// ── Direct PDO Import ─────────────────────────────────────────────────────────

echo "\nParsed {$stats['total']} barcodes:\n";
echo "  Mapped to correct room : {$stats['mapped']}\n";
echo "  Sent to Center Store   : {$stats['center_store']}\n";
echo "  Chemical not in DB (→1): {$stats['no_chem']}\n\n";
echo "Importing via PDO prepared statements...\n";

$insertSQL = "INSERT INTO containers 
    (qr_code, bottle_code, chemical_id, owner_id, lab_id,
     building_id, room_id, cabinet_id, shelf_id, slot_id,
     container_type, container_material, container_size, grade,
     initial_quantity, capacity_unit, current_quantity, quantity_unit,
     manufacture_date, received_date, opened_date, expiry_date, expiry_alert_days,
     status, quality_status, is_active, label_image, container_3d_model,
     batch_number, lot_number, po_number, invoice_number, supplier_id,
     created_by, added_by_name, added_at_original,
     created_at, updated_at)
    VALUES
    (?, ?, ?, ?, ?,
     ?, ?, NULL, NULL, NULL,
     ?, 'glass', NULL, ?,
     ?, ?, ?, ?,
     NULL, ?, NULL, NULL, 30,
     'active', 'good', 1, NULL, NULL,
     ?, NULL, NULL, NULL, NULL,
     ?, ?, NULL,
     NOW(), NOW())
    ON DUPLICATE KEY UPDATE updated_at=NOW()";

$stmt = $pdo->prepare($insertSQL);
$pdo->beginTransaction();
$done   = 0;
$failed = 0;
$errors = [];

foreach ($inserts as $row) {
    try {
        $stmt->execute($row);
        $done++;
    } catch (\Throwable $e) {
        $failed++;
        if (count($errors) < 10) {
            $errors[] = "Row qr={$row[0]}: " . $e->getMessage();
        }
    }
    if (($done + $failed) % 500 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  ... " . ($done + $failed) . " processed ($done ok)\n";
    }
}
$pdo->commit();

echo "\n✅ Import complete!\n";
echo "   Inserted/updated : $done\n";
echo "   Failed           : $failed\n";

if ($errors) {
    echo "\nFirst errors:\n";
    foreach ($errors as $e) echo "  $e\n";
}
echo "\n";
