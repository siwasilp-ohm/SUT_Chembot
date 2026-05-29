<?php
/**
 * Fix Container Locations — re-map building_id & room_id from bottle_code
 *
 * Barcode format: RRRRRRSFFSSSSS (14 chars)
 *   [0-5]  RRRRRR = room_code   e.g. F02212  → rooms.code = 'F2212'
 *   [6]    S      = section      e.g. A
 *   [7-8]  FF     = fiscal year  e.g. 60
 *   [9-13] SSSSS  = serial       e.g. 00028
 *
 * The seeder stored room codes zero-padded (F02212) but the DB has them
 * without leading zero on building number (F2212, F05202A → F05202A  — F5 bldg).
 *
 * Normalisation rule:
 *   Barcode room prefix  →  canonical room code in DB
 *   F01xxx  → F1xxx   (building F1)
 *   F02xxx  → F2xxx   (building F2)
 *   F03xxx  → F3xxx   (building F3)
 *   F04xxx  → F4xxx   (building F4)
 *   F05xxx  → F5xxx   (building F5)
 *   F06xxx  → F6xxx / F6/1 xxx  (building F6 or F6/1)
 *   F07xxx  → F7xxx   (building F7)
 *   F09xxx  → F9xxx   (building F9)
 *   F10xxx  → F10xxx  (building F10 — already correct)
 *   F11xxx  → F11xxx  (building F11 — already correct)
 *   F12xxx  → F12xxx  (building F12 — already correct)
 *   F14xxx  → F14xxx  (building F14)
 *
 * For single-digit buildings, the barcode uses 3-char zero-padded prefix
 * but the room codes in the DB just use 2-char building prefix.
 * e.g. barcode F02212 → DB room code F2212
 *      barcode F05202 → DB room code F05202A (already correct — F5 uses F05xxx)
 *
 * Wait — from the room dump: F5 uses "F05101A", F6 uses "F06102A" etc.
 * So F5 rooms ARE stored as F05xxx in DB!
 * And F2 rooms ARE stored as F2xxx (not F02xxx) in DB!
 *
 * Mapping:
 *   Barcode[0:6] = F01xxx → DB room code starts with F01 → building "F1"  ← F1 bldg uses F01xxx!
 *   Barcode[0:6] = F02xxx → DB room code starts with F2  → building "F2"
 *   Barcode[0:6] = F03xxx → DB room code starts with F3  → building "F3"
 *   Barcode[0:6] = F04xxx → DB room code starts with F04 → building "F4"   ← F4 uses F04xxx!
 *   Barcode[0:6] = F05xxx → DB room code starts with F05 → building "F5"   ← already correct
 *   Barcode[0:6] = F06xxx → DB room code starts with F06 → building "F6"   ← already correct
 *   Barcode[0:6] = F07xxx → DB room code starts with F07 → building "F7"   ← already correct
 *   Barcode[0:6] = F09xxx → DB room code starts with F9  → building "F9"
 *   F10, F11, F12, F14 → already 3-char match works
 *
 * So the transform for barcode room prefix → DB room code prefix:
 *   F01 → F01  (F1 building, rooms stored as F01xxx)
 *   F02 → F2   (F2 building, rooms stored as F2xxx)
 *   F03 → F3   (F3 building, rooms stored as F3xxx)
 *   F04 → F04  (F4 building, rooms stored as F04xxx)
 *   F05 → F05  (F5 building, rooms stored as F05xxx)
 *   F06 → F06  (F6 building, rooms stored as F06xxx)
 *   F07 → F07  (F7 building, rooms stored as F07xxx / F7xxx mixed)
 *   F09 → F9   (F9 building, rooms stored as F9xxx)
 *   F10 → F10
 *   F11 → F11
 *   F12 → F12
 *   F14 → F14
 */

require_once __DIR__ . '/../../includes/database.php';
$pdo = Database::getInstance();

echo "═══════════════════════════════════════════════════════\n";
echo "  Fix Container Locations from bottle_code\n";
echo "═══════════════════════════════════════════════════════\n\n";

// ── Step 1: Build room lookup map ──────────────────────────────
echo "[1/3] Building room + building maps...\n";

