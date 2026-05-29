<?php
/**
 * 3D Model Management API
 * Bridge between Chemical Packaging ↔ VRX Studio
 * 
 * GET    ?action=list              List all 3D models (with filters)
 * GET    ?action=detail&id=N       Get single model detail
 * GET    ?action=vrx_files         Proxy list from VRX Studio files
 * GET    ?action=vrx_search&q=X    Search VRX files
 * GET    ?action=for_packaging&id=N Get model for a packaging record
 * GET    ?action=for_type&type=X   Get default model for container type
 * GET    ?action=stats             Dashboard stats
 * GET    ?action=requests          List model requests
 * POST   ?action=save              Create/update packaging↔model link
 * POST   ?action=request           Submit model request
 * POST   ?action=request_update    Update request status (admin)
 * POST   ?action=upload_to_vrx     Upload file to VRX + create link
 * DELETE ?action=delete&id=N       Soft-delete model link
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
        case 'GET':
            switch ($action) {
                case 'list': listModels($_GET); break;
                case 'detail': getModelDetail((int)($_GET['id'] ?? 0)); break;
                case 'vrx_files': proxyVrxFiles($_GET); break;
                case 'vrx_search': searchVrxFiles($_GET['q'] ?? ''); break;
                case 'for_packaging': getModelForPackaging((int)($_GET['id'] ?? 0)); break;
                case 'for_type': getModelForType($_GET['type'] ?? '', $_GET['material'] ?? ''); break;
                case 'stats': getStats(); break;
                case 'requests': listRequests($_GET); break;
                default: throw new Exception('Unknown GET action');
            }
            break;
        case 'POST':
            if (!$isManager && $action !== 'request') throw new Exception('Permission denied', 403);
            switch ($action) {
                case 'save': saveModel($user); break;
                case 'request': submitRequest($user); break;
                case 'request_update':
                    if (!$isManager) throw new Exception('Permission denied', 403);
                    updateRequest($user);
                    break;
                case 'upload_to_vrx':
                    if (!$isManager) throw new Exception('Permission denied', 403);
                    uploadToVrx($user);
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
                echo json_encode(['success' => true, 'message' => 'Model link deleted']);
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
// VRX Studio Bridge — Connect to the vrx_studio database
// ═══════════════════════════════════════════════════════
function vrxDb(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=vrx_studio;charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

// ═══════════════════════════════════════════════════════
// GET Handlers
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
    if (!empty($filters['search'])) {
        $s = '%' . $filters['search'] . '%';
        $where[] = "(m.label LIKE :s1 OR m.description LIKE :s2 OR m.container_type LIKE :s3)";
        $params[':s1'] = $s; $params[':s2'] = $s; $params[':s3'] = $s;
    }

    $w = implode(' AND ', $where);
    $page = max(1, (int)($filters['page'] ?? 1));
    $limit = min(50, max(1, (int)($filters['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $total = (int)Database::fetch("SELECT COUNT(*) as c FROM packaging_3d_models m WHERE $w", $params)['c'];

    $items = Database::fetchAll("
        SELECT m.*, 
               cp.label as packaging_label, cp.chemical_id,
               c.name as chemical_name, c.cas_number,
               u.first_name as creator_first, u.last_name as creator_last
        FROM packaging_3d_models m
        LEFT JOIN chemical_packaging cp ON m.packaging_id = cp.id
        LEFT JOIN chemicals c ON cp.chemical_id = c.id
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

function getModelDetail($id) {
    if (!$id) throw new Exception('id required');
    $model = Database::fetch("
        SELECT m.*,
               cp.label as packaging_label, cp.chemical_id,
               cp.container_type as pkg_type, cp.container_material as pkg_material,
               cp.capacity as pkg_capacity, cp.capacity_unit as pkg_unit,
               c.name as chemical_name, c.cas_number
        FROM packaging_3d_models m
        LEFT JOIN chemical_packaging cp ON m.packaging_id = cp.id
        LEFT JOIN chemicals c ON cp.chemical_id = c.id
        WHERE m.id = :id AND m.is_active = 1",
        [':id' => $id]
    );
    if (!$model) throw new Exception('Model not found');
    echo json_encode(['success' => true, 'data' => $model]);
}

function getModelForPackaging($packagingId) {
    if (!$packagingId) throw new Exception('packaging id required');

    // 1. Direct link: packaging has model_3d_id
    $pkg = Database::fetch("SELECT model_3d_id, container_type, container_material, capacity, capacity_unit FROM chemical_packaging WHERE id = :id AND is_active = 1", [':id' => $packagingId]);
    if (!$pkg) throw new Exception('Packaging not found');

    if ($pkg['model_3d_id']) {
        $model = Database::fetch("SELECT * FROM packaging_3d_models WHERE id = :id AND is_active = 1", [':id' => $pkg['model_3d_id']]);
        if ($model) {
            echo json_encode(['success' => true, 'data' => $model, 'match' => 'direct']);
            return;
        }
    }

    // 2. Search by packaging_id
    $model = Database::fetch("SELECT * FROM packaging_3d_models WHERE packaging_id = :pid AND is_active = 1 ORDER BY is_default DESC LIMIT 1", [':pid' => $packagingId]);
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
            ':cap' => $pkg['capacity'],
            ':cap2' => $pkg['capacity'],
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

function getStats() {
    $stats = Database::fetch("
        SELECT 
            COUNT(*) as total_models,
            COUNT(DISTINCT container_type) as container_types,
            COUNT(CASE WHEN is_default = 1 THEN 1 END) as default_models,
            COUNT(CASE WHEN packaging_id IS NOT NULL THEN 1 END) as specific_links,
            COUNT(CASE WHEN packaging_id IS NULL THEN 1 END) as generic_models
        FROM packaging_3d_models WHERE is_active = 1
    ");
    $stats['pending_requests'] = (int)Database::fetch("SELECT COUNT(*) as c FROM model_requests WHERE status IN ('pending','approved','in_progress')")['c'];

    // Get VRX stats
    try {
        $vrx = vrxDb();
        $vrxStats = $vrx->query("
            SELECT COUNT(*) as vrx_total,
                   SUM(CASE WHEN c.slug='model' THEN 1 ELSE 0 END) as vrx_3d_models
            FROM files f 
            LEFT JOIN categories c ON c.id = f.category_id
            WHERE f.status='active' AND f.deleted_at IS NULL
        ")->fetch();
        $stats['vrx_total_files'] = (int)$vrxStats['vrx_total'];
        $stats['vrx_3d_models'] = (int)$vrxStats['vrx_3d_models'];
    } catch (Exception $e) {
        $stats['vrx_total_files'] = 0;
        $stats['vrx_3d_models'] = 0;
    }

    echo json_encode(['success' => true, 'data' => $stats]);
}

// ═══════════════════════════════════════════════════════
// VRX Proxy — List/Search files from VRX Studio
// ═══════════════════════════════════════════════════════
function proxyVrxFiles($filters) {
    try {
        $vrx = vrxDb();
        $where = ["f.status='active'", "f.deleted_at IS NULL"];
        $params = [];

        // Only 3D model category by default
        if (empty($filters['all_categories'])) {
            $where[] = "c.slug = 'model'";
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(f.name LIKE :s1 OR f.description LIKE :s2 OR f.original_name LIKE :s3)";
            $params[':s1'] = $s; $params[':s2'] = $s; $params[':s3'] = $s;
        }

        $w = implode(' AND ', $where);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(50, max(1, (int)($filters['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;

        $stmt = $vrx->prepare("SELECT COUNT(*) FROM files f LEFT JOIN categories c ON c.id=f.category_id WHERE $w");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT f.id, f.uuid, f.name, f.original_name, f.description,
                       f.file_path, f.file_url, f.thumbnail_path, f.mime_type, f.extension,
                       f.file_size, f.source_type, f.ar_enabled,
                       f.view_count, f.visibility, f.uploaded_at,
                       c.slug AS category_slug, c.name AS category_name,
                       u.display_name AS uploader
                FROM files f
                LEFT JOIN categories c ON c.id=f.category_id
                LEFT JOIN users u ON u.id=f.user_id
                WHERE $w ORDER BY f.uploaded_at DESC LIMIT :lim OFFSET :off";

        $stmt = $vrx->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Normalize URLs
        foreach ($rows as &$r) {
            $r['file_url'] = vrxNormalizeUrl($r['file_url']);
            $r['thumbnail_url'] = vrxNormalizeUrl($r['thumbnail_path']);
            // Check if already linked
            $linked = Database::fetch("SELECT id FROM packaging_3d_models WHERE vrx_file_id = :vid AND is_active = 1", [':vid' => $r['id']]);
            $r['is_linked'] = !!$linked;
        }
        unset($r);

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / max(1, $limit))
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'VRX connection error: ' . $e->getMessage()]);
    }
}

function searchVrxFiles($q) {
    proxyVrxFiles(['search' => $q, 'limit' => 12]);
}

function vrxNormalizeUrl(?string $url): ?string {
    if (!$url) return null;
    if (preg_match('#^(https?://|/)#i', $url)) return $url;
    return '/vrx/database/' . $url;
}

// ═══════════════════════════════════════════════════════
// POST Handlers
// ═══════════════════════════════════════════════════════
function saveModel($user) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['vrx_file_id']) && empty($data['id'])) throw new Exception('vrx_file_id required');
    if (empty($data['container_type'])) throw new Exception('container_type required');
    if (empty($data['label'])) throw new Exception('label required');

    $id = (int)($data['id'] ?? 0);

    // If new, fetch VRX file info
    $vrxFileUrl = $data['vrx_file_url'] ?? '';
    $vrxThumbnail = $data['vrx_thumbnail_url'] ?? '';
    $vrxFileUuid = $data['vrx_file_uuid'] ?? '';

    if (!$id && !empty($data['vrx_file_id'])) {
        try {
            $vrx = vrxDb();
            $vf = $vrx->prepare("SELECT uuid, file_url, file_path, thumbnail_path, ar_enabled FROM files WHERE id = :id AND status='active'");
            $vf->execute([':id' => (int)$data['vrx_file_id']]);
            $vrxFile = $vf->fetch();
            if ($vrxFile) {
                $vrxFileUuid = $vrxFile['uuid'];
                $vrxFileUrl = vrxNormalizeUrl($vrxFile['file_url'] ?: $vrxFile['file_path']);
                $vrxThumbnail = vrxNormalizeUrl($vrxFile['thumbnail_path']);
            }
        } catch (Exception $e) {
            // Continue without VRX data
        }
    }

    $fields = [
        'packaging_id'       => !empty($data['packaging_id']) ? (int)$data['packaging_id'] : null,
        'container_type'     => $data['container_type'],
        'container_material' => $data['container_material'] ?? null,
        'capacity_range_min' => !empty($data['capacity_range_min']) ? (float)$data['capacity_range_min'] : null,
        'capacity_range_max' => !empty($data['capacity_range_max']) ? (float)$data['capacity_range_max'] : null,
        'capacity_unit'      => $data['capacity_unit'] ?? null,
        'vrx_file_id'        => (int)($data['vrx_file_id'] ?? 0),
        'vrx_file_uuid'      => $vrxFileUuid,
        'vrx_file_url'       => $vrxFileUrl,
        'vrx_thumbnail_url'  => $vrxThumbnail,
        'label'              => $data['label'],
        'description'        => $data['description'] ?? null,
        'ar_enabled'         => (int)($data['ar_enabled'] ?? 0),
        'is_default'         => (int)($data['is_default'] ?? 0),
        'sort_order'         => (int)($data['sort_order'] ?? 0),
    ];

    if ($id) {
        $existing = Database::fetch("SELECT id FROM packaging_3d_models WHERE id = :id AND is_active = 1", [':id' => $id]);
        if (!$existing) throw new Exception('Model link not found');
        Database::update('packaging_3d_models', $fields, 'id = :id', [':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Model updated', 'data' => ['id' => $id]]);
    } else {
        $fields['created_by'] = $user['id'];
        $fields['is_active'] = 1;
        $newId = Database::insert('packaging_3d_models', $fields);

        // If packaging_id specified, also update chemical_packaging.model_3d_id
        if (!empty($data['packaging_id'])) {
            Database::query("UPDATE chemical_packaging SET model_3d_id = :mid WHERE id = :pid",
                [':mid' => $newId, ':pid' => (int)$data['packaging_id']]);
        }

        echo json_encode(['success' => true, 'message' => 'Model link created', 'data' => ['id' => $newId]]);
    }
}

function uploadToVrx($user) {
    if (!isset($_FILES['model_file'])) throw new Exception('No file uploaded');
    $f = $_FILES['model_file'];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error: ' . $f['error']);

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['glb', 'gltf', 'obj', 'fbx', 'stl'];
    if (!in_array($ext, $allowed)) throw new Exception('File type not allowed. Use: ' . implode(', ', $allowed));

    // Upload to VRX storage
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

    $safe = $uuid . '.' . $ext;
    $ym = date('Y/m');
    $uploadDir = realpath(__DIR__ . '/../module3d/vrx/database/uploads') . '/' . $ym . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $dest = $uploadDir . $safe;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new Exception('Failed to save file');
    }

    $filePath = 'uploads/' . $ym . '/' . $safe;
    $fileUrl = '/vrx/database/' . $filePath;

    // Create record in VRX database
    try {
        $vrx = vrxDb();
        $catStmt = $vrx->prepare("SELECT id FROM categories WHERE slug = 'model'");
        $catStmt->execute();
        $catId = $catStmt->fetchColumn();

        $name = $_POST['name'] ?? pathinfo($f['name'], PATHINFO_FILENAME);

        $vrx->prepare("INSERT INTO files (uuid, user_id, category_id, name, original_name, description,
                        file_path, file_url, mime_type, extension, file_size, source_type, visibility)
                       VALUES (:uuid, 1, :cat, :name, :orig, :desc, :path, :url, :mime, :ext, :size, 'upload', 'public')")
            ->execute([
                ':uuid' => $uuid,
                ':cat' => $catId,
                ':name' => $name,
                ':orig' => $f['name'],
                ':desc' => $_POST['description'] ?? null,
                ':path' => $filePath,
                ':url' => $fileUrl,
                ':mime' => $f['type'],
                ':ext' => $ext,
                ':size' => $f['size'],
            ]);

        $vrxFileId = (int)$vrx->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Uploaded to VRX Studio',
            'data' => [
                'vrx_file_id' => $vrxFileId,
                'vrx_file_uuid' => $uuid,
                'vrx_file_url' => $fileUrl,
                'file_path' => $filePath,
                'original_name' => $f['name'],
                'extension' => $ext,
                'file_size' => $f['size'],
            ]
        ]);
    } catch (Exception $e) {
        // Clean up uploaded file
        if (file_exists($dest)) unlink($dest);
        throw new Exception('Failed to save to VRX database: ' . $e->getMessage());
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
    echo json_encode(['success' => true, 'message' => 'Model request submitted', 'data' => ['id' => $newId]]);
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
    echo json_encode(['success' => true, 'message' => 'Request updated']);
}
