<?php
/**
 * VRX Studio — Core Configuration
 * Single source of truth for all settings
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'vrx_studio');
define('DB_USER', 'root');
define('DB_PASS', '');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/vrx');
define('UPLOAD_DIR', BASE_PATH . '/database/uploads/');
define('THUMB_DIR', UPLOAD_DIR . 'thumbnails/');

// Upload limits
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXT', ['glb','gltf','obj','fbx','stl','jpg','jpeg','png','webp','gif','svg','bmp','hdr','mp4','webm','pdf']);

// Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * PDO singleton
 */
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

/**
 * Auth helpers
 */
function auth_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'           => (int)$_SESSION['user_id'],
        'username'     => $_SESSION['username'] ?? '',
        'display_name' => $_SESSION['display_name'] ?? '',
        'role'         => $_SESSION['role'] ?? 'viewer',
    ];
}

function auth_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function auth_role(): string {
    return $_SESSION['role'] ?? 'viewer';
}

function is_logged_in(): bool {
    return auth_id() > 0;
}

function is_admin(): bool {
    return auth_role() === 'admin';
}

function can(string $perm): bool {
    $map = [
        'admin'  => ['view','upload','edit','delete','manage'],
        'user'   => ['view','upload','edit','delete'],
        'viewer' => ['view'],
    ];
    return in_array($perm, $map[auth_role()] ?? ['view'], true);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_auth_api(string $perm = 'view'): void {
    if (!is_logged_in()) {
        json_response(401, ['error' => 'Login required']);
    }
    if (!can($perm)) {
        json_response(403, ['error' => 'Permission denied']);
    }
}

/**
 * JSON response helper
 */
function json_response(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * UUID v4 generator
 */
function uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Sanitize filename
 */
function safe_filename(string $name): string {
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
}

// Ensure upload dirs exist
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(THUMB_DIR))  mkdir(THUMB_DIR, 0755, true);
