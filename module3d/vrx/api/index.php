<?php
/**
 * VRX Studio — REST API v2.0
 *
 * GET    ?action=files              List files
 * GET    ?action=files&id=N         Get single file
 * POST   ?action=files              Create file record       [user+]
 * PUT    ?action=files&id=N         Update file               [user+]
 * DELETE ?action=files&id=N         Soft-delete file           [user+]
 * POST   ?action=upload             Upload binary             [user+]
 * GET    ?action=categories         List categories
 * GET    ?action=stats              Dashboard stats
 * POST   ?action=view&id=N          Increment view count
 * GET    ?action=auth               Auth status
 */

require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($action) {
        case 'files':
            if ($method === 'GET' && $id)        getFile($id);
            elseif ($method === 'GET')            listFiles();
            elseif ($method === 'POST')         { require_auth_api('upload'); createFile(); }
            elseif ($method === 'PUT' && $id)   { require_auth_api('edit');   updateFile($id); }
            elseif ($method === 'DELETE' && $id){ require_auth_api('delete'); deleteFile($id); }
            else json_response(400, ['error' => 'Bad request']);
            break;
        case 'upload':
            require_auth_api('upload');
            uploadFile();
            break;
        case 'categories':
            listCategories();
            break;
        case 'users':
            listUsers();
            break;
        case 'stats':
            getStats();
            break;
        case 'view':
            if ($method === 'POST' && $id) incrementView($id);
            else json_response(400, ['error' => 'Bad request']);
            break;
        case 'auth':
            authStatus();
            break;
        case 'bulk_update_embeds':
            require_auth_api('manage');
            bulkUpdateEmbeds();
            break;
        case 'lookup':
            lookupByUrl();
            break;
        default:
            json_response(404, ['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    json_response(500, ['error' => 'Server error: ' . $e->getMessage()]);
}

// ─── Handlers ─────────────────────────────────

function authStatus() {
    $u = auth_user();
    json_response(200, ['logged_in' => !!$u, 'user' => $u]);
}

function listFiles() {
    $pdo    = db();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 24)));
    $offset = ($page - 1) * $limit;
    $cat    = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort   = $_GET['sort'] ?? 'newest';
    $owner  = $_GET['owner'] ?? '';

    $where  = ["f.status='active'", "f.deleted_at IS NULL"];
    $params = [];

    // Visibility: guests see public; logged-in see public + own
    if (!is_admin()) {
        $uid = auth_id();
        if ($uid) {
            $where[]  = "(f.visibility='public' OR f.user_id=:uid)";
            $params[':uid'] = $uid;
        } else {
            $where[] = "f.visibility='public'";
        }
    }

    if ($cat && $cat !== 'all') {
        $where[] = "c.slug=:cat";
        $params[':cat'] = $cat;
    }
    if ($search) {
        $where[] = "(f.name LIKE :s1 OR f.description LIKE :s2 OR f.original_name LIKE :s3)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
        $params[':s3'] = "%$search%";
    }
    // Owner filter
    if ($owner === 'me') {
        $where[] = "f.user_id=:owner_id";
        $params[':owner_id'] = auth_id();
    } elseif ($owner && is_numeric($owner)) {
        $where[] = "f.user_id=:owner_id";
        $params[':owner_id'] = (int)$owner;
    }

    $w = implode(' AND ', $where);

    $orders = [
        'newest'=>'f.uploaded_at DESC','oldest'=>'f.uploaded_at ASC',
        'name_asc'=>'f.name ASC','name_desc'=>'f.name DESC',
        'popular'=>'f.view_count DESC','largest'=>'f.file_size DESC',
    ];
    $o = $orders[$sort] ?? 'f.uploaded_at DESC';

    // Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM files f LEFT JOIN categories c ON c.id=f.category_id WHERE $w");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Fetch
    $sql = "SELECT f.id, f.uuid, f.name, f.original_name, f.description,
                   f.file_path, f.file_url, f.thumbnail_path, f.mime_type, f.extension,
                   f.file_size, f.source_type, f.is_external,
                   f.embed_src, f.embed_code, f.embed_provider,
                   f.ar_enabled, f.ar_scale,
                   f.view_count, f.download_count, f.like_count,
                   f.visibility, f.uploaded_at, f.updated_at, f.user_id,
                   c.slug AS category_slug, c.name AS category_name,
                   c.icon AS category_icon, c.color AS category_color
            FROM files f
            LEFT JOIN categories c ON c.id=f.category_id
            WHERE $w ORDER BY $o LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Normalize URLs + add computed fields
    foreach ($rows as &$r) {
        $r['file_url'] = normalizeUrl($r['file_url']);
        $r['thumbnail_url'] = normalizeUrl($r['thumbnail_path']);
        $r['title'] = $r['name'];
        $r['created_at'] = $r['uploaded_at'];
        // Get uploader name
        if (!empty($r['user_id'])) {
            $us = $pdo->prepare("SELECT display_name FROM users WHERE id=:id");
            $us->execute([':id' => $r['user_id']]);
            $r['uploader'] = $us->fetchColumn() ?: 'Unknown';
        } else {
            $r['uploader'] = 'Unknown';
        }
    }
    unset($r);

    $totalPages = (int)ceil($total / max(1, $limit));
    json_response(200, [
        'data' => [
            'files' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
        ]
    ]);
}

