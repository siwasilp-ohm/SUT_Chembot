<?php
/**
 * Dashboard API - Role-based Analytics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$user = Auth::requireAuth();
$role = $user['role_name'];

try {
    // Drill-down: lab detail by department
    $action = $_GET['action'] ?? '';
    if ($action === 'lab_detail') {
        $dept = $_GET['dept'] ?? '';
        if (!$dept) { echo json_encode(['success' => false, 'error' => 'Missing dept']); exit; }
        echo json_encode(['success' => true, 'data' => getLabDetail($dept)]);
        exit;
    }

    $dashboard = [];
    
    switch ($role) {
        case 'admin':
        case 'ceo':
            $dashboard = getOrgWideDashboard($user);
            break;
        case 'lab_manager':
            $dashboard = getLabManagerDashboard($user);
            break;
        case 'user':
            $dashboard = getUserDashboard($user);
            break;
        case 'visitor':
            $dashboard = getVisitorDashboard();
            break;
        default:
            $dashboard = getUserDashboard($user);
    }
    
    echo json_encode(['success' => true, 'data' => $dashboard]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/* ═══════════════════════════════════════
   Lab Detail drill-down (by department)
   ═══════════════════════════════════════ */
function getLabDetail(string $dept): array {
    // Summary stats for this department
    $summary = Database::fetch(
        "SELECT COUNT(DISTINCT cs.id) as total_bottles,
                COUNT(DISTINCT CASE WHEN cs.status IN ('active','low') THEN cs.id END) as active_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'expired' THEN cs.id END) as expired_bottles,
                COUNT(DISTINCT cs.chemical_id) as unique_chemicals,
                COUNT(DISTINCT u.id) as user_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_remaining_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id
         WHERE u.is_active = 1 AND u.department = :dept",
        [':dept' => $dept]
    );

    // Members with their stock counts
    $members = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.position,
                COUNT(DISTINCT cs.id) as bottle_count,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
         WHERE u.is_active = 1 AND u.department = :dept
         GROUP BY u.id
         ORDER BY bottle_count DESC",
        [':dept' => $dept]
    );

    // Top chemicals in this department (grouped by chemical)
    $chemicals = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                c.molecular_formula,
                COUNT(cs.id) as bottle_count,
                SUM(cs.remaining_qty) as total_remaining,
                SUM(cs.package_size) as total_capacity,
                cs.unit,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct,
                MIN(cs.remaining_pct) as min_pct
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept AND cs.status IN ('active','low')
         GROUP BY COALESCE(cs.chemical_id, cs.id)
         ORDER BY bottle_count DESC
         LIMIT 20",
        [':dept' => $dept]
    );

    // Recent transactions in this department
    $transactions = Database::fetchAll(
        "SELECT ct.id, ct.txn_number, ct.txn_type, ct.quantity, ct.unit,
                ct.purpose, ct.status, ct.created_at,
                COALESCE(c.name, cs.chemical_name) as chemical_name,
                ui.first_name as initiated_first, ui.last_name as initiated_last,
                uf.first_name as from_first, uf.last_name as from_last,
                ut.first_name as to_first, ut.last_name as to_last
         FROM chemical_transactions ct
         JOIN chemical_stock cs ON ct.source_id = cs.id AND ct.source_type = 'stock'
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         LEFT JOIN users ui ON ct.initiated_by = ui.id
         LEFT JOIN users uf ON ct.from_user_id = uf.id
         LEFT JOIN users ut ON ct.to_user_id = ut.id
         WHERE EXISTS (SELECT 1 FROM users u2 WHERE u2.id = cs.owner_user_id AND u2.department = :dept)
         ORDER BY ct.created_at DESC
         LIMIT 15",
        [':dept' => $dept]
    );

    // Overdue borrows
    $overdue = Database::fetchAll(
        "SELECT br.id, br.request_number, br.requested_quantity, br.quantity_unit,
                br.expected_return_date,
                DATEDIFF(CURDATE(), br.expected_return_date) as days_overdue,
                c.name as chemical_name,
                u.first_name, u.last_name
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users u ON br.requester_id = u.id
         WHERE u.department = :dept AND br.status = 'overdue'
         ORDER BY br.expected_return_date ASC",
        [':dept' => $dept]
    );

    return [
        'department' => $dept,
        'summary' => $summary,
        'members' => $members,
        'chemicals' => $chemicals,
        'transactions' => $transactions,
        'overdue' => $overdue
    ];
}

