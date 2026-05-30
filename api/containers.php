<?php
/**
 * Containers API v3 — Unified Data Edition
 * Merges data from both `containers` table AND `chemical_stock` table (CSV-imported)
 * 
 * GET  ?action=list          List all bottles (paginated, filtered, both sources)
 * GET  ?action=stats         Unified dashboard stats
 * GET  ?action=detail&id=N   Single record detail (positive ID = containers, negative ID = chemical_stock)
 * GET  ?action=export        CSV export (all data)
 * GET  ?qr=CODE              Get container by QR code
 * POST                       Create container
 * PUT  ?id=N                 Update container
 * DELETE ?id=N               Soft-delete (dispose)
 *
 * Data Sources:
 *   containers     → full v2 records (3D/AR/history/buildings/rooms)
 *   chemical_stock → CSV-imported inventory (5,400+ bottles from คลังสาร)
 *   IDs: containers use positive IDs, chemical_stock uses NEGATIVE IDs (-stock.id)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/qr_generator.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

// ── Unit mapping: Thai → ISO ──
function mapUnit(?string $thaiUnit): string {
    $map = [
        'กรัม' => 'g', 'มิลลิกรัม' => 'mg', 'กิโลกรัม' => 'kg',
        'มิลลิลิตร' => 'mL', 'ลิตร' => 'L', 'ไมโครลิตร' => 'µL',
        'ไมโครกรัม' => 'µg', 'ลูกบาศก์เมตร' => 'm³',
        'Packs' => 'units', 'Units' => 'units', 'Vials' => 'units',
    ];
    return $map[$thaiUnit ?? ''] ?? ($thaiUnit ?: 'units');
}

// ── Guess container type from unit ──
function guessContainerType(?string $unit): string {
    $liq = ['mL','L','ลิตร','มิลลิลิตร','ไมโครลิตร'];
    $gas = ['m³','ลูกบาศก์เมตร'];
    if (in_array($unit, $gas)) return 'cylinder';
    if (in_array($unit, $liq)) return 'bottle';
    if (in_array($unit, ['Vials'])) return 'vial';
    return 'bottle';
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';
            if (isset($_GET['qr'])) {
                echo json_encode(['success' => true, 'data' => getContainerByQR($_GET['qr'], $user)]);
            } elseif ($action === 'stats') {
                echo json_encode(['success' => true, 'data' => getStats($user)]);
            } elseif ($action === 'export') {
                exportContainers($_GET, $user);
            } elseif ($action === 'detail' || isset($_GET['id'])) {
                $id = (int)($_GET['id'] ?? 0);
                if ($id < 0) {
                    echo json_encode(['success' => true, 'data' => getStockDetail(abs($id), $user)]);
                } else {
                    echo json_encode(['success' => true, 'data' => getContainerDetails($id, $user)]);
                }
            } else {
                echo json_encode(['success' => true, 'data' => listContainers($_GET, $user)]);
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(['success' => true, 'data' => createContainer($data, $user)]);
            break;
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($_GET['id'] ?? 0);
            updateContainer($id, $data, $user);
            echo json_encode(['success' => true, 'message' => 'Container updated']);
            break;
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            deleteContainer($id, $user);
            echo json_encode(['success' => true, 'message' => 'Container deleted']);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ══════════════════════════════════════════════
// STATS (Unified: containers + chemical_stock)
// ══════════════════════════════════════════════
function getStats(array $user): array {
    $uid = (int)$user['id'];

    // ── Containers table stats ──
    $cn = Database::fetch("SELECT 
        COUNT(*) as total,
        SUM(status='active') as active,
        SUM(status='empty') as empty_cnt,
        SUM(status='expired') as expired,
        SUM(status='quarantined') as quarantined,
        SUM(status='disposed') as disposed,
        SUM(remaining_percentage <= 20 AND remaining_percentage > 0 AND status='active') as low,
        SUM(expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active') as expiring_soon,
        COUNT(DISTINCT chemical_id) as chemicals,
        COUNT(DISTINCT owner_id) as owners
    FROM containers WHERE is_active = 1");

    // ── Chemical_stock table stats ──
    $cs = Database::fetch("SELECT 
        COUNT(*) as total,
        SUM(status='active') as active,
        SUM(status='empty') as empty_cnt,
        SUM(status='expired') as expired,
        SUM(status='disposed') as disposed,
        SUM(status='low') as low,
        COUNT(DISTINCT chemical_id) as chemicals,
        COUNT(DISTINCT owner_user_id) as owners,
        COUNT(DISTINCT storage_location) as locations
    FROM chemical_stock");

    // ── Merge stats ──
    $total    = (int)$cn['total'] + (int)$cs['total'];
    $active   = (int)$cn['active'] + (int)$cs['active'];
    $empty    = (int)$cn['empty_cnt'] + (int)$cs['empty_cnt'];
    $expired  = (int)$cn['expired'] + (int)($cs['expired'] ?? 0);
    $low      = (int)$cn['low'] + (int)$cs['low'];
    $disposed = (int)$cn['disposed'] + (int)($cs['disposed'] ?? 0);
    $quarantined = (int)($cn['quarantined'] ?? 0);
    $expiring = (int)($cn['expiring_soon'] ?? 0);

    // ── Unique chemicals count (union) ──
    $chemCount = Database::fetch("SELECT COUNT(DISTINCT chem) as cnt FROM (
        SELECT chemical_id as chem FROM containers WHERE is_active = 1 AND chemical_id IS NOT NULL
        UNION
        SELECT chemical_id as chem FROM chemical_stock WHERE chemical_id IS NOT NULL
    ) t");

    // ── My stock (both tables) ──
    $myCn = Database::fetch("SELECT COUNT(*) as total, SUM(status='active') as active, SUM(status='empty') as empty_cnt,
        SUM(remaining_percentage <= 20 AND remaining_percentage > 0 AND status='active') as low,
        SUM(expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active') as expiring
    FROM containers WHERE is_active = 1 AND owner_id = :uid", [':uid' => $uid]);
    $myCs = Database::fetch("SELECT COUNT(*) as total, SUM(status='active') as active, SUM(status='empty') as empty_cnt,
        SUM(status='low') as low
    FROM chemical_stock WHERE owner_user_id = :uid", [':uid' => $uid]);

    // ── Unified type distribution ──
    $types = Database::fetchAll("SELECT container_type, SUM(cnt) as cnt FROM (
        SELECT container_type, COUNT(*) as cnt FROM containers WHERE is_active = 1 GROUP BY container_type
        UNION ALL
        SELECT 'bottle' as container_type, COUNT(*) as cnt FROM chemical_stock
    ) t GROUP BY container_type ORDER BY cnt DESC");

    // ── Unified status distribution ──
    $statuses = Database::fetchAll("SELECT status, SUM(cnt) as cnt FROM (
        SELECT status, COUNT(*) as cnt FROM containers WHERE is_active = 1 GROUP BY status
        UNION ALL
        SELECT status, COUNT(*) as cnt FROM chemical_stock GROUP BY status
    ) t GROUP BY status ORDER BY cnt DESC");

    // ── Top chemicals (both tables) ──
    $topChems = Database::fetchAll("SELECT chemical_name, SUM(cnt) as cnt FROM (
        SELECT ch.name as chemical_name, COUNT(*) as cnt FROM containers cn JOIN chemicals ch ON cn.chemical_id = ch.id WHERE cn.is_active = 1 GROUP BY ch.name
        UNION ALL
        SELECT chemical_name, COUNT(*) as cnt FROM chemical_stock GROUP BY chemical_name
    ) t GROUP BY chemical_name ORDER BY cnt DESC LIMIT 8");

    // ── Top owners (both tables) ──
    $topOwners = Database::fetchAll("SELECT owner_name, SUM(cnt) as cnt FROM (
        SELECT CONCAT(u.first_name,' ',u.last_name) as owner_name, COUNT(*) as cnt FROM containers cn JOIN users u ON cn.owner_id = u.id WHERE cn.is_active = 1 GROUP BY u.id
        UNION ALL
        SELECT COALESCE(CONCAT(u.first_name,' ',u.last_name), s.owner_name) as owner_name, COUNT(*) as cnt FROM chemical_stock s LEFT JOIN users u ON s.owner_user_id = u.id GROUP BY owner_name
    ) t GROUP BY owner_name ORDER BY cnt DESC LIMIT 8");

    $models3d = Database::fetch("SELECT COUNT(*) as cnt FROM packaging_3d_models WHERE is_active = 1");
    $myName = $user['full_name_th'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    return [
        'total'          => $total,
        'active'         => $active,
        'empty'          => $empty,
        'expired'        => $expired,
        'quarantined'    => $quarantined,
        'disposed'       => $disposed,
        'low'            => $low,
        'expiring_soon'  => $expiring,
        'chemicals'      => (int)($chemCount['cnt'] ?? 0),
        'owners'         => (int)$cn['owners'] + (int)$cs['owners'],
        'locations'      => (int)($cs['locations'] ?? 1),
        'models_3d'      => (int)($models3d['cnt'] ?? 0),
        'my_total'       => (int)$myCn['total'] + (int)$myCs['total'],
        'my_active'      => (int)$myCn['active'] + (int)$myCs['active'],
        'my_empty'       => (int)$myCn['empty_cnt'] + (int)$myCs['empty_cnt'],
        'my_low'         => (int)($myCn['low'] ?? 0) + (int)($myCs['low'] ?? 0),
        'my_expiring'    => (int)($myCn['expiring'] ?? 0),
        'my_name'        => $myName,
        'types'          => $types,
        'statuses'       => $statuses,
        'top_chemicals'  => $topChems,
        'top_owners'     => $topOwners,
        'source_breakdown' => [
            'containers' => (int)$cn['total'],
            'chemical_stock' => (int)$cs['total'],
        ],
    ];
}

// ══════════════════════════════════════════════
// LIST (Unified: containers + chemical_stock)
// ══════════════════════════════════════════════
function listContainers(array $params, array $user): array {
    $page    = max(1, (int)($params['page'] ?? 1));
    $limit   = min(100, max(1, (int)($params['limit'] ?? 25)));
    $offset  = ($page - 1) * $limit;
    $search  = trim($params['search'] ?? '');
    $status  = $params['status'] ?? '';
    $type    = $params['type'] ?? '';
    $owner   = $params['owner'] ?? '';
    $tab     = $params['tab'] ?? 'all';
    $sort    = $params['sort'] ?? 'newest';
    $source  = $params['source'] ?? '';  // 'containers', 'stock', or '' (both)
    $role    = $user['role_name'] ?? '';
    $uid     = (int)$user['id'];
    $labId   = (int)($user['lab_id'] ?? 0);

    // ── Build WHERE for containers table ──
    $cnWhere = ["cn.is_active = 1"];
    $cnBind  = [];
    if ($tab === 'my') {
        $cnWhere[] = "cn.owner_id = :cn_my_uid";
        $cnBind[':cn_my_uid'] = $uid;
    } elseif ($role === 'lab_manager') {
        $cnWhere[] = "(cn.owner_id = :cn_uid3 OR cn.lab_id = :cn_lab2)";
        $cnBind[':cn_uid3'] = $uid;
        $cnBind[':cn_lab2'] = $labId;
    }
    // role='user' + tab='all': show all active containers (read-only; write ops gated separately)
    if ($search) {
        $cnWhere[] = "(ch.name LIKE :cn_s1 OR cn.bottle_code LIKE :cn_s2 OR cn.qr_code LIKE :cn_s3 OR ch.cas_number LIKE :cn_s4 OR m.name LIKE :cn_s5)";
        $sv = "%{$search}%";
        $cnBind[':cn_s1'] = $sv; $cnBind[':cn_s2'] = $sv; $cnBind[':cn_s3'] = $sv; $cnBind[':cn_s4'] = $sv; $cnBind[':cn_s5'] = $sv;
    }
    if ($status) { $cnWhere[] = "cn.status = :cn_st"; $cnBind[':cn_st'] = $status; }
    elseif (!empty($params['exclude_status'])) { $cnWhere[] = "cn.status != :cn_exst"; $cnBind[':cn_exst'] = $params['exclude_status']; }
    if ($type)   { $cnWhere[] = "cn.container_type = :cn_ct"; $cnBind[':cn_ct'] = $type; }
    if ($owner)  { $cnWhere[] = "cn.owner_id = :cn_oid"; $cnBind[':cn_oid'] = (int)$owner; }
    if (!empty($params['building_id'])) { $cnWhere[] = "cn.building_id = :cn_bid"; $cnBind[':cn_bid'] = (int)$params['building_id']; }
    if (!empty($params['expiring_soon'])) { $cnWhere[] = "cn.expiry_date IS NOT NULL AND cn.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND cn.status = 'active'"; }
    if (!empty($params['low_stock']))     { $cnWhere[] = "cn.remaining_percentage <= 20 AND cn.remaining_percentage > 0 AND cn.status = 'active'"; }
    $cnWhereStr = implode(' AND ', $cnWhere);

    // ── Build WHERE for chemical_stock table ──
    $csWhere = ['1=1'];
    $csBind  = [];
    if ($tab === 'my') {
        $csWhere[] = "s.owner_user_id = :cs_my_uid";
        $csBind[':cs_my_uid'] = $uid;
    } elseif ($role === 'lab_manager') {
        $csWhere[] = "(s.owner_user_id = :cs_uid3 OR s.owner_user_id IN (SELECT id FROM users WHERE manager_id = :cs_mgr))";
        $csBind[':cs_uid3'] = $uid;
        $csBind[':cs_mgr'] = $uid;
    }
    // role='user' + tab='all': show all stock (read-only; write ops gated separately)
    if ($search) {
        $csWhere[] = "(s.chemical_name LIKE :cs_s1 OR s.bottle_code LIKE :cs_s2 OR s.cas_no LIKE :cs_s3 OR s.owner_name LIKE :cs_s4 OR s.grade LIKE :cs_s5)";
        $sv = "%{$search}%";
        $csBind[':cs_s1'] = $sv; $csBind[':cs_s2'] = $sv; $csBind[':cs_s3'] = $sv; $csBind[':cs_s4'] = $sv; $csBind[':cs_s5'] = $sv;
    }
    if ($status) {
        // Map status: chemical_stock has 'low' but containers doesn't
        $csWhere[] = "s.status = :cs_st";
        $csBind[':cs_st'] = $status;
    } elseif (!empty($params['exclude_status'])) {
        $csWhere[] = "s.status != :cs_exst";
        $csBind[':cs_exst'] = $params['exclude_status'];
    }
    if (!empty($params['low_stock'])) { $csWhere[] = "s.status = 'low'"; }
    $csWhereStr = implode(' AND ', $csWhere);

    // ── Sort mapping ──
    $orderMap = match($sort) {
        'name_asc'    => 'chemical_name ASC',
        'name_desc'   => 'chemical_name DESC',
        'pct_asc'     => 'remaining_percentage ASC',
        'pct_desc'    => 'remaining_percentage DESC',
        'oldest'      => 'created_at ASC',
        'bottle_code' => 'bottle_code ASC',
        default       => 'created_at DESC',
    };

    // ── Container SELECT (positive IDs) ──
    $cnSelect = "SELECT cn.id as id, 'container' as source,
            cn.qr_code, cn.bottle_code, cn.container_type, cn.container_material,
            cn.initial_quantity, cn.current_quantity, cn.quantity_unit, cn.remaining_percentage,
            cn.status, cn.quality_status, cn.grade, cn.cost, cn.expiry_date, cn.received_date,
            cn.building_id, cn.room_id, cn.container_3d_model,
            cn.invoice_number, cn.notes, cn.created_at,
            ch.name as chemical_name, ch.cas_number, ch.hazard_pictograms, ch.molecular_formula,
            ch.signal_word,
            CONCAT(u.first_name,' ',u.last_name) as owner_name, cn.owner_id as owner_uid,
            u.avatar_url as owner_avatar_url,
            l.name as lab_name, m.name as manufacturer_name,
            b.name as building_name, COALESCE(b.shortname, b.name) as building_short,
            rm.name as room_name, rm.code as room_code,
            NULL as storage_location
        FROM containers cn
        LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
        LEFT JOIN users u ON cn.owner_id = u.id
        LEFT JOIN labs l ON cn.lab_id = l.id
        LEFT JOIN manufacturers m ON cn.manufacturer_id = m.id
        LEFT JOIN buildings b ON cn.building_id = b.id
        LEFT JOIN rooms rm ON cn.room_id = rm.id
        WHERE {$cnWhereStr}";

    // ── Chemical_stock SELECT (negative IDs) ──
    $csSelect = "SELECT -(s.id) as id, 'stock' as source,
            NULL as qr_code, s.bottle_code, 'bottle' as container_type, 'glass' as container_material,
            s.package_size as initial_quantity, s.remaining_qty as current_quantity, s.unit as quantity_unit,
            s.remaining_pct as remaining_percentage,
            s.status, 'good' as quality_status, s.grade, NULL as cost, NULL as expiry_date,
            s.added_at as received_date,
            NULL as building_id, NULL as room_id, NULL as container_3d_model,
            NULL as invoice_number, NULL as notes, s.created_at,
            s.chemical_name, s.cas_no as cas_number,
            COALESCE(ch2.hazard_pictograms, '[]') as hazard_pictograms,
            ch2.molecular_formula,
            ch2.signal_word,
            COALESCE(CONCAT(u2.first_name,' ',u2.last_name), s.owner_name) as owner_name,
            s.owner_user_id as owner_uid,
            u2.avatar_url as owner_avatar_url,
            NULL as lab_name, NULL as manufacturer_name,
            NULL as building_name, NULL as building_short,
            NULL as room_name, NULL as room_code,
            s.storage_location
        FROM chemical_stock s
        LEFT JOIN chemicals ch2 ON s.chemical_id = ch2.id
        LEFT JOIN users u2 ON s.owner_user_id = u2.id
        WHERE {$csWhereStr}";

    // ── Build UNION or single source ──
    $allBind = array_merge($cnBind, $csBind);
    if ($source === 'containers') {
        $countSql = "SELECT COUNT(*) as cnt FROM ({$cnSelect}) unified";
        $unionSql = "SELECT * FROM ({$cnSelect}) unified ORDER BY {$orderMap} LIMIT {$limit} OFFSET {$offset}";
        $allBind = $cnBind;
    } elseif ($source === 'stock') {
        $countSql = "SELECT COUNT(*) as cnt FROM ({$csSelect}) unified";
        $unionSql = "SELECT * FROM ({$csSelect}) unified ORDER BY {$orderMap} LIMIT {$limit} OFFSET {$offset}";
        $allBind = $csBind;
    } else {
        $countSql = "SELECT COUNT(*) as cnt FROM (({$cnSelect}) UNION ALL ({$csSelect})) unified";
        $unionSql = "SELECT * FROM (({$cnSelect}) UNION ALL ({$csSelect})) unified ORDER BY {$orderMap} LIMIT {$limit} OFFSET {$offset}";
    }

    $total = (int)Database::fetch($countSql, $allBind)['cnt'];
    $data = Database::fetchAll($unionSql, $allBind);

    // ── Pending transfer lookup (batch, one query per source type) ──
    $pendingMap = [];
    $cnIds = []; $csIds = [];
    foreach ($data as $row) {
        if ($row['source'] === 'container') $cnIds[] = abs((int)$row['id']);
        else $csIds[] = abs((int)$row['id']);
    }
    try {
        if ($cnIds) {
            $in = implode(',', array_map('intval', $cnIds));
            foreach (Database::fetchAll("SELECT id, source_id, initiated_by, to_user_id FROM chemical_transactions WHERE txn_type='transfer' AND status='pending' AND source_type='container' AND source_id IN ({$in})") as $pt)
                $pendingMap['container_'.$pt['source_id']] = $pt;
        }
        if ($csIds) {
            $in = implode(',', array_map('intval', $csIds));
            foreach (Database::fetchAll("SELECT id, source_id, initiated_by, to_user_id FROM chemical_transactions WHERE txn_type='transfer' AND status='pending' AND source_type='stock' AND source_id IN ({$in})") as $pt)
                $pendingMap['stock_'.$pt['source_id']] = $pt;
        }
    } catch (\Throwable $e) { /* non-fatal */ }

    // ── Pending borrow-by-me lookup ──
    $borrowedByMeMap = [];
    $uid = (int)$user['id'];
    try {
        if ($cnIds) {
            $in = implode(',', array_map('intval', $cnIds));
            foreach (Database::fetchAll("SELECT source_id FROM chemical_transactions WHERE txn_type='borrow' AND status='pending' AND initiated_by={$uid} AND source_type='container' AND source_id IN ({$in})") as $br)
                $borrowedByMeMap['container_'.$br['source_id']] = true;
        }
        if ($csIds) {
            $in = implode(',', array_map('intval', $csIds));
            foreach (Database::fetchAll("SELECT source_id FROM chemical_transactions WHERE txn_type='borrow' AND status='pending' AND initiated_by={$uid} AND source_type='stock' AND source_id IN ({$in})") as $br)
                $borrowedByMeMap['stock_'.$br['source_id']] = true;
        }
    } catch (\Throwable $e) { /* non-fatal */ }

    foreach ($data as &$row) {
        $row['hazard_pictograms'] = json_decode($row['hazard_pictograms'] ?? '[]', true);
        $row['is_mine'] = ((int)($row['owner_uid'] ?? 0) === (int)$user['id']);

        // Stamp pending transfer info
        $pt = $pendingMap[$row['source'].'_'.abs((int)$row['id'])] ?? null;
        $row['pending_transfer_id'] = $pt ? (int)$pt['id']          : null;
        $row['pending_transfer_by'] = $pt ? (int)$pt['initiated_by'] : null;
        $row['pending_transfer_to'] = $pt ? (int)$pt['to_user_id']   : null;

        // Stamp pending borrow by current user
        $row['pending_borrow_by_me'] = isset($borrowedByMeMap[$row['source'].'_'.abs((int)$row['id'])]);

        if ($row['source'] === 'container') {
            $row['has_3d'] = !empty($row['container_3d_model']) || has3DModel($row['container_type'], $row['container_material']);
            $row['location_text'] = buildLocationText($row);
        } else {
            $row['has_3d'] = has3DModel($row['container_type'] ?? 'bottle', $row['container_material'] ?? 'glass');
            $row['location_text'] = $row['storage_location'] ?: '-';
            // Map Thai units to ISO for consistent display
            $row['quantity_unit'] = mapUnit($row['quantity_unit']);
        }

        // Add lightweight 3D model URL for grid preview
        if ($row['has_3d']) {
            $arInfo = getARDataLight($row);
            $row['model_url']  = $arInfo['model_url'];
            $row['model_type'] = $arInfo['model_type'];
        }
    }

    return [
        'data'       => $data,
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / max($limit, 1)),
        ],
    ];
}