function getFile(int $id) {
    $stmt = db()->prepare("SELECT f.*, c.slug AS category_slug, c.name AS category_name,
                            c.icon AS category_icon, c.color AS category_color
                           FROM files f LEFT JOIN categories c ON c.id=f.category_id
                           WHERE f.id=:id AND f.status='active' AND f.deleted_at IS NULL");
    $stmt->execute([':id' => $id]);
    $f = $stmt->fetch();
    if (!$f) json_response(404, ['error' => 'Not found']);

    $f['file_url'] = normalizeUrl($f['file_url']);
    $f['thumbnail_url'] = normalizeUrl($f['thumbnail_path']);
    $f['title'] = $f['name'];
    $f['created_at'] = $f['uploaded_at'];
    // Get uploader name
    if (!empty($f['user_id'])) {
        $us = db()->prepare("SELECT display_name FROM users WHERE id=:id");
        $us->execute([':id' => $f['user_id']]);
        $f['uploader'] = $us->fetchColumn() ?: 'Unknown';
    } else {
        $f['uploader'] = 'Unknown';
    }
    json_response(200, ['data' => $f]);
}

function createFile() {
    $input = json_decode(file_get_contents('php://input'), true);
    // Accept 'title' as alias for 'name'
    if (!empty($input['title']) && empty($input['name'])) $input['name'] = $input['title'];
    if (!$input || empty($input['name'])) json_response(400, ['error' => 'name required']);

    $pdo = db();
    $uid = auth_id() ?: 1;
    $catId = null;
    if (!empty($input['category'])) {
        $s = $pdo->prepare("SELECT id FROM categories WHERE slug=:s");
        $s->execute([':s' => $input['category']]);
        $catId = $s->fetchColumn() ?: null;
    }

    $u = uuid();
    $sql = "INSERT INTO files (uuid,user_id,category_id,name,original_name,description,
                file_path,file_url,mime_type,extension,file_size,source_type,is_external,
                embed_src,embed_code,embed_provider,visibility)
            VALUES (:uuid,:uid,:cat,:name,:orig,:desc,:path,:url,:mime,:ext,:size,:src,:ext_flag,
                    :esrc,:ecode,:eprov,:vis)";

    $pdo->prepare($sql)->execute([
        ':uuid'     => $u,
        ':uid'      => $uid,
        ':cat'      => $catId,
        ':name'     => $input['name'],
        ':orig'     => $input['original_name'] ?? $input['name'],
        ':desc'     => $input['description'] ?? null,
        ':path'     => $input['file_path'] ?? null,
        ':url'      => $input['file_url'] ?? null,
        ':mime'     => $input['mime_type'] ?? $input['file_type'] ?? null,
        ':ext'      => $input['extension'] ?? (isset($input['file_url']) ? strtolower(pathinfo($input['file_url'], PATHINFO_EXTENSION)) : null),
        ':size'     => $input['file_size'] ?? 0,
        ':src'      => $input['source_type'] ?? 'upload',
        ':ext_flag' => $input['is_external'] ?? 0,
        ':esrc'     => $input['embed_src'] ?? null,
        ':ecode'    => $input['embed_code'] ?? null,
        ':eprov'    => $input['embed_provider'] ?? null,
        ':vis'      => $input['visibility'] ?? 'public',
    ]);

    json_response(200, ['data' => ['id' => (int)$pdo->lastInsertId(), 'uuid' => $u]]);
}

