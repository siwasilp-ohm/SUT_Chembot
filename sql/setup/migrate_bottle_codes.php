<?php
/**
 * Migration: Convert existing BTL-XXXX bottle codes to SUT format
 * and assign codes to containers that have empty bottle_code
 * 
 * SUT format: RoomCode(6) + Section(1) + FiscalYear(2) + Serial(5)
 * Example: F05202A6800001
 */
require_once __DIR__ . '/../includes/database.php';
$pdo = Database::getInstance();

echo "═══════════════════════════════════════════\n";
echo "  Migrate Container Bottle Codes to SUT Format\n";
echo "═══════════════════════════════════════════\n\n";

// Get all containers with their room info
$containers = $pdo->query("
    SELECT cn.id, cn.bottle_code, cn.room_id, cn.building_id, cn.created_at,
           rm.code as room_code, b.code as building_code
    FROM containers cn
    LEFT JOIN rooms rm ON cn.room_id = rm.id
    LEFT JOIN buildings b ON cn.building_id = b.id
    ORDER BY cn.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total containers: " . count($containers) . "\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;

// Track serial numbers per prefix to avoid duplicates
$serialTracker = [];

// Pre-load existing serials from chemical_stock to avoid collision
$existingStockCodes = $pdo->query("SELECT bottle_code FROM chemical_stock WHERE bottle_code IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
foreach ($existingStockCodes as $ec) {
    if (strlen($ec) >= 9) {
        $pfx = substr($ec, 0, 9); // e.g. F05202A65
        $tail = substr($ec, 9);
        if (is_numeric($tail)) {
            $serialTracker[$pfx] = max($serialTracker[$pfx] ?? 0, intval($tail));
        }
    }
}
echo "Pre-loaded " . count($serialTracker) . " prefix serials from chemical_stock\n\n";

foreach ($containers as $cn) {
    $currentCode = $cn['bottle_code'];
    
    // Skip if already has a valid SUT-format code (14 chars, not BTL-)
    if (!empty($currentCode) && strlen($currentCode) == 14 && !str_starts_with($currentCode, 'BTL-')) {
        echo "  SKIP #{$cn['id']}: Already SUT format [{$currentCode}]\n";
        $skipped++;
        continue;
    }
    
    // Build room code
    $roomCode = '';
    if (!empty($cn['room_code'])) {
        $roomCode = preg_replace('/[^A-Za-z0-9]/', '', $cn['room_code']);
    } elseif (!empty($cn['building_code'])) {
        $roomCode = preg_replace('/[^A-Za-z0-9]/', '', $cn['building_code']);
    }
    if (empty($roomCode)) $roomCode = 'F00000';
    $roomCode = substr(str_pad($roomCode, 6, '0'), 0, 6);
    
    // Thai fiscal year from container's created_at
    $createdYear = $cn['created_at'] ? (int)date('Y', strtotime($cn['created_at'])) : (int)date('Y');
    $thaiYear = $createdYear + 543;
    $fy = substr((string)$thaiYear, -2);
    
    $section = 'A';
    $prefix = $roomCode . $section . $fy;
    
    // Get next serial
    $serialTracker[$prefix] = ($serialTracker[$prefix] ?? 0) + 1;
    $newCode = $prefix . str_pad($serialTracker[$prefix], 5, '0', STR_PAD_LEFT);
    
    try {
        $stmt = $pdo->prepare("UPDATE containers SET bottle_code = ? WHERE id = ?");
        $stmt->execute([$newCode, $cn['id']]);
        
        $oldLabel = $currentCode ?: '(empty)';
        echo "  OK   #{$cn['id']}: [{$oldLabel}] → [{$newCode}]\n";
        $updated++;
    } catch (Exception $e) {
        echo "  ERR  #{$cn['id']}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n═══════════════════════════════════════════\n";
echo "  Results: Updated=$updated  Skipped=$skipped  Errors=$errors\n";
echo "═══════════════════════════════════════════\n";

// Verify
echo "\n=== Verification: All container bottle codes ===\n";
$rows = $pdo->query("SELECT id, bottle_code FROM containers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  #{$r['id']}: {$r['bottle_code']}\n";
}
