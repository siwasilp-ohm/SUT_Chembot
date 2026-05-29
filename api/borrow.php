<?php
/**
 * Chemical Transaction Lifecycle API
 * Manages borrow, return, transfer, dispose — full traceability by barcode
 *
 * GET  ?action=dashboard          Stats overview
 * GET  ?action=list               List transactions (with filters)
 * GET  ?action=detail&id=N        Single transaction detail
 * GET  ?action=timeline&barcode=X Full lifecycle timeline for a barcode
 * GET  ?action=search_items       Search containers/stock for quick-pick
 * GET  ?action=search_users       Search users for recipient
 * GET  ?action=scan_barcode       Smart barcode lookup with role detection
 * GET  ?action=disposal_bin       List items in disposal bin
 * GET  ?action=my_active          My currently borrowed items
 * POST ?action=borrow             Create borrow transaction
 * POST ?action=return             Return a borrowed item
 * POST ?action=transfer           Transfer ownership
 * POST ?action=use                Owner uses/consumes own chemical
 * POST ?action=dispose            Move to disposal bin
 * POST ?action=approve            Approve a pending transaction
 * POST ?action=reject             Reject a pending transaction
 * POST ?action=disposal_complete  Complete disposal (admin)
 * POST ?action=disposal_cancel    Cancel disposal (admin)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user   = Auth::requireAuth();
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin   = $roleLevel >= 5;
$isManager = $roleLevel >= 3;

try {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method === 'GET') {
        switch ($action) {
            case 'dashboard':       echo json_encode(['success'=>true,'data'=>getDashboard($user)]); break;
            case 'list':            echo json_encode(['success'=>true,'data'=>listTransactions($_GET, $user)]); break;
            case 'detail':          echo json_encode(['success'=>true,'data'=>getDetail((int)($_GET['id']??0), $user)]); break;
            case 'timeline':        echo json_encode(['success'=>true,'data'=>getTimeline($_GET)]); break;
            case 'search_items':    echo json_encode(['success'=>true,'data'=>searchItems($_GET, $user)]); break;
            case 'search_users':    echo json_encode(['success'=>true,'data'=>searchUsers($_GET)]); break;
            case 'scan_barcode':    echo json_encode(['success'=>true,'data'=>scanBarcode($_GET, $user)]); break;
            case 'disposal_bin':    echo json_encode(['success'=>true,'data'=>listDisposalBin($_GET, $user)]); break;
            case 'disposal_report': echo json_encode(['success'=>true,'data'=>disposalReport($_GET, $user)]); break;
            case 'my_active':       echo json_encode(['success'=>true,'data'=>myActive($user)]); break;
            case 'activity_summary': echo json_encode(['success'=>true,'data'=>activitySummary($_GET, $user)]); break;
            case 'activity_type_detail': echo json_encode(['success'=>true,'data'=>activityTypeDetail($_GET, $user)]); break;
            case 'activity_chem_lifecycle': echo json_encode(['success'=>true,'data'=>activityChemLifecycle($_GET, $user)]); break;
            case 'activity_chart': echo json_encode(['success'=>true,'data'=>activityChartData($_GET, $user)]); break;
            default: throw new Exception('Unknown GET action: '.$action);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        switch ($action) {
            case 'borrow':           echo json_encode(['success'=>true,'data'=>createBorrow($data, $user)]); break;
            case 'return':           echo json_encode(['success'=>true,'data'=>createReturn($data, $user)]); break;
            case 'transfer':         echo json_encode(['success'=>true,'data'=>createTransfer($data, $user)]); break;
            case 'use':              echo json_encode(['success'=>true,'data'=>createUse($data, $user)]); break;
            case 'report_item':      echo json_encode(['success'=>true,'data'=>reportItem($data, $user)]); break;
            case 'dispose':
                if (!$isManager) throw new Exception('Permission denied', 403);
                echo json_encode(['success'=>true,'data'=>createDispose($data, $user)]);
                break;
            case 'approve':          echo json_encode(['success'=>true,'data'=>approveTxn($data, $user)]); break;
            case 'reject':           echo json_encode(['success'=>true,'data'=>rejectTxn($data, $user)]); break;
            case 'disposal_complete':
                if (!$isAdmin) throw new Exception('Permission denied', 403);
                echo json_encode(['success'=>true,'data'=>disposalComplete($data, $user)]);
                break;
            case 'disposal_cancel':
                if (!$isManager) throw new Exception('Permission denied', 403);
                echo json_encode(['success'=>true,'data'=>disposalCancel($data, $user)]);
                break;
            default: throw new Exception('Unknown POST action: '.$action);
        }
    } else {
        throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    http_response_code($code);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}

// ========== HELPERS ==========

/**
 * Fire-and-forget alert insertion. Never throws — borrow ops must not fail due to alert errors.
 */
function notifyAlert(array $data): void {
    try {
        Database::insert('alerts', array_merge([
            'is_read' => 0, 'dismissed' => 0, 'action_required' => 0, 'severity' => 'info'
        ], $data));
    } catch (\Throwable $e) {
        error_log('[notifyAlert] ' . $e->getMessage());
    }
}

function genTxnNumber(): string {
    return 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function getSourceInfo(string $type, int $id): array {
    if ($type === 'container') {
        $row = Database::fetch("
            SELECT c.id, c.qr_code as barcode, c.chemical_id, c.current_quantity as remaining_qty,
                   c.quantity_unit as unit, c.owner_id, c.status,
                   ch.name as chemical_name, ch.cas_number,
                   u.first_name, u.last_name, u.department,
                   COALESCE(b.name,'') as building_name
            FROM containers c
            JOIN chemicals ch ON ch.id = c.chemical_id
            LEFT JOIN users u ON u.id = c.owner_id
            LEFT JOIN slots sl ON sl.id = c.location_slot_id
            LEFT JOIN shelves sh ON sh.id = sl.shelf_id
            LEFT JOIN cabinets cb ON cb.id = sh.cabinet_id
            LEFT JOIN rooms rm ON rm.id = cb.room_id
            LEFT JOIN buildings b ON b.id = rm.building_id
            WHERE c.id = :id", [':id'=>$id]);
    } else {
        $row = Database::fetch("
            SELECT cs.id, cs.bottle_code as barcode, cs.chemical_id, cs.remaining_qty,
                   cs.unit, cs.owner_user_id as owner_id, cs.status,
                   COALESCE(ch.name, cs.chemical_name) as chemical_name,
                   COALESCE(ch.cas_number, cs.cas_no) as cas_number,
                   COALESCE(u.first_name,'') as first_name, COALESCE(u.last_name,'') as last_name,
                   COALESCE(u.department,'') as department,
                   cs.storage_location as building_name, cs.owner_name
            FROM chemical_stock cs
            LEFT JOIN chemicals ch ON ch.id = cs.chemical_id
            LEFT JOIN users u ON u.id = cs.owner_user_id
            WHERE cs.id = :id", [':id'=>$id]);
    }
    if (!$row) throw new Exception("Item not found: {$type}#{$id}");
    return $row;
}

function updateSourceQty(string $type, int $id, float $newQty): void {
    if ($type === 'container') {
        Database::update('containers', ['current_quantity'=>$newQty], 'id = :id', [':id'=>$id]);
    } else {
        Database::update('chemical_stock', ['remaining_qty'=>$newQty], 'id = :id', [':id'=>$id]);
    }
}

function getUserBuilding(int $userId): ?int {
    $row = Database::fetch("
        SELECT r.building_id FROM users u
        JOIN labs l ON l.id = u.lab_id
        JOIN rooms r ON r.lab_id = l.id
        WHERE u.id = :uid LIMIT 1", [':uid'=>$userId]);
    return $row ? (int)$row['building_id'] : null;
}

// ========== DASHBOARD ==========

function getDashboard(array $user): array {
    $uid = $user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;

    // ── Common stats for all roles ──
    $myBorrows = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions
        WHERE txn_type='borrow' AND to_user_id = :uid AND status='completed'
          AND id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)",
        [':uid'=>$uid])['cnt'] ?? 0;

    $myUses = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions
        WHERE txn_type='use' AND initiated_by = :uid AND status='completed'",
        [':uid'=>$uid])['cnt'] ?? 0;

    $myTransfers = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions
        WHERE txn_type='transfer' AND (from_user_id = :uid1 OR to_user_id = :uid2) AND status='completed'",
        [':uid1'=>$uid, ':uid2'=>$uid])['cnt'] ?? 0;

    // My chemicals being lent out (I own, others are borrowing)
    $myLentOut = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions
        WHERE txn_type='borrow' AND from_user_id = :uid AND to_user_id != :uid2 AND status='completed'
          AND id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)",
        [':uid'=>$uid, ':uid2'=>$uid])['cnt'] ?? 0;

    // My stock count
    $myStockCount = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_stock WHERE owner_user_id = :uid AND status IN ('active','low')",
        [':uid'=>$uid])['cnt'] ?? 0;
    $myContainerCount = Database::fetch("
        SELECT COUNT(*) as cnt FROM containers WHERE owner_id = :uid AND status IN ('active','empty')",
        [':uid'=>$uid])['cnt'] ?? 0;

    // ── Pending approvals (role-aware) ──
    $pendingWhere = $isAdmin ? "1=1" : ($isManager ? "1=1" : "ct.from_user_id = :uid2");
    $pendingParams = ($isAdmin || $isManager) ? [] : [':uid2'=>$uid];
    $pendingApprovals = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions ct WHERE ct.status='pending' AND {$pendingWhere}",
        $pendingParams)['cnt'] ?? 0;

    // ── Overdue ──
    $overdueWhere = ($isAdmin || $isManager) ? "1=1" : "(to_user_id = :uid3 OR from_user_id = :uid3)";
    $overdueParams = ($isAdmin || $isManager) ? [] : [':uid3'=>$uid];
    $overdue = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions
        WHERE txn_type='borrow' AND status='completed' AND expected_return_date IS NOT NULL AND expected_return_date < CURDATE()
          AND {$overdueWhere}
          AND id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)",
        $overdueParams)['cnt'] ?? 0;

    $disposalCount = Database::fetch("SELECT COUNT(*) as cnt FROM disposal_bin WHERE status IN ('pending','approved')")['cnt'] ?? 0;
    if ($isAdmin || $isManager) {
        $recentCount = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['cnt'] ?? 0;
        $totalTxn    = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_transactions")['cnt'] ?? 0;
    } else {
        $recentCount = Database::fetch(
            "SELECT COUNT(*) as cnt FROM chemical_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND (from_user_id = :uid OR to_user_id = :uid OR initiated_by = :uid)",
            [':uid' => $uid])['cnt'] ?? 0;
        $totalTxn = Database::fetch(
            "SELECT COUNT(*) as cnt FROM chemical_transactions WHERE from_user_id = :uid OR to_user_id = :uid OR initiated_by = :uid",
            [':uid' => $uid])['cnt'] ?? 0;
    }

    $result = [
        'role_level'        => $roleLevel,
        'my_borrows'        => (int)$myBorrows,
        'my_uses'           => (int)$myUses,
        'my_transfers'      => (int)$myTransfers,
        'my_lent_out'       => (int)$myLentOut,
        'my_stock'          => (int)$myStockCount + (int)$myContainerCount,
        'pending_approvals' => (int)$pendingApprovals,
        'overdue'           => (int)$overdue,
        'disposal_bin'      => (int)$disposalCount,
        'recent_7d'         => (int)$recentCount,
        'total_transactions'=> (int)$totalTxn
    ];

    // ── Admin/Manager extras ──
    if ($isAdmin || $isManager) {
        $totalActiveBorrows = Database::fetch("
            SELECT COUNT(*) as cnt FROM chemical_transactions
            WHERE txn_type='borrow' AND status='completed'
              AND id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)")['cnt'] ?? 0;
        $totalUsers = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE is_active = 1")['cnt'] ?? 0;
        $totalChemicals = Database::fetch("SELECT COUNT(*) as cnt FROM chemicals")['cnt'] ?? 0;
        $result['total_active_borrows'] = (int)$totalActiveBorrows;
        $result['total_users'] = (int)$totalUsers;
        $result['total_chemicals'] = (int)$totalChemicals;
    }

    return $result;
}