function buildLocationText(array $r): string {
    $p = [];
    if (!empty($r['building_short'])) $p[] = $r['building_short'];
    elseif (!empty($r['building_name'])) $p[] = $r['building_name'];
    if (!empty($r['room_code'])) $p[] = $r['room_code'];
    elseif (!empty($r['room_name'])) $p[] = $r['room_name'];
    return implode(' › ', $p) ?: '-';
}

function has3DModel(string $type, ?string $material): bool {
    $bind = [':t' => $type];
    $w = "is_active = 1 AND container_type = :t";
    if ($material) {
        $w .= " AND (container_material = :m OR container_material IS NULL OR container_material = '')";
        $bind[':m'] = $material;
    }
    return (bool)Database::fetch("SELECT id FROM packaging_3d_models WHERE {$w} LIMIT 1", $bind);
}

// ══════════════════════════════════════════════
// EXPORT CSV (Unified)
// ══════════════════════════════════════════════
function exportContainers(array $params, array $user): void {
    $search = trim($params['search'] ?? '');
    $sv = "%{$search}%";
    
    // Containers
    $cnWhere = ["cn.is_active = 1"];
    $cnBind = [];
    if ($search) {
        $cnWhere[] = "(ch.name LIKE :cn_s1 OR cn.bottle_code LIKE :cn_s2 OR ch.cas_number LIKE :cn_s3)";
        $cnBind[':cn_s1'] = $sv; $cnBind[':cn_s2'] = $sv; $cnBind[':cn_s3'] = $sv;
    }
    $cnW = implode(' AND ', $cnWhere);
    $cnRows = Database::fetchAll(
        "SELECT cn.bottle_code, cn.qr_code, ch.name as chemical_name, ch.cas_number,
                ch.molecular_formula, cn.container_type, cn.container_material, cn.grade,
                cn.initial_quantity, cn.current_quantity, cn.quantity_unit, cn.remaining_percentage,
                cn.status, cn.received_date, cn.expiry_date, cn.batch_number, cn.lot_number,
                cn.cost, cn.invoice_number,
                CONCAT(u.first_name,' ',u.last_name) as owner_name,
                CONCAT(COALESCE(b.shortname, b.name, ''), ' › ', COALESCE(rm.name, '')) as location_text,
                ch.signal_word, ch.hazard_pictograms, m.name as manufacturer_name,
                'container' as source
         FROM containers cn
         LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
         LEFT JOIN users u ON cn.owner_id = u.id
         LEFT JOIN buildings b ON cn.building_id = b.id
         LEFT JOIN rooms rm ON cn.room_id = rm.id
         LEFT JOIN manufacturers m ON cn.manufacturer_id = m.id
         WHERE {$cnW} ORDER BY cn.id DESC",
        $cnBind
    );

    // Chemical_stock
    $csWhere = ['1=1'];
    $csBind = [];
    if ($search) {
        $csWhere[] = "(s.chemical_name LIKE :cs_s1 OR s.bottle_code LIKE :cs_s2 OR s.cas_no LIKE :cs_s3)";
        $csBind[':cs_s1'] = $sv; $csBind[':cs_s2'] = $sv; $csBind[':cs_s3'] = $sv;
    }
    $csW = implode(' AND ', $csWhere);
    $csRows = Database::fetchAll(
        "SELECT s.bottle_code, NULL as qr_code, s.chemical_name, s.cas_no as cas_number,
                ch2.molecular_formula, 'bottle' as container_type, 'glass' as container_material,
                s.grade, s.package_size as initial_quantity, s.remaining_qty as current_quantity,
                s.unit as quantity_unit, s.remaining_pct as remaining_percentage,
                s.status, s.added_at as received_date, NULL as expiry_date,
                NULL as batch_number, NULL as lot_number, NULL as cost, NULL as invoice_number,
                COALESCE(CONCAT(u2.first_name,' ',u2.last_name), s.owner_name) as owner_name,
                s.storage_location as location_text,
                ch2.signal_word, ch2.hazard_pictograms, NULL as manufacturer_name,
                'stock' as source
         FROM chemical_stock s
         LEFT JOIN chemicals ch2 ON s.chemical_id = ch2.id
         LEFT JOIN users u2 ON s.owner_user_id = u2.id
         WHERE {$csW} ORDER BY s.id DESC",
        $csBind
    );

    $allRows = array_merge($cnRows, $csRows);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="chemical_inventory_' . date('Y-m-d_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    fputcsv($out, [
        'Source', 'Bottle Code', 'QR Code', 'Chemical Name', 'CAS Number', 'Formula',
        'Container Type', 'Material', 'Grade', 'Initial Qty', 'Current Qty', 'Unit',
        'Remaining %', 'Status', 'Received Date', 'Expiry Date', 'Batch No', 'Lot No',
        'Cost', 'Invoice', 'Owner', 'Location', 'Signal Word', 'Hazard Pictograms', 'Manufacturer'
    ]);
    
    foreach ($allRows as $r) {
        fputcsv($out, [
            $r['source'], $r['bottle_code'], $r['qr_code'], $r['chemical_name'], $r['cas_number'],
            $r['molecular_formula'], $r['container_type'], $r['container_material'], $r['grade'],
            $r['initial_quantity'], $r['current_quantity'], $r['quantity_unit'],
            round((float)($r['remaining_percentage'] ?? 0), 1), $r['status'],
            $r['received_date'], $r['expiry_date'], $r['batch_number'] ?? '', $r['lot_number'] ?? '',
            $r['cost'] ?? '', $r['invoice_number'] ?? '', $r['owner_name'], $r['location_text'] ?? '',
            $r['signal_word'] ?? '', $r['hazard_pictograms'] ?? '', $r['manufacturer_name'] ?? ''
        ]);
    }
    
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════
// STOCK DETAIL (for chemical_stock records with negative IDs)
// ══════════════════════════════════════════════
function getStockDetail(int $stockId, array $user): array {
    $s = Database::fetch(
        "SELECT s.*, ch.name as linked_name, ch.cas_number as linked_cas,
                ch.molecular_formula, ch.molecular_weight, ch.hazard_pictograms,
                ch.signal_word, ch.ghs_classifications, ch.sds_url, ch.physical_state,
                CONCAT(u.first_name,' ',u.last_name) as resolved_owner_name,
                u.avatar_url as owner_avatar_url
         FROM chemical_stock s
         LEFT JOIN chemicals ch ON s.chemical_id = ch.id
         LEFT JOIN users u ON s.owner_user_id = u.id
         WHERE s.id = :id", [':id' => $stockId]
    );
    if (!$s) throw new Exception('Stock record not found');

    // Map to the same output structure as getContainerDetails()
    $pct = (float)($s['remaining_pct'] ?? 100);
    $containerType = guessContainerType($s['unit']);
    
    $c = [
        'id'                   => -$stockId,  // negative ID
        'source'               => 'stock',
        'qr_code'              => null,
        'bottle_code'          => $s['bottle_code'],
        'container_type'       => $containerType,
        'container_material'   => 'glass',
        'initial_quantity'     => (float)$s['package_size'],
        'current_quantity'     => (float)$s['remaining_qty'],
        'quantity_unit'        => mapUnit($s['unit']),
        'quantity_unit_original' => $s['unit'],
        'remaining_percentage' => $pct,
        'status'               => $s['status'],
        'quality_status'       => 'good',
        'grade'                => $s['grade'],
        'cost'                 => null,
        'expiry_date'          => null,
        'received_date'        => $s['added_at'],
        'chemical_name'        => $s['chemical_name'],
        'cas_number'           => $s['cas_no'],
        'molecular_formula'    => $s['molecular_formula'],
        'molecular_weight'     => $s['molecular_weight'],
        'hazard_pictograms'    => json_decode($s['hazard_pictograms'] ?? '[]', true),
        'signal_word'          => $s['signal_word'],
        'ghs_classifications'  => json_decode($s['ghs_classifications'] ?? '[]', true),
        'sds_url'              => $s['sds_url'],
        'physical_state'       => $s['physical_state'],
        'owner_name'           => $s['resolved_owner_name'] ?: $s['owner_name'],
        'owner_uid'            => $s['owner_user_id'],
        'owner_avatar_url'     => $s['owner_avatar_url'] ?? null,
        'lab_name'             => null,
        'manufacturer_name'    => null,
        'building_name'        => null,
        'building_short'       => null,
        'room_name'            => null,
        'room_code'            => null,
        'location_text'        => $s['storage_location'] ?: '-',
        'storage_location'     => $s['storage_location'],
        'invoice_number'       => null,
        'batch_number'         => null,
        'lot_number'           => null,
        'notes'                => null,
        'created_at'           => $s['created_at'],
        'is_mine'              => ((int)($s['owner_user_id'] ?? 0) === (int)$user['id']),
        'history'              => [],  // chemical_stock has no history table
        'ar_data'              => getARData([
            'container_type'    => $containerType,
            'container_material'=> 'glass',
            'remaining_percentage' => $pct,
            'hazard_pictograms' => $s['hazard_pictograms'],
            'cas_number'        => $s['cas_no'],
            'signal_word'       => $s['signal_word'],
            'container_3d_model'=> null,
        ]),
    ];

    return $c;
}

