<?php
/**
 * Chemicals Master API - Full CRUD + SDS/GHS/Files Management
 * Supports: chemicals, GHS data, SDS files, manufacturers
 * Role-based: Admin/Manager = full CRUD, Users = upload files (delete own only)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = Auth::requireAuth();
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;

try {
    switch ($method) {
        case 'GET': handleGet($action, $user); break;
        case 'POST': handlePost($action, $user, $isAdmin, $isManager); break;
        case 'PUT': handlePut($action, $user, $isAdmin, $isManager); break;
        case 'DELETE': handleDelete($action, $user, $isAdmin, $isManager); break;
        default: throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code >= 400 && $code < 600) http_response_code($code);
    else http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════
// GET Handlers
// ═══════════════════════════════════════════════════════
function handleGet($action, $user) {
    switch ($action) {
        case 'stats': getStats(); break;
        case 'list': listChemicals($_GET); break;
        case 'detail': getChemicalDetail((int)($_GET['id'] ?? 0)); break;
        case 'ghs': getGhsData((int)($_GET['chemical_id'] ?? 0)); break;
        case 'files': getFiles((int)($_GET['chemical_id'] ?? 0)); break;
        case 'categories': getCategories(); break;
        case 'manufacturers': getManufacturers(); break;
        case 'search': quickSearch($_GET['q'] ?? ''); break;
        case 'export': exportChemicals($_GET); break;
        case 'packaging': getPackaging((int)($_GET['chemical_id'] ?? 0)); break;
        default:
            // Legacy support
            if (isset($_GET['id'])) { getChemicalDetail((int)$_GET['id']); }
            elseif (isset($_GET['categories'])) { getCategories(); }
            else { listChemicals($_GET); }
    }
}

function getStats() {
    $stats = Database::fetch("
        SELECT 
            COUNT(*) as total_chemicals,
            COUNT(DISTINCT cas_number) as unique_cas,
            COUNT(CASE WHEN physical_state='solid' THEN 1 END) as solid_count,
            COUNT(CASE WHEN physical_state='liquid' THEN 1 END) as liquid_count,
            COUNT(CASE WHEN physical_state='gas' THEN 1 END) as gas_count,
            COUNT(DISTINCT manufacturer_id) as manufacturer_count,
            COUNT(CASE WHEN substance_type='HomogeneousSubstance' THEN 1 END) as homogeneous,
            COUNT(CASE WHEN substance_type='HeterogenousSubstance' THEN 1 END) as heterogeneous
        FROM chemicals WHERE is_active = 1
    ");
    $stats['sds_files'] = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
    $stats['ghs_records'] = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data")['c'];
    $stats['with_sds'] = (int)Database::fetch("SELECT COUNT(DISTINCT chemical_id) as c FROM chemical_sds_files")['c'];
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function listChemicals($filters) {
    $where = ['c.is_active = 1'];
    $params = [];

    if (!empty($filters['search'])) {
        $s = '%' . $filters['search'] . '%';
        $where[] = "(c.name LIKE :s1 OR c.cas_number LIKE :s2 OR c.catalogue_number LIKE :s3 OR c.substance_category LIKE :s4 OR c.iupac_name LIKE :s5)";
        $params[':s1'] = $s; $params[':s2'] = $s; $params[':s3'] = $s; $params[':s4'] = $s; $params[':s5'] = $s;
    }
    if (!empty($filters['state'])) {
        $where[] = "c.physical_state = :state";
        $params[':state'] = $filters['state'];
    }
    if (!empty($filters['substance_type'])) {
        $where[] = "c.substance_type = :stype";
        $params[':stype'] = $filters['substance_type'];
    }
    if (!empty($filters['manufacturer_id'])) {
        $where[] = "c.manufacturer_id = :mfr";
        $params[':mfr'] = (int)$filters['manufacturer_id'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = "c.category_id = :cat";
        $params[':cat'] = (int)$filters['category_id'];
    }
    if (!empty($filters['has_cas'])) {
        $where[] = ($filters['has_cas'] === '1' ? "c.cas_number IS NOT NULL AND c.cas_number != ''" : "(c.cas_number IS NULL OR c.cas_number = '')");
    }
    if (!empty($filters['has_ghs'])) {
        $where[] = ($filters['has_ghs'] === '1'
            ? "EXISTS(SELECT 1 FROM chemical_ghs_data WHERE chemical_id = c.id)"
            : "NOT EXISTS(SELECT 1 FROM chemical_ghs_data WHERE chemical_id = c.id)");
    }
    if (!empty($filters['has_sds'])) {
        $where[] = ($filters['has_sds'] === '1'
            ? "EXISTS(SELECT 1 FROM chemical_sds_files WHERE chemical_id = c.id)"
            : "NOT EXISTS(SELECT 1 FROM chemical_sds_files WHERE chemical_id = c.id)");
    }

    $whereClause = implode(' AND ', $where);
    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int)($filters['limit'] ?? $filters['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $sortMap = ['name'=>'c.name','cas'=>'c.cas_number','state'=>'c.physical_state','date'=>'c.created_at','manufacturer'=>'m.name'];
    $sortCol = $sortMap[$filters['sort'] ?? 'name'] ?? 'c.name';
    $sortDir = (strtoupper($filters['dir'] ?? 'ASC') === 'DESC') ? 'DESC' : 'ASC';

    $chemicals = Database::fetchAll("
        SELECT c.id, c.cas_number, c.name, c.iupac_name, c.molecular_formula, c.molecular_weight,
               c.physical_state, c.substance_type, c.substance_category, c.catalogue_number,
               c.hazard_pictograms, c.signal_word, c.image_url, c.verified, c.created_at,
               m.name as manufacturer_name, cat.name as category_name,
               (SELECT COUNT(*) FROM containers WHERE chemical_id = c.id AND status = 'active') as container_count,
               (SELECT COUNT(*) FROM chemical_sds_files WHERE chemical_id = c.id) as sds_count,
               (EXISTS(SELECT 1 FROM chemical_ghs_data WHERE chemical_id = c.id)) as has_ghs
        FROM chemicals c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN chemical_categories cat ON c.category_id = cat.id
        WHERE {$whereClause}
        ORDER BY {$sortCol} {$sortDir}
        LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );

    $total = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals c LEFT JOIN manufacturers m ON c.manufacturer_id = m.id WHERE {$whereClause}", $params)['c'];

    echo json_encode(['success' => true, 'data' => [
        'chemicals' => $chemicals,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ]]);
}

function getChemicalDetail($id) {
    if (!$id) throw new Exception('ID required');
    $chemical = Database::fetch("
        SELECT c.*, m.name as manufacturer_name, cat.name as category_name,
               (SELECT COUNT(*) FROM containers WHERE chemical_id = c.id AND status = 'active') as active_containers,
               (SELECT SUM(current_quantity) FROM containers WHERE chemical_id = c.id AND status = 'active') as total_stock
        FROM chemicals c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN chemical_categories cat ON c.category_id = cat.id
        WHERE c.id = :id", [':id' => $id]);
    if (!$chemical) throw new Exception('Chemical not found');

    foreach (['synonyms','ghs_classifications','hazard_pictograms','hazard_statements','precautionary_statements','safety_info','incompatible_chemicals'] as $jf) {
        $chemical[$jf] = json_decode($chemical[$jf] ?? '[]', true);
    }

    $chemical['ghs'] = Database::fetch("SELECT * FROM chemical_ghs_data WHERE chemical_id = :id", [':id' => $id]);
    if ($chemical['ghs']) {
        foreach (['ghs_pictograms','h_statements','p_statements','ppe_required'] as $jf) {
            $chemical['ghs'][$jf] = json_decode($chemical['ghs'][$jf] ?? '[]', true);
        }
    }
    $chemical['sds_files'] = Database::fetchAll("
        SELECT sf.*, COALESCE(u.first_name,'') as first_name, COALESCE(u.last_name,'') as last_name 
        FROM chemical_sds_files sf
        LEFT JOIN users u ON sf.uploaded_by = u.id
        WHERE sf.chemical_id = :id ORDER BY sf.is_primary DESC, sf.created_at DESC", [':id' => $id]);
    $chemical['containers'] = Database::fetchAll("
        SELECT cn.id, cn.bottle_code as container_number, cn.current_quantity, cn.quantity_unit as unit,
               cn.status, cn.expiry_date, cn.container_type, cn.grade,
               u.first_name, u.last_name, l.name as lab_name
        FROM containers cn
        LEFT JOIN users u ON cn.owner_id = u.id
        LEFT JOIN labs l ON cn.lab_id = l.id
        WHERE cn.chemical_id = :id AND cn.status = 'active' ORDER BY cn.created_at DESC", [':id' => $id]);

    // Packaging templates
    $chemical['packaging'] = Database::fetchAll("
        SELECT cp.*, u.first_name as creator_first, u.last_name as creator_last
        FROM chemical_packaging cp
        LEFT JOIN users u ON cp.created_by = u.id
        WHERE cp.chemical_id = :id AND cp.is_active = 1
        ORDER BY cp.is_default DESC, cp.sort_order ASC, cp.capacity ASC", [':id' => $id]);

    echo json_encode(['success' => true, 'data' => $chemical]);
}

function getGhsData($chemicalId) {
    if (!$chemicalId) throw new Exception('chemical_id required');
    $ghs = Database::fetch("SELECT * FROM chemical_ghs_data WHERE chemical_id = :id", [':id' => $chemicalId]);
    if ($ghs) {
        foreach (['ghs_pictograms','h_statements','p_statements','ppe_required'] as $jf) {
            $ghs[$jf] = json_decode($ghs[$jf] ?? '[]', true);
        }
    }
    echo json_encode(['success' => true, 'data' => $ghs]);
}

function getFiles($chemicalId) {
    if (!$chemicalId) throw new Exception('chemical_id required');
    $files = Database::fetchAll("
        SELECT sf.*, u.first_name, u.last_name FROM chemical_sds_files sf
        JOIN users u ON sf.uploaded_by = u.id
        WHERE sf.chemical_id = :id ORDER BY sf.is_primary DESC, sf.created_at DESC", [':id' => $chemicalId]);
    echo json_encode(['success' => true, 'data' => $files]);
}

function getCategories() {
    $cats = Database::fetchAll("SELECT id, name FROM chemical_categories ORDER BY name");
    echo json_encode(['success' => true, 'data' => $cats]);
}

function getManufacturers() {
    $search = $_GET['q'] ?? '';
    $params = [];
    $where = $search ? "WHERE name LIKE :s" : "";
    if ($search) $params[':s'] = '%'.$search.'%';
    $mfrs = Database::fetchAll("SELECT id, name FROM manufacturers {$where} ORDER BY name LIMIT 50", $params);
    echo json_encode(['success' => true, 'data' => $mfrs]);
}

function quickSearch($q) {
    if (mb_strlen($q) < 2) { echo json_encode(['success'=>true,'data'=>[]]); return; }
    $s = '%'.$q.'%';
    $results = Database::fetchAll("
        SELECT id, name, cas_number, physical_state, substance_type,
               (SELECT m.name FROM manufacturers m WHERE m.id = c.manufacturer_id) as manufacturer_name
        FROM chemicals c WHERE c.is_active = 1 AND (c.name LIKE :s1 OR c.cas_number LIKE :s2 OR c.catalogue_number LIKE :s3)
        ORDER BY CASE WHEN c.cas_number LIKE :s4 THEN 0 WHEN c.name LIKE :s5 THEN 1 ELSE 2 END, c.name LIMIT 20",
        [':s1'=>$s,':s2'=>$s,':s3'=>$s,':s4'=>$s,':s5'=>$q.'%']);
    echo json_encode(['success'=>true,'data'=>$results]);
}

function exportChemicals($filters) {
    $where = ['c.is_active = 1']; $params = [];
    if (!empty($filters['search'])) { $where[] = "(c.name LIKE :s OR c.cas_number LIKE :s)"; $params[':s'] = '%'.$filters['search'].'%'; }
    $wc = implode(' AND ', $where);
    $data = Database::fetchAll("SELECT c.name, c.cas_number, m.name as manufacturer, c.catalogue_number, c.physical_state, c.substance_type, c.substance_category
        FROM chemicals c LEFT JOIN manufacturers m ON c.manufacturer_id = m.id WHERE {$wc} ORDER BY c.name LIMIT 10000", $params);
    echo json_encode(['success'=>true,'data'=>$data]);
}

// ═══════════════════════════════════════════════════════
// POST Handlers
// ═══════════════════════════════════════════════════════
function handlePost($action, $user, $isAdmin, $isManager) {
    switch ($action) {
        case 'create':
            if (!$isManager) throw new Exception('Permission denied', 403);
            createChemical($user); break;
        case 'ghs_save':
            if (!$isManager) throw new Exception('Permission denied', 403);
            saveGhsData($user); break;
        case 'upload_file':
            uploadFile($user); break;
        case 'add_file_url':
            addFileUrl($user); break;
        case 'update':
            if (!$isManager) throw new Exception('Permission denied', 403);
            updateChemicalPost($user); break;
        case 'packaging_save':
            if (!$isManager) throw new Exception('Permission denied', 403);
            savePackaging($user); break;
        default: throw new Exception('Invalid action');
    }
}

function createChemical($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['name'])) throw new Exception('Chemical name is required');
    if (!empty($data['cas_number'])) {
        $existing = Database::fetch("SELECT id FROM chemicals WHERE cas_number = :cas AND is_active = 1", [':cas' => $data['cas_number']]);
        if ($existing) throw new Exception('CAS number already exists (ID: '.$existing['id'].')');
    }
    $id = Database::insert('chemicals', [
        'name' => $data['name'], 'cas_number' => $data['cas_number'] ?? null,
        'iupac_name' => $data['iupac_name'] ?? null, 'catalogue_number' => $data['catalogue_number'] ?? null,
        'physical_state' => $data['physical_state'] ?? 'solid', 'substance_type' => $data['substance_type'] ?? null,
        'substance_category' => $data['substance_category'] ?? null, 'manufacturer_id' => $data['manufacturer_id'] ?? null,
        'category_id' => $data['category_id'] ?? null, 'molecular_formula' => $data['molecular_formula'] ?? null,
        'molecular_weight' => $data['molecular_weight'] ?? null, 'description' => $data['description'] ?? null,
        'created_by' => $user['id'], 'is_active' => 1, 'verified' => 0
    ]);
    echo json_encode(['success'=>true,'data'=>['id'=>$id,'message'=>'Chemical created']]);
}

function updateChemicalPost($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID required');
    if (!Database::fetch("SELECT id FROM chemicals WHERE id = :id", [':id' => $id])) throw new Exception('Chemical not found');
    $allowed = ['name','cas_number','iupac_name','catalogue_number','physical_state','substance_type',
                'substance_category','manufacturer_id','category_id','molecular_formula','molecular_weight',
                'description','appearance','odor','melting_point','boiling_point','density','solubility',
                'flash_point','handling_procedures','storage_requirements','disposal_methods',
                'first_aid_measures','fire_fighting_measures','image_url','sds_url','verified'];
    $updateData = [];
    foreach ($data as $k => $v) { if (in_array($k, $allowed)) $updateData[$k] = $v; }
    if (empty($updateData)) throw new Exception('No valid fields');
    Database::update('chemicals', $updateData, 'id = :id', [':id' => $id]);
    Database::insert('audit_logs', ['table_name'=>'chemicals','record_id'=>$id,'action'=>'UPDATE','new_values'=>json_encode($updateData),'user_id'=>$user['id'],'ip_address'=>$_SERVER['REMOTE_ADDR']??null]);
    echo json_encode(['success'=>true,'message'=>'Chemical updated']);
}

function saveGhsData($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $chemId = (int)($data['chemical_id'] ?? 0);
    if (!$chemId) throw new Exception('chemical_id required');
    $fields = [
        'chemical_id' => $chemId, 'signal_word' => $data['signal_word'] ?? 'None',
        'ghs_pictograms' => json_encode($data['ghs_pictograms'] ?? []),
        'h_statements' => json_encode($data['h_statements'] ?? []),
        'h_statements_text' => $data['h_statements_text'] ?? null,
        'p_statements' => json_encode($data['p_statements'] ?? []),
        'p_statements_text' => $data['p_statements_text'] ?? null,
        'un_number' => $data['un_number'] ?? null, 'transport_hazard_class' => $data['transport_hazard_class'] ?? null,
        'packing_group' => $data['packing_group'] ?? null, 'safety_summary' => $data['safety_summary'] ?? null,
        'handling_precautions' => $data['handling_precautions'] ?? null, 'storage_instructions' => $data['storage_instructions'] ?? null,
        'disposal_instructions' => $data['disposal_instructions'] ?? null,
        'first_aid_inhalation' => $data['first_aid_inhalation'] ?? null, 'first_aid_skin' => $data['first_aid_skin'] ?? null,
        'first_aid_eye' => $data['first_aid_eye'] ?? null, 'first_aid_ingestion' => $data['first_aid_ingestion'] ?? null,
        'suitable_extinguishing' => $data['suitable_extinguishing'] ?? null,
        'exposure_limits' => $data['exposure_limits'] ?? null, 'ppe_required' => json_encode($data['ppe_required'] ?? []),
        'ld50' => $data['ld50'] ?? null, 'lc50' => $data['lc50'] ?? null,
        'source' => $data['source'] ?? null, 'last_reviewed' => date('Y-m-d'),
        'reviewed_by' => $user['id'], 'notes' => $data['notes'] ?? null,
    ];
    $existing = Database::fetch("SELECT id FROM chemical_ghs_data WHERE chemical_id = :id", [':id' => $chemId]);
    if ($existing) {
        $upd = $fields; unset($upd['chemical_id']);
        Database::update('chemical_ghs_data', $upd, 'chemical_id = :id', [':id' => $chemId]);
    } else {
        Database::insert('chemical_ghs_data', $fields);
    }
    Database::update('chemicals', [
        'signal_word' => $data['signal_word'] ?? 'No signal word',
        'hazard_pictograms' => json_encode($data['ghs_pictograms'] ?? []),
    ], 'id = :id', [':id' => $chemId]);
    echo json_encode(['success'=>true,'message'=>'GHS data saved']);
}

function uploadFile($user) {
    $chemId = (int)($_POST['chemical_id'] ?? 0);
    if (!$chemId) throw new Exception('chemical_id required');
    if (empty($_FILES['file'])) throw new Exception('No file uploaded');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error code: ' . $file['error']);
    if ($file['size'] > 20*1024*1024) throw new Exception('File too large (max 20MB)');
    $uploadDir = __DIR__ . '/../uploads/sds/' . $chemId;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir.'/'.$safeName)) throw new Exception('Failed to save file');
    // Validity dates — default expiry_date = issue_date + 5 years
    $issueDate  = !empty($_POST['valid_from'])  ? $_POST['valid_from']  : date('Y-m-d');
    $expiryDate = !empty($_POST['valid_until']) ? $_POST['valid_until'] : date('Y-m-d', strtotime($issueDate . ' +5 years'));
    $id = Database::insert('chemical_sds_files', [
        'chemical_id' => $chemId, 'file_type' => $_POST['file_type'] ?? 'sds',
        'title' => $_POST['title'] ?? $file['name'], 'description' => $_POST['description'] ?? null,
        'file_path' => '/v1/uploads/sds/'.$chemId.'/'.$safeName, 'file_size' => $file['size'],
        'mime_type' => $file['type'], 'language' => $_POST['language'] ?? 'en',
        'version' => $_POST['version'] ?? null, 'uploaded_by' => $user['id'],
        'is_primary' => (int)($_POST['is_primary'] ?? 0),
        'issue_date' => $issueDate, 'expiry_date' => $expiryDate,
    ]);
    echo json_encode(['success'=>true,'data'=>['id'=>$id,'message'=>'File uploaded']]);
}

function addFileUrl($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $chemId = (int)($data['chemical_id'] ?? 0);
    if (!$chemId) throw new Exception('chemical_id required');
    if (empty($data['file_url'])) throw new Exception('file_url required');
    // Validity dates — default expiry_date = issue_date + 5 years
    $issueDate  = !empty($data['valid_from'])  ? $data['valid_from']  : date('Y-m-d');
    $expiryDate = !empty($data['valid_until']) ? $data['valid_until'] : date('Y-m-d', strtotime($issueDate . ' +5 years'));
    $id = Database::insert('chemical_sds_files', [
        'chemical_id' => $chemId, 'file_type' => $data['file_type'] ?? 'sds',
        'title' => $data['title'] ?? 'External Link', 'description' => $data['description'] ?? null,
        'file_url' => $data['file_url'], 'language' => $data['language'] ?? 'en',
        'version' => $data['version'] ?? null, 'uploaded_by' => $user['id'],
        'is_primary' => (int)($data['is_primary'] ?? 0),
        'issue_date' => $issueDate, 'expiry_date' => $expiryDate,
    ]);
    echo json_encode(['success'=>true,'data'=>['id'=>$id,'message'=>'Link added']]);
}

// ═══════════════════════════════════════════════════════
// PUT / DELETE
// ═══════════════════════════════════════════════════════
function handlePut($action, $user, $isAdmin, $isManager) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($action === 'file') {
        $file = Database::fetch("SELECT * FROM chemical_sds_files WHERE id = :id", [':id' => $id]);
        if (!$file) throw new Exception('File not found');
        if (!$isManager && $file['uploaded_by'] != $user['id']) throw new Exception('Permission denied', 403);
        $upd = [];
        foreach (['title','description','file_type','language','version','is_primary','file_url','issue_date','expiry_date'] as $f) {
            if (array_key_exists($f, $data)) $upd[$f] = $data[$f] ?: null;
        }
        if ($upd) Database::update('chemical_sds_files', $upd, 'id = :id', [':id' => $id]);
        echo json_encode(['success'=>true,'message'=>'File updated']);
    } else {
        if (!$isManager) throw new Exception('Permission denied', 403);
        updateChemicalPost($user);
    }
}

function handleDelete($action, $user, $isAdmin, $isManager) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID required');
    switch ($action) {
        case 'chemical':
            if (!$isAdmin) throw new Exception('Permission denied: admin only', 403);
            $cnt = (int)Database::fetch("SELECT COUNT(*) as c FROM containers WHERE chemical_id = :id AND status='active'", [':id'=>$id])['c'];
            if ($cnt) throw new Exception("ไม่สามารถลบได้: มี {$cnt} ภาชนะในคลัง");
            Database::update('chemicals', ['is_active'=>0], 'id = :id', [':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'ลบสารเคมีเรียบร้อยแล้ว']);
            break;
        case 'file':
            $file = Database::fetch("SELECT * FROM chemical_sds_files WHERE id = :id", [':id'=>$id]);
            if (!$file) throw new Exception('File not found');
            if (!$isManager && $file['uploaded_by'] != $user['id']) throw new Exception('You can only delete your own files', 403);
            if ($file['file_path']) { $p = __DIR__.'/..'.$file['file_path']; if (file_exists($p)) unlink($p); }
            Database::delete('chemical_sds_files', 'id = :id', [':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'File deleted']);
            break;
        case 'ghs':
            if (!$isManager) throw new Exception('Permission denied', 403);
            Database::delete('chemical_ghs_data', 'chemical_id = :id', [':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'GHS data deleted']);
            break;
        case 'packaging':
            if (!$isManager) throw new Exception('Permission denied', 403);
            Database::update('chemical_packaging', ['is_active'=>0], 'id = :id', [':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'Packaging deleted']);
            break;
        default:
            if (!$isManager) throw new Exception('Permission denied', 403);
            Database::update('chemicals', ['is_active'=>0], 'id = :id', [':id'=>$id]);
            echo json_encode(['success'=>true,'message'=>'Chemical deleted']);
    }
}

// ═══════════════════════════════════════════════════════
// Packaging Template Functions
// ═══════════════════════════════════════════════════════
function getPackaging($chemicalId) {
    if (!$chemicalId) throw new Exception('chemical_id required');
    $items = Database::fetchAll("
        SELECT cp.*, u.first_name as creator_first, u.last_name as creator_last
        FROM chemical_packaging cp
        LEFT JOIN users u ON cp.created_by = u.id
        WHERE cp.chemical_id = :id AND cp.is_active = 1
        ORDER BY cp.is_default DESC, cp.sort_order ASC, cp.capacity ASC",
        [':id' => $chemicalId]);
    echo json_encode(['success' => true, 'data' => $items]);
}

function savePackaging($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $chemId = (int)($data['chemical_id'] ?? 0);
    if (!$chemId) throw new Exception('chemical_id required');
    if (empty($data['label'])) throw new Exception('label (ชื่อบรรจุภัณฑ์) is required');
    if (empty($data['capacity']) || $data['capacity'] <= 0) throw new Exception('capacity must be > 0');

    $id = (int)($data['id'] ?? 0);

    $fields = [
        'chemical_id'        => $chemId,
        'container_type'     => $data['container_type'] ?? 'bottle',
        'container_material' => $data['container_material'] ?? 'glass',
        'capacity'           => (float)$data['capacity'],
        'capacity_unit'      => $data['capacity_unit'] ?? 'mL',
        'label'              => $data['label'],
        'description'        => $data['description'] ?? null,
        'image_url'          => $data['image_url'] ?? null,
        'supplier_name'      => $data['supplier_name'] ?? null,
        'catalogue_number'   => $data['catalogue_number'] ?? null,
        'unit_price'         => !empty($data['unit_price']) ? (float)$data['unit_price'] : null,
        'currency'           => $data['currency'] ?? 'THB',
        'is_default'         => (int)($data['is_default'] ?? 0),
        'sort_order'         => (int)($data['sort_order'] ?? 0),
        'model_3d_id'        => !empty($data['model_3d_id']) ? (int)$data['model_3d_id'] : null,
    ];

    if ($id) {
        // Update existing
        $existing = Database::fetch("SELECT id FROM chemical_packaging WHERE id = :id AND is_active = 1", [':id' => $id]);
        if (!$existing) throw new Exception('Packaging not found');
        unset($fields['chemical_id']);
        Database::update('chemical_packaging', $fields, 'id = :id', [':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Packaging updated', 'data' => ['id' => $id]]);
    } else {
        // Create new
        $fields['created_by'] = $user['id'];
        $fields['is_active'] = 1;
        $newId = Database::insert('chemical_packaging', $fields);
        echo json_encode(['success' => true, 'message' => 'Packaging created', 'data' => ['id' => $newId]]);
    }
}
