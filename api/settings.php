<?php
/**
 * System Settings API - Admin only
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

// Admin only
if ($user['role_name'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $category = $_GET['category'] ?? null;
            $settings = getSettings($category);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data) || !is_array($data)) {
                throw new Exception('Invalid data');
            }
            $updated = saveSettings($data, $user['id']);
            echo json_encode(['success' => true, 'data' => ['updated' => $updated]]);
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getSettings(?string $category = null): array {
    $sql = "SELECT setting_key, setting_value, setting_type, category, label, description FROM system_settings";
    $params = [];
    if ($category) {
        $sql .= " WHERE category = :cat";
        $params[':cat'] = $category;
    }
    $sql .= " ORDER BY category, setting_key";
    $rows = Database::fetchAll($sql, $params);

    // Group by category
    $grouped = [];
    foreach ($rows as $row) {
        // Cast value by type
        $val = $row['setting_value'];
        if ($row['setting_type'] === 'boolean') $val = ($val === '1' || $val === 'true');
        elseif ($row['setting_type'] === 'integer') $val = (int)$val;
        elseif ($row['setting_type'] === 'json') $val = json_decode($val, true);

        $grouped[$row['category']][] = [
            'key' => $row['setting_key'],
            'value' => $val,
            'type' => $row['setting_type'],
            'label' => $row['label'],
            'description' => $row['description'],
        ];
    }
    return $grouped;
}

function saveSettings(array $data, int $userId): int {
    $count = 0;
    $stmt = Database::getInstance()->prepare(
        "UPDATE system_settings SET setting_value = :val, updated_by = :uid WHERE setting_key = :key"
    );
    foreach ($data as $key => $value) {
        // Validate key exists
        $existing = Database::fetch("SELECT setting_key, setting_type FROM system_settings WHERE setting_key = :k", [':k' => $key]);
        if (!$existing) continue;

        // Normalize value
        if ($existing['setting_type'] === 'boolean') {
            $value = ($value === true || $value === '1' || $value === 'true') ? '1' : '0';
        } else {
            $value = (string)$value;
        }

        $stmt->execute([':val' => $value, ':uid' => $userId, ':key' => $key]);
        $count++;
    }
    return $count;
}
