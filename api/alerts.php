<?php
/**
 * Alerts & Notifications API
 * Supports admin scope=all for monitoring all users' alerts
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

// Helper: check if user is admin/manager
function isAdminUser(array $user): bool {
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    return $roleLevel >= 4 || in_array($user['role_name'] ?? '', ['admin', 'ceo']);
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $alert = getAlert((int)$_GET['id'], $user);
                echo json_encode(['success' => true, 'data' => $alert]);
            } elseif (isset($_GET['stats']) && $_GET['stats'] === '1') {
                $stats = getAlertStats($user);
                echo json_encode(['success' => true, 'data' => $stats]);
            } else {
                $alerts = listAlerts($_GET, $user);
                echo json_encode(['success' => true, 'data' => $alerts]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';
            
            switch ($action) {
                case 'mark_read':
                    markAsRead($data['alert_ids'], $user);
                    echo json_encode(['success' => true, 'message' => 'Alerts marked as read']);
                    break;
                case 'dismiss':
                    dismissAlerts($data['alert_ids'], $user);
                    echo json_encode(['success' => true, 'message' => 'Alerts dismissed']);
                    break;
                case 'bulk_delete':
                    if (!isAdminUser($user)) throw new Exception('Permission denied');
                    bulkDeleteAlerts($data['alert_ids']);
                    echo json_encode(['success' => true, 'message' => 'Alerts deleted']);
                    break;
                case 'create':
                    Auth::requirePermission('alerts.manage');
                    $id = createAlert($data);
                    echo json_encode(['success' => true, 'data' => ['id' => $id]]);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateAlert((int)$_GET['id'], $data, $user);
            echo json_encode(['success' => true, 'message' => 'Alert updated']);
            break;
            
        case 'DELETE':
            deleteAlert((int)$_GET['id'], $user);
            echo json_encode(['success' => true, 'message' => 'Alert deleted']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getAlert(int $id, array $user): array {
    $isAdmin = isAdminUser($user);
    
    $sql = "SELECT a.*, c.name as chemical_name, cn.qr_code,
                   u.first_name as user_first_name, u.last_name as user_last_name, 
                   u.full_name_th as user_full_name_th, u.username as user_username
            FROM alerts a
            LEFT JOIN chemicals c ON a.chemical_id = c.id
            LEFT JOIN containers cn ON a.container_id = cn.id
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.id = :id";
    
    $params = [':id' => $id];
    if (!$isAdmin) {
        $sql .= " AND a.user_id = :user_id";
        $params[':user_id'] = $user['id'];
    }
    
    $alert = Database::fetch($sql, $params);
    
    if (!$alert) {
        throw new Exception('Alert not found');
    }
    
    return $alert;
}

function listAlerts(array $filters, array $user): array {
    $isAdmin = isAdminUser($user);
    $scope = $filters['scope'] ?? 'own'; // 'own' or 'all'
    
    $where = [];
    $params = [];
    
    // If scope=all and admin, don't filter by user_id
    if ($scope === 'all' && $isAdmin) {
        // No user_id filter — admin sees all
    } else {
        $where[] = 'a.user_id = :user_id';
        $params[':user_id'] = $user['id'];
    }
    
    // Filter by specific target user (admin only)
    if (!empty($filters['target_user_id']) && $isAdmin) {
        $where[] = 'a.user_id = :target_user_id';
        $params[':target_user_id'] = (int)$filters['target_user_id'];
    }
    
    if (isset($filters['unread_only']) && $filters['unread_only']) {
        $where[] = 'a.is_read = 0';
    }
    
    if (isset($filters['read_status'])) {
        if ($filters['read_status'] === 'read') {
            $where[] = 'a.is_read = 1';
        } elseif ($filters['read_status'] === 'unread') {
            $where[] = 'a.is_read = 0';
        }
    }
    
    if (isset($filters['dismissed']) && $filters['dismissed'] === 'false') {
        $where[] = 'a.dismissed = 0';
    }
    
    if (!empty($filters['type'])) {
        $where[] = 'a.alert_type = :type';
        $params[':type'] = $filters['type'];
    }
    
    if (!empty($filters['severity'])) {
        $where[] = 'a.severity = :severity';
        $params[':severity'] = $filters['severity'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = '(a.title LIKE :search OR a.message LIKE :search2)';
        $params[':search'] = '%' . $filters['search'] . '%';
        $params[':search2'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = 'a.created_at >= :date_from';
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = 'a.created_at <= :date_to';
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    $whereClause = $where ? implode(' AND ', $where) : '1=1';
    
    // Sorting
    $sortField = $filters['sort'] ?? 'created_at';
    $sortDir = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
    $allowedSorts = ['created_at', 'severity', 'alert_type', 'is_read', 'title'];
    if (!in_array($sortField, $allowedSorts)) $sortField = 'created_at';
    // Custom severity sort order: critical > warning > info
    $orderBy = $sortField === 'severity' 
        ? "FIELD(a.severity, 'critical', 'warning', 'info') {$sortDir}" 
        : "a.{$sortField} {$sortDir}";
    
    $page = (int)($filters['page'] ?? 1);
    $perPage = (int)($filters['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;
    
    // Get total count first
    $totalRow = Database::fetch(
        "SELECT COUNT(*) as total FROM alerts a WHERE {$whereClause}",
        $params
    );
    $total = (int)($totalRow['total'] ?? 0);
    
    $alerts = Database::fetchAll(
        "SELECT a.*, c.name as chemical_name,
                u.first_name as user_first_name, u.last_name as user_last_name,
                u.full_name_th as user_full_name_th, u.username as user_username,
                u.avatar_url as user_avatar
         FROM alerts a
         LEFT JOIN chemicals c ON a.chemical_id = c.id
         LEFT JOIN users u ON a.user_id = u.id
         WHERE {$whereClause}
         ORDER BY {$orderBy}
         LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );
    
    $unreadCount = Database::fetch(
        "SELECT COUNT(*) as count FROM alerts WHERE user_id = :user_id AND is_read = 0 AND dismissed = 0",
        [':user_id' => $user['id']]
    )['count'];
    
    return [
        'data' => $alerts,
        'unread_count' => (int)$unreadCount,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

function getAlertStats(array $user): array {
    if (!isAdminUser($user)) throw new Exception('Permission denied');
    
    $total = Database::fetch("SELECT COUNT(*) as c FROM alerts")['c'];
    $unread = Database::fetch("SELECT COUNT(*) as c FROM alerts WHERE is_read = 0")['c'];
    $critical = Database::fetch("SELECT COUNT(*) as c FROM alerts WHERE severity = 'critical' AND is_read = 0 AND dismissed = 0")['c'];
    $actionRequired = Database::fetch("SELECT COUNT(*) as c FROM alerts WHERE action_required = 1 AND (action_taken IS NULL OR action_taken = '') AND dismissed = 0")['c'];
    
    $byType = Database::fetchAll(
        "SELECT alert_type, COUNT(*) as count, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
         FROM alerts WHERE dismissed = 0 GROUP BY alert_type ORDER BY count DESC"
    );
    
    $bySeverity = Database::fetchAll(
        "SELECT severity, COUNT(*) as count FROM alerts WHERE dismissed = 0 GROUP BY severity"
    );
    
    $recentUsers = Database::fetchAll(
        "SELECT a.user_id, u.full_name_th, u.first_name, u.last_name, u.username,
                COUNT(*) as alert_count, SUM(CASE WHEN a.is_read = 0 THEN 1 ELSE 0 END) as unread_count
         FROM alerts a JOIN users u ON a.user_id = u.id
         WHERE a.dismissed = 0
         GROUP BY a.user_id ORDER BY unread_count DESC, alert_count DESC LIMIT 10"
    );
    
    return [
        'total' => (int)$total,
        'unread' => (int)$unread,
        'critical' => (int)$critical,
        'action_required' => (int)$actionRequired,
        'by_type' => $byType,
        'by_severity' => $bySeverity,
        'top_users' => $recentUsers
    ];
}

function _buildInParams(array $ids): array {
    $placeholders = [];
    $params = [];
    foreach (array_values($ids) as $i => $id) {
        $k = ':id' . $i;
        $placeholders[] = $k;
        $params[$k] = (int)$id;
    }
    return [implode(',', $placeholders), $params];
}

function markAsRead(array $alertIds, array $user): void {
    if (empty($alertIds)) return;
    [$in, $params] = _buildInParams($alertIds);
    if (isAdminUser($user)) {
        Database::query("UPDATE alerts SET is_read=1, read_at=NOW() WHERE id IN ($in)", $params);
    } else {
        $params[':uid'] = (int)$user['id'];
        Database::query("UPDATE alerts SET is_read=1, read_at=NOW() WHERE id IN ($in) AND user_id=:uid", $params);
    }
}

function dismissAlerts(array $alertIds, array $user): void {
    if (empty($alertIds)) return;
    [$in, $params] = _buildInParams($alertIds);
    if (isAdminUser($user)) {
        Database::query("UPDATE alerts SET dismissed=1 WHERE id IN ($in)", $params);
    } else {
        $params[':uid'] = (int)$user['id'];
        Database::query("UPDATE alerts SET dismissed=1 WHERE id IN ($in) AND user_id=:uid", $params);
    }
}

function bulkDeleteAlerts(array $alertIds): void {
    if (empty($alertIds)) return;
    [$in, $params] = _buildInParams($alertIds);
    Database::query("DELETE FROM alerts WHERE id IN ($in)", $params);
}

function createAlert(array $data): int {
    return Database::insert('alerts', [
        'alert_type' => $data['alert_type'],
        'severity' => $data['severity'] ?? 'info',
        'title' => $data['title'],
        'message' => $data['message'],
        'user_id' => $data['user_id'],
        'chemical_id' => $data['chemical_id'] ?? null,
        'container_id' => $data['container_id'] ?? null,
        'lab_id' => $data['lab_id'] ?? null,
        'borrow_request_id' => $data['borrow_request_id'] ?? null,
        'action_required' => $data['action_required'] ?? false
    ]);
}

function updateAlert(int $id, array $data, array $user): void {
    $alert = Database::fetch("SELECT * FROM alerts WHERE id = :id", [':id' => $id]);
    if (!$alert) {
        throw new Exception('Alert not found');
    }
    
    if ($alert['user_id'] !== $user['id'] && !isAdminUser($user)) {
        throw new Exception('Permission denied');
    }
    
    $updateData = [];
    if (isset($data['is_read'])) {
        $updateData['is_read'] = $data['is_read'];
        $updateData['read_at'] = $data['is_read'] ? date('Y-m-d H:i:s') : null;
    }
    if (isset($data['dismissed'])) {
        $updateData['dismissed'] = $data['dismissed'];
    }
    if (isset($data['action_taken'])) {
        $updateData['action_taken'] = $data['action_taken'];
        $updateData['action_taken_by'] = $user['id'];
        $updateData['action_taken_at'] = date('Y-m-d H:i:s');
    }
    
    Database::update('alerts', $updateData, 'id = :id', [':id' => $id]);
}

function deleteAlert(int $id, array $user): void {
    $alert = Database::fetch("SELECT * FROM alerts WHERE id = :id", [':id' => $id]);
    if (!$alert) {
        throw new Exception('Alert not found');
    }
    
    if ($alert['user_id'] !== $user['id'] && !isAdminUser($user)) {
        throw new Exception('Permission denied');
    }
    
    Database::delete('alerts', 'id = :id', [':id' => $id]);
}

/**
 * Generate alerts for expiring chemicals
 * This function should be called by a cron job daily
 */
