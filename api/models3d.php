<?php
/**
 * 3D Model Management API — STANDALONE
 * Self-contained 3D model system for Chemical Inventory
 * NO dependency on VRX Studio
 * 
 * GET    ?action=list              List all 3D models (with filters)
 * GET    ?action=detail&id=N       Get single model detail
 * GET    ?action=for_packaging&id=N Get model for a packaging record
 * GET    ?action=for_type&type=X   Get default model for container type
 * GET    ?action=stats             Dashboard stats
 * GET    ?action=requests          List model requests
 * GET    ?action=iframe_config     Get iframe config settings
 * POST   ?action=upload            Upload 3D model file
 * POST   ?action=save              Create/update model record
 * POST   ?action=save_iframe_config Save iframe config settings
 * POST   ?action=request           Submit model request
 * POST   ?action=request_update    Update request status (admin)
 * DELETE ?action=delete&id=N       Soft-delete model
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = Auth::requireAuth();
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;

// Upload/model storage config
define('MODEL_UPLOAD_DIR', realpath(__DIR__ . '/../assets/uploads') . '/models/');
define('MODEL_UPLOAD_URL', '/v1/assets/uploads/models/');
define('MODEL_MAX_SIZE', 100 * 1024 * 1024); // 100MB
define('MODEL_ALLOWED_EXT', ['glb', 'gltf', 'obj', 'fbx', 'stl']);

if (!is_dir(MODEL_UPLOAD_DIR)) mkdir(MODEL_UPLOAD_DIR, 0755, true);

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list': listModels($_GET); break;
                case 'detail': getModelDetail((int)($_GET['id'] ?? 0)); break;
                case 'for_packaging': getModelForPackaging((int)($_GET['id'] ?? 0)); break;
                case 'for_type': getModelForType($_GET['type'] ?? '', $_GET['material'] ?? ''); break;
                case 'stats': getStats(); break;
                case 'requests': listRequests($_GET); break;
                case 'chemicals_search': searchChemicals($_GET['q'] ?? ''); break;
                case 'iframe_config': getIframeConfig(); break;
                case 'cas_packaging_map': getCasPackagingMap($_GET); break;
                case 'models_for_cards': getModelsForCards($_GET); break;
                default: throw new Exception('Unknown GET action');
            }
            break;
        case 'POST':
            switch ($action) {
                case 'upload':
                    if (!$isManager) throw new Exception('Permission denied', 403);
                    uploadModel($user);
                    break;
                case 'save':
                    if (!$isManager) throw new Exception('Permission denied', 403);
                    saveModel($user);
                    break;
                case 'request':
                    submitRequest($user); // any logged-in user
                    break;
                case 'request_update':
                    if (!$isManager) throw new Exception('Permission denied', 403);
                    updateRequest($user);
                    break;
                case 'save_iframe_config':
                    if (!$isAdmin) throw new Exception('Permission denied — admin only', 403);
                    saveIframeConfig($user);
                    break;
                default: throw new Exception('Unknown POST action');
            }
            break;
        case 'DELETE':
            if (!$isManager) throw new Exception('Permission denied', 403);
            if ($action === 'delete') {
                $id = (int)($_GET['id'] ?? 0);
                if (!$id) throw new Exception('id required');
                Database::update('packaging_3d_models', ['is_active' => 0], 'id = :id', [':id' => $id]);
                echo json_encode(['success' => true, 'message' => 'ลบโมเดลสำเร็จ']);
            } else {
                throw new Exception('Unknown DELETE action');
            }
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code >= 400 && $code < 600) http_response_code($code);
    else http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════
// Helper: UUID v4 generator
// ═══════════════════════════════════════════════════════
function genUuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ═══════════════════════════════════════════════════════
// Helper: Format file size
// ═══════════════════════════════════════════════════════
function fmtSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ═══════════════════════════════════════════════════════
// GET: List all models
// ═══════════════════════════════════════════════════════
function listModels($filters) {
    $where = ['m.is_active = 1'];
    $params = [];

    if (!empty($filters['container_type'])) {
        $where[] = "m.container_type = :type";
        $params[':type'] = $filters['container_type'];
    }
    if (!empty($filters['container_material'])) {
        $where[] = "m.container_material = :mat";
        $params[':mat'] = $filters['container_material'];
    }
    if (!empty($filters['chemical_id'])) {
        $where[] = "m.chemical_id = :chem_id";
        $params[':chem_id'] = (int)$filters['chemical_id'];
    }
    if (!empty($filters['cas_number'])) {
        $where[] = "c.cas_number = :cas";
        $params[':cas'] = $filters['cas_number'];
    }
    if (!empty($filters['search'])) {
        $s = '%' . $filters['search'] . '%';
        $where[] = "(m.label LIKE :s1 OR m.description LIKE :s2 OR m.container_type LIKE :s3 OR m.original_name LIKE :s4 OR c.cas_number LIKE :s5 OR c.name LIKE :s6)";
        $params[':s1'] = $s; $params[':s2'] = $s; $params[':s3'] = $s; $params[':s4'] = $s; $params[':s5'] = $s; $params[':s6'] = $s;
    }
    // Group mode: return models grouped by CAS number
    if (!empty($filters['group_by']) && $filters['group_by'] === 'cas') {
        $w = implode(' AND ', $where);
        $items = Database::fetchAll("
            SELECT m.*, 
                   c.name as chemical_name, c.cas_number,
                   u.first_name as creator_first, u.last_name as creator_last
            FROM packaging_3d_models m
            LEFT JOIN chemicals c ON m.chemical_id = c.id
            LEFT JOIN users u ON m.created_by = u.id
            WHERE $w
            ORDER BY c.cas_number ASC, m.sort_order ASC, m.created_at DESC",
            $params
        );
        // Group into cas_number => [models]
        $grouped = [];
        $ungrouped = [];
        foreach ($items as $item) {
            $cas = $item['cas_number'] ?: null;
            if ($cas) {
                if (!isset($grouped[$cas])) {
                    $grouped[$cas] = [
                        'cas_number'    => $cas,
                        'chemical_name' => $item['chemical_name'],
                        'chemical_id'   => $item['chemical_id'],
                        'models'        => [],
                    ];
                }
                $grouped[$cas]['models'][] = $item;
            } else {
                $ungrouped[] = $item;
            }
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'grouped'   => array_values($grouped),
                'ungrouped' => $ungrouped,
            ],
            'total' => count($items),
        ]);
        return;
    }

    $w = implode(' AND ', $where);
    $page = max(1, (int)($filters['page'] ?? 1));
    $limit = min(50, max(1, (int)($filters['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $total = (int)Database::fetch("SELECT COUNT(*) as c FROM packaging_3d_models m LEFT JOIN chemicals c ON m.chemical_id = c.id WHERE $w", $params)['c'];

    $items = Database::fetchAll("
        SELECT m.*, 
               c.name as chemical_name, c.cas_number,
               u.first_name as creator_first, u.last_name as creator_last
        FROM packaging_3d_models m
        LEFT JOIN chemicals c ON m.chemical_id = c.id
        LEFT JOIN users u ON m.created_by = u.id
        WHERE $w
        ORDER BY m.is_default DESC, m.sort_order ASC, m.created_at DESC
        LIMIT :lim OFFSET :off",
        array_merge($params, [':lim' => $limit, ':off' => $offset])
    );

    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total / max(1, $limit))
        ]
    ]);
}

// ═══════════════════════════════════════════════════════
// GET: Single model detail
// ═══════════════════════════════════════════════════════
function getModelDetail($id) {
    if (!$id) throw new Exception('id required');
    $model = Database::fetch("
        SELECT m.*,
               c.name as chemical_name, c.cas_number
        FROM packaging_3d_models m
        LEFT JOIN chemicals c ON m.chemical_id = c.id
        WHERE m.id = :id AND m.is_active = 1",
        [':id' => $id]
    );
    if (!$model) throw new Exception('Model not found');
    echo json_encode(['success' => true, 'data' => $model]);
}

// ═══════════════════════════════════════════════════════
// GET: Model for specific packaging (with fallback chain)
// ═══════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════
// GET: Batch models for chemical cards (lightweight)
// Returns best GLB/embed model per chemical for card preview
// ═══════════════════════════════════════════════════════
function getModelsForCards($filters) {
    $ids = $filters['chemical_ids'] ?? '';
    if (!$ids) { echo json_encode(['success'=>true,'data'=>[]]); return; }
    $idList = array_filter(array_map('intval', explode(',', $ids)));
    if (empty($idList)) { echo json_encode(['success'=>true,'data'=>[]]); return; }
    $idList = array_slice($idList, 0, 100); // max 100

    $pdo = Database::getInstance();
    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    // 1. Packaging → model via cp.model_3d_id (new direct link)
    $stmt1 = $pdo->prepare("
        SELECT cp.chemical_id, cp.id as pkg_id, cp.label as pkg_label,
               cp.container_type as pkg_type, cp.capacity, cp.capacity_unit,
               m.id as model_id, m.label, m.source_type,
               m.file_url, m.embed_url, m.embed_provider, m.container_type,
               m.thumbnail_path, m.ar_enabled
        FROM chemical_packaging cp
        INNER JOIN packaging_3d_models m ON (cp.model_3d_id = m.id AND m.is_active = 1)
        WHERE cp.chemical_id IN ($placeholders) AND cp.is_active = 1
        ORDER BY cp.chemical_id, cp.is_default DESC, cp.sort_order ASC");
    $stmt1->execute($idList);
    $linkedModels = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

    // 2. Packaging → model via m.packaging_id (legacy link)
    $stmt2 = $pdo->prepare("
        SELECT cp.chemical_id, cp.id as pkg_id, cp.label as pkg_label,
               cp.container_type as pkg_type, cp.capacity, cp.capacity_unit,
               m.id as model_id, m.label, m.source_type,
               m.file_url, m.embed_url, m.embed_provider, m.container_type,
               m.thumbnail_path, m.ar_enabled
        FROM chemical_packaging cp
        INNER JOIN packaging_3d_models m ON (m.packaging_id = cp.id AND m.is_active = 1)
        WHERE cp.chemical_id IN ($placeholders) AND cp.is_active = 1
        ORDER BY cp.chemical_id, m.is_default DESC, m.sort_order ASC");
    $stmt2->execute($idList);
    $legacyModels = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

    // 3. Direct chemical-linked models (no packaging)
    $stmt3 = $pdo->prepare("
        SELECT m.chemical_id, NULL as pkg_id, m.label as pkg_label,
               m.container_type as pkg_type, NULL as capacity, NULL as capacity_unit,
               m.id as model_id, m.label, m.source_type,
               m.file_url, m.embed_url, m.embed_provider, m.container_type,
               m.thumbnail_path, m.ar_enabled
        FROM packaging_3d_models m
        WHERE m.chemical_id IN ($placeholders) AND m.is_active = 1 AND m.packaging_id IS NULL
        ORDER BY m.chemical_id, m.is_default DESC, m.sort_order ASC");
    $stmt3->execute($idList);
    $directModels = $stmt3->fetchAll(\PDO::FETCH_ASSOC);

    // Merge all — deduplicate by model_id per chemical
    $result = [];  // chemical_id => [models...]
    $all = array_merge($linkedModels, $legacyModels, $directModels);
    $seen = [];    // track model_id per chemical to avoid dupes
    foreach ($all as $m) {
        $cid = (int)$m['chemical_id'];
        $mid = (int)$m['model_id'];
        $key = $cid . '_' . $mid;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        if (!isset($result[$cid])) $result[$cid] = [];
        $result[$cid][] = $m;
    }

    // 4. For chemicals without any model, try type-based default
    $missing = array_values(array_diff($idList, array_keys($result)));
    if (!empty($missing)) {
        $mp = implode(',', array_fill(0, count($missing), '?'));
        $stmt4 = $pdo->prepare("
            SELECT cp.chemical_id, cp.id as pkg_id, cp.label as pkg_label,
                   cp.container_type as pkg_type, cp.capacity, cp.capacity_unit
            FROM chemical_packaging cp
            WHERE cp.chemical_id IN ($mp) AND cp.is_active = 1
            ORDER BY cp.chemical_id, cp.is_default DESC, cp.sort_order ASC");
        $stmt4->execute($missing);
        $pkgTypes = $stmt4->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($pkgTypes as $pt) {
            $cid = (int)$pt['chemical_id'];
            if (isset($result[$cid])) continue;
            $defModel = Database::fetch("
                SELECT id as model_id, label, source_type, file_url, embed_url, embed_provider,
                       container_type, thumbnail_path, ar_enabled
                FROM packaging_3d_models
                WHERE container_type = :type AND is_default = 1 AND is_active = 1 AND packaging_id IS NULL
                ORDER BY sort_order ASC LIMIT 1",
                [':type' => $pt['pkg_type']]
            );
            if ($defModel) {
                $defModel['chemical_id'] = $cid;
                $defModel['pkg_id'] = $pt['pkg_id'];
                $defModel['pkg_label'] = $pt['pkg_label'];
                $defModel['pkg_type'] = $pt['pkg_type'];
                $defModel['capacity'] = $pt['capacity'];
                $defModel['capacity_unit'] = $pt['capacity_unit'];
                $result[$cid] = [$defModel];
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $result]);
}

function getModelForPackaging($packagingId) {
    if (!$packagingId) throw new Exception('packaging id required');

    // Get packaging record
    $pkg = Database::fetch("
        SELECT model_3d_id, container_type, container_material, capacity, capacity_unit 
        FROM chemical_packaging WHERE id = :id AND is_active = 1", 
        [':id' => $packagingId]
    );
    if (!$pkg) throw new Exception('Packaging not found');

    // 1. Direct link: packaging has model_3d_id assigned
    if ($pkg['model_3d_id']) {
        $model = Database::fetch("SELECT * FROM packaging_3d_models WHERE id = :id AND is_active = 1", [':id' => $pkg['model_3d_id']]);
        if ($model) {
            echo json_encode(['success' => true, 'data' => $model, 'match' => 'direct']);
            return;
        }
    }

    // 2. Search by packaging_id link
    $model = Database::fetch("
        SELECT * FROM packaging_3d_models 
        WHERE packaging_id = :pid AND is_active = 1 
        ORDER BY is_default DESC LIMIT 1", 
        [':pid' => $packagingId]
    );
    if ($model) {
        echo json_encode(['success' => true, 'data' => $model, 'match' => 'packaging_link']);
        return;
    }

    // 3. Fallback: match by container_type + material + capacity range
    $model = Database::fetch("
        SELECT * FROM packaging_3d_models 
        WHERE container_type = :type 
          AND (container_material = :mat OR container_material IS NULL)
          AND (capacity_range_min IS NULL OR capacity_range_min <= :cap)
          AND (capacity_range_max IS NULL OR capacity_range_max >= :cap2)
          AND packaging_id IS NULL
          AND is_active = 1
        ORDER BY is_default DESC, 
                 CASE WHEN container_material = :mat2 THEN 0 ELSE 1 END
        LIMIT 1",
        [
            ':type' => $pkg['container_type'],
            ':mat' => $pkg['container_material'],
            ':cap' => $pkg['capacity'] ?? 0,
            ':cap2' => $pkg['capacity'] ?? 0,
            ':mat2' => $pkg['container_material']
        ]
    );
    if ($model) {
        echo json_encode(['success' => true, 'data' => $model, 'match' => 'type_fallback']);
        return;
    }

    // 4. Last fallback: just container_type default
    $model = Database::fetch("
        SELECT * FROM packaging_3d_models 
        WHERE container_type = :type AND is_default = 1 AND is_active = 1
        ORDER BY sort_order ASC LIMIT 1",
        [':type' => $pkg['container_type']]
    );
    if ($model) {
        echo json_encode(['success' => true, 'data' => $model, 'match' => 'type_default']);
        return;
    }

    echo json_encode(['success' => true, 'data' => null, 'match' => 'none']);
}

// ═══════════════════════════════════════════════════════
// GET: Models for a container type
// ═══════════════════════════════════════════════════════
function getModelForType($type, $material) {
    if (!$type) throw new Exception('type required');
    $params = [':type' => $type];
    $matWhere = '';
    if ($material) {
        $matWhere = 'AND (m.container_material = :mat OR m.container_material IS NULL)';
        $params[':mat'] = $material;
    }
    $models = Database::fetchAll("
        SELECT * FROM packaging_3d_models m
        WHERE m.container_type = :type $matWhere AND m.is_active = 1
        ORDER BY m.is_default DESC, m.sort_order ASC",
        $params
    );
    echo json_encode(['success' => true, 'data' => $models]);
}

// ═══════════════════════════════════════════════════════
// GET: Dashboard stats
// ═══════════════════════════════════════════════════════
function getStats() {
    $stats = Database::fetch("
        SELECT 
            COUNT(*) as total_models,
            COUNT(DISTINCT container_type) as container_types,
            COUNT(CASE WHEN is_default = 1 THEN 1 END) as default_models,
            COUNT(CASE WHEN packaging_id IS NOT NULL THEN 1 END) as specific_links,
            COUNT(CASE WHEN packaging_id IS NULL THEN 1 END) as generic_models,
            COUNT(CASE WHEN source_type = 'embed' THEN 1 END) as embed_models,
            COALESCE(SUM(file_size), 0) as total_storage
        FROM packaging_3d_models WHERE is_active = 1
    ");
    $stats['pending_requests'] = (int)Database::fetch(
        "SELECT COUNT(*) as c FROM model_requests WHERE status IN ('pending','approved','in_progress')"
    )['c'];
    $stats['total_storage_fmt'] = fmtSize($stats['total_storage']);

    echo json_encode(['success' => true, 'data' => $stats]);
}

// ═══════════════════════════════════════════════════════
// POST: Upload 3D model file (self-contained)
// ═══════════════════════════════════════════════════════
function uploadModel($user) {
    if (!isset($_FILES['model_file'])) throw new Exception('No file uploaded');
    $f = $_FILES['model_file'];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error: ' . $f['error']);
    if ($f['size'] > MODEL_MAX_SIZE) throw new Exception('File too large (max ' . round(MODEL_MAX_SIZE / 1048576) . ' MB)');

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, MODEL_ALLOWED_EXT)) {
        throw new Exception('File type not allowed. Accepted: ' . implode(', ', MODEL_ALLOWED_EXT));
    }

    // Generate unique filename
    $uuid = genUuid();
    $safeName = $uuid . '.' . $ext;
    $ym = date('Y/m');
    $dir = MODEL_UPLOAD_DIR . $ym . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $dest = $dir . $safeName;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new Exception('Failed to save file');
    }

    // Sanitize GLB/GLTF: strip leading bytes before glTF magic header
    if (in_array($ext, ['glb', 'gltf'])) {
        $raw = file_get_contents($dest);
        $pos = strpos($raw, 'glTF');
        if ($pos !== false && $pos > 0 && $pos <= 8) {
            file_put_contents($dest, substr($raw, $pos));
        }
    }

    $filePath = 'models/' . $ym . '/' . $safeName;
    $fileUrl = MODEL_UPLOAD_URL . $ym . '/' . $safeName;

    echo json_encode([
        'success' => true,
        'message' => 'อัปโหลดสำเร็จ',
        'data' => [
            'file_path'     => $filePath,
            'file_url'      => $fileUrl,
            'original_name' => $f['name'],
            'mime_type'     => $f['type'],
            'extension'     => $ext,
            'file_size'     => $f['size'],
            'file_size_fmt' => fmtSize($f['size']),
        ]
    ]);
}

// ═══════════════════════════════════════════════════════
// POST: Save model record (create/update)
// ═══════════════════════════════════════════════════════
function saveModel($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['container_type'])) throw new Exception('container_type required');
    if (empty($data['label'])) throw new Exception('label required');

    $id = (int)($data['id'] ?? 0);

    $fields = [
        'packaging_id'       => !empty($data['packaging_id']) ? (int)$data['packaging_id'] : null,
        'chemical_id'        => !empty($data['chemical_id']) ? (int)$data['chemical_id'] : null,
        'container_type'     => $data['container_type'],
        'container_material' => $data['container_material'] ?? null,
        'capacity_range_min' => !empty($data['capacity_range_min']) ? (float)$data['capacity_range_min'] : null,
        'capacity_range_max' => !empty($data['capacity_range_max']) ? (float)$data['capacity_range_max'] : null,
        'capacity_unit'      => $data['capacity_unit'] ?? null,
        'file_path'          => $data['file_path'] ?? null,
        'file_url'           => $data['file_url'] ?? null,
        'original_name'      => $data['original_name'] ?? null,
        'mime_type'          => $data['mime_type'] ?? null,
        'extension'          => $data['extension'] ?? null,
        'file_size'          => (int)($data['file_size'] ?? 0),
        'thumbnail_path'     => $data['thumbnail_path'] ?? null,
        'source_type'        => ($data['source_type'] ?? 'upload') === 'embed' ? 'embed' : 'upload',
        'embed_url'          => $data['embed_url'] ?? null,
        'embed_code'         => $data['embed_code'] ?? null,
        'embed_provider'     => $data['embed_provider'] ?? null,
        'label'              => $data['label'],
        'description'        => $data['description'] ?? null,
        'ar_enabled'         => (int)($data['ar_enabled'] ?? 0),
        'is_default'         => (int)($data['is_default'] ?? 0),
        'sort_order'         => (int)($data['sort_order'] ?? 0),
    ];

    if ($id) {
        $existing = Database::fetch("SELECT id FROM packaging_3d_models WHERE id = :id AND is_active = 1", [':id' => $id]);
        if (!$existing) throw new Exception('Model not found');
        Database::update('packaging_3d_models', $fields, 'id = :id', [':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'อัปเดตโมเดลสำเร็จ', 'data' => ['id' => $id]]);
    } else {
        $fields['created_by'] = $user['id'];
        $fields['is_active'] = 1;
        $newId = Database::insert('packaging_3d_models', $fields);

        // If packaging_id specified, also update chemical_packaging.model_3d_id
        if (!empty($data['packaging_id'])) {
            Database::query("UPDATE chemical_packaging SET model_3d_id = :mid WHERE id = :pid",
                [':mid' => $newId, ':pid' => (int)$data['packaging_id']]);
        }

        echo json_encode(['success' => true, 'message' => 'สร้างโมเดลสำเร็จ', 'data' => ['id' => $newId]]);
    }
}

// ═══════════════════════════════════════════════════════
// Model Request Functions
// ═══════════════════════════════════════════════════════
function listRequests($filters) {
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = "mr.status = :status";
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['my'])) {
        global $user;
        $where[] = "mr.requested_by = :uid";
        $params[':uid'] = $user['id'];
    }

    $w = implode(' AND ', $where);
    $items = Database::fetchAll("
        SELECT mr.*,
               c.name as chemical_name, c.cas_number,
               cp.label as packaging_label,
               u.first_name as requester_first, u.last_name as requester_last,
               ua.first_name as assignee_first, ua.last_name as assignee_last
        FROM model_requests mr
        LEFT JOIN chemicals c ON mr.chemical_id = c.id
        LEFT JOIN chemical_packaging cp ON mr.packaging_id = cp.id
        LEFT JOIN users u ON mr.requested_by = u.id
        LEFT JOIN users ua ON mr.assigned_to = ua.id
        WHERE $w
        ORDER BY FIELD(mr.status, 'pending','approved','in_progress','completed','rejected'),
                 FIELD(mr.priority, 'urgent','high','normal','low'),
                 mr.requested_at DESC",
        $params
    );
    echo json_encode(['success' => true, 'data' => $items]);
}

function submitRequest($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['title'])) throw new Exception('title required');
    if (empty($data['container_type'])) throw new Exception('container_type required');

    $fields = [
        'chemical_id'         => !empty($data['chemical_id']) ? (int)$data['chemical_id'] : null,
        'packaging_id'        => !empty($data['packaging_id']) ? (int)$data['packaging_id'] : null,
        'container_type'      => $data['container_type'],
        'container_material'  => $data['container_material'] ?? null,
        'capacity'            => !empty($data['capacity']) ? (float)$data['capacity'] : null,
        'capacity_unit'       => $data['capacity_unit'] ?? null,
        'title'               => $data['title'],
        'description'         => $data['description'] ?? null,
        'reference_image_url' => $data['reference_image_url'] ?? null,
        'priority'            => $data['priority'] ?? 'normal',
        'status'              => 'pending',
        'requested_by'        => $user['id'],
    ];

    $newId = Database::insert('model_requests', $fields);
    echo json_encode(['success' => true, 'message' => 'ส่งคำขอโมเดลสำเร็จ', 'data' => ['id' => $newId]]);
}

function updateRequest($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) throw new Exception('id required');

    $existing = Database::fetch("SELECT * FROM model_requests WHERE id = :id", [':id' => $id]);
    if (!$existing) throw new Exception('Request not found');

    $fields = [];
    if (isset($data['status'])) $fields['status'] = $data['status'];
    if (isset($data['assigned_to'])) $fields['assigned_to'] = (int)$data['assigned_to'];
    if (isset($data['admin_notes'])) $fields['admin_notes'] = $data['admin_notes'];
    if (isset($data['priority'])) $fields['priority'] = $data['priority'];
    if (isset($data['fulfilled_model_id'])) {
        $fields['fulfilled_model_id'] = (int)$data['fulfilled_model_id'];
        $fields['status'] = 'completed';
        $fields['completed_at'] = date('Y-m-d H:i:s');
    }

    if (empty($fields)) throw new Exception('Nothing to update');
    Database::update('model_requests', $fields, 'id = :id', [':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'อัปเดตคำขอสำเร็จ']);
}

// ═══════════════════════════════════════════════════════
// GET: Search chemicals for autocomplete
// ═══════════════════════════════════════════════════════
function searchChemicals($q) {
    $q = trim($q);
    if (strlen($q) < 1) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    $s = '%' . $q . '%';
    $items = Database::fetchAll("
        SELECT id, name, cas_number, molecular_formula
        FROM chemicals
        WHERE (name LIKE :s1 OR cas_number LIKE :s2 OR molecular_formula LIKE :s3) AND is_active = 1
        ORDER BY cas_number ASC
        LIMIT 20",
        [':s1' => $s, ':s2' => $s, ':s3' => $s]
    );
    echo json_encode(['success' => true, 'data' => $items]);
}

// ═══════════════════════════════════════════════════════
// GET: CAS Number → Packaging → 3D Model mapping table
// ═══════════════════════════════════════════════════════
function getCasPackagingMap($filters) {
    $where = ['c.is_active = 1'];
    $params = [];

    if (!empty($filters['search'])) {
        $s = '%' . $filters['search'] . '%';
        $where[] = "(c.cas_number LIKE :s1 OR c.name LIKE :s2)";
        $params[':s1'] = $s;
        $params[':s2'] = $s;
    }
    if (!empty($filters['status'])) {
        // will filter after query
    }

    $w = implode(' AND ', $where);

    // Get all chemicals that have CAS numbers
    $chemicals = Database::fetchAll("
        SELECT c.id, c.name, c.cas_number, c.molecular_formula,
               c.hazard_pictograms, c.signal_word
        FROM chemicals c
        WHERE $w AND c.cas_number IS NOT NULL AND c.cas_number != ''
        ORDER BY c.cas_number ASC",
        $params
    );

    // Get packaging for these chemicals
    $chemIds = array_column($chemicals, 'id');
    $packagingMap = [];
    if (!empty($chemIds)) {
        $placeholders = implode(',', array_fill(0, count($chemIds), '?'));
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT cp.*, m.id as model_id, m.label as model_label, m.source_type as model_source_type,
                   m.embed_url as model_embed_url, m.file_url as model_file_url, m.embed_provider as model_provider
            FROM chemical_packaging cp
            LEFT JOIN packaging_3d_models m ON (m.packaging_id = cp.id AND m.is_active = 1)
            WHERE cp.chemical_id IN ($placeholders) AND cp.is_active = 1
            ORDER BY cp.chemical_id, cp.sort_order ASC, cp.id ASC");
        $stmt->execute($chemIds);
        $allPkg = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($allPkg as $pkg) {
            $cid = $pkg['chemical_id'];
            if (!isset($packagingMap[$cid])) $packagingMap[$cid] = [];
            $packagingMap[$cid][] = $pkg;
        }
    }

    // Also get 3D models linked by chemical_id (not via packaging)
    $directModelMap = [];
    if (!empty($chemIds)) {
        $placeholders2 = implode(',', array_fill(0, count($chemIds), '?'));
        $pdo2 = Database::getInstance();
        $stmt2 = $pdo2->prepare("
            SELECT m.id, m.chemical_id, m.label, m.container_type, m.container_material,
                   m.source_type, m.embed_url, m.file_url, m.embed_provider, m.is_default
            FROM packaging_3d_models m
            WHERE m.chemical_id IN ($placeholders2) AND m.is_active = 1 AND m.packaging_id IS NULL
            ORDER BY m.chemical_id, m.sort_order ASC");
        $stmt2->execute($chemIds);
        $directModels = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($directModels as $dm) {
            $cid = $dm['chemical_id'];
            if (!isset($directModelMap[$cid])) $directModelMap[$cid] = [];
            $directModelMap[$cid][] = $dm;
        }
    }

    // Build all rows first (for accurate stats), then filter
    $allRows = [];
    foreach ($chemicals as $chem) {
        $cid = $chem['id'];
        $pkgs = $packagingMap[$cid] ?? [];
        $directModels = $directModelMap[$cid] ?? [];

        $pkgCount = count($pkgs);
        $modelCount = count($directModels);
        $pkgWithModel = 0;
        foreach ($pkgs as $p) {
            if (!empty($p['model_id'])) $pkgWithModel++;
            if (!empty($p['model_id'])) $modelCount++;
        }

        $statusCode = 'none';
        if ($pkgCount > 0) {
            if ($pkgWithModel === $pkgCount) {
                $statusCode = 'complete'; // all packaging has 3D models
            } elseif ($pkgWithModel > 0 || count($directModels) > 0) {
                $statusCode = 'partial';
            } else {
                $statusCode = 'missing';
            }
        } elseif (count($directModels) > 0) {
            $statusCode = 'partial';
        }

        $allRows[] = [
            'chemical_id'   => $cid,
            'cas_number'    => $chem['cas_number'],
            'chemical_name' => $chem['name'],
            'formula'       => $chem['molecular_formula'],
            'signal_word'   => $chem['signal_word'],
            'hazard_pictograms' => $chem['hazard_pictograms'],
            'packaging'     => $pkgs,
            'direct_models' => $directModels,
            'pkg_count'     => $pkgCount,
            'model_count'   => $modelCount,
            'pkg_with_model'=> $pkgWithModel,
            'status'        => $statusCode,
        ];
    }

    // Stats from ALL rows (before status filter)
    $stats = [
        'total'    => count($allRows),
        'complete' => count(array_filter($allRows, fn($r) => $r['status'] === 'complete')),
        'partial'  => count(array_filter($allRows, fn($r) => $r['status'] === 'partial')),
        'missing'  => count(array_filter($allRows, fn($r) => $r['status'] === 'missing')),
        'none'     => count(array_filter($allRows, fn($r) => $r['status'] === 'none')),
    ];

    // Apply status filter for returned data
    $result = $allRows;
    if (!empty($filters['status'])) {
        $result = array_values(array_filter($allRows, fn($r) => $r['status'] === $filters['status']));
    }

    echo json_encode(['success' => true, 'data' => $result, 'stats' => $stats]);
}

// ═══════════════════════════════════════════════════════
// GET: Iframe config settings
// ═══════════════════════════════════════════════════════
function getIframeConfig() {
    $keys = ['iframe_kiri_bg_theme', 'iframe_kiri_auto_spin', 'iframe_default_params',
             'iframe_default_attrs', 'iframe_width', 'iframe_height'];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

    $config = [
        'kiri_bg_theme'  => $rows['iframe_kiri_bg_theme'] ?? 'transparent',
        'kiri_auto_spin' => $rows['iframe_kiri_auto_spin'] ?? '1',
        'default_params' => $rows['iframe_default_params'] ?? 'bg_theme=transparent&auto_spin_model=1',
        'default_attrs'  => $rows['iframe_default_attrs'] ?? 'frameborder="0" allowfullscreen allow="autoplay; fullscreen;"',
        'width'          => $rows['iframe_width'] ?? '640',
        'height'         => $rows['iframe_height'] ?? '480',
    ];

    echo json_encode(['success' => true, 'data' => $config]);
}

// ═══════════════════════════════════════════════════════
// POST: Save iframe config settings (admin only)
// ═══════════════════════════════════════════════════════
function saveIframeConfig($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception('Invalid data');

    $allowed = [
        'iframe_kiri_bg_theme', 'iframe_kiri_auto_spin', 'iframe_default_params',
        'iframe_default_attrs', 'iframe_width', 'iframe_height'
    ];

    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, category, updated_by) 
                           VALUES (?, ?, 'string', '3d_iframe', ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");

    $saved = 0;
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $stmt->execute([$key, (string)$value, $user['id']]);
            $saved++;
        }
    }

    echo json_encode(['success' => true, 'message' => "บันทึก $saved รายการสำเร็จ"]);
}
