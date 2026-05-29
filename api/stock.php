<?php
/**
 * Chemical Stock API (ขวดสารเคมีในคลัง)
 * 
 * GET  ?action=stats          → Dashboard stats (role-filtered)
 * GET  ?action=list           → Paginated list with search/filter
 * GET  ?action=detail&id=N    → Single record detail
 * GET  ?action=export         → CSV export (role-filtered)
 * GET  ?action=my             → Current user's stock only
 * GET  ?action=units          → Distinct unit list
 * GET  ?action=owners         → Owner list (for filter dropdown)
 * POST ?action=create         → Add new stock record
 * POST ?action=import         → Bulk CSV import
 * POST ?action=use            → Record usage (reduce qty)
 * PUT  ?id=N                  → Update record
 * DELETE ?id=N                → Delete record (admin only)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();
$role = $user['role_name'];
$userId = $user['id'];

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';
            switch ($action) {
                case 'stats':   echo json_encode(['success'=>true,'data'=>getStats($user)]); break;
                case 'list':    echo json_encode(['success'=>true,'data'=>getList($_GET, $user)]); break;
                case 'detail':  echo json_encode(['success'=>true,'data'=>getDetail((int)($_GET['id']??0), $user)]); break;
                case 'export':  exportCSV($_GET, $user); break;
                case 'my':      $_GET['owner_id'] = $userId; echo json_encode(['success'=>true,'data'=>getList($_GET, $user)]); break;
                case 'units':   echo json_encode(['success'=>true,'data'=>getUnits()]); break;
                case 'owners':  echo json_encode(['success'=>true,'data'=>getOwners($user)]); break;
                case 'relink':  echo json_encode(['success'=>true,'data'=>relinkOwners($user)]); break;
                default: throw new Exception('Unknown action');
            }
            break;

        case 'POST':
            $action = $_GET['action'] ?? '';
            $data = json_decode(file_get_contents('php://input'), true);
            switch ($action) {
                case 'create':  echo json_encode(['success'=>true,'data'=>createStock($data, $user)]); break;
                case 'import':  handleImport($user); break;
                case 'use':     echo json_encode(['success'=>true,'data'=>recordUsage($data, $user)]); break;
                default: throw new Exception('Unknown action');
            }
            break;

        case 'PUT':
            $id = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(['success'=>true,'data'=>updateStock($id, $data, $user)]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            deleteStock($id, $user);
            echo json_encode(['success'=>true,'message'=>'Deleted']);
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

// ════════════════════════════════════════════
// ROLE-BASED WHERE CLAUSE
// ════════════════════════════════════════════
function roleWhere(array $user, string $alias = 's'): array {
    $role = $user['role_name'];
    $uid = $user['id'];

    if (in_array($role, ['admin', 'ceo'])) {
        return ['where' => '1=1', 'params' => []];
    }

    if ($role === 'lab_manager') {
        // Manager sees own + subordinates
        return [
            'where' => "($alias.owner_user_id = :uid OR $alias.owner_user_id IN (SELECT id FROM users WHERE manager_id = :uid2))",
            'params' => [':uid' => $uid, ':uid2' => $uid]
        ];
    }

    // Regular user sees all active stock (can only edit their own — enforced in updateStock/deleteStock)
    return ['where' => "$alias.status != 'disposed'", 'params' => []];
}

// ════════════════════════════════════════════
// STATS
// ════════════════════════════════════════════
function getStats(array $user): array {
    $r = roleWhere($user);
    $pdo = Database::getInstance();

    $base = "FROM chemical_stock s WHERE {$r['where']}";

    $total = $pdo->prepare("SELECT COUNT(*) $base");
    $total->execute($r['params']);
    $totalCount = (int)$total->fetchColumn();

    $active = $pdo->prepare("SELECT COUNT(*) $base AND s.status='active'");
    $active->execute($r['params']);
    $activeCount = (int)$active->fetchColumn();

    $low = $pdo->prepare("SELECT COUNT(*) $base AND s.status='low'");
    $low->execute($r['params']);
    $lowCount = (int)$low->fetchColumn();

    $empty = $pdo->prepare("SELECT COUNT(*) $base AND s.status='empty'");
    $empty->execute($r['params']);
    $emptyCount = (int)$empty->fetchColumn();

    $uniqueChemicals = $pdo->prepare("SELECT COUNT(DISTINCT s.chemical_name) $base");
    $uniqueChemicals->execute($r['params']);
    $uniqueChemCount = (int)$uniqueChemicals->fetchColumn();

    $uniqueOwners = $pdo->prepare("SELECT COUNT(DISTINCT s.owner_user_id) $base AND s.owner_user_id IS NOT NULL");
    $uniqueOwners->execute($r['params']);
    $uniqueOwnerCount = (int)$uniqueOwners->fetchColumn();

    // Unit distribution
    $unitDist = $pdo->prepare("SELECT s.unit, COUNT(*) as cnt $base AND s.unit IS NOT NULL GROUP BY s.unit ORDER BY cnt DESC LIMIT 8");
    $unitDist->execute($r['params']);
    $units = $unitDist->fetchAll(PDO::FETCH_ASSOC);

    // Top chemicals by bottle count
    $topChems = $pdo->prepare("SELECT s.chemical_name, COUNT(*) as cnt $base GROUP BY s.chemical_name ORDER BY cnt DESC LIMIT 5");
    $topChems->execute($r['params']);
    $topList = $topChems->fetchAll(PDO::FETCH_ASSOC);

    // My stock counts (for the current user)
    $myUid = $user['id'];
    $myCount = $pdo->prepare("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = :uid");
    $myCount->execute([':uid' => $myUid]);
    $myTotal = (int)$myCount->fetchColumn();

    $myActive = $pdo->prepare("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = :uid AND status='active'");
    $myActive->execute([':uid' => $myUid]);
    $myActiveCount = (int)$myActive->fetchColumn();

    $myLow = $pdo->prepare("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = :uid AND status='low'");
    $myLow->execute([':uid' => $myUid]);
    $myLowCount = (int)$myLow->fetchColumn();

    $myEmpty = $pdo->prepare("SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = :uid AND status='empty'");
    $myEmpty->execute([':uid' => $myUid]);
    $myEmptyCount = (int)$myEmpty->fetchColumn();

    // User's display name for My Stock banner
    $myName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    return [
        'total' => $totalCount,
        'active' => $activeCount,
        'low' => $lowCount,
        'empty' => $emptyCount,
        'unique_chemicals' => $uniqueChemCount,
        'unique_owners' => $uniqueOwnerCount,
        'unit_distribution' => $units,
        'top_chemicals' => $topList,
        'my_total' => $myTotal,
        'my_active' => $myActiveCount,
        'my_low' => $myLowCount,
        'my_empty' => $myEmptyCount,
        'my_name' => $myName
    ];
}

// ════════════════════════════════════════════
// LIST (paginated, searchable, filterable)
// ════════════════════════════════════════════
function getList(array $params, array $user): array {
    $r = roleWhere($user);
    $pdo = Database::getInstance();

    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min(100, max(10, (int)($params['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $search = trim($params['search'] ?? '');
    $status = $params['status'] ?? '';
    $unit = $params['unit'] ?? '';
    $ownerId = $params['owner_id'] ?? '';
    $sort = $params['sort'] ?? 'added_at';
    $dir = strtoupper($params['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $where = [$r['where']];
    $bind = $r['params'];

    if ($search !== '') {
        $where[] = "(s.bottle_code LIKE :q OR s.chemical_name LIKE :q2 OR s.cas_no LIKE :q3 OR s.owner_name LIKE :q4 OR s.grade LIKE :q5)";
        $bind[':q'] = "%$search%";
        $bind[':q2'] = "%$search%";
        $bind[':q3'] = "%$search%";
        $bind[':q4'] = "%$search%";
        $bind[':q5'] = "%$search%";
    }
    if ($status !== '' && in_array($status, ['active','low','empty','expired','disposed'])) {
        $where[] = "s.status = :status";
        $bind[':status'] = $status;
    }
    if ($unit !== '') {
        $where[] = "s.unit = :unit";
        $bind[':unit'] = $unit;
    }
    if ($ownerId !== '') {
        $where[] = "s.owner_user_id = :owner_id";
        $bind[':owner_id'] = (int)$ownerId;
    }

    $allowedSort = ['bottle_code','chemical_name','cas_no','grade','package_size','remaining_qty','remaining_pct','unit','owner_name','added_at','status','created_at'];
    if (!in_array($sort, $allowedSort)) $sort = 'added_at';

    $whereSQL = implode(' AND ', $where);

    // Count
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM chemical_stock s WHERE $whereSQL");
    $cnt->execute($bind);
    $totalRows = (int)$cnt->fetchColumn();

    // Data
    $sql = "SELECT s.*, 
                   u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last,
                   u.department as owner_department, u.avatar_url as owner_avatar,
                   c.name as linked_chem_name, c.molecular_formula, c.signal_word
            FROM chemical_stock s
            LEFT JOIN users u ON s.owner_user_id = u.id
            LEFT JOIN chemicals c ON s.chemical_id = c.id
            WHERE $whereSQL
            ORDER BY s.$sort $dir
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'items' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRows,
            'pages' => ceil($totalRows / $limit)
        ]
    ];
}

// ════════════════════════════════════════════
// DETAIL
// ════════════════════════════════════════════
function getDetail(int $id, array $user): array {
    if ($id <= 0) throw new Exception('Invalid ID');

    $row = Database::fetch(
        "SELECT s.*, 
                u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last,
                u.department as owner_department, u.avatar_url as owner_avatar, u.role_id as owner_role_id,
                c.name as linked_chem_name, c.cas_number as linked_cas, c.molecular_formula,
                c.signal_word, c.hazard_pictograms, c.ghs_classifications,
                c.physical_state, c.sds_url
         FROM chemical_stock s
         LEFT JOIN users u ON s.owner_user_id = u.id
         LEFT JOIN chemicals c ON s.chemical_id = c.id
         WHERE s.id = :id",
        [':id' => $id]
    );

    if (!$row) throw new Exception('Record not found');

    // Add mapping status indicator
    $row['owner_is_mapped'] = !empty($row['owner_username']);

    // Permission check — all authenticated users may view detail (read-only)
    // Lab managers may only view their own team's items; write ops are gated separately
    $role = $user['role_name'];
    if ($role === 'lab_manager') {
        $teamIds = Database::fetchAll("SELECT id FROM users WHERE manager_id = :mid", [':mid' => $user['id']]);
        $allowed = array_column($teamIds, 'id');
        $allowed[] = $user['id'];
        if (!in_array($row['owner_user_id'], $allowed) && !in_array($role, ['admin','ceo'])) {
            throw new Exception('Access denied');
        }
    }
    // admin, ceo, user: can view any item's detail

    return $row;
}

// ════════════════════════════════════════════
// CREATE
// ════════════════════════════════════════════
function createStock(array $data, array $user): array {
    $required = ['bottle_code', 'chemical_name'];
    foreach ($required as $f) {
        if (empty($data[$f])) throw new Exception("Missing field: $f");
    }

    $pdo = Database::getInstance();

    // Auto-resolve CAS → chemical_id
    $chemId = null;
    if (!empty($data['cas_no'])) {
        $chem = Database::fetch("SELECT id FROM chemicals WHERE cas_number = :cas", [':cas' => $data['cas_no']]);
        if ($chem) $chemId = $chem['id'];
    }

    $ownerId = $data['owner_user_id'] ?? $user['id'];
    // Non-admin can only create for themselves
    if (!in_array($user['role_name'], ['admin'])) {
        $ownerId = $user['id'];
    }

    $pkgSize = floatval($data['package_size'] ?? 0);
    $remQty = floatval($data['remaining_qty'] ?? $pkgSize);
    $status = 'active';
    if ($remQty <= 0) $status = 'empty';
    elseif ($pkgSize > 0 && ($remQty / $pkgSize) < 0.1) $status = 'low';

    $stmt = $pdo->prepare("
        INSERT INTO chemical_stock
            (bottle_code, chemical_name, cas_no, grade, package_size, remaining_qty, unit,
             owner_name, owner_user_id, storage_location, chemical_id, added_at, created_by, status)
        VALUES
            (:bottle_code, :chemical_name, :cas_no, :grade, :package_size, :remaining_qty, :unit,
             :owner_name, :owner_user_id, :storage_location, :chemical_id, NOW(), :created_by, :status)
    ");

    // Get owner name
    $ownerRow = Database::fetch("SELECT CONCAT(first_name,' ',last_name) as fn FROM users WHERE id=:id", [':id'=>$ownerId]);
    $ownerName = $ownerRow ? $ownerRow['fn'] : ($data['owner_name'] ?? '');

    $stmt->execute([
        ':bottle_code' => $data['bottle_code'],
        ':chemical_name' => $data['chemical_name'],
        ':cas_no' => $data['cas_no'] ?? null,
        ':grade' => $data['grade'] ?? null,
        ':package_size' => $pkgSize ?: null,
        ':remaining_qty' => $remQty ?: null,
        ':unit' => $data['unit'] ?? null,
        ':owner_name' => $ownerName,
        ':owner_user_id' => $ownerId,
        ':storage_location' => $data['storage_location'] ?? null,
        ':chemical_id' => $chemId,
        ':created_by' => $user['id'],
        ':status' => $status
    ]);

    return ['id' => (int)$pdo->lastInsertId()];
}

// ════════════════════════════════════════════
// UPDATE
// ════════════════════════════════════════════
function updateStock(int $id, array $data, array $user): array {
    if ($id <= 0) throw new Exception('Invalid ID');

    $existing = Database::fetch("SELECT * FROM chemical_stock WHERE id = :id", [':id' => $id]);
    if (!$existing) throw new Exception('Not found');

    // Permission: admin can edit all; user/lab_manager can edit own
    $role = $user['role_name'];
    if (!in_array($role, ['admin'])) {
        if ((int)$existing['owner_user_id'] !== (int)$user['id']) {
            throw new Exception('You can only edit your own stock');
        }
    }

    $allowed = ['bottle_code','chemical_name','cas_no','grade','package_size','remaining_qty','unit',
                'storage_location','status','owner_user_id'];
    $sets = [];
    $bind = [':id' => $id];

    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            $sets[] = "$col = :$col";
            $bind[":$col"] = $data[$col];
        }
    }

    if (empty($sets)) throw new Exception('No fields to update');

    // Recalculate status if qty changed
    if (isset($data['remaining_qty']) || isset($data['package_size'])) {
        $pkg = floatval($data['package_size'] ?? $existing['package_size']);
        $rem = floatval($data['remaining_qty'] ?? $existing['remaining_qty']);
        $status = 'active';
        if ($rem <= 0) $status = 'empty';
        elseif ($pkg > 0 && ($rem / $pkg) < 0.1) $status = 'low';
        $sets[] = "status = :auto_status";
        $bind[':auto_status'] = $status;
    }

    // Re-resolve CAS
    if (isset($data['cas_no']) && !empty($data['cas_no'])) {
        $chem = Database::fetch("SELECT id FROM chemicals WHERE cas_number = :cas", [':cas' => $data['cas_no']]);
        $sets[] = "chemical_id = :chem_id";
        $bind[':chem_id'] = $chem ? $chem['id'] : null;
    }

    $sql = "UPDATE chemical_stock SET " . implode(', ', $sets) . " WHERE id = :id";
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);

    return ['id' => $id, 'updated' => true];
}

// ════════════════════════════════════════════
// RECORD USAGE (reduce qty)
// ════════════════════════════════════════════
function recordUsage(array $data, array $user): array {
    $id = (int)($data['id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    if ($id <= 0 || $amount <= 0) throw new Exception('Invalid id or amount');

    $existing = Database::fetch("SELECT * FROM chemical_stock WHERE id = :id", [':id' => $id]);
    if (!$existing) throw new Exception('Not found');

    // Permission: owner or admin
    if (!in_array($user['role_name'], ['admin']) && (int)$existing['owner_user_id'] !== (int)$user['id']) {
        throw new Exception('You can only use your own stock');
    }

    $newQty = max(0, floatval($existing['remaining_qty']) - $amount);
    $pkg = floatval($existing['package_size']);
    $status = 'active';
    if ($newQty <= 0) $status = 'empty';
    elseif ($pkg > 0 && ($newQty / $pkg) < 0.1) $status = 'low';

    $pdo = Database::getInstance();
    $pdo->prepare("UPDATE chemical_stock SET remaining_qty = :qty, status = :st WHERE id = :id")
        ->execute([':qty' => $newQty, ':st' => $status, ':id' => $id]);

    return ['id' => $id, 'remaining_qty' => $newQty, 'status' => $status];
}

// ════════════════════════════════════════════
// DELETE (admin only)
// ════════════════════════════════════════════
function deleteStock(int $id, array $user): void {
    if ($user['role_name'] !== 'admin') throw new Exception('Admin only');
    if ($id <= 0) throw new Exception('Invalid ID');

    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("DELETE FROM chemical_stock WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) throw new Exception('Not found');
}

// ════════════════════════════════════════════
// CSV EXPORT
// ════════════════════════════════════════════
function exportCSV(array $params, array $user): void {
    $r = roleWhere($user);
    $pdo = Database::getInstance();

    $where = [$r['where']];
    $bind = $r['params'];

    $search = trim($params['search'] ?? '');
    if ($search !== '') {
        $where[] = "(s.bottle_code LIKE :q OR s.chemical_name LIKE :q2 OR s.cas_no LIKE :q3)";
        $bind[':q'] = "%$search%";
        $bind[':q2'] = "%$search%";
        $bind[':q3'] = "%$search%";
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "SELECT s.bottle_code, s.chemical_name, s.cas_no, s.grade,
                   s.package_size, s.remaining_qty, s.unit, s.remaining_pct,
                   s.owner_name, u.department as owner_department,
                   s.storage_location, s.status, s.added_at
            FROM chemical_stock s
            LEFT JOIN users u ON s.owner_user_id = u.id
            WHERE $whereSQL
            ORDER BY s.bottle_code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // BOM + CSV output
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="chemical_stock_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($out, ['รหัสขวด','ชื่อสารเคมี','CAS No.','เกรด','ขนาดบรรจุ','ปริมาณคงเหลือ','หน่วย','% คงเหลือ','ผู้เพิ่มขวด','ฝ่าย/แผนก','สถานที่จัดเก็บ','สถานะ','เวลาเพิ่ม']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['bottle_code'], $r['chemical_name'], $r['cas_no'], $r['grade'],
            $r['package_size'], $r['remaining_qty'], $r['unit'],
            $r['remaining_pct'] !== null ? round($r['remaining_pct'], 1) . '%' : '',
            $r['owner_name'], $r['owner_department'],
            $r['storage_location'], $r['status'], $r['added_at']
        ]);
    }
    fclose($out);
    exit;
}

// ════════════════════════════════════════════
// CSV IMPORT (admin only)
// ════════════════════════════════════════════
function handleImport(array $user): void {
    if ($user['role_name'] !== 'admin') throw new Exception('Admin only');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file']['tmp_name'];
    $f = fopen($file, 'r');
    if (!$f) throw new Exception('Cannot read file');

    // Detect if first row is header
    $firstLine = fgets($f);
    $cols = str_getcsv($firstLine);
    $hasHeader = (mb_strpos($cols[0], 'รหัสขวด') !== false || mb_strpos($cols[0], 'bottle') !== false);
    if (!$hasHeader) rewind($f);
    else {
        // Check for 2nd header row
        $secondLine = fgets($f);
        $cols2 = str_getcsv($secondLine);
        if (!empty(trim($cols2[0])) && mb_strpos($cols2[0], 'รหัสขวด') === false) {
            // Not a header, rewind past first header
            fseek($f, strlen($firstLine));
        }
    }

    $pdo = Database::getInstance();

    // Build maps — prefer higher-role users when duplicate names exist (e.g. admin1 vs lab0)
    $users = $pdo->query("
        SELECT u.id, CONCAT(u.first_name,' ',u.last_name) as fn, u.full_name_th,
               COALESCE(r.level, 0) as role_level
        FROM users u LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY r.level DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $nameMap = [];
    foreach ($users as $u) {
        $variants = [];
        $variants[] = mb_strtolower(trim($u['fn']), 'UTF-8');
        if (!empty($u['full_name_th'])) {
            $variants[] = mb_strtolower(trim($u['full_name_th']), 'UTF-8');
            $stripped = preg_replace('/^(นาย|นางสาว|นาง|ดร\\.)\ s*/u', '', $u['full_name_th']);
            $variants[] = mb_strtolower(trim($stripped), 'UTF-8');
        }
        foreach (array_unique($variants) as $key) {
            if ($key === '') continue;
            // Only store if no higher-role user already claimed this name
            if (!isset($nameMap[$key])) {
                $nameMap[$key] = $u['id'];
            }
        }
    }

    $chems = $pdo->query("SELECT id, cas_number FROM chemicals WHERE cas_number IS NOT NULL AND cas_number != ''")->fetchAll(PDO::FETCH_ASSOC);
    $casMap = [];
    foreach ($chems as $c) $casMap[trim($c['cas_number'])] = $c['id'];

    $stmt = $pdo->prepare("
        INSERT INTO chemical_stock
            (bottle_code, chemical_name, cas_no, grade, package_size, remaining_qty, unit,
             owner_name, owner_user_id, storage_location, chemical_id, added_at, created_by, status)
        VALUES
            (:bc, :cn, :cas, :gr, :ps, :rq, :un, :on, :ouid, :sl, :cid, :at, :cb, :st)
    ");

    $imported = 0;
    $errors = [];
    $pdo->beginTransaction();

    while (($line = fgets($f)) !== false) {
        $cols = str_getcsv($line);
        $bc = trim($cols[0] ?? '');
        if (empty($bc)) continue;

        $cn = trim($cols[1] ?? '');
        $cas = trim($cols[2] ?? '');
        $gr = trim($cols[3] ?? '');
        $ps = str_replace(',', '', trim($cols[4] ?? ''));
        $rq = str_replace(',', '', trim($cols[5] ?? ''));
        $un = trim($cols[6] ?? '');
        $on = trim($cols[7] ?? '');
        $at = trim($cols[8] ?? '');

        $ownerClean = preg_replace('/^(นาย|นางสาว|นาง|ดร\\.)\s*/u', '', $on);
        $ouid = $nameMap[mb_strtolower($ownerClean, 'UTF-8')] ?? $nameMap[mb_strtolower($on, 'UTF-8')] ?? null;
        $cid = $casMap[$cas] ?? null;

        // Parse date
        $addedAt = null;
        if (!empty($at)) {
            $parts = preg_split('/[\s\/]/', $at);
            if (count($parts) >= 3) {
                $d = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $addedAt = "{$parts[2]}-$m-$d " . ($parts[3] ?? '00:00') . ":00";
            }
        }

        $pkgF = is_numeric($ps) ? floatval($ps) : null;
        $remF = is_numeric($rq) ? floatval($rq) : null;
        $status = 'active';
        if ($pkgF !== null && $remF !== null) {
            if ($remF <= 0) $status = 'empty';
            elseif ($pkgF > 0 && ($remF / $pkgF) < 0.1) $status = 'low';
        }

        try {
            $stmt->execute([
                ':bc' => $bc, ':cn' => $cn, ':cas' => $cas ?: null,
                ':gr' => $gr ?: null, ':ps' => $pkgF, ':rq' => $remF,
                ':un' => $un ?: null, ':on' => $on ?: null, ':ouid' => $ouid,
                ':sl' => null, ':cid' => $cid, ':at' => $addedAt,
                ':cb' => $user['id'], ':st' => $status
            ]);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $bc: " . $e->getMessage();
        }
    }

    $pdo->commit();
    fclose($f);

    echo json_encode([
        'success' => true,
        'data' => [
            'imported' => $imported,
            'errors' => count($errors),
            'error_details' => array_slice($errors, 0, 10)
        ]
    ]);
    exit;
}