function generateExpiryAlerts(): void {
    $expiring = Database::fetchAll(
        "SELECT cn.id, cn.chemical_id, cn.owner_id, cn.expiry_date, cn.expiry_alert_days,
                c.name as chemical_name
         FROM containers cn
         JOIN chemicals c ON cn.chemical_id = c.id
         WHERE cn.status = 'active'
         AND cn.expiry_date IS NOT NULL
         AND cn.expiry_date <= DATE_ADD(CURDATE(), INTERVAL cn.expiry_alert_days DAY)
         AND NOT EXISTS (
             SELECT 1 FROM alerts a 
             WHERE a.container_id = cn.id 
             AND a.alert_type = 'expiry'
             AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         )"
    );
    
    foreach ($expiring as $container) {
        $daysUntilExpiry = (new DateTime($container['expiry_date']))->diff(new DateTime())->days;
        
        Database::insert('alerts', [
            'alert_type' => 'expiry',
            'severity' => $daysUntilExpiry <= 7 ? 'critical' : 'warning',
            'title' => 'Chemical Expiring Soon',
            'message' => "{$container['chemical_name']} expires in {$daysUntilExpiry} days",
            'user_id' => $container['owner_id'],
            'chemical_id' => $container['chemical_id'],
            'container_id' => $container['id'],
            'action_required' => true
        ]);
    }
}

