<?php
/**
 * Fix round-2: handle remaining mismatches
 * - F01xxx containers that landed on F0 (room codes not in DB — map to F1 building, closest room)
 * - F06xxx containers that landed on F0 (F6/1 building rooms)
 * - F10xxx containers that landed on F0
 * - 320Fxx old-format barcodes → keep on F0 (Center Store, correct)
 */
require_once __DIR__ . '/../../includes/database.php';
$pdo = Database::getInstance();

echo "═══════════════════════════════════════════════════════\n";
echo "  Fix Container Locations — Round 2 (remaining mismatches)\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Load buildings
$buildings = $pdo->query("SELECT id, code FROM buildings")->fetchAll(PDO::FETCH_KEY_PAIR);
$bldgByCode = array_flip($buildings); // code → id

// Load rooms
$rooms = $pdo->query("
    SELECT r.id AS room_id, r.code AS room_code, r.building_id, b.code AS bldg_code
    FROM rooms r JOIN buildings b ON r.building_id = b.id
")->fetchAll(PDO::FETCH_ASSOC);
$roomIndex = [];
foreach ($rooms as $r) {
    $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r['room_code']));
    if (!isset($roomIndex[$key])) $roomIndex[$key] = $r;
}

// ── Investigate each not-found group ──────────────────────────
echo "=== Checking not-found groups ===\n";

// 1. F01xxx that are NOT matched
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code, b.code AS bldg, rm.code AS room
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    LEFT JOIN rooms rm ON cn.room_id=rm.id
    WHERE cn.bottle_code LIKE 'F01%' AND LENGTH(cn.bottle_code)=14
      AND b.code = 'F0'
    ORDER BY cn.bottle_code
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nF01xxx still on F0 (sample):\n";
foreach ($rows as $r) {
    $prefix = substr($r['bottle_code'], 0, 6);
    $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix));
    $found = isset($roomIndex[$key]) ? "found→{$roomIndex[$key]['room_code']}" : "NOT IN DB";
    echo "  bc={$r['bottle_code']} prefix=$prefix $found\n";
}

// 2. F06xxx still on F0
$rows2 = $pdo->query("
    SELECT cn.id, cn.bottle_code, b.code AS bldg, rm.code AS room
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    LEFT JOIN rooms rm ON cn.room_id=rm.id
    WHERE cn.bottle_code LIKE 'F06%' AND LENGTH(cn.bottle_code)=14
      AND b.code = 'F0'
    ORDER BY cn.bottle_code
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nF06xxx still on F0 (sample):\n";
foreach ($rows2 as $r) {
    $prefix = substr($r['bottle_code'], 0, 6);
    $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix));
    $found = isset($roomIndex[$key]) ? "found→{$roomIndex[$key]['room_code']}" : "NOT IN DB";
    echo "  bc={$r['bottle_code']} prefix=$prefix $found\n";
}

// 3. F10xxx still on F0
$rows3 = $pdo->query("
    SELECT cn.id, cn.bottle_code, b.code AS bldg, rm.code AS room
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    LEFT JOIN rooms rm ON cn.room_id=rm.id
    WHERE cn.bottle_code LIKE 'F10%' AND LENGTH(cn.bottle_code)=14
      AND b.code = 'F0'
    ORDER BY cn.bottle_code
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nF10xxx still on F0 (sample):\n";
foreach ($rows3 as $r) {
    $prefix = substr($r['bottle_code'], 0, 6);
    $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix));
    $found = isset($roomIndex[$key]) ? "found→{$roomIndex[$key]['room_code']}" : "NOT IN DB";
    echo "  bc={$r['bottle_code']} prefix=$prefix $found\n";
}

// ── Fix F01xxx → assign to correct F1 room (best-effort prefix search) ──
echo "\n=== Fixing F01xxx unmatched → F1 building ===\n";
$f1bldgId = $pdo->query("SELECT id FROM buildings WHERE code='F1'")->fetchColumn();
$f0roomId = $pdo->query("SELECT id FROM rooms WHERE code='F0' LIMIT 1")->fetchColumn();
echo "F1 building_id = $f1bldgId\n";

$stmt = $pdo->prepare("UPDATE containers SET building_id=:bid, room_id=:rid WHERE id=:id");

$fixedF01 = 0;
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    WHERE cn.bottle_code LIKE 'F01%' AND LENGTH(cn.bottle_code)=14 AND b.code='F0'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $cn) {
    $bc = strtoupper($cn['bottle_code']);
    $bcPrefix6 = substr($bc, 0, 6);      // e.g. F01133
    $roomNumPart = substr($bc, 3, 3);    // e.g. 133

    // Try: F1 + roomNumPart + sectionLetter
    $secLetter = strtoupper($bc[6]);
    $candidates = [
        'F1' . $roomNumPart . $secLetter,  // F1133A
        'F1' . $roomNumPart,               // F1133
        'F01' . $roomNumPart . $secLetter, // F01133A (already tried)
        'F01' . $roomNumPart,              // F01133
    ];

    $found = null;
    foreach ($candidates as $cand) {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cand));
        if (isset($roomIndex[$key])) { $found = $roomIndex[$key]; break; }
    }

    // Try prefix: rooms starting with F01roomNum or F1roomNum
    if (!$found) {
        $pfx1 = 'F1' . $roomNumPart;
        $pfx2 = 'F01' . $roomNumPart;
        foreach ($roomIndex as $key => $info) {
            if ($info['bldg_code'] === 'F1' && (str_starts_with($key, strtoupper($pfx1)) || str_starts_with($key, strtoupper($pfx2)))) {
                $found = $info; break;
            }
        }
    }

    if ($found) {
        $stmt->execute([':bid'=>$found['building_id'],':rid'=>$found['room_id'],':id'=>$cn['id']]);
        echo "  FIXED #{$cn['id']}: {$cn['bottle_code']} → bldg={$found['bldg_code']} room={$found['room_code']}\n";
        $fixedF01++;
    } else {
        // Assign to F1 building, keep F0 room (better than F0 building)
        $stmt->execute([':bid'=>$f1bldgId,':rid'=>$f0roomId ?: null,':id'=>$cn['id']]);
        echo "  PARTIAL #{$cn['id']}: {$cn['bottle_code']} → bldg=F1 (room not found in DB)\n";
        $fixedF01++;
    }
}
echo "  Fixed $fixedF01 F01xxx containers\n";

