<?php
/**
 * User Import/Export API
 * 
 * Endpoints:
 *   GET  ?action=export          → Download users as CSV
 *   GET  ?action=export_template → Download empty CSV template
 *   POST ?action=import          → Import users from CSV upload
 *   POST ?action=import_preview  → Preview CSV before importing
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Auth: require admin
    $user = Auth::requireAuth();
    if ($user['role_name'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    switch ($action) {

    // ═══════════════════════════════════════════════════
    // EXPORT: Download all users as CSV
    // ═══════════════════════════════════════════════════
    case 'export':
        $format = $_GET['format'] ?? 'csv';
        
        $users = Database::fetchAll("
            SELECT u.id, u.full_name_th, u.first_name, u.last_name, u.username, 
                   u.email, u.phone, u.department, u.position,
                   r.name as role_name, r.display_name as role_display,
                   l.name as lab_name,
                   d3.name as dept_unit,
                   d2.name as dept_division,
                   d1.name as dept_center,
                   u.is_active, u.last_login, u.created_at
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN labs l ON u.lab_id = l.id
            LEFT JOIN departments d3 ON u.department_id = d3.id
            LEFT JOIN departments d2 ON d3.parent_id = d2.id
            LEFT JOIN departments d1 ON d2.parent_id = d1.id
            ORDER BY r.level DESC, u.first_name ASC
        ");

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.json"');
            echo json_encode(['export_date' => date('Y-m-d H:i:s'), 'total' => count($users), 'users' => $users], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // CSV Export
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.csv"');
            
            // BOM for Excel Thai support
            echo "\xEF\xBB\xBF";
            
            $out = fopen('php://output', 'w');
            
            // Header row
            fputcsv($out, [
                'id', 'ชื่อ นามสกุล', 'ชื่อ', 'นามสกุล', 'username', 'email', 'phone',
                'role', 'role_display', 'lab', 'ศูนย์', 'ฝ่าย', 'งาน',
                'department', 'position', 'is_active', 'last_login', 'created_at'
            ]);
            
            foreach ($users as $u) {
                fputcsv($out, [
                    $u['id'],
                    $u['full_name_th'] ?: ($u['first_name'] . ' ' . $u['last_name']),
                    $u['first_name'],
                    $u['last_name'],
                    $u['username'],
                    $u['email'],
                    $u['phone'],
                    $u['role_name'],
                    $u['role_display'],
                    $u['lab_name'],
                    $u['dept_center'],
                    $u['dept_division'],
                    $u['dept_unit'],
                    $u['department'],
                    $u['position'],
                    $u['is_active'] ? 'active' : 'inactive',
                    $u['last_login'],
                    $u['created_at']
                ]);
            }
            
            fclose($out);
        }
        exit;

    // ═══════════════════════════════════════════════════
    // EXPORT TEMPLATE: Empty CSV for filling
    // ═══════════════════════════════════════════════════
    case 'export_template':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_import_template.csv"');
        
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        
        // Template header
        fputcsv($out, ['ชื่อ นามสกุล', 'username', 'password', 'email', 'phone', 'role', 'ศูนย์', 'ฝ่าย', 'งาน']);
        
        // Example rows
        fputcsv($out, ['นายตัวอย่าง ทดสอบ', 'example1', '123456', 'example1@sut.ac.th', '0800000001', 'user', 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 'ฝ่ายวิเคราะห์ด้วยเครื่องมือ', 'งานวิเคราะห์ทางเคมีและชีวเคมี']);
        fputcsv($out, ['นางสาวตัวอย่าง สอง', 'example2', '123456', 'example2@sut.ac.th', '0800000002', 'lab_manager', 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 'ฝ่ายห้องปฏิบัติการเทคโนโลยีการเกษตร', 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีอาหาร']);
        
        fclose($out);
        exit;

    // ═══════════════════════════════════════════════════
    // IMPORT PREVIEW: Parse CSV and show preview
    // ═══════════════════════════════════════════════════
    case 'import_preview':
        if ($method !== 'POST') throw new Exception('Method not allowed');
        
        if (empty($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded');
        }
        
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }
        
        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            throw new Exception('Only CSV files are allowed');
        }
        
        // Read CSV
        $rows = parseUploadedCsv($file['tmp_name']);
        
        // Load lookup data
        $roles = Database::fetchAll("SELECT name, id, display_name FROM roles");
        $roleMap = [];
        foreach ($roles as $r) $roleMap[$r['name']] = $r;
        
        $departments = Database::fetchAll("SELECT name, id FROM departments WHERE level = 3");
        $deptMap = [];
        foreach ($departments as $d) $deptMap[$d['name']] = $d['id'];
        
        $existingUsers = Database::fetchAll("SELECT username, email, id FROM users");
        $usernames = array_column($existingUsers, 'id', 'username');
        $emails    = array_column($existingUsers, 'id', 'email');
        
        // Analyze each row
        $preview = [];
        $stats = ['total' => 0, 'new' => 0, 'update' => 0, 'error' => 0];
        
        foreach ($rows as $i => $row) {
            $stats['total']++;
            $item = [
                'row'      => $i + 1,
                'name'     => $row['ชื่อ นามสกุล'] ?? $row['name'] ?? '',
                'username' => $row['username'] ?? $row['user'] ?? '',
                'email'    => $row['email'] ?? '',
                'phone'    => $row['phone'] ?? '',
                'role'     => $row['role'] ?? '',
                'center'   => $row['ศูนย์'] ?? $row['center'] ?? '',
                'division' => $row['ฝ่าย'] ?? $row['division'] ?? '',
                'unit'     => $row['งาน'] ?? $row['unit'] ?? '',
                'status'   => 'new',
                'errors'   => [],
                'warnings' => []
            ];
            
            // Validate
            if (empty($item['username'])) {
                $item['errors'][] = 'Username is required';
            }
            if (empty($item['name'])) {
                $item['errors'][] = 'Name is required';
            }
            
            // Check existing
            if ($item['username'] && isset($usernames[$item['username']])) {
                $item['status'] = 'update';
                $item['existing_id'] = $usernames[$item['username']];
                $item['warnings'][] = 'User exists (ID:' . $usernames[$item['username']] . '), will update';
            }
            
            // Check email conflict
            if ($item['email'] && isset($emails[$item['email']])) {
                $existId = $emails[$item['email']];
                if ($item['status'] === 'new' || (isset($item['existing_id']) && $item['existing_id'] != $existId)) {
                    $item['warnings'][] = 'Email already used by user ID:' . $existId;
                }
            }
            
            // Auto-detect role from username if not specified
            if (empty($item['role'])) {
                if (preg_match('/^admin/i', $item['username'])) $item['role'] = 'admin';
                elseif (preg_match('/^ceo/i', $item['username'])) $item['role'] = 'ceo';
                elseif (preg_match('/^lab/i', $item['username'])) $item['role'] = 'lab_manager';
                else $item['role'] = 'user';
                $item['warnings'][] = 'Role auto-detected: ' . $item['role'];
            }
            
            // Validate role
            if ($item['role'] && !isset($roleMap[$item['role']])) {
                $item['errors'][] = 'Unknown role: ' . $item['role'];
            }
            
            // Match department
            if ($item['unit']) {
                $item['dept_id'] = findDeptId($item['unit'], $deptMap);
                if (!$item['dept_id']) {
                    $item['warnings'][] = 'Department not found: ' . $item['unit'];
                }
            }
            
            // Count
            if (!empty($item['errors'])) {
                $item['status'] = 'error';
                $stats['error']++;
            } elseif ($item['status'] === 'update') {
                $stats['update']++;
            } else {
                $stats['new']++;
            }
            
            $preview[] = $item;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'stats'   => $stats,
            'preview' => $preview,
            'roles'   => array_values($roles)
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // ═══════════════════════════════════════════════════
    // IMPORT: Actually insert/update users from CSV
    // ═══════════════════════════════════════════════════
    case 'import':
        if ($method !== 'POST') throw new Exception('Method not allowed');
        
        if (empty($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded');
        }
        
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) throw new Exception('Only CSV files are allowed');
        
        $updateExisting = ($_POST['update_existing'] ?? '0') === '1';
        $defaultPassword = $_POST['default_password'] ?? '123';
        
        // Read CSV
        $rows = parseUploadedCsv($file['tmp_name']);
        
        // Load lookup data
        $rolesList = Database::fetchAll("SELECT name, id FROM roles");
        $roleMap = [];
        foreach ($rolesList as $r) $roleMap[$r['name']] = (int)$r['id'];
        
        $departments = Database::fetchAll("SELECT name, id FROM departments WHERE level = 3");
        $deptMap = [];
        foreach ($departments as $d) $deptMap[$d['name']] = $d['id'];
        
        $results = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            foreach ($rows as $i => $row) {
                $fullName = $row['ชื่อ นามสกุล'] ?? $row['name'] ?? '';
                $username = $row['username'] ?? $row['user'] ?? '';
                $password = $row['password'] ?? $defaultPassword;
                $email    = $row['email'] ?? '';
                $phone    = $row['phone'] ?? '';
                $roleName = $row['role'] ?? '';
                $center   = $row['ศูนย์'] ?? $row['center'] ?? '';
                $division = $row['ฝ่าย'] ?? $row['division'] ?? '';
                $unit     = $row['งาน'] ?? $row['unit'] ?? '';
                
                if (empty($username) || empty($fullName)) {
                    $results['errors'][] = "Row " . ($i+1) . ": Missing username or name";
                    continue;
                }
                
                // Parse name
                [$firstName, $lastName] = parseThaiName($fullName);
                
                // Auto-detect role if empty
                if (empty($roleName)) {
                    if (preg_match('/^admin/i', $username)) $roleName = 'admin';
                    elseif (preg_match('/^ceo/i', $username)) $roleName = 'ceo';
                    elseif (preg_match('/^lab/i', $username)) $roleName = 'lab_manager';
                    else $roleName = 'user';
                }
                
                $roleId = $roleMap[$roleName] ?? ($roleMap['user'] ?? null);
                if (!$roleId) {
                    $results['errors'][] = "Row " . ($i+1) . " ($username): Invalid role '$roleName'";
                    continue;
                }
                
                // Generate unique email
                if (empty($email) || $email === 'sut@sut.ac.th') {
                    $email = $username . '@sut.ac.th';
                }
                
                // Department
                $deptId = $unit ? findDeptId($unit, $deptMap) : null;
                $deptStr = $division ?: ($center ?: '');
                $position = $unit ?: null;
                
                // Check existing
                $existing = Database::fetch("SELECT id FROM users WHERE username = :u", [':u' => $username]);
                
                if ($existing) {
                    if ($updateExisting) {
                        $updateData = [
                            'first_name'  => $firstName,
                            'last_name'   => $lastName,
                            'full_name_th'=> $fullName,
                            'email'       => $email,
                            'phone'       => $phone,
                            'department'  => $deptStr,
                            'position'    => $position,
                            'role_id'     => $roleId
                        ];
                        if ($deptId) $updateData['department_id'] = $deptId;
                        
                        Database::update('users', $updateData, 'id = :id', [':id' => $existing['id']]);
                        $results['updated']++;
                    } else {
                        $results['skipped']++;
                    }
                } else {
                    // Insert
                    try {
                        $newId = Database::insert('users', [
                            'organization_id' => 1,
                            'role_id'         => $roleId,
                            'username'        => $username,
                            'email'           => $email,
                            'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
                            'first_name'      => $firstName,
                            'last_name'       => $lastName,
                            'full_name_th'    => $fullName,
                            'phone'           => $phone,
                            'department'      => $deptStr,
                            'position'        => $position,
                            'department_id'   => $deptId,
                            'theme_preference'=> 'auto',
                            'language'        => 'th',
                            'email_verified'  => 1,
                            'is_active'       => 1
                        ]);
                        
                        // Create notification settings
                        try {
                            Database::insert('notification_settings', ['user_id' => $newId]);
                        } catch (Exception $e) { /* ignore */ }
                        
                        $results['inserted']++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate') !== false) {
                            $results['errors'][] = "Row " . ($i+1) . " ($username): Duplicate entry";
                            $results['skipped']++;
                        } else {
                            throw $e;
                        }
                    }
                }
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
        // Log the import
        try {
            Database::insert('audit_logs', [
                'user_id'    => $user['id'],
                'action'     => 'import',
                'table_name' => 'users',
                'details'    => json_encode($results, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Exception $e) { /* ignore */ }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}


// ═══════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════

function parseUploadedCsv(string $filepath): array {
    $content = file_get_contents($filepath);
    // Remove BOM
    $content = preg_replace('/^\x{FEFF}/u', '', $content);
    // Detect encoding
    $enc = mb_detect_encoding($content, ['UTF-8', 'TIS-620', 'Windows-874', 'ISO-8859-11'], true);
    if ($enc && $enc !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $enc);
    }
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmpFile, $content);
    
    $handle = fopen($tmpFile, 'r');
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        unlink($tmpFile);
        throw new Exception('Empty or invalid CSV file');
    }
    
    // Clean headers
    $header = array_map('trim', $header);
    $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
    
    $rows = [];
    while (($line = fgetcsv($handle)) !== false) {
        if (count($line) < 2) continue;
        $row = [];
        foreach ($header as $idx => $key) {
            $row[$key] = isset($line[$idx]) ? trim($line[$idx]) : '';
        }
        if (!empty($row[$header[0]]) || !empty($row[$header[1]] ?? '')) {
            $rows[] = $row;
        }
    }
    
    fclose($handle);
    unlink($tmpFile);
    
    return $rows;
}

function parseThaiName(string $fullName): array {
    $fullName = trim($fullName);
    $titles = ['นางสาว', 'นาง', 'นาย', 'ดร\.?', 'ผศ\.?ดร\.?', 'รศ\.?ดร\.?', 'ศ\.?ดร\.?', 'ผศ\.?', 'รศ\.?', 'ศ\.?'];
    $pattern = '/^(' . implode('|', $titles) . ')\s*/u';
    $name = preg_replace($pattern, '', $fullName);
    $parts = preg_split('/\s+/u', trim($name), 2);
    return [$parts[0] ?? $fullName, $parts[1] ?? ''];
}

function findDeptId(string $name, array $map): ?int {
    $name = trim($name);
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $k => $v) {
        if (mb_strpos($k, $name) !== false || mb_strpos($name, $k) !== false) return $v;
    }
    return null;
}
