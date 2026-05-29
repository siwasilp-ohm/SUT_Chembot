<?php
/**
 * Page Permissions API  (admin only)
 *
 * GET  ?action=roles         → all roles with user counts
 * GET  ?action=get_all       → all roles + their configured page lists
 * GET  ?action=get_role&role_id=X → allowed page keys for one role
 * POST ?action=save          → { role_id, pages: [...keys] }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

$user = Auth::requireAuth();
if (($user['role_name'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin only']);
    exit;
}

// Auto-create table (no FK — avoids engine/reference failures)
try {
    Database::query("CREATE TABLE IF NOT EXISTS page_permissions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        role_id    INT          NOT NULL,
        page_key   VARCHAR(100) NOT NULL,
        is_allowed TINYINT(1)   NOT NULL DEFAULT 1,
        updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_role_page (role_id, page_key),
        INDEX idx_role (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) {
    // table already exists or DB engine issue — continue
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // ─── Roles with user counts ───────────────────────────────────────
        case 'roles':
            $rows = Database::fetchAll("
                SELECT r.id, r.name, r.display_name, r.level, r.description,
                       COUNT(u.id) AS user_count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id AND u.is_active = 1
                GROUP BY r.id
                ORDER BY r.level ASC
            ");
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ─── All roles + their configured pages ──────────────────────────
        case 'get_all':
            $roles = Database::fetchAll("SELECT id, name, display_name, level FROM roles ORDER BY level");
            $perms = Database::fetchAll("SELECT role_id, page_key FROM page_permissions WHERE is_allowed = 1");
            $map   = [];
            foreach ($perms as $p) {
                $map[(int)$p['role_id']][$p['page_key']] = true;
            }
            $result = [];
            foreach ($roles as $r) {
                $result[] = ['role' => $r, 'permissions' => $map[(int)$r['id']] ?? []];
            }
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        // ─── Single role's allowed pages ─────────────────────────────────
        case 'get_role':
            $roleId = (int)($_GET['role_id'] ?? 0);
            if (!$roleId) throw new Exception('Missing role_id');
            $rows = Database::fetchAll(
                "SELECT page_key FROM page_permissions WHERE role_id = ? AND is_allowed = 1",
                [$roleId]
            );
            $map = [];
            foreach ($rows as $r) $map[$r['page_key']] = true;
            echo json_encode(['success' => true, 'data' => $map, 'has_custom' => count($map) > 0]);
            break;

        // ─── Save permissions (full replace for the role) ─────────────────
        case 'save':
            $body   = json_decode(file_get_contents('php://input'), true) ?? [];
            $roleId = (int)($body['role_id'] ?? 0);
            $pages  = $body['pages'] ?? [];

            if (!$roleId) throw new Exception('Invalid role_id');

            // Sanitise
            $clean = [];
            foreach ($pages as $k) {
                $k = preg_replace('/[^a-z0-9\-_]/', '', strtolower((string)$k));
                if ($k !== '') $clean[] = $k;
            }

            // Atomic replace
            Database::query("DELETE FROM page_permissions WHERE role_id = ?", [$roleId]);
            foreach ($clean as $key) {
                Database::query(
                    "INSERT IGNORE INTO page_permissions (role_id, page_key, is_allowed) VALUES (?, ?, 1)",
                    [$roleId, $key]
                );
            }

            // Best-effort audit log
            try {
                Database::query(
                    "INSERT INTO audit_logs (user_id, action, target_type, details, created_at)
                     VALUES (?, 'page_permissions_updated', 'role', ?, NOW())",
                    [$user['id'], json_encode(['role_id' => $roleId, 'pages' => $clean])]
                );
            } catch (\Throwable $e) {}

            echo json_encode(['success' => true, 'saved' => count($clean)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