function updateFile(int $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) json_response(400, ['error' => 'Invalid JSON']);

    $pdo = db();

    // Ownership check
    if (!is_admin()) {
        $s = $pdo->prepare("SELECT user_id FROM files WHERE id=:id AND status='active'");
        $s->execute([':id' => $id]);
        if ((int)$s->fetchColumn() !== auth_id()) {
            json_response(403, ['error' => 'You can only edit your own files']);
        }
    }

    $allowed = ['name','description','visibility','ar_enabled','ar_scale','embed_src','embed_code','embed_provider'];
    $sets = []; $params = [':id' => $id];

    // Handle category slug → id conversion
    if (!empty($input['category'])) {
        $cs = $pdo->prepare("SELECT id FROM categories WHERE slug=:s");
        $cs->execute([':s' => $input['category']]);
        $catId = $cs->fetchColumn();
        if ($catId) {
            $sets[] = 'category_id=:category_id';
            $params[':category_id'] = (int)$catId;
        }
    }

    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $sets[] = "$f=:$f";
            $params[":$f"] = $input[$f];
        }
    }
    // Always update timestamp
    $sets[] = 'updated_at=NOW()';
    if (!$sets) json_response(400, ['error' => 'Nothing to update']);

    $pdo->prepare("UPDATE files SET " . implode(',',$sets) . " WHERE id=:id AND status='active'")->execute($params);
    json_response(200, ['message' => 'Updated']);
}

function deleteFile(int $id) {
    $pdo = db();
    if (!is_admin()) {
        $s = $pdo->prepare("SELECT user_id FROM files WHERE id=:id AND status='active'");
        $s->execute([':id' => $id]);
        if ((int)$s->fetchColumn() !== auth_id()) {
            json_response(403, ['error' => 'You can only delete your own files']);
        }
    }
    $pdo->prepare("UPDATE files SET status='deleted', deleted_at=NOW() WHERE id=:id")->execute([':id'=>$id]);
    json_response(200, ['data' => ['deleted' => true, 'id' => $id]]);
}

function uploadFile() {
    if (!isset($_FILES['file'])) json_response(400, ['error' => 'No file']);
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_response(400, ['error' => 'Upload error: '.$f['error']]);

    // Load upload settings from DB (fallback to config constants)
    $pdo = db();
    $uploadCfg = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('allowed_extensions','max_upload_size')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $maxSize = (int)($uploadCfg['max_upload_size'] ?? MAX_UPLOAD_SIZE);
    $allowedExtStr = $uploadCfg['allowed_extensions'] ?? implode(',', ALLOWED_EXT);
    $allowedExt = array_filter(array_map('trim', explode(',', $allowedExtStr)));

    if ($f['size'] > $maxSize) json_response(400, ['error' => 'File too large']);

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) json_response(400, ['error' => 'File type not allowed: '.$ext]);

    $uid  = uuid();
    $safe = $uid . '.' . $ext;
    $ym   = date('Y/m');
    $dir  = UPLOAD_DIR . $ym . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $dest = $dir . $safe;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        json_response(500, ['error' => 'Failed to save file']);
    }

    // Sanitize GLB/GLTF: strip any leading bytes before the glTF magic header
    if (in_array($ext, ['glb','gltf'])) {
        $raw = file_get_contents($dest);
        $pos = strpos($raw, 'glTF');
        if ($pos !== false && $pos > 0 && $pos <= 8) {
            file_put_contents($dest, substr($raw, $pos));
        }
    }

    json_response(200, ['data' => [
        'file_path' => 'uploads/' . $ym . '/' . $safe,
        'file_url'  => BASE_URL . '/database/uploads/' . $ym . '/' . $safe,
        'original_name' => $f['name'],
        'mime_type' => $f['type'],
        'extension' => $ext,
        'file_size' => $f['size'],
    ]]);
}

