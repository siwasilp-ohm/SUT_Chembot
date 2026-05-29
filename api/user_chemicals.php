<?php
/**
 * User Chemicals API — ข้อมูลสารรายบุคคล
 * Admin-only: lists all users with their chemical container counts,
 * and can drill down to see individual container details per user.
 * 
 * Filter: store_name (from lab_stores table) and division_name
 * Users link to lab_stores via department field matching division_name or section_name
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$user = Auth::requireAuth();

// Admin/CEO/Lab Manager only
if (!in_array($user['role_name'], ['admin', 'ceo', 'lab_manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'data' => getUserList($user)]);
            break;
        case 'detail':
            $userId = intval($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid user_id']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => getUserContainers($userId)]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Resolve user's store info from lab_stores via department field
 * users.department can match lab_stores.division_name OR lab_stores.section_name
 */
function resolveUserStores(array $users): array {
    // Get all active lab_stores for mapping
    $stores = Database::fetchAll(
        "SELECT id, store_name, division_name, section_name
         FROM lab_stores WHERE is_active = 1"
    );

    // Build lookup maps
    $divisionStores = [];  // division_name -> [store_names]
    $sectionStores = [];   // section_name -> [store_names]
    foreach ($stores as $s) {
        $div = trim($s['division_name']);
        $sec = trim($s['section_name']);
        if ($div !== '') {
            $divisionStores[$div][] = $s;
        }
        if ($sec !== '') {
            $sectionStores[$sec][] = $s;
        }
    }

    foreach ($users as &$u) {
        $dept = trim($u['department'] ?? '');
        $u['store_names'] = [];
        $u['division_name'] = '';
        $u['section_name'] = '';

        if ($dept === '') continue;

        // Try match as division_name first (ฝ่าย-level)
        if (isset($divisionStores[$dept])) {
            $matched = $divisionStores[$dept];
            $u['division_name'] = $dept;
            $u['store_names'] = array_unique(array_column($matched, 'store_name'));
            // section from first match
            $u['section_name'] = $matched[0]['section_name'] ?? '';
        }
        // Try match as section_name (งาน-level)
        elseif (isset($sectionStores[$dept])) {
            $matched = $sectionStores[$dept];
            $u['section_name'] = $dept;
            $u['division_name'] = $matched[0]['division_name'] ?? '';
            $u['store_names'] = array_unique(array_column($matched, 'store_name'));
        }
    }
    unset($u);

    return $users;
}

/**
 * Get all active users with summary counts
 */
