<?php
/**
 * VRX Studio — REST API (Auth-Enabled)
 * Lightweight PHP API for XAMPP / MySQL
 * 
 * Endpoints:
 *   GET    /api.php?action=files              — List files (gallery)
 *   GET    /api.php?action=files&id=123       — Get single file
 *   POST   /api.php?action=files              — Create file record  [user+]
 *   PUT    /api.php?action=files&id=123       — Update file          [user+]
 *   DELETE /api.php?action=files&id=123       — Soft delete file     [user+]
 *   POST   /api.php?action=upload             — Upload file binary   [user+]
 *   GET    /api.php?action=categories         — List categories
 *   GET    /api.php?action=tags               — List tags
 *   GET    /api.php?action=stats              — Dashboard stats
 *   POST   /api.php?action=view&id=123        — Increment view count
 */

/* ── Auth ── */
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ── Database ── */
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMBNAIL_DIR', __DIR__ . '/uploads/thumbnails/');

$pdo = vrx_db();   // shared singleton from session.php

/* ── Ensure upload dirs ── */
if (!is_dir(UPLOAD_DIR))    mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(THUMBNAIL_DIR)) mkdir(THUMBNAIL_DIR, 0755, true);

/* ── Route ── */
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($action) {
    case 'files':
        if ($method === 'GET' && $id)       getFile($pdo, $id);
        elseif ($method === 'GET')           listFiles($pdo);
        elseif ($method === 'POST')          { requireApiAuth('upload'); createFile($pdo); }
        elseif ($method === 'PUT' && $id)    { requireApiAuth('edit_own'); updateFile($pdo, $id); }
        elseif ($method === 'DELETE' && $id) { requireApiAuth('delete_own'); deleteFile($pdo, $id); }
        else badRequest();
        break;

    case 'upload':
        if ($method === 'POST') { requireApiAuth('upload'); uploadFile($pdo); }
        else badRequest();
        break;

    case 'categories':
        listCategories($pdo);
        break;

    case 'tags':
        listTags($pdo);
        break;

    case 'stats':
        getStats($pdo);
        break;

    case 'view':
        if ($method === 'POST' && $id) incrementView($pdo, $id);
        else badRequest();
        break;

    case 'log_scan':
        if ($method === 'POST') logScan($pdo);
        else badRequest();
        break;

    default:
        jsonResponse(404, ['error' => 'Unknown action', 'available' => ['files','upload','categories','tags','stats','view','log_scan']]);
}

// ────────────────────────────────────────────
// Auth helpers
// ────────────────────────────────────────────

/**
 * Guard: require the current session has the given permission.
 * Returns JSON 401/403 on failure — never returns on error.
 */
function requireApiAuth(string $permission): void {
    if (!vrx_is_logged_in()) {
        jsonResponse(401, ['error' => 'Authentication required']);
    }
    if (!vrx_can($permission)) {
        jsonResponse(403, ['error' => 'Forbidden — insufficient permissions', 'required' => $permission]);
    }
}

/** Get the logged-in user id (or null for guests). */
function currentUserId(): ?int {
    return vrx_is_logged_in() ? (int)$_SESSION['vrx_user_id'] : null;
}

/** Is the current user an admin? */
function isAdmin(): bool {
    return vrx_role() === 'admin';
}

// ────────────────────────────────────────────
// Handlers
// ────────────────────────────────────────────

