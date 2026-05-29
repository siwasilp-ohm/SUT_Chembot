<?php
/**
 * Containers API - QR Generation, AR, Inventory Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/qr_generator.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['qr'])) {
                // Get container by QR code
                $container = getContainerByQR($_GET['qr']);
                echo json_encode(['success' => true, 'data' => $container]);
            } elseif (isset($_GET['id'])) {
                $container = getContainerDetails((int)$_GET['id']);
                echo json_encode(['success' => true, 'data' => $container]);
            } else {
                $containers = listContainers($_GET, $user);
                echo json_encode(['success' => true, 'data' => $containers]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = createContainer($data, $user);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($_GET['id'] ?? 0);
            updateContainer($id, $data, $user);
            echo json_encode(['success' => true, 'message' => 'Container updated successfully']);
            break;
            
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            deleteContainer($id, $user);
            echo json_encode(['success' => true, 'message' => 'Container deleted successfully']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getContainerByQR(string $qrCode): array {
    $container = Database::fetch(
        "SELECT cn.*, c.name as chemical_name, c.cas_number, c.hazard_pictograms,
                c.signal_word, c.ghs_classifications, c.sds_url,
                u.first_name, u.last_name, l.name as lab_name,
                sl.name as slot_name, sh.name as shelf_name, cab.name as cabinet_name,
                r.name as room_name, b.name as building_name
         FROM containers cn
         JOIN chemicals c ON cn.chemical_id = c.id
         JOIN users u ON cn.owner_id = u.id
         JOIN labs l ON cn.lab_id = l.id
         LEFT JOIN slots sl ON cn.location_slot_id = sl.id
         LEFT JOIN shelves sh ON sl.shelf_id = sh.id
         LEFT JOIN cabinets cab ON sh.cabinet_id = cab.id
         LEFT JOIN rooms r ON cab.room_id = r.id
         LEFT JOIN buildings b ON r.building_id = b.id
         WHERE cn.qr_code = :qr",
        [':qr' => $qrCode]
    );
    
    if (!$container) {
        throw new Exception('Container not found');
    }
    
    // Parse JSON fields
    $container['hazard_pictograms'] = json_decode($container['hazard_pictograms'] ?? '[]', true);
    $container['ghs_classifications'] = json_decode($container['ghs_classifications'] ?? '[]', true);
    
    // Get 3D model info
    $container['ar_data'] = getARData($container);
    
    return $container;
}

function getContainerDetails(int $id): array {
    $container = Database::fetch(
        "SELECT cn.*, c.*, u.first_name, u.last_name, l.name as lab_name
         FROM containers cn
         JOIN chemicals c ON cn.chemical_id = c.id
         JOIN users u ON cn.owner_id = u.id
         JOIN labs l ON cn.lab_id = l.id
         WHERE cn.id = :id",
        [':id' => $id]
    );
    
    if (!$container) {
        throw new Exception('Container not found');
    }
    
    // Get location hierarchy
    $container['location'] = Database::fetch(
        "SELECT b.name as building, r.name as room, cab.name as cabinet, 
                cab.type as cabinet_type, sh.name as shelf, sl.name as slot,
                cab.position_x, cab.position_y, cab.width, cab.height,
                sl.position_x as slot_x, sl.position_y as slot_y
         FROM slots sl
         JOIN shelves sh ON sl.shelf_id = sh.id
         JOIN cabinets cab ON sh.cabinet_id = cab.id
         JOIN rooms r ON cab.room_id = r.id
         JOIN buildings b ON r.building_id = b.id
         WHERE sl.id = :slot_id",
        [':slot_id' => $container['location_slot_id']]
    );
    
    // Get history
    $container['history'] = Database::fetchAll(
        "SELECT ch.*, u.first_name, u.last_name
         FROM container_history ch
         JOIN users u ON ch.user_id = u.id
         WHERE ch.container_id = :id
         ORDER BY ch.created_at DESC
         LIMIT 50",
        [':id' => $id]
    );
    
    return $container;
}

function listContainers(array $filters, array $user): array {
    $where = ['cn.is_active = 1'];
    $params = [];
    
    // Role-based filtering
    if ($user['role_name'] === 'user') {
        $where[] = "(cn.owner_id = :user_id OR cn.lab_id = :lab_id)";
        $params[':user_id'] = $user['id'];
        $params[':lab_id'] = $user['lab_id'];
    } elseif ($user['role_name'] === 'lab_manager') {
        $where[] = "cn.lab_id = :lab_id";
        $params[':lab_id'] = $user['lab_id'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(c.name LIKE :search OR cn.qr_code LIKE :search2 OR cn.bottle_code LIKE :search3)";
        $params[':search'] = '%' . $filters['search'] . '%';
        $params[':search2'] = '%' . $filters['search'] . '%';
        $params[':search3'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['chemical_id'])) {
        $where[] = "cn.chemical_id = :chemical_id";
        $params[':chemical_id'] = (int)$filters['chemical_id'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "cn.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['owner_id'])) {
        $where[] = "cn.owner_id = :owner_id";
        $params[':owner_id'] = (int)$filters['owner_id'];
    }
    
    if (!empty($filters['expiring_soon'])) {
        $where[] = "cn.expiry_date IS NOT NULL AND cn.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)";
        $params[':days'] = (int)$filters['expiring_soon'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $page = (int)($filters['page'] ?? 1);
    $perPage = (int)($filters['limit'] ?? $filters['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;
    
    $containers = Database::fetchAll(
        "SELECT cn.id, cn.qr_code, cn.bottle_code, cn.container_type, cn.current_quantity, 
                cn.initial_quantity, cn.quantity_unit, cn.remaining_percentage, cn.status, 
                cn.expiry_date, cn.grade, cn.cost,
                c.name as chemical_name, c.cas_number, c.hazard_pictograms,
                u.first_name, u.last_name, u.full_name_th,
                COALESCE(u.full_name_th, CONCAT(u.first_name, ' ', u.last_name)) as owner_name,
                l.name as lab_name,
                m.name as manufacturer_name
         FROM containers cn
         JOIN chemicals c ON cn.chemical_id = c.id
         JOIN users u ON cn.owner_id = u.id
         JOIN labs l ON cn.lab_id = l.id
         LEFT JOIN manufacturers m ON cn.manufacturer_id = m.id
         WHERE {$whereClause}
         ORDER BY cn.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );
    
    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM containers cn 
         JOIN chemicals c ON cn.chemical_id = c.id
         WHERE {$whereClause}",
        $params
    )['count'];
    
    return [
        'data' => $containers,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

function createContainer(array $data, array $user): array {
    // Support creating container by chemical_name (find-or-create)
    $chemicalId = $data['chemical_id'] ?? null;
    
    if (!$chemicalId && !empty($data['chemical_name'])) {
        // Try to find existing chemical by name
        $existing = Database::fetch(
            "SELECT id FROM chemicals WHERE name = :name LIMIT 1",
            [':name' => trim($data['chemical_name'])]
        );
        if ($existing) {
            $chemicalId = $existing['id'];
        } else {
            // Create new chemical
            $chemicalId = Database::insert('chemicals', [
                'name' => trim($data['chemical_name']),
                'cas_number' => $data['cas_number'] ?? null,
                'physical_state' => $data['physical_state'] ?? 'liquid',
                'created_by' => $user['id']
            ]);
        }
    }
    
    if (!$chemicalId) {
        throw new Exception('chemical_name or chemical_id is required');
    }
    
    $initialQty = $data['initial_quantity'] ?? null;
    if (!$initialQty || $initialQty <= 0) {
        throw new Exception('initial_quantity is required');
    }
    
    $unit = $data['quantity_unit'] ?? 'mL';
    
    // Generate bottle code if not provided
    $bottleCode = $data['bottle_code'] ?? null;
    if (empty($bottleCode)) {
        $lastCode = Database::fetch(
            "SELECT bottle_code FROM containers WHERE bottle_code LIKE 'BTL-%' ORDER BY id DESC LIMIT 1"
        );
        $nextNum = 1;
        if ($lastCode && preg_match('/BTL-(\d+)/', $lastCode['bottle_code'], $m)) {
            $nextNum = intval($m[1]) + 1;
        }
        $bottleCode = 'BTL-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
    
    // Generate QR code
    $qrCode = generateUniqueQRCode();
    $qrImagePath = null;
    try {
        $qrImagePath = QRGenerator::generate($qrCode, $chemicalId);
    } catch (Exception $e) {
        // QR generation is optional, don't block container creation
    }
    
    // Resolve manufacturer_id
    $manufacturerId = null;
    if (!empty($data['manufacturer'])) {
        $mfr = Database::fetch(
            "SELECT id FROM manufacturers WHERE name = :name LIMIT 1",
            [':name' => trim($data['manufacturer'])]
        );
        if ($mfr) {
            $manufacturerId = $mfr['id'];
        } else {
            $manufacturerId = Database::insert('manufacturers', [
                'name' => trim($data['manufacturer'])
            ]);
        }
    }
    
    $currentQty = $data['current_quantity'] ?? $initialQty;
    $pct = ($currentQty / $initialQty) * 100;
    
    $containerData = [
        'qr_code' => $qrCode,
        'qr_code_image' => $qrImagePath,
        'chemical_id' => $chemicalId,
        'owner_id' => $data['owner_id'] ?? $user['id'],
        'lab_id' => $data['lab_id'] ?? $user['lab_id'],
        'bottle_code' => $bottleCode,
        'container_type' => $data['container_type'] ?? 'bottle',
        'container_material' => $data['container_material'] ?? 'glass',
        'initial_quantity' => $initialQty,
        'current_quantity' => $currentQty,
        'quantity_unit' => $unit,
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
    ];
    
    $id = Database::insert('containers', $containerData);
    
    // Log history
    Database::insert('container_history', [
        'container_id' => $id,
        'action_type' => 'created',
        'user_id' => $user['id'],
        'quantity_after' => $initialQty,
        'notes' => 'Container created: ' . $bottleCode
    ]);
    
    return [
        'id' => $id,
        'bottle_code' => $bottleCode,
        'qr_code' => $qrCode,
        'message' => 'Container created successfully'
    ];
}

function updateContainer(int $id, array $data, array $user): void {
    $container = Database::fetch("SELECT * FROM containers WHERE id = :id", [':id' => $id]);
    if (!$container) {
        throw new Exception('Container not found');
    }
    
    // Check permission
    if ($user['role_name'] !== 'admin' && 
        $user['role_name'] !== 'lab_manager' && 
        $container['owner_id'] !== $user['id']) {
        throw new Exception('Permission denied');
    }
    
    $updateData = [];
    $allowedFields = ['location_slot_id', 'current_quantity', 'status', 'quality_status', 
                      'opened_date', 'expiry_date', 'notes', 'container_3d_model'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    Database::update('containers', $updateData, 'id = :id', [':id' => $id]);
    
    // Log history
    if (isset($data['current_quantity']) && $data['current_quantity'] != $container['current_quantity']) {
        Database::insert('container_history', [
            'container_id' => $id,
            'action_type' => 'used',
            'user_id' => $user['id'],
            'quantity_change' => $data['current_quantity'] - $container['current_quantity'],
            'quantity_after' => $data['current_quantity'],
            'notes' => $data['usage_notes'] ?? 'Quantity updated'
        ]);
    }
    
    if (isset($data['location_slot_id']) && $data['location_slot_id'] != $container['location_slot_id']) {
        Database::insert('container_history', [
            'container_id' => $id,
            'action_type' => 'moved',
            'user_id' => $user['id'],
            'from_location_id' => $container['location_slot_id'],
            'to_location_id' => $data['location_slot_id'],
            'notes' => 'Location updated'
        ]);
    }
}

function deleteContainer(int $id, array $user): void {
    $container = Database::fetch("SELECT * FROM containers WHERE id = :id", [':id' => $id]);
    if (!$container) {
        throw new Exception('Container not found');
    }
    
    // Check permission
    if ($user['role_name'] !== 'admin' && 
        $user['role_name'] !== 'lab_manager' && 
        $container['owner_id'] !== $user['id']) {
        throw new Exception('Permission denied');
    }
    
    Database::update('containers', ['status' => 'disposed'], 'id = :id', [':id' => $id]);
    
    Database::insert('container_history', [
        'container_id' => $id,
        'action_type' => 'disposed',
        'user_id' => $user['id'],
        'notes' => 'Container disposed'
    ]);
}

function generateUniqueQRCode(): string {
    $prefix = 'CHEM';
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    return "{$prefix}-{$timestamp}-{$random}";
}

function getARData(array $container): array {
    // Get appropriate 3D model
    $model = Database::fetch(
        "SELECT * FROM container_3d_models 
         WHERE container_type = :type AND material = :material
         AND is_default = 1
         LIMIT 1",
        [':type' => $container['container_type'], ':material' => $container['container_material']]
    );
    
    return [
        'container_3d_model' => $container['container_3d_model'] ?? $model['glb_file_path'] ?? null,
        'usdz_model' => $model['usdz_file_path'] ?? null,
        'remaining_level' => (float)$container['remaining_percentage'],
        'hazard_labels' => json_decode($container['hazard_pictograms'] ?? '[]', true),
        'cas_number' => $container['cas_number'],
        'signal_word' => $container['signal_word'],
        'location_highlight' => true
    ];
}