function getUserList(array $currentUser): array {
    $search = trim($_GET['search'] ?? '');
    $storeFilter = trim($_GET['store'] ?? '');
    $divFilter = trim($_GET['division'] ?? '');
    $sort = $_GET['sort'] ?? 'containers_desc';

    $where = "u.is_active = 1";
    $params = [];

    if ($search !== '') {
        $where .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search 
                     OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Filter by division (ฝ่าย) — match users whose department = division_name or whose department is a section under this division
    if ($divFilter !== '') {
        // Find all section_names under this division
        $sections = Database::fetchAll(
            "SELECT DISTINCT section_name FROM lab_stores WHERE division_name = :div AND is_active = 1",
            [':div' => $divFilter]
        );
        $sectionNames = array_column($sections, 'section_name');
        $sectionNames[] = $divFilter; // also match division_name itself

        // Build IN clause dynamically
        $inParts = [];
        foreach ($sectionNames as $i => $sn) {
            $key = ":div_sec_{$i}";
            $inParts[] = $key;
            $params[$key] = $sn;
        }
        $inClause = implode(',', $inParts);
        $where .= " AND u.department IN ({$inClause})";
    }

    // Filter by store_name — find users whose department matches division or section of matching stores
    if ($storeFilter !== '') {
        $storeRows = Database::fetchAll(
            "SELECT DISTINCT division_name, section_name FROM lab_stores WHERE store_name = :sn AND is_active = 1",
            [':sn' => $storeFilter]
        );
        if (!empty($storeRows)) {
            $matchDepts = [];
            foreach ($storeRows as $sr) {
                $matchDepts[] = $sr['division_name'];
                $matchDepts[] = $sr['section_name'];
            }
            $matchDepts = array_unique(array_filter($matchDepts));
            $inParts = [];
            foreach (array_values($matchDepts) as $i => $md) {
                $key = ":store_dept_{$i}";
                $inParts[] = $key;
                $params[$key] = $md;
            }
            $inClause = implode(',', $inParts);
            $where .= " AND u.department IN ({$inClause})";
        } else {
            // No matching store — return empty
            $where .= " AND 1=0";
        }
    }

    // Lab manager scope: only users in same department
    if ($currentUser['role_name'] === 'lab_manager') {
        $mgrDept = trim($currentUser['department'] ?? '');
        if ($mgrDept !== '') {
            $where .= " AND u.department = :mgr_dept";
            $params[':mgr_dept'] = $mgrDept;
        } elseif (!empty($currentUser['lab_id'])) {
            $where .= " AND u.lab_id = :mgr_lab_id";
            $params[':mgr_lab_id'] = $currentUser['lab_id'];
        }
    }

    $orderBy = match ($sort) {
        'name_asc'        => 'u.first_name ASC, u.last_name ASC',
        'name_desc'       => 'u.first_name DESC, u.last_name DESC',
        'containers_asc'  => 'active_containers ASC',
        'store'           => 'u.department ASC, u.first_name ASC',
        default           => 'active_containers DESC',
    };

    $users = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url,
                u.department,
                r.name as role_name,
                COUNT(DISTINCT CASE WHEN cs.status IN ('active','low') THEN cs.id END) as active_containers,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_stock_count,
                COUNT(DISTINCT CASE WHEN cs.status = 'expired' THEN cs.id END) as expiring_count,
                COALESCE(SUM(CASE WHEN cs.status IN ('active','low') THEN cs.remaining_qty END), 0) as total_quantity,
                COALESCE(AVG(CASE WHEN cs.status IN ('active','low') THEN cs.remaining_pct END), 0) as avg_remaining
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id
         WHERE {$where}
         GROUP BY u.id
         ORDER BY {$orderBy}",
        $params
    );

    // Resolve store info from lab_stores for each user
    $users = resolveUserStores($users);

    // Get store_name list for filter dropdown (from lab_stores)
    $stores = Database::fetchAll(
        "SELECT DISTINCT store_name FROM lab_stores WHERE is_active = 1 AND store_name IS NOT NULL AND store_name != '' ORDER BY store_name"
    );

    // Get division list for filter dropdown (from lab_stores)
    $divisions = Database::fetchAll(
        "SELECT DISTINCT division_name FROM lab_stores WHERE is_active = 1 AND division_name IS NOT NULL AND division_name != '' ORDER BY division_name"
    );

    // Summary stats
    $totalUsers = count($users);
    $totalContainers = array_sum(array_column($users, 'active_containers'));
    $usersWithChemicals = count(array_filter($users, fn($u) => $u['active_containers'] > 0));
    $totalLowStock = array_sum(array_column($users, 'low_stock_count'));
    $totalExpiring = array_sum(array_column($users, 'expiring_count'));

    return [
        'users' => $users,
        'stores' => $stores,
        'divisions' => $divisions,
        'stats' => [
            'total_users' => $totalUsers,
            'total_containers' => $totalContainers,
            'users_with_chemicals' => $usersWithChemicals,
            'total_low_stock' => $totalLowStock,
            'total_expiring' => $totalExpiring,
        ]
    ];
}

/**
 * Get detailed containers for a specific user
 */
function getUserContainers(int $userId): array {
    $containers = Database::fetchAll(
        "SELECT cs.id, cs.bottle_code, cs.bottle_code as qr_code, cs.status,
                cs.remaining_qty as current_quantity,
                cs.package_size as initial_quantity,
                cs.unit as quantity_unit,
                cs.remaining_pct as remaining_percentage,
                'bottle' as container_type,
                cs.grade,
                NULL as expiry_date,
                cs.storage_location as location_path,
                cs.added_at as received_date,
                COALESCE(c.name, cs.chemical_name) as chemical_name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                c.molecular_formula
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         WHERE cs.owner_user_id = :user_id AND cs.status IN ('active','low')
         ORDER BY cs.chemical_name ASC, cs.id ASC",
        [':user_id' => $userId]
    );

    $userInfo = Database::fetch(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url,
                u.department,
                r.name as role_name
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.id = :id",
        [':id' => $userId]
    );

    // Resolve store info for this user
    if ($userInfo) {
        $resolved = resolveUserStores([$userInfo]);
        $userInfo = $resolved[0];
    }

    return [
        'user' => $userInfo,
        'containers' => $containers,
        'total' => count($containers)
    ];
}