function listFiles($pdo) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = min(100, max(1, (int)($_GET['limit'] ?? 24)));
    $offset   = ($page - 1) * $limit;
    $category = $_GET['category'] ?? '';
    $search   = $_GET['search'] ?? '';
    $sort     = $_GET['sort'] ?? 'newest';
    $source   = $_GET['source'] ?? '';

    $where  = ["f.status = 'active'", "f.deleted_at IS NULL"];
    $params = [];

    /* Visibility: non-admin only sees public + own files */
    if (!isAdmin()) {
        $uid = currentUserId();
        if ($uid) {
            $where[]  = "(f.visibility = 'public' OR f.user_id = :owner_uid)";
            $params[':owner_uid'] = $uid;
        } else {
            $where[] = "f.visibility = 'public'";
        }
    }

    if ($category && $category !== 'all') {
        $where[]  = "c.slug = :category";
        $params[':category'] = $category;
    }

    if ($source) {
        $where[]  = "f.source_type = :source";
        $params[':source'] = $source;
    }

    if ($search) {
        $where[]  = "(f.name LIKE :search OR f.description LIKE :search2 OR f.original_name LIKE :search3)";
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }

    $whereSQL = implode(' AND ', $where);

    // Sort
    $orderMap = [
        'newest'    => 'f.uploaded_at DESC',
        'oldest'    => 'f.uploaded_at ASC',
        'name_asc'  => 'f.name ASC',
        'name_desc' => 'f.name DESC',
        'popular'   => 'f.view_count DESC',
        'largest'   => 'f.file_size DESC',
        'smallest'  => 'f.file_size ASC',
    ];
    $orderSQL = $orderMap[$sort] ?? 'f.uploaded_at DESC';

    // Total count
    $countSQL = "SELECT COUNT(*) FROM files f LEFT JOIN categories c ON c.id = f.category_id WHERE $whereSQL";
    $stmt = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Fetch
    $sql = "SELECT
        f.id, f.uuid, f.name, f.original_name, f.description, f.slug,
        f.file_path, f.file_url, f.thumbnail_path, f.mime_type, f.extension,
        f.file_size, f.source_type, f.is_external,
        f.embed_src, f.embed_code, f.embed_provider,
        f.model_format, f.is_panorama, f.panorama_type,
        f.view_count, f.download_count, f.like_count, f.share_count,
        f.rating_avg, f.rating_count,
        f.visibility, f.has_qr, f.ar_enabled, f.ar_scale,
        f.status, f.uploaded_at, f.updated_at,
        f.user_id,
        c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
    FROM files f
    LEFT JOIN categories c ON c.id = f.category_id
    WHERE $whereSQL
    ORDER BY $orderSQL
    LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $files = $stmt->fetchAll();

    // Normalize file_url / thumbnail_path to web-accessible paths
    foreach ($files as &$f) {
        if (!empty($f['file_url']) && !preg_match('#^https?://#i', $f['file_url']) && strpos($f['file_url'], '/') !== 0) {
            $f['file_url'] = '/vrx/database/' . $f['file_url'];
        }
        if (!empty($f['thumbnail_path']) && !preg_match('#^https?://#i', $f['thumbnail_path']) && strpos($f['thumbnail_path'], '/') !== 0) {
            $f['thumbnail_path'] = '/vrx/database/' . $f['thumbnail_path'];
        }
    }
    unset($f);

    jsonResponse(200, [
        'data'       => $files,
        'pagination' => [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'totalPages' => (int)ceil($total / max(1, $limit)),
        ],
    ]);
}

function getFile($pdo, $id) {
    $sql = "SELECT f.*, c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
            FROM files f LEFT JOIN categories c ON c.id = f.category_id
            WHERE f.id = :id AND f.status = 'active' AND f.deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $file = $stmt->fetch();

    if (!$file) {
        jsonResponse(404, ['error' => 'File not found']);
    }

    /* Visibility check: non-admin can only see public or own files */
    if (!isAdmin() && $file['visibility'] !== 'public') {
        $uid = currentUserId();
        if (!$uid || (int)$file['user_id'] !== $uid) {
            jsonResponse(403, ['error' => 'Access denied']);
        }
    }

    // Get tags
    $tagStmt = $pdo->prepare("SELECT t.name FROM file_tags ft JOIN tags t ON t.id = ft.tag_id WHERE ft.file_id = :fid ORDER BY t.name");
    $tagStmt->execute([':fid' => $id]);
    $file['tags'] = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

    // Normalize paths
    if (!empty($file['file_url']) && !preg_match('#^https?://#i', $file['file_url']) && strpos($file['file_url'], '/') !== 0) {
        $file['file_url'] = '/vrx/database/' . $file['file_url'];
    }
    if (!empty($file['thumbnail_path']) && !preg_match('#^https?://#i', $file['thumbnail_path']) && strpos($file['thumbnail_path'], '/') !== 0) {
        $file['thumbnail_path'] = '/vrx/database/' . $file['thumbnail_path'];
    }

    jsonResponse(200, ['data' => $file]);
}