// ══════════════════════════════════════════════
// DETAIL
// ══════════════════════════════════════════════
function getContainerDetails(int $id, array $user): array {
    $c = Database::fetch(
        "SELECT cn.*, ch.name as chemical_name, ch.cas_number, ch.molecular_formula,
                ch.molecular_weight, ch.hazard_pictograms, ch.signal_word, ch.ghs_classifications,
                ch.sds_url, ch.physical_state,
                CONCAT(u.first_name,' ',u.last_name) as owner_name,
                u.avatar_url as owner_avatar_url,
                l.name as lab_name, m.name as manufacturer_name,
                b.name as building_name, b.shortname as building_short,
                rm.name as room_name, rm.code as room_code,
                cab.name as cabinet_name
        FROM containers cn
        LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
        LEFT JOIN users u ON cn.owner_id = u.id
        LEFT JOIN labs l ON cn.lab_id = l.id
        LEFT JOIN manufacturers m ON cn.manufacturer_id = m.id
        LEFT JOIN buildings b ON cn.building_id = b.id
        LEFT JOIN rooms rm ON cn.room_id = rm.id
        LEFT JOIN cabinets cab ON cn.cabinet_id = cab.id
        WHERE cn.id = :id", [':id' => $id]
    );
    if (!$c) throw new Exception('Container not found');

    $c['source'] = 'container';
    $c['hazard_pictograms'] = json_decode($c['hazard_pictograms'] ?? '[]', true);
    $c['ghs_classifications'] = json_decode($c['ghs_classifications'] ?? '[]', true);
    $c['location_text'] = buildLocationText($c);
    $c['is_mine'] = ((int)($c['owner_id'] ?? 0) === (int)$user['id']);

    $c['history'] = Database::fetchAll(
        "SELECT h.*, CONCAT(u.first_name,' ',u.last_name) as user_name 
        FROM container_history h LEFT JOIN users u ON h.user_id = u.id 
        WHERE h.container_id = :cid ORDER BY h.id DESC LIMIT 20",
        [':cid' => $id]
    );

    $c['ar_data'] = getARData($c);
    return $c;
}