function getOrgWideDashboard(array $user): array {
    $orgId = $user['organization_id'] ?? 1;
    
    // Overall totals — use chemical_stock as primary data source
    $totals = Database::fetch(
        "SELECT 
            (SELECT COUNT(*) FROM chemicals WHERE is_active = 1) as total_chemicals,
            (SELECT COUNT(*) FROM chemical_stock WHERE status IN ('active','low')) as active_containers,
            (SELECT COUNT(*) FROM chemical_stock WHERE status = 'expired') as expired_containers,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM lab_stores WHERE is_active = 1) as total_labs"
    );
    
    // Top consumed chemicals — chemicals with most used quantity
    $topConsumed = Database::fetchAll(
        "SELECT c.name, c.cas_number,
                COUNT(cs.id) as usage_count,
                SUM(cs.package_size - cs.remaining_qty) as total_consumed
         FROM chemical_stock cs
         JOIN chemicals c ON cs.chemical_id = c.id
         WHERE cs.status IN ('active','low','empty')
           AND cs.remaining_qty < cs.package_size
         GROUP BY cs.chemical_id
         ORDER BY total_consumed DESC
         LIMIT 10"
    );
    
    // Low stock bottles from chemical_stock
    $lowStock = Database::fetchAll(
        "SELECT cs.id as container_id, cs.bottle_code, cs.bottle_code as qr_code,
                COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                cs.remaining_qty as current_quantity, cs.unit as quantity_unit,
                cs.package_size as initial_quantity, cs.remaining_pct as remaining_percentage,
                'bottle' as container_type,
                NULL as expiry_date, cs.storage_location as location_path, cs.grade,
                cs.storage_location as lab_name,
                u.first_name, u.last_name
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         LEFT JOIN users u ON cs.owner_user_id = u.id
         WHERE cs.status IN ('active','low') AND cs.remaining_pct <= 20
         ORDER BY cs.remaining_pct ASC
         LIMIT 50"
    );
    
    // Lab/Store performance — group by division (department)
    $labPerformance = Database::fetchAll(
        "SELECT u.department as name,
                COUNT(DISTINCT cs.id) as container_count,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT br.id) as borrow_requests,
                COUNT(DISTINCT CASE WHEN br.status = 'overdue' THEN br.id END) as overdue_borrows
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
         LEFT JOIN borrow_requests br ON br.requester_id = u.id
         WHERE u.is_active = 1 AND u.department IS NOT NULL AND u.department != ''
         GROUP BY u.department
         ORDER BY container_count DESC"
    );
    
    // Expiring soon — chemical_stock with status='expired'
    $expiringSoon = Database::fetchAll(
        "SELECT cs.id as container_id, cs.bottle_code, cs.bottle_code as qr_code,
                COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                NULL as expiry_date,
                cs.remaining_qty as current_quantity, cs.unit as quantity_unit,
                cs.remaining_pct as remaining_percentage,
                'bottle' as container_type,
                cs.storage_location as location_path, cs.grade,
                cs.storage_location as lab_name,
                u.first_name as owner_first, u.last_name as owner_last,
                0 as days_until_expiry
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         LEFT JOIN users u ON cs.owner_user_id = u.id
         WHERE cs.status = 'expired'
         ORDER BY cs.updated_at DESC
         LIMIT 50"
    );
    
    // Compliance status
    $compliance = Database::fetch(
        "SELECT 
            COUNT(CASE WHEN check_result = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN check_result = 'warning' THEN 1 END) as warnings,
            COUNT(CASE WHEN check_result = 'fail' THEN 1 END) as failed
         FROM compliance_checks
         WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    // Monthly usage trend — from chemical_transactions
    $usageTrend = Database::fetchAll(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as transactions,
                SUM(ABS(quantity)) as total_quantity
         FROM chemical_transactions
         WHERE txn_type IN ('use','dispose') AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
         GROUP BY month
         ORDER BY month ASC"
    );
    
    return [
        'role' => 'admin',
        'scope' => 'organization_wide',
        'summary' => $totals,
        'top_consumed' => $topConsumed,
        'low_stock' => $lowStock,
        'lab_performance' => $labPerformance,
        'expiring_soon' => $expiringSoon,
        'compliance_status' => $compliance,
        'usage_trend' => $usageTrend,
        'alerts' => getRecentAlerts($user['id'], 10)
    ];
}

