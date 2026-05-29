<?php
/**
 * Authentication & User Management API Endpoints
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $result = Auth::login(
                $data['username'] ?? '',
                $data['password'] ?? '',
                $data['remember'] ?? false
            );
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'register':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $result = Auth::register($data);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'public_labs':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $labs = fetchActiveLabs();
            echo json_encode(['success' => true, 'data' => $labs]);
            break;

        case 'public_stores':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $stores = Database::fetchAll(
                "SELECT cw.id, cw.name, cw.center_name, cw.division_name, cw.unit_name,
                        b.shortname AS building_short, b.name AS building_name
                 FROM chemical_warehouses cw
                 LEFT JOIN buildings b ON cw.building_id = b.id
                 WHERE cw.status = 'active'
                 ORDER BY cw.center_name, cw.division_name, cw.name"
            );
            echo json_encode(['success' => true, 'data' => $stores]);
            break;

        case 'public_buildings':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $buildings = Database::fetchAll(
                "SELECT b.id, b.code, b.shortname, b.name,
                        COUNT(r.id) AS room_count
                 FROM buildings b
                 INNER JOIN rooms r ON r.building_id = b.id
                 GROUP BY b.id
                 HAVING room_count > 0
                 ORDER BY CASE
                     WHEN b.code REGEXP '^F[0-9]' THEN CAST(REPLACE(b.code,'F','') AS DECIMAL(6,2))
                     ELSE 9999
                 END, b.code"
            );
            echo json_encode(['success' => true, 'data' => $buildings]);
            break;

        case 'public_rooms':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $buildingId = (int)($_GET['building_id'] ?? 0);
            if (!$buildingId) throw new Exception('building_id required');
            $rooms = Database::fetchAll(
                "SELECT r.id, r.room_number, r.name, r.floor, r.room_type
                 FROM rooms r
                 WHERE r.building_id = :bid
                 ORDER BY r.floor, r.room_number",
                [':bid' => $buildingId]
            );
            echo json_encode(['success' => true, 'data' => $rooms]);
            break;

        case 'forgot_password':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            $identifier = trim($data['identifier'] ?? '');
            if (!$identifier) throw new Exception('กรุณากรอกชื่อผู้ใช้หรืออีเมล');

            $u = Database::fetch(
                "SELECT id, first_name FROM users WHERE (username=:i OR email=:i) AND is_active=1",
                [':i' => $identifier]
            );
            if (!$u) throw new Exception('ไม่พบบัญชีผู้ใช้นี้ในระบบ');

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            Database::query(
                "UPDATE users SET reset_token=:t, reset_token_expires=:e WHERE id=:id",
                [':t' => $token, ':e' => $expires, ':id' => $u['id']]
            );
            $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . '/v1/pages/reset_password.php?token=' . $token;
            echo json_encode(['success' => true, 'reset_url' => $resetUrl, 'name' => $u['first_name']]);
            break;

        case 'do_reset_password':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data     = json_decode(file_get_contents('php://input'), true);
            $token    = trim($data['token']    ?? '');
            $password = trim($data['password'] ?? '');
            if ($password === '') throw new Exception('กรุณากรอกรหัสผ่าน');

            $u = Database::fetch(
                "SELECT id FROM users WHERE reset_token=:t AND reset_token_expires > NOW() AND is_active=1",
                [':t' => $token]
            );
            if (!$u) throw new Exception('ลิงก์หมดอายุหรือไม่ถูกต้อง กรุณาขอ reset ใหม่อีกครั้ง');

            Database::query(
                "UPDATE users SET password_hash=:h, reset_token=NULL, reset_token_expires=NULL WHERE id=:id",
                [':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $u['id']]
            );
            echo json_encode(['success' => true]);
            break;
            
        case 'logout':
            Auth::logout();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
            break;
            
        case 'me':
            $user = Auth::requireAuth();
            echo json_encode(['success' => true, 'data' => $user]);
            break;
            
        case 'refresh':
            $user = Auth::requireAuth();
            $token = Auth::generateToken($user);
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('auth_token', $token, [
                'expires' => time() + (24 * 60 * 60),
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            echo json_encode(['success' => true, 'token' => $token]);
            break;

        // ==================== USER MANAGEMENT ====================
        case 'users':
            $user = Auth::requireAuth();
            if (!in_array($user['role_name'], ['admin', 'lab_manager'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
                break;
            }
            
            if ($method === 'GET') {
                // List all users — room-based access
                $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                               u.phone, u.department, u.position, u.is_active,
                               u.last_login, u.created_at, u.store_id, u.lab_id, u.avatar_url,
                               COALESCE(ura.room_count, 0) AS room_count,
                               pr.code  AS primary_room_code,
                               pr.name  AS primary_room_name,
                               pb.code  AS primary_bld_code,
                               pb.shortname AS primary_bld_short,
                               r.id as role_id, r.name as role_name, r.display_name as role_display,
                               ls.center_name, ls.division_name, ls.section_name, ls.store_name
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        LEFT JOIN (
                            SELECT user_id,
                                   COUNT(*) AS room_count,
                                   MAX(CASE WHEN is_primary = 1 THEN room_id ELSE NULL END) AS primary_room_id
                            FROM user_room_access
                            GROUP BY user_id
                        ) ura ON ura.user_id = u.id
                        LEFT JOIN rooms    pr ON pr.id  = ura.primary_room_id
                        LEFT JOIN buildings pb ON pb.id = pr.building_id
                        LEFT JOIN lab_stores ls ON u.store_id = ls.id
                        ORDER BY r.level DESC, u.first_name ASC";

                // Lab managers can only see users in their department
                if ($user['role_name'] === 'lab_manager') {
                    $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                                   u.phone, u.department, u.position, u.is_active,
                                   u.last_login, u.created_at, u.store_id, u.lab_id, u.avatar_url,
                                   COALESCE(ura.room_count, 0) AS room_count,
                                   pr.code  AS primary_room_code,
                                   pr.name  AS primary_room_name,
                                   pb.code  AS primary_bld_code,
                                   pb.shortname AS primary_bld_short,
                                   r.id as role_id, r.name as role_name, r.display_name as role_display,
                                   ls.center_name, ls.division_name, ls.section_name, ls.store_name
                            FROM users u
                            JOIN roles r ON u.role_id = r.id
                            LEFT JOIN (
                                SELECT user_id,
                                       COUNT(*) AS room_count,
                                       MAX(CASE WHEN is_primary = 1 THEN room_id ELSE NULL END) AS primary_room_id
                                FROM user_room_access
                                GROUP BY user_id
                            ) ura ON ura.user_id = u.id
                            LEFT JOIN rooms    pr ON pr.id  = ura.primary_room_id
                            LEFT JOIN buildings pb ON pb.id = pr.building_id
                            LEFT JOIN lab_stores ls ON u.store_id = ls.id
                            WHERE u.department = :dept
                            ORDER BY r.level DESC, u.first_name ASC";
                    $users = Database::fetchAll($sql, [':dept' => $user['department']]);
                } else {
                    $users = Database::fetchAll($sql);
                }
                
                // Remove sensitive fields
                foreach ($users as &$u) {
                    unset($u['password_hash'], $u['api_token']);
                }
                
                echo json_encode(['success' => true, 'data' => $users]);
                
            } elseif ($method === 'POST') {
                // Create new user (admin only)
                if ($user['role_name'] !== 'admin') {
                    throw new Exception('Only admins can create users');
                }
                $data = json_decode(file_get_contents('php://input'), true);
                $result = Auth::register($data);
                echo json_encode(['success' => true, 'data' => $result]);
                
            } else {
                throw new Exception('Method not allowed');
            }
            break;

        case 'users_update':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if (!in_array($user['role_name'], ['admin', 'lab_manager'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($data['user_id'] ?? 0);
            if (!$userId) throw new Exception('User ID is required');
            
            // Get target user
            $target = Database::fetch("SELECT u.*, r.name as role_name, r.level as role_level FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id", [':id' => $userId]);
            if (!$target) throw new Exception('User not found');
            
            // Lab managers can only edit users in their lab and lower roles
            if ($user['role_name'] === 'lab_manager') {
                if ((string)$target['lab_id'] !== (string)$user['lab_id']) throw new Exception('Cannot edit users outside your lab');
                if ($target['role_level'] >= 3) throw new Exception('Cannot edit users with equal or higher role');
            }
            
            // Prevent editing self role/status (safety)
            $updateData = [];
            if (isset($data['first_name']) && $data['first_name'] !== '') $updateData['first_name'] = $data['first_name'];
            if (isset($data['last_name']) && $data['last_name'] !== '') $updateData['last_name'] = $data['last_name'];
            if (isset($data['email']) && $data['email'] !== '') {
                // Check email uniqueness
                $existing = Database::fetch("SELECT id FROM users WHERE email = :e AND id != :id", [':e' => $data['email'], ':id' => $userId]);
                if ($existing) throw new Exception('Email already in use');
                $updateData['email'] = $data['email'];
            }
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            if (isset($data['department'])) $updateData['department'] = $data['department'];
            if (isset($data['position'])) $updateData['position'] = $data['position'];
            if (isset($data['store_id'])) $updateData['store_id'] = $data['store_id'] ?: null;
            if (isset($data['lab_id'])) $updateData['lab_id'] = $data['lab_id'] ?: null;
            
            // Role change - admin only
            if (isset($data['role_id']) && $user['role_name'] === 'admin') {
                $newRole = Database::fetch("SELECT id, name FROM roles WHERE id = :id", [':id' => (int)$data['role_id']]);
                if ($newRole) {
                    $updateData['role_id'] = $newRole['id'];
                }
            }
            
            // Status change
            if (isset($data['is_active'])) {
                // Cannot deactivate self
                if ($userId === (int)$user['id']) throw new Exception('Cannot change your own status');
                $updateData['is_active'] = $data['is_active'] ? 1 : 0;
            }
            
            // Password reset
            if (!empty($data['password'])) {
                if (strlen($data['password']) < 6) throw new Exception('Password must be at least 6 characters');
                $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateData)) throw new Exception('No data to update');
            
            Database::update('users', $updateData, 'id = :id', [':id' => $userId]);

            // Keep lab access mapping consistent when primary lab is edited
            if (isset($data['lab_id']) && (int)$data['lab_id'] > 0) {
                ensureUserLabAccessTable();
                $primaryLabId = (int)$data['lab_id'];
                Database::query(
                    "INSERT INTO user_lab_access (user_id, lab_id, is_primary, created_at)
                     VALUES (:uid, :lid, 1, NOW())
                     ON DUPLICATE KEY UPDATE is_primary = 1",
                    [':uid' => $userId, ':lid' => $primaryLabId]
                );
                Database::query(
                    "UPDATE user_lab_access
                     SET is_primary = CASE WHEN lab_id = :lid THEN 1 ELSE 0 END
                     WHERE user_id = :uid",
                    [':uid' => $userId, ':lid' => $primaryLabId]
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;

        case 'user_lab_access':
            $user = Auth::requireAuth();
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin only']);
                break;
            }
            $targetUserId = (int)($_GET['user_id'] ?? 0);
            if (!$targetUserId) throw new Exception('User ID is required');

            ensureUserLabAccessTable();

            $target = Database::fetch(
                "SELECT id, username, first_name, last_name, lab_id FROM users WHERE id = :id",
                [':id' => $targetUserId]
            );
            if (!$target) throw new Exception('User not found');

            $allLabs = fetchActiveLabs();

            $assigned = Database::fetchAll(
                "SELECT ula.lab_id, ula.is_primary, l.name, l.code AS location, l.description
                 FROM user_lab_access ula
                 JOIN labs l ON l.id = ula.lab_id
                 WHERE ula.user_id = :uid
                 ORDER BY ula.is_primary DESC, l.name",
                [':uid' => $targetUserId]
            );

            // Backward compatibility: if mapping empty but user has primary lab_id, auto-expose it
            if (empty($assigned) && !empty($target['lab_id'])) {
                $labRow = fetchActiveLabById((int)$target['lab_id']);
                if ($labRow) {
                    $assigned[] = [
                        'lab_id' => (int)$labRow['lab_id'],
                        'is_primary' => 1,
                        'name' => $labRow['name'],
                        'location' => $labRow['location'] ?? null,
                        'description' => $labRow['description'] ?? null,
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => $target,
                    'all_labs' => $allLabs,
                    'assigned_labs' => $assigned
                ]
            ]);
            break;

        case 'user_lab_access_update':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin only']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $targetUserId = (int)($data['user_id'] ?? 0);
            if (!$targetUserId) throw new Exception('User ID is required');

            $labIdsRaw = $data['lab_ids'] ?? [];
            if (!is_array($labIdsRaw)) throw new Exception('lab_ids must be an array');
            $labIds = array_values(array_unique(array_filter(array_map('intval', $labIdsRaw), function ($id) {
                return $id > 0;
            })));
            if (empty($labIds)) throw new Exception('At least one laboratory is required');

            $primaryLabId = (int)($data['primary_lab_id'] ?? $labIds[0]);
            if (!in_array($primaryLabId, $labIds, true)) {
                $primaryLabId = $labIds[0];
            }

            $target = Database::fetch("SELECT id FROM users WHERE id = :id", [':id' => $targetUserId]);
            if (!$target) throw new Exception('User not found');

            $placeholders = implode(',', array_fill(0, count($labIds), '?'));
            $validLabs = Database::fetchAll(
                "SELECT id FROM labs WHERE is_active = 1 AND id IN ({$placeholders})",
                $labIds
            );
            $validLabIds = array_map('intval', array_column($validLabs, 'id'));
            $labIds = array_values(array_filter($labIds, function ($id) use ($validLabIds) {
                return in_array((int)$id, $validLabIds, true);
            }));
            if (empty($labIds)) throw new Exception('No valid active laboratory selected');
            if (!in_array($primaryLabId, $labIds, true)) {
                $primaryLabId = $labIds[0];
            }

            ensureUserLabAccessTable();
            Database::beginTransaction();
            try {
                Database::query("DELETE FROM user_lab_access WHERE user_id = :uid", [':uid' => $targetUserId]);
                foreach ($labIds as $labId) {
                    Database::query(
                        "INSERT INTO user_lab_access (user_id, lab_id, is_primary, created_at)
                         VALUES (:uid, :lid, :primary, NOW())",
                        [
                            ':uid' => $targetUserId,
                            ':lid' => (int)$labId,
                            ':primary' => ((int)$labId === (int)$primaryLabId) ? 1 : 0
                        ]
                    );
                }

                Database::update('users', ['lab_id' => (int)$primaryLabId], 'id = :id', [':id' => $targetUserId]);
                Database::commit();
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }

            echo json_encode(['success' => true, 'message' => 'User lab access updated successfully']);
            break;

        case 'users_toggle':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only admins can toggle user status']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($data['user_id'] ?? 0);
            if (!$userId) throw new Exception('User ID is required');
            if ($userId === (int)$user['id']) throw new Exception('Cannot toggle your own status');
            
            $target = Database::fetch("SELECT id, is_active FROM users WHERE id = :id", [':id' => $userId]);
            if (!$target) throw new Exception('User not found');
            
            $newStatus = $target['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id = :id', [':id' => $userId]);
            
            echo json_encode(['success' => true, 'data' => ['is_active' => $newStatus]]);
            break;

        case 'users_delete':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only admins can delete users']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($data['user_id'] ?? 0);
            if (!$userId) throw new Exception('User ID is required');
            if ($userId === (int)$user['id']) throw new Exception('Cannot delete yourself');
            
            $target = Database::fetch("SELECT id, username, is_active FROM users WHERE id = :id", [':id' => $userId]);
            if (!$target) throw new Exception('User not found');
            
            // Only allow deleting inactive users
            if ((int)$target['is_active'] === 1) {
                throw new Exception('ต้องปิดใช้งานผู้ใช้ก่อนลบ / Deactivate the user first');
            }
            
            // Check for related data that would block deletion
            $stockCount = (int)Database::fetch(
                "SELECT COUNT(*) as cnt FROM chemical_stock WHERE owner_user_id = :uid",
                [':uid' => $userId]
            )['cnt'];
            
            $borrowCount = (int)Database::fetch(
                "SELECT COUNT(*) as cnt FROM borrow_requests WHERE requester_id = :uid",
                [':uid' => $userId]
            )['cnt'];
            
            $transferCount = (int)Database::fetch(
                "SELECT COUNT(*) as cnt FROM transfers WHERE from_user_id = :uid OR to_user_id = :uid2",
                [':uid' => $userId, ':uid2' => $userId]
            )['cnt'];
            
            if ($stockCount > 0 || $borrowCount > 0 || $transferCount > 0) {
                $details = [];
                if ($stockCount > 0) $details[] = "สารเคมี {$stockCount} รายการ";
                if ($borrowCount > 0) $details[] = "คำขอยืม {$borrowCount} รายการ";
                if ($transferCount > 0) $details[] = "การโอน {$transferCount} รายการ";
                throw new Exception('ไม่สามารถลบได้ ผู้ใช้มีข้อมูลที่เกี่ยวข้อง: ' . implode(', ', $details));
            }
            
            // Safe to hard delete — clean up cascade-able records first
            try {
                Database::query("DELETE FROM user_sessions WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM notification_settings WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM alerts WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM ai_chat_sessions WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM ar_sessions WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM visual_searches WHERE user_id = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM model_requests WHERE requested_by = :uid", [':uid' => $userId]);
                Database::query("DELETE FROM users WHERE id = :uid", [':uid' => $userId]);
                
                echo json_encode(['success' => true, 'message' => "ลบผู้ใช้ {$target['username']} เรียบร้อยแล้ว"]);
            } catch (Exception $delEx) {
                throw new Exception('ลบไม่สำเร็จ: ' . $delEx->getMessage());
            }
            break;

        case 'roles':
            $user = Auth::requireAuth();
            $roles = Database::fetchAll("SELECT id, name, display_name, level FROM roles ORDER BY level DESC");
            echo json_encode(['success' => true, 'data' => $roles]);
            break;

        case 'labs_list':
        case 'org_hierarchy':
            $user = Auth::requireAuth();
            $stores = Database::fetchAll(
                "SELECT id, center_name, division_name, section_name, store_name
                 FROM lab_stores WHERE is_active = 1
                 ORDER BY center_name, division_name, section_name, store_name"
            );
            echo json_encode(['success' => true, 'data' => $stores]);
            break;

        /* ─────────────────────────────────────────────────────
           USER ROOM ACCESS  (ห้องที่ดูแล)
        ───────────────────────────────────────────────────── */
        case 'user_room_access':
            $user = Auth::requireAuth();
            if (!in_array($user['role_name'], ['admin', 'lab_manager'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
                break;
            }
            $targetUserId = (int)($_GET['user_id'] ?? 0);
            if (!$targetUserId) throw new Exception('user_id is required');

            // Rooms the user currently has
            $assigned = Database::fetchAll(
                "SELECT ura.room_id, ura.is_primary,
                        r.name AS room_name, r.code AS room_code,
                        r.floor, r.room_type, r.safety_level,
                        b.name AS building_name, b.code AS building_code, b.shortname AS building_short,
                        COUNT(c.id) AS container_count
                 FROM user_room_access ura
                 JOIN rooms r ON r.id = ura.room_id
                 JOIN buildings b ON b.id = r.building_id
                 LEFT JOIN containers c ON c.room_id = r.id
                 WHERE ura.user_id = :uid
                 GROUP BY ura.room_id, ura.is_primary, r.name, r.code, r.floor,
                          r.room_type, r.safety_level, b.name, b.code, b.shortname
                 ORDER BY ura.is_primary DESC, b.code, r.floor, r.code",
                [':uid' => $targetUserId]
            );

            // All rooms that have containers (for the picker)
            $allRooms = Database::fetchAll(
                "SELECT r.id, r.name, r.code, r.floor, r.room_type, r.safety_level,
                        b.id AS building_id, b.name AS building_name,
                        b.code AS building_code, b.shortname AS building_short,
                        COUNT(c.id) AS container_count
                 FROM rooms r
                 JOIN buildings b ON b.id = r.building_id
                 LEFT JOIN containers c ON c.room_id = r.id
                 GROUP BY r.id, r.name, r.code, r.floor, r.room_type, r.safety_level,
                          b.id, b.name, b.code, b.shortname
                 HAVING container_count > 0
                 ORDER BY b.code, r.floor, r.code"
            );

            echo json_encode(['success' => true, 'data' => [
                'assigned_rooms' => $assigned,
                'all_rooms'      => $allRooms,
            ]]);
            break;

        case 'user_room_access_update':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin only']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $targetUserId  = (int)($data['user_id']        ?? 0);
            $roomIdsRaw    = $data['room_ids']              ?? [];
            $primaryRoomId = (int)($data['primary_room_id'] ?? 0);

            if (!$targetUserId) throw new Exception('user_id is required');
            if (!is_array($roomIdsRaw) || empty($roomIdsRaw))
                throw new Exception('At least one room is required');

            $roomIds = array_values(array_unique(
                array_filter(array_map('intval', $roomIdsRaw), fn($v) => $v > 0)
            ));

            // Validate rooms exist
            $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
            $validRooms   = Database::fetchAll(
                "SELECT id FROM rooms WHERE id IN ({$placeholders})", $roomIds
            );
            $validIds = array_map('intval', array_column($validRooms, 'id'));
            $roomIds  = array_values(array_filter($roomIds, fn($id) => in_array($id, $validIds)));

            if (empty($roomIds)) throw new Exception('No valid rooms selected');
            if (!in_array($primaryRoomId, $roomIds)) $primaryRoomId = $roomIds[0];

            // Replace assignments
            Database::query("DELETE FROM user_room_access WHERE user_id = :uid", [':uid' => $targetUserId]);
            foreach ($roomIds as $rid) {
                Database::query(
                    "INSERT INTO user_room_access (user_id, room_id, is_primary) VALUES (:u, :r, :p)",
                    [':u' => $targetUserId, ':r' => $rid, ':p' => ($rid === $primaryRoomId ? 1 : 0)]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Room access updated']);
            break;

        case 'locked_users':
            $user = Auth::requireAuth();
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin only']);
                break;
            }
            $locked = Database::fetchAll(
                "SELECT id, username, first_name, last_name, full_name_th, login_attempts, locked_until
                 FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()
                 ORDER BY locked_until DESC"
            );
            echo json_encode(['success' => true, 'data' => $locked]);
            break;

        case 'unlock_user':
            $user = Auth::requireAuth();
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if ($user['role_name'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin only']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $uid = (int)($data['user_id'] ?? 0);
            if (!$uid) throw new Exception('User ID is required');
            Database::update('users', ['login_attempts' => 0, 'locked_until' => null], 'id = :id', [':id' => $uid]);
            echo json_encode(['success' => true, 'message' => 'User unlocked']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function ensureUserLabAccessTable(): void {
    Database::query(
        "CREATE TABLE IF NOT EXISTS user_lab_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            lab_id INT NOT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_lab (user_id, lab_id),
            KEY idx_ula_lab_id (lab_id),
            KEY idx_ula_user_primary (user_id, is_primary),
            CONSTRAINT fk_ula_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_ula_lab FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function fetchActiveLabs(): array {
    try {
        return Database::fetchAll(
            "SELECT id, name, code AS location, description
             FROM labs
             WHERE is_active = 1
             ORDER BY name"
        );
    } catch (Exception $e) {
        return Database::fetchAll(
            "SELECT id, name, NULL AS location, description
             FROM labs
             ORDER BY name"
        );
    }
}

function fetchActiveLabById(int $labId): ?array {
    try {
        return Database::fetch(
            "SELECT id AS lab_id, name, code AS location, description
             FROM labs
             WHERE id = :id AND is_active = 1",
            [':id' => $labId]
        );
    } catch (Exception $e) {
        return Database::fetch(
            "SELECT id AS lab_id, name, NULL AS location, description
             FROM labs
             WHERE id = :id",
            [':id' => $labId]
        );
    }
}