// ══════════════════════════════════════════════
// QR LOOKUP
// ══════════════════════════════════════════════
function getContainerByQR(string $qrCode, array $user = []): array {
    // Try containers table first (by qr_code)
    $c = Database::fetch(
        "SELECT cn.*, ch.name as chemical_name, ch.cas_number, ch.hazard_pictograms,
                ch.signal_word, ch.ghs_classifications, ch.sds_url, ch.molecular_formula,
                CONCAT(u.first_name,' ',u.last_name) as owner_name,
                l.name as lab_name,
                b.name as building_name, b.shortname as building_short,
                rm.name as room_name, rm.code as room_code
        FROM containers cn
        LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
        LEFT JOIN users u ON cn.owner_id = u.id
        LEFT JOIN labs l ON cn.lab_id = l.id
        LEFT JOIN buildings b ON cn.building_id = b.id
        LEFT JOIN rooms rm ON cn.room_id = rm.id
        WHERE cn.qr_code = :qr", [':qr' => $qrCode]
    );
    if ($c) {
        $c['source'] = 'container';
        $c['hazard_pictograms'] = json_decode($c['hazard_pictograms'] ?? '[]', true);
        $c['ghs_classifications'] = json_decode($c['ghs_classifications'] ?? '[]', true);
        $c['ar_data'] = getARData($c);
        return $c;
    }
    // Fallback: search chemical_stock by bottle_code (QR often contains bottle code)
    $s = Database::fetch(
        "SELECT s.id FROM chemical_stock s WHERE s.bottle_code = :bc LIMIT 1",
        [':bc' => $qrCode]
    );
    if ($s) return getStockDetail((int)$s['id'], $user);
    throw new Exception('Container not found');
}

