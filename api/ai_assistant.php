<?php
/**
 * AI Assistant API — SUT ChemBot
 * Enhanced Chemical Search Engine: No external AI API
 * Supports: CAS, formula, name, location, SDS, hazard, physical properties
 * Stores all queries for continuous learning
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'];
$user   = null;
try { $user = Auth::getCurrentUser(); } catch (Exception $e) {}

try {
    switch ($method) {
        case 'GET':
            if (!$user) { echo json_encode(['success' => false, 'error' => 'Authentication required']); break; }
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'sessions': echo json_encode(['success' => true, 'data' => getChatSessions($user['id'])]); break;
                    case 'suggest':  echo json_encode(['success' => true, 'data' => getSmartSuggestions($user)]); break;
                    default: echo json_encode(['success' => false, 'error' => 'Invalid action']);
                }
            } elseif (isset($_GET['session_id'])) {
                echo json_encode(['success' => true, 'data' => getChatMessages($_GET['session_id'], $user['id'])]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid request']);
            }
            break;

        case 'POST':
            $data   = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $data['action'] ?? 'chat';

            if ($action === 'chat_local') {
                echo json_encode(handleLocalChat($data));
                break;
            }
            if ($action === 'get_stats') {
                echo json_encode(getSystemStats());
                break;
            }
            if ($action === 'get_buildings') {
                echo json_encode(getPublicBuildings());
                break;
            }
            if ($action === 'get_rooms') {
                echo json_encode(getPublicRooms((int)($data['building_id'] ?? 0)));
                break;
            }
            if ($action === 'get_room_containers') {
                echo json_encode(getPublicRoomContainers((int)($data['room_id'] ?? 0)));
                break;
            }

            if (!$user) { echo json_encode(['success' => false, 'error' => 'Authentication required']); break; }

            switch ($action) {
                case 'chat':
                    echo json_encode(['success' => true, 'data' => processChatMessage($data, $user)]);
                    break;
                case 'search':
                    echo json_encode(['success' => true, 'data' => smartSearch($data['query'] ?? '', $user)]);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;

        case 'DELETE':
            if (!$user) { echo json_encode(['success' => false, 'error' => 'Authentication required']); break; }
            if (isset($_GET['session_id'])) {
                deleteChatSession($_GET['session_id'], $user['id']);
                echo json_encode(['success' => true]);
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════
// STATS
// ═══════════════════════════════════════════════════════════════════

function getSystemStats(): array {
    try {
        $chems = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active = 1");
        $conts = Database::fetch("SELECT COUNT(*) as c FROM containers WHERE status = 'active'");
        $locs  = Database::fetch("
            SELECT COUNT(DISTINCT CONCAT(IFNULL(building_id,'0'),'-',IFNULL(room_id,'0'))) AS c
            FROM containers
            WHERE status = 'active' AND (building_id IS NOT NULL OR room_id IS NOT NULL)
        ");
        return ['success' => true, 'data' => [
            'chemicals'  => (int)($chems['c'] ?? 0),
            'containers' => (int)($conts['c'] ?? 0),
            'locations'  => (int)($locs['c']  ?? 0),
        ]];
    } catch (Exception $e) {
        return ['success' => false, 'data' => ['chemicals' => 0, 'containers' => 0, 'locations' => 0]];
    }
}

function getPublicBuildings(): array {
    try {
        $rows = Database::fetchAll("
            SELECT b.id, b.code, b.name,
                   COUNT(DISTINCT c.id)      AS bottle_count,
                   COUNT(DISTINCT c.room_id) AS room_count
            FROM buildings b
            LEFT JOIN containers c ON c.building_id = b.id AND c.status = 'active'
            GROUP BY b.id
            HAVING bottle_count > 0
            ORDER BY bottle_count DESC
        ");
        return ['success' => true, 'data' => $rows];
    } catch (Exception $e) {
        return ['success' => false, 'data' => []];
    }
}

function getPublicRooms(int $buildingId): array {
    if ($buildingId <= 0) return ['success' => false, 'data' => []];
    try {
        $rows = Database::fetchAll("
            SELECT r.id, r.room_number, r.name, r.floor,
                   COUNT(c.id) AS bottle_count
            FROM rooms r
            LEFT JOIN containers c ON c.room_id = r.id AND c.status = 'active'
            WHERE r.building_id = :bid
            GROUP BY r.id
            HAVING bottle_count > 0
            ORDER BY r.room_number
        ", [':bid' => $buildingId]);
        return ['success' => true, 'data' => $rows];
    } catch (Exception $e) {
        return ['success' => false, 'data' => []];
    }
}

function getPublicRoomContainers(int $roomId): array {
    if ($roomId <= 0) return ['success' => false, 'data' => []];
    try {
        $rows = Database::fetchAll("
            SELECT c.id, c.qr_code, c.bottle_code,
                   ch.name AS chem_name, ch.cas_number,
                   c.current_quantity, c.quantity_unit,
                   c.container_type, c.grade, c.status,
                   c.received_date, c.expiry_date,
                   CONCAT(u.first_name,' ',u.last_name) AS owner_name,
                   u.phone                              AS owner_phone
            FROM containers c
            JOIN chemicals ch ON c.chemical_id = ch.id
            LEFT JOIN users u  ON c.owner_id = u.id
            WHERE c.room_id = :rid AND c.status = 'active'
            ORDER BY ch.name, c.qr_code
            LIMIT 200
        ", [':rid' => $roomId]);
        return ['success' => true, 'data' => $rows];
    } catch (Exception $e) {
        return ['success' => false, 'data' => []];
    }
}

// ═══════════════════════════════════════════════════════════════════
// QUERY ANALYSIS
// ═══════════════════════════════════════════════════════════════════

function analyzeQuery(string $msg): string {
    $m = mb_strtolower(trim($msg), 'UTF-8');

    // ── CAS number (also handles 'CAS: 67-56-1' prefix) ──────────
    $stripped = preg_replace('/^cas\s*[:#]?\s*/i', '', $m);
    if (preg_match('/\d{2,7}-\d{2}-\d/', $stripped)) return 'cas';
    if (preg_match('/\d{2,7}-\d{2}-\d/', $m))        return 'cas';

    // ── Expiry / near-expiry ───────────────────────────────────────
    $expiryKw = ['ใกล้หมดอายุ','หมดอายุ','expire','expir','วันหมดอายุ','expired','near expiry',
                 'เกินอายุ','สารหมดอายุ','expiring'];
    foreach ($expiryKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'expiry';

    // ── Low stock / inventory ──────────────────────────────────────
    $stockKw = ['สต็อก','สต๊อก','คงเหลือน้อย','สารคงเหลือ','low stock','stock','สินค้าคงคลัง',
                'inventory','ปริมาณน้อย','ของน้อย','almost out','ทั้งหมดในคลัง','ทั้งหมด',
                'all chemical','รายการสาร','รายการทั้งหมด'];
    foreach ($stockKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'stock';

    // ── Hazard / GHS ───────────────────────────────────────────────
    $hazardKw = ['อันตราย','hazard','ghs','pictogram','signal word','h-statement','p-statement',
                 'toxic','corrosive','flammable','explosive','oxidiz','enviro','poison','harmful',
                 'irritant','carcinogen','ติดไฟ','กัดกร่อน','เป็นพิษ','อันตรายต่อสุขภาพ'];
    foreach ($hazardKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'hazard';

    // ── SDS ────────────────────────────────────────────────────────
    $sdsKw = ['sds','msds','safety data','เอกสารความปลอดภัย','safety sheet','ดาวน์โหลด sds','ดู sds'];
    foreach ($sdsKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'sds';

    // ── Storage browser (browse all locations, no specific chemical) ─
    $browsePhrases = [
        'สถานที่จัดเก็บทั้งหมด','สถานที่จัดเก็บ','ดูสถานที่จัดเก็บ',
        'ดูคลังสาร','ดูคลังทั้งหมด','คลังสารเคมีทั้งหมด',
        'browse storage','storage browser','storage locations',
        'ดูอาคาร','รายการอาคาร','รายการสถานที่','warehouse list',
        'แสดงสถานที่','แสดงอาคาร','ดูห้องทั้งหมด','ห้องทั้งหมด',
    ];
    foreach ($browsePhrases as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'storage_browse';
    // standalone phrase without a chemical name following
    if (mb_strpos($m, 'สถานที่จัดเก็บ', 0, 'UTF-8') !== false && mb_strlen($m, 'UTF-8') <= 20) return 'storage_browse';

    // ── Location ───────────────────────────────────────────────────
    $locKw = ['อยู่ที่ไหน','อยู่ไหน','ตำแหน่ง','จัดเก็บ','เก็บอยู่','where is','where are',
              'location','stored','คลัง','ห้อง','ตู้','ชั้น','find location','เก็บที่ไหน'];
    foreach ($locKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'location';

    // ── Periodic table ──────────────────────────────────────────
    $periodicKw = [
        'ตารางธาตุ','ไฟแอคทินอายุธาตุ','ธาตุ',
        'periodic table','periodic element','element table','all element','ธาตุทั้งหมด',
        'elements','ดูธาตุ','รายการธาตุ',
    ];
    foreach ($periodicKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'periodic_table';

    // ── Formula keyword prefix: "สูตร NaOH", "formula H2SO4" ────────
    // If message contains formula keyword, extract the token after it
    if (preg_match('/(?:สูตร(?:เคมี)?|formula(?:\s+of)?)\s+([A-Za-z][A-Za-z0-9]{1,14})/ui', $msg, $fm)) {
        return 'formula';
    }

    // ── Formula (mixed-case with digits, no spaces) ────────────────
    $orig    = trim($msg);
    $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $orig);
    if (strlen($cleaned) >= 2 && strlen($cleaned) <= 15
        && preg_match('/[0-9]/', $cleaned)
        && preg_match('/[A-Za-z]/', $cleaned)
        && !str_contains($orig, ' ')) return 'formula';
    // NaCl, NaOH, KMnO4 — uppercase mid-word (no spaces)
    if (preg_match('/^[A-Z][a-z]?[A-Z]/', $orig) && strlen($orig) <= 10 && !str_contains($orig, ' ')) return 'formula';

    // ── 3D model query ────────────────────────────────────────────
    $model3dKw = ['โมเดล 3d','โมเดล3d','model 3d','model3d','3d model','3dmodel',
                  'มีโมเดล','ดูโมเดล','โมเดลสาร','แบบ 3d','แบบ3d','3 มิติ','3มิติ',
                  'ar model','vrx','glb','gltf','3d preview','preview 3d',
                  'ดู 3d','เปิด 3d','โหลด 3d','แสดง 3d'];
    foreach ($model3dKw as $kw) if (mb_strpos($m, $kw, 0, 'UTF-8') !== false) return 'model3d';

    return 'name';
}

function extractCAS(string $msg): string {
    // Strip common prefixes like 'CAS:', 'CAS #', etc.
    $clean = preg_replace('/^\s*cas\s*[:#]?\s*/i', '', $msg);
    preg_match('/(\d{2,7}-\d{2}-\d)/', $clean, $m);
    if (empty($m[1])) preg_match('/(\d{2,7}-\d{2}-\d)/', $msg, $m);
    return $m[1] ?? trim($msg);
}

function cleanSearchTerm(string $msg, array $removeWords = []): string {
    $defaults = ['หา','ค้น','ค้นหา','สาร','สารเคมี','chemical','search','find','ขอ','ข้อมูล','show me','tell me about','what is','what are'];
    $all      = array_merge($defaults, $removeWords);
    $term     = mb_strtolower(trim($msg), 'UTF-8');
    foreach ($all as $w) $term = str_replace(mb_strtolower($w, 'UTF-8'), '', $term);
    return trim($term);
}

// ═══════════════════════════════════════════════════════════════════
// DATABASE QUERIES
// ═══════════════════════════════════════════════════════════════════

function fetchChemicalByCAS(string $cas): ?array {
    $chem = Database::fetch("
        SELECT c.*, cc.name as category_name, cc.description as category_desc
        FROM chemicals c
        LEFT JOIN chemical_categories cc ON c.category_id = cc.id
        WHERE c.cas_number = :val AND c.is_active = 1
        LIMIT 1
    ", [':val' => $cas]);
    if (!$chem) return null;
    return ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
}

function fetchChemicalByFormula(string $formula): ?array {
    $chem = Database::fetch("
        SELECT c.*, cc.name as category_name, cc.description as category_desc
        FROM chemicals c
        LEFT JOIN chemical_categories cc ON c.category_id = cc.id
        WHERE c.molecular_formula = :val AND c.is_active = 1
        LIMIT 1
    ", [':val' => $formula]);
    if (!$chem) return null;
    return ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
}

function fetchChemicalByFormulaCI(string $formula): ?array {
    $chem = Database::fetch("
        SELECT c.*, cc.name as category_name, cc.description as category_desc
        FROM chemicals c
        LEFT JOIN chemical_categories cc ON c.category_id = cc.id
        WHERE LOWER(c.molecular_formula) = LOWER(:val) AND c.is_active = 1
        LIMIT 1
    ", [':val' => $formula]);
    if (!$chem) return null;
    return ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
}

function fetchContainersForChem(int $chemId): array {
    return Database::fetchAll("
        SELECT con.id, con.qr_code, con.current_quantity, con.initial_quantity,
               con.quantity_unit, con.expiry_date, con.status, con.batch_number,
               con.container_type, con.container_size, con.quality_status,
               con.owner_id,
               -- Location: prefer direct building_id/room_id, fall back to slot path
               COALESCE(b_dir.name,  b_sl.name)  AS building,
               COALESCE(b_dir.id,    b_sl.id)    AS building_id,
               COALESCE(r_dir.name,  r_sl.name)  AS room,
               COALESCE(r_dir.id,    r_sl.id)    AS room_id,
               COALESCE(r_dir.room_number, r_sl.room_number) AS room_number,
               c.name  as cabinet,   c.id as cabinet_id,    c.type as cabinet_type,
               s.name  as shelf,     s.id as shelf_id,      s.level as shelf_level,
               sl.name as slot,      sl.id as slot_id,
               u.username                          as owner_username,
               CONCAT(u.first_name,' ',u.last_name)  as owner_name,
               u.department                           as dept_name,
               u.phone                                as owner_phone,
               u.email                                as owner_email
        FROM containers con
        JOIN users  u  ON con.owner_id   = u.id
        -- Slot-based path (old system)
        LEFT JOIN slots    sl ON con.location_slot_id = sl.id
        LEFT JOIN shelves  s  ON sl.shelf_id   = s.id
        LEFT JOIN cabinets c  ON s.cabinet_id  = c.id
        LEFT JOIN rooms    r_sl ON c.room_id   = r_sl.id
        LEFT JOIN buildings b_sl ON r_sl.building_id = b_sl.id
        -- Direct FK path (new seeder data)
        LEFT JOIN rooms    r_dir ON con.room_id     = r_dir.id
        LEFT JOIN buildings b_dir ON con.building_id = b_dir.id
        WHERE con.chemical_id = :id AND con.status = 'active'
        ORDER BY building, room, cabinet, shelf_level, slot
    ", [':id' => $chemId]);
}

function searchChemicalsByTerm(string $term, int $limit = 12): array {
    $like = '%' . $term . '%';
    $rows = Database::fetchAll("
        SELECT c.id, c.name, c.cas_number, c.molecular_formula, c.molecular_weight,
               c.physical_state, c.signal_word, c.image_url, c.sds_url, c.sds_pdf_path,
               c.description, c.appearance, c.category_id,
               c.hazard_pictograms, c.ghs_classifications,
               cc.name as category_name,
               CASE
                 WHEN c.name LIKE :exact1       THEN 100
                 WHEN c.cas_number = :exact2    THEN 99
                 WHEN c.molecular_formula = :exact3 THEN 95
                 WHEN c.name LIKE :start1       THEN 80
                 ELSE 50
               END AS relevance
        FROM chemicals c
        LEFT JOIN chemical_categories cc ON c.category_id = cc.id
        WHERE c.is_active = 1 AND (
            c.name             LIKE :like1 OR
            c.cas_number       LIKE :like2 OR
            c.iupac_name       LIKE :like3 OR
            c.molecular_formula LIKE :like4 OR
            c.synonyms          LIKE :like5
        )
        ORDER BY relevance DESC, c.name ASC
        LIMIT :lim
    ", [
        ':exact1' => $term, ':exact2' => $term, ':exact3' => $term,
        ':start1' => $term . '%',
        ':like1'  => $like, ':like2' => $like, ':like3' => $like,
        ':like4'  => $like, ':like5' => $like,
        ':lim'    => (int)$limit,
    ]);

    $results = [];
    foreach ($rows as $chem) {
        $results[] = ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
    }
    return $results;
}

// ═══════════════════════════════════════════════════════════════════
// MAIN DISPATCH
// ═══════════════════════════════════════════════════════════════════

function handleLocalChat(array $data): array {
    $message = trim($data['message'] ?? '');
    $lang    = $data['lang'] ?? 'th';

    if ($message === '') return ['success' => false, 'error' => 'Message required'];

    $type = analyzeQuery($message);

    // Save query for learning
    saveSearchQuery($message, $type);

    $html = '';
    $text = '';

    switch ($type) {
        case 'cas':
            $cas    = extractCAS($message);
            $result = fetchChemicalByCAS($cas);
            if ($result) {
                $html = renderChemicalFull($result, $lang);
                $text = buildPlainText($result['chemical'], $lang);
            } else {
                $html = renderNotFound($message, $lang, 'cas');
                $text = 'Not found';
            }
            break;

        case 'formula':
            // Try to extract formula token after keyword prefix first
            if (preg_match('/(?:สูตร(?:เคมี)?|formula(?:\s+of)?)\s+([A-Za-z][A-Za-z0-9]{1,14})/ui', $message, $fmMatch)) {
                $formula = $fmMatch[1];
            } else {
                $formula = preg_replace('/[^a-zA-Z0-9]/', '', cleanSearchTerm($message, ['สูตร','สูตรเคมี','formula','ค้น','หา','ข้อมูล']));
            }
            if ($formula === '') $formula = $message;
            // Try exact match first
            $result = fetchChemicalByFormula($formula);
            if (!$result) {
                // Try case-insensitive search
                $result = fetchChemicalByFormulaCI($formula);
            }
            if ($result) {
                $html = renderChemicalFull($result, $lang);
                $text = buildPlainText($result['chemical'], $lang);
            } else {
                // Try as name search
                $results = searchChemicalsByTerm($formula, 8);
                if (!empty($results)) {
                    $html = renderChemicalList($results, $formula, $lang);
                    $text = 'Found results';
                } else {
                    $html = renderNotFound($message, $lang, 'formula');
                    $text = 'Not found';
                }
            }
            break;

        case 'hazard':
            $term   = cleanSearchTerm($message, ['อันตราย','hazard','ghs','pictogram','signal word','h-statement','p-statement','toxic','corrosive','flammable','explosive','poison']);
            if ($term === '') $term = $message; // fallback to full message if term is empty
            $result = null;
            $like = '%' . $term . '%';
            // Try exact/LIKE search first
            $chem = Database::fetch("
                SELECT c.*, cc.name as category_name FROM chemicals c
                LEFT JOIN chemical_categories cc ON c.category_id = cc.id
                WHERE c.is_active = 1 AND (c.name LIKE :n OR c.cas_number LIKE :c OR c.molecular_formula LIKE :f OR c.iupac_name LIKE :i)
                ORDER BY CASE WHEN c.name LIKE :n THEN 0 WHEN c.cas_number LIKE :c THEN 1 ELSE 2 END LIMIT 1
            ", [':n' => $like, ':c' => $like, ':f' => $like, ':i' => $like]);
            if ($chem) $result = ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
            // If still not found and user just typed a hazard keyword without a chemical name,
            // show the most dangerous chemicals in the database as a fallback
            if (!$result) {
                $chem = Database::fetch("
                    SELECT c.*, cc.name as category_name FROM chemicals c
                    LEFT JOIN chemical_categories cc ON c.category_id = cc.id
                    WHERE c.is_active = 1 AND c.signal_word = 'Danger'
                    ORDER BY c.name LIMIT 1
                ");
                if ($chem) $result = ['chemical' => $chem, 'containers' => fetchContainersForChem($chem['id'])];
            }
            if ($result) {
                $html = renderHazardFull($result['chemical'], $lang);
                $text = 'Hazard info';
            } else {
                $html = renderNotFound($message, $lang, 'hazard');
                $text = 'Not found';
            }
            break;

        case 'sds':
            $term = cleanSearchTerm($message, ['sds','msds','ความปลอดภัย','safety','เอกสาร','sheet','ดาวน์โหลด','ดู']);
            if (mb_strlen($term, 'UTF-8') < 2) $term = $message;
            $like = '%' . $term . '%';
            $chem = Database::fetch("
                SELECT c.*, cc.name as category_name FROM chemicals c
                LEFT JOIN chemical_categories cc ON c.category_id = cc.id
                WHERE c.is_active = 1 AND (c.name LIKE :n OR c.cas_number LIKE :c OR c.molecular_formula LIKE :f)
                ORDER BY c.name LIMIT 1
            ", [':n' => $like, ':c' => $like, ':f' => $like]);
            if ($chem) {
                $html = renderSDSFull($chem, $lang);
                $text = 'SDS info';
            } else {
                $html = renderNotFound($message, $lang, 'sds');
                $text = 'Not found';
            }
            break;

        case 'location':
            $term = cleanSearchTerm($message, [
                'อยู่ที่ไหน','อยู่ไหน','ที่ไหน','ตำแหน่ง','จัดเก็บที่ไหน','จัดเก็บ',
                'เก็บที่ไหน','เก็บอยู่ที่ไหน','เก็บ','อยู่','where is','where are',
                'location','stored','คลัง','ห้อง','ตู้','ชั้น','find location',
            ]);
            if (mb_strlen($term, 'UTF-8') < 2) $term = $message;
            $results = searchChemicalsByTerm($term, 10);
            // Also try without spaces (e.g. "เอทานอล" if term has trailing space)
            if (empty($results)) {
                $results = searchChemicalsByTerm(trim($term), 10);
            }
            $withLoc = array_filter($results, fn($r) => !empty($r['containers']));
            if (!empty($withLoc)) {
                $html = renderLocationResults(array_values($withLoc), $term, $lang);
                $text = 'Location found';
            } elseif (!empty($results)) {
                // Chemical found but no containers yet
                $html = renderLocationNoStock(array_values($results), $term, $lang);
                $text = 'No stock';
            } else {
                $html = renderNotFound($message, $lang, 'location');
                $text = 'Not found';
            }
            break;

        case 'expiry':
            $html = renderExpiryReport($lang);
            $text = 'Expiry report';
            break;

        case 'stock':
            // Check if also a name search (e.g. "สต็อก Ethanol")
            $stockTerm = cleanSearchTerm($message, ['สต็อก','สต๊อก','คงเหลือน้อย','low stock','stock',
                'สินค้าคงคลัง','inventory','ปริมาณน้อย','ของน้อย','almost out',
                'ทั้งหมดในคลัง','ทั้งหมด','all chemical','รายการสาร','รายการทั้งหมด']);
            if (mb_strlen($stockTerm, 'UTF-8') >= 2 && $stockTerm !== $message) {
                // Stock of a specific chemical
                $results = searchChemicalsByTerm($stockTerm, 8);
                if (!empty($results)) {
                    $html = renderChemicalList($results, $stockTerm, $lang);
                } else {
                    $html = renderNotFound($message, $lang, 'name');
                }
            } else {
                $html = renderStockSummary($lang);
            }
            $text = 'Stock info';
            break;

        case 'model3d':
            $term = cleanSearchTerm($message, ['โมเดล','3d','model','มีไหม','มีหรือไม่','ดูได้ไหม',
                '3 มิติ','3มิติ','glb','gltf','ar','vrx','preview','แสดง','ดู','เปิด','โหลด']);
            if (mb_strlen($term, 'UTF-8') < 2) $term = $message;
            $results = searchChemicalsByTerm($term, 6);
            if (!empty($results)) {
                $html = renderModel3DResult($results, $term, $lang);
                $text = '3D model info';
            } else {
                $html = renderNotFound($term, $lang, 'model3d');
                $text = 'Not found';
            }
            break;

        case 'storage_browse':
            $html = renderStorageBrowser($lang);
            $text = 'Storage browser';
            break;

        case 'periodic_table':
            $html = renderPeriodicTable($lang);
            $text = 'Periodic table';
            break;

        case 'name':
        default:
            $term = cleanSearchTerm($message);
            if (mb_strlen($term, 'UTF-8') < 2) $term = $message;
            $results = searchChemicalsByTerm($term, 10);
            if (!empty($results)) {
                if (count($results) === 1) {
                    $html = renderChemicalFull($results[0], $lang);
                } else {
                    $html = renderChemicalList($results, $term, $lang);
                }
                $text = 'Found results';
            } else {
                $html = renderNotFound($message, $lang, 'name');
                $text = 'Not found';
            }
            break;
    }

    return [
        'success'    => true,
        'response'   => $text,
        'html'       => $html,
        'query_type' => $type,
    ];
}

// ═══════════════════════════════════════════════════════════════════
// PERIODIC TABLE
// ═══════════════════════════════════════════════════════════════════

function renderPeriodicTable(string $lang): string {
    $th = $lang === 'th';

    // ── Element data ────────────────────────────────────────────────
    // [Z, Symbol, Name_EN, Name_TH, Period, Group, Category, Weight, State, Config]
    $elements = [
        [1,'H','Hydrogen','ไฮโดรเจน',1,1,'nonmetal',1.008,'gas','1s¹'],
        [2,'He','Helium','ฮีเลียม',1,18,'noble-gas',4.003,'gas','1s²'],
        [3,'Li','Lithium','ลิเธียม',2,1,'alkali',6.941,'solid','[He]2s¹'],
        [4,'Be','Beryllium','เบริลเลียม',2,2,'alkaline-earth',9.012,'solid','[He]2s²'],
        [5,'B','Boron','โบรอน',2,13,'metalloid',10.81,'solid','[He]2s²2p¹'],
        [6,'C','Carbon','คาร์บอน',2,14,'nonmetal',12.011,'solid','[He]2s²2p²'],
        [7,'N','Nitrogen','ไนโตรเจน',2,15,'nonmetal',14.007,'gas','[He]2s²2p³'],
        [8,'O','Oxygen','ออกซิเจน',2,16,'nonmetal',15.999,'gas','[He]2s²2p⁴'],
        [9,'F','Fluorine','ฟลูอรีน',2,17,'halogen',18.998,'gas','[He]2s²2p⁵'],
        [10,'Ne','Neon','นีอน',2,18,'noble-gas',20.180,'gas','[He]2s²2p⁶'],
        [11,'Na','Sodium','โซเดียม',3,1,'alkali',22.990,'solid','[Ne]3s¹'],
        [12,'Mg','Magnesium','แมกนีเซียม',3,2,'alkaline-earth',24.305,'solid','[Ne]3s²'],
        [13,'Al','Aluminum','อลูมิเนียม',3,13,'post-transition',26.982,'solid','[Ne]3s²3p¹'],
        [14,'Si','Silicon','ซิลิคอน',3,14,'metalloid',28.086,'solid','[Ne]3s²3p²'],
        [15,'P','Phosphorus','ฟอสฟอรัส',3,15,'nonmetal',30.974,'solid','[Ne]3s²3p³'],
        [16,'S','Sulfur','ซัลเฟอร์',3,16,'nonmetal',32.06,'solid','[Ne]3s²3p⁴'],
        [17,'Cl','Chlorine','คลอรีน',3,17,'halogen',35.45,'gas','[Ne]3s²3p⁵'],
        [18,'Ar','Argon','อาร์กอน',3,18,'noble-gas',39.948,'gas','[Ne]3s²3p⁶'],
        [19,'K','Potassium','โพแตสเซียม',4,1,'alkali',39.098,'solid','[Ar]4s¹'],
        [20,'Ca','Calcium','แคลเซียม',4,2,'alkaline-earth',40.078,'solid','[Ar]4s²'],
        [21,'Sc','Scandium','สแกนเดียม',4,3,'transition',44.956,'solid','[Ar]3d±4s²'],
        [22,'Ti','Titanium','ไทเตเนียม',4,4,'transition',47.867,'solid','[Ar]3d²4s²'],
        [23,'V','Vanadium','วานาเดียม',4,5,'transition',50.942,'solid','[Ar]3d³4s²'],
        [24,'Cr','Chromium','โครเมียม',4,6,'transition',51.996,'solid','[Ar]3d⁵ 4s¹'],
        [25,'Mn','Manganese','แมงกานีส',4,7,'transition',54.938,'solid','[Ar]3d⁵ 4s²'],
        [26,'Fe','Iron','เหล็ก',4,8,'transition',55.845,'solid','[Ar]3d⁶ 4s²'],
        [27,'Co','Cobalt','โคบอลต์',4,9,'transition',58.933,'solid','[Ar]3d⁷ 4s²'],
        [28,'Ni','Nickel','นิเกิล',4,10,'transition',58.693,'solid','[Ar]3d⁸ 4s²'],
        [29,'Cu','Copper','ทองแดง',4,11,'transition',63.546,'solid','[Ar]3d¹⁰ 4s¹'],
        [30,'Zn','Zinc','สังกะสี',4,12,'transition',65.38,'solid','[Ar]3d¹⁰ 4s²'],
        [31,'Ga','Gallium','แกลเลียม',4,13,'post-transition',69.723,'solid','[Ar]3d¹⁰ 4s²4p¹'],
        [32,'Ge','Germanium','เจอร์มาเนียม',4,14,'metalloid',72.630,'solid','[Ar]3d¹⁰ 4s²4p²'],
        [33,'As','Arsenic','อาร์เซนิก',4,15,'metalloid',74.922,'solid','[Ar]3d¹⁰ 4s²4p³'],
        [34,'Se','Selenium','ซีเลเนียม',4,16,'nonmetal',78.971,'solid','[Ar]3d¹⁰ 4s²4p⁴'],
        [35,'Br','Bromine','โบรมีน',4,17,'halogen',79.904,'liquid','[Ar]3d¹⁰ 4s²4p⁵'],
        [36,'Kr','Krypton','คริปตอน',4,18,'noble-gas',83.798,'gas','[Ar]3d¹⁰ 4s²4p⁶'],
        [37,'Rb','Rubidium','รูบิเดียม',5,1,'alkali',85.468,'solid','[Kr]5s¹'],
        [38,'Sr','Strontium','สทรอนเชียม',5,2,'alkaline-earth',87.62,'solid','[Kr]5s²'],
        [39,'Y','Yttrium','อิตเตรียม',5,3,'transition',88.906,'solid','[Kr]4d±5s²'],
        [40,'Zr','Zirconium','เซอร์โคเนียม',5,4,'transition',91.224,'solid','[Kr]4d²5s²'],
        [41,'Nb','Niobium','ไนโอเบียม',5,5,'transition',92.906,'solid','[Kr]4d⁴ 5s¹'],
        [42,'Mo','Molybdenum','โมลิบดีนัม',5,6,'transition',95.95,'solid','[Kr]4d⁵ 5s¹'],
        [43,'Tc','Technetium','เทคนีเชียม',5,7,'transition',97,'solid','[Kr]4d⁵ 5s²'],
        [44,'Ru','Ruthenium','รูเทนียม',5,8,'transition',101.07,'solid','[Kr]4d⁷ 5s¹'],
        [45,'Rh','Rhodium','โรเดียม',5,9,'transition',102.91,'solid','[Kr]4d⁸ 5s¹'],
        [46,'Pd','Palladium','พัลลาเดียม',5,10,'transition',106.42,'solid','[Kr]4d¹⁰'],
        [47,'Ag','Silver','เงิน',5,11,'transition',107.87,'solid','[Kr]4d¹⁰ 5s¹'],
        [48,'Cd','Cadmium','แคดเมียม',5,12,'transition',112.41,'solid','[Kr]4d¹⁰ 5s²'],
        [49,'In','Indium','อินเดียม',5,13,'post-transition',114.82,'solid','[Kr]4d¹⁰ 5s²5p¹'],
        [50,'Sn','Tin','ดีบุก',5,14,'post-transition',118.71,'solid','[Kr]4d¹⁰ 5s²5p²'],
        [51,'Sb','Antimony','แอนติมอนี',5,15,'metalloid',121.76,'solid','[Kr]4d¹⁰ 5s²5p³'],
        [52,'Te','Tellurium','เทเลอรียม',5,16,'metalloid',127.60,'solid','[Kr]4d¹⁰ 5s²5p⁴'],
        [53,'I','Iodine','ไอโอดีน',5,17,'halogen',126.90,'solid','[Kr]4d¹⁰ 5s²5p⁵'],
        [54,'Xe','Xenon','ซีนอน',5,18,'noble-gas',131.29,'gas','[Kr]4d¹⁰ 5s²5p⁶'],
        [55,'Cs','Cesium','ซีเซียม',6,1,'alkali',132.91,'solid','[Xe]6s¹'],
        [56,'Ba','Barium','แบเรียม',6,2,'alkaline-earth',137.33,'solid','[Xe]6s²'],
        [57,'La','Lanthanum','แลนทานัม',6,3,'lanthanide',138.91,'solid','[Xe]5d±6s²'],
        [58,'Ce','Cerium','ซีเรียม',6,null,'lanthanide',140.12,'solid','[Xe]4f±5d±6s²'],
        [59,'Pr','Praseodymium','พราซิโอดีเมียม',6,null,'lanthanide',140.91,'solid','[Xe]4f³6s²'],
        [60,'Nd','Neodymium','นีโอไดเมียม',6,null,'lanthanide',144.24,'solid','[Xe]4f⁴ 6s²'],
        [61,'Pm','Promethium','โพรเมเธียม',6,null,'lanthanide',145,'solid','[Xe]4f⁵ 6s²'],
        [62,'Sm','Samarium','แซมาเรียม',6,null,'lanthanide',150.36,'solid','[Xe]4f⁶ 6s²'],
        [63,'Eu','Europium','ยูโรเปียม',6,null,'lanthanide',151.96,'solid','[Xe]4f⁷ 6s²'],
        [64,'Gd','Gadolinium','แกโดลิเนียม',6,null,'lanthanide',157.25,'solid','[Xe]4f⁷ 5d±6s²'],
        [65,'Tb','Terbium','เทอร์เบียม',6,null,'lanthanide',158.93,'solid','[Xe]4f⁹ 6s²'],
        [66,'Dy','Dysprosium','ดิสโพรสียม',6,null,'lanthanide',162.50,'solid','[Xe]4f¹⁰ 6s²'],
        [67,'Ho','Holmium','โฮลเมียม',6,null,'lanthanide',164.93,'solid','[Xe]4f¹¹ 6s²'],
        [68,'Er','Erbium','เออร์เบียม',6,null,'lanthanide',167.26,'solid','[Xe]4f¹² 6s²'],
        [69,'Tm','Thulium','ทูเลียม',6,null,'lanthanide',168.93,'solid','[Xe]4f¹³ 6s²'],
        [70,'Yb','Ytterbium','อิตเตอร์เบียม',6,null,'lanthanide',173.04,'solid','[Xe]4f¹⁴ 6s²'],
        [71,'Lu','Lutetium','ลูทีเชียม',6,null,'lanthanide',174.97,'solid','[Xe]4f¹⁴ 5d±6s²'],
        [72,'Hf','Hafnium','แฮฟเนียม',6,4,'transition',178.49,'solid','[Xe]4f¹⁴ 5d²6s²'],
        [73,'Ta','Tantalum','แทนทาลัม',6,5,'transition',180.95,'solid','[Xe]4f¹⁴ 5d³6s²'],
        [74,'W','Tungsten','ทังสเตน',6,6,'transition',183.84,'solid','[Xe]4f¹⁴ 5d⁴ 6s²'],
        [75,'Re','Rhenium','รีเนียม',6,7,'transition',186.21,'solid','[Xe]4f¹⁴ 5d⁵ 6s²'],
        [76,'Os','Osmium','ออสเมียม',6,8,'transition',190.23,'solid','[Xe]4f¹⁴ 5d⁶ 6s²'],
        [77,'Ir','Iridium','อิริเดียม',6,9,'transition',192.22,'solid','[Xe]4f¹⁴ 5d⁷ 6s²'],
        [78,'Pt','Platinum','แพลตินัม',6,10,'transition',195.08,'solid','[Xe]4f¹⁴ 5d⁹ 6s¹'],
        [79,'Au','Gold','ทองคำ',6,11,'transition',196.97,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s¹'],
        [80,'Hg','Mercury','ปรอท',6,12,'transition',200.59,'liquid','[Xe]4f¹⁴ 5d¹⁰ 6s²'],
        [81,'Tl','Thallium','ทัลเลียม',6,13,'post-transition',204.38,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s²6p¹'],
        [82,'Pb','Lead','ตะกั่ว',6,14,'post-transition',207.2,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s²6p²'],
        [83,'Bi','Bismuth','บิสมัท',6,15,'post-transition',208.98,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s²6p³'],
        [84,'Po','Polonium','โพโลเนียม',6,16,'metalloid',209,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s²6p⁴'],
        [85,'At','Astatine','อะสแตตีน',6,17,'halogen',210,'solid','[Xe]4f¹⁴ 5d¹⁰ 6s²6p⁵'],
        [86,'Rn','Radon','เรดอน',6,18,'noble-gas',222,'gas','[Xe]4f¹⁴ 5d¹⁰ 6s²6p⁶'],
        [87,'Fr','Francium','แฟรนเซียม',7,1,'alkali',223,'solid','[Rn]7s¹'],
        [88,'Ra','Radium','เรเดียม',7,2,'alkaline-earth',226,'solid','[Rn]7s²'],
        [89,'Ac','Actinium','แอคติเนียม',7,3,'actinide',227,'solid','[Rn]6d±7s²'],
        [90,'Th','Thorium','ทอเรียม',7,null,'actinide',232.04,'solid','[Rn]6d²7s²'],
        [91,'Pa','Protactinium','โพรแทคติเนียม',7,null,'actinide',231.04,'solid','[Rn]5f²6d±7s²'],
        [92,'U','Uranium','ยูเรเนียม',7,null,'actinide',238.03,'solid','[Rn]5f³6d±7s²'],
        [93,'Np','Neptunium','เนปจูนียม',7,null,'actinide',237,'solid','[Rn]5f⁴ 6d±7s²'],
        [94,'Pu','Plutonium','พลูโตเนียม',7,null,'actinide',244,'solid','[Rn]5f⁶ 7s²'],
        [95,'Am','Americium','อเมริซีเยียม',7,null,'actinide',243,'solid','[Rn]5f⁷ 7s²'],
        [96,'Cm','Curium','คิวรียม',7,null,'actinide',247,'solid','[Rn]5f⁷ 6d±7s²'],
        [97,'Bk','Berkelium','เบิร์กีเลียม',7,null,'actinide',247,'solid','[Rn]5f⁹ 7s²'],
        [98,'Cf','Californium','คาลีฟอร์เนียม',7,null,'actinide',251,'solid','[Rn]5f¹⁰ 7s²'],
        [99,'Es','Einsteinium','ไอนสไตนีเยียม',7,null,'actinide',252,'solid','[Rn]5f¹¹ 7s²'],
        [100,'Fm','Fermium','เฟอร์เมียม',7,null,'actinide',257,'solid','[Rn]5f¹² 7s²'],
        [101,'Md','Mendelevium','เมนเดเลเวียม',7,null,'actinide',258,'solid','[Rn]5f¹³ 7s²'],
        [102,'No','Nobelium','โนเบเลียม',7,null,'actinide',259,'solid','[Rn]5f¹⁴ 7s²'],
        [103,'Lr','Lawrencium','ลอร์เรนซียม',7,3,'actinide',262,'solid','[Rn]5f¹⁴ 7s²7p¹'],
        [104,'Rf','Rutherfordium','รัตเดอร์ฟอร์เดียม',7,4,'transition',267,'solid','[Rn]5f¹⁴ 6d²7s²'],
        [105,'Db','Dubnium','ดูบนียม',7,5,'transition',268,'solid',''],
        [106,'Sg','Seaborgium','ซีบอร์กียม',7,6,'transition',271,'solid',''],
        [107,'Bh','Bohrium','โบฮรียม',7,7,'transition',272,'solid',''],
        [108,'Hs','Hassium','แฮสเซียม',7,8,'transition',270,'solid',''],
        [109,'Mt','Meitnerium','ไมต์เนอรียม',7,9,'unknown',278,'solid',''],
        [110,'Ds','Darmstadtium','ดาร์มสตัดทียม',7,10,'unknown',281,'solid',''],
        [111,'Rg','Roentgenium','เรนต์เจนียม',7,11,'unknown',282,'solid',''],
        [112,'Cn','Copernicium','โคเปอร์นิคียม',7,12,'transition',285,'solid',''],
        [113,'Nh','Nihonium','นิฮอนียม',7,13,'post-transition',286,'solid',''],
        [114,'Fl','Flerovium','เฟลอโรเวียม',7,14,'post-transition',289,'solid',''],
        [115,'Mc','Moscovium','มอสโคเวียม',7,15,'post-transition',290,'solid',''],
        [116,'Lv','Livermorium','ลิฟวิไมอรียม',7,16,'post-transition',293,'solid',''],
        [117,'Ts','Tennessine','เทนเนสซีน',7,17,'halogen',294,'solid',''],
        [118,'Og','Oganesson','โอกาเนสอน',7,18,'noble-gas',294,'solid',''],
    ];

    // Category colors
    $catStyle = [
        'alkali'          => ['#fef2f2','#dc2626','#fee2e2'],
        'alkaline-earth'  => ['#fff7ed','#ea580c','#fed7aa'],
        'transition'      => ['#eff6ff','#2563eb','#bfdbfe'],
        'post-transition' => ['#f0fdf4','#16a34a','#bbf7d0'],
        'metalloid'       => ['#faf5ff','#9333ea','#e9d5ff'],
        'nonmetal'        => ['#f0fdfa','#0d9488','#99f6e4'],
        'halogen'         => ['#fff1f2','#e11d48','#fecdd3'],
        'noble-gas'       => ['#f0f9ff','#0284c7','#bae6fd'],
        'lanthanide'      => ['#fdf4ff','#a21caf','#f0abfc'],
        'actinide'        => ['#fefce8','#ca8a04','#fef08a'],
        'unknown'         => ['#f8fafc','#64748b','#e2e8f0'],
    ];

    // ── Build grid map: period × group → element ───────────────────
    $grid = []; // $grid[period][group] = element
    $lanthanides = []; $actinides = [];
    foreach ($elements as $e) {
        [$z, $sym, $nameEn, $nameTh, $period, $group, $cat, $weight, $state, $config] = $e;
        if ($cat === 'lanthanide') { $lanthanides[] = $e; continue; }
        if ($cat === 'actinide')   { $actinides[]   = $e; continue; }
        if ($group) $grid[$period][$group] = $e;
    }

    // ── Unique ID for this instance ────────────────────────────────
    $uid = 'pt_' . uniqid();

    $h = '';

    // ── Styles ────────────────────────────────────────────────────
    $h .= '<style>
.pt-wrap{font-family:"Inter","Noto Sans Thai",sans-serif;padding:4px 0;transition:background .3s,border-radius .3s}
.pt-header{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.pt-hdr-left{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.pt-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e40af);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 14px rgba(30,64,175,.35)}
.pt-htxt h3{font-size:16px;font-weight:800;color:#1e293b;margin:0;line-height:1.2}
.pt-htxt p{font-size:11px;color:#94a3b8;margin:3px 0 0;line-height:1.4}
.pt-ctrls{display:flex;align-items:center;gap:5px;flex-wrap:wrap;padding-top:2px}
.pt-btn{padding:5px 10px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;font-family:inherit;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;user-select:none;line-height:1}
.pt-btn:hover{border-color:#94a3b8;color:#334155;transform:translateY(-1px)}
.pt-btn.pton{background:#0f172a;color:#fff;border-color:#0f172a;box-shadow:0 2px 8px rgba(15,23,42,.3)}
.pt-wrap.pt-dark{background:#0f172a;border-radius:16px;padding:14px 16px}
.pt-wrap.pt-dark .pt-htxt h3{color:#e2e8f0}
.pt-wrap.pt-dark .pt-htxt p{color:#475569}
.pt-wrap.pt-dark .pt-btn{background:#1e293b;border-color:#334155;color:#94a3b8}
.pt-wrap.pt-dark .pt-btn:hover{background:#334155;color:#e2e8f0}
.pt-wrap.pt-dark .pt-btn.pton{background:#3b82f6;border-color:#3b82f6;color:#fff;box-shadow:0 2px 10px rgba(59,130,246,.35)}
.pt-srch-wrap{position:relative;margin-bottom:10px}
.pt-srch{width:100%;box-sizing:border-box;padding:8px 30px 8px 34px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:12px;font-family:inherit;color:#334155;outline:none;transition:all .15s}
.pt-srch:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.pt-si{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px;pointer-events:none}
.pt-sx{position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:11px;cursor:pointer;background:none;border:none;padding:3px;display:none;line-height:1}
.pt-sx.v{display:block}
.pt-wrap.pt-dark .pt-srch{background:#1e293b;border-color:#334155;color:#e2e8f0}
.pt-wrap.pt-dark .pt-srch:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.pt-wrap.pt-dark .pt-si,.pt-wrap.pt-dark .pt-sx{color:#475569}
.pt-legend{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.pt-leg{padding:3px 9px;border-radius:8px;font-size:10px;font-weight:700;cursor:pointer;transition:all .15s;border:1.5px solid transparent;user-select:none;line-height:1.5}
.pt-leg:hover{transform:translateY(-1px);box-shadow:0 2px 10px rgba(0,0,0,.12)}
.pt-leg.ldim{opacity:.22;transform:none!important;box-shadow:none!important}
.pt-leg.llit{box-shadow:0 3px 12px rgba(0,0,0,.2);transform:translateY(-1px)}
.pt-gnums-w{margin-bottom:0}
.pt-go{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
.pt-go::-webkit-scrollbar{height:4px}
.pt-go::-webkit-scrollbar-track{background:transparent}
.pt-go::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px}
.pt-wrap.pt-dark .pt-go{scrollbar-color:#334155 transparent}
.pt-wrap.pt-dark .pt-go::-webkit-scrollbar-thumb{background:#334155}
.pt-gnums{display:grid;grid-template-columns:repeat(18,minmax(40px,1fr));gap:2px;min-width:720px;padding-bottom:1px}
.pt-gn{text-align:center;font-size:7px;font-weight:700;color:#94a3b8;padding:1px 0}
.pt-wrap.pt-dark .pt-gn{color:#334155}
.pt-grid{display:grid;grid-template-columns:repeat(18,minmax(40px,1fr));gap:2px;min-width:720px;padding-bottom:4px}
.pt-cell{border-radius:5px;padding:3px 1px 2px;text-align:center;cursor:pointer;transition:transform .2s cubic-bezier(.34,1.56,.64,1),box-shadow .2s,opacity .2s;position:relative;border:1.5px solid transparent;min-width:0}
.pt-cell:hover{transform:scale(1.32) translateY(-3px);z-index:20;box-shadow:0 6px 22px rgba(0,0,0,.22),0 0 12px var(--ptg,transparent)}
.pt-cell .z{font-size:7px;color:rgba(0,0,0,.38);line-height:1;text-align:left;padding-left:2px;font-weight:600}
.pt-cell .sym{font-size:13px;font-weight:900;line-height:1.15}
.pt-cell .nm{font-size:6.5px;color:rgba(0,0,0,.5);line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pt-cell .wt{font-size:6px;color:rgba(0,0,0,.38);line-height:1.3}
.pt-cell.empty{background:transparent!important;border:none!important;pointer-events:none!important;box-shadow:none!important;transform:none!important}
.pt-sep{grid-column:1/-1;height:3px}
.pt-cell .sdot{position:absolute;top:2px;right:2px;width:4px;height:4px;border-radius:50%;pointer-events:none}
.sdot.gas{background:#06b6d4;animation:sdG 2s ease-in-out infinite}
.sdot.liquid{background:#60a5fa;animation:sdL 1.8s ease-in-out infinite}
.sdot.solid{background:rgba(0,0,0,.18)}
@keyframes sdG{0%,100%{opacity:.5;transform:scale(1)}50%{opacity:1;transform:scale(1.8)}}
@keyframes sdL{0%,100%{opacity:.5}50%{opacity:1}}
.pt-wrap.pt-dark .pt-cell .z{color:rgba(255,255,255,.35)}
.pt-wrap.pt-dark .pt-cell .nm{color:rgba(255,255,255,.45)}
.pt-wrap.pt-dark .pt-cell .wt{color:rgba(255,255,255,.3)}
.pt-wrap.pt-dark .pt-cell .sdot.solid{background:rgba(255,255,255,.22)}
.pt-wrap.pt-dark .pt-cell[data-cat="alkali"]{background:#450a0a;border-color:#7f1d1d}
.pt-wrap.pt-dark .pt-cell[data-cat="alkali"] .sym{color:#fca5a5}
.pt-wrap.pt-dark .pt-cell[data-cat="alkaline-earth"]{background:#431407;border-color:#7c2d12}
.pt-wrap.pt-dark .pt-cell[data-cat="alkaline-earth"] .sym{color:#fdba74}
.pt-wrap.pt-dark .pt-cell[data-cat="transition"]{background:#0f1e4a;border-color:#1e3a8a}
.pt-wrap.pt-dark .pt-cell[data-cat="transition"] .sym{color:#93c5fd}
.pt-wrap.pt-dark .pt-cell[data-cat="post-transition"]{background:#052e16;border-color:#14532d}
.pt-wrap.pt-dark .pt-cell[data-cat="post-transition"] .sym{color:#86efac}
.pt-wrap.pt-dark .pt-cell[data-cat="metalloid"]{background:#2e1065;border-color:#581c87}
.pt-wrap.pt-dark .pt-cell[data-cat="metalloid"] .sym{color:#d8b4fe}
.pt-wrap.pt-dark .pt-cell[data-cat="nonmetal"]{background:#042f2e;border-color:#134e4a}
.pt-wrap.pt-dark .pt-cell[data-cat="nonmetal"] .sym{color:#5eead4}
.pt-wrap.pt-dark .pt-cell[data-cat="halogen"]{background:#4c0519;border-color:#881337}
.pt-wrap.pt-dark .pt-cell[data-cat="halogen"] .sym{color:#fda4af}
.pt-wrap.pt-dark .pt-cell[data-cat="noble-gas"]{background:#082f49;border-color:#0c4a6e}
.pt-wrap.pt-dark .pt-cell[data-cat="noble-gas"] .sym{color:#7dd3fc}
.pt-wrap.pt-dark .pt-cell[data-cat="lanthanide"]{background:#3b0764;border-color:#6b21a8}
.pt-wrap.pt-dark .pt-cell[data-cat="lanthanide"] .sym{color:#e879f9}
.pt-wrap.pt-dark .pt-cell[data-cat="actinide"]{background:#422006;border-color:#78350f}
.pt-wrap.pt-dark .pt-cell[data-cat="actinide"] .sym{color:#fcd34d}
.pt-wrap.pt-dark .pt-cell[data-cat="unknown"]{background:#1e293b;border-color:#334155}
.pt-wrap.pt-dark .pt-cell[data-cat="unknown"] .sym{color:#94a3b8}
.pt-wrap.pt-dark .pt-cell:hover{box-shadow:0 0 20px var(--ptg,rgba(255,255,255,.1)),0 6px 22px rgba(0,0,0,.5)}
.pt-cell.pdim{opacity:.08;transform:none!important;box-shadow:none!important;transition:opacity .25s}
.pt-so{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
.pt-so::-webkit-scrollbar{height:3px}
.pt-so::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px}
.pt-wrap.pt-dark .pt-so{scrollbar-color:#334155 transparent}
.pt-wrap.pt-dark .pt-so::-webkit-scrollbar-thumb{background:#334155}
.pt-lan-row,.pt-act-row{display:flex;gap:2px;min-width:720px}
.pt-lan-lbl,.pt-act-lbl{width:36px;font-size:8px;font-weight:700;color:#94a3b8;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:2px;flex-shrink:0;line-height:1.4;font-style:italic}
.pt-wrap.pt-dark .pt-lan-lbl,.pt-wrap.pt-dark .pt-act-lbl{color:#475569}
.pt-listview{display:none;margin-top:4px}
.pt-wrap.pt-lm .pt-listview{display:block}
.pt-wrap.pt-lm .pt-gnums-w,.pt-wrap.pt-lm .pt-so{display:none}
.pt-catg{margin-bottom:10px}
.pt-catg-h{padding:5px 12px;border-radius:8px;font-size:11px;font-weight:800;letter-spacing:.3px;margin-bottom:5px;display:flex;align-items:center;gap:8px}
.pt-catg-h::after{content:"";flex:1;height:1px;background:currentColor;opacity:.2}
.pt-listg{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:5px}
.pt-lcel{border-radius:8px;padding:7px 9px;cursor:pointer;transition:all .18s;border:1.5px solid transparent;display:flex;align-items:center;gap:7px}
.pt-lcel:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.14)}
.pt-lsym{font-size:20px;font-weight:900;width:26px;text-align:center;flex-shrink:0;line-height:1}
.pt-linfo{min-width:0;flex:1}
.pt-lname{font-size:10px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.pt-lz{font-size:9.5px;opacity:.55;line-height:1.3}
.pt-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.pt-ov.show{display:flex;animation:ptfi .2s ease}
@keyframes ptfi{from{opacity:0}to{opacity:1}}
.pt-mbox{width:min(430px,100%);border-radius:22px;overflow:hidden;box-shadow:0 32px 100px rgba(0,0,0,.45);animation:ptsi .28s cubic-bezier(.34,1.56,.64,1)}
@keyframes ptsi{from{opacity:0;transform:scale(.84) translateY(30px)}to{opacity:1;transform:none}}
.pt-mhdr{padding:20px 18px 14px;position:relative}
.pt-mtop{display:flex;align-items:center;gap:14px;margin-bottom:8px}
.pt-atom{width:68px;height:68px;flex-shrink:0}
.pt-mclose{position:absolute;top:12px;right:12px;width:32px;height:32px;border:none;border-radius:10px;background:rgba(0,0,0,.12);color:rgba(0,0,0,.55);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:.15s}
.pt-mclose:hover{background:rgba(0,0,0,.22);transform:scale(1.08)}
.pt-mz{font-size:11px;font-weight:700;opacity:.55;margin-bottom:2px}
.pt-msym{font-size:52px;font-weight:900;line-height:1;letter-spacing:-2px;opacity:.9}
.pt-mname{font-size:17px;font-weight:800;opacity:.88;line-height:1.2;margin-bottom:1px}
.pt-mnth{font-size:12px;opacity:.5}
.pt-mbody{background:#fff;padding:15px 18px 18px}
.pt-irow{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:10px}
.pt-ic{background:#f8fafc;border-radius:10px;padding:8px 10px}
.pt-il{font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.pt-iv{font-size:13px;font-weight:700;color:#1e293b}
.pt-ecfg{background:#f1f5f9;border-radius:8px;padding:8px 11px;font-family:monospace;font-size:11.5px;color:#334155;margin-bottom:10px;word-break:break-all;letter-spacing:.3px}
.pt-badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:700;margin-bottom:2px}
.pt-dbsec{border-top:1px solid #e2e8f0;padding-top:10px}
.pt-dblbl{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;display:flex;align-items:center;gap:5px}
.pt-dbload{color:#94a3b8;font-size:12px;text-align:center;padding:8px}
.pt-dbfound{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:9px;font-size:12px;color:#166534;display:flex;align-items:center;gap:8px;cursor:pointer;transition:.15s}
.pt-dbfound:hover{background:#dcfce7}
.pt-dbnone{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px;font-size:12px;color:#dc2626;text-align:center}
.pt-gosearch{width:100%;padding:9px;background:#10b981;color:#fff;border:none;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;font-family:inherit;margin-top:8px}
.pt-gosearch:hover{background:#059669;transform:translateY(-1px)}
@keyframes ptPulse{0%{transform:scale(1)}25%{transform:scale(1.55) translateY(-4px)}75%{transform:scale(1.35) translateY(-2px)}100%{transform:scale(1)}}
@media(max-width:600px){
.pt-grid,.pt-gnums{grid-template-columns:repeat(18,minmax(34px,1fr));gap:1.5px;min-width:620px}
.pt-lan-row,.pt-act-row{min-width:620px}
.pt-cell{padding:2px 0 1px}
.pt-cell .sym{font-size:11px}
.pt-cell .z{font-size:6px}
.pt-cell .nm,.pt-cell .wt{display:none}
.pt-cell .sdot{width:3px;height:3px}
.pt-header{flex-direction:column;gap:6px}
.pt-hdr-left{flex:none;width:100%}
.pt-ctrls{width:100%;justify-content:flex-end}
.pt-htxt h3{font-size:14px}
.pt-listg{grid-template-columns:repeat(auto-fill,minmax(95px,1fr));gap:4px}
}
</style>';

    // ── Category labels (shared by legend + list view) ─────────────
    $catLabel = [
        'alkali'         => $th?'โลหะอัลคาไล':'Alkali Metal',
        'alkaline-earth' => $th?'โลหะอัลคาไลน์เอิร์ท':'Alkaline Earth',
        'transition'     => $th?'โลหะทรานซิชัน':'Transition Metal',
        'post-transition'=> $th?'Post-transition':'Post-transition',
        'metalloid'      => $th?'กึ่งโลหะ':'Metalloid',
        'nonmetal'       => $th?'อโลหะ':'Nonmetal',
        'halogen'        => $th?'แฮโลเจน':'Halogen',
        'noble-gas'      => $th?'แก๊สมีตระกูล':'Noble Gas',
        'lanthanide'     => $th?'แลนทาไนด์':'Lanthanide',
        'actinide'       => $th?'แอกทิไนด์':'Actinide',
    ];

    // ── Table HTML ─────────────────────────────────────────────────
    $h .= '<div class="pt-wrap" id="' . $uid . '">';

    // Header + controls
    $h .= '<div class="pt-header">';
    $h .= '<div class="pt-hdr-left">';
    $h .= '<div class="pt-icon">⚗️</div>';
    $h .= '<div class="pt-htxt"><h3>' . ($th ? 'ตารางธาตุ (Periodic Table)' : 'Periodic Table of Elements') . '</h3>';
    $h .= '<p>' . ($th ? '118 ธาตุ · กดเพื่อดูรายละเอียด · ค้นหา · กรอง' : '118 elements · Click for details · Search · Filter') . '</p></div></div>';
    $h .= '<div class="pt-ctrls">';
    $h .= '<button class="pt-btn" id="' . $uid . '_gbtn" onclick="ptToggleView(\'' . $uid . '\',\'grid\')" title="' . ($th?'ตาราง':'Grid') . '"><i class="fas fa-th"></i></button>';
    $h .= '<button class="pt-btn" id="' . $uid . '_lbtn" onclick="ptToggleView(\'' . $uid . '\',\'list\')" title="' . ($th?'รายการ':'List') . '"><i class="fas fa-list"></i></button>';
    $h .= '<button class="pt-btn" id="' . $uid . '_dbtn" onclick="ptToggleDark(\'' . $uid . '\')" title="' . ($th?'โหมดมืด':'Dark Mode') . '"><i class="fas fa-moon"></i></button>';
    $h .= '<button class="pt-btn" onclick="ptRandom(\'' . $uid . '\')" title="' . ($th?'สุ่มธาตุ':'Random Element') . '"><i class="fas fa-dice"></i></button>';
    $h .= '</div></div>';

    // Search bar
    $h .= '<div class="pt-srch-wrap"><i class="fas fa-search pt-si"></i>';
    $h .= '<input type="text" class="pt-srch" id="' . $uid . '_srch" placeholder="' . ($th?'ค้นหาธาตุ เช่น Iron, Fe, 26...':'Search element e.g. Iron, Fe, 26...') . '" oninput="ptSearch(\'' . $uid . '\',this.value)">';
    $h .= '<button class="pt-sx" id="' . $uid . '_sx" onclick="ptClearSearch(\'' . $uid . '\')">✕</button></div>';

    // Legend (clickable filters)
    $h .= '<div class="pt-legend" id="' . $uid . '_leg">';
    foreach ($catLabel as $cat => $label) {
        [$bg, $col, $bdr] = $catStyle[$cat];
        $h .= "<button class='pt-leg' data-cat='{$cat}' style='background:{$bg};color:{$col};border-color:{$bdr}' onclick='ptFilterCat(\"{$uid}\",\"{$cat}\")'>{$label}</button>";
    }
    $h .= '</div>';

    // Group numbers + Grid in same scroll container
    $h .= '<div class="pt-gnums-w"><div class="pt-go" id="' . $uid . '_go">';
    $h .= '<div class="pt-gnums">';
    for ($g = 1; $g <= 18; $g++) { $h .= "<div class='pt-gn'>{$g}</div>"; }
    $h .= '</div>';

    $h .= '<div class="pt-grid" id="' . $uid . '_grid">';
    for ($period = 1; $period <= 7; $period++) {
        for ($group = 1; $group <= 18; $group++) {
            if (isset($grid[$period][$group])) {
                $e = $grid[$period][$group];
                [$z, $sym, $nameEn, $nameTh, $p, $g, $cat, $weight, $state, $config] = $e;
                [$bg, $col, $bdr] = $catStyle[$cat] ?? $catStyle['unknown'];
                $displayName = mb_substr($th ? $nameTh : $nameEn, 0, 7, 'UTF-8');
                $eJson = htmlspecialchars(json_encode([
                    'z'=>$z,'sym'=>$sym,'en'=>$nameEn,'th'=>$nameTh,
                    'cat'=>$cat,'w'=>$weight,'state'=>$state,'config'=>$config,
                    'period'=>$period,'group'=>$group
                ]), ENT_QUOTES);
                $enEsc = htmlspecialchars($nameEn, ENT_QUOTES);
                $thEsc = htmlspecialchars($nameTh, ENT_QUOTES);
                $h .= "<div class='pt-cell' data-cat='{$cat}' data-z='{$z}' data-sym='{$sym}' data-en='{$enEsc}' data-th='{$thEsc}'
                    style='background:{$bg};border-color:{$bdr};--ptg:{$col}40'
                    data-el='{$eJson}' onclick='ptShowModal(this,\"{$uid}\")'>
                    <div class='z'>{$z}</div>
                    <div class='sym' style='color:{$col}'>{$sym}</div>
                    <div class='nm'>{$displayName}</div>
                    <div class='wt'>" . number_format((float)$weight, 2) . "</div>
                    <span class='sdot {$state}'></span>
                </div>";
            } elseif ($period === 6 && $group === 3) {
                $h .= "<div class='pt-cell' style='background:#fdf4ff;border-color:#f0abfc' onclick='ptScrollSeries(\"{$uid}_lan\")'>
                    <div class='z'></div><div class='sym' style='color:#a21caf;font-size:9px'>57–71</div><div class='nm'>Lan…</div></div>";
            } elseif ($period === 7 && $group === 3) {
                $h .= "<div class='pt-cell' style='background:#fefce8;border-color:#fef08a' onclick='ptScrollSeries(\"{$uid}_act\")'>
                    <div class='z'></div><div class='sym' style='color:#ca8a04;font-size:9px'>89–103</div><div class='nm'>Act…</div></div>";
            } else {
                $h .= "<div class='pt-cell empty'></div>";
            }
        }
        $h .= '<div class="pt-sep"></div>';
    }
    $h .= '</div>'; // pt-grid
    $h .= '</div></div>'; // pt-go + pt-gnums-w

    // Lanthanide series
    $h .= '<div class="pt-so" style="margin-top:4px" id="' . $uid . '_lan"><div class="pt-lan-row">';
    $h .= '<div class="pt-lan-lbl">Lantha-<br>nides</div>';
    foreach ($lanthanides as $e) {
        [$z, $sym, $nameEn, $nameTh, $p, $g, $cat, $weight, $state, $config] = $e;
        [$bg, $col, $bdr] = $catStyle['lanthanide'];
        $displayName = mb_substr($th ? $nameTh : $nameEn, 0, 6, 'UTF-8');
        $eJson = htmlspecialchars(json_encode(['z'=>$z,'sym'=>$sym,'en'=>$nameEn,'th'=>$nameTh,'cat'=>$cat,'w'=>$weight,'state'=>$state,'config'=>$config,'period'=>$p,'group'=>$g]), ENT_QUOTES);
        $enEsc = htmlspecialchars($nameEn, ENT_QUOTES); $thEsc = htmlspecialchars($nameTh, ENT_QUOTES);
        $h .= "<div class='pt-cell' data-cat='lanthanide' data-z='{$z}' data-sym='{$sym}' data-en='{$enEsc}' data-th='{$thEsc}'
            style='background:{$bg};border-color:{$bdr};flex-shrink:0;--ptg:{$col}40' data-el='{$eJson}' onclick='ptShowModal(this,\"{$uid}\")'>
            <div class='z'>{$z}</div><div class='sym' style='color:{$col}'>{$sym}</div><div class='nm'>{$displayName}</div>
            <span class='sdot {$state}'></span></div>";
    }
    $h .= '</div></div>';

    // Actinide series
    $h .= '<div class="pt-so" style="margin-top:3px" id="' . $uid . '_act"><div class="pt-act-row">';
    $h .= '<div class="pt-act-lbl">Acti-<br>nides</div>';
    foreach ($actinides as $e) {
        [$z, $sym, $nameEn, $nameTh, $p, $g, $cat, $weight, $state, $config] = $e;
        [$bg, $col, $bdr] = $catStyle['actinide'];
        $displayName = mb_substr($th ? $nameTh : $nameEn, 0, 6, 'UTF-8');
        $eJson = htmlspecialchars(json_encode(['z'=>$z,'sym'=>$sym,'en'=>$nameEn,'th'=>$nameTh,'cat'=>$cat,'w'=>$weight,'state'=>$state,'config'=>$config,'period'=>$p,'group'=>$g]), ENT_QUOTES);
        $enEsc = htmlspecialchars($nameEn, ENT_QUOTES); $thEsc = htmlspecialchars($nameTh, ENT_QUOTES);
        $h .= "<div class='pt-cell' data-cat='actinide' data-z='{$z}' data-sym='{$sym}' data-en='{$enEsc}' data-th='{$thEsc}'
            style='background:{$bg};border-color:{$bdr};flex-shrink:0;--ptg:{$col}40' data-el='{$eJson}' onclick='ptShowModal(this,\"{$uid}\")'>
            <div class='z'>{$z}</div><div class='sym' style='color:{$col}'>{$sym}</div><div class='nm'>{$displayName}</div>
            <span class='sdot {$state}'></span></div>";
    }
    $h .= '</div></div>';

    // List view (grouped by category, shown when pt-lm class active)
    $catOrder = ['alkali','alkaline-earth','transition','post-transition','metalloid','nonmetal','halogen','noble-gas','lanthanide','actinide','unknown'];
    $byCategory = [];
    foreach ($elements as $e) { $byCategory[$e[6]][] = $e; }

    $h .= '<div class="pt-listview" id="' . $uid . '_lv">';
    foreach ($catOrder as $cat) {
        if (empty($byCategory[$cat])) continue;
        [$bg, $col, $bdr] = $catStyle[$cat];
        $label = $catLabel[$cat] ?? $cat;
        $h .= "<div class='pt-catg'><div class='pt-catg-h' style='background:{$bg};color:{$col}'>{$label}</div><div class='pt-listg'>";
        foreach ($byCategory[$cat] as $e) {
            [$z, $sym, $nameEn, $nameTh, $p, $g2, $cat2, $weight, $state, $config] = $e;
            $displayName = $th ? $nameTh : $nameEn;
            $eJson = htmlspecialchars(json_encode(['z'=>$z,'sym'=>$sym,'en'=>$nameEn,'th'=>$nameTh,'cat'=>$cat,'w'=>$weight,'state'=>$state,'config'=>$config,'period'=>$p,'group'=>$g2]), ENT_QUOTES);
            $enEsc = htmlspecialchars($nameEn, ENT_QUOTES);
            $thEsc = htmlspecialchars($nameTh, ENT_QUOTES);
            $dnEsc = htmlspecialchars($displayName, ENT_QUOTES);
            $h .= "<div class='pt-lcel' data-cat='{$cat}' data-z='{$z}' data-sym='{$sym}' data-en='{$enEsc}' data-th='{$thEsc}'
                style='background:{$bg};border-color:{$bdr}' data-el='{$eJson}' onclick='ptShowModal(this,\"{$uid}\")'>
                <div class='pt-lsym' style='color:{$col}'>{$sym}</div>
                <div class='pt-linfo'><div class='pt-lname'>{$displayName}</div><div class='pt-lz'>Z={$z}</div></div>
            </div>";
        }
        $h .= "</div></div>";
    }
    $h .= '</div>'; // pt-listview

    // Modal
    $h .= '<div class="pt-ov" id="' . $uid . '_ov" onclick="if(event.target===this)ptClose(\'' . $uid . '\')">';
    $h .= '<div class="pt-mbox" id="' . $uid . '_mb">';
    $h .= '<div class="pt-mhdr" id="' . $uid . '_mh">';
    $h .= '<button class="pt-mclose" onclick="ptClose(\'' . $uid . '\')"><i class="fas fa-times"></i></button>';
    $h .= '<div class="pt-mtop"><div class="pt-atom" id="' . $uid . '_atom"></div>';
    $h .= '<div><div class="pt-mz" id="' . $uid . '_mz"></div><div class="pt-msym" id="' . $uid . '_ms"></div></div></div>';
    $h .= '<div class="pt-mname" id="' . $uid . '_mn"></div>';
    $h .= '<div class="pt-mnth" id="' . $uid . '_mt"></div>';
    $h .= '</div>';
    $h .= '<div class="pt-mbody"><div id="' . $uid . '_mi"></div>';
    $h .= '<div class="pt-dbsec"><div class="pt-dblbl"><i class="fas fa-database"></i> ' . ($th?'ในฐานข้อมูล SUT ChemBot':'In SUT ChemBot DB') . '</div>';
    $h .= '<div id="' . $uid . '_mdb"><div class="pt-dbload"><i class="fas fa-circle-notch fa-spin"></i></div></div>';
    $h .= '<button class="pt-gosearch" id="' . $uid . '_mgs"><i class="fas fa-search"></i> ' . ($th?'ค้นหาในระบบ':'Search in System') . '</button>';
    $h .= '</div></div></div></div>'; // dbsec + mbody + mbox + ov
    $h .= '</div>'; // pt-wrap

    // ── JavaScript ────────────────────────────────────────────────
    $h .= '<script>(function(){
var UID=' . json_encode($uid) . ',LANG=' . json_encode($lang) . ';
var T=function(t,e){return LANG===\'th\'?t:e;};
var CC={alkali:\'#dc2626\',\'alkaline-earth\':\'#ea580c\',transition:\'#2563eb\',\'post-transition\':\'#16a34a\',metalloid:\'#9333ea\',nonmetal:\'#0d9488\',halogen:\'#e11d48\',\'noble-gas\':\'#0284c7\',lanthanide:\'#a21caf\',actinide:\'#ca8a04\',unknown:\'#64748b\'};
var CB={alkali:\'#fef2f2\',\'alkaline-earth\':\'#fff7ed\',transition:\'#eff6ff\',\'post-transition\':\'#f0fdf4\',metalloid:\'#faf5ff\',nonmetal:\'#f0fdfa\',halogen:\'#fff1f2\',\'noble-gas\':\'#f0f9ff\',lanthanide:\'#fdf4ff\',actinide:\'#fefce8\',unknown:\'#f8fafc\'};
var CN={alkali:T(\'โลหะอัลคาไล\',\'Alkali Metal\'),\'alkaline-earth\':T(\'อัลคาไลน์เอิร์ท\',\'Alkaline Earth\'),transition:T(\'ทรานซิชัน\',\'Transition Metal\'),\'post-transition\':T(\'Post-transition\',\'Post-transition\'),metalloid:T(\'กึ่งโลหะ\',\'Metalloid\'),nonmetal:T(\'อโลหะ\',\'Nonmetal\'),halogen:T(\'แฮโลเจน\',\'Halogen\'),\'noble-gas\':T(\'แก๊สมีตระกูล\',\'Noble Gas\'),lanthanide:T(\'แลนทาไนด์\',\'Lanthanide\'),actinide:T(\'แอกทิไนด์\',\'Actinide\'),unknown:T(\'ไม่ทราบ\',\'Unknown\')};
var SL={solid:T(\'ของแข็ง\',\'Solid\'),liquid:T(\'ของเหลว\',\'Liquid\'),gas:T(\'แก๊ส\',\'Gas\')};
function g(id){return document.getElementById(id);}
function tx(id,v){var e=g(id);if(e)e.textContent=v;}
function st(id,p,v){var e=g(id);if(e)e.style[p]=v;}

window.ptToggleDark=function(uid){
  var w=g(uid),b=g(uid+\'_dbtn\');
  if(w.classList.toggle(\'pt-dark\')){b.classList.add(\'pton\');b.innerHTML=\'<i class="fas fa-sun"></i>\';}
  else{b.classList.remove(\'pton\');b.innerHTML=\'<i class="fas fa-moon"></i>\';}
};

window.ptToggleView=function(uid,mode){
  var w=g(uid),gb=g(uid+\'_gbtn\'),lb=g(uid+\'_lbtn\');
  if(mode===\'list\'){w.classList.add(\'pt-lm\');lb.classList.add(\'pton\');gb.classList.remove(\'pton\');}
  else{w.classList.remove(\'pt-lm\');gb.classList.add(\'pton\');lb.classList.remove(\'pton\');}
};

var _ac={};
window.ptFilterCat=function(uid,cat){
  var legs=document.querySelectorAll(\'#\'+uid+\' .pt-leg\');
  var cells=document.querySelectorAll(\'#\'+uid+\' [data-z]\');
  if(_ac[uid]===cat){
    _ac[uid]=null;
    legs.forEach(function(l){l.classList.remove(\'llit\',\'ldim\');});
    cells.forEach(function(c){c.classList.remove(\'pdim\');});
  }else{
    _ac[uid]=cat;
    legs.forEach(function(l){
      if(l.dataset.cat===cat){l.classList.add(\'llit\');l.classList.remove(\'ldim\');}
      else{l.classList.add(\'ldim\');l.classList.remove(\'llit\');}
    });
    cells.forEach(function(c){
      if(c.dataset.cat===cat)c.classList.remove(\'pdim\');
      else c.classList.add(\'pdim\');
    });
  }
};

window.ptSearch=function(uid,q){
  q=(q||\'\').trim().toLowerCase();
  var sx=g(uid+\'_sx\');if(sx)sx.classList.toggle(\'v\',q.length>0);
  var cells=document.querySelectorAll(\'#\'+uid+\' [data-z]\');
  if(!q){cells.forEach(function(c){c.classList.remove(\'pdim\');});return;}
  cells.forEach(function(c){
    var z=c.dataset.z||\'\',sym=(c.dataset.sym||\'\').toLowerCase(),en=(c.dataset.en||\'\').toLowerCase(),th=(c.dataset.th||\'\').toLowerCase();
    var m=z===q||sym.startsWith(q)||en.includes(q)||th.includes(q);
    c.classList.toggle(\'pdim\',!m);
  });
};

window.ptClearSearch=function(uid){
  var i=g(uid+\'_srch\');if(i){i.value=\'\';ptSearch(uid,\'\');}
};

window.ptRandom=function(uid){
  var cells=Array.from(document.querySelectorAll(\'#\'+uid+\' .pt-cell:not(.empty):not(.pdim)\'));
  if(!cells.length)return;
  var w=g(uid);
  if(w.classList.contains(\'pt-lm\')){w.classList.remove(\'pt-lm\');g(uid+\'_gbtn\').classList.add(\'pton\');g(uid+\'_lbtn\').classList.remove(\'pton\');}
  var c=cells[Math.floor(Math.random()*cells.length)];
  c.scrollIntoView({behavior:\'smooth\',block:\'center\'});
  setTimeout(function(){
    c.style.animation=\'ptPulse .65s ease\';
    c.addEventListener(\'animationend\',function h(){c.style.animation=\'\';c.removeEventListener(\'animationend\',h);});
    ptShowModal(c,uid);
  },420);
};

window.ptScrollSeries=function(tid){var t=g(tid);if(t)t.scrollIntoView({behavior:\'smooth\',block:\'nearest\'});};

function buildAtom(z,col){
  var shells=[],rem=z,caps=[2,8,18,32];
  for(var i=0;i<4&&rem>0;i++){var n=Math.min(rem,caps[i]);shells.push(n);rem-=n;}
  var max=Math.min(shells.length,4),sz=68,cx=34,cy=34,nr=9,gap=(sz/2-nr-3)/max;
  var s=\'<svg width="\'+sz+\'" height="\'+sz+\'" viewBox="0 0 \'+sz+\' \'+sz+\'">\';
  for(var si=0;si<max;si++){
    var r=nr+(si+1)*gap;
    s+=\'<circle cx="\'+cx+\'" cy="\'+cy+\'" r="\'+r.toFixed(1)+\'" fill="none" stroke="\'+col+\'" stroke-opacity=".22" stroke-width="1"/>\';
    var ne=Math.min(shells[si],8);
    for(var ei=0;ei<ne;ei++){
      var a=(ei/ne)*2*Math.PI-Math.PI/2;
      s+=\'<circle cx="\'+(cx+r*Math.cos(a)).toFixed(1)+\'" cy="\'+(cy+r*Math.sin(a)).toFixed(1)+\'" r="2.2" fill="\'+col+\'" opacity=".85"/>\';
    }
  }
  s+=\'<circle cx="\'+cx+\'" cy="\'+cy+\'" r="\'+nr+\'" fill="\'+col+\'" opacity=".9"/>\';
  s+=\'<text x="\'+cx+\'" y="\'+(cy+3)+\'" text-anchor="middle" fill="white" font-size="7" font-weight="900">\'+z+\'</text></svg>\';
  return s;
}

window.ptShowModal=function(cell,uid){
  var el=JSON.parse(cell.getAttribute(\'data-el\')||\'{}\')||{};
  var cat=el.cat||\'unknown\',col=CC[cat]||\'#64748b\',bg=CB[cat]||\'#f8fafc\';
  var mh=g(uid+\'_mh\');if(mh)mh.style.background=bg;
  var atom=g(uid+\'_atom\');if(atom)atom.innerHTML=buildAtom(el.z,col);
  tx(uid+\'_mz\',\'Z = \'+el.z);st(uid+\'_mz\',\'color\',col);
  tx(uid+\'_ms\',el.sym);st(uid+\'_ms\',\'color\',col);
  tx(uid+\'_mn\',el.en);tx(uid+\'_mt\',el.th);
  var si=el.state===\'gas\'?\'💨\':el.state===\'liquid\'?\'💧\':\'🪨\';
  var badge=\'<span class="pt-badge" style="background:\'+bg+\';color:\'+col+\';border:1px solid \'+col+\'40">\'+( CN[cat]||\'\')+\'</span>\';
  var mi=g(uid+\'_mi\');
  if(mi)mi.innerHTML=\'<div class="pt-irow">\'+
    \'<div class="pt-ic"><div class="pt-il">\'+T(\'น้ำหนักอะตอม\',\'Atomic Weight\')+\'</div><div class="pt-iv">\'+el.w+\' u</div></div>\'+
    \'<div class="pt-ic"><div class="pt-il">\'+T(\'สถานะ\',\'State\')+\'</div><div class="pt-iv">\'+si+\' \'+(SL[el.state]||el.state)+\'</div></div>\'+
    \'<div class="pt-ic"><div class="pt-il">\'+T(\'คาบ\',\'Period\')+\'</div><div class="pt-iv">\'+el.period+\'</div></div>\'+
    \'<div class="pt-ic"><div class="pt-il">\'+T(\'หมู่\',\'Group\')+\'</div><div class="pt-iv">\'+( el.group||\'—\')+\'</div></div>\'+
    \'</div>\'+(el.config?\'<div class="pt-ecfg">e⁻ \'+el.config+\'</div>\':\'\')+badge;
  var mdb=g(uid+\'_mdb\');
  if(mdb)mdb.innerHTML=\'<div class="pt-dbload"><i class="fas fa-circle-notch fa-spin"></i> \'+T(\'กำลังตรวจสอบ...\',\'Checking...\')+\'</div>\';
  var mgs=g(uid+\'_mgs\');
  if(mgs)mgs.onclick=function(){if(typeof sendMsg===\'function\')sendMsg(LANG===\'th\'?el.th:el.en);ptClose(uid);};
  g(uid+\'_ov\').classList.add(\'show\');
  document.body.style.overflow=\'hidden\';
  fetch(\'/v1/api/ai_assistant.php\',{method:\'POST\',headers:{\'Content-Type\':\'application/json\'},
    body:JSON.stringify({action:\'chat_local\',message:LANG===\'th\'?el.th:el.en,lang:LANG})
  }).then(function(r){return r.json();}).then(function(d){
    var db=g(uid+\'_mdb\');if(!db)return;
    if(d.html&&d.html.indexOf(\'no-result-card\')<0){
      db.innerHTML=\'<div class="pt-dbfound" id="\'+uid+\'_dbr"><i class="fas fa-check-circle" style="color:#10b981"></i> \'+T(\'พบในฐานข้อมูล — กดดูรายละเอียด\',\'Found in database — click for details\')+\'</div>\';
      var dbr=g(uid+\'_dbr\');
      if(dbr)dbr.onclick=function(){if(typeof sendMsg===\'function\')sendMsg(LANG===\'th\'?el.th:el.en);ptClose(uid);};
    }else{
      db.innerHTML=\'<div class="pt-dbnone"><i class="fas fa-times-circle"></i> \'+T(\'ไม่มีข้อมูลในฐานข้อมูล\',\'Not in local database\')+\'</div>\';
    }
  }).catch(function(){var db=g(uid+\'_mdb\');if(db)db.innerHTML=\'<div class="pt-dbnone">⚠ Error</div>\';});
};

window.ptClose=function(uid){
  var ov=g(uid+\'_ov\');if(ov)ov.classList.remove(\'show\');
  document.body.style.overflow=\'\';
};

// Stagger entrance animation
var cells2=document.querySelectorAll(\'#\'+UID+\' .pt-cell:not(.empty)\');
cells2.forEach(function(c,i){
  c.style.opacity=\'0\';c.style.transform=\'scale(0.5)\';
  c.style.transition=\'opacity .25s ease,transform .25s cubic-bezier(.34,1.56,.64,1)\';
  setTimeout(function(){c.style.opacity=\'1\';c.style.transform=\'\';},30+(i*4));
});

// ESC closes modal
document.addEventListener(\'keydown\',function(e){
  if(e.key===\'Escape\'){var m=document.querySelector(\'.pt-ov.show\');if(m)ptClose(m.id.replace(\'_ov\',\'\'));}
});

// Init: activate grid button
var igb=g(UID+\'_gbtn\');if(igb)igb.classList.add(\'pton\');
})();</script>';

    return $h;
}


// ═══════════════════════════════════════════════════════════════════
// STORAGE BROWSER RESPONSE
// ═══════════════════════════════════════════════════════════════════

function renderStorageBrowser(string $lang): string {
    $th = $lang === 'th';
    $buildings = getPublicBuildings()['data'] ?? [];

    $colMap = [
        'F0'=>'#6b7280','F1'=>'#3b82f6','F4'=>'#8b5cf6','F5'=>'#f59e0b',
        'F6'=>'#06b6d4','F7'=>'#10b981','F10'=>'#ec4899','F11'=>'#f97316','F12'=>'#dc2626',
    ];

    $totalBottles = array_sum(array_column($buildings, 'bottle_count'));
    $totalRooms   = array_sum(array_column($buildings, 'room_count'));

    $h = '<div class="chembot-response">';

    // Header
    $h .= '<div class="rs-head">';
    $h .= '<div class="rs-head-icon" style="background:#dcfce7;color:#166534"><i class="fas fa-warehouse"></i></div>';
    $h .= '<h3>' . ($th ? '🏢 สถานที่จัดเก็บสารเคมี' : '🏢 Chemical Storage Locations') . '</h3>';
    $h .= '</div>';

    // Summary bar
    $h .= '<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">';
    $h .= '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px">';
    $h .= '<i class="fas fa-building" style="color:var(--ok)"></i>';
    $h .= '<div><div style="font-size:18px;font-weight:900;color:#166534">' . count($buildings) . '</div>';
    $h .= '<div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">' . ($th?'อาคาร':'Buildings') . '</div></div></div>';

    $h .= '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px">';
    $h .= '<i class="fas fa-door-open" style="color:#3b82f6"></i>';
    $h .= '<div><div style="font-size:18px;font-weight:900;color:#1e40af">' . $totalRooms . '</div>';
    $h .= '<div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">' . ($th?'ห้อง':'Rooms') . '</div></div></div>';

    $h .= '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px">';
    $h .= '<i class="fas fa-vial" style="color:#f97316"></i>';
    $h .= '<div><div style="font-size:18px;font-weight:900;color:#c2410c">' . number_format($totalBottles) . '</div>';
    $h .= '<div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">' . ($th?'ขวดสาร':'Bottles') . '</div></div></div>';
    $h .= '</div>';

    // Building grid
    $h .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:16px">';
    foreach ($buildings as $b) {
        $col  = $colMap[$b['code']] ?? '#10b981';
        $code = htmlspecialchars($b['code'], ENT_QUOTES);
        $name = htmlspecialchars($b['name'], ENT_QUOTES);
        $jsCode = addslashes($b['code']);
        $jsName = addslashes($b['name']);
        $h .= "<div onclick=\"openStorageBrowser();setTimeout(()=>sbmLoadRooms({$b['id']},'{$jsCode}','{$jsName}'),320)\"
            style='background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:13px 14px;
                   cursor:pointer;transition:.2s;border-top:3px solid {$col};position:relative'
            onmouseenter=\"this.style.borderColor='{$col}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.1)'\"
            onmouseleave=\"this.style.borderColor='#e5e7eb';this.style.transform='';this.style.boxShadow=''\">
            <div style='font-size:20px;font-weight:900;color:{$col};font-family:monospace;line-height:1'>{$code}</div>
            <div style='font-size:11px;font-weight:600;color:#1e293b;margin-top:4px;line-height:1.3'>{$name}</div>
            <div style='display:flex;gap:5px;margin-top:8px;flex-wrap:wrap'>
                <span style='background:{$col}20;color:{$col};border:1px solid {$col}40;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:700'>
                    <i class='fas fa-vial' style='font-size:8px'></i> " . number_format((int)$b['bottle_count']) . "</span>
                <span style='background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:700'>
                    <i class='fas fa-door-open' style='font-size:8px'></i> {$b['room_count']}</span>
            </div>
            <i class='fas fa-chevron-right' style='position:absolute;bottom:10px;right:10px;color:#cbd5e1;font-size:10px'></i>
        </div>";
    }
    $h .= '</div>';

    // CTA button to open full browser
    $h .= '<div style="text-align:center">';
    $h .= '<button onclick="openStorageBrowser()" style="background:var(--ok);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.15s;font-family:inherit" onmouseenter="this.style.background=\'#059669\'" onmouseleave="this.style.background=\'var(--ok)\'">';
    $h .= '<i class="fas fa-expand-alt"></i> ' . ($th ? 'เปิด Storage Browser เต็มจอ' : 'Open Full Storage Browser') . '</button>';
    $h .= '</div>';

    $h .= '</div>';
    return $h;
}

// ═══════════════════════════════════════════════════════════════════
// SAVE QUERY (continuous learning store)
// ═══════════════════════════════════════════════════════════════════

function saveSearchQuery(string $query, string $type): void {
    // Non-critical analytics — write to PHP error log in a structured way
    // This avoids FK/schema constraints while still capturing search patterns
    try {
        $logEntry = json_encode([
            'ts'    => date('Y-m-d H:i:s'),
            'query' => mb_substr($query, 0, 200, 'UTF-8'),
            'type'  => $type,
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        $logFile = __DIR__ . '/../logs/public_searches.log';
        if (is_writable(dirname($logFile))) {
            file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    } catch (Exception $e) {
        // Non-critical — ignore all errors
    }
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — CHEMICAL FULL (DeepSeek-style hierarchical)
// ═══════════════════════════════════════════════════════════════════

function renderChemicalFull(array $result, string $lang): string {
    global $user;
    $c  = $result['chemical'];
    $cs = $result['containers'];
    $th = $lang === 'th';

    $html = '<div class="chembot-response">';

    // ── 1. CHEMICAL HERO CARD ────────────────────────────────────
    $imgUrl = '';
    if (!empty($c['cas_number'])) {
        $imgUrl = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/' . urlencode($c['cas_number']) . '/PNG?record_type=2d&image_size=300x300';
    } elseif (!empty($c['molecular_formula'])) {
        $imgUrl = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/formula/' . urlencode($c['molecular_formula']) . '/PNG?record_type=2d&image_size=300x300';
    }
    if (!empty($c['image_url'])) $imgUrl = $c['image_url'];

    $signalWord = $c['signal_word'] ?? '';
    $signalClass = ($signalWord === 'Danger') ? 'danger' : (($signalWord === 'Warning') ? 'warning' : '');

    $html .= '<div class="chem-hero">';
    if ($imgUrl) {
        $html .= '<div class="chem-hero-img"><img src="' . h($imgUrl) . '" alt="' . h($c['molecular_formula'] ?? $c['name']) . '" loading="lazy" onerror="this.parentElement.innerHTML=\'<i class=&quot;fas fa-atom&quot; style=&quot;font-size:48px;color:#c7d2fe&quot;></i>\'"></div>';
    }
    $html .= '<div class="chem-hero-info">';
    $html .= '<div class="chem-hero-name">' . h($c['name']) . '</div>';
    $html .= '<div class="chem-pills">';
    if ($c['cas_number'])        $html .= pill('fas fa-hashtag', 'CAS: ' . h($c['cas_number']), 'cas');
    if ($c['molecular_formula']) $html .= pill('fas fa-atom', h($c['molecular_formula']), 'formula');
    if ($c['molecular_weight'])  $html .= pill('fas fa-weight', h($c['molecular_weight']) . ' g/mol', 'mw');
    if ($c['physical_state'])    $html .= pill('fas fa-cube', h(ucfirst($c['physical_state'])), 'state');
    if ($c['category_name'])     $html .= pill('fas fa-tags', h($c['category_name']), 'cat');
    if ($signalClass)            $html .= pill('fas fa-exclamation-triangle', $signalWord, $signalClass);
    $html .= '</div>';
    if (!empty($c['description'])) {
        $html .= '<div class="chem-hero-desc">' . h(mb_substr($c['description'], 0, 220, 'UTF-8')) . (mb_strlen($c['description'], 'UTF-8') > 220 ? '…' : '') . '</div>';
    }
    $html .= '</div></div>'; // chem-basic-info + chem-hero

    // ── 2. PHYSICAL PROPERTIES TABLE ────────────────────────────
    $props = [
        [$th?'IUPAC Name':'IUPAC Name',          $c['iupac_name'] ?? null],
        [$th?'สถานะ':'Physical State',            $c['physical_state'] ? ucfirst($c['physical_state']) : null],
        [$th?'ลักษณะ':'Appearance',               $c['appearance'] ?? null],
        [$th?'กลิ่น':'Odor',                      $c['odor'] ?? null],
        [$th?'จุดหลอมเหลว':'Melting Point',       $c['melting_point'] ? $c['melting_point'] . ' °C' : null],
        [$th?'จุดเดือด':'Boiling Point',          $c['boiling_point'] ? $c['boiling_point'] . ' °C' : null],
        [$th?'ความหนาแน่น':'Density',             $c['density'] ? $c['density'] . ' g/cm³' : null],
        [$th?'ความสามารถละลาย':'Solubility',      $c['solubility'] ?? null],
        [$th?'ความดันไอ':'Vapor Pressure',        $c['vapor_pressure'] ? $c['vapor_pressure'] . ' mmHg' : null],
        [$th?'จุดวาบไฟ':'Flash Point',            $c['flash_point'] ? $c['flash_point'] . ' °C' : null],
        [$th?'อุณหภูมิติดไฟเอง':'Auto-ignition', $c['auto_ignition_temp'] ? $c['auto_ignition_temp'] . ' °C' : null],
    ];
    $propRows = array_filter($props, fn($p) => $p[1] !== null && $p[1] !== '');
    if (!empty($propRows)) {
        $html .= sectionToggle('fas fa-flask', $th?'คุณสมบัติทางกายภาพ':'Physical Properties', true);
        $html .= '<div class="section-body">';
        $html .= '<table class="phys-table">';
        foreach ($propRows as [$lbl, $val]) {
            $html .= '<tr><td>' . h($lbl) . '</td><td>' . h($val) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }

    // ── 3. HAZARD / GHS ─────────────────────────────────────────
    $html .= renderHazardSection($c, $lang);

    // ── 4. STORAGE LOCATION ──────────────────────────────────────
    if (!empty($cs)) {
        $html .= renderLocationSection($cs, $lang, $user ?? null);
    } else {
        $html .= '<div class="no-stock"><i class="fas fa-box-open"></i> ' . ($th?'ไม่พบข้อมูลการจัดเก็บในคลัง':'No storage data found in warehouse') . '</div>';
    }

    // ── 5. SDS ───────────────────────────────────────────────────
    if (!empty($c['sds_url']) || !empty($c['sds_pdf_path']) || !empty($c['image_url'])) {
        $html .= renderSDSSection($c, $lang);
    }

    // ── 6. EXTERNAL LINKS ────────────────────────────────────────
    $html .= renderExternalLinks($c, $lang);

    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — HAZARD FULL (dedicated hazard query)
// ═══════════════════════════════════════════════════════════════════

function renderHazardFull(array $c, string $lang): string {
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    // Minimal chem header
    $html .= '<div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:linear-gradient(135deg,#fef2f2,#fff5f5);border-radius:14px;margin-bottom:14px;border:1px solid #fecaca">';
    $html .= '<div style="font-size:28px">🧪</div>';
    $html .= '<div>';
    $html .= '<div style="font-size:18px;font-weight:800;color:#991b1b">' . h($c['name']) . '</div>';
    $meta = [];
    if (!empty($c['cas_number']))        $meta[] = 'CAS: <code>' . h($c['cas_number']) . '</code>';
    if (!empty($c['molecular_formula'])) $meta[] = h($c['molecular_formula']);
    if (!empty($c['signal_word']) && $c['signal_word'] !== 'No signal word') $meta[] = '<span style="color:' . ($c['signal_word']==='Danger'?'#dc2626':'#d97706') . ';font-weight:700">' . h($c['signal_word']) . '</span>';
    if ($meta) $html .= '<div style="font-size:12px;color:#b91c1c;margin-top:4px">' . implode(' &nbsp;|&nbsp; ', $meta) . '</div>';
    $html .= '</div></div></div>';

    $html .= renderHazardSection($c, $lang);
    $html .= renderExternalLinks($c, $lang);
    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — HAZARD SECTION (reusable)
// ═══════════════════════════════════════════════════════════════════

function renderHazardSection(array $c, string $lang): string {
    $th          = $lang === 'th';
    $signalWord  = $c['signal_word'] ?? '';
    $pictograms  = json_decode($c['hazard_pictograms'] ?? '[]', true) ?? [];
    $hStatements = json_decode($c['hazard_statements'] ?? '[]', true) ?? [];
    $pStatements = json_decode($c['precautionary_statements'] ?? '[]', true) ?? [];
    $ghsClass    = json_decode($c['ghs_classifications'] ?? '[]', true) ?? [];
    $incompat    = json_decode($c['incompatible_chemicals'] ?? '[]', true) ?? [];

    $hasHazard = $signalWord || !empty($pictograms) || !empty($hStatements);
    if (!$hasHazard) return '';

    $signalClass = ($signalWord === 'Danger') ? 'danger' : 'warning';
    $html = sectionToggle('fas fa-exclamation-triangle', $th?'⚠️ ข้อมูลความเป็นอันตราย (GHS)':'⚠️ Hazard Information (GHS)', true);
    $html .= '<div class="section-body">';
    $html .= '<div class="hazard-block">';
    $html .= '<div class="hazard-block-head"><i class="fas fa-exclamation-triangle"></i> ' . ($th?'ข้อมูลความเป็นอันตราย GHS':'GHS Hazard Classification') . '</div>';

    // Signal Word
    if ($signalWord && $signalWord !== 'No signal word') {
        $html .= '<div style="margin-bottom:12px">';
        $html .= '<div style="font-size:11px;color:#9ca3af;margin-bottom:5px">' . ($th?'ข้อความสัญญาณ':'Signal Word') . ':</div>';
        $html .= '<span class="signal-badge ' . $signalClass . '">' . h($signalWord) . '</span>';
        $html .= '</div>';
    }

    // ═══════════════════════════════════════════════════════════════
    // GHS Pictograms — UN GHS Rev.9 International Standard
    // Official SVG files from Wikimedia Commons (CC0 / Public Domain)
    // Stored in: assets/ghs/ghs01.svg … ghs09.svg
    // Source: https://commons.wikimedia.org/wiki/GHS_hazard_pictograms
    // ═══════════════════════════════════════════════════════════════

    /**
     * Load an official GHS SVG file and prepare it for inline HTML use.
     * Strips XML declaration, adds class="ghs-official-svg" for CSS sizing.
     */
    function loadGHSSVG(string $dir, string $code): string {
        $file = $dir . strtolower($code) . '.svg';
        if (!file_exists($file)) return '';
        $svg = file_get_contents($file);
        if ($svg === false) return '';
        // Remove XML declaration if present
        $svg = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg);
        // Inject class for CSS sizing (add to existing class or create new)
        $svg = preg_replace('/<svg\b/', '<svg class="ghs-official-svg"', $svg, 1);
        return trim($svg);
    }

    $ghsDir = __DIR__ . '/../assets/ghs/';

    // Map canonical GHSxx → [en_name, th_name, complete_svg_string]
    $ghsMap = [
        'GHS01' => ['Explosive',       'วัตถุระเบิด',           loadGHSSVG($ghsDir, 'ghs01')],
        'GHS02' => ['Flammable',       'ติดไฟได้',              loadGHSSVG($ghsDir, 'ghs02')],
        'GHS03' => ['Oxidizing',       'สารออกซิไดซ์',          loadGHSSVG($ghsDir, 'ghs03')],
        'GHS04' => ['Compressed Gas',  'ก๊าซอัดความดัน',        loadGHSSVG($ghsDir, 'ghs04')],
        'GHS05' => ['Corrosive',       'กัดกร่อน',              loadGHSSVG($ghsDir, 'ghs05')],
        'GHS06' => ['Toxic',           'เป็นพิษเฉียบพลัน',      loadGHSSVG($ghsDir, 'ghs06')],
        'GHS07' => ['Harmful',         'เป็นอันตราย',           loadGHSSVG($ghsDir, 'ghs07')],
        'GHS08' => ['Health Hazard',   'อันตรายต่อสุขภาพ',      loadGHSSVG($ghsDir, 'ghs08')],
        'GHS09' => ['Environmental',   'อันตรายต่อสิ่งแวดล้อม', loadGHSSVG($ghsDir, 'ghs09')],
    ];

    // ── Alias table: DB may store text names OR GHSxx codes ──────────────
    // Maps any variant (text name / code) → canonical GHSxx key
    $ghsAlias = [
        // Text names stored in DB
        'EXPLOSIVE'          => 'GHS01', 'EXPLODING_BOMB'     => 'GHS01',
        'FLAMMABLE'          => 'GHS02', 'FLAME'              => 'GHS02',
        'OXIDIZING'          => 'GHS03', 'OXIDIZER'           => 'GHS03', 'FLAME_OVER_CIRCLE' => 'GHS03',
        'COMPRESSED_GAS'     => 'GHS04', 'GAS_CYLINDER'       => 'GHS04', 'GAS CYLINDER'      => 'GHS04',
        'CORROSIVE'          => 'GHS05', 'CORROSION'          => 'GHS05',
        'TOXIC'              => 'GHS06', 'SKULL'              => 'GHS06', 'SKULL_AND_CROSSBONES' => 'GHS06',
        'HARMFUL'            => 'GHS07', 'IRRITANT'           => 'GHS07', 'EXCLAMATION_MARK'  => 'GHS07',
        'HEALTH_HAZARD'      => 'GHS08', 'HEALTH HAZARD'      => 'GHS08', 'SERIOUS_HEALTH_HAZARD' => 'GHS08',
        'ENVIRONMENTAL'      => 'GHS09', 'ENVIRONMENTAL_HAZARD' => 'GHS09', 'AQUATIC_TOXICITY'  => 'GHS09',
        'ENVIRO_HAZARD'      => 'GHS09', 'ENVIRO HAZARD'      => 'GHS09',
        // Also accept GHS1 / GHS2 short forms
        'GHS1'  => 'GHS01', 'GHS2'  => 'GHS02', 'GHS3'  => 'GHS03',
        'GHS4'  => 'GHS04', 'GHS5'  => 'GHS05', 'GHS6'  => 'GHS06',
        'GHS7'  => 'GHS07', 'GHS8'  => 'GHS08', 'GHS9'  => 'GHS09',
    ];

    if (!empty($pictograms)) {
        // de-duplicate so same symbol never appears twice
        $seen = [];
        $html .= '<div class="ghs-section">';
        $html .= '<div class="ghs-section-title"><i class="fas fa-shield-halved"></i> '
               . ($th ? 'GHS Pictograms — สัญลักษณ์ความเป็นอันตราย' : 'GHS Pictograms — Hazard Symbols')
               . '</div>';
        $html .= '<div class="ghs-grid">';
        foreach ($pictograms as $raw) {
            $p    = strtoupper(trim((string)$raw));
            // Normalise: resolve alias → canonical GHSxx key
            $key  = $ghsAlias[$p] ?? (isset($ghsMap[$p]) ? $p : null);
            if (!$key || isset($seen[$key])) continue;
            $seen[$key] = true;

            $info        = $ghsMap[$key];
            $enName      = $info[0];
            $thName      = $info[1];
            $completeSVG = $info[2]; // complete <svg>...</svg> string
            $nameDisplay = $th ? $thName : $enName;

            $html .= '<div class="ghs-item" data-ghs="' . h($key) . '" title="' . h($key) . ' · ' . h($enName) . ' — ' . h($thName) . '">';
            $html .= '<div class="ghs-diamond-wrap">' . $completeSVG . '</div>';
            $html .= '<div class="ghs-code-pill">' . h($key) . '</div>';
            $html .= '<div class="ghs-name">' . h($nameDisplay) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    // GHS Classes
    if (!empty($ghsClass)) {
        $html .= '<div style="margin-bottom:12px">';
        $html .= '<div style="font-size:11px;font-weight:700;color:#dc2626;margin-bottom:6px">' . ($th?'หมวดหมู่อันตราย:':'Hazard Classes:') . '</div>';
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:6px">';
        foreach ((array)$ghsClass as $cls) {
            if (is_string($cls)) $html .= '<span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600">' . h($cls) . '</span>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    // H-Statements
    if (!empty($hStatements)) {
        $html .= '<div class="stmt-block">';
        $html .= '<div class="stmt-head"><i class="fas fa-triangle-exclamation"></i> ' . ($th?'H-Statements (ข้อความแสดงอันตราย):':'H-Statements (Hazard Statements):') . '</div>';
        $html .= '<ul class="stmt-list">';
        foreach ((array)$hStatements as $h_) {
            if (is_string($h_)) $html .= '<li>' . h($h_) . '</li>';
        }
        $html .= '</ul></div>';
    }

    // P-Statements
    if (!empty($pStatements)) {
        $html .= '<div class="stmt-block">';
        $html .= '<div class="stmt-head"><i class="fas fa-shield-halved"></i> ' . ($th?'P-Statements (ข้อควรระวัง):':'P-Statements (Precautionary):') . '</div>';
        $html .= '<ul class="stmt-list">';
        foreach ((array)$pStatements as $p_) {
            if (is_string($p_)) $html .= '<li>' . h($p_) . '</li>';
        }
        $html .= '</ul></div>';
    }

    // Incompatible chemicals
    if (!empty($incompat)) {
        $html .= '<div class="compat-block">';
        $html .= '<div class="compat-block-head"><i class="fas fa-ban"></i> ' . ($th?'สารที่เข้ากันไม่ได้:':'Incompatible With:') . '</div>';
        $html .= '<div class="compat-list">';
        foreach ((array)$incompat as $ic) {
            if (is_string($ic)) $html .= '<span class="compat-item">' . h($ic) . '</span>';
        }
        $html .= '</div></div>';
    }

    // Storage requirement hint
    if (!empty($c['storage_requirements'])) {
        $html .= '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px;margin-top:8px;font-size:12px;color:#78350f">';
        $html .= '<i class="fas fa-warehouse"></i> ' . ($th?'ข้อกำหนดการจัดเก็บ: ':'Storage Requirements: ') . h($c['storage_requirements']);
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>'; // section-body
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — LOCATION SECTION (reusable)
// ═══════════════════════════════════════════════════════════════════

function renderLocationSection(array $containers, string $lang, ?array $currentUser = null): string {
    $th      = $lang === 'th';
    $myRole  = $currentUser['role']  ?? '';
    $myId    = intval($currentUser['id'] ?? 0);
    $isGuest = ($myId === 0);
    $html = sectionToggle('fas fa-warehouse', $th?'📍 ตำแหน่งจัดเก็บ':'📍 Storage Locations', false);
    $html .= '<div class="section-body collapsed">';

    // Group: building → room → cabinet
    $tree = [];
    $totalQty  = 0;
    $totalUnit = '';
    foreach ($containers as $c) {
        // Build human-readable labels
        $bld = $c['building'] ?? null;
        if (!$bld) $bld = $th ? 'ไม่ระบุอาคาร' : 'Unknown Building';

        // Room: prefer name, fall back to room_number
        $rm = $c['room'] ?? null;
        if (!$rm || in_array(strtolower($rm), ['center','unknown','ไม่ระบุ'])) {
            $rm = $c['room_number'] ?? ($th ? 'ไม่ระบุห้อง' : 'Unknown Room');
        }
        // Append room_number in brackets if name != number and number exists
        $rn = $c['room_number'] ?? '';
        if ($rn && $rm !== $rn && !str_contains($rm, $rn)) {
            $rm = $rm . ' (' . $rn . ')';
        }

        $cab = $c['cabinet'] ?? null;
        // Room-level containers (no cabinet) → group under a special key
        $cabKey = $cab ?: '__room_level__';

        $tree[$bld][$rm][$cabKey][] = $c;
        $totalQty  += floatval($c['current_quantity']);
        $totalUnit  = $c['quantity_unit'] ?? $totalUnit;
    }

    // Count totals for subtitle
    $totalBld   = count($tree);
    $totalRooms = array_sum(array_map('count', $tree));
    $subLabel   = $th
        ? $totalBld . ' อาคาร · ' . count($containers) . ' รายการ'
        : $totalBld . ' building' . ($totalBld > 1 ? 's' : '') . ' · ' . count($containers) . ' item' . (count($containers) > 1 ? 's' : '');

    // Container-type → icon + CSS class
    $ctypeMap = [
        'bottle'  => ['fas fa-wine-bottle', 'ctype-bottle'],
        'flask'   => ['fas fa-flask',        'ctype-flask'],
        'bag'     => ['fas fa-bag-shopping', 'ctype-bag'],
        'drum'    => ['fas fa-barrel',       'ctype-drum'],
        'can'     => ['fas fa-spray-can',    'ctype-can'],
        'box'     => ['fas fa-box',          'ctype-box'],
    ];

    $html .= '<div class="loc-block">';

    // ── Header ──────────────────────────────────────────────────────
    $html .= '<div class="loc-block-head">';
    $html .= '<div class="loc-head-left">';
    $html .= '<div class="loc-head-icon"><i class="fas fa-map-marker-alt"></i></div>';
    $html .= '<div><div class="loc-head-title">' . ($th?'ตำแหน่งจัดเก็บ':'Storage Locations') . '</div>';
    $html .= '<div class="loc-head-sub">' . $subLabel . '</div></div>';
    $html .= '</div>';
    $html .= '<div class="loc-total-badge">';
    $html .= '<span class="loc-qty-big">' . number_format($totalQty, 2) . '</span>';
    $html .= '<span class="loc-unit">' . h($totalUnit) . '</span>';
    $html .= '<span class="loc-count-tag">· ' . count($containers) . ($th?' รายการ':' items') . '</span>';
    $html .= '</div></div>';

    // ── Tree ────────────────────────────────────────────────────────
    $html .= '<div class="loc-tree">';
    foreach ($tree as $bld => $rooms) {
        $bldCount = array_sum(array_map('count', array_merge(...array_values($rooms))));
        $html .= '<div class="loc-building">';
        $html .= '<div class="loc-building-hdr">';
        $html .= '<div class="loc-bld-icon-wrap"><i class="fas fa-building"></i></div>';
        $html .= '<div class="loc-bld-text"><i class="fas fa-building" style="color:#10b981;font-size:12px"></i>' . h($bld);
        $html .= '<div class="loc-bld-meta"><span class="loc-n-badge">' . $bldCount . ($th?' รายการ':' item' . ($bldCount > 1 ? 's' : '')) . '</span>';
        $html .= '<i class="fas fa-chevron-down expand-icon"></i></div></div>';
        $html .= '</div>';
        $html .= '<div class="loc-rooms" style="display:none">';

        foreach ($rooms as $rm => $cabs) {
            $rmCount = array_sum(array_map('count', $cabs));
            $html .= '<div class="loc-room">';
            $html .= '<div class="loc-room-hdr"><i class="fas fa-door-open"></i> ' . h($rm);
            $html .= '<span class="loc-n-badge">' . $rmCount . ($th?' รายการ':' item' . ($rmCount > 1 ? 's' : '')) . '</span></div>';

            foreach ($cabs as $cabKey => $items) {
                $isRoomLevel = ($cabKey === '__room_level__');
                $html .= '<div class="loc-cabinet">';
                if (!$isRoomLevel) {
                    $html .= '<div class="loc-cabinet-hdr"><i class="fas fa-box" style="color:#10b981"></i> ' . h($cabKey) . '</div>';
                }

                $html .= '<div class="loc-items">';
                foreach ($items as $item) {
                    $shelf = $item['shelf'] ?? '';
                    $slot  = $item['slot']  ?? '';
                    $loc   = trim(($shelf ? $shelf . ' › ' : '') . $slot, ' › ');
                    $qty   = number_format(floatval($item['current_quantity']), 2) . ' ' . ($item['quantity_unit'] ?? '');
                    $ctype = strtolower($item['container_type'] ?? 'bottle');
                    $qrCode = trim($item['qr_code'] ?? '');

                    // Expiry
                    $exp = $item['expiry_date'] ?? '';
                    $expClass = 'nodate'; $expLabel = $th ? 'ไม่ระบุ' : 'No date';
                    if ($exp) {
                        $days = (int)((strtotime($exp) - time()) / 86400);
                        if ($days < 0)      { $expClass = 'danger'; $expLabel = $exp . ' ⚠'; }
                        elseif ($days < 90) { $expClass = 'warn';   $expLabel = $exp . ' ⚡'; }
                        else                { $expClass = 'fresh';  $expLabel = $exp; }
                    }

                    // Container type icon
                    $ctypeKey = array_key_exists($ctype, $ctypeMap) ? $ctype : 'bottle';
                    [$ctypeIcon, $ctypeClass] = $ctypeMap[$ctypeKey];

                    // Owner
                    $ownerName  = trim($item['owner_name']  ?? '');
                    $ownerPhone = trim($item['owner_phone'] ?? '');
                    $ownerEmail = trim($item['owner_email'] ?? '');

                    $html .= '<div class="loc-item ' . $ctypeClass . '">';
                    $html .= '<div class="loc-item-main">';

                    // Left: type icon
                    $html .= '<div class="loc-item-type-col">';
                    $html .= '<i class="' . $ctypeIcon . ' ctype-icon"></i>';
                    $html .= '<span class="ctype-lbl">' . h($ctype ?: 'bottle') . '</span>';
                    $html .= '</div>';

                    // Center: QR + location
                    $html .= '<div class="loc-item-info">';
                    if ($qrCode) {
                        $html .= '<div class="loc-item-qr"><i class="fas fa-qrcode qr-icon"></i>' . h($qrCode) . '</div>';
                    } else {
                        $html .= '<div class="loc-item-qr" style="color:#94a3b8"><i class="fas fa-minus qr-icon"></i>' . ($th?'ไม่มี QR':'No QR') . '</div>';
                    }
                    if ($loc) {
                        $html .= '<div class="loc-item-loc"><i class="fas fa-layer-group"></i>' . h($loc) . '</div>';
                    } elseif ($isRoomLevel) {
                        $html .= '<div class="loc-item-loc"><i class="fas fa-door-open"></i>' . ($th?'วางในห้อง':'Room level') . '</div>';
                    }
                    $html .= '</div>';

                    // Right: qty + expiry
                    $html .= '<div class="loc-item-right">';
                    $html .= '<div class="loc-item-qty">' . h($qty) . '</div>';
                    // For nodate: admin/manager get an action badge instead of static text
                    $isOwner = ($myId > 0 && intval($item['owner_id'] ?? 0) === $myId);
                    $canEdit = in_array($myRole, ['admin', 'lab_manager']);
                    if ($expClass === 'nodate' && $canEdit) {
                        $html .= '<div class="loc-exp-badge nodate loc-set-exp-btn" onclick="locSetExpiry(' . intval($item['id']) . ',this)" title="' . ($th?'กำหนดวันหมดอายุ':'Set expiry date') . '">';
                        $html .= '<i class="fas fa-calendar-plus"></i> ' . ($th?'กำหนดวันหมดอายุ':'Set Expiry') . '</div>';
                    } else {
                        $html .= '<div class="loc-exp-badge ' . $expClass . '">' . h($expLabel) . '</div>';
                    }
                    $html .= '</div>';

                    $html .= '</div>'; // loc-item-main

                    // Owner row
                    $html .= '<div class="loc-item-owner">';
                    if ($ownerName || $ownerPhone || $ownerEmail) {
                        if ($ownerName)  $html .= '<span class="loc-owner-name"><i class="fas fa-user"></i>' . h($ownerName) . '</span>';
                        if ($ownerPhone) $html .= '<a href="tel:' . h($ownerPhone) . '"><i class="fas fa-phone"></i>' . h($ownerPhone) . '</a>';
                        if ($ownerEmail) $html .= '<a href="mailto:' . h($ownerEmail) . '"><i class="fas fa-envelope"></i>' . h($ownerEmail) . '</a>';
                    } else {
                        $html .= '<span class="loc-no-owner">' . ($th?'ไม่ระบุผู้ดูแล':'No custodian') . '</span>';
                    }
                    $html .= '</div>';

                    // ── Role-based action buttons ────────────────────
                    $containerId = intval($item['id']);
                    $ownerIdItem = intval($item['owner_id'] ?? 0);
                    $html .= '<div class="loc-item-actions">';

                    // Correct role names: admin, ceo, lab_manager, user
                    $canBorrow = !$isGuest;
                    $canEditReal = in_array($myRole, ['admin', 'lab_manager']);
                    $canSeeStock = in_array($myRole, ['admin', 'ceo']);

                    if ($isGuest) {
                        // Guest: prompt to login
                        $html .= '<button class="loc-act-btn loc-act-view3d" onclick="locGuestPrompt()"><i class="fas fa-cube"></i> ' . ($th?'ดู 3D':'View 3D') . '</button>';
                        $html .= '<button class="loc-act-btn loc-act-info" onclick="locGuestPrompt()"><i class="fas fa-info-circle"></i> ' . ($th?'รายละเอียด':'Details') . '</button>';
                    } else {
                        // All logged-in users can borrow
                        $html .= '<button class="loc-act-btn loc-act-borrow" onclick="locBorrow(' . $containerId . ')"><i class="fas fa-hand-holding"></i> ' . ($th?'เบิกใช้สาร':'Borrow') . '</button>';
                        if ($canEditReal) {
                            $html .= '<button class="loc-act-btn loc-act-edit" onclick="locEditItem(' . $containerId . ')"><i class="fas fa-pen"></i> ' . ($th?'แก้ไข':'Edit') . '</button>';
                        }
                        if ($canSeeStock && floatval($item['initial_quantity']) > 0) {
                            $usedPct = round((1 - floatval($item['current_quantity']) / floatval($item['initial_quantity'])) * 100);
                            $usedPct = max(0, min(100, $usedPct));
                            $remainPct = 100 - $usedPct;
                            $isCeo = ($myRole === 'ceo');
                            $html .= '<div class="loc-stock-status' . ($isCeo ? ' loc-stock-ceo' : '') . '">';
                            if ($isCeo) $html .= '<span class="loc-stock-icon"><i class="fas fa-chart-pie"></i></span>';
                            $html .= '<div class="loc-stock-bar"><div class="loc-stock-fill" style="width:' . $remainPct . '%"></div></div>';
                            $html .= '<span class="loc-stock-lbl">' . ($th?'คงเหลือ ':'Rem. ') . $remainPct . '% · ' . h($qty) . '</span>';
                            $html .= '</div>';
                        }
                        // Detail + 3D for all logged-in
                        $html .= '<button class="loc-act-btn loc-act-info" onclick="locViewDetail(' . $containerId . ')"><i class="fas fa-info-circle"></i> ' . ($th?'รายละเอียด':'Details') . '</button>';
                    }

                    $html .= '</div>'; // loc-item-actions

                    $html .= '</div>'; // loc-item
                }
                $html .= '</div></div>'; // loc-items + loc-cabinet
            }
            $html .= '</div>'; // loc-room
        }
        $html .= '</div></div>'; // loc-rooms + loc-building
    }
    $html .= '</div></div>'; // loc-tree + loc-block
    $html .= '</div>'; // section-body
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — SDS FULL (dedicated SDS query)
// ═══════════════════════════════════════════════════════════════════

function renderSDSFull(array $c, string $lang): string {
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    // Chem header
    $html .= '<div class="sds-block">';
    $html .= '<div class="sds-block-head"><i class="fas fa-file-shield"></i> ' . ($th?'เอกสารความปลอดภัย (SDS)':'Safety Data Sheet (SDS)') . '</div>';

    $html .= '<div style="background:#fff;border-radius:10px;padding:14px;margin-bottom:14px">';
    $html .= '<div style="font-size:20px;font-weight:800;color:#991b1b;margin-bottom:8px">' . h($c['name']) . '</div>';
    $html .= '<div style="display:flex;flex-wrap:wrap;gap:6px">';
    if ($c['cas_number'])        $html .= pill('fas fa-hashtag', 'CAS: ' . h($c['cas_number']), 'cas');
    if ($c['molecular_formula']) $html .= pill('fas fa-atom', h($c['molecular_formula']), 'formula');
    $html .= '</div>';
    $html .= '</div>';

    // Image (if available)
    if (!empty($c['image_url'])) {
        $html .= '<div style="margin-bottom:14px">';
        $html .= '<div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:8px"><i class="fas fa-image"></i> ' . ($th?'ภาพ SDS / Label':'SDS Image / Label') . '</div>';
        $html .= '<div class="sds-img-wrap">';
        $html .= '<img src="' . h($c['image_url']) . '" alt="SDS Image" class="sds-full-img" onclick="this.classList.toggle(\'fs\')">';
        $html .= '<p class="sds-img-hint"><i class="fas fa-hand-pointer"></i> ' . ($th?'คลิกที่ภาพเพื่อขยาย/ย่อ':'Click image to fullscreen/zoom') . '</p>';
        $html .= '</div></div>';
    }

    // Download / View buttons
    $html .= '<div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:10px"><i class="fas fa-download"></i> ' . ($th?'ดาวน์โหลด / เปิด SDS:':'Download / View SDS:') . '</div>';
    $html .= '<div class="sds-btns">';
    if (!empty($c['sds_url'])) {
        $html .= '<a href="' . h($c['sds_url']) . '" target="_blank" class="sds-btn sds-btn-b"><i class="fas fa-external-link-alt"></i> ' . ($th?'เปิด SDS ออนไลน์':'View Online SDS') . '</a>';
    }
    if (!empty($c['sds_pdf_path'])) {
        $html .= '<a href="' . h($c['sds_pdf_path']) . '" target="_blank" class="sds-btn sds-btn-r"><i class="fas fa-file-pdf"></i> ' . ($th?'ดาวน์โหลด PDF':'Download PDF') . '</a>';
    }
    if (empty($c['sds_url']) && empty($c['sds_pdf_path'])) {
        $q = urlencode($c['name']);
        $html .= '<a href="https://pubchem.ncbi.nlm.nih.gov/search/?query=' . $q . '" target="_blank" class="sds-btn sds-btn-c"><i class="fas fa-database"></i> PubChem</a>';
        $html .= '<a href="https://www.google.com/search?q=' . $q . '+SDS+PDF" target="_blank" class="sds-btn sds-btn-g"><i class="fas fa-search"></i> Google SDS</a>';
        $html .= '<a href="https://www.sciencelab.com/search.php?searchterm=' . $q . '" target="_blank" class="sds-btn sds-btn-g"><i class="fas fa-flask"></i> ScienceLab</a>';
    }
    $html .= '</div>';

    // Safety info
    if (!empty($c['handling_procedures'])) {
        $html .= '<div style="background:#fff;border-radius:8px;padding:12px;margin-top:12px">';
        $html .= '<div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:6px"><i class="fas fa-hand-sparkles"></i> ' . ($th?'วิธีการจัดการ':'Handling Procedures') . '</div>';
        $html .= '<p style="font-size:12px;color:#4b5563;line-height:1.6">' . h($c['handling_procedures']) . '</p>';
        $html .= '</div>';
    }
    if (!empty($c['first_aid_measures'])) {
        $html .= '<div style="background:#fff;border-radius:8px;padding:12px;margin-top:8px">';
        $html .= '<div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:6px"><i class="fas fa-kit-medical"></i> ' . ($th?'การปฐมพยาบาล':'First Aid Measures') . '</div>';
        $html .= '<p style="font-size:12px;color:#4b5563;line-height:1.6">' . h($c['first_aid_measures']) . '</p>';
        $html .= '</div>';
    }

    $html .= '</div>'; // sds-block
    $html .= renderExternalLinks($c, $lang);
    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — SDS SECTION (reusable for full chemical view)
// ═══════════════════════════════════════════════════════════════════

function renderSDSSection(array $c, string $lang): string {
    $th  = $lang === 'th';
    $cid = (int)($c['id'] ?? 0);

    // ── Fetch uploaded SDS files from chemical_sds_files table ──
    $sdsFiles = [];
    if ($cid > 0) {
        try {
            $sdsFiles = \Database::fetchAll("
                SELECT sf.id, sf.file_name, sf.file_path, sf.file_url,
                       sf.file_type, sf.file_size, sf.language,
                       sf.version, sf.uploaded_at,
                       CONCAT(u.first_name,' ',u.last_name) AS uploader_name
                FROM chemical_sds_files sf
                LEFT JOIN users u ON sf.uploaded_by = u.id
                WHERE sf.chemical_id = :cid
                ORDER BY sf.uploaded_at DESC
            ", [':cid' => $cid]);
        } catch (\Exception $e) { $sdsFiles = []; }
    }

    // ── Determine what we have ──
    $hasPdf   = !empty($c['sds_pdf_path']);
    $hasLink  = !empty($c['sds_url']);
    $hasImg   = !empty($c['image_url']);
    $hasFiles = !empty($sdsFiles);
    $hasAny   = $hasPdf || $hasLink || $hasFiles;
    $hasInfo  = !empty($c['handling_procedures']) || !empty($c['first_aid_measures'])
             || !empty($c['disposal_methods'])    || !empty($c['storage_requirements']);

    $chemName = $c['name'] ?? '';
    $qEnc     = urlencode($chemName);
    $casEnc   = urlencode($c['cas_number'] ?? $chemName);
    $lastUp   = $c['sds_last_updated'] ?? null;

    // ── Section header (collapsed by default) ──
    $html  = sectionToggle('fas fa-file-shield', $th ? '📋 เอกสารความปลอดภัย (SDS)' : '📋 Safety Data Sheet (SDS)', false);
    $html .= '<div class="section-body collapsed">';

    // ══ MAIN SDS CARD ══════════════════════════════════════════
    $html .= '<div style="background:#fff;border:1px solid #fecaca;border-radius:16px;overflow:hidden;margin-bottom:12px">';

    // Card header bar
    $html .= '<div style="background:linear-gradient(135deg,#fef2f2,#fff5f5);padding:14px 16px;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:10px">';
    $html .= '<div style="width:36px;height:36px;border-radius:10px;background:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0">';
    $html .= '<i class="fas fa-file-shield" style="color:#fff;font-size:15px"></i></div>';
    $html .= '<div style="flex:1">';
    $html .= '<div style="font-size:13px;font-weight:800;color:#991b1b">' . ($th ? 'เอกสารความปลอดภัย' : 'Safety Data Sheet') . ' (SDS / MSDS)</div>';
    $html .= '<div style="font-size:11px;color:#dc2626;opacity:.7;margin-top:1px">' . h($chemName);
    if ($c['cas_number'] ?? '') $html .= ' &nbsp;·&nbsp; CAS: ' . h($c['cas_number']);
    $html .= '</div></div>';
    // Status badge
    if ($hasAny) {
        $html .= '<span style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap"><i class="fas fa-check-circle" style="margin-right:3px"></i>' . ($th ? 'มีข้อมูล' : 'Available') . '</span>';
    } else {
        $html .= '<span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap"><i class="fas fa-search" style="margin-right:3px"></i>' . ($th ? 'ค้นหาออนไลน์' : 'Find Online') . '</span>';
    }
    $html .= '</div>'; // header bar

    $html .= '<div style="padding:16px">';

    // ── SDS Image preview (if available) ──────────────────────────
    if ($hasImg) {
        $html .= '<div style="margin-bottom:14px">';
        $html .= '<div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:flex;align-items:center;gap:5px">';
        $html .= '<i class="fas fa-image"></i> ' . ($th ? 'ภาพ Label / SDS' : 'SDS Label Image') . '</div>';
        $html .= '<div class="sds-img-wrap" style="border-radius:10px;overflow:hidden">';
        $html .= '<img src="' . h($c['image_url']) . '" alt="SDS Image" class="sds-full-img" onclick="this.classList.toggle(\'fs\')" style="max-height:220px;width:100%;object-fit:contain">';
        $html .= '<p class="sds-img-hint"><i class="fas fa-expand"></i> ' . ($th ? 'คลิกเพื่อขยายเต็มจอ' : 'Click to fullscreen') . '</p>';
        $html .= '</div></div>';
        $html .= '<div style="border-top:1px solid #f3f4f6;margin-bottom:14px"></div>';
    }

    // ── Upload section ─────────────────────────────────────────────
    if ($hasAny) {
        $html .= '<div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:5px">';
        $html .= '<i class="fas fa-file-download"></i> ' . ($th ? 'เอกสารที่อัปโหลด' : 'Uploaded Documents') . '</div>';

        $html .= '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">';

        // Primary SDS URL (link)
        if ($hasLink) {
            $html .= '<a href="' . h($c['sds_url']) . '" target="_blank" rel="noopener" ';
            $html .= 'style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:linear-gradient(135deg,#eff6ff,#f0f9ff);border:1.5px solid #bfdbfe;border-radius:12px;text-decoration:none;transition:.15s" ';
            $html .= 'onmouseover="this.style.borderColor=\'#3b82f6\';this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.borderColor=\'#bfdbfe\';this.style.transform=\'\'">';
            $html .= '<div style="width:38px;height:38px;border-radius:10px;background:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-external-link-alt" style="color:#fff;font-size:14px"></i></div>';
            $html .= '<div style="flex:1;min-width:0">';
            $html .= '<div style="font-size:13px;font-weight:700;color:#1e40af">' . ($th ? 'เปิด SDS ออนไลน์' : 'View SDS Online') . '</div>';
            $html .= '<div style="font-size:10px;color:#3b82f6;opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($c['sds_url']) . '</div>';
            $html .= '</div>';
            $html .= '<i class="fas fa-arrow-right" style="color:#3b82f6;opacity:.5;font-size:12px"></i>';
            $html .= '</a>';
        }

        // Primary SDS PDF
        if ($hasPdf) {
            $fname = basename($c['sds_pdf_path']);
            $html .= '<a href="' . h($c['sds_pdf_path']) . '" target="_blank" rel="noopener" ';
            $html .= 'style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1.5px solid #fecaca;border-radius:12px;text-decoration:none;transition:.15s" ';
            $html .= 'onmouseover="this.style.borderColor=\'#dc2626\';this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.borderColor=\'#fecaca\';this.style.transform=\'\'">';
            $html .= '<div style="width:38px;height:38px;border-radius:10px;background:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-file-pdf" style="color:#fff;font-size:16px"></i></div>';
            $html .= '<div style="flex:1;min-width:0">';
            $html .= '<div style="font-size:13px;font-weight:700;color:#991b1b">' . ($th ? 'ดาวน์โหลด PDF (อัปโหลดโดยระบบ)' : 'Download PDF (System Upload)') . '</div>';
            $html .= '<div style="font-size:10px;color:#dc2626;opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($fname) . '</div>';
            $html .= '</div>';
            $html .= '<div style="display:flex;align-items:center;gap:4px">';
            $html .= '<i class="fas fa-download" style="color:#dc2626;opacity:.6;font-size:12px"></i>';
            $html .= '</div>';
            $html .= '</a>';
        }

        // Extra uploaded SDS files from chemical_sds_files
        if ($hasFiles) {
            foreach ($sdsFiles as $sf) {
                $isPdf     = stripos($sf['file_type'] ?? '', 'pdf') !== false || pathinfo($sf['file_path'] ?? '', PATHINFO_EXTENSION) === 'pdf';
                $fileUrl   = !empty($sf['file_url']) ? $sf['file_url'] : (!empty($sf['file_path']) ? $sf['file_path'] : null);
                if (!$fileUrl) continue;

                $icon      = $isPdf ? 'fa-file-pdf' : 'fa-file-alt';
                $bgColor   = $isPdf ? '#dc2626' : '#7c3aed';
                $borderCol = $isPdf ? '#fecaca' : '#ddd6fe';
                $bgGrad    = $isPdf ? 'linear-gradient(135deg,#fef2f2,#fff5f5)' : 'linear-gradient(135deg,#f5f3ff,#faf5ff)';
                $textColor = $isPdf ? '#991b1b' : '#5b21b6';

                $sizeStr   = '';
                if (!empty($sf['file_size'])) {
                    $kb = round($sf['file_size'] / 1024, 1);
                    $sizeStr = $kb > 1024 ? round($kb/1024, 1).' MB' : $kb.' KB';
                }
                $uploadedAt = !empty($sf['uploaded_at']) ? date('d/m/Y', strtotime($sf['uploaded_at'])) : '';
                $langLabel  = !empty($sf['language']) ? strtoupper($sf['language']) : '';
                $version    = !empty($sf['version'])  ? 'v'.$sf['version'] : '';
                $uploader   = !empty($sf['uploader_name']) ? trim($sf['uploader_name']) : '';

                $html .= '<a href="' . h($fileUrl) . '" target="_blank" rel="noopener" ';
                $html .= 'style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:'.$bgGrad.';border:1.5px solid '.$borderCol.';border-radius:12px;text-decoration:none;transition:.15s" ';
                $html .= 'onmouseover="this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.transform=\'\'">';
                $html .= '<div style="width:38px;height:38px;border-radius:10px;background:'.$bgColor.';display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas '.$icon.'" style="color:#fff;font-size:15px"></i></div>';
                $html .= '<div style="flex:1;min-width:0">';
                $fname2 = !empty($sf['file_name']) ? $sf['file_name'] : basename($fileUrl);
                $html .= '<div style="font-size:13px;font-weight:700;color:'.$textColor.'">' . h($fname2) . '</div>';
                $meta = array_filter([$langLabel, $version, $sizeStr, $uploadedAt ? ($th?'อัปโหลด ':'Uploaded ').$uploadedAt : '', $uploader ? 'by '.$uploader : '']);
                if ($meta) $html .= '<div style="font-size:10px;color:#6b7280;margin-top:1px">' . implode(' · ', $meta) . '</div>';
                $html .= '</div>';
                $html .= '<i class="fas fa-download" style="color:'.$textColor.';opacity:.5;font-size:12px"></i>';
                $html .= '</a>';
            }
        }

        $html .= '</div>'; // flex column

        if ($lastUp) {
            $html .= '<div style="font-size:10px;color:#9ca3af;text-align:right;margin-bottom:10px"><i class="fas fa-clock" style="margin-right:3px"></i>' . ($th?'อัปเดตล่าสุด: ':'Last updated: ') . date('d/m/Y', strtotime($lastUp)) . '</div>';
        }

        $html .= '<div style="border-top:1px solid #f3f4f6;margin-bottom:12px"></div>';
    }

    // ── External SDS links (always shown) ─────────────────────────
    $html .= '<div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:5px">';
    if ($hasAny) {
        $html .= '<i class="fas fa-globe"></i> ' . ($th ? 'แหล่งข้อมูลภายนอก' : 'External References');
    } else {
        $html .= '<i class="fas fa-search"></i> ' . ($th ? 'ค้นหา SDS จากแหล่งภายนอก' : 'Find SDS from External Sources');
    }
    $html .= '</div>';

    // No-upload banner
    if (!$hasAny) {
        $html .= '<div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:12px;display:flex;gap:10px;align-items:flex-start">';
        $html .= '<div style="width:32px;height:32px;border-radius:9px;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-exclamation" style="color:#fff;font-size:13px"></i></div>';
        $html .= '<div><div style="font-size:12px;font-weight:700;color:#92400e">' . ($th ? 'ยังไม่มีไฟล์ SDS ในระบบ' : 'No SDS file uploaded yet') . '</div>';
        $html .= '<div style="font-size:11px;color:#b45309;margin-top:2px;line-height:1.5">' . ($th ? 'สามารถกดปุ่มด้านล่างเพื่อค้นหา SDS จากฐานข้อมูลสารเคมีโลก' : 'Use the buttons below to search SDS from global chemical databases') . '</div>';
        $html .= '</div></div>';
    }

    // External link grid
    $extLinks = [
        [
            'url'   => 'https://www.google.com/search?q=' . $qEnc . '+SDS+PDF+safety+data+sheet',
            'icon'  => 'fab fa-google',
            'bg'    => '#ea4335',
            'label' => 'Google SDS',
            'sub'   => $th ? 'ค้นหาไฟล์ SDS PDF' : 'Search SDS PDF files',
        ],
        [
            'url'   => 'https://pubchem.ncbi.nlm.nih.gov/search/?query=' . $qEnc,
            'icon'  => 'fas fa-database',
            'bg'    => '#2563eb',
            'label' => 'PubChem',
            'sub'   => $th ? 'ฐานข้อมูล NCBI สหรัฐ' : 'NCBI chemical database',
        ],
        [
            'url'   => 'https://en.wikipedia.org/w/index.php?search=' . $qEnc,
            'icon'  => 'fab fa-wikipedia-w',
            'bg'    => '#374151',
            'label' => 'Wikipedia',
            'sub'   => $th ? 'บทความ Wikipedia' : 'Wikipedia article',
        ],
        [
            'url'   => 'https://www.chemspider.com/Search.aspx?q=' . $casEnc,
            'icon'  => 'fas fa-atom',
            'bg'    => '#7c3aed',
            'label' => 'ChemSpider',
            'sub'   => $th ? 'ค้นหาโครงสร้างโมเลกุล' : 'Molecular structure search',
        ],
        [
            'url'   => 'https://echa.europa.eu/registration-dossier/-/registered-dossier/search/?term=' . $casEnc,
            'icon'  => 'fas fa-shield-alt',
            'bg'    => '#0891b2',
            'label' => 'ECHA',
            'sub'   => $th ? 'ฐานข้อมูล EU สารเคมี' : 'EU chemical registry',
        ],
        [
            'url'   => 'https://www.sigmaaldrich.com/TH/en/search#q=' . $qEnc . '&t=products',
            'icon'  => 'fas fa-flask',
            'bg'    => '#dc2626',
            'label' => 'Sigma-Aldrich',
            'sub'   => $th ? 'ดาวน์โหลด SDS ฟรี' : 'Free SDS download',
        ],
    ];

    $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:7px">';
    foreach ($extLinks as $el) {
        $html .= '<a href="' . $el['url'] . '" target="_blank" rel="noopener" ';
        $html .= 'style="display:flex;align-items:center;gap:9px;padding:9px 11px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;transition:.15s" ';
        $html .= 'onmouseover="this.style.borderColor=\'' . $el['bg'] . '\';this.style.background=\'#fff\';this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.background=\'#f8fafc\';this.style.transform=\'\'">';
        $html .= '<div style="width:30px;height:30px;border-radius:8px;background:' . $el['bg'] . ';display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="' . $el['icon'] . '" style="color:#fff;font-size:12px"></i></div>';
        $html .= '<div style="min-width:0">';
        $html .= '<div style="font-size:11px;font-weight:700;color:#1e293b">' . h($el['label']) . '</div>';
        $html .= '<div style="font-size:9.5px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . h($el['sub']) . '</div>';
        $html .= '</div></a>';
    }
    $html .= '</div>'; // grid

    $html .= '</div>'; // card padding
    $html .= '</div>'; // main SDS card

    // ══ SAFETY INFO CARDS ══════════════════════════════════════════
    if ($hasInfo) {
        $infoCards = [
            ['handling_procedures', 'fa-hand-sparkles',   '#10b981', '#f0fdf4', '#86efac', $th?'วิธีการจัดการ / การใช้งาน':'Handling Procedures'],
            ['first_aid_measures',  'fa-kit-medical',      '#dc2626', '#fef2f2', '#fecaca', $th?'การปฐมพยาบาล':'First Aid Measures'],
            ['storage_requirements','fa-warehouse',        '#f59e0b', '#fffbeb', '#fde68a', $th?'การจัดเก็บ':'Storage Requirements'],
            ['disposal_methods',    'fa-trash-alt',        '#6b7280', '#f9fafb', '#e5e7eb', $th?'การกำจัด / ทิ้ง':'Disposal Methods'],
        ];
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
        foreach ($infoCards as [$field, $icon, $color, $bg, $border, $label]) {
            if (empty($c[$field])) continue;
            $text = $c[$field];
            $preview = mb_strlen($text, 'UTF-8') > 120 ? mb_substr($text, 0, 120, 'UTF-8') . '…' : $text;
            $uid = 'sds-info-' . $cid . '-' . preg_replace('/[^a-z]/', '', $field);
            $html .= '<div style="background:'.$bg.';border:1px solid '.$border.';border-radius:12px;padding:12px;position:relative;overflow:hidden">';
            $html .= '<div style="display:flex;align-items:center;gap:7px;margin-bottom:7px">';
            $html .= '<div style="width:26px;height:26px;border-radius:7px;background:'.$color.';display:flex;align-items:center;justify-content:center"><i class="fas '.$icon.'" style="color:#fff;font-size:11px"></i></div>';
            $html .= '<div style="font-size:11px;font-weight:700;color:'.$color.'">' . h($label) . '</div>';
            $html .= '</div>';
            $html .= '<div id="'.$uid.'" style="font-size:11px;color:#374151;line-height:1.55">' . h($preview) . '</div>';
            if (mb_strlen($text, 'UTF-8') > 120) {
                $html .= '<button onclick="toggleSdsInfo(\''.$uid.'\',this,\''.addslashes(h($text)).'\',\''.($th?'ดูน้อยลง':'Show less').'\')" ';
                $html .= 'style="margin-top:6px;background:none;border:none;font-size:10px;font-weight:700;color:'.$color.';cursor:pointer;padding:0;font-family:inherit">';
                $html .= ($th ? '▼ ดูทั้งหมด' : '▼ Show all') . '</button>';
            }
            $html .= '</div>';
        }
        $html .= '</div>'; // info grid
    }

    $html .= '</div>'; // section-body
    return $html;
}


// ═══════════════════════════════════════════════════════════════════
// RENDER — CHEMICAL LIST (multiple results)
// ═══════════════════════════════════════════════════════════════════

function renderChemicalList(array $results, string $term, string $lang): string {
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    $html .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">';
    $html .= '<i class="fas fa-search" style="color:#0ea5e9;font-size:16px"></i>';
    $html .= '<div>';
    $html .= '<div style="font-weight:700;color:#0369a1">' . ($th?'ผลการค้นหา:':'Search Results:') . ' <strong>"' . h($term) . '"</strong></div>';
    $html .= '<div style="font-size:12px;color:#0284c7">' . ($th?'พบ ':'Found ') . count($results) . ($th?' รายการ':' items') . ($th?'  (คลิกที่การ์ดเพื่อดูรายละเอียด)':'  (click a card for details)') . '</div>';
    $html .= '</div></div>';

    $html .= '<div class="result-list">';
    foreach ($results as $item) {
        $c  = $item['chemical'];
        $cs = $item['containers'];

        $imgUrl = '';
        if (!empty($c['cas_number'])) $imgUrl = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/' . urlencode($c['cas_number']) . '/PNG?record_type=2d&image_size=200x200';
        if (!empty($c['image_url'])) $imgUrl = $c['image_url'];

        $signalWord  = $c['signal_word'] ?? '';
        $signalClass = ($signalWord === 'Danger') ? 'danger' : (($signalWord === 'Warning') ? 'warning' : '');
        $pill_sds    = (!empty($c['sds_url']) || !empty($c['sds_pdf_path']));

        $html .= '<div class="rc">';
        $html .= '<div class="rc-hdr" onclick="sendMsg(\'' . escAttrJS(($th?'ค้นหา ':' ') . $c['name']) . '\')">';

        // Image
        $html .= '<div class="rc-img">';
        if ($imgUrl) {
            $html .= '<img src="' . h($imgUrl) . '" alt="' . h($c['molecular_formula'] ?? '') . '" loading="lazy" onerror="this.parentElement.innerHTML=\'<i class=&quot;fas fa-atom no-img&quot;></i>\'">';
        } else {
            $html .= '<i class="fas fa-atom no-img"></i>';
        }
        $html .= '</div>';

        // Meta
        $html .= '<div class="rc-meta">';
        $html .= '<div class="rc-name">' . h($c['name']) . '</div>';
        $html .= '<div class="rc-pills">';
        if ($c['cas_number'])        $html .= pill('fas fa-hashtag', 'CAS: ' . h($c['cas_number']), 'cas');
        if ($c['molecular_formula']) $html .= pill('fas fa-atom', h($c['molecular_formula']), 'formula');
        if ($c['molecular_weight'])  $html .= pill('fas fa-weight', h($c['molecular_weight']) . ' g/mol', 'mw');
        if ($c['physical_state'])    $html .= pill('fas fa-cube', h(ucfirst($c['physical_state'])), 'state');
        if ($signalClass)    $html .= pill('fas fa-exclamation-triangle', $signalWord, $signalClass);
        if ($pill_sds)      $html .= '<span class="pill" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe"><i class="fas fa-file-shield"></i> SDS</span>';
        $html .= '</div>';
        if (!empty($c['description'])) {
            $html .= '<div class="rc-desc">' . h(mb_substr($c['description'], 0, 120, 'UTF-8')) . '…</div>';
        }
        $html .= '</div></div>'; // rc-meta + rc-hdr

        // Body: location chips + actions
        $html .= '<div class="rc-body">';
        if (!empty($cs)) {
            $buildings = array_unique(array_filter(array_column($cs, 'building')));
            $html .= '<div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:6px">' . ($th?'📍 จัดเก็บที่:':'📍 Stored at:') . '</div>';
            $html .= '<div class="rc-loc-summary">';
            foreach ($buildings as $bld) $html .= '<span class="rc-loc-chip"><i class="fas fa-building"></i>' . h($bld) . '</span>';
            $totalQ = array_sum(array_map(fn($con) => floatval($con['current_quantity']), $cs));
            $unit   = $cs[0]['quantity_unit'] ?? '';
            $html .= '<span class="rc-loc-chip" style="background:#dcfce7;color:#166534"><i class="fas fa-boxes-stacked"></i>' . number_format($totalQ, 1) . ' ' . $unit . '</span>';
            $html .= '</div>';
        } else {
            $html .= '<span style="font-size:11px;color:#f59e0b"><i class="fas fa-box-open"></i> ' . ($th?'ไม่มีในคลัง':'Not in stock') . '</span>';
        }
        $html .= '<div class="rc-actions">';
        $html .= '<button class="rc-action-btn primary" onclick="sendMsg(\'' . escAttrJS(($th?'ค้นหา ':' ') . $c['name']) . '\')"><i class="fas fa-search"></i> ' . ($th?'รายละเอียด':'Details') . '</button>';
        if ($c['cas_number']) {
            $html .= '<button class="rc-action-btn outline" onclick="sendMsg(\'' . h($c['cas_number']) . '\')"><i class="fas fa-hashtag"></i> CAS</button>';
        }
        $html .= '<button class="rc-action-btn outline" onclick="sendMsg(\'SDS ' . escAttrJS($c['name']) . '\')"><i class="fas fa-file-shield"></i> SDS</button>';
        if (!empty($cs)) {
            $html .= '<button class="rc-action-btn outline" onclick="sendMsg(\'' . ($th?'อยู่ที่ไหน ':'where is ') . escAttrJS($c['name']) . '\')"><i class="fas fa-map-marker-alt"></i> ' . ($th?'ตำแหน่ง':'Location') . '</button>';
        }
        $html .= '</div>';
        $html .= '</div>'; // rc-body
        $html .= '</div>'; // rc
    }
    $html .= '</div></div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — LOCATION RESULTS (dedicated location query)
// ═══════════════════════════════════════════════════════════════════

function renderLocationResults(array $results, string $term, string $lang): string {
    global $user;
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    $html .= '<div style="background:var(--ok-bg);border:1px solid var(--ok-border);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">';
    $html .= '<i class="fas fa-map-marker-alt" style="color:var(--ok);font-size:16px"></i>';
    $html .= '<div>';
    $html .= '<div style="font-weight:700;color:#166534">' . ($th?'ตำแหน่งจัดเก็บ:':'Storage Locations:') . ' <strong>"' . h($term) . '"</strong></div>';
    $html .= '<div style="font-size:12px;color:#15803d">' . ($th?'พบ ':'Found ') . count($results) . ($th?' สาร':' chemicals') . '</div>';
    $html .= '</div></div>';

    foreach ($results as $item) {
        $c  = $item['chemical'];
        $cs = $item['containers'];
        if (empty($cs)) continue;

        $html .= '<div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:var(--shadow)">';

        // Chemical mini header
        $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border)">';
        $html .= '<div style="font-size:20px">🧪</div>';
        $html .= '<div>';
        $html .= '<div style="font-size:16px;font-weight:800;color:var(--c1)">' . h($c['name']) . '</div>';
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:4px">';
        if ($c['cas_number'])        $html .= pill('fas fa-hashtag', 'CAS: ' . h($c['cas_number']), 'cas');
        if ($c['molecular_formula']) $html .= pill('fas fa-atom', h($c['molecular_formula']), 'formula');
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<button class="rc-action-btn primary" style="margin-left:auto" onclick="sendMsg(\'' . escAttrJS(($th?'ค้นหา ':' ') . $c['name']) . '\')"><i class="fas fa-info-circle"></i> ' . ($th?'ข้อมูลเต็ม':'Full Info') . '</button>';
        $html .= '</div>';

        $html .= renderLocationSection($cs, $lang, $user ?? null);
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════════
// RENDER — 3D MODEL QUERY (smart response: found / no model / not found)
// ═══════════════════════════════════════════════════════════════════

function renderModel3DResult(array $results, string $term, string $lang): string {
    $th   = $lang === 'th';

    // If multiple matches, pick best (first) and note the rest
    $primary  = $results[0];
    $c        = $primary['chemical'];
    $chemId   = (int)($c['id'] ?? 0);
    $cas      = $c['cas_number'] ?? '';
    $chemName = $c['name'];

    // Fetch 3D models
    $models = fetch3DModels($chemId, $cas);

    // Sort into buckets
    $glbModels   = [];
    $embedModels = [];
    $localGlb    = trim($c['model_3d_glb']  ?? '');
    $localUsdz   = trim($c['model_3d_usdz'] ?? '');
    $localUrl    = trim($c['model_3d_url']  ?? '');
    if ($localGlb) {
        $glbUrl = (str_starts_with($localGlb, 'http') || str_starts_with($localGlb, '/'))
            ? $localGlb : '/v1/assets/uploads/' . ltrim($localGlb, '/');
        $glbModels[] = ['file_url' => $glbUrl, 'label' => $chemName,
                        'thumbnail_path' => '', 'usdz_url' => $localUsdz];
    }
    if ($localUrl) {
        if (isEmbedUrl($localUrl)) {
            $embedModels[] = ['embed_url' => $localUrl, 'embed_provider' => '',
                              'label' => $chemName, 'thumbnail_path' => ''];
        } elseif (!$localGlb) {
            $glbModels[] = ['file_url' => $localUrl, 'label' => $chemName,
                            'thumbnail_path' => '', 'usdz_url' => ''];
        }
    }
    foreach ($models as $m) {
        $src = $m['source_type'] ?? 'upload';
        if ($src === 'embed' && !empty($m['embed_url'])) {
            $embedModels[] = $m;
        } elseif (!empty($m['file_url'])) {
            $ext = strtolower($m['extension'] ?? pathinfo($m['file_url'], PATHINFO_EXTENSION));
            if (in_array($ext, ['glb', 'gltf'])) {
                $glbModels[] = $m;
            } elseif (isEmbedUrl($m['file_url'])) {
                $m['embed_url'] = $m['file_url']; $m['embed_provider'] = $m['embed_provider'] ?? '';
                $embedModels[] = $m;
            }
        }
    }
    $hasGlb   = !empty($glbModels);
    $hasEmbed = !empty($embedModels);
    $has3D    = $hasGlb || $hasEmbed;

    $html = '<div class="chembot-response">';

    // ── Chemical identity header ──────────────────────────────────
    $html .= '<div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #bae6fd;border-radius:14px;padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px">';
    $html .= '<div style="font-size:28px">&#x1F9CA;</div>';
    $html .= '<div style="flex:1">';
    $html .= '<div style="font-size:17px;font-weight:800;color:#0369a1">' . h($chemName) . '</div>';
    $meta = [];
    if ($cas)                         $meta[] = 'CAS: <code>' . h($cas) . '</code>';
    if (!empty($c['molecular_formula'])) $meta[] = h($c['molecular_formula']);
    if ($meta) $html .= '<div style="font-size:12px;color:#0284c7;margin-top:3px">' . implode(' &nbsp;·&nbsp; ', $meta) . '</div>';
    $html .= '</div>';
    // Show count of other matches if any
    if (count($results) > 1) {
        $others = count($results) - 1;
        $html .= '<div style="font-size:11px;color:#64748b;text-align:right">';
        $html .= ($th ? 'พบสาร' : 'Found') . ' <strong>' . count($results) . '</strong> ' . ($th ? 'รายการ' : 'results') . '<br>';
        $html .= '<span style="color:#0ea5e9;cursor:pointer" onclick="sendMsg(\'' . escAttrJS(($th ? 'ค้นหา ' : '') . $term) . '\')">' . ($th ? 'ดูทั้งหมด →' : 'See all →') . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // ── Answer: has 3D / no 3D ────────────────────────────────────
    if ($has3D) {
        // ✅ Found — show answer badge + models
        $total = count($glbModels) + count($embedModels);
        $html .= '<div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:12px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px">';
        $html .= '<span style="font-size:20px">&#x2705;</span>';
        $html .= '<div>';
        $html .= '<div style="font-weight:700;color:#166534;font-size:14px">';
        $html .= $th ? "มีโมเดล 3D ในระบบ ({$total} รายการ)" : "3D model found ({$total} item" . ($total > 1 ? 's' : '') . ")";
        $html .= '</div>';
        $typeDesc = [];
        if ($hasGlb)   $typeDesc[] = $th ? count($glbModels) . ' GLB (interactive inline)' : count($glbModels) . ' GLB file' . (count($glbModels) > 1 ? 's' : '');
        if ($hasEmbed) $typeDesc[] = $th ? count($embedModels) . ' Embed (Kiri/Sketchfab)' : count($embedModels) . ' embed viewer' . (count($embedModels) > 1 ? 's' : '');
        $html .= '<div style="font-size:11px;color:#15803d">' . implode(' &nbsp;+&nbsp; ', $typeDesc) . '</div>';
        $html .= '</div></div>';

        // GLB inline viewers
        if ($hasGlb) {
            $html .= '<div class="glb-list">';
            foreach ($glbModels as $gm) {
                $glbSrc  = $gm['file_url'];
                $usdzSrc = $gm['usdz_url'] ?? '';
                $lbl     = $gm['label'] ?? $chemName;
                $jsLabel = addslashes($lbl);
                $jsUrl   = addslashes($glbSrc);
                $html .= '<div class="glb-card">';
                $html .= '<div class="glb-mv-slot" data-src="' . h($glbSrc) . '" data-usdz="' . h($usdzSrc) . '">';
                $html .= '<div class="glb-mv-loading"><div class="glb-mv-spinner"></div>'
                    . '<div class="glb-mv-loading-txt">' . ($th ? 'กำลังโหลด 3D...' : 'Loading 3D...') . '</div></div>';
                $html .= '</div>';
                $html .= '<div class="glb-footer">';
                $html .= '<span class="glb-footer-label">' . h($lbl) . '</span>';
                $html .= '<span class="glb-badge">GLB</span>';
                if ($usdzSrc) $html .= '<a href="' . h($usdzSrc) . '" class="glb-ar-btn">&#x1F4F1; AR</a>';
                $html .= '<button onclick="openGLBOverlay(\'' . $jsUrl . '\',\'' . $jsLabel . '\')" '
                    . 'style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:rgba(99,102,241,.12);color:#4338ca;border:1px solid #c7d2fe;cursor:pointer;margin-left:4px"'
                    . ' title="' . ($th?'เปิดเต็มจอ':'Fullscreen') . '">&#x26F6;</button>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
        }

        // Embed viewers
        foreach ($embedModels as $em) {
            $eUrl  = $em['embed_url'];
            $eProv = $em['embed_provider'] ?? '';
            $eLbl  = $em['label'] ?? '';
            [$pLabel, , $pIcon, $pColor] = providerInfo($eUrl, $eProv);
            $html .= '<div class="embed3d-container">';
            $html .= '<div class="embed3d-header" style="background:' . $pColor . '">';
            $html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>';
            $html .= '<span class="embed3d-provider">' . h($pLabel) . '</span>';
            if ($eLbl) $html .= '<span class="embed3d-model-name">— ' . h($eLbl) . '</span>';
            $html .= '<a href="' . h($eUrl) . '" target="_blank" rel="noopener" class="embed3d-fullscreen-btn">&#x26F6;</a>';
            $html .= '</div>';
            $html .= '<div class="embed3d-frame-wrap">';
            $html .= '<iframe src="' . h($eUrl) . '" allow="autoplay; fullscreen; vr; xr; accelerometer; gyroscope" allowfullscreen'
                . ' loading="lazy" style="width:100%;height:320px;border:0;display:block;background:#0f172a"></iframe>';
            $html .= '</div></div>';
        }

    } else {
        // ❌ Chemical found but no 3D model in system
        $html .= '<div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:12px;padding:12px 14px;margin-bottom:12px">';
        $html .= '<div style="display:flex;align-items:flex-start;gap:10px">';
        $html .= '<span style="font-size:24px;flex-shrink:0">&#x1F4ED;</span>';
        $html .= '<div>';
        $html .= '<div style="font-weight:700;color:#92400e;font-size:14px;margin-bottom:4px">';
        $html .= $th ? 'ยังไม่มีโมเดล 3D ในระบบ' : 'No 3D model in system yet';
        $html .= '</div>';
        $html .= '<div style="font-size:12px;color:#78350f;line-height:1.6">';
        $html .= $th
            ? 'สาร <strong>' . h($chemName) . '</strong> มีข้อมูลในฐานข้อมูล แต่ยังไม่มีโมเดล 3D / AR ที่อัปโหลดไว้'
            : 'Chemical <strong>' . h($chemName) . '</strong> exists in the database but has no 3D/AR model uploaded yet';
        $html .= '</div>';
        $html .= '</div></div>';
        // Suggest action
        $html .= '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #fde68a;font-size:11px;color:#92400e">';
        $html .= '<i class="fas fa-lightbulb" style="color:#f59e0b"></i> ';
        $html .= $th
            ? 'ต้องการเพิ่มโมเดล 3D? ไปที่เมนู <strong>CAS-Map / 3D Models</strong> แล้วอัปโหลดไฟล์ GLB หรือใส่ลิงก์ Kiri Engine'
            : 'Want to add a 3D model? Go to <strong>CAS-Map / 3D Models</strong> and upload a GLB file or paste a Kiri Engine link';
        $html .= '</div></div>';

        // Show external 3D sources as fallback
        $q = urlencode($cas ?: $c['molecular_formula'] ?? $chemName);
        $html .= '<div style="margin-top:12px">';
        $html .= '<div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:8px"><i class="fas fa-globe"></i> ';
        $html .= $th ? 'โมเดล 3D ออนไลน์ (แหล่งภายนอก)' : 'Online 3D Models (external)';
        $html .= '</div><div class="model-grid">';
        $extModels = [
            ['https://pubchem.ncbi.nlm.nih.gov/search/?query='.$q.'#section=3D-Conformer', 'linear-gradient(135deg,#0ea5e9,#0284c7)', 'PubChem 3D', $th?'โครงสร้าง 3D':'3D Conformer'],
            ['https://molview.org/?q='.$q, 'linear-gradient(135deg,#8b5cf6,#7c3aed)', 'MolView', $th?'โมเดลอินเตอร์แอคทีฟ':'Interactive'],
            ['https://sketchfab.com/search?q='.urlencode($chemName).'&type=models', 'linear-gradient(135deg,#d97706,#f59e0b)', 'Sketchfab', $th?'โมเดล 3D ชุมชน':'Community models'],
        ];
        foreach ($extModels as [$url, $bg, $title, $sub]) {
            $html .= '<a href="' . h($url) . '" target="_blank" class="model-link" style="background:' . h($bg) . '">';
            $html .= $title . '<small>' . $sub . '</small>';
            $html .= '</a>';
        }
        $html .= '</div></div>';
    }

    // Quick-action button to see full chemical info
    $html .= '<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">';
    $html .= '<button class="rc-action-btn primary" onclick="sendMsg(\'' . escAttrJS(($th ? 'ค้นหา ' : '') . $chemName) . '\')">';
    $html .= '<i class="fas fa-info-circle"></i> ' . ($th ? 'ดูข้อมูลสารทั้งหมด' : 'Full chemical info') . '</button>';
    if ($cas) {
        $html .= '<button class="rc-action-btn outline" onclick="sendMsg(\'' . h($cas) . '\')">';
        $html .= '<i class="fas fa-hashtag"></i> ' . h($cas) . '</button>';
    }
    $html .= '</div>';

    $html .= '</div>'; // chembot-response
    return $html;
}

// HELPER — Fetch local 3D models for a chemical (all linkage paths)
// Mirrors getCasPackagingMap logic:
//   Path A: chemical_id directly on packaging_3d_models (no packaging_id)
//   Path B: via chemical_packaging → packaging_3d_models (packaging_id link)
//   Path C: via chemical_packaging.model_3d_id direct link
//   Path D: cas_number match on packaging_3d_models
// ═══════════════════════════════════════════════════════════════════

function fetch3DModels(int $chemId, string $cas): array {
    if ($chemId <= 0 && $cas === '') return [];
    try {
        $pdo    = Database::getInstance();
        $models = [];
        $seen   = [];

        // ── Path A: directly linked by chemical_id ─────────────────
        if ($chemId > 0) {
            $st = $pdo->prepare("
                SELECT m.id, m.label, m.description, m.container_type, m.source_type,
                       m.file_url, m.file_path, m.embed_url, m.embed_provider,
                       m.original_name, m.extension, m.thumbnail_path, m.ar_enabled,
                       m.is_default, m.sort_order
                FROM packaging_3d_models m
                WHERE m.chemical_id = ? AND m.is_active = 1
                ORDER BY m.is_default DESC, m.sort_order ASC, m.id DESC
                LIMIT 6");
            $st->execute([$chemId]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (!isset($seen[$row['id']])) { $seen[$row['id']] = true; $models[] = $row; }
            }
        }

        // ── Path B: via packaging_id link (cas-map saves this way) ──
        if ($chemId > 0) {
            $st = $pdo->prepare("
                SELECT m.id, m.label, m.description, m.container_type, m.source_type,
                       m.file_url, m.file_path, m.embed_url, m.embed_provider,
                       m.original_name, m.extension, m.thumbnail_path, m.ar_enabled,
                       m.is_default, m.sort_order
                FROM chemical_packaging cp
                INNER JOIN packaging_3d_models m ON (m.packaging_id = cp.id AND m.is_active = 1)
                WHERE cp.chemical_id = ? AND cp.is_active = 1
                ORDER BY m.is_default DESC, m.sort_order ASC, m.id DESC
                LIMIT 6");
            $st->execute([$chemId]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (!isset($seen[$row['id']])) { $seen[$row['id']] = true; $models[] = $row; }
            }
        }

        // ── Path C: via chemical_packaging.model_3d_id ─────────────
        if ($chemId > 0) {
            $st = $pdo->prepare("
                SELECT m.id, m.label, m.description, m.container_type, m.source_type,
                       m.file_url, m.file_path, m.embed_url, m.embed_provider,
                       m.original_name, m.extension, m.thumbnail_path, m.ar_enabled,
                       m.is_default, m.sort_order
                FROM chemical_packaging cp
                INNER JOIN packaging_3d_models m ON (cp.model_3d_id = m.id AND m.is_active = 1)
                WHERE cp.chemical_id = ? AND cp.is_active = 1
                ORDER BY m.is_default DESC, m.sort_order ASC, m.id DESC
                LIMIT 6");
            $st->execute([$chemId]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (!isset($seen[$row['id']])) { $seen[$row['id']] = true; $models[] = $row; }
            }
        }

        // ── Path D: cas_number field (fallback) ─────────────────────
        if ($cas !== '' && count($models) === 0) {
            $st = $pdo->prepare("
                SELECT m.id, m.label, m.description, m.container_type, m.source_type,
                       m.file_url, m.file_path, m.embed_url, m.embed_provider,
                       m.original_name, m.extension, m.thumbnail_path, m.ar_enabled,
                       m.is_default, m.sort_order
                FROM packaging_3d_models m
                WHERE m.cas_number = ? AND m.is_active = 1
                ORDER BY m.is_default DESC, m.sort_order ASC, m.id DESC
                LIMIT 6");
            $st->execute([$cas]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (!isset($seen[$row['id']])) { $seen[$row['id']] = true; $models[] = $row; }
            }
        }

        return array_slice($models, 0, 8);
    } catch (\Throwable $e) {
        return [];
    }
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — EXTERNAL LINKS + LOCAL 3D / AR
// ═══════════════════════════════════════════════════════════════════

function providerInfo(string $url, string $storedProvider): array {
    // Returns [label, badgeClass, icon, color] for a known 3D embed provider
    $url_l = strtolower($url);
    $p     = strtolower($storedProvider);
    if (str_contains($url_l, 'kiri') || $p === 'kiri') {
        return ['Kiri Engine', 'kiri', 'fa-cube', 'linear-gradient(135deg,#1d4ed8,#3b82f6)'];
    }
    if (str_contains($url_l, 'sketchfab') || $p === 'sketchfab') {
        return ['Sketchfab', 'sketchfab', 'fa-cube', 'linear-gradient(135deg,#d97706,#f59e0b)'];
    }
    if (str_contains($url_l, '3dmol') || $p === '3dmol') {
        return ['3Dmol.js', 'generic', 'fa-atom', 'linear-gradient(135deg,#7c3aed,#8b5cf6)'];
    }
    if (str_contains($url_l, 'molview') || $p === 'molview') {
        return ['MolView', 'generic', 'fa-atom', 'linear-gradient(135deg,#7c3aed,#8b5cf6)'];
    }
    return [$storedProvider ?: 'External 3D', 'generic', 'fa-cube', 'linear-gradient(135deg,#475569,#64748b)'];
}

function isEmbedUrl(string $url): bool {
    return (bool)preg_match('/kiri|sketchfab|3dwarehouse|3dmol|molview|model-viewer\.dev/i', $url);
}

function renderExternalLinks(array $c, string $lang): string {
    $th      = $lang === 'th';
    $name    = $c['name'];
    $cas     = $c['cas_number'] ?? '';
    $form    = $c['molecular_formula'] ?? '';
    $chemId  = (int)($c['id'] ?? 0);
    $q       = urlencode($cas ?: $form ?: $name);

    // ── Gather local 3D model sources ──────────────────────────────
    $localGlb   = trim($c['model_3d_glb']  ?? '');
    $localUsdz  = trim($c['model_3d_usdz'] ?? '');
    $localUrl   = trim($c['model_3d_url']  ?? '');
    $packModels = fetch3DModels($chemId, $cas);

    // ── Sort into typed buckets ────────────────────────────────────
    $glbModels   = [];   // uploaded GLB/GLTF — open via overlay
    $embedModels = [];   // embed URLs (Kiri, Sketchfab) — iframe inline

    // Seed from chemical row direct fields
    if ($localGlb) {
        $glbUrl = (str_starts_with($localGlb, 'http') || str_starts_with($localGlb, '/'))
            ? $localGlb : '/v1/assets/uploads/' . ltrim($localGlb, '/');
        $glbModels[] = ['file_url' => $glbUrl, 'label' => $name,
                        'thumbnail_path' => '', 'usdz_url' => $localUsdz];
    }
    if ($localUrl) {
        if (isEmbedUrl($localUrl)) {
            $embedModels[] = ['embed_url' => $localUrl, 'embed_provider' => '',
                              'label' => $name, 'thumbnail_path' => ''];
        } elseif (!$localGlb) {
            $glbModels[] = ['file_url' => $localUrl, 'label' => $name,
                            'thumbnail_path' => '', 'usdz_url' => ''];
        }
    }

    // Add from packaging_3d_models table
    foreach ($packModels as $m) {
        $src = $m['source_type'] ?? 'upload';
        if ($src === 'embed' && !empty($m['embed_url'])) {
            $embedModels[] = $m;
        } elseif (!empty($m['file_url'])) {
            $ext = strtolower($m['extension'] ?? pathinfo($m['file_url'], PATHINFO_EXTENSION));
            if (in_array($ext, ['glb', 'gltf'])) {
                $glbModels[] = $m;
            } elseif (isEmbedUrl($m['file_url'])) {
                $m['embed_url']      = $m['file_url'];
                $m['embed_provider'] = $m['embed_provider'] ?? '';
                $embedModels[] = $m;
            }
        }
    }

    $hasGlb   = !empty($glbModels);
    $hasEmbed = !empty($embedModels);
    $hasLocal3D = $hasGlb || $hasEmbed;

    // ── Section header ─────────────────────────────────────────────
    $sectionTitle = $hasLocal3D
        ? ($th ? '🧊 โมเดล 3D / AR & แหล่งข้อมูล' : '🧊 3D / AR Model & Resources')
        : ($th ? '🌐 แหล่งข้อมูลออนไลน์' : '🌐 Online Resources');

    $html  = sectionToggle('fas fa-cube', $sectionTitle, $hasLocal3D);
    $html .= '<div class="section-body' . ($hasLocal3D ? '' : ' collapsed') . '">';

    // ══════════════════════════════════════════════════════════════
    // A. LOCAL 3D MODELS
    // ══════════════════════════════════════════════════════════════
    if ($hasLocal3D) {
        $html .= '<div class="local3d-wrapper">';

        // ── A1. GLB cards (inline model-viewer injected by JS) ────
        if ($hasGlb) {
            $html .= '<div class="glb-list">';
            foreach ($glbModels as $gm) {
                $glbSrc  = $gm['file_url'];
                $usdzSrc = $gm['usdz_url'] ?? '';
                $lbl     = $gm['label'] ?? $name;
                $jsLabel = addslashes($lbl);
                $jsUrl   = addslashes($glbSrc);

                $html .= '<div class="glb-card">';

                // Slot: JS injects <model-viewer> here via attachModelViewers()
                $html .= '<div class="glb-mv-slot" data-src="' . h($glbSrc) . '" data-usdz="' . h($usdzSrc) . '">';
                $html .= '<div class="glb-mv-loading"><div class="glb-mv-spinner"></div>'
                    . '<div class="glb-mv-loading-txt">' . ($th ? 'กำลังโหลด 3D...' : 'Loading 3D...') . '</div></div>';
                $html .= '</div>'; // glb-mv-slot

                // Footer
                $html .= '<div class="glb-footer">';
                $html .= '<span class="glb-footer-label">' . h($lbl) . '</span>';
                $html .= '<span class="glb-badge">GLB</span>';
                if ($usdzSrc) {
                    $html .= '<a href="' . h($usdzSrc) . '" class="glb-ar-btn">&#x1F4F1; AR</a>';
                }
                $html .= '<button onclick="openGLBOverlay(\'' . $jsUrl . '\',\'' . $jsLabel . '\')" '
                    . 'style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:rgba(99,102,241,.12);color:#4338ca;border:1px solid #c7d2fe;cursor:pointer;margin-left:4px"'
                    . ' title="' . ($th?'เปิดเต็มจอ':'Fullscreen') . '">&#x26F6;</button>';
                $html .= '</div>'; // glb-footer

                $html .= '</div>'; // glb-card
            }
            $html .= '</div>'; // glb-list
        }

        // ── A2. Embed viewers (iframe inline) ─────────────────────
        foreach ($embedModels as $em) {
            $eUrl  = $em['embed_url'];
            $eProv = $em['embed_provider'] ?? '';
            $eLbl  = $em['label'] ?? '';
            [$pLabel, , $pIcon, $pColor] = providerInfo($eUrl, $eProv);

            $html .= '<div class="embed3d-container">';
            $html .= '<div class="embed3d-header" style="background:' . $pColor . '">';
            $html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>';
            $html .= '<span class="embed3d-provider">' . h($pLabel) . '</span>';
            if ($eLbl) $html .= '<span class="embed3d-model-name">— ' . h($eLbl) . '</span>';
            $html .= '<a href="' . h($eUrl) . '" target="_blank" rel="noopener" class="embed3d-fullscreen-btn" title="' . ($th?'เปิดเต็มจอ':'Open fullscreen') . '">&#x26F6;</a>';
            $html .= '</div>';
            $html .= '<div class="embed3d-frame-wrap">';
            $html .= '<iframe src="' . h($eUrl) . '" allow="autoplay; fullscreen; vr; xr; accelerometer; gyroscope" allowfullscreen'
                . ' loading="lazy" style="width:100%;height:320px;border:0;display:block;background:#0f172a"></iframe>';
            $html .= '</div>';
            $html .= '</div>'; // embed3d-container
        }

        $html .= '</div>'; // local3d-wrapper
    }

    // ══════════════════════════════════════════════════════════════
    // B. FALLBACK — External 3D resources (shown when no local model)
    // ══════════════════════════════════════════════════════════════
    if (!$hasLocal3D) {
        $html .= '<div class="fallback3d-section">';
        $html .= '<div class="fallback3d-title">&#x1F9CA; ' . ($th?'โมเดล 3D ออนไลน์':'Online 3D Models') . '</div>';
        $html .= '<div class="fallback3d-note">' . ($th?'ยังไม่มีโมเดล 3D ในระบบ — แนะนำแหล่งภายนอก':'No local 3D model — showing external sources') . '</div>';
        $html .= '<div class="model-grid">';
        $extModels = [
            ['https://pubchem.ncbi.nlm.nih.gov/search/?query='.$q.'#section=3D-Conformer', 'linear-gradient(135deg,#0ea5e9,#0284c7)', 'PubChem 3D', $th?'โครงสร้าง 3D':'3D Conformer'],
            ['https://molview.org/?q='.$q, 'linear-gradient(135deg,#8b5cf6,#7c3aed)', 'MolView', $th?'โมเดลอินเตอร์แอคทีฟ':'Interactive'],
            ['https://en.wikipedia.org/wiki/'.urlencode(str_replace(' ','_',$name)), 'linear-gradient(135deg,#475569,#334155)', 'Wikipedia', $th?'ข้อมูลทั่วไป':'General info'],
        ];
        foreach ($extModels as [$url, $bg, $title, $sub]) {
            $html .= '<a href="' . h($url) . '" target="_blank" class="model-link" style="background:' . h($bg) . '">';
            $html .= $title . '<small>' . $sub . '</small>';
            $html .= '</a>';
        }
        $html .= '</div></div>'; // model-grid + fallback3d-section
    }

    // ══════════════════════════════════════════════════════════════
    // C. EXTERNAL INFO LINKS (always shown)
    // ══════════════════════════════════════════════════════════════
    $html .= '<div class="ext-links-section" style="margin-top:12px">';
    $html .= '<div class="ext-links-title">' . ($th?'แหล่งข้อมูลออนไลน์':'Online Resources') . '</div>';
    $html .= '<div class="ext-grid">';
    $links = [
        ['https://pubchem.ncbi.nlm.nih.gov/search/?query='.$q, '#0ea5e9', 'P', 'PubChem', $th?'ข้อมูลสารเคมีครบถ้วน':'Full chemical data'],
        ['https://www.chemspider.com/Search.aspx?term='.$q, '#8b5cf6', 'CS', 'ChemSpider', $th?'ฐานข้อมูล RSC':'RSC database'],
        ['https://www.google.com/search?q='.urlencode($name.' SDS'), '#4285f4', 'G', 'Google SDS', $th?'ค้นหา SDS':'Search SDS'],
        ['https://en.wikipedia.org/wiki/'.urlencode(str_replace(' ','_',$name)), '#6366f1', 'W', 'Wikipedia', $th?'ข้อมูลทั่วไป':'General info'],
    ];
    foreach ($links as [$url, $color, $abbr, $title, $desc]) {
        $html .= '<a href="' . h($url) . '" target="_blank" class="ext-link" style="background:#f8fafc;border-color:#e2e8f0">';
        $html .= '<div class="ext-link-icon" style="background:' . h($color) . '">' . $abbr . '</div>';
        $html .= '<div class="ext-link-info"><strong>' . $title . '</strong><small>' . $desc . '</small></div>';
        $html .= '<i class="fas fa-external-link-alt arr"></i>';
        $html .= '</a>';
    }
    $html .= '</div></div>'; // ext-grid + ext-links-section

    $html .= '</div>'; // section-body
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — EXPIRY REPORT
// ═══════════════════════════════════════════════════════════════════

function renderExpiryReport(string $lang): string {
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    $rows = Database::fetchAll("
        SELECT con.id, con.qr_code, con.current_quantity, con.quantity_unit,
               con.expiry_date, con.batch_number,
               c.name as chem_name, c.cas_number,
               CONCAT(u.first_name,' ',u.last_name) as owner_name,
               b.name as building, r.name as room,
               cab.name as cabinet, sl.name as slot,
               DATEDIFF(con.expiry_date, CURDATE()) as days_left
        FROM containers con
        JOIN chemicals c  ON con.chemical_id = c.id
        JOIN users     u  ON con.owner_id    = u.id
        LEFT JOIN slots    sl  ON con.location_slot_id = sl.id
        LEFT JOIN shelves  sh  ON sl.shelf_id   = sh.id
        LEFT JOIN cabinets cab ON sh.cabinet_id = cab.id
        LEFT JOIN rooms    r   ON cab.room_id   = r.id
        LEFT JOIN buildings b  ON r.building_id = b.id
        WHERE con.status = 'active' AND c.is_active = 1
          AND con.expiry_date IS NOT NULL
          AND con.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY con.expiry_date ASC
        LIMIT 60
    ");

    $expired  = array_values(array_filter($rows, fn($r) => (int)$r['days_left'] < 0));
    $critical = array_values(array_filter($rows, fn($r) => (int)$r['days_left'] >= 0 && (int)$r['days_left'] <= 14));
    $warning  = array_values(array_filter($rows, fn($r) => (int)$r['days_left'] > 14 && (int)$r['days_left'] <= 90));

    // Header
    $html .= '<div style="background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1px solid #fecaca;border-radius:14px;padding:16px;margin-bottom:14px">';
    $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">';
    $html .= '<div style="width:36px;height:36px;background:#dc2626;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px"><i class="fas fa-calendar-times"></i></div>';
    $html .= '<div><div style="font-size:15px;font-weight:800;color:#991b1b">' . ($th ? '⚠️ รายงานสารใกล้หมดอายุ' : '⚠️ Expiry Alert Report') . '</div>';
    $html .= '<div style="font-size:11px;color:#b91c1c">' . ($th ? 'สารหมดอายุแล้วและใกล้หมดภายใน 90 วัน' : 'Expired and within-90-day chemicals') . '</div></div></div>';
    $html .= '<div style="display:flex;gap:10px;flex-wrap:wrap">';
    foreach ([
        [count($expired),  $th?'หมดอายุแล้ว':'EXPIRED',         '#dc2626','#fee2e2'],
        [count($critical), $th?'วิกฤต (≤14 วัน)':'CRITICAL ≤14d','#ea580c','#ffedd5'],
        [count($warning),  $th?'เฝ้าระวัง (≤90 วัน)':'WARNING ≤90d', '#d97706','#fffbeb'],
    ] as [$cnt, $lbl, $col, $bg]) {
        $html .= '<div style="flex:1;min-width:70px;text-align:center;background:' . $bg . ';border-radius:10px;padding:10px">';
        $html .= '<div style="font-size:22px;font-weight:900;color:' . $col . '">' . $cnt . '</div>';
        $html .= '<div style="font-size:10px;font-weight:700;color:' . $col . '">' . $lbl . '</div></div>';
    }
    $html .= '</div></div>';

    if (empty($rows)) {
        $html .= '<div style="text-align:center;padding:24px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px">';
        $html .= '<i class="fas fa-check-circle" style="font-size:32px;color:#22c55e"></i>';
        $html .= '<div style="font-size:14px;font-weight:700;color:#15803d;margin-top:8px">' . ($th ? '✅ ไม่มีสารที่ใกล้หมดอายุ!' : '✅ No chemicals near expiry!') . '</div>';
        $html .= '</div></div>'; return $html;
    }

    $groups = [
        [$expired,  $th?'🔴 หมดอายุแล้ว':'🔴 Already Expired',       '#fef2f2','#fecaca','#dc2626'],
        [$critical, $th?'🟠 วิกฤต — ≤14 วัน':'🟠 Critical — ≤14 Days',  '#fff7ed','#fed7aa','#ea580c'],
        [$warning,  $th?'🟡 เฝ้าระวัง — ≤90 วัน':'🟡 Warning — ≤90 Days','#fffbeb','#fde68a','#d97706'],
    ];

    foreach ($groups as [$grp, $title, $bg, $border, $col]) {
        if (empty($grp)) continue;
        $html .= '<div style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:12px;padding:14px;margin-bottom:12px">';
        $html .= '<div style="font-size:12px;font-weight:800;color:#1e293b;margin-bottom:10px">' . $title
               . ' <span style="background:' . $col . ';color:#fff;padding:1px 8px;border-radius:20px;font-size:10px">' . count($grp) . '</span></div>';
        $html .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:11px">';
        $html .= '<thead><tr style="background:rgba(0,0,0,.04)">';
        foreach ($th ? ['สาร','ตำแหน่ง','จำนวน','วันหมดอายุ','เหลือ'] : ['Chemical','Location','Qty','Expiry','Days'] as $h_) {
            $html .= '<th style="padding:5px 8px;text-align:left;font-weight:700">' . $h_ . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($grp as $r) {
            $d   = (int)$r['days_left'];
            $ds  = $d < 0 ? ($th ? abs($d).'วันที่แล้ว' : abs($d).'d ago') : ($th ? $d.' วัน' : $d.'d');
            $loc = implode(' › ', array_filter([$r['building']??'', $r['room']??'', $r['cabinet']??'', $r['slot']??'']));
            $html .= '<tr style="border-top:1px solid rgba(0,0,0,.06)">';
            $html .= '<td style="padding:5px 8px"><strong>' . h($r['chem_name']) . '</strong>';
            if ($r['cas_number']) $html .= '<br><span style="color:#9ca3af;font-size:10px">' . h($r['cas_number']) . '</span>';
            $html .= '</td>';
            $html .= '<td style="padding:5px 8px;color:#475569;font-size:10px">' . h($loc ?: '-') . '</td>';
            $html .= '<td style="padding:5px 8px;font-weight:700;color:#0369a1">' . number_format(floatval($r['current_quantity']),1) . ' ' . h($r['quantity_unit']??'') . '</td>';
            $html .= '<td style="padding:5px 8px;font-weight:600">' . h($r['expiry_date']??'-') . '</td>';
            $html .= '<td style="padding:5px 8px;font-weight:800;color:' . $col . '">' . $ds . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div></div>';
    }
    $html .= '<div style="text-align:right;margin-top:6px"><a href="/v1/pages/alerts.php" style="font-size:11px;color:#6366f1;font-weight:700"><i class="fas fa-external-link-alt"></i> ' . ($th ? 'ดูรายงานเต็ม' : 'Full report') . '</a></div>';
    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — STOCK SUMMARY
// ═══════════════════════════════════════════════════════════════════

function renderStockSummary(string $lang): string {
    $th   = $lang === 'th';
    $html = '<div class="chembot-response">';

    $totals = Database::fetch("
        SELECT COUNT(DISTINCT c.id) AS total_chems,
               COUNT(con.id)        AS total_containers,
               SUM(con.current_quantity) AS total_qty
        FROM chemicals c
        LEFT JOIN containers con ON c.id = con.chemical_id AND con.status = 'active'
        WHERE c.is_active = 1
    ");

    $top = Database::fetchAll("
        SELECT c.name, c.cas_number, c.molecular_formula,
               SUM(con.current_quantity) AS total_qty, con.quantity_unit,
               COUNT(con.id) AS num_containers
        FROM chemicals c
        JOIN containers con ON c.id = con.chemical_id AND con.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id, con.quantity_unit
        ORDER BY total_qty DESC
        LIMIT 10
    ");

    $lowStock = Database::fetchAll("
        SELECT c.name, c.cas_number, con.current_quantity, con.initial_quantity, con.quantity_unit,
               CASE WHEN con.initial_quantity > 0 THEN ROUND(con.current_quantity/con.initial_quantity*100,1) ELSE NULL END AS pct,
               b.name as building, r.name as room
        FROM containers con
        JOIN chemicals  c   ON con.chemical_id = c.id
        LEFT JOIN slots    sl  ON con.location_slot_id = sl.id
        LEFT JOIN shelves  sh  ON sl.shelf_id   = sh.id
        LEFT JOIN cabinets cab ON sh.cabinet_id = cab.id
        LEFT JOIN rooms    r   ON cab.room_id   = r.id
        LEFT JOIN buildings b  ON r.building_id = b.id
        WHERE con.status = 'active' AND c.is_active = 1
          AND con.initial_quantity > 0
          AND (con.current_quantity / con.initial_quantity) <= 0.1
        ORDER BY (con.current_quantity / con.initial_quantity) ASC
        LIMIT 15
    ");

    $catBreak = Database::fetchAll("
        SELECT cc.name as cat, COUNT(DISTINCT c.id) as cnt
        FROM chemicals c
        JOIN chemical_categories cc ON c.category_id = cc.id
        WHERE c.is_active = 1
        GROUP BY cc.id ORDER BY cnt DESC LIMIT 6
    ");

    // Header
    $html .= '<div style="background:linear-gradient(135deg,#f0fdf4,#f0f9ff);border:1px solid #bbf7d0;border-radius:14px;padding:16px;margin-bottom:14px">';
    $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">';
    $html .= '<div style="width:36px;height:36px;background:linear-gradient(135deg,#16a34a,#0284c7);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px"><i class="fas fa-boxes-stacked"></i></div>';
    $html .= '<div><div style="font-size:15px;font-weight:800;color:#166534">' . ($th ? '📦 สรุปสต็อกคลังสารเคมี' : '📦 Chemical Stock Summary') . '</div>';
    $html .= '<div style="font-size:11px;color:#15803d">' . ($th ? 'ข้อมูลปัจจุบัน ณ วันนี้' : 'Live inventory data') . '</div></div></div>';

    $html .= '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">';
    foreach ([
        ['fas fa-flask',         number_format((int)($totals['total_chems']??0)),          $th?'สารทั้งหมด':'Chemicals',  '#eff6ff','#1d4ed8'],
        ['fas fa-box',           number_format((int)($totals['total_containers']??0)),     'Containers',                  '#f0fdf4','#15803d'],
        ['fas fa-layer-group',   number_format((float)($totals['total_qty']??0), 0),       $th?'ปริมาณรวม':'Total Qty',   '#faf5ff','#7c3aed'],
    ] as [$ico, $val, $lbl, $bg, $col]) {
        $html .= '<div style="background:' . $bg . ';border-radius:10px;padding:12px;text-align:center">';
        $html .= '<i class="fas ' . $ico . '" style="color:' . $col . ';font-size:18px;margin-bottom:4px;display:block"></i>';
        $html .= '<div style="font-size:20px;font-weight:900;color:' . $col . '">' . $val . '</div>';
        $html .= '<div style="font-size:10px;font-weight:700;color:#6b7280">' . $lbl . '</div>';
        $html .= '</div>';
    }
    $html .= '</div></div>';

    // Low stock
    if (!empty($lowStock)) {
        $html .= '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:12px">';
        $html .= '<div style="font-size:12px;font-weight:800;color:#92400e;margin-bottom:10px"><i class="fas fa-exclamation-triangle"></i> ' . ($th ? '⚠️ สารปริมาณน้อย (≤10%)' : '⚠️ Low Stock (≤10% remaining)') . '</div>';
        $html .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:11px">';
        $html .= '<thead><tr style="background:rgba(0,0,0,.04)"><th style="padding:5px 8px;text-align:left">' . ($th?'สาร':'Chemical') . '</th><th style="padding:5px 8px;text-align:right">' . ($th?'เหลือ':'Remaining') . '</th><th style="padding:5px 8px;text-align:right">%</th><th style="padding:5px 8px;text-align:left">' . ($th?'ตำแหน่ง':'Location') . '</th></tr></thead><tbody>';
        foreach ($lowStock as $r) {
            $pct = $r['pct'] !== null ? $r['pct'] . '%' : '-';
            $loc = implode(' / ', array_filter([$r['building']??'', $r['room']??'']));
            $html .= '<tr style="border-top:1px solid rgba(0,0,0,.06)">';
            $html .= '<td style="padding:5px 8px"><strong>' . h($r['name']) . '</strong>' . ($r['cas_number'] ? '<br><span style="color:#9ca3af;font-size:10px">' . h($r['cas_number']) . '</span>' : '') . '</td>';
            $html .= '<td style="padding:5px 8px;text-align:right;color:#b45309;font-weight:700">' . number_format(floatval($r['current_quantity']),1) . ' ' . h($r['quantity_unit']??'') . '</td>';
            $html .= '<td style="padding:5px 8px;text-align:right;color:#dc2626;font-weight:800">' . $pct . '</td>';
            $html .= '<td style="padding:5px 8px;color:#6b7280">' . h($loc ?: '-') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div></div>';
    }

    // Top 10 bar chart
    if (!empty($top)) {
        $html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px">';
        $html .= '<div style="font-size:12px;font-weight:800;color:#0f172a;margin-bottom:10px"><i class="fas fa-chart-bar" style="color:#6366f1"></i> ' . ($th ? 'Top 10 — ปริมาณมากที่สุด' : 'Top 10 — Highest Quantity') . '</div>';
        $maxQty = max(array_column($top, 'total_qty') ?: [1]);
        $colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#0891b2','#16a34a','#dc2626','#d97706'];
        foreach ($top as $i => $r) {
            $pct = $maxQty > 0 ? max(4, round(floatval($r['total_qty']) / $maxQty * 100)) : 4;
            $col = $colors[$i % count($colors)];
            $html .= '<div style="margin-bottom:7px"><div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">';
            $html .= '<span style="font-weight:600;color:#1e293b">' . ($i+1) . '. ' . h($r['name']) . '</span>';
            $html .= '<span style="font-weight:700;color:' . $col . '">' . number_format(floatval($r['total_qty']),1) . ' ' . h($r['quantity_unit']??'') . '</span></div>';
            $html .= '<div style="height:6px;background:#e2e8f0;border-radius:3px"><div style="height:6px;background:' . $col . ';border-radius:3px;width:' . $pct . '%"></div></div></div>';
        }
        $html .= '</div>';
    }

    // Category pills
    if (!empty($catBreak)) {
        $colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6'];
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">';
        foreach ($catBreak as $i => $r) {
            $col = $colors[$i % count($colors)];
            $html .= '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:5px 10px;display:flex;gap:5px;align-items:center">';
            $html .= '<span style="width:8px;height:8px;border-radius:50%;background:' . $col . ';flex-shrink:0"></span>';
            $html .= '<span style="font-size:11px;font-weight:600;color:#374151">' . h($r['cat']) . '</span>';
            $html .= '<span style="font-size:11px;font-weight:800;color:' . $col . '">' . $r['cnt'] . '</span></div>';
        }
        $html .= '</div>';
    }

    $html .= '<div style="text-align:right"><a href="/v1/pages/stock.php" style="font-size:11px;color:#6366f1;font-weight:700"><i class="fas fa-external-link-alt"></i> ' . ($th ? 'ดูสต็อกทั้งหมด' : 'Full stock') . '</a></div>';
    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// RENDER — NOT FOUND
// ═══════════════════════════════════════════════════════════════════

function renderLocationNoStock(array $results, string $term, string $lang): string {
    $th = $lang === 'th';
    $html = '<div class="chembot-response">';
    $html .= '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:16px;margin-bottom:14px">';
    $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">';
    $html .= '<i class="fas fa-box-open" style="color:#f97316;font-size:20px"></i>';
    $html .= '<div>';
    $html .= '<div style="font-weight:700;color:#c2410c;font-size:14px">' . ($th ? 'พบสารเคมีในฐานข้อมูล แต่ยังไม่มีข้อมูลการจัดเก็บ' : 'Chemical found, but no storage data available') . '</div>';
    $html .= '<div style="font-size:12px;color:#ea580c;margin-top:2px">' . ($th ? 'สาร "' . h($term) . '" มีในฐานข้อมูล แต่ยังไม่ได้ลงทะเบียนตำแหน่งจัดเก็บ' : '"' . h($term) . '" exists in DB but has no registered storage location') . '</div>';
    $html .= '</div></div>';
    foreach ($results as $item) {
        $c = $item['chemical'];
        $html .= '<div style="background:#fff;border-radius:8px;padding:10px 12px;margin-top:8px;display:flex;align-items:center;gap:10px">';
        $html .= '<span style="font-size:18px">🧪</span>';
        $html .= '<div style="flex:1"><div style="font-weight:700;color:#1e293b">' . h($c['name']) . '</div>';
        if ($c['cas_number']) $html .= '<div style="font-size:11px;color:#64748b;font-family:monospace">CAS: ' . h($c['cas_number']) . '</div>';
        $html .= '</div>';
        $html .= '<button onclick="sendMsg(\'' . escAttrJS(($th ? 'ค้นหา ' : '') . $c['name']) . '\')" style="background:var(--accent);color:#fff;border:none;border-radius:7px;padding:6px 12px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit"><i class="fas fa-info-circle"></i> ' . ($th ? 'ข้อมูลเต็ม' : 'Full Info') . '</button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<div style="text-align:center">';
    $html .= '<button onclick="openStorageBrowser()" style="background:var(--ok);color:#fff;border:none;border-radius:10px;padding:9px 20px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;font-family:inherit">';
    $html .= '<i class="fas fa-warehouse"></i> ' . ($th ? 'ดูสถานที่จัดเก็บทั้งหมด' : 'Browse All Storage Locations') . '</button>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

function renderNotFound(string $query, string $lang, string $type): string {
    $th   = $lang === 'th';
    $q    = urlencode($query);
    $html = '<div class="chembot-response">';
    $html .= '<div class="no-result-card">';
    $html .= '<div class="no-result-icon"><i class="fas fa-search-minus"></i></div>';
    $html .= '<div class="no-result-title">' . ($th?'🔬 ไม่พบข้อมูลในฐานข้อมูล':'🔬 No Data Found in Database') . '</div>';
    $html .= '<div class="no-result-sub">' . ($th?'ไม่พบ':'Cannot find') . ' <strong>"' . h($query) . '"</strong><br>' . ($th?'ลองค้นหาจากแหล่งข้อมูลภายนอกด้านล่าง':'Try searching from external sources below') . '</div>';

    $html .= '<div class="ext-grid">';
    $links = [
        ['https://pubchem.ncbi.nlm.nih.gov/search/?query='.$q, '#0ea5e9', 'P', 'PubChem', $th?'ข้อมูลสารเคมีครบถ้วน':'Full chemical data'],
        ['https://www.google.com/search?q='.$q.'+SDS+PDF', '#4285f4', 'G', 'Google SDS', $th?'ค้นหา SDS':'Search SDS'],
        ['https://www.chemspider.com/Search.aspx?term='.$q, '#8b5cf6', 'CS', 'ChemSpider', $th?'ฐานข้อมูล RSC':'RSC database'],
        ['https://en.wikipedia.org/wiki/'.urlencode(str_replace(' ','_',$query)), '#6366f1', 'W', 'Wikipedia', $th?'ข้อมูลทั่วไป':'General info'],
        ['https://www.sigmaaldrich.com/TH/en/search?query='.$q, '#e11d48', 'Σ', 'Sigma-Aldrich', $th?'ซัพพลายเออร์':'Supplier'],
        ['https://molview.org/?q='.$q, '#0891b2', 'MV', 'MolView', $th?'โมเดล 3D':'3D model'],
    ];
    foreach ($links as [$url, $color, $abbr, $title, $desc]) {
        $html .= '<a href="' . h($url) . '" target="_blank" class="ext-link" style="background:#f8fafc;border-color:#e2e8f0">';
        $html .= '<div class="ext-link-icon" style="background:' . h($color) . '">' . h($abbr) . '</div>';
        $html .= '<div class="ext-link-info"><strong>' . h($title) . '</strong><small>' . h($desc) . '</small></div>';
        $html .= '<i class="fas fa-external-link-alt arr"></i>';
        $html .= '</a>';
    }
    $html .= '</div>';

    // DB stats
    $stats = Database::fetch("SELECT COUNT(*) as cnt FROM chemicals WHERE is_active = 1");
    $html .= '<div class="db-stats-bar"><i class="fas fa-database" style="color:var(--info)"></i> ' . ($th?'ฐานข้อมูลมีสารเคมีทั้งหมด <strong>':'Database has <strong>') . number_format((int)($stats['cnt'] ?? 0)) . '</strong>' . ($th?' รายการ':' chemicals') . '</div>';

    $html .= '</div></div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════════════
// HELPER: Build plain text summary
// ═══════════════════════════════════════════════════════════════════

function buildPlainText(array $c, string $lang): string {
    $th = $lang === 'th';
    $t  = ($th?'สารเคมี: ':'Chemical: ') . $c['name'] . "\n";
    if ($c['cas_number'])        $t .= 'CAS: ' . $c['cas_number'] . "\n";
    if ($c['molecular_formula']) $t .= ($th?'สูตร: ':'Formula: ') . $c['molecular_formula'] . "\n";
    return $t;
}

// ═══════════════════════════════════════════════════════════════════
// HELPER: HTML utilities
// ═══════════════════════════════════════════════════════════════════

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function escAttrJS(string $s): string {
    return str_replace(["'", '"', "\n", "\r"], ["&#39;", '&quot;', '', ''], h($s));
}

function pill(string $icon, string $text, string $type): string {
    return '<span class="pill ' . $type . '"><i class="fas ' . $icon . '"></i> ' . $text . '</span>';
}

function sectionToggle(string $icon, string $title, bool $open): string {
    $cls = $open ? '' : 'collapsed';
    return '<div class="rs-head section-toggle ' . $cls . '">
        <div class="rs-head-icon" style="background:#f0f9ff;color:#0ea5e9"><i class="fas ' . $icon . '"></i></div>
        <h3>' . $title . '</h3>
        <i class="fas fa-chevron-down toggle-icon" style="margin-left:auto;color:#9ca3af;font-size:11px"></i>
    </div>';
}

// ═══════════════════════════════════════════════════════════════════
// AUTHENTICATED-USER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

function processChatMessage(array $data, array $user): array {
    $message   = $data['message'] ?? '';
    $sessionId = $data['session_id'] ?? null;

    if (!$sessionId) {
        $sessionId = 'chat_' . time() . '_' . bin2hex(random_bytes(4));
        Database::insert('ai_chat_sessions', [
            'user_id'    => $user['id'],
            'session_id' => $sessionId,
            'title'      => mb_substr($message, 0, 50, 'UTF-8'),
        ]);
    }

    Database::insert('ai_chat_messages', [
        'session_id' => $sessionId,
        'role'       => 'user',
        'content'    => $message,
    ]);

    // Use local search engine
    $localResult = handleLocalChat(['message' => $message, 'lang' => ($user['language'] ?? 'th') === 'en' ? 'en' : 'th']);

    Database::insert('ai_chat_messages', [
        'session_id' => $sessionId,
        'role'       => 'assistant',
        'content'    => $localResult['response'],
    ]);

    return ['session_id' => $sessionId, 'response' => $localResult['response'], 'html' => $localResult['html'] ?? ''];
}

function smartSearch(string $query, array $user): array {
    $results = searchChemicalsByTerm($query, 20);
    return ['results' => $results];
}

function getChatSessions(int $userId): array {
    try {
        return Database::fetchAll("SELECT * FROM ai_chat_sessions WHERE user_id = :uid ORDER BY updated_at DESC LIMIT 20", [':uid' => $userId]);
    } catch (Exception $e) { return []; }
}

function getChatMessages(string $sessionId, int $userId): array {
    try {
        return Database::fetchAll("
            SELECT m.* FROM ai_chat_messages m
            JOIN ai_chat_sessions s ON m.session_id = s.session_id
            WHERE m.session_id = :sid AND s.user_id = :uid
            ORDER BY m.created_at ASC
        ", [':sid' => $sessionId, ':uid' => $userId]);
    } catch (Exception $e) { return []; }
}

function getSmartSuggestions(array $user): array {
    try {
        $recent = Database::fetchAll("
            SELECT DISTINCT c.name, c.cas_number FROM chemicals c
            JOIN containers con ON c.id = con.chemical_id
            WHERE con.owner_id = :uid AND con.status = 'active'
            ORDER BY con.updated_at DESC LIMIT 5
        ", [':uid' => $user['id']]);
        return $recent;
    } catch (Exception $e) { return []; }
}

function deleteChatSession(string $sessionId, int $userId): void {
    try {
        Database::delete('ai_chat_sessions', 'session_id = :sid AND user_id = :uid', [':sid' => $sessionId, ':uid' => $userId]);
    } catch (Exception $e) {}
}