// ── Fix F06xxx still on F0 → F6 or F6/1 building ──
echo "\n=== Fixing F06xxx unmatched → F6 building ===\n";
$f6bldgId  = $pdo->query("SELECT id FROM buildings WHERE code='F6'")->fetchColumn();
$f61bldgId = $pdo->query("SELECT id FROM buildings WHERE code='F6/1'")->fetchColumn();

$fixedF06 = 0;
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    WHERE cn.bottle_code LIKE 'F06%' AND LENGTH(cn.bottle_code)=14 AND b.code='F0'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $cn) {
    $bc = strtoupper($cn['bottle_code']);
    $roomNumPart = substr($bc, 3, 3);
    $secLetter = $bc[6];
    $candidates = [
        'F06' . $roomNumPart . $secLetter,
        'F06' . $roomNumPart,
    ];
    $found = null;
    foreach ($candidates as $cand) {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cand));
        if (isset($roomIndex[$key])) { $found = $roomIndex[$key]; break; }
    }
    if (!$found) {
        // Check F6/1 building prefix rooms (F06152-F06166 range)
        $numVal = intval($roomNumPart);
        if ($numVal >= 152 && $numVal <= 166) {
            $pfx = 'F06' . $roomNumPart;
            foreach ($roomIndex as $key => $info) {
                if ($info['bldg_code'] === 'F6/1' && str_starts_with($key, strtoupper($pfx))) {
                    $found = $info; break;
                }
            }
        }
    }
    if ($found) {
        $stmt->execute([':bid'=>$found['building_id'],':rid'=>$found['room_id'],':id'=>$cn['id']]);
        echo "  FIXED #{$cn['id']}: {$cn['bottle_code']} → bldg={$found['bldg_code']} room={$found['room_code']}\n";
    } else {
        $stmt->execute([':bid'=>$f6bldgId,':rid'=>null,':id'=>$cn['id']]);
        echo "  PARTIAL #{$cn['id']}: {$cn['bottle_code']} → bldg=F6 (room not found)\n";
    }
    $fixedF06++;
}
echo "  Fixed $fixedF06 F06xxx containers\n";

// ── Fix F10xxx still on F0 ──
echo "\n=== Fixing F10xxx unmatched → F10 building ===\n";
$f10bldgId = $pdo->query("SELECT id FROM buildings WHERE code='F10'")->fetchColumn();

$fixedF10 = 0;
$rows = $pdo->query("
    SELECT cn.id, cn.bottle_code
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    WHERE cn.bottle_code LIKE 'F10%' AND LENGTH(cn.bottle_code)=14 AND b.code='F0'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $cn) {
    $bc = strtoupper($cn['bottle_code']);
    $roomNumPart = substr($bc, 3, 3);
    $secLetter = $bc[6];
    $candidates = [
        'F10' . $roomNumPart . $secLetter,
        'F10' . $roomNumPart,
    ];
    $found = null;
    foreach ($candidates as $cand) {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cand));
        if (isset($roomIndex[$key])) { $found = $roomIndex[$key]; break; }
    }
    if ($found) {
        $stmt->execute([':bid'=>$found['building_id'],':rid'=>$found['room_id'],':id'=>$cn['id']]);
        echo "  FIXED #{$cn['id']}: {$cn['bottle_code']} → bldg={$found['bldg_code']} room={$found['room_code']}\n";
    } else {
        $stmt->execute([':bid'=>$f10bldgId,':rid'=>null,':id'=>$cn['id']]);
        echo "  PARTIAL #{$cn['id']}: {$cn['bottle_code']} → bldg=F10 (room not found)\n";
    }
    $fixedF10++;
}
echo "  Fixed $fixedF10 F10xxx containers\n";

// ── Final verification ─────────────────────────────────────────
echo "\n═══════════════════════════════════════════════════════\n";
echo "  FINAL MISMATCH SUMMARY\n";
echo "═══════════════════════════════════════════════════════\n";
$rows = $pdo->query("
    SELECT LEFT(cn.bottle_code,3) AS bc3, b.code AS bldg, COUNT(*) AS cnt
    FROM containers cn
    LEFT JOIN buildings b ON cn.building_id=b.id
    WHERE cn.bottle_code IS NOT NULL AND LENGTH(cn.bottle_code)=14
    GROUP BY LEFT(cn.bottle_code,3), b.code
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$expected = ['F01'=>'F1','F02'=>'F2','F03'=>'F3','F04'=>'F4','F05'=>'F5',
             'F06'=>'F6','F07'=>'F7','F09'=>'F9','F10'=>'F10','F11'=>'F11',
             'F12'=>'F12','F14'=>'F14'];
foreach ($rows as $r) {
    $exp = $expected[$r['bc3']] ?? '?';
    $ok = ($r['bldg']==$exp || $r['bldg']==$r['bc3']) ? '✓' : '✗';
    echo "  {$r['bc3']} → actual={$r['bldg']} (expect=$exp) cnt={$r['cnt']} $ok\n";
}