$rooms = $pdo->query("
    SELECT r.id AS room_id, r.code AS room_code, r.name AS room_name,
           r.building_id, b.id AS bldg_id, b.code AS bldg_code
    FROM rooms r
    JOIN buildings b ON r.building_id = b.id
")->fetchAll(PDO::FETCH_ASSOC);

// Index: room_code (lowercase stripped) → [room_id, building_id]
$roomIndex = [];
foreach ($rooms as $r) {
    // Store exact room_code stripped of non-alnum for lookup
    $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r['room_code']));
    // If duplicate (e.g. F3203H appears twice), keep first
    if (!isset($roomIndex[$key])) {
        $roomIndex[$key] = [
            'room_id'     => $r['room_id'],
            'building_id' => $r['bldg_id'],
            'bldg_code'   => $r['bldg_code'],
            'room_code'   => $r['room_code'],
        ];
    }
}
echo "   ✅ Indexed " . count($roomIndex) . " rooms\n\n";

// ── Step 2: Define barcode prefix → DB room prefix transform ──
/**
 * Given the first 6 chars of bottle_code (the room prefix),
 * strip the leading zero to get the DB room_code prefix.
 */
function barcodeToDbRoomPrefix(string $bcPrefix): string {
    // bcPrefix is always 6 uppercase chars e.g. F02212, F05202, F10103
    $bldg3 = substr($bcPrefix, 0, 3); // F02, F05, F10
    $room3 = substr($bcPrefix, 3);    // 212, 202, 103

    // Buildings that use zero-padded 2-digit in DB room codes:
    // F2  → stored as F2xxx  (barcode F02)
    // F3  → stored as F3xxx  (barcode F03)
    // F9  → stored as F9xxx  (barcode F09)
    $stripLeadingZero = [
        'F02' => 'F2',
        'F03' => 'F3',
        'F09' => 'F9',
    ];

    if (isset($stripLeadingZero[$bldg3])) {
        return $stripLeadingZero[$bldg3] . $room3;
    }

    // Everything else: F01, F04, F05, F06, F07, F10, F11, F12, F14
    // — keep as-is (DB room codes use same prefix)
    return $bcPrefix;
}

// ── Step 3: Load all containers and fix locations ──────────────
echo "[2/3] Loading containers...\n";

$containers = $pdo->query("
    SELECT cn.id, cn.bottle_code, cn.building_id, cn.room_id,
           b.code AS cur_bldg, rm.code AS cur_room
    FROM containers cn
    LEFT JOIN buildings b  ON cn.building_id = b.id
    LEFT JOIN rooms    rm  ON cn.room_id      = rm.id
    WHERE cn.bottle_code IS NOT NULL
      AND cn.bottle_code != ''
      AND LENGTH(cn.bottle_code) = 14
")->fetchAll(PDO::FETCH_ASSOC);

echo "   Total containers with 14-char bottle_code: " . count($containers) . "\n\n";

echo "[3/3] Fixing locations...\n";

$stmt = $pdo->prepare("
    UPDATE containers SET building_id = :bid, room_id = :rid WHERE id = :id
");

$fixed      = 0;
$alreadyOk  = 0;
$notFound   = 0;
$notFoundList = [];

$pdo->beginTransaction();

foreach ($containers as $cn) {
    $bc = strtoupper($cn['bottle_code']);

    // Extract 6-char room prefix from barcode
    $bcRoomPrefix = substr($bc, 0, 6);

    // Transform to DB room code prefix
    $dbPrefix = barcodeToDbRoomPrefix($bcRoomPrefix);

    // Also extract the full "room code part" from barcode for exact match
    // e.g. F02212A → the section letter is at [6], so barcode room = F02212
    // The actual DB room code could be F2212 (without section suffix)
    // OR F2212A if the section is part of the room code
    // Strategy: try exact match first, then prefix match

    // Build possible DB room code candidates:
    $candidates = [
        $dbPrefix,                           // e.g. F2212
        $dbPrefix . strtoupper($bc[6]),      // e.g. F2212A (with section letter)
    ];

    $found = null;
    foreach ($candidates as $cand) {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cand));
        if (isset($roomIndex[$key])) {
            $found = $roomIndex[$key];
            break;
        }
    }

    if (!$found) {
        // Try prefix search: find any room whose stripped code starts with dbPrefix
        $pfxKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $dbPrefix));
        foreach ($roomIndex as $key => $info) {
            if (str_starts_with($key, $pfxKey)) {
                $found = $info;
                break;
            }
        }
    }

    if (!$found) {
        $notFound++;
        $notFoundList[] = "  id={$cn['id']} bc={$cn['bottle_code']} prefix={$dbPrefix}";
        continue;
    }

    // Check if already correct
    if ($cn['building_id'] == $found['building_id'] && $cn['room_id'] == $found['room_id']) {
        $alreadyOk++;
        continue;
    }

    // Update
    $stmt->execute([
        ':bid' => $found['building_id'],
        ':rid' => $found['room_id'],
        ':id'  => $cn['id'],
    ]);
    $fixed++;

    if ($fixed <= 30 || $fixed % 500 === 0) {
        echo "  FIXED #{$cn['id']}: bc={$cn['bottle_code']} "
           . "{$cn['cur_bldg']}→{$found['bldg_code']} "
           . "{$cn['cur_room']}→{$found['room_code']}\n";
    }
}