// ========== ACTIVITY SUMMARY ==========

function activitySummary(array $filters, array $user): array {
    $uid = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;

    $scope = ($isAdmin || $isManager) ? '1=1' : '(ct.from_user_id = :me1 OR ct.to_user_id = :me2 OR ct.initiated_by = :me3)';
    $scopeP = ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid];

    // 1) Summary by type
    $byType = Database::fetchAll("
        SELECT ct.txn_type, ct.status, COUNT(*) as cnt
        FROM chemical_transactions ct
        WHERE {$scope}
        GROUP BY ct.txn_type, ct.status
        ORDER BY ct.txn_type, ct.status", $scopeP);

    $typeSummary = [];
    foreach ($byType as $row) {
        $t = $row['txn_type'];
        if (!isset($typeSummary[$t])) $typeSummary[$t] = ['total'=>0, 'statuses'=>[]];
        $typeSummary[$t]['total'] += (int)$row['cnt'];
        $typeSummary[$t]['statuses'][$row['status']] = (int)$row['cnt'];
    }

    // 2) Summary by chemical (top 10 most active)
    $byChemical = Database::fetchAll("
        SELECT ct.chemical_id,
               COALESCE(ch.name, 'Unknown') as chemical_name,
               ch.cas_number,
               COUNT(*) as txn_count,
               SUM(CASE WHEN ct.txn_type='borrow' THEN 1 ELSE 0 END) as borrows,
               SUM(CASE WHEN ct.txn_type='use' THEN 1 ELSE 0 END) as uses,
               SUM(CASE WHEN ct.txn_type='transfer' THEN 1 ELSE 0 END) as transfers,
               SUM(CASE WHEN ct.txn_type='return' THEN 1 ELSE 0 END) as returns,
               SUM(CASE WHEN ct.txn_type='dispose' THEN 1 ELSE 0 END) as disposes,
               MAX(ct.created_at) as last_activity
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        WHERE {$scope} AND ct.chemical_id > 0
        GROUP BY ct.chemical_id, ch.name, ch.cas_number
        ORDER BY txn_count DESC
        LIMIT 12", $scopeP);

    // 3) Recent activity feed (last 10)
    $recentFeed = Database::fetchAll("
        SELECT ct.id, ct.txn_number, ct.txn_type, ct.status, ct.quantity, ct.unit, ct.barcode,
               ct.created_at,
               COALESCE(ch.name, 'Unknown') as chemical_name,
               CONCAT(fu.first_name,' ',fu.last_name) as from_name,
               CONCAT(tu.first_name,' ',tu.last_name) as to_name
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        WHERE {$scope}
        ORDER BY ct.created_at DESC
        LIMIT 10", $scopeP);

    // 4) Monthly trend (last 6 months)
    $monthly = Database::fetchAll("
        SELECT DATE_FORMAT(ct.created_at,'%Y-%m') as month,
               ct.txn_type,
               COUNT(*) as cnt
        FROM chemical_transactions ct
        WHERE ct.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND {$scope}
        GROUP BY month, ct.txn_type
        ORDER BY month", $scopeP);

    $monthlyData = [];
    foreach ($monthly as $row) {
        $m = $row['month'];
        if (!isset($monthlyData[$m])) $monthlyData[$m] = ['month'=>$m, 'borrow'=>0, 'use'=>0, 'transfer'=>0, 'return'=>0, 'dispose'=>0, 'total'=>0];
        $monthlyData[$m][$row['txn_type']] = (int)$row['cnt'];
        $monthlyData[$m]['total'] += (int)$row['cnt'];
    }

    return [
        'by_type'      => $typeSummary,
        'by_chemical'   => $byChemical,
        'recent_feed'   => $recentFeed,
        'monthly_trend' => array_values($monthlyData)
    ];
}

// ========== ACTIVITY TYPE DETAIL ==========
function activityTypeDetail(array $filters, array $user): array {
    $uid = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;
    $scope = ($isAdmin || $isManager) ? '1=1' : '(ct.from_user_id = :me1 OR ct.to_user_id = :me2 OR ct.initiated_by = :me3)';
    $params = ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid];

    $txnType = $filters['txn_type'] ?? '';
    if (!$txnType) throw new Exception('txn_type is required');
    $params[':tt'] = $txnType;

    $status = $filters['status'] ?? '';
    $statusWhere = '';
    if ($status) { $statusWhere = ' AND ct.status = :st'; $params[':st'] = $status; }

    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    $params[':lim'] = $perPage;
    $params[':off'] = $offset;

    $rows = Database::fetchAll("
        SELECT ct.id, ct.txn_number, ct.txn_type, ct.status, ct.quantity, ct.unit, ct.barcode,
               ct.created_at, ct.purpose as notes,
               COALESCE(ch.name, 'Unknown') as chemical_name, ch.cas_number,
               CONCAT(fu.first_name,' ',fu.last_name) as from_name, fu.department as from_dept,
               CONCAT(tu.first_name,' ',tu.last_name) as to_name, tu.department as to_dept
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        WHERE {$scope} AND ct.txn_type = :tt {$statusWhere}
        ORDER BY ct.created_at DESC
        LIMIT :lim OFFSET :off", $params);

    unset($params[':lim'], $params[':off']);
    $total = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_transactions ct WHERE {$scope} AND ct.txn_type = :tt {$statusWhere}", $params)['cnt'] ?? 0;

    return ['items'=>$rows, 'total'=>(int)$total, 'page'=>$page, 'pages'=>(int)ceil($total/$perPage)];
}

// ========== ACTIVITY CHEMICAL LIFECYCLE ==========
function activityChemLifecycle(array $filters, array $user): array {
    $uid = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;
    $scope = ($isAdmin || $isManager) ? '1=1' : '(ct.from_user_id = :me1 OR ct.to_user_id = :me2 OR ct.initiated_by = :me3)';
    $params = ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid];

    $chemicalId = (int)($filters['chemical_id'] ?? 0);
    if (!$chemicalId) throw new Exception('chemical_id is required');
    $params[':cid'] = $chemicalId;

    // Get chemical info
    $chem = Database::fetch("SELECT id, name, cas_number, molecular_formula as formula FROM chemicals WHERE id = :cid", [':cid'=>$chemicalId]);
    if (!$chem) {
        // Fallback: look up from chemical_stock
        $stockChem = Database::fetch("SELECT chemical_id as id, chemical_name as name, cas_no as cas_number FROM chemical_stock WHERE chemical_id = :cid LIMIT 1", [':cid'=>$chemicalId]);
        if ($stockChem) {
            $chem = $stockChem;
            $chem['formula'] = null;
        } else {
            throw new Exception('Chemical not found');
        }
    }

    // Get all transactions for this chemical, grouped by barcode
    $rows = Database::fetchAll("
        SELECT ct.id, ct.txn_number, ct.txn_type, ct.status, ct.quantity, ct.unit, ct.barcode,
               ct.created_at, ct.purpose as notes,
               CONCAT(fu.first_name,' ',fu.last_name) as from_name, fu.department as from_dept,
               CONCAT(tu.first_name,' ',tu.last_name) as to_name, tu.department as to_dept
        FROM chemical_transactions ct
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        WHERE {$scope} AND ct.chemical_id = :cid
        ORDER BY ct.barcode, ct.created_at ASC", $params);

    // Group by barcode
    $byBarcode = [];
    foreach ($rows as $r) {
        $bc = $r['barcode'] ?: 'no-barcode';
        if (!isset($byBarcode[$bc])) $byBarcode[$bc] = [];
        $byBarcode[$bc][] = $r;
    }

    return ['chemical'=>$chem, 'by_barcode'=>$byBarcode, 'total_txns'=>count($rows)];
}

// ========== ACTIVITY CHART DATA ==========
function activityChartData(array $filters, array $user): array {
    $uid = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;
    $scope = ($isAdmin || $isManager) ? '1=1' : '(ct.from_user_id = :me1 OR ct.to_user_id = :me2 OR ct.initiated_by = :me3)';
    $params = ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid];

    $mode = $filters['mode'] ?? 'month'; // day, month, year
    $chemicalId = (int)($filters['chemical_id'] ?? 0);
    $yearFilter = $filters['year'] ?? '';
    $monthFilter = $filters['month'] ?? ''; // YYYY-MM

    $chemWhere = '';
    if ($chemicalId) { $chemWhere = ' AND ct.chemical_id = :cid'; $params[':cid'] = $chemicalId; }

    $dateWhere = '';
    if ($mode === 'day' && $monthFilter) {
        $dateWhere = " AND DATE_FORMAT(ct.created_at,'%Y-%m') = :ym";
        $params[':ym'] = $monthFilter;
    } elseif ($mode === 'month' && $yearFilter) {
        $dateWhere = " AND YEAR(ct.created_at) = :yr";
        $params[':yr'] = (int)$yearFilter;
    }

    $dateFormat = match($mode) {
        'day' => "DATE_FORMAT(ct.created_at,'%Y-%m-%d')",
        'year' => "YEAR(ct.created_at)",
        default => "DATE_FORMAT(ct.created_at,'%Y-%m')"
    };

    $rows = Database::fetchAll("
        SELECT {$dateFormat} as period, ct.txn_type, COUNT(*) as cnt,
               SUM(ABS(ct.quantity)) as total_qty
        FROM chemical_transactions ct
        WHERE {$scope} {$chemWhere} {$dateWhere}
        GROUP BY period, ct.txn_type
        ORDER BY period", $params);

    // Also get drill-down detail for a specific period if requested
    $drillPeriod = $filters['drill'] ?? '';
    $drillData = [];
    if ($drillPeriod) {
        $dp = $params;
        if ($mode === 'day') {
            $drillWhere = "AND DATE(ct.created_at) = :dp";
        } elseif ($mode === 'year') {
            $drillWhere = "AND YEAR(ct.created_at) = :dp";
        } else {
            $drillWhere = "AND DATE_FORMAT(ct.created_at,'%Y-%m') = :dp";
        }
        $dp[':dp'] = $drillPeriod;
        $drillData = Database::fetchAll("
            SELECT ct.id, ct.txn_number, ct.txn_type, ct.status, ct.quantity, ct.unit, ct.barcode,
                   ct.created_at, COALESCE(ch.name,'Unknown') as chemical_name, ch.cas_number,
                   CONCAT(fu.first_name,' ',fu.last_name) as from_name,
                   CONCAT(tu.first_name,' ',tu.last_name) as to_name
            FROM chemical_transactions ct
            LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
            LEFT JOIN users fu ON fu.id = ct.from_user_id
            LEFT JOIN users tu ON tu.id = ct.to_user_id
            WHERE {$scope} {$chemWhere} {$drillWhere}
            ORDER BY ct.created_at DESC
            LIMIT 50", $dp);
    }

    // Get list of chemicals for filter
    $chemicals = Database::fetchAll("
        SELECT DISTINCT ct.chemical_id as id, COALESCE(ch.name,'Unknown') as name
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        WHERE {$scope}
        ORDER BY name LIMIT 50",
        ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid]);

    // Get available years
    $years = Database::fetchAll("
        SELECT DISTINCT YEAR(ct.created_at) as yr
        FROM chemical_transactions ct
        WHERE {$scope}
        ORDER BY yr DESC",
        ($isAdmin || $isManager) ? [] : [':me1'=>$uid, ':me2'=>$uid, ':me3'=>$uid]);

    return [
        'chart' => $rows,
        'drill' => $drillData,
        'chemicals' => $chemicals,
        'years' => array_column($years, 'yr'),
        'mode' => $mode
    ];
}

// ========== LIST TRANSACTIONS ==========

function listTransactions(array $filters, array $user): array {
    $where  = ['1=1'];
    $params = [];
    $uid    = $user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;

    if (!$isAdmin && !$isManager) {
        $where[] = "(ct.from_user_id = :me OR ct.to_user_id = :me OR ct.initiated_by = :me)";
        $params[':me'] = $uid;
    }

    if (!empty($filters['txn_type'])) {
        $types = explode(',', $filters['txn_type']);
        $tp = [];
        foreach ($types as $i => $t) { $k = ":tt{$i}"; $tp[] = $k; $params[$k] = trim($t); }
        $where[] = "ct.txn_type IN (" . implode(',', $tp) . ")";
    }
    if (!empty($filters['status'])) { $where[] = "ct.status = :st"; $params[':st'] = $filters['status']; }
    if (!empty($filters['building_id'])) { $where[] = "(ct.from_building_id = :bld OR ct.to_building_id = :bld)"; $params[':bld'] = (int)$filters['building_id']; }
    if (!empty($filters['department'])) { $where[] = "(ct.from_department LIKE :dept OR ct.to_department LIKE :dept)"; $params[':dept'] = '%'.$filters['department'].'%'; }
    if (!empty($filters['user_id'])) { $where[] = "(ct.from_user_id = :fuid OR ct.to_user_id = :fuid)"; $params[':fuid'] = (int)$filters['user_id']; }
    if (!empty($filters['barcode'])) { $where[] = "ct.barcode LIKE :bc"; $params[':bc'] = '%'.$filters['barcode'].'%'; }
    if (!empty($filters['chemical_id'])) { $where[] = "ct.chemical_id = :cid"; $params[':cid'] = (int)$filters['chemical_id']; }
    if (!empty($filters['date_from'])) { $where[] = "ct.created_at >= :dfrom"; $params[':dfrom'] = $filters['date_from'].' 00:00:00'; }
    if (!empty($filters['date_to'])) { $where[] = "ct.created_at <= :dto"; $params[':dto'] = $filters['date_to'].' 23:59:59'; }

    if (!empty($filters['tab'])) {
        switch ($filters['tab']) {
            case 'pending':
                $where[] = "ct.status = 'pending'";
                break;
            case 'active':
                $where[] = "ct.txn_type = 'borrow' AND ct.status = 'completed'";
                $where[] = "ct.id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)";
                break;
            case 'overdue':
                $where[] = "ct.txn_type = 'borrow' AND ct.status = 'completed' AND ct.expected_return_date IS NOT NULL AND ct.expected_return_date < CURDATE()";
                $where[] = "ct.id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)";
                break;
            case 'disposal':
                $where[] = "ct.txn_type = 'dispose'";
                break;
        }
    }

    $whereSQL = implode(' AND ', $where);
    $page    = max(1, (int)($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int)($filters['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $rows = Database::fetchAll("
        SELECT ct.*,
               ch.name as chemical_name, ch.cas_number,
               fu.first_name as from_first, fu.last_name as from_last, fu.department as from_dept,
               tu.first_name as to_first, tu.last_name as to_last, tu.department as to_dept,
               iu.first_name as init_first, iu.last_name as init_last,
               fb.name as from_building_name, tb.name as to_building_name
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        LEFT JOIN users iu ON iu.id = ct.initiated_by
        LEFT JOIN buildings fb ON fb.id = ct.from_building_id
        LEFT JOIN buildings tb ON tb.id = ct.to_building_id
        WHERE {$whereSQL}
        ORDER BY ct.created_at DESC
        LIMIT :lim OFFSET :off",
        array_merge($params, [':lim'=>$perPage, ':off'=>$offset]));

    $total = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_transactions ct WHERE {$whereSQL}", $params)['cnt'] ?? 0;

    return [
        'items'      => $rows,
        'pagination' => ['page'=>$page, 'per_page'=>$perPage, 'total'=>(int)$total, 'total_pages'=>(int)ceil($total / $perPage)]
    ];
}

// ========== DETAIL & TIMELINE ==========

function getDetail(int $id, array $user): array {
    $row = Database::fetch("
        SELECT ct.*,
               ch.name as chemical_name, ch.cas_number,
               fu.first_name as from_first, fu.last_name as from_last, fu.department as from_dept,
               tu.first_name as to_first, tu.last_name as to_last, tu.department as to_dept,
               iu.first_name as init_first, iu.last_name as init_last,
               au.first_name as approver_first, au.last_name as approver_last
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        LEFT JOIN users iu ON iu.id = ct.initiated_by
        LEFT JOIN users au ON au.id = ct.approved_by
        WHERE ct.id = :id", [':id'=>$id]);
    if (!$row) throw new Exception('Transaction not found');
    // For pending transactions, attach live source qty so UI can warn before user confirms
    if ($row['status'] === 'pending' && !empty($row['source_type']) && !empty($row['source_id'])) {
        try {
            $src = getSourceInfo($row['source_type'], (int)$row['source_id']);
            $row['source_remaining_qty'] = (float)$src['remaining_qty'];
        } catch (\Exception $e) {
            $row['source_remaining_qty'] = null;
        }
    }
    return $row;
}

function getTimeline(array $filters): array {
    $barcode = $filters['barcode'] ?? '';
    if (!$barcode) throw new Exception('barcode is required');
    return Database::fetchAll("
        SELECT ct.*, ch.name as chemical_name,
               fu.first_name as from_first, fu.last_name as from_last,
               tu.first_name as to_first, tu.last_name as to_last,
               iu.first_name as init_first, iu.last_name as init_last
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        LEFT JOIN users tu ON tu.id = ct.to_user_id
        LEFT JOIN users iu ON iu.id = ct.initiated_by
        WHERE ct.barcode = :bc ORDER BY ct.created_at ASC", [':bc'=>$barcode]);
}

// ========== SEARCH HELPERS ==========

function searchItems(array $filters, array $user): array {
    $q = trim($filters['q'] ?? '');
    if (strlen($q) < 1) return [];
    // Escape special LIKE characters to prevent wildcard injection
    $escapedQ = str_replace(['%', '_'], ['\\%', '\\_'], $q);
    $like = "%{$escapedQ}%";
    $ownerOnly = !empty($filters['owner_only']);
    $searchType = $filters['type'] ?? 'all'; // 'all', 'borrow', 'use', 'transfer'
    $uid = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;

    // For 'use' mode: always show only user's own items
    // For 'transfer' mode: regular users see only their own, admin/manager can see all
    // For 'borrow' mode: show all available items (from other users or shared stock)
    $showAllItems = ($searchType === 'borrow') || 
                    ($searchType === 'transfer' && ($isAdmin || $isManager));
    
    $containerExtra = "";
    $stockExtra = "";
    $params = [':q1'=>$like, ':q2'=>$like, ':q3'=>$like, ':q4'=>$like];
    
    if (!$showAllItems || $ownerOnly) {
        $containerExtra = " AND c.owner_id = :uid";
        $stockExtra = " AND cs.owner_user_id = :uid";
        $params[':uid'] = $uid;
    }

    // Only show active/available items (not disposed, not already fully borrowed)
    $containerExtra .= " AND c.status IN ('active','empty') AND c.current_quantity > 0";
    $stockExtra .= " AND cs.status IN ('active','low') AND cs.remaining_qty > 0";

    $containers = Database::fetchAll("
        SELECT 'container' as source_type, c.id as source_id,
               c.qr_code as barcode, c.chemical_id,
               ch.name as chemical_name, ch.cas_number,
               c.current_quantity as remaining_qty, c.quantity_unit as unit,
               c.owner_id, c.status,
               CONCAT(u.first_name,' ',u.last_name) as owner_name, u.department
        FROM containers c
        JOIN chemicals ch ON ch.id = c.chemical_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE (c.qr_code LIKE :q1 OR ch.name LIKE :q2 OR ch.cas_number LIKE :q3)
          {$containerExtra}
        LIMIT 15", $params);

    $stocks = Database::fetchAll("
        SELECT 'stock' as source_type, cs.id as source_id,
               cs.bottle_code as barcode, cs.chemical_id,
               COALESCE(ch.name, cs.chemical_name) as chemical_name,
               COALESCE(ch.cas_number, cs.cas_no) as cas_number,
               cs.remaining_qty, cs.unit,
               cs.owner_user_id as owner_id, cs.status,
               cs.owner_name, COALESCE(u.department,'') as department
        FROM chemical_stock cs
        LEFT JOIN chemicals ch ON ch.id = cs.chemical_id
        LEFT JOIN users u ON u.id = cs.owner_user_id
        WHERE (cs.bottle_code LIKE :q1 OR cs.chemical_name LIKE :q2 OR ch.name LIKE :q3 OR cs.cas_no LIKE :q4)
          {$stockExtra}
        LIMIT 15", $params);

    // Combine and add ownership info
    $results = array_merge($containers, $stocks);
    
    // Mark items as "canBorrow" for borrow mode
    foreach ($results as &$item) {
        $item['is_owner'] = ((int)$item['owner_id'] === $uid);
        $item['can_borrow'] = !$item['is_owner'] || $showAllItems;
    }
    
    return $results;
}

function searchUsers(array $filters): array {
    $q = trim($filters['q'] ?? '');
    if (strlen($q) < 1) return [];
    // Escape special LIKE characters to prevent wildcard injection
    $escapedQ = str_replace(['%', '_'], ['\\%', '\\_'], $q);
    $like = "%{$escapedQ}%";
    return Database::fetchAll("
        SELECT id, username, first_name, last_name, department, avatar_url,
               CONCAT(first_name,' ',last_name) as display_name
        FROM users WHERE is_active = 1
          AND (first_name LIKE :q1 OR last_name LIKE :q2 OR username LIKE :q3 OR department LIKE :q4)
        ORDER BY first_name LIMIT 20",
        [':q1'=>$like, ':q2'=>$like, ':q3'=>$like, ':q4'=>$like]);
}

// ========== SCAN BARCODE (smart lookup) ==========

function scanBarcode(array $filters, array $user): array {
    $barcode = trim($filters['barcode'] ?? '');
    if (!$barcode) throw new Exception('Barcode is required');

    $uid = (int)$user['id'];

    // 1. Search containers by qr_code
    $item = Database::fetch("
        SELECT 'container' as source_type, c.id as source_id,
               c.qr_code as barcode, c.chemical_id,
               ch.name as chemical_name, ch.cas_number,
               c.current_quantity as remaining_qty, c.quantity_unit as unit,
               c.owner_id, c.status,
               CONCAT(u.first_name,' ',u.last_name) as owner_name, u.department
        FROM containers c
        JOIN chemicals ch ON ch.id = c.chemical_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE c.qr_code = :bc AND c.status IN ('active','empty')
        LIMIT 1", [':bc' => $barcode]);

    // 2. If not found in containers, search chemical_stock by bottle_code
    if (!$item) {
        $item = Database::fetch("
            SELECT 'stock' as source_type, cs.id as source_id,
                   cs.bottle_code as barcode, cs.chemical_id,
                   COALESCE(ch.name, cs.chemical_name) as chemical_name,
                   COALESCE(ch.cas_number, cs.cas_no) as cas_number,
                   cs.remaining_qty, cs.unit,
                   cs.owner_user_id as owner_id, cs.status,
                   cs.owner_name, COALESCE(u.department,'') as department
            FROM chemical_stock cs
            LEFT JOIN chemicals ch ON ch.id = cs.chemical_id
            LEFT JOIN users u ON u.id = cs.owner_user_id
            WHERE cs.bottle_code = :bc AND cs.status IN ('active','low')
            LIMIT 1", [':bc' => $barcode]);
    }

    if (!$item) {
        throw new Exception('ไม่พบสารเคมีที่ตรงกับ Barcode: ' . $barcode);
    }

    // 3. Determine relation: owner, borrower, or other
    $ownerId = (int)($item['owner_id'] ?? 0);
    $relation = 'other';
    if ($ownerId === $uid) {
        $relation = 'owner';
    }

    // 4. Check if user currently has an active borrow for this item
    $activeBorrow = null;
    $barcodeForQuery = $item['barcode'] ?? '';
    if ($barcodeForQuery) {
        $activeBorrow = Database::fetch("
            SELECT ct.id, ct.quantity, ct.unit, ct.created_at, ct.expected_return_date,
                   ct.txn_number, ct.barcode
            FROM chemical_transactions ct
            WHERE ct.txn_type = 'borrow'
              AND ct.to_user_id = :uid
              AND ct.barcode = :bc
              AND ct.status = 'completed'
              AND ct.id NOT IN (
                  SELECT COALESCE(parent_txn_id, 0)
                  FROM chemical_transactions
                  WHERE txn_type = 'return' AND status = 'completed'
                    AND parent_txn_id IS NOT NULL
              )
            ORDER BY ct.created_at DESC
            LIMIT 1", [':uid' => $uid, ':bc' => $barcodeForQuery]);
    }

    if ($activeBorrow && $relation !== 'owner') {
        $relation = 'borrower';
    }

    return [
        'item' => $item,
        'relation' => $relation,
        'active_borrow' => $activeBorrow
    ];
}

// ========== MY ACTIVE ==========

function myActive(array $user): array {
    return Database::fetchAll("
        SELECT ct.*, ch.name as chemical_name, ch.cas_number,
               fu.first_name as from_first, fu.last_name as from_last
        FROM chemical_transactions ct
        LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
        LEFT JOIN users fu ON fu.id = ct.from_user_id
        WHERE ct.txn_type='borrow' AND ct.to_user_id = :uid AND ct.status='completed'
          AND ct.id NOT IN (SELECT COALESCE(parent_txn_id,0) FROM chemical_transactions WHERE txn_type='return' AND status='completed' AND parent_txn_id IS NOT NULL)
        ORDER BY ct.created_at DESC", [':uid'=>$user['id']]);
}

// ========== CREATE BORROW ==========

function createBorrow(array $data, array $user): array {
    $sourceType = $data['source_type'] ?? '';
    $sourceId   = (int)($data['source_id'] ?? 0);
    $qty        = (float)($data['quantity'] ?? 0);
    $purpose    = trim($data['purpose'] ?? '');
    $returnDate = $data['expected_return_date'] ?? null;
    $toUserId   = (int)($data['to_user_id'] ?? ($user['id'] ?? 0));

    if (!$sourceType || !$sourceId) throw new Exception('source_type and source_id required');
    if ($qty <= 0) throw new Exception('quantity must be > 0');

    $src = getSourceInfo($sourceType, $sourceId);
    if ((float)$src['remaining_qty'] < $qty) {
        throw new Exception("ปริมาณไม่เพียงพอ มีเหลือ: {$src['remaining_qty']} {$src['unit']}");
    }

    $ownerId  = (int)($src['owner_id'] ?? 0);
    $currentUserId = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $isAdmin = $roleLevel >= 5;
    $isManager = $roleLevel >= 3;
    
    $newBalance = (float)$src['remaining_qty'] - $qty;
    $txnNum = genTxnNumber();
    
    // Borrowing logic:
    // - If borrowing from self: no approval needed
    // - If borrowing from others: requires approval from owner
    // - If owner_id is null/0 (shared/unassigned stock): no approval needed for managers+, require approval for regular users borrowing from pool
    // - Admin/Manager can borrow without approval from anyone
    $needApproval = 0;
    
    if ($ownerId === $currentUserId) {
        // Borrowing from self - no approval needed
        $needApproval = 0;
    } elseif ($ownerId === 0) {
        // Borrowing from shared/unassigned stock
        // Regular users need approval, managers+ don't
        $needApproval = ($isAdmin || $isManager) ? 0 : 1;
    } else {
        // Borrowing from another user - need approval
        $needApproval = ($isAdmin || $isManager) ? 0 : 1;
    }

    $txnData = [
        'txn_number'=>$txnNum, 'source_type'=>$sourceType, 'source_id'=>$sourceId,
        'chemical_id'=>(int)$src['chemical_id'], 'barcode'=>$src['barcode']??'',
        'txn_type'=>'borrow', 'from_user_id'=>$ownerId ?: null, 'to_user_id'=>$toUserId,
        'initiated_by'=>$currentUserId, 'quantity'=>$qty, 'unit'=>$src['unit']??$data['unit']??'mL',
        'balance_after'=>$newBalance, 'purpose'=>$purpose?:null,
        'project_name'=>$data['project_name']??null,
        'from_building_id'=>getUserBuilding($ownerId ?: $currentUserId), 'from_department'=>$src['department']??null,
        'to_building_id'=>getUserBuilding($toUserId), 'to_department'=>$data['to_department']??null,
        'requires_approval'=>$needApproval, 'status'=>$needApproval?'pending':'completed',
        'expected_return_date'=>$returnDate
    ];

    $id = Database::insert('chemical_transactions', $txnData);
    if ($txnData['status'] === 'completed') {
        updateSourceQty($sourceType, $sourceId, $newBalance);
    } elseif ($needApproval) {
        // Notify the approver about the pending borrow request
        $requesterName = trim(($user['full_name_th'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'ผู้ใช้';
        $chemName = $src['chemical_name'] ?? 'สารเคมี';
        $alertBase = [
            'alert_type'       => 'borrow_request',
            'severity'         => 'warning',
            'title'            => "คำขอยืม: {$chemName}",
            'message'          => "{$requesterName} ขอยืม {$qty} {$src['unit']}" . ($purpose ? " — {$purpose}" : ''),
            'chemical_id'      => (int)$src['chemical_id'],
            'borrow_request_id'=> $id,
            'action_required'  => 1
        ];
        if ($ownerId > 0) {
            notifyAlert(array_merge($alertBase, ['user_id' => $ownerId]));
        } else {
            // Shared/unassigned pool: notify all admin/manager users
            try {
                $admins = Database::fetchAll(
                    "SELECT id FROM users WHERE role_level >= 4 AND id != :uid LIMIT 10",
                    [':uid' => $currentUserId]
                );
                foreach ($admins as $admin) {
                    notifyAlert(array_merge($alertBase, ['user_id' => (int)$admin['id']]));
                }
            } catch (\Throwable $e) { /* silent */ }
        }
    }
    return ['id'=>$id, 'txn_number'=>$txnNum, 'status'=>$txnData['status']];
}

// ========== CREATE USE (owner consumes own stock) ==========

function createUse(array $data, array $user): array {
    $sourceType = $data['source_type'] ?? '';
    $sourceId   = (int)($data['source_id'] ?? 0);
    $qty        = (float)($data['quantity'] ?? 0);
    $purpose    = trim($data['purpose'] ?? '');

    if (!$sourceType || !$sourceId) throw new Exception('source_type and source_id required');
    if ($qty <= 0) throw new Exception('quantity must be > 0');

    $src = getSourceInfo($sourceType, $sourceId);
    $ownerId = (int)($src['owner_id'] ?? 0);

    // Only owner (or admin) can use their own stock
    if ($ownerId !== (int)$user['id'] && ((int)($user['role_level'] ?? $user['level'] ?? 0)) < 5) {
        throw new Exception('คุณไม่ใช่เจ้าของสารนี้ — ไม่สามารถเบิกใช้ได้');
    }

    if ((float)$src['remaining_qty'] < $qty) {
        throw new Exception("ปริมาณไม่เพียงพอ มีเหลือ: {$src['remaining_qty']} {$src['unit']}");
    }

    $newBalance = (float)$src['remaining_qty'] - $qty;
    $txnNum = genTxnNumber();

    $id = Database::insert('chemical_transactions', [
        'txn_number'=>$txnNum, 'source_type'=>$sourceType, 'source_id'=>$sourceId,
        'chemical_id'=>(int)$src['chemical_id'], 'barcode'=>$src['barcode']??'',
        'txn_type'=>'use', 'from_user_id'=>(int)$user['id'], 'to_user_id'=>(int)$user['id'],
        'initiated_by'=>(int)$user['id'], 'quantity'=>$qty, 'unit'=>$src['unit']??$data['unit']??'mL',
        'balance_after'=>$newBalance, 'purpose'=>$purpose?:null,
        'from_building_id'=>getUserBuilding((int)$user['id']),
        'from_department'=>$src['department']??null,
        'to_building_id'=>getUserBuilding((int)$user['id']),
        'to_department'=>$src['department']??null,
        'requires_approval'=>0, 'status'=>'completed'
    ]);

    updateSourceQty($sourceType, $sourceId, $newBalance);
    return ['id'=>$id, 'txn_number'=>$txnNum, 'status'=>'completed'];
}

// ========== CREATE RETURN ==========

function createReturn(array $data, array $user): array {
    $borrowTxnId = (int)($data['borrow_txn_id'] ?? 0);
    $qty         = (float)($data['quantity'] ?? 0);
    $condition   = $data['return_condition'] ?? 'good';
    $notes       = trim($data['notes'] ?? '');

    if (!$borrowTxnId) throw new Exception('borrow_txn_id required');

    $borrow = Database::fetch("SELECT * FROM chemical_transactions WHERE id = :id AND txn_type='borrow'", [':id'=>$borrowTxnId]);
    if (!$borrow) throw new Exception('Borrow transaction not found');

    $borrowerId = (int)($borrow['to_user_id'] ?? 0);
    
    $currentUserId = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    
    // Handle case where to_user_id was null - require admin for orphan records
    if ($borrowerId === 0 && $roleLevel < 3) {
        throw new Exception('Cannot return: borrow record has no valid assignee');
    }
    
    $currentUserId = (int)$user['id'];
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    
    // Only the borrower or admin/manager can initiate a return
    if ($borrowerId !== $currentUserId && $roleLevel < 3) {
        throw new Exception('คุณไม่ใช่ผู้ยืมสารนี้ ไม่สามารถคืนได้');
    }

    $existing = Database::fetch("SELECT id FROM chemical_transactions WHERE parent_txn_id = :pid AND txn_type='return' AND status='completed'", [':pid'=>$borrowTxnId]);
    if ($existing) throw new Exception('รายการยืมนี้ถูกคืนไปแล้ว');

    // Check if partially returned
    $partialReturn = Database::fetch("SELECT SUM(quantity) as total_returned FROM chemical_transactions WHERE parent_txn_id = :pid AND txn_type='return'", [':pid'=>$borrowTxnId]);
    $alreadyReturned = (float)($partialReturn['total_returned'] ?? 0);
    $originalQty = (float)$borrow['quantity'];
    $maxReturnQty = $originalQty - $alreadyReturned;

    if ($qty <= 0) $qty = $maxReturnQty;
    if ($qty > $maxReturnQty) {
        throw new Exception("ปริมาณคืนเกินกว่าที่ยืมไป สามารถคืนได้สูงสุด: {$maxReturnQty} {$borrow['unit']}");
    }

    $src = getSourceInfo($borrow['source_type'], (int)$borrow['source_id']);
    $newBalance = (float)$src['remaining_qty'] + $qty;
    $txnNum = genTxnNumber();

    $id = Database::insert('chemical_transactions', [
        'txn_number'=>$txnNum, 'source_type'=>$borrow['source_type'],
        'source_id'=>(int)$borrow['source_id'], 'chemical_id'=>(int)$borrow['chemical_id'],
        'barcode'=>$borrow['barcode'], 'txn_type'=>'return',
        'from_user_id'=>$borrowerId, 'to_user_id'=>(int)$borrow['from_user_id'],
        'initiated_by'=>$currentUserId, 'quantity'=>$qty, 'unit'=>$borrow['unit'],
        'balance_after'=>$newBalance, 'parent_txn_id'=>$borrowTxnId,
        'return_condition'=>$condition, 'purpose'=>$notes?:null,
        'status'=>'completed'
    ]);

    updateSourceQty($borrow['source_type'], (int)$borrow['source_id'], $newBalance);

    return ['id'=>$id, 'txn_number'=>$txnNum, 'status'=>'completed', 'returned_qty'=>$qty];
}

// ========== CREATE TRANSFER ==========

function createTransfer(array $data, array $user): array {
    $sourceType  = $data['source_type'] ?? '';
    $sourceId    = (int)($data['source_id'] ?? 0);
    $toUserId    = (int)($data['to_user_id'] ?? 0);

    if (!$sourceType || !$sourceId || !$toUserId) throw new Exception('source_type, source_id, to_user_id required');

    $src = getSourceInfo($sourceType, $sourceId);
    $ownerId = (int)($src['owner_id'] ?? 0);

    // Permission check: only owner can transfer, unless lab_manager (role>=3) or admin (role>=5)
    $isOwner = ($ownerId === (int)$user['id']);
    $isPrivileged = ((int)($user['role_level'] ?? 0) >= 3);
    if (!$isOwner && !$isPrivileged) {
        throw new Exception('คุณไม่มีสิทธิ์โอนสารเคมีของผู้อื่น เฉพาะเจ้าของสาร หัวหน้าห้องปฏิบัติการ หรือผู้ดูแลระบบเท่านั้น');
    }

    // Transfer is ALWAYS whole-bottle and ALWAYS requires recipient acceptance
    $qty = (float)$src['remaining_qty'];
    if ($qty <= 0) throw new Exception("ปริมาณคงเหลือเป็นศูนย์ ไม่สามารถโอนได้");

    $newBalance  = 0.0;
    $txnNum      = genTxnNumber();
    $purposeText = $data['purpose'] ?? null;

    $txnRow = [
        'txn_number'      => $txnNum,
        'source_type'     => $sourceType,
        'source_id'       => $sourceId,
        'chemical_id'     => (int)$src['chemical_id'],
        'barcode'         => $src['barcode'] ?? '',
        'txn_type'        => 'transfer',
        'from_user_id'    => $ownerId,
        'to_user_id'      => $toUserId,
        'initiated_by'    => $user['id'],
        'quantity'        => $qty,
        'unit'            => $src['unit'] ?? $data['unit'] ?? 'mL',
        'balance_after'   => $newBalance,
        'purpose'         => $purposeText,
        'from_building_id'=> getUserBuilding($ownerId),
        'from_department' => $src['department'] ?? null,
        'to_building_id'  => getUserBuilding($toUserId),
        'to_department'   => $data['to_department'] ?? null,
        'requires_approval'=> 1,
        'status'          => 'pending',
    ];
    $id = Database::insert('chemical_transactions', $txnRow);

    // Mark as whole_bottle in approval_notes so ownership changes on acceptance
    try {
        Database::query(
            "UPDATE chemical_transactions SET approval_notes = 'whole_bottle' WHERE id = :id",
            [':id' => $id]
        );
    } catch (\Exception $e) { /* column may not exist yet — safe to skip */ }

    // Notify RECIPIENT — must accept to receive ownership
    $chemName     = $src['chemical_name'] ?? 'สารเคมี';
    $initiatorName = trim(($user['full_name_th'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'ผู้โอน';
    notifyAlert([
        'alert_type'        => 'borrow_request',
        'severity'          => 'info',
        'title'             => 'คำขอโอนกรรมสิทธิ์สาร',
        'message'           => "{$chemName} {$qty} {$src['unit']} — โอนจาก {$initiatorName} รอการยืนยันจากคุณ",
        'user_id'           => $toUserId,
        'chemical_id'       => (int)$src['chemical_id'],
        'borrow_request_id' => $id,
        'action_required'   => 1,
    ]);

    // Notify INITIATOR — transfer request sent
    $recipientRow = Database::fetch("SELECT first_name, last_name FROM users WHERE id = :id", [':id' => $toUserId]);
    $recipientName = $recipientRow ? trim("{$recipientRow['first_name']} {$recipientRow['last_name']}") : 'ผู้รับ';
    notifyAlert([
        'alert_type'        => 'borrow_request',
        'severity'          => 'info',
        'title'             => 'ส่งคำขอโอนสารแล้ว',
        'message'           => "{$chemName} {$qty} {$src['unit']} — รอ {$recipientName} ยืนยันรับโอน",
        'user_id'           => (int)$user['id'],
        'chemical_id'       => (int)$src['chemical_id'],
        'borrow_request_id' => $id,
        'action_required'   => 0,
    ]);

    return ['id' => $id, 'txn_number' => $txnNum, 'status' => 'pending', 'whole_bottle' => true];
}

// ========== CREATE DISPOSE ==========

function createDispose(array $data, array $user): array {
    $sourceType = $data['source_type'] ?? '';
    $sourceId   = (int)($data['source_id'] ?? 0);
    $reason     = trim($data['disposal_reason'] ?? '');
    $method     = trim($data['disposal_method'] ?? '');

    if (!$sourceType || !$sourceId) throw new Exception('source_type and source_id required');
    $src = getSourceInfo($sourceType, $sourceId);

    $txnNum = genTxnNumber();
    $txnId = Database::insert('chemical_transactions', [
        'txn_number'=>$txnNum, 'source_type'=>$sourceType, 'source_id'=>$sourceId,
        'chemical_id'=>(int)$src['chemical_id'], 'barcode'=>$src['barcode']??'',
        'txn_type'=>'dispose', 'from_user_id'=>(int)($src['owner_id']??$user['id']),
        'to_user_id'=>null, 'initiated_by'=>$user['id'],
        'quantity'=>(float)$src['remaining_qty'], 'unit'=>$src['unit']??'mL',
        'balance_after'=>0, 'disposal_reason'=>$reason, 'disposal_method'=>$method,
        'status'=>'completed'
    ]);

    Database::insert('disposal_bin', [
        'source_type'=>$sourceType, 'source_id'=>$sourceId,
        'chemical_id'=>(int)$src['chemical_id'], 'barcode'=>$src['barcode']??'',
        'chemical_name'=>$src['chemical_name']??'',
        'remaining_qty'=>(float)$src['remaining_qty'], 'unit'=>$src['unit']??'',
        'disposed_by'=>$user['id'], 'disposal_reason'=>$reason, 'disposal_method'=>$method,
        'owner_name'=>trim(($src['first_name']??'').' '.($src['last_name']??''))?:($src['owner_name']??''),
        'department'=>$src['department']??'', 'building_name'=>$src['building_name']??'',
        'storage_location'=>$src['building_name']??'',
        'status'=>'pending', 'txn_id'=>$txnId
    ]);

    if ($sourceType === 'container') {
        Database::update('containers', ['status'=>'disposed','current_quantity'=>0], 'id = :id', [':id'=>$sourceId]);
    } else {
        Database::update('chemical_stock', ['status'=>'disposed','remaining_qty'=>0], 'id = :id', [':id'=>$sourceId]);
    }
    return ['id'=>$txnId, 'txn_number'=>$txnNum];
}

// ========== APPROVE / REJECT ==========

function approveTxn(array $data, array $user): array {
    $txnId = (int)($data['txn_id'] ?? 0);
    if (!$txnId) throw new Exception('txn_id required');

    $txn = Database::fetch("SELECT * FROM chemical_transactions WHERE id = :id AND status='pending'", [':id'=>$txnId]);
    if (!$txn) throw new Exception('Pending transaction not found');

    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $currentUserId = (int)$user['id'];

    $ownerId     = (int)$txn['from_user_id'];
    $recipientId = (int)$txn['to_user_id'];
    $isTransfer  = ($txn['txn_type'] === 'transfer');

    // Transfer: RECIPIENT accepts; Borrow: OWNER or admin approves
    if ($isTransfer) {
        if ($roleLevel < 3 && $recipientId !== $currentUserId) {
            throw new Exception('เฉพาะผู้รับโอนหรือผู้ดูแลระบบเท่านั้นที่สามารถยืนยันรับโอนได้');
        }
    } else {
        if ($roleLevel < 3 && $ownerId !== $currentUserId) {
            throw new Exception('Permission denied - only the chemical owner or manager can approve');
        }
    }

    $src = getSourceInfo($txn['source_type'], (int)$txn['source_id']);
    $qty = (float)$txn['quantity'];
    if ((float)$src['remaining_qty'] < $qty) {
        // Auto-reject: stock exhausted by a prior approval — clean up queue
        $autoNote = "ยกเลิกอัตโนมัติ: ปริมาณคงเหลือไม่เพียงพอ ({$src['remaining_qty']} {$src['unit']}) — หรือมีการอนุมัติรายการอื่นไปแล้ว";
        Database::update('chemical_transactions', [
            'status'         => 'rejected',
            'approved_by'    => $currentUserId,
            'approved_at'    => date('Y-m-d H:i:s'),
            'approval_notes' => $autoNote,
        ], 'id = :id', [':id' => $txnId]);
        $reqId = (int)($txn['initiated_by'] ?: $txn['to_user_id']);
        if ($reqId && $reqId !== $currentUserId) {
            $chemNm = $src['chemical_name'] ?? 'สารเคมี';
            notifyAlert([
                'alert_type'        => 'borrow_request',
                'severity'          => 'warning',
                'title'             => 'คำขอยืมถูกยกเลิกอัตโนมัติ',
                'message'           => "{$chemNm} — ปริมาณคงเหลือไม่เพียงพอ ({$src['remaining_qty']} {$src['unit']})",
                'user_id'           => $reqId,
                'chemical_id'       => (int)$txn['chemical_id'],
                'borrow_request_id' => $txnId,
                'action_required'   => 0,
            ]);
        }
        // Return 200 (not 400) so the browser console stays clean; frontend detects auto_rejected flag
        return [
            'auto_rejected' => true,
            'txn_id'        => $txnId,
            'remaining_qty' => (float)$src['remaining_qty'],
            'unit'          => $src['unit'] ?? '',
        ];
    }

    $newBalance = (float)$src['remaining_qty'] - $qty;
    $approveData = [
        'status'=>'completed', 'approved_by'=>$currentUserId,
        'approved_at'=>date('Y-m-d H:i:s'), 'balance_after'=>$newBalance
    ];
    Database::update('chemical_transactions', $approveData, 'id = :id', [':id'=>$txnId]);
    // Store reviewer notes separately (tolerates DB schemas without approval_notes column)
    if (!empty($data['notes'])) {
        try {
            Database::query(
                "UPDATE chemical_transactions SET approval_notes = :n WHERE id = :id",
                [':n'=>$data['notes'], ':id'=>$txnId]
            );
        } catch (\Exception $e) { /* column may not exist */ }
    }

    updateSourceQty($txn['source_type'], (int)$txn['source_id'], $newBalance);

    // Handle ownership transfer — only when whole_bottle flag was set at create time
    if ($txn['txn_type'] === 'transfer' && ($txn['approval_notes'] ?? '') === 'whole_bottle') {
        $toUserId = (int)$txn['to_user_id'];
        if ($txn['source_type'] === 'container') {
            Database::update('containers', ['owner_id'=>$toUserId], 'id = :id', [':id'=>$txn['source_id']]);
        } else {
            $tu = Database::fetch("SELECT first_name, last_name FROM users WHERE id = :id", [':id'=>$toUserId]);
            Database::update('chemical_stock', [
                'owner_user_id' => $toUserId,
                'owner_name'    => $tu ? "{$tu['first_name']} {$tu['last_name']}" : ''
            ], 'id = :id', [':id'=>$txn['source_id']]);
        }
    }

    $chemName    = $src['chemical_name'] ?? 'สารเคมี';
    $actorName   = trim(($user['full_name_th'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'ผู้ดูแล';

    if ($isTransfer) {
        // Notify INITIATOR that recipient accepted ownership
        $notifyUserId = (int)($txn['initiated_by'] ?: $txn['from_user_id']);
        if ($notifyUserId && $notifyUserId !== $currentUserId) {
            notifyAlert([
                'alert_type'        => 'borrow_request',
                'severity'          => 'info',
                'title'             => 'ยืนยันรับโอนสารแล้ว',
                'message'           => "{$chemName} {$txn['quantity']} {$txn['unit']} — {$actorName} ยืนยันรับโอนกรรมสิทธิ์แล้ว",
                'user_id'           => $notifyUserId,
                'chemical_id'       => (int)$txn['chemical_id'],
                'borrow_request_id' => $txnId,
                'action_required'   => 0,
            ]);
        }
    } else {
        // Notify the requester that their borrow was approved
        $borrowerId = (int)($txn['initiated_by'] ?: $txn['to_user_id']);
        if ($borrowerId && $borrowerId !== $currentUserId) {
            notifyAlert([
                'alert_type'        => 'borrow_request',
                'severity'          => 'info',
                'title'             => 'คำขอยืมได้รับการอนุมัติ',
                'message'           => "{$chemName} {$txn['quantity']} {$txn['unit']} — อนุมัติโดย {$actorName}",
                'user_id'           => $borrowerId,
                'chemical_id'       => (int)$txn['chemical_id'],
                'borrow_request_id' => $txnId,
                'action_required'   => 0,
            ]);
        }
    }
    return ['txn_id'=>$txnId, 'status'=>'completed'];
}

function rejectTxn(array $data, array $user): array {
    $txnId = (int)($data['txn_id'] ?? 0);
    if (!$txnId) throw new Exception('txn_id required');

    $txn = Database::fetch("SELECT * FROM chemical_transactions WHERE id = :id AND status='pending'", [':id'=>$txnId]);
    if (!$txn) throw new Exception('Pending transaction not found');

    $roleLevel     = (int)($user['role_level'] ?? $user['level'] ?? 0);
    $currentUserId = (int)$user['id'];
    $isTransfer    = ($txn['txn_type'] === 'transfer');
    $ownerId       = (int)$txn['from_user_id'];
    $recipientId   = (int)$txn['to_user_id'];

    $initiatorId = (int)($txn['initiated_by'] ?? 0);
    $isInitiator = ($initiatorId === $currentUserId || $ownerId === $currentUserId);

    if ($isTransfer) {
        if ($roleLevel < 3 && $recipientId !== $currentUserId && !$isInitiator) {
            throw new Exception('เฉพาะผู้รับโอน เจ้าของสาร หรือผู้ดูแลระบบเท่านั้นที่สามารถดำเนินการได้');
        }
    } else {
        if ($roleLevel < 3 && $ownerId !== $currentUserId) {
            throw new Exception('Permission denied');
        }
    }

    // Initiator cancelling = 'cancelled'; recipient/admin rejecting = 'rejected'
    $newStatus = ($isTransfer && $isInitiator && $roleLevel < 3) ? 'cancelled' : 'rejected';

    Database::update('chemical_transactions', [
        'status'=>$newStatus, 'approved_by'=>$user['id'],
        'approved_at'=>date('Y-m-d H:i:s')
    ], 'id = :id', [':id'=>$txnId]);
    if (!empty($data['reason'])) {
        try {
            Database::query(
                "UPDATE chemical_transactions SET approval_notes = :n WHERE id = :id",
                [':n'=>$data['reason'], ':id'=>$txnId]
            );
        } catch (\Exception $e) { /* column may not exist */ }
    }

    $src = getSourceInfo($txn['source_type'], (int)$txn['source_id']);
    $chemName     = $src['chemical_name'] ?? 'สารเคมี';
    $actorName    = trim(($user['full_name_th'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: 'ผู้ดูแล';
    $reasonSuffix = !empty($data['reason']) ? " — {$data['reason']}" : '';

    if ($isTransfer) {
        if ($isInitiator && $roleLevel < 3) {
            // Initiator cancelled — notify RECIPIENT
            if ($recipientId && $recipientId !== $currentUserId) {
                notifyAlert([
                    'alert_type'        => 'borrow_request',
                    'severity'          => 'warning',
                    'title'             => 'ยกเลิกคำขอโอนสาร',
                    'message'           => "{$chemName} {$txn['quantity']} {$txn['unit']} — {$actorName} ยกเลิกคำขอโอนกรรมสิทธิ์",
                    'user_id'           => $recipientId,
                    'chemical_id'       => (int)$txn['chemical_id'],
                    'borrow_request_id' => $txnId,
                    'action_required'   => 0,
                ]);
            }
        } else {
            // Recipient (or admin) rejected — notify INITIATOR
            $notifyUserId = (int)($txn['initiated_by'] ?: $ownerId);
            if ($notifyUserId && $notifyUserId !== $currentUserId) {
                notifyAlert([
                    'alert_type'        => 'borrow_request',
                    'severity'          => 'warning',
                    'title'             => 'ปฏิเสธการรับโอนสาร',
                    'message'           => "{$chemName} {$txn['quantity']} {$txn['unit']} — {$actorName} ปฏิเสธรับโอนกรรมสิทธิ์{$reasonSuffix}",
                    'user_id'           => $notifyUserId,
                    'chemical_id'       => (int)$txn['chemical_id'],
                    'borrow_request_id' => $txnId,
                    'action_required'   => 0,
                ]);
            }
        }
    } else {
        $borrowerId = (int)($txn['initiated_by'] ?: $txn['to_user_id']);
        if ($borrowerId) {
            notifyAlert([
                'alert_type'        => 'borrow_request',
                'severity'          => 'warning',
                'title'             => 'คำขอยืมถูกปฏิเสธ',
                'message'           => "{$chemName} {$txn['quantity']} {$txn['unit']}{$reasonSuffix}",
                'user_id'           => $borrowerId,
                'chemical_id'       => (int)$txn['chemical_id'],
                'borrow_request_id' => $txnId,
                'action_required'   => 0,
            ]);
        }
    }
    return ['txn_id'=>$txnId, 'status'=>'rejected'];
}

// ========== DISPOSAL BIN ==========

function listDisposalBin(array $filters, array $user): array {
    $where  = ['1=1'];
    $params = [];
    if (!empty($filters['status'])) { $where[] = "db.status = :st"; $params[':st'] = $filters['status']; }
    else if (empty($filters['show_all'])) { $where[] = "db.status IN ('pending','approved')"; }
    if (!empty($filters['building'])) { $where[] = "db.building_name LIKE :bn"; $params[':bn'] = '%'.$filters['building'].'%'; }
    if (!empty($filters['department'])) { $where[] = "db.department LIKE :dp"; $params[':dp'] = '%'.$filters['department'].'%'; }
    if (!empty($filters['disposed_by'])) { $where[] = "db.disposed_by = :dby"; $params[':dby'] = (int)$filters['disposed_by']; }
    if (!empty($filters['reason'])) { $where[] = "db.disposal_reason LIKE :rsn"; $params[':rsn'] = '%'.$filters['reason'].'%'; }
    if (!empty($filters['search'])) { $like = '%'.$filters['search'].'%'; $where[] = "(db.chemical_name LIKE :sq1 OR db.barcode LIKE :sq2 OR db.owner_name LIKE :sq3)"; $params[':sq1'] = $like; $params[':sq2'] = $like; $params[':sq3'] = $like; }
    if (!empty($filters['date_from'])) { $where[] = "db.created_at >= :dfrom"; $params[':dfrom'] = $filters['date_from'].' 00:00:00'; }
    if (!empty($filters['date_to'])) { $where[] = "db.created_at <= :dto"; $params[':dto'] = $filters['date_to'].' 23:59:59'; }

    $whereSQL = implode(' AND ', $where);

    $page    = max(1, (int)($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int)($filters['per_page'] ?? 30)));
    $offset  = ($page - 1) * $perPage;

    $items = Database::fetchAll("
        SELECT db.*, u.first_name as disposed_first, u.last_name as disposed_last, u.department as user_dept,
               au.first_name as approver_first, au.last_name as approver_last
        FROM disposal_bin db
        LEFT JOIN users u ON u.id = db.disposed_by
        LEFT JOIN users au ON au.id = db.approved_by
        WHERE {$whereSQL} ORDER BY db.created_at DESC
        LIMIT :lim OFFSET :off",
        array_merge($params, [':lim'=>$perPage, ':off'=>$offset]));

    $total = Database::fetch("SELECT COUNT(*) as cnt FROM disposal_bin db WHERE {$whereSQL}", $params)['cnt'] ?? 0;

    return [
        'items' => $items,
        'pagination' => ['page'=>$page, 'per_page'=>$perPage, 'total'=>(int)$total, 'total_pages'=>(int)ceil($total / $perPage)]
    ];
}

function disposalReport(array $filters, array $user): array {
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    if ($roleLevel < 3) throw new Exception('Permission denied', 403);

    $where  = ['1=1'];
    $params = [];
    if (!empty($filters['date_from'])) { $where[] = "db.created_at >= :dfrom"; $params[':dfrom'] = $filters['date_from'].' 00:00:00'; }
    if (!empty($filters['date_to'])) { $where[] = "db.created_at <= :dto"; $params[':dto'] = $filters['date_to'].' 23:59:59'; }
    if (!empty($filters['status'])) { $where[] = "db.status = :st"; $params[':st'] = $filters['status']; }
    $whereSQL = implode(' AND ', $where);

    // Summary stats
    $stats = Database::fetch("SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as cancelled FROM disposal_bin db WHERE {$whereSQL}", $params);

    // By person
    $byPerson = Database::fetchAll("
        SELECT db.disposed_by, u.first_name, u.last_name, u.department,
               COUNT(*) as item_count, SUM(db.remaining_qty) as total_qty,
               GROUP_CONCAT(DISTINCT db.unit ORDER BY db.unit) as units
        FROM disposal_bin db
        LEFT JOIN users u ON u.id = db.disposed_by
        WHERE {$whereSQL}
        GROUP BY db.disposed_by, u.first_name, u.last_name, u.department
        ORDER BY item_count DESC", $params);

    // By department
    $byDepartment = Database::fetchAll("
        SELECT COALESCE(NULLIF(db.department,''), u.department, 'ไม่ระบุ') as dept_name,
               COUNT(*) as item_count, SUM(db.remaining_qty) as total_qty,
               GROUP_CONCAT(DISTINCT db.unit ORDER BY db.unit) as units,
               SUM(CASE WHEN db.status='pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN db.status='completed' THEN 1 ELSE 0 END) as completed
        FROM disposal_bin db
        LEFT JOIN users u ON u.id = db.disposed_by
        WHERE {$whereSQL}
        GROUP BY dept_name
        ORDER BY item_count DESC", $params);

    // By reason
    $byReason = Database::fetchAll("
        SELECT COALESCE(db.disposal_reason, 'other') as reason,
               COUNT(*) as item_count, SUM(db.remaining_qty) as total_qty
        FROM disposal_bin db
        WHERE {$whereSQL}
        GROUP BY reason
        ORDER BY item_count DESC", $params);

    // By building
    $byBuilding = Database::fetchAll("
        SELECT COALESCE(NULLIF(db.building_name,''), 'ไม่ระบุ') as bld_name,
               COUNT(*) as item_count, SUM(db.remaining_qty) as total_qty,
               GROUP_CONCAT(DISTINCT db.unit ORDER BY db.unit) as units
        FROM disposal_bin db
        WHERE {$whereSQL}
        GROUP BY bld_name
        ORDER BY item_count DESC", $params);

    // By method
    $byMethod = Database::fetchAll("
        SELECT COALESCE(db.disposal_method, 'other') as method,
               COUNT(*) as item_count
        FROM disposal_bin db
        WHERE {$whereSQL}
        GROUP BY method
        ORDER BY item_count DESC", $params);

    // Recent timeline (last 20)
    $recent = Database::fetchAll("
        SELECT db.*, u.first_name as disposed_first, u.last_name as disposed_last
        FROM disposal_bin db
        LEFT JOIN users u ON u.id = db.disposed_by
        WHERE {$whereSQL}
        ORDER BY db.created_at DESC LIMIT 20", $params);

    return [
        'stats'         => $stats,
        'by_person'     => $byPerson,
        'by_department' => $byDepartment,
        'by_reason'     => $byReason,
        'by_building'   => $byBuilding,
        'by_method'     => $byMethod,
        'recent'        => $recent
    ];
}

function disposalComplete(array $data, array $user): array {
    $binId = (int)($data['bin_id'] ?? 0);
    if (!$binId) throw new Exception('bin_id required');
    Database::update('disposal_bin', [
        'status'=>'completed', 'approved_by'=>$user['id'],
        'approved_at'=>date('Y-m-d H:i:s'), 'completed_at'=>date('Y-m-d H:i:s'),
        'completion_notes'=>$data['notes']??null
    ], 'id = :id', [':id'=>$binId]);
    return ['bin_id'=>$binId, 'status'=>'completed'];
}

function disposalCancel(array $data, array $user): array {
    $binId = (int)($data['bin_id'] ?? 0);
    if (!$binId) throw new Exception('bin_id required');

    $bin = Database::fetch("SELECT * FROM disposal_bin WHERE id = :id", [':id'=>$binId]);
    if (!$bin) throw new Exception('Disposal bin item not found');

    if ($bin['source_type'] === 'container') {
        Database::update('containers', ['status'=>'active','current_quantity'=>$bin['remaining_qty']], 'id = :id', [':id'=>$bin['source_id']]);
    } else {
        Database::update('chemical_stock', ['status'=>'active','remaining_qty'=>$bin['remaining_qty']], 'id = :id', [':id'=>$bin['source_id']]);
    }

    Database::update('disposal_bin', ['status'=>'rejected'], 'id = :id', [':id'=>$binId]);
    if ($bin['txn_id']) {
        Database::update('chemical_transactions', ['status'=>'cancelled'], 'id = :id', [':id'=>$bin['txn_id']]);
    }
    return ['bin_id'=>$binId, 'status'=>'cancelled', 'restored'=>true];
}

// ========== REPORT ITEM ==========
/**
 * User reports an issue/inquiry about a chemical item (scan barcode page)
 * Creates alert that notifies the item owner
 */
function reportItem(array $data, array $user): array {
    $sourceType = $data['source_type'] ?? '';
    $sourceId = (int)($data['source_id'] ?? 0);
    $barcode = trim($data['barcode'] ?? '');
    $reportType = $data['report_type'] ?? 'inquiry'; // inquiry, issue, request
    $message = trim($data['message'] ?? '');
    $ownerId = (int)($data['owner_id'] ?? 0);

    if (!$sourceType || !$sourceId || !$message) {
        throw new Exception('Missing required fields');
    }

    // Create an alert for the item owner
    $alertId = Database::insert('alerts', [
        'alert_type' => $reportType === 'issue' ? 'warning' : 'info',
        'severity' => $reportType === 'issue' ? 'high' : 'medium',
        'title' => match($reportType) {
            'issue' => 'สารเคมี: ' . ($barcode ?? 'Unknown') . ' - มีปัญหา',
            'request' => 'สารเคมี: ' . ($barcode ?? 'Unknown') . ' - มีการขอยืม/เบิก',
            default => 'สารเคมี: ' . ($barcode ?? 'Unknown') . ' - การสอบถาม'
        },
        'message' => '[จาก: ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . '] ' . $message,
        'user_id' => $ownerId ?: null, // Alert goes to item owner (if known)
        'action_required' => $reportType !== 'inquiry'
    ]);

    // Also create a log record in chemical_transactions for traceability
    Database::insert('chemical_transactions', [
        'txn_number' => genTxnNumber(),
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'barcode' => $barcode,
        'txn_type' => 'report',
        'from_user_id' => (int)$user['id'],
        'to_user_id' => $ownerId,
        'initiated_by' => (int)$user['id'],
        'purpose' => $reportType . ': ' . $message,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s'),
        'requires_approval' => 0
    ]);

    return [
        'alert_id' => $alertId,
        'status' => 'reported',
        'message' => 'Item owner has been notified'
    ];
}