function listCategories() {
    json_response(200, ['data' => db()->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll()]);
}

function listUsers() {
    $pdo = db();
    $rows = $pdo->query("
        SELECT u.id, u.username, u.display_name, u.role,
               COUNT(f.id) AS file_count
        FROM users u
        LEFT JOIN files f ON f.user_id = u.id AND f.status='active' AND f.deleted_at IS NULL
        WHERE u.is_active = 1
        GROUP BY u.id
        ORDER BY u.display_name
    ")->fetchAll();
    json_response(200, ['data' => $rows]);
}

function getStats() {
    $uid = auth_id();
    $cond = "f.status='active' AND f.deleted_at IS NULL";
    if (!is_admin() && $uid) {
        $cond .= " AND (f.visibility='public' OR f.user_id=$uid)";
    } elseif (!is_admin()) {
        $cond .= " AND f.visibility='public'";
    }
    $row = db()->query("SELECT COUNT(*) AS total_files,
        SUM(CASE WHEN c.slug='model' THEN 1 ELSE 0 END) AS models,
        SUM(CASE WHEN c.slug='panorama' THEN 1 ELSE 0 END) AS panoramas,
        SUM(CASE WHEN c.slug='image' THEN 1 ELSE 0 END) AS images,
        SUM(CASE WHEN c.slug='embed' THEN 1 ELSE 0 END) AS embeds,
        COALESCE(SUM(f.view_count),0) AS total_views,
        COALESCE(SUM(f.file_size),0) AS total_storage
        FROM files f LEFT JOIN categories c ON c.id=f.category_id
        WHERE $cond")->fetch();
    json_response(200, ['data' => $row]);
}

function incrementView(int $id) {
    db()->prepare("UPDATE files SET view_count=view_count+1 WHERE id=:id")->execute([':id'=>$id]);
    json_response(200, ['message' => 'OK']);
}

function normalizeUrl(?string $url): ?string {
    if (!$url) return null;
    if (preg_match('#^(https?://|/)#i', $url)) return $url;
    return BASE_URL . '/database/' . $url;
}

function bulkUpdateEmbeds() {
    $input = json_decode(file_get_contents('php://input'), true);
    $overrides = $input['overrides'] ?? '';
    if (!$overrides) json_response(400, ['error' => 'Missing overrides']);

    // Parse override params into map
    $overrideMap = [];
    foreach (explode('&', $overrides) as $p) {
        $kv = explode('=', $p, 2);
        if ($kv[0]) $overrideMap[$kv[0]] = $kv[1] ?? '';
    }

    $pdo = db();
    $files = $pdo->query("SELECT id, embed_src FROM files WHERE source_type='embed' AND status='active' AND deleted_at IS NULL")->fetchAll();

    $updated = 0;
    $stmt = $pdo->prepare("UPDATE files SET embed_src = :src WHERE id = :id");

    foreach ($files as $f) {
        $src = $f['embed_src'] ?? '';
        if (!$src) continue;

        // Split URL and query
        $parts = explode('?', $src, 2);
        $base = $parts[0];
        $origQuery = $parts[1] ?? '';

        // Replace sharemodel → embed (case-insensitive), keep /share/ intact
        $base = preg_replace('#/sharemodel(/|$)#i', '/embed$1', $base);

        // Parse original params (keep userId etc)
        $paramMap = [];
        if ($origQuery) {
            foreach (explode('&', $origQuery) as $p) {
                $kv = explode('=', $p, 2);
                if ($kv[0]) $paramMap[$kv[0]] = $kv[1] ?? '';
            }
        }

        // Apply overrides (bg_theme, auto_spin_model) — userId stays from original
        foreach ($overrideMap as $k => $v) {
            $paramMap[$k] = $v;
        }

        $newQuery = http_build_query($paramMap);
        $newSrc = $base . '?' . $newQuery;

        $stmt->execute([':src' => $newSrc, ':id' => $f['id']]);
        $updated++;
    }

    json_response(200, ['data' => ['updated' => $updated]]);
}

/**
 * Lookup a file by its file_url or embed_src
 * GET ?action=lookup&url=...
 */
function lookupByUrl() {
    $url = $_GET['url'] ?? '';
    if (!$url) json_response(400, ['error' => 'Missing url parameter']);

    $pdo = db();

    // Decode URL in case it's encoded
    $decoded = urldecode($url);

    // Try multiple matching strategies
    // 1. Exact match on file_url or embed_src
    $stmt = $pdo->prepare("
        SELECT f.id, f.uuid, f.name, f.file_url, f.embed_src, f.source_type,
               f.file_path, f.extension, f.embed_provider,
               c.slug AS category_slug
        FROM files f
        LEFT JOIN categories c ON c.id=f.category_id
        WHERE f.status='active' AND f.deleted_at IS NULL
          AND (f.file_url = :u1 OR f.embed_src = :u2 OR f.file_url = :u3 OR f.embed_src = :u4)
        LIMIT 1
    ");
    $stmt->execute([':u1' => $url, ':u2' => $url, ':u3' => $decoded, ':u4' => $decoded]);
    $file = $stmt->fetch();

    // 2. Try partial match (URL might contain the file_url path)
    if (!$file) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.uuid, f.name, f.file_url, f.embed_src, f.source_type,
                   f.file_path, f.extension, f.embed_provider,
                   c.slug AS category_slug
            FROM files f
            LEFT JOIN categories c ON c.id=f.category_id
            WHERE f.status='active' AND f.deleted_at IS NULL
              AND (LOCATE(f.file_url, :u1) > 0 OR LOCATE(f.embed_src, :u2) > 0
                   OR LOCATE(:u3, f.file_url) > 0 OR LOCATE(:u4, f.embed_src) > 0)
            LIMIT 1
        ");
        $stmt->execute([':u1' => $decoded, ':u2' => $decoded, ':u3' => $decoded, ':u4' => $decoded]);
        $file = $stmt->fetch();
    }

    // 3. Try matching by file_path (in case URL is a relative upload path)
    if (!$file) {
        // Extract just the uploads/... part if present
        $pathPart = $decoded;
        if (preg_match('#(uploads/.+)$#i', $decoded, $m)) {
            $pathPart = $m[1];
        }
        $stmt = $pdo->prepare("
            SELECT f.id, f.uuid, f.name, f.file_url, f.embed_src, f.source_type,
                   f.file_path, f.extension, f.embed_provider,
                   c.slug AS category_slug
            FROM files f
            LEFT JOIN categories c ON c.id=f.category_id
            WHERE f.status='active' AND f.deleted_at IS NULL
              AND f.file_path LIKE :path
            LIMIT 1
        ");
        $stmt->execute([':path' => '%' . $pathPart . '%']);
        $file = $stmt->fetch();
    }

    if ($file) {
        $file['file_url'] = normalizeUrl($file['file_url']);
        json_response(200, ['data' => ['file' => $file, 'found' => true]]);
    } else {
        json_response(200, ['data' => ['file' => null, 'found' => false]]);
    }
}