$pdo->commit();

echo "\n═══════════════════════════════════════════════════════\n";
echo "  Results:\n";
echo "  Fixed:      $fixed\n";
echo "  Already OK: $alreadyOk\n";
echo "  Not found:  $notFound\n";
echo "═══════════════════════════════════════════════════════\n";

if (!empty($notFoundList)) {
    echo "\nNot-found sample (first 20):\n";
    foreach (array_slice($notFoundList, 0, 20) as $l) echo $l . "\n";
}

// ── Verification ────────────────────────────────────────────────
echo "\n=== Post-fix mismatch check ===\n";
$rows = $pdo->query("
    SELECT 
        LEFT(cn.bottle_code, 3) AS bc_bldg3,
        b.code AS actual_bldg,
        COUNT(*) AS cnt
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id = b.id
    WHERE cn.bottle_code IS NOT NULL AND LENGTH(cn.bottle_code) = 14
    GROUP BY LEFT(cn.bottle_code, 3), b.code
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Expected mappings
$expected = [
    'F01' => 'F1', 'F02' => 'F2', 'F03' => 'F3', 'F04' => 'F4',
    'F05' => 'F5', 'F06' => 'F6', 'F07' => 'F7', 'F09' => 'F9',
    'F10' => 'F10','F11' => 'F11','F12' => 'F12','F14' => 'F14',
];

foreach ($rows as $r) {
    $exp = $expected[$r['bc_bldg3']] ?? $r['bc_bldg3'];
    $ok  = ($r['actual_bldg'] === $exp || $r['actual_bldg'] === $r['bc_bldg3']) ? '✓' : '✗ MISMATCH';
    echo "  bc_prefix={$r['bc_bldg3']} actual_bldg={$r['actual_bldg']} cnt={$r['cnt']} $ok\n";
}

// Specific test: F02212A6000028
echo "\n=== Verify example barcode: F02212A6000028 ===\n";
$row = $pdo->query("
    SELECT cn.id, cn.bottle_code, cn.building_id, cn.room_id,
           b.code AS bldg, b.name AS bldg_name,
           rm.code AS room, rm.name AS room_name
    FROM containers cn
    LEFT JOIN buildings b  ON cn.building_id = b.id
    LEFT JOIN rooms    rm  ON cn.room_id      = rm.id
    WHERE cn.bottle_code = 'F02212A6000028'
")->fetchAll(PDO::FETCH_ASSOC);

if ($row) {
    foreach ($row as $r) {
        echo "  id={$r['id']} bc={$r['bottle_code']}\n";
        echo "  Building: [{$r['bldg']}] {$r['bldg_name']}\n";
        echo "  Room:     [{$r['room']}] {$r['room_name']}\n";
    }
} else {
    // Show nearby barcodes with that room prefix
    echo "  (Barcode not found — showing F02212* sample)\n";
    $rows2 = $pdo->query("
        SELECT cn.id, cn.bottle_code, b.code AS bldg, rm.code AS room
        FROM containers cn
        LEFT JOIN buildings b  ON cn.building_id = b.id
        LEFT JOIN rooms    rm  ON cn.room_id      = rm.id
        WHERE cn.bottle_code LIKE 'F02212%'
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) {
        echo "  id={$r['id']} bc={$r['bottle_code']} bldg={$r['bldg']} room={$r['room']}\n";
    }
}