function getLabManagerDashboard(array $user): array {
    $userId = $user['id'];
    $dept = $user['department'] ?? '';
    
    // Chemicals under management — from chemical_stock via department users
    $chemicals = Database::fetch(
        "SELECT 
            COUNT(DISTINCT cs.id) as total_containers,
            COUNT(DISTINCT CASE WHEN cs.status IN ('active','low') THEN cs.id END) as active_containers,
            COUNT(DISTINCT CASE WHEN cs.status = 'expired' THEN cs.id END) as expired_containers,
            COUNT(DISTINCT cs.chemical_id) as unique_chemicals,
            COUNT(DISTINCT cs.chemical_id) as total_chemicals,
            (SELECT COUNT(DISTINCT u2.id) FROM users u2 WHERE u2.is_active = 1 AND u2.department = :dept2) as team_members,
            (SELECT COUNT(*) FROM chemical_stock cs2 
             JOIN users u3 ON cs2.owner_user_id = u3.id 
             WHERE u3.department = :dept3 AND cs2.status = 'expired') as expiring_soon,
            (SELECT COUNT(*) FROM borrow_requests WHERE owner_id = :user_id AND status = 'pending') as pending_requests
         FROM chemical_stock cs
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept",
        [':dept' => $dept, ':dept2' => $dept, ':dept3' => $dept, ':user_id' => $userId]
    );
    
    // Make summary compatible with admin format
    $chemicals['total_users'] = $chemicals['team_members'];
    $chemicals['total_labs'] = 1;
    
    // Expiring chemicals — expired status in chemical_stock for this department
    $expiring = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name, cs.id,
                NULL as expiry_date,
                cs.remaining_qty as current_quantity, cs.unit as quantity_unit,
                u.first_name, u.last_name,
                0 as days_left
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept
         AND cs.status = 'expired'
         ORDER BY cs.updated_at DESC",
        [':dept' => $dept]
    );
    
    // Borrowed chemicals by team members
    $borrowed = Database::fetchAll(
        "SELECT c.name, br.requested_quantity, br.quantity_unit,
                br.expected_return_date, u.first_name, u.last_name,
                DATEDIFF(br.expected_return_date, CURDATE()) as days_remaining
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users u ON br.requester_id = u.id
         WHERE u.department = :dept
         AND br.status IN ('fulfilled', 'partially_returned')
         ORDER BY br.expected_return_date ASC",
        [':dept' => $dept]
    );
    
    // Pending borrow requests
    $pendingRequests = Database::fetchAll(
        "SELECT br.id, br.request_number, c.name as chemical_name,
                br.requested_quantity, br.quantity_unit, br.purpose,
                u.first_name, u.last_name, br.created_at
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users u ON br.requester_id = u.id
         WHERE u.department = :dept AND br.status = 'pending'
         ORDER BY br.created_at DESC",
        [':dept' => $dept]
    );
    
    // Chemicals to reorder (low stock) — from chemical_stock
    $toReorder = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                SUM(cs.remaining_qty) as total_quantity,
                cs.unit as quantity_unit,
                COUNT(cs.id) as container_count,
                AVG(cs.remaining_pct) as avg_remaining
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept AND cs.status IN ('active','low')
         GROUP BY COALESCE(cs.chemical_id, cs.id)
         HAVING avg_remaining <= 25
         ORDER BY avg_remaining ASC",
        [':dept' => $dept]
    );
    
    // Team member activity — from chemical_stock
    $teamActivity = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.avatar_url,
                COUNT(DISTINCT cs.id) as owned_containers,
                COUNT(DISTINCT br.id) as borrow_count,
                COUNT(DISTINCT CASE WHEN br.status = 'overdue' THEN br.id END) as overdue_count
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
         LEFT JOIN borrow_requests br ON br.requester_id = u.id
         WHERE u.department = :dept AND u.is_active = 1
         GROUP BY u.id
         ORDER BY owned_containers DESC",
        [':dept' => $dept]
    );
    
    return [
        'role' => 'lab_manager',
        'scope' => 'lab_wide',
        'summary' => $chemicals,
        'expiring_chemicals' => $expiring,
        'borrowed_chemicals' => $borrowed,
        'pending_requests' => $pendingRequests,
        'chemicals_to_reorder' => $toReorder,
        'low_stock' => $toReorder,
        'team_activity' => $teamActivity,
        'recent_activity' => getRecentLabActivity($dept),
        'alerts' => getRecentAlerts($user['id'], 10)
    ];
}