// ══════════════════════════════════════════════
// CREATE
// ══════════════════════════════════════════════
function createContainer(array $data, array $user): array {
    $chemicalId = $data['chemical_id'] ?? null;
    if (!$chemicalId && !empty($data['chemical_name'])) {
        $existing = Database::fetch("SELECT id FROM chemicals WHERE name = :n LIMIT 1", [':n' => trim($data['chemical_name'])]);
        $chemicalId = $existing ? $existing['id'] : Database::insert('chemicals', [
            'name' => trim($data['chemical_name']),
            'cas_number' => $data['cas_number'] ?? null,
            'physical_state' => $data['physical_state'] ?? null,
        ]);
    }
    if (!$chemicalId) throw new Exception('Chemical name or ID required');

    $initialQty = (float)($data['initial_quantity'] ?? 0);
    if ($initialQty <= 0) throw new Exception('initial_quantity is required');

    $bottleCode = $data['bottle_code'] ?? null;
    if (empty($bottleCode)) {
        // Generate SUT-format bottle code: RoomCode + Section + FiscalYear(2) + Serial(5)
        // e.g. F05202A6800001
        $roomCode = '';
        $roomId = !empty($data['room_id']) ? (int)$data['room_id'] : null;
        if ($roomId) {
            $rm = Database::fetch("SELECT code FROM rooms WHERE id = :id", [':id' => $roomId]);
            if ($rm && !empty($rm['code'])) $roomCode = preg_replace('/[^A-Za-z0-9]/', '', $rm['code']);
        }
        if (empty($roomCode)) {
            $buildingId = !empty($data['building_id']) ? (int)$data['building_id'] : null;
            if ($buildingId) {
                $bld = Database::fetch("SELECT code FROM buildings WHERE id = :id", [':id' => $buildingId]);
                if ($bld && !empty($bld['code'])) $roomCode = preg_replace('/[^A-Za-z0-9]/', '', $bld['code']);
            }
        }
        if (empty($roomCode)) $roomCode = 'F00000';
        // Pad/trim room code to consistent length
        $roomCode = substr(str_pad($roomCode, 6, '0'), 0, 6);
        // Thai fiscal year (BE) last 2 digits: CE + 543
        $thaiYear = (int)date('Y') + 543;
        $fy = substr((string)$thaiYear, -2);
        // Section letter (A = general chemicals)
        $section = 'A';
        // Find next serial for this prefix
        $prefix = $roomCode . $section . $fy;
        $lastCode = Database::fetch(
            "SELECT bottle_code FROM containers WHERE bottle_code LIKE :pfx ORDER BY bottle_code DESC LIMIT 1",
            [':pfx' => $prefix . '%']
        );
        $nextSerial = 1;
        if ($lastCode) {
            $tail = substr($lastCode['bottle_code'], strlen($prefix));
            if (is_numeric($tail)) $nextSerial = intval($tail) + 1;
        }
        // Also check chemical_stock for same prefix to avoid collision
        $lastStockCode = Database::fetch(
            "SELECT bottle_code FROM chemical_stock WHERE bottle_code LIKE :pfx ORDER BY bottle_code DESC LIMIT 1",
            [':pfx' => $prefix . '%']
        );
        if ($lastStockCode) {
            $tail2 = substr($lastStockCode['bottle_code'], strlen($prefix));
            if (is_numeric($tail2)) $nextSerial = max($nextSerial, intval($tail2) + 1);
        }
        $bottleCode = $prefix . str_pad($nextSerial, 5, '0', STR_PAD_LEFT);
    }

    $qrCode = generateUniqueQRCode();
    $qrImagePath = null;
    try { $qrImagePath = QRGenerator::generate($qrCode, $chemicalId); } catch (Exception $e) {}

    $manufacturerId = null;
    if (!empty($data['manufacturer'])) {
        $mfr = Database::fetch("SELECT id FROM manufacturers WHERE name = :n LIMIT 1", [':n' => trim($data['manufacturer'])]);
        $manufacturerId = $mfr ? $mfr['id'] : Database::insert('manufacturers', ['name' => trim($data['manufacturer'])]);
    }

    $currentQty = $data['current_quantity'] ?? $initialQty;
    $pct = ($currentQty / $initialQty) * 100;

    $id = Database::insert('containers', [
        'qr_code' => $qrCode, 'qr_code_image' => $qrImagePath,
        'chemical_id' => $chemicalId,
        'owner_id' => $data['owner_id'] ?? $user['id'],
        'lab_id' => $data['lab_id'] ?? $user['lab_id'],
        'bottle_code' => $bottleCode,
        'container_type' => $data['container_type'] ?? 'bottle',
        'container_material' => $data['container_material'] ?? 'glass',
        'initial_quantity' => $initialQty,
        'current_quantity' => $currentQty,
        'quantity_unit' => $data['quantity_unit'] ?? 'mL',
        'remaining_percentage' => round($pct, 1),
        'grade' => $data['grade'] ?? null,
        'manufacturer_id' => $manufacturerId,
        'building_id' => !empty($data['building_id']) ? (int)$data['building_id'] : null,
        'room_id' => !empty($data['room_id']) ? (int)$data['room_id'] : null,
        'location_slot_id' => !empty($data['cabinet_id']) ? (int)$data['cabinet_id'] : ($data['location_slot_id'] ?? null),
        'received_date' => $data['received_date'] ?? date('Y-m-d'),
        'expiry_date' => $data['expiry_date'] ?? null,
        'cost' => !empty($data['cost']) ? (float)$data['cost'] : null,
        'invoice_number' => $data['invoice_number'] ?? null,
        'funding_source_id' => !empty($data['funding_source_id']) ? (int)$data['funding_source_id'] : null,
        'department_id' => !empty($data['department_id']) ? (int)$data['department_id'] : ($user['department_id'] ?? null),
        'project_name' => $data['project_name'] ?? null,
        'notes' => $data['notes'] ?? null,
        'created_by' => $user['id']
    ]);

    Database::insert('container_history', [
        'container_id' => $id, 'action_type' => 'created',
        'user_id' => $user['id'], 'quantity_after' => $initialQty,
        'notes' => 'Container created: ' . $bottleCode,
    ]);

    return ['id' => $id, 'bottle_code' => $bottleCode, 'qr_code' => $qrCode, 'message' => 'Container created'];
}

