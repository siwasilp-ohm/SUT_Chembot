<?php
/**
 * Chemical Warehouses API
 * 
 * Endpoints:
 *   GET  ?action=list           → All warehouses with hierarchy
 *   GET  ?action=overview       → Org-wide summary stats (CEO view)
 *   GET  ?action=detail&id=X    → Single warehouse detail
 *   GET  ?action=store_chemicals&id=X → Chemical stock items in a warehouse
 *   GET  ?action=divisions      → Summary by division (ฝ่าย)
 *   POST ?action=update&id=X    → Update warehouse info (admin)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

$user = Auth::requireAuth();
$role = $user['role_name'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// Only admin, ceo, lab_manager can access
if (!in_array($role, ['admin', 'ceo', 'lab_manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    switch ($action) {

    // ═══════════════════════════════════════════
    // LIST: All warehouses with full hierarchy
    // ═══════════════════════════════════════════
    case 'list':
        $division = $_GET['division'] ?? '';
        $status   = $_GET['status'] ?? '';
        $search   = $_GET['search'] ?? '';
        $sort     = $_GET['sort'] ?? 'weight';
        $hasStock = $_GET['has_stock'] ?? '';
        
        $where = ['1=1'];
        $params = [];
        
        if ($division) {
            $where[] = 'w.division_id = :div';
            $params[':div'] = $division;
        }
        if ($status) {
            $where[] = 'w.status = :st';
            $params[':st'] = $status;
        }
        if ($hasStock === '1') {
            $where[] = 'w.total_bottles > 0';
        } elseif ($hasStock === '0') {
            $where[] = 'w.total_bottles = 0';
        }
        if ($search) {
            $where[] = '(w.name LIKE :q OR w.unit_name LIKE :q OR w.division_name LIKE :q OR d3.name LIKE :q)';
            $params[':q'] = "%$search%";
        }
        
        $orderBy = match($sort) {
            'bottles' => 'w.total_bottles DESC',
            'chemicals' => 'w.total_chemicals DESC',
            'name' => 'w.name ASC',
            'division' => 'w.division_name ASC, w.total_weight_kg DESC',
            default => 'w.total_weight_kg DESC'
        };
        
        $warehouses = Database::fetchAll("
            SELECT w.*,
                   d3.name as dept_name, d3.level_label as dept_level,
                   d2.name as div_name, d2.id as div_id_calc,
                   d1.name as ctr_name,
                   b.name as building_name_full, b.shortname as building_short,
                   u.first_name as mgr_first, u.last_name as mgr_last, u.full_name_th as mgr_name
            FROM chemical_warehouses w
            LEFT JOIN departments d3 ON w.department_id = d3.id
            LEFT JOIN departments d2 ON w.division_id = d2.id
            LEFT JOIN departments d1 ON w.center_id = d1.id
            LEFT JOIN buildings b ON w.building_id = b.id
            LEFT JOIN users u ON w.manager_user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
        ", $params);
        
        echo json_encode(['success' => true, 'data' => $warehouses, 'total' => count($warehouses)]);
        break;

    // ═══════════════════════════════════════════
    // OVERVIEW: Org-wide summary (CEO dashboard)
    // ═══════════════════════════════════════════
    case 'overview':
        // Grand totals
        $totals = Database::fetch("
            SELECT 
                COUNT(*) as total_warehouses,
                COUNT(CASE WHEN total_bottles > 0 THEN 1 END) as active_warehouses,
                SUM(total_bottles) as total_bottles,
                SUM(total_chemicals) as total_chemicals,
                SUM(total_weight_kg) as total_weight_kg
            FROM chemical_warehouses
            WHERE status = 'active'
        ");
        
        // By division (ฝ่าย)
        $byDivision = Database::fetchAll("
            SELECT 
                w.division_id, w.division_name,
                COUNT(*) as warehouse_count,
                COUNT(CASE WHEN w.total_bottles > 0 THEN 1 END) as active_count,
                SUM(w.total_bottles) as bottles,
                SUM(w.total_chemicals) as chemicals,
                SUM(w.total_weight_kg) as weight_kg
            FROM chemical_warehouses w
            WHERE w.status = 'active' AND w.division_id IS NOT NULL
            GROUP BY w.division_id, w.division_name
            ORDER BY weight_kg DESC
        ");
        
        // Top 10 warehouses by weight
        $topByWeight = Database::fetchAll("
            SELECT w.id, w.name, w.division_name, w.unit_name,
                   w.total_bottles, w.total_chemicals, w.total_weight_kg,
                   b.shortname as building
            FROM chemical_warehouses w
            LEFT JOIN buildings b ON w.building_id = b.id
            WHERE w.status = 'active' AND w.total_weight_kg > 0
            ORDER BY w.total_weight_kg DESC
            LIMIT 10
        ");
        
        // Top 10 by bottle count
        $topByBottles = Database::fetchAll("
            SELECT w.id, w.name, w.division_name, w.unit_name,
                   w.total_bottles, w.total_chemicals, w.total_weight_kg,
                   b.shortname as building
            FROM chemical_warehouses w
            LEFT JOIN buildings b ON w.building_id = b.id
            WHERE w.status = 'active' AND w.total_bottles > 0
            ORDER BY w.total_bottles DESC
            LIMIT 10
        ");
        
        // By building
        $byBuilding = Database::fetchAll("
            SELECT 
                b.id as building_id, b.name as building_name, b.shortname,
                COUNT(w.id) as warehouse_count,
                SUM(w.total_bottles) as bottles,
                SUM(w.total_chemicals) as chemicals,
                SUM(w.total_weight_kg) as weight_kg
            FROM chemical_warehouses w
            JOIN buildings b ON w.building_id = b.id
            WHERE w.status = 'active'
            GROUP BY b.id, b.name, b.shortname
            ORDER BY weight_kg DESC
        ");
        
        // Empty warehouses count
        $emptyCount = Database::fetch("
            SELECT COUNT(*) as cnt FROM chemical_warehouses 
            WHERE status = 'active' AND total_bottles = 0
        ");
        
        echo json_encode([
            'success' => true,
            'data' => [
                'totals'       => $totals,
                'by_division'  => $byDivision,
                'by_building'  => $byBuilding,
                'top_by_weight'=> $topByWeight,
                'top_by_bottles'=> $topByBottles,
                'empty_count'  => (int)$emptyCount['cnt']
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════════
    // DETAIL: Single warehouse
    // ═══════════════════════════════════════════
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Warehouse ID required');
        
        $warehouse = Database::fetch("
            SELECT w.*,
                   d3.name as dept_name,
                   d2.name as div_name,
                   d1.name as ctr_name,
                   b.name as building_name_full, b.shortname as building_short, b.code as building_code,
                   u.first_name as mgr_first, u.last_name as mgr_last, u.full_name_th as mgr_name, u.email as mgr_email
            FROM chemical_warehouses w
            LEFT JOIN departments d3 ON w.department_id = d3.id
            LEFT JOIN departments d2 ON w.division_id = d2.id
            LEFT JOIN departments d1 ON w.center_id = d1.id
            LEFT JOIN buildings b ON w.building_id = b.id
            LEFT JOIN users u ON w.manager_user_id = u.id
            WHERE w.id = :id
        ", [':id' => $id]);
        
        if (!$warehouse) throw new Exception('Warehouse not found');
        
        // Sibling warehouses (same division)
        $siblings = Database::fetchAll("
            SELECT id, name, total_bottles, total_chemicals, total_weight_kg
            FROM chemical_warehouses
            WHERE division_id = :div AND id != :id AND status = 'active'
            ORDER BY total_weight_kg DESC
            LIMIT 10
        ", [':div' => $warehouse['division_id'], ':id' => $id]);
        
        echo json_encode([
            'success' => true,
            'data' => $warehouse,
            'siblings' => $siblings
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════════
    // STORE_CHEMICALS: Chemicals in a warehouse
    // ═══════════════════════════════════════════
    case 'store_chemicals':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Warehouse ID required');
        $search = $_GET['search'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $ownerFilter = (int)($_GET['owner_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        // Get warehouse info
        $wh = Database::fetch("SELECT * FROM chemical_warehouses WHERE id = :id", [':id' => $id]);
        if (!$wh) throw new Exception('Warehouse not found');
        
        // Find users that belong to this warehouse via org hierarchy
        // Matching logic: warehouse.unit_name ↔ users.position OR users.department
        $unitName = $wh['unit_name'] ?? '';
        $divName  = $wh['division_name'] ?? '';
        $whName   = $wh['name'] ?? '';
        
        // Build user matching conditions
        // Users can be matched by: 
        // 1) position = warehouse.unit_name (section-level match)
        // 2) department = warehouse.division_name AND position matches warehouse name
        // 3) position contains warehouse name pattern
        $userWhere = [];
        $userParams = [];
        
        if ($unitName) {
            $userWhere[] = "u.position = :unit1";
            $userParams[':unit1'] = $unitName;
            
            // Also match department = unit_name (inconsistent data pattern)
            $userWhere[] = "u.department = :unit2";
            $userParams[':unit2'] = $unitName;
        }
        
        if ($whName) {
            // Match position to warehouse name directly
            $userWhere[] = "u.position = :whname";
            $userParams[':whname'] = $whName;
        }
        
        if (empty($userWhere)) {
            // Fallback: match by division
            if ($divName) {
                $userWhere[] = "u.department = :div";
                $userParams[':div'] = $divName;
            }
        }
        
        if (empty($userWhere)) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'summary' => ['total_items' => 0, 'total_bottles' => 0, 'unique_chemicals' => 0, 'total_weight' => 0],
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'pages' => 0]
            ], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $userSQL = implode(' OR ', $userWhere);
        
        // Get matching user IDs
        $userIds = Database::fetchAll("SELECT u.id FROM users u WHERE $userSQL", $userParams);
        $ids = array_map(fn($u) => (int)$u['id'], $userIds);
        
        if (empty($ids)) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'summary' => ['total_items' => 0, 'total_bottles' => 0, 'unique_chemicals' => 0, 'total_weight' => 0],
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'pages' => 0]
            ], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        // If owner_id filter is set, narrow down to just that user
        if ($ownerFilter && in_array($ownerFilter, $ids)) {
            $ids = [$ownerFilter];
        }
        
        // Build named placeholders for user IDs (Database::query uses bindValue which needs named or 1-based params)
        $idPlaceholders = [];
        $idParams = [];
        foreach ($ids as $i => $uid) {
            $key = ':uid' . $i;
            $idPlaceholders[] = $key;
            $idParams[$key] = $uid;
        }
        $inSQL = implode(',', $idPlaceholders);
        
        // Build chemical stock query with filters
        $stockWhere = ["cs.owner_user_id IN ($inSQL)"];
        $stockParams = $idParams;  // named params
        
        if ($statusFilter && $statusFilter !== 'all') {
            $stockWhere[] = "cs.status = :sfilt";
            $stockParams[':sfilt'] = $statusFilter;
        }
        
        if ($search) {
            $stockWhere[] = "(cs.chemical_name LIKE :sq1 OR cs.bottle_code LIKE :sq2 OR cs.cas_no LIKE :sq3 OR cs.owner_name LIKE :sq4)";
            $sq = "%$search%";
            $stockParams[':sq1'] = $sq;
            $stockParams[':sq2'] = $sq;
            $stockParams[':sq3'] = $sq;
            $stockParams[':sq4'] = $sq;
        }
        
        $whereSQL = implode(' AND ', $stockWhere);
        
        // Get summary
        $summary = Database::fetch("
            SELECT 
                COUNT(*) as total_items,
                COUNT(DISTINCT cs.chemical_name) as unique_chemicals,
                SUM(cs.remaining_qty) as total_weight,
                COUNT(DISTINCT cs.owner_user_id) as holder_count
            FROM chemical_stock cs
            WHERE $whereSQL
        ", $stockParams);
        
        // Status breakdown (only needs id params)
        $statusBreak = Database::fetchAll("
            SELECT cs.status, COUNT(*) as cnt
            FROM chemical_stock cs
            WHERE cs.owner_user_id IN ($inSQL)
            GROUP BY cs.status
            ORDER BY cnt DESC
        ", $idParams);
        
        // Get paginated chemical list
        $chemicals = Database::fetchAll("
            SELECT 
                cs.id, cs.bottle_code, cs.chemical_name, cs.cas_no, cs.grade,
                cs.package_size, cs.remaining_qty, cs.unit, cs.remaining_pct,
                cs.owner_name, cs.owner_user_id, cs.status,
                cs.added_at, cs.storage_location,
                u.first_name as owner_first, u.last_name as owner_last
            FROM chemical_stock cs
            LEFT JOIN users u ON cs.owner_user_id = u.id
            WHERE $whereSQL
            ORDER BY cs.chemical_name ASC, cs.bottle_code ASC
            LIMIT $limit OFFSET $offset
        ", $stockParams);
        
        // Top chemicals by count (only needs id params)
        $topChems = Database::fetchAll("
            SELECT cs.chemical_name, cs.cas_no, COUNT(*) as bottle_count, 
                   SUM(cs.remaining_qty) as total_qty, MIN(cs.unit) as unit
            FROM chemical_stock cs
            WHERE cs.owner_user_id IN ($inSQL) AND cs.status = 'active'
            GROUP BY cs.chemical_name, cs.cas_no
            ORDER BY bottle_count DESC
            LIMIT 10
        ", $idParams);
        
        // Owner breakdown (only needs id params)
        $owners = Database::fetchAll("
            SELECT cs.owner_user_id, cs.owner_name, 
                   u.first_name, u.last_name,
                   COUNT(*) as bottle_count,
                   SUM(cs.remaining_qty) as total_qty
            FROM chemical_stock cs
            LEFT JOIN users u ON cs.owner_user_id = u.id
            WHERE cs.owner_user_id IN ($inSQL) AND cs.status = 'active'
            GROUP BY cs.owner_user_id, cs.owner_name, u.first_name, u.last_name
            ORDER BY bottle_count DESC
        ", $idParams);
        
        $total = (int)$summary['total_items'];
        
        echo json_encode([
            'success' => true,
            'data' => $chemicals,
            'summary' => [
                'total_items' => $total,
                'unique_chemicals' => (int)$summary['unique_chemicals'],
                'total_weight' => (float)$summary['total_weight'],
                'holder_count' => (int)$summary['holder_count']
            ],
            'status_breakdown' => $statusBreak,
            'top_chemicals' => $topChems,
            'owners' => $owners,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════════
    // DIVISIONS: Summary grouped by ฝ่าย
    // ═══════════════════════════════════════════
    case 'divisions':
        $divisions = Database::fetchAll("
            SELECT 
                d2.id, d2.name,
                COUNT(w.id) as warehouse_count,
                COUNT(CASE WHEN w.total_bottles > 0 THEN 1 END) as active_warehouses,
                SUM(w.total_bottles) as bottles,
                SUM(w.total_chemicals) as chemicals,
                SUM(w.total_weight_kg) as weight_kg,
                GROUP_CONCAT(DISTINCT d3.name SEPARATOR '||') as units
            FROM departments d2
            LEFT JOIN chemical_warehouses w ON w.division_id = d2.id AND w.status = 'active'
            LEFT JOIN departments d3 ON w.department_id = d3.id
            WHERE d2.level = 2
            GROUP BY d2.id, d2.name
            ORDER BY weight_kg DESC
        ");
        
        echo json_encode(['success' => true, 'data' => $divisions], JSON_UNESCAPED_UNICODE);
        break;

    // ═══════════════════════════════════════════
    // UPDATE: Edit warehouse (admin only)
    // ═══════════════════════════════════════════
    case 'update':
        if ($role !== 'admin') throw new Exception('Admin only');
        if ($method !== 'POST') throw new Exception('POST required');
        
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Warehouse ID required');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $allowed = ['name', 'code', 'description', 'status', 'manager_user_id', 
                     'floor', 'zone', 'building_id', 'room_id'];
        $updateData = [];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field] === '' ? null : $input[$field];
            }
        }
        
        if (empty($updateData)) throw new Exception('No fields to update');
        
        Database::update('chemical_warehouses', $updateData, 'id = :id', [':id' => $id]);
        
        echo json_encode(['success' => true, 'message' => 'Updated']);
        break;

    default:
        throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