// ════════════════════════════════════════════
// RELINK OWNERS (admin-only auto-repair)
// ════════════════════════════════════════════
function relinkOwners(array $user): array {
    if ($user['role_name'] !== 'admin') throw new Exception('Admin only');

    $pdo = Database::getInstance();

    // Build name → user_id map (prefer higher-role users for duplicate names)
    $users = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.full_name_th,
               COALESCE(r.level, 0) as role_level
        FROM users u LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY r.level DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $nameMap = []; // lowered name → [user_id, role_level]
    foreach ($users as $u) {
        $concat = trim($u['first_name'] . ' ' . $u['last_name']);
        $full = $u['full_name_th'] ?? '';
        $stripped = trim(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $full));

        foreach ([$concat, $full, $stripped] as $variant) {
            $key = mb_strtolower(trim($variant), 'UTF-8');
            if ($key === '') continue;
            if (!isset($nameMap[$key]) || $u['role_level'] > $nameMap[$key][1]) {
                $nameMap[$key] = [$u['id'], $u['role_level']];
            }
        }
    }

    // Get all distinct owner entries
    $owners = $pdo->query("SELECT DISTINCT owner_user_id, owner_name FROM chemical_stock WHERE owner_name IS NOT NULL AND owner_name != ''")->fetchAll(PDO::FETCH_ASSOC);

    $fixed = 0;
    $details = [];
    foreach ($owners as $o) {
        $name = $o['owner_name'];
        $currentId = (int)$o['owner_user_id'];
        $stripped = trim(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $name));
        $key = mb_strtolower($stripped, 'UTF-8');
        $match = $nameMap[$key] ?? $nameMap[mb_strtolower($name, 'UTF-8')] ?? null;

        if ($match && $match[0] !== $currentId) {
            $stmt = $pdo->prepare("UPDATE chemical_stock SET owner_user_id = :new WHERE owner_user_id = :old AND owner_name = :name");
            $stmt->execute([':new' => $match[0], ':old' => $currentId, ':name' => $name]);
            $cnt = $stmt->rowCount();
            if ($cnt > 0) {
                $fixed += $cnt;
                $details[] = "{$name}: {$currentId}→{$match[0]} ({$cnt} rows)";
            }
        }
    }

    return ['fixed' => $fixed, 'details' => $details];
}

// ════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════
function getUnits(): array {
    return Database::fetchAll("SELECT DISTINCT unit FROM chemical_stock WHERE unit IS NOT NULL AND unit != '' ORDER BY unit");
}

function getOwners(array $user): array {
    $r = roleWhere($user);
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.owner_user_id, s.owner_name, u.username, u.department, COUNT(*) as cnt
        FROM chemical_stock s
        LEFT JOIN users u ON s.owner_user_id = u.id
        WHERE {$r['where']} AND s.owner_user_id IS NOT NULL
        GROUP BY s.owner_user_id, s.owner_name, u.username, u.department
        ORDER BY cnt DESC
    ");
    $stmt->execute($r['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