function getRecentLabActivity(string $dept): array {
    return Database::fetchAll(
        "SELECT ct.*, u.first_name, u.last_name,
                COALESCE(c.name, cs.chemical_name) as chemical_name,
                CONCAT(u.first_name, ' ', u.last_name) as description,
                ct.txn_type as action_type
         FROM chemical_transactions ct
         JOIN chemical_stock cs ON ct.source_id = cs.id AND ct.source_type = 'stock'
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON ct.initiated_by = u.id
         WHERE u.department = :dept
         ORDER BY ct.created_at DESC
         LIMIT 10",
        [':dept' => $dept]
    );
}

function getUserDashboard(array $user): array {
    $userId = $user['id'];
    
    // My chemicals — from chemical_stock
    $myChemicals = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                cs.id, cs.remaining_qty as current_quantity,
                cs.unit as quantity_unit, cs.status,
                NULL as expiry_date,
                cs.storage_location as location_name
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         WHERE cs.owner_user_id = :user_id AND cs.status IN ('active','low')
         ORDER BY cs.created_at DESC
         LIMIT 20",
        [':user_id' => $userId]
    );
    
    // Borrow history
    $borrowHistory = Database::fetchAll(
        "SELECT br.id, c.name as chemical_name, br.requested_quantity,
                br.quantity_unit, br.status, br.expected_return_date,
                br.actual_return_date, br.created_at
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         WHERE br.requester_id = :user_id
         ORDER BY br.created_at DESC
         LIMIT 10",
        [':user_id' => $userId]
    );
    
    // Pending my requests
    $myPending = Database::fetchAll(
        "SELECT br.id, c.name, br.requested_quantity, br.quantity_unit,
                br.status, br.created_at
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         WHERE br.requester_id = :user_id AND br.status = 'pending'
         ORDER BY br.created_at DESC",
        [':user_id' => $userId]
    );
    
    // Requests for my chemicals (if any)
    $requestsForMe = Database::fetchAll(
        "SELECT br.id, c.name, br.requested_quantity, br.quantity_unit,
                u.first_name, u.last_name, br.purpose, br.created_at
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users u ON br.requester_id = u.id
         WHERE br.owner_id = :user_id AND br.status = 'pending'
         ORDER BY br.created_at DESC",
        [':user_id' => $userId]
    );
    
    // Quick stats — from chemical_stock
    $stats = Database::fetch(
        "SELECT 
            (SELECT COUNT(*) FROM chemical_stock WHERE owner_user_id = :user_id AND status IN ('active','low')) as owned_count,
            (SELECT COUNT(*) FROM borrow_requests WHERE requester_id = :user_id2 AND status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM borrow_requests WHERE requester_id = :user_id3 AND status IN ('fulfilled', 'partially_returned')) as active_borrows",
        [':user_id' => $userId, ':user_id2' => $userId, ':user_id3' => $userId]
    );
    
    return [
        'role' => 'user',
        'scope' => 'personal',
        'my_chemicals' => $myChemicals,
        'borrow_history' => $borrowHistory,
        'my_pending_requests' => $myPending,
        'requests_for_me' => $requestsForMe,
        'quick_stats' => $stats,
        'alerts' => getRecentAlerts($user['id'], 10)
    ];
}

function getVisitorDashboard(): array {
    // Public chemical information only
    $popularChemicals = Database::fetchAll(
        "SELECT c.name, c.cas_number, c.molecular_formula, c.physical_state,
                c.description, c.ghs_classifications
         FROM chemicals c
         WHERE c.is_active = 1 AND c.verified = 1
         ORDER BY c.created_at DESC
         LIMIT 20"
    );
    
    return [
        'role' => 'visitor',
        'scope' => 'public',
        'message' => 'Welcome to SUT chemBot. Please log in for full access.',
        'popular_chemicals' => $popularChemicals
    ];
}

function getRecentAlerts(int $userId, int $limit = 10): array {
    return Database::fetchAll(
        "SELECT a.*, c.name as chemical_name
         FROM alerts a
         LEFT JOIN chemicals c ON a.chemical_id = c.id
         WHERE a.user_id = :user_id AND a.is_read = 0 AND a.dismissed = 0
         ORDER BY a.created_at DESC
         LIMIT :limit",
        [':user_id' => $userId, ':limit' => $limit]
    );
}