function createFile($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['name'])) {
        jsonResponse(400, ['error' => 'Missing required field: name']);
    }

    $uuid = generateUUID();
    $userId = currentUserId() ?? 1;
    $categoryId = null;
    if (isset($input['category'])) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug");
        $stmt->execute([':slug' => $input['category']]);
        $categoryId = $stmt->fetchColumn() ?: null;
    }

    $sql = "INSERT INTO files (uuid, user_id, category_id, name, original_name, description, file_url, mime_type, extension, file_size, source_type, is_external, embed_src, embed_code, embed_provider, model_format, is_panorama, visibility)
            VALUES (:uuid, :uid, :cat, :name, :orig, :desc, :url, :mime, :ext, :size, :src, :ext_flag, :embed_src, :embed_code, :embed_prov, :model_fmt, :pano, :vis)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uuid'       => $uuid,
        ':uid'        => $userId,
        ':cat'        => $categoryId,
        ':name'       => $input['name'],
        ':orig'       => $input['original_name'] ?? $input['name'],
        ':desc'       => $input['description'] ?? null,
        ':url'        => $input['file_url'] ?? null,
        ':mime'       => $input['mime_type'] ?? null,
        ':ext'        => $input['extension'] ?? null,
        ':size'       => $input['file_size'] ?? 0,
        ':src'        => $input['source_type'] ?? 'upload',
        ':ext_flag'   => $input['is_external'] ?? 0,
        ':embed_src'  => $input['embed_src'] ?? null,
        ':embed_code' => $input['embed_code'] ?? null,
        ':embed_prov' => $input['embed_provider'] ?? null,
        ':model_fmt'  => $input['model_format'] ?? null,
        ':pano'       => $input['is_panorama'] ?? 0,
        ':vis'        => $input['visibility'] ?? 'private',
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Handle tags — FIXED: use prepared statements (was SQL injection)
    if (!empty($input['tags']) && is_array($input['tags'])) {
        $insertTag = $pdo->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (:n, :s)");
        $selectTag = $pdo->prepare("SELECT id FROM tags WHERE slug = :slug");
        $linkTag   = $pdo->prepare("INSERT IGNORE INTO file_tags (file_id, tag_id) VALUES (:fid, :tid)");
        $bumpTag   = $pdo->prepare("UPDATE tags SET usage_count = usage_count + 1 WHERE id = :id");

        foreach ($input['tags'] as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') continue;
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tagName));

            $insertTag->execute([':n' => $tagName, ':s' => $slug]);

            $selectTag->execute([':slug' => $slug]);
            $tagId = $selectTag->fetchColumn();

            if ($tagId) {
                $linkTag->execute([':fid' => $newId, ':tid' => $tagId]);
                $bumpTag->execute([':id' => $tagId]);
            }
        }
    }

    jsonResponse(201, ['data' => ['id' => $newId, 'uuid' => $uuid], 'message' => 'File created']);
}

function updateFile($pdo, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) jsonResponse(400, ['error' => 'Invalid JSON']);

    /* Ownership check: non-admin can only edit own files */
    if (!isAdmin()) {
        $stmt = $pdo->prepare("SELECT user_id FROM files WHERE id = :id AND status = 'active'");
        $stmt->execute([':id' => $id]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId !== currentUserId()) {
            jsonResponse(403, ['error' => 'You can only edit your own files']);
        }
    }

    $allowed = ['name','description','visibility','ar_enabled','ar_scale','custom_meta'];
    $sets = [];
    $params = [':id' => $id];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $sets[] = "$field = :$field";
            $params[":$field"] = is_array($input[$field]) ? json_encode($input[$field]) : $input[$field];
        }
    }

    if (empty($sets)) jsonResponse(400, ['error' => 'No valid fields to update']);

    $sql = "UPDATE files SET " . implode(', ', $sets) . " WHERE id = :id AND status = 'active'";
    $pdo->prepare($sql)->execute($params);

    jsonResponse(200, ['message' => 'File updated']);
}

function deleteFile($pdo, $id) {
    /* Ownership check: non-admin can only delete own files */
    if (!isAdmin()) {
        $stmt = $pdo->prepare("SELECT user_id FROM files WHERE id = :id AND status = 'active'");
        $stmt->execute([':id' => $id]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId !== currentUserId()) {
            jsonResponse(403, ['error' => 'You can only delete your own files']);
        }
    }

    $sql = "UPDATE files SET status = 'deleted', deleted_at = NOW() WHERE id = :id";
    $pdo->prepare($sql)->execute([':id' => $id]);
    jsonResponse(200, ['message' => 'File deleted']);
}

