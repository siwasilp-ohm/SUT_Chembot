<?php
/**
 * User Profile API
 * GET  ?action=profile            — get current user profile
 * GET  ?action=departments&level=X&parent_id=Y — get departments by level/parent
 * POST ?action=update_profile     — update profile fields
 * POST ?action=upload_avatar      — upload avatar image
 * POST ?action=set_default_avatar — set a default avatar
 * POST ?action=change_password    — change password
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $user = Auth::requireAuth();

    switch ($action) {

        // ──────────────── GET PROFILE ────────────────
        case 'profile':
            if ($method !== 'GET') throw new Exception('Method not allowed');

            $profile = Database::fetch(
                "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.full_name_th,
                        u.phone, u.avatar_url, u.department, u.position,
                        u.department_id, u.organization_id,
                        u.theme_preference, u.primary_color, u.language,
                        u.email_notifications, u.push_notifications,
                        u.created_at, u.last_login,
                        r.name as role_name, r.display_name as role_display
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 WHERE u.id = :id",
                [':id' => $user['id']]
            );

            if (!$profile) throw new Exception('User not found');
            unset($profile['password_hash']);

            // Get org hierarchy for this user's department
            $orgPath = [];
            if ($profile['department_id']) {
                $orgPath = getOrgPath((int)$profile['department_id']);
            }
            $profile['org_path'] = $orgPath;

            echo json_encode(['success' => true, 'data' => $profile]);
            break;

        // ──────────────── DEPARTMENTS BY LEVEL ────────────────
        case 'departments':
            if ($method !== 'GET') throw new Exception('Method not allowed');

            $level = isset($_GET['level']) ? (int)$_GET['level'] : null;
            $parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

            $sql = "SELECT id, name, name_en, level, level_label, parent_id FROM departments WHERE is_active = 1";
            $params = [];

            if ($level !== null) {
                $sql .= " AND level = :level";
                $params[':level'] = $level;
            }
            if ($parentId !== null) {
                $sql .= " AND parent_id = :pid";
                $params[':pid'] = $parentId;
            }
            $sql .= " ORDER BY sort_order, name";

            $rows = Database::fetchAll($sql, $params);
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ──────────────── UPDATE PROFILE ────────────────
        case 'update_profile':
            if ($method !== 'POST') throw new Exception('Method not allowed');

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) throw new Exception('No data provided');

            $allowed = [
                'first_name', 'last_name', 'full_name_th',
                'phone', 'position', 'department',
                'department_id', 'organization_id',
                'theme_preference', 'primary_color', 'language',
                'email_notifications', 'push_notifications'
            ];

            $updateData = [];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $val = $data[$field];
                    if (in_array($field, ['email_notifications', 'push_notifications'])) {
                        $val = $val ? 1 : 0;
                    }
                    if ($field === 'department_id' || $field === 'organization_id') {
                        $val = $val ? (int)$val : null;
                    }
                    $updateData[$field] = $val;
                }
            }

            // Email change — check uniqueness
            if (!empty($data['email']) && $data['email'] !== $user['email']) {
                $exists = Database::fetch(
                    "SELECT id FROM users WHERE email = :e AND id != :id",
                    [':e' => $data['email'], ':id' => $user['id']]
                );
                if ($exists) throw new Exception('Email already in use');
                $updateData['email'] = $data['email'];
            }

            if (empty($updateData)) throw new Exception('Nothing to update');

            Database::update('users', $updateData, 'id = :id', [':id' => $user['id']]);

            echo json_encode(['success' => true, 'message' => 'Profile updated']);
            break;

        // ──────────────── UPLOAD AVATAR ────────────────
        case 'upload_avatar':
            if ($method !== 'POST') throw new Exception('Method not allowed');

            if (empty($_FILES['avatar'])) throw new Exception('No file uploaded');

            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error: ' . $file['error']);

            // Validate
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) throw new Exception('File too large (max 5MB)');

            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedTypes)) throw new Exception('Invalid file type. Allowed: JPG, PNG, WebP, GIF');

            // Generate filename
            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'jpg'
            };
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
            $destPath = $uploadDir . $filename;

            // Create directory if not exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Resize image to 256x256
            $resized = resizeAvatar($file['tmp_name'], $mime, 256);
            if ($resized) {
                file_put_contents($destPath, $resized);
            } else {
                move_uploaded_file($file['tmp_name'], $destPath);
            }

            // Delete old avatar
            $old = Database::fetch("SELECT avatar_url FROM users WHERE id = :id", [':id' => $user['id']]);
            if ($old && $old['avatar_url']) {
                $oldFile = __DIR__ . '/../' . ltrim($old['avatar_url'], '/');
                if (file_exists($oldFile)) @unlink($oldFile);
            }

            // Update DB
            $avatarUrl = '/v1/assets/uploads/avatars/' . $filename;
            Database::update('users', ['avatar_url' => $avatarUrl], 'id = :id', [':id' => $user['id']]);

            echo json_encode(['success' => true, 'data' => ['avatar_url' => $avatarUrl]]);
            break;

        // ──────────────── SET DEFAULT AVATAR ────────────────
        case 'set_default_avatar':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $input = json_decode(file_get_contents('php://input'), true);
            $avatar = $input['avatar'] ?? '';
            if (!$avatar) throw new Exception('No avatar selected');

            // Validate: must be a file inside /assets/uploads/avatars/default/
            $basename = basename($avatar);
            $localPath = __DIR__ . '/../assets/uploads/avatars/default/' . $basename;
            if (!file_exists($localPath)) throw new Exception('Invalid avatar file');

            // Delete old custom avatar (only if it's NOT a default)
            $old = Database::fetch("SELECT avatar_url FROM users WHERE id = :id", [':id' => $user['id']]);
            if ($old && $old['avatar_url'] && strpos($old['avatar_url'], '/default/') === false) {
                $oldFile = __DIR__ . '/../' . ltrim($old['avatar_url'], '/');
                if (file_exists($oldFile)) @unlink($oldFile);
            }

            $avatarUrl = '/v1/assets/uploads/avatars/default/' . $basename;
            Database::update('users', ['avatar_url' => $avatarUrl], 'id = :id', [':id' => $user['id']]);

            echo json_encode(['success' => true, 'data' => ['avatar_url' => $avatarUrl]]);
            break;

        // ──────────────── CHANGE PASSWORD ────────────────
        case 'change_password':
            if ($method !== 'POST') throw new Exception('Method not allowed');

            $data = json_decode(file_get_contents('php://input'), true);
            $current = $data['current_password'] ?? '';
            $newPass = $data['new_password'] ?? '';
            $confirm = $data['confirm_password'] ?? '';

            if (!$current || !$newPass) throw new Exception('All fields required');
            if ($newPass !== $confirm) throw new Exception('Passwords do not match');
            if (strlen($newPass) < 6) throw new Exception('Password must be at least 6 characters');

            // Verify current password
            $u = Database::fetch("SELECT password_hash FROM users WHERE id = :id", [':id' => $user['id']]);
            if (!password_verify($current, $u['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }

            Database::update('users', [
                'password_hash' => password_hash($newPass, PASSWORD_DEFAULT)
            ], 'id = :id', [':id' => $user['id']]);

            echo json_encode(['success' => true, 'message' => 'Password changed']);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ──────────────── HELPERS ────────────────

function getOrgPath(int $deptId): array {
    $path = [];
    $current = $deptId;
    $maxDepth = 10;
    while ($current && $maxDepth-- > 0) {
        $dept = Database::fetch(
            "SELECT id, name, level, level_label, parent_id FROM departments WHERE id = :id",
            [':id' => $current]
        );
        if (!$dept) break;
        array_unshift($path, $dept);
        $current = $dept['parent_id'];
    }
    return $path;
}

function resizeAvatar(string $tmpPath, string $mime, int $size): ?string {
    if (!extension_loaded('gd')) return null;

    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmpPath),
        'image/png'  => @imagecreatefrompng($tmpPath),
        'image/webp' => @imagecreatefromwebp($tmpPath),
        'image/gif'  => @imagecreatefromgif($tmpPath),
        default      => null
    };
    if (!$src) return null;

    $w = imagesx($src);
    $h = imagesy($src);

    // Crop to square from center
    $cropSize = min($w, $h);
    $x = (int)(($w - $cropSize) / 2);
    $y = (int)(($h - $cropSize) / 2);

    $dst = imagecreatetruecolor($size, $size);
    // Preserve transparency for PNG/WebP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $size, $size, $cropSize, $cropSize);

    ob_start();
    if ($mime === 'image/png') {
        imagepng($dst, null, 8);
    } elseif ($mime === 'image/webp') {
        imagewebp($dst, null, 85);
    } else {
        imagejpeg($dst, null, 90);
    }
    $data = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    return $data;
}