/**
 * Generate alerts for low stock
 */
function generateLowStockAlerts(): void {
    $lowStock = Database::fetchAll(
        "SELECT c.id as chemical_id, c.name as chemical_name, cn.owner_id,
                AVG(cn.remaining_percentage) as avg_remaining
         FROM containers cn
         JOIN chemicals c ON cn.chemical_id = c.id
         WHERE cn.status = 'active'
         GROUP BY c.id, cn.owner_id
         HAVING avg_remaining <= 20
         AND NOT EXISTS (
             SELECT 1 FROM alerts a 
             WHERE a.chemical_id = c.id 
             AND a.alert_type = 'low_stock'
             AND a.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
         )"
    );
    
    foreach ($lowStock as $item) {
        Database::insert('alerts', [
            'alert_type' => 'low_stock',
            'severity' => $item['avg_remaining'] <= 10 ? 'critical' : 'warning',
            'title' => 'Low Stock Alert',
            'message' => "{$item['chemical_name']} is at " . round($item['avg_remaining'], 1) . "% remaining",
            'user_id' => $item['owner_id'],
            'chemical_id' => $item['chemical_id'],
            'action_required' => true
        ]);
    }
}

/**
 * Generate alerts for overdue borrows
 */
function generateOverdueAlerts(): void {
    $overdue = Database::fetchAll(
        "SELECT br.id, br.requester_id, br.expected_return_date, 
                c.name as chemical_name, c.id as chemical_id
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         WHERE br.status IN ('fulfilled', 'partially_returned')
         AND br.expected_return_date < CURDATE()
         AND br.overdue_notice_sent = 0"
    );
    
    foreach ($overdue as $borrow) {
        $daysOverdue = (new DateTime())->diff(new DateTime($borrow['expected_return_date']))->days;
        
        Database::insert('alerts', [
            'alert_type' => 'overdue_borrow',
            'severity' => 'critical',
            'title' => 'Overdue Borrow',
            'message' => "{$borrow['chemical_name']} is {$daysOverdue} days overdue",
            'user_id' => $borrow['requester_id'],
            'chemical_id' => $borrow['chemical_id'],
            'borrow_request_id' => $borrow['id'],
            'action_required' => true
        ]);
        
        Database::update('borrow_requests', 
            ['overdue_notice_sent' => 1], 
            'id = :id', 
            [':id' => $borrow['id']]
        );
    }
}
