<?php
/**
 * Chemicals API - CRUD Operations with GHS Support
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
                // Get single chemical with details
                $chemical = getChemicalDetails((int)$_GET['id']);
                echo json_encode(['success' => true, 'data' => $chemical]);
            } else {
                // List chemicals with filters
                $chemicals = listChemicals($_GET);
                echo json_encode(['success' => true, 'data' => $chemicals]);
            }
            break;
            
        case 'POST':
            Auth::requirePermission('chemicals.manage');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = createChemical($data, $user['id']);
            echo json_encode(['success' => true, 'data' => ['id' => $id, 'message' => 'Chemical created successfully']]);
            break;
            
        case 'PUT':
            Auth::requirePermission('chemicals.manage');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($_GET['id'] ?? 0);
            updateChemical($id, $data, $user['id']);
            echo json_encode(['success' => true, 'message' => 'Chemical updated successfully']);
            break;
            
        case 'DELETE':
            Auth::requirePermission('chemicals.manage');
            $id = (int)($_GET['id'] ?? 0);
            deleteChemical($id);
            echo json_encode(['success' => true, 'message' => 'Chemical deleted successfully']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getChemicalDetails(int $id): array {
    $chemical = Database::fetch(
        "SELECT c.*, cat.name as category_name,
                (SELECT COUNT(*) FROM containers WHERE chemical_id = c.id AND status = 'active') as active_containers,
                (SELECT SUM(current_quantity) FROM containers WHERE chemical_id = c.id AND status = 'active') as total_stock
         FROM chemicals c
         LEFT JOIN chemical_categories cat ON c.category_id = cat.id
         WHERE c.id = :id",
        [':id' => $id]
    );
    
    if (!$chemical) {
        throw new Exception('Chemical not found');
    }
    
    // Parse JSON fields
    $jsonFields = ['synonyms', 'ghs_classifications', 'hazard_pictograms', 'hazard_statements', 
                   'precautionary_statements', 'safety_info', 'incompatible_chemicals'];
    foreach ($jsonFields as $field) {
        $chemical[$field] = json_decode($chemical[$field] ?? '[]', true);
    }
    
    // Get suppliers
    $chemical['suppliers'] = Database::fetchAll(
        "SELECT * FROM chemical_suppliers WHERE chemical_id = :id",
        [':id' => $id]
    );
    
    // Get active containers
    $chemical['containers'] = Database::fetchAll(
        "SELECT cn.*, u.first_name, u.last_name, l.name as lab_name
         FROM containers cn
         JOIN users u ON cn.owner_id = u.id
         JOIN labs l ON cn.lab_id = l.id
         WHERE cn.chemical_id = :id AND cn.status = 'active'
         ORDER BY cn.created_at DESC",
        [':id' => $id]
    );
    
    return $chemical;
}

function listChemicals(array $filters): array {
    $where = ['c.is_active = 1'];
    $params = [];
    
    if (!empty($filters['search'])) {
        $where[] = "(c.name LIKE :search OR c.cas_number LIKE :search OR c.iupac_name LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['category'])) {
        $where[] = "c.category_id = :category";
        $params[':category'] = (int)$filters['category'];
    }
    
    if (!empty($filters['physical_state'])) {
        $where[] = "c.physical_state = :state";
        $params[':state'] = $filters['physical_state'];
    }
    
    if (!empty($filters['hazard_class'])) {
        $where[] = "JSON_CONTAINS(c.ghs_classifications, :hazard)";
        $params[':hazard'] = '"' . $filters['hazard_class'] . '"';
    }
    
    if (!empty($filters['lab_id'])) {
        $where[] = "EXISTS (SELECT 1 FROM containers cn WHERE cn.chemical_id = c.id AND cn.lab_id = :lab_id)";
        $params[':lab_id'] = (int)$filters['lab_id'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $page = (int)($filters['page'] ?? 1);
    $perPage = (int)($filters['limit'] ?? $filters['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;
    
    $chemicals = Database::fetchAll(
        "SELECT c.id, c.cas_number, c.name, c.iupac_name, c.molecular_formula,
                c.physical_state, c.hazard_pictograms, c.signal_word,
                c.image_url, cat.name as category_name,
                (SELECT COUNT(*) FROM containers WHERE chemical_id = c.id AND status = 'active') as container_count
         FROM chemicals c
         LEFT JOIN chemical_categories cat ON c.category_id = cat.id
         WHERE {$whereClause}
         ORDER BY c.name ASC
         LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );
    
    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM chemicals c WHERE {$whereClause}",
        $params
    )['count'];
    
    return [
        'data' => $chemicals,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

function createChemical(array $data, int $userId): int {
    $required = ['name', 'cas_number'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("{$field} is required");
        }
    }
    
    // Check for duplicate CAS
    $existing = Database::fetch("SELECT id FROM chemicals WHERE cas_number = :cas", [':cas' => $data['cas_number']]);
    if ($existing) {
        throw new Exception('Chemical with this CAS number already exists');
    }
    
    $chemicalData = [
        'cas_number' => $data['cas_number'],
        'name' => $data['name'],
        'iupac_name' => $data['iupac_name'] ?? null,
        'synonyms' => json_encode($data['synonyms'] ?? []),
        'molecular_formula' => $data['molecular_formula'] ?? null,
        'molecular_weight' => $data['molecular_weight'] ?? null,
        'description' => $data['description'] ?? null,
        'category_id' => $data['category_id'] ?? null,
        'physical_state' => $data['physical_state'] ?? 'solid',
        'appearance' => $data['appearance'] ?? null,
        'ghs_classifications' => json_encode($data['ghs_classifications'] ?? []),
        'hazard_pictograms' => json_encode($data['hazard_pictograms'] ?? []),
        'signal_word' => $data['signal_word'] ?? 'No signal word',
        'hazard_statements' => json_encode($data['hazard_statements'] ?? []),
        'precautionary_statements' => json_encode($data['precautionary_statements'] ?? []),
        'sds_url' => $data['sds_url'] ?? null,
        'handling_procedures' => $data['handling_procedures'] ?? null,
        'storage_requirements' => $data['storage_requirements'] ?? null,
        'disposal_methods' => $data['disposal_methods'] ?? null,
        'first_aid_measures' => $data['first_aid_measures'] ?? null,
        'incompatible_chemicals' => json_encode($data['incompatible_chemicals'] ?? []),
        'storage_compatibility_group' => $data['storage_compatibility_group'] ?? null,
        'image_url' => $data['image_url'] ?? null,
        'model_3d_url' => $data['model_3d_url'] ?? null,
        'created_by' => $userId
    ];
    
    return Database::insert('chemicals', $chemicalData);
}

function updateChemical(int $id, array $data, int $userId): void {
    $chemical = Database::fetch("SELECT id FROM chemicals WHERE id = :id", [':id' => $id]);
    if (!$chemical) {
        throw new Exception('Chemical not found');
    }
    
    $updateData = [];
    $jsonFields = ['synonyms', 'ghs_classifications', 'hazard_pictograms', 
                   'hazard_statements', 'precautionary_statements', 
                   'incompatible_chemicals'];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $jsonFields)) {
            $updateData[$key] = json_encode($value);
        } else {
            $updateData[$key] = $value;
        }
    }
    
    Database::update('chemicals', $updateData, 'id = :id', [':id' => $id]);
    
    // Log audit
    Database::insert('audit_logs', [
        'table_name' => 'chemicals',
        'record_id' => $id,
        'action' => 'UPDATE',
        'new_values' => json_encode($updateData),
        'user_id' => $userId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

function deleteChemical(int $id): void {
    // Check if chemical has active containers
    $containers = Database::fetch(
        "SELECT COUNT(*) as count FROM containers WHERE chemical_id = :id AND status = 'active'",
        [':id' => $id]
    )['count'];
    
    if ($containers > 0) {
        throw new Exception('Cannot delete chemical with active containers');
    }
    
    Database::update('chemicals', ['is_active' => 0], 'id = :id', [':id' => $id]);
}