// ══════════════════════════════════════════════
// UPDATE
// ══════════════════════════════════════════════
function updateContainer(int $id, array $data, array $user): void {
    $c = Database::fetch("SELECT * FROM containers WHERE id = :id", [':id' => $id]);
    if (!$c) throw new Exception('Container not found');
    if ($user['role_name'] !== 'admin' && $user['role_name'] !== 'lab_manager' && $c['owner_id'] !== $user['id'])
        throw new Exception('Permission denied');

    $upd = [];
    foreach (['location_slot_id','current_quantity','status','quality_status','opened_date','expiry_date','notes','container_3d_model','building_id','room_id','cabinet_id'] as $f) {
        if (isset($data[$f])) $upd[$f] = $data[$f];
    }

    // Admin-only: update barcode / QR code
    if ($user['role_name'] === 'admin') {
        if (isset($data['bottle_code']) && trim($data['bottle_code']) !== '') {
            $newBC = trim($data['bottle_code']);
            // Duplicate check (exclude self)
            $dup = Database::fetch("SELECT id FROM containers WHERE bottle_code = :bc AND id != :id", [':bc' => $newBC, ':id' => $id]);
            if ($dup) throw new Exception('Barcode already exists on another container (id=' . $dup['id'] . ')');
            $upd['bottle_code'] = $newBC;
        }
        if (isset($data['qr_code']) && trim($data['qr_code']) !== '') {
            $newQR = trim($data['qr_code']);
            $dupQR = Database::fetch("SELECT id FROM containers WHERE qr_code = :qr AND id != :id", [':qr' => $newQR, ':id' => $id]);
            if ($dupQR) throw new Exception('QR Code already exists on another container (id=' . $dupQR['id'] . ')');
            $upd['qr_code'] = $newQR;
        }
    }

    if (isset($data['current_quantity'])) {
        $init = (float)$c['initial_quantity'];
        if ($init > 0) $upd['remaining_percentage'] = round(((float)$data['current_quantity'] / $init) * 100, 1);
        if ((float)$data['current_quantity'] <= 0) { $upd['status'] = 'empty'; $upd['remaining_percentage'] = 0; }
    }

    if ($upd) Database::update('containers', $upd, 'id = :id', [':id' => $id]);

    if (isset($data['current_quantity']) && $data['current_quantity'] != $c['current_quantity']) {
        Database::insert('container_history', [
            'container_id' => $id, 'action_type' => 'used', 'user_id' => $user['id'],
            'quantity_change' => $data['current_quantity'] - $c['current_quantity'],
            'quantity_after' => $data['current_quantity'],
            'notes' => $data['usage_notes'] ?? 'Quantity updated',
        ]);
    }
    if (isset($data['location_slot_id']) && $data['location_slot_id'] != $c['location_slot_id']) {
        Database::insert('container_history', [
            'container_id' => $id, 'action_type' => 'moved', 'user_id' => $user['id'],
            'from_location_id' => $c['location_slot_id'], 'to_location_id' => $data['location_slot_id'],
            'notes' => 'Location updated',
        ]);
    }
}

