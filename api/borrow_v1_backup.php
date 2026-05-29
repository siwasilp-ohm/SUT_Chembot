<?php
/**
 * Borrow/Loan & Transfer System API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $request = getBorrowRequest((int)$_GET['id'], $user);
                echo json_encode(['success' => true, 'data' => $request]);
            } else {
                $requests = listBorrowRequests($_GET, $user);
                echo json_encode(['success' => true, 'data' => $requests]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'create';
            
            switch ($action) {
                case 'create':
                    $id = createBorrowRequest($data, $user);
                    echo json_encode(['success' => true, 'data' => ['id' => $id, 'message' => 'Request created successfully']]);
                    break;
                case 'approve':
                    approveRequest($data['request_id'], $data, $user);
                    echo json_encode(['success' => true, 'message' => 'Request approved']);
                    break;
                case 'fulfill':
                    fulfillRequest($data['request_id'], $data, $user);
                    echo json_encode(['success' => true, 'message' => 'Request fulfilled']);
                    break;
                case 'return':
                    returnRequest($data['request_id'], $data, $user);
                    echo json_encode(['success' => true, 'message' => 'Request returned']);
                    break;
                case 'reject':
                    rejectRequest($data['request_id'], $data, $user);
                    echo json_encode(['success' => true, 'message' => 'Request rejected']);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateBorrowRequest((int)$_GET['id'], $data, $user);
            echo json_encode(['success' => true, 'message' => 'Request updated']);
            break;
            
        case 'DELETE':
            cancelBorrowRequest((int)$_GET['id'], $user);
            echo json_encode(['success' => true, 'message' => 'Request cancelled']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function createBorrowRequest(array $data, array $user): int {
    $required = ['chemical_id', 'requested_quantity', 'quantity_unit', 'purpose'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("{$field} is required");
        }
    }
    
    $requestNumber = 'BRW-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    
    $requestData = [
        'request_number' => $requestNumber,
        'requester_id' => $user['id'],
        'owner_id' => $data['owner_id'] ?? null,
        'container_id' => $data['container_id'] ?? null,
        'chemical_id' => $data['chemical_id'],
        'request_type' => $data['request_type'] ?? 'borrow',
        'requested_quantity' => $data['requested_quantity'],
        'quantity_unit' => $data['quantity_unit'],
        'purpose' => $data['purpose'],
        'experiment_id' => $data['experiment_id'] ?? null,
        'project_name' => $data['project_name'] ?? null,
        'needed_by_date' => $data['needed_by_date'] ?? null,
        'expected_return_date' => $data['expected_return_date'] ?? null
    ];
    
    $id = Database::insert('borrow_requests', $requestData);
    
    // Create notification for owner/lab manager
    createBorrowNotification($id, $data['owner_id'] ?? null, $user);
    
    return $id;
}

function getBorrowRequest(int $id, array $user): array {
    $request = Database::fetch(
        "SELECT br.*, 
                c.name as chemical_name, c.cas_number,
                req.first_name as requester_first, req.last_name as requester_last,
                own.first_name as owner_first, own.last_name as owner_last,
                fc.qr_code as fulfilled_container_qr
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users req ON br.requester_id = req.id
         LEFT JOIN users own ON br.owner_id = own.id
         LEFT JOIN containers fc ON br.fulfilled_container_id = fc.id
         WHERE br.id = :id",
        [':id' => $id]
    );
    
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    // Check permission
    if ($user['role_name'] !== 'admin' && 
        $user['role_name'] !== 'lab_manager' &&
        $request['requester_id'] !== $user['id'] &&
        $request['owner_id'] !== $user['id']) {
        throw new Exception('Permission denied');
    }
    
    return $request;
}

function listBorrowRequests(array $filters, array $user): array {
    $where = ['1=1'];
    $params = [];
    
    // Role-based filtering
    if ($user['role_name'] === 'user') {
        $where[] = "(br.requester_id = :user_id OR br.owner_id = :user_id)";
        $params[':user_id'] = $user['id'];
    } elseif ($user['role_name'] === 'lab_manager') {
        $where[] = "(req.lab_id = :lab_id OR own.lab_id = :lab_id)";
        $params[':lab_id'] = $user['lab_id'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "br.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['chemical_id'])) {
        $where[] = "br.chemical_id = :chemical_id";
        $params[':chemical_id'] = (int)$filters['chemical_id'];
    }
    
    if (!empty($filters['overdue'])) {
        $where[] = "br.status IN ('fulfilled', 'partially_returned') AND br.expected_return_date < CURDATE()";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $page = (int)($filters['page'] ?? 1);
    $perPage = (int)($filters['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;
    
    $requests = Database::fetchAll(
        "SELECT br.id, br.request_number, br.status, br.requested_quantity, 
                br.quantity_unit, br.expected_return_date, br.created_at,
                c.name as chemical_name, c.cas_number,
                req.first_name as requester_first, req.last_name as requester_last,
                own.first_name as owner_first, own.last_name as owner_last
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users req ON br.requester_id = req.id
         LEFT JOIN users own ON br.owner_id = own.id
         WHERE {$whereClause}
         ORDER BY br.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );
    
    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM borrow_requests br
         JOIN users req ON br.requester_id = req.id
         LEFT JOIN users own ON br.owner_id = own.id
         WHERE {$whereClause}",
        $params
    )['count'];
    
    return [
        'data' => $requests,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

function approveRequest(int $requestId, array $data, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $requestId]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    // Check permission to approve
    if ($user['role_name'] !== 'admin' && 
        $user['role_name'] !== 'lab_manager' &&
        $request['owner_id'] !== $user['id']) {
        throw new Exception('Permission denied');
    }
    
    Database::update('borrow_requests', [
        'status' => 'approved',
        'approved_by' => $user['id'],
        'approved_at' => date('Y-m-d H:i:s'),
        'approval_notes' => $data['notes'] ?? null
    ], 'id = :id', [':id' => $requestId]);
    
    // Notify requester
    createApprovalNotification($requestId, $request['requester_id'], true);
}

function fulfillRequest(int $requestId, array $data, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $requestId]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    if (empty($data['container_id'])) {
        throw new Exception('Container ID required for fulfillment');
    }
    
    // Get container
    $container = Database::fetch(
        "SELECT * FROM containers WHERE id = :id",
        [':id' => $data['container_id']]
    );
    
    if (!$container) {
        throw new Exception('Container not found');
    }
    
    // Check if enough quantity
    if ($container['current_quantity'] < $request['requested_quantity']) {
        throw new Exception('Insufficient quantity in container');
    }
    
    // Update container quantity
    $newQuantity = $container['current_quantity'] - $request['requested_quantity'];
    Database::update('containers', [
        'current_quantity' => $newQuantity,
        'owner_id' => $request['requester_id'] // Transfer ownership for borrow
    ], 'id = :id', [':id' => $data['container_id']]);
    
    // Log container history
    Database::insert('container_history', [
        'container_id' => $data['container_id'],
        'action_type' => 'borrowed',
        'user_id' => $user['id'],
        'from_user_id' => $container['owner_id'],
        'to_user_id' => $request['requester_id'],
        'quantity_change' => -$request['requested_quantity'],
        'quantity_after' => $newQuantity,
        'notes' => 'Borrowed: ' . $request['request_number']
    ]);
    
    // Update request
    Database::update('borrow_requests', [
        'status' => 'fulfilled',
        'fulfilled_container_id' => $data['container_id'],
        'fulfilled_quantity' => $request['requested_quantity'],
        'fulfilled_by' => $user['id'],
        'fulfilled_at' => date('Y-m-d H:i:s')
    ], 'id = :id', [':id' => $requestId]);
}

function returnRequest(int $requestId, array $data, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $requestId]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    $returnedQty = $data['returned_quantity'] ?? $request['fulfilled_quantity'];
    
    // Update container - return quantity
    $container = Database::fetch(
        "SELECT * FROM containers WHERE id = :id",
        [':id' => $request['fulfilled_container_id']]
    );
    
    if ($container) {
        $newQuantity = $container['current_quantity'] + $returnedQty;
        Database::update('containers', [
            'current_quantity' => min($newQuantity, $container['initial_quantity']),
            'owner_id' => $request['owner_id'] // Return to original owner
        ], 'id = :id', [':id' => $request['fulfilled_container_id']]);
        
        Database::insert('container_history', [
            'container_id' => $request['fulfilled_container_id'],
            'action_type' => 'returned',
            'user_id' => $user['id'],
            'from_user_id' => $request['requester_id'],
            'to_user_id' => $request['owner_id'],
            'quantity_change' => $returnedQty,
            'quantity_after' => $newQuantity,
            'notes' => 'Returned: ' . $request['request_number']
        ]);
    }
    
    // Determine final status
    $status = ($returnedQty >= $request['fulfilled_quantity']) ? 'returned' : 'partially_returned';
    
    Database::update('borrow_requests', [
        'status' => $status,
        'returned_quantity' => $returnedQty,
        'return_condition' => $data['return_condition'] ?? 'good',
        'return_notes' => $data['notes'] ?? null,
        'actual_return_date' => date('Y-m-d')
    ], 'id = :id', [':id' => $requestId]);
}

function rejectRequest(int $requestId, array $data, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $requestId]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    Database::update('borrow_requests', [
        'status' => 'rejected',
        'approval_notes' => $data['reason'] ?? null
    ], 'id = :id', [':id' => $requestId]);
    
    createApprovalNotification($requestId, $request['requester_id'], false);
}

function updateBorrowRequest(int $id, array $data, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $id]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    // Only requester can update pending requests
    if ($request['requester_id'] !== $user['id'] || $request['status'] !== 'pending') {
        throw new Exception('Cannot update this request');
    }
    
    $updateData = [];
    $allowedFields = ['requested_quantity', 'purpose', 'needed_by_date', 'expected_return_date'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    Database::update('borrow_requests', $updateData, 'id = :id', [':id' => $id]);
}

function cancelBorrowRequest(int $id, array $user): void {
    $request = Database::fetch("SELECT * FROM borrow_requests WHERE id = :id", [':id' => $id]);
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    // Only requester can cancel pending requests
    if ($request['requester_id'] !== $user['id'] || !in_array($request['status'], ['pending', 'approved'])) {
        throw new Exception('Cannot cancel this request');
    }
    
    Database::update('borrow_requests', ['status' => 'cancelled'], 'id = :id', [':id' => $id]);
}

function createBorrowNotification(int $requestId, ?int $ownerId, array $requester): void {
    $message = "New borrow request from {$requester['first_name']} {$requester['last_name']}";
    
    Database::insert('alerts', [
        'alert_type' => 'custom',
        'title' => 'New Borrow Request',
        'message' => $message,
        'user_id' => $ownerId,
        'borrow_request_id' => $requestId,
        'action_required' => true
    ]);
}

function createApprovalNotification(int $requestId, int $userId, bool $approved): void {
    $message = $approved ? 'Your borrow request has been approved' : 'Your borrow request has been rejected';
    
    Database::insert('alerts', [
        'alert_type' => 'custom',
        'title' => $approved ? 'Request Approved' : 'Request Rejected',
        'message' => $message,
        'user_id' => $userId,
        'borrow_request_id' => $requestId
    ]);
}