function uploadFile($pdo) {
    if (!isset($_FILES['file'])) {
        jsonResponse(400, ['error' => 'No file uploaded']);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(400, ['error' => 'Upload error code: ' . $file['error']]);
    }

    $maxSize = 100 * 1024 * 1024; // 100MB
    if ($file['size'] > $maxSize) {
        jsonResponse(400, ['error' => 'File exceeds 100MB limit']);
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uuid     = generateUUID();
    $safeName = $uuid . '.' . $ext;
    $yearMon  = date('Y/m');
    $destDir  = UPLOAD_DIR . $yearMon . '/';

    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $destPath = $destDir . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        jsonResponse(500, ['error' => 'Failed to save file']);
    }

    $relativePath = 'uploads/' . $yearMon . '/' . $safeName;
    $webPath      = '/vrx/database/uploads/' . $yearMon . '/' . $safeName;
    $fileHash     = hash_file('sha256', $destPath);

    jsonResponse(200, [
        'data' => [
            'file_path'     => $relativePath,
            'file_url'      => $webPath,
            'original_name' => $file['name'],
            'mime_type'     => $file['type'],
            'extension'     => $ext,
            'file_size'     => $file['size'],
            'file_hash'     => $fileHash,
        ],
        'message' => 'File uploaded successfully',
    ]);
}

function listCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC");
    jsonResponse(200, ['data' => $stmt->fetchAll()]);
}

function listTags($pdo) {
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare("SELECT * FROM tags ORDER BY usage_count DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    jsonResponse(200, ['data' => $stmt->fetchAll()]);
}

function getStats($pdo) {
    /* Single query instead of 9 separate queries — O(1) round-trips instead of O(n) */
    $sql = "SELECT
        COUNT(*)                                                    AS total_files,
        SUM(CASE WHEN c.slug = 'model'    THEN 1 ELSE 0 END)       AS total_models,
        SUM(CASE WHEN c.slug = 'panorama' THEN 1 ELSE 0 END)       AS total_panoramas,
        SUM(CASE WHEN c.slug = 'image'    THEN 1 ELSE 0 END)       AS total_images,
        SUM(CASE WHEN c.slug = 'embed'    THEN 1 ELSE 0 END)       AS total_embeds,
        COALESCE(SUM(f.view_count), 0)                              AS total_views,
        COALESCE(SUM(f.file_size), 0)                               AS total_storage
    FROM files f
    LEFT JOIN categories c ON c.id = f.category_id
    WHERE f.status = 'active'";

    $row = $pdo->query($sql)->fetch();
    $row['total_qr']    = (int)$pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
    $row['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

    /* Cast all to int */
    foreach ($row as $k => &$v) $v = (int)$v;
    unset($v);

    jsonResponse(200, ['data' => $row]);
}

function incrementView($pdo, $id) {
    $pdo->prepare("UPDATE files SET view_count = view_count + 1 WHERE id = :id")->execute([':id' => $id]);
    jsonResponse(200, ['message' => 'View counted']);
}

// ── QR Scan Logging ──

function logScan($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['url'])) {
        jsonResponse(400, ['error' => 'Missing url field']);
    }

    $userId  = currentUserId();
    $url     = substr($input['url'], 0, 2000);
    $type    = substr($input['type']  ?? 'external', 0, 50);
    $name    = substr($input['name']  ?? '',         0, 255);

    // Log to activity_log
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, action, details, ip_address, user_agent)
        VALUES (:uid, 'qr_scan', :details, :ip, :ua)
    ");
    $details = json_encode([
        'url'  => $url,
        'type' => $type,
        'name' => $name,
    ], JSON_UNESCAPED_UNICODE);
    $stmt->execute([
        ':uid'     => $userId,
        ':details' => $details,
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);

    // If it's a VRX QR, update scan_count in qr_codes table
    if ($type !== 'external' && strpos($url, '/vrx/') !== false) {
        $pdo->prepare("
            UPDATE qr_codes SET scan_count = scan_count + 1, last_scanned_at = NOW()
            WHERE qr_data_url LIKE :url
            ORDER BY created_at DESC LIMIT 1
        ")->execute([':url' => '%' . $url . '%']);
    }

    jsonResponse(200, ['message' => 'Scan logged', 'logged' => true]);
}

// ── Helpers ──

function jsonResponse($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function badRequest() {
    jsonResponse(400, ['error' => 'Bad request']);
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