// ══════════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════════
function deleteContainer(int $id, array $user): void {
    $c = Database::fetch("SELECT * FROM containers WHERE id = :id", [':id' => $id]);
    if (!$c) throw new Exception('Container not found');
    if ($user['role_name'] !== 'admin' && $user['role_name'] !== 'lab_manager' && $c['owner_id'] !== $user['id'])
        throw new Exception('Permission denied');
    Database::update('containers', ['status' => 'disposed'], 'id = :id', [':id' => $id]);
    Database::insert('container_history', [
        'container_id' => $id, 'action_type' => 'disposed',
        'user_id' => $user['id'], 'notes' => 'Container disposed',
    ]);
}

// ══════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════
function generateUniqueQRCode(): string {
    return 'CHEM-' . time() . '-' . bin2hex(random_bytes(4));
}

function getARDataLight(array $c): array {
    static $cache = [];
    $cacheKey = ($c['container_type'] ?? 'bottle') . '|' . ($c['container_material'] ?? 'glass');

    if (!empty($c['container_3d_model'])) {
        return ['model_url' => $c['container_3d_model'], 'model_type' => 'glb'];
    }

    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $model = Database::fetch(
        "SELECT source_type, embed_url, file_url FROM packaging_3d_models 
         WHERE container_type = :t AND (container_material = :m OR container_material IS NULL OR container_material = '')
         AND is_active = 1 ORDER BY is_default DESC LIMIT 1",
        [':t' => $c['container_type'] ?? 'bottle', ':m' => $c['container_material'] ?? 'glass']
    );
    if (!$model) {
        $model = Database::fetch(
            "SELECT source_type, embed_url, file_url FROM packaging_3d_models WHERE container_type = :t AND is_active = 1 ORDER BY is_default DESC LIMIT 1",
            [':t' => $c['container_type'] ?? 'bottle']
        );
    }

    $url = null; $type = null;
    if ($model) {
        if ($model['source_type'] === 'embed' && !empty($model['embed_url'])) {
            $url = $model['embed_url']; $type = 'embed';
        } elseif (!empty($model['file_url'])) {
            $url = $model['file_url']; $type = 'glb';
        }
    }

    $cache[$cacheKey] = ['model_url' => $url, 'model_type' => $type];
    return $cache[$cacheKey];
}

function getARData(array $c): array {
    $model = Database::fetch(
        "SELECT * FROM packaging_3d_models 
         WHERE container_type = :t AND (container_material = :m OR container_material IS NULL OR container_material = '')
         AND is_active = 1 ORDER BY is_default DESC LIMIT 1",
        [':t' => $c['container_type'] ?? 'bottle', ':m' => $c['container_material'] ?? 'glass']
    );
    if (!$model) {
        $model = Database::fetch(
            "SELECT * FROM packaging_3d_models WHERE container_type = :t AND is_active = 1 ORDER BY is_default DESC LIMIT 1",
            [':t' => $c['container_type'] ?? 'bottle']
        );
    }

    $modelUrl = null; $modelType = null; $embedCode = null; $thumbnailUrl = null;

    if (!empty($c['container_3d_model'])) {
        $modelUrl = $c['container_3d_model']; $modelType = 'glb';
    } elseif ($model) {
        if ($model['source_type'] === 'embed' && !empty($model['embed_url'])) {
            $modelUrl = $model['embed_url']; $modelType = 'embed';
            $embedCode = $model['embed_code'] ?? null;
        } elseif (!empty($model['file_url'])) {
            $modelUrl = $model['file_url']; $modelType = 'glb';
        }
        $thumbnailUrl = $model['thumbnail_path'] ?? null;
    }

    return [
        'model_id'        => $model['id'] ?? null,
        'model_url'       => $modelUrl,
        'model_type'      => $modelType,
        'embed_code'      => $embedCode,
        'thumbnail_url'   => $thumbnailUrl,
        'model_label'     => $model['label'] ?? null,
        'ar_enabled'      => (bool)($model['ar_enabled'] ?? false),
        'remaining_level' => (float)($c['remaining_percentage'] ?? 100),
        'hazard_labels'   => is_array($c['hazard_pictograms'] ?? null) ? $c['hazard_pictograms'] : json_decode($c['hazard_pictograms'] ?? '[]', true),
        'cas_number'      => $c['cas_number'] ?? null,
        'signal_word'     => $c['signal_word'] ?? null,
        'has_model'       => $modelUrl !== null,
    ];
}
