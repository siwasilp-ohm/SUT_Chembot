<?php
/**
 * SUT chemBot - Automated System Test
 * Tests all critical components: DB, Auth, API, Pages
 */

// Suppress HTML output for clean test results  
ini_set('display_errors', '1');
error_reporting(E_ALL);

$testResults = [];
$passed = 0;
$failed = 0;
$startTime = microtime(true);

function test($name, $callback) {
    global $testResults, $passed, $failed;
    try {
        $result = $callback();
        if ($result === true) {
            $testResults[] = ['status' => 'PASS', 'name' => $name, 'message' => ''];
            $passed++;
        } else {
            $testResults[] = ['status' => 'FAIL', 'name' => $name, 'message' => $result ?: 'Returned false'];
            $failed++;
        }
    } catch (Throwable $e) {
        $testResults[] = ['status' => 'FAIL', 'name' => $name, 'message' => $e->getMessage()];
        $failed++;
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "  SUT chemBot - Automated System Test\n";
echo str_repeat('=', 60) . "\n\n";

// ============================================
// 1. CONFIG TESTS
// ============================================
echo "[Config Tests]\n";

test('Config file loads', function() {
    require_once __DIR__ . '/includes/config.php';
    return defined('DB_NAME') && defined('APP_URL');
});

test('DB_NAME is chem_inventory_db', function() {
    return DB_NAME === 'chem_inventory_db';
});

test('APP_URL is http://localhost/v1', function() {
    return APP_URL === 'http://localhost/v1';
});

test('.env file exists', function() {
    return file_exists(__DIR__ . '/.env');
});

test('.htaccess file exists', function() {
    return file_exists(__DIR__ . '/.htaccess');
});

test('Composer vendor directory exists', function() {
    return is_dir(__DIR__ . '/vendor') && file_exists(__DIR__ . '/vendor/autoload.php');
});

// ============================================
// 2. DATABASE TESTS
// ============================================
echo "\n[Database Tests]\n";

test('Database connection works', function() {
    require_once __DIR__ . '/includes/database.php';
    $db = Database::getInstance();
    return $db instanceof PDO;
});

test('All 27 tables exist', function() {
    $tables = Database::fetchAll("SHOW TABLES");
    $count = count($tables);
    if ($count < 27) return "Only {$count} tables found, expected 27";
    return true;
});

test('Users table has 14 records', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM users");
    return $result['cnt'] == 14 ? true : "Found {$result['cnt']} users, expected 14";
});

test('Chemicals table has 17 records', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM chemicals");
    return $result['cnt'] == 17 ? true : "Found {$result['cnt']} chemicals, expected 17";
});

test('Containers table has 30 records', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM containers");
    return $result['cnt'] == 30 ? true : "Found {$result['cnt']} containers, expected 30";
});

test('Containers table has is_active column', function() {
    $result = Database::fetchAll("SHOW COLUMNS FROM containers LIKE 'is_active'");
    return count($result) === 1 ? true : "is_active column missing from containers";
});

test('Roles table has 5 roles', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM roles");
    return $result['cnt'] == 5 ? true : "Found {$result['cnt']} roles, expected 5";
});

test('Labs table has 5 labs', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM labs");
    return $result['cnt'] == 5 ? true : "Found {$result['cnt']} labs, expected 5";
});

test('Borrow requests exist', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM borrow_requests");
    return $result['cnt'] > 0 ? true : "No borrow requests found";
});

test('Alerts exist', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM alerts");
    return $result['cnt'] > 0 ? true : "No alerts found";
});

test('LIMIT/OFFSET query works (PDO fix)', function() {
    $result = Database::fetchAll(
        "SELECT id, username FROM users ORDER BY id LIMIT :limit OFFSET :offset",
        [':limit' => 5, ':offset' => 0]
    );
    return count($result) === 5 ? true : "Got " . count($result) . " results, expected 5";
});

// ============================================
// 3. DATABASE SECURITY TESTS
// ============================================
echo "\n[Security Tests]\n";

test('Database::insert validates table name', function() {
    try {
        Database::insert('invalid_table; DROP TABLE users;--', ['col' => 'val']);
        return 'Should have thrown exception';
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Invalid table name') !== false;
    }
});

test('Database::update validates table name', function() {
    try {
        Database::update('users; DROP TABLE users;--', ['username' => 'hack'], 'id = 1');
        return 'Should have thrown exception';
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Invalid table name') !== false;
    }
});

test('Database::insert validates column name', function() {
    try {
        Database::insert('users', ['username; DROP TABLE users;--' => 'val']);
        return 'Should have thrown exception';
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'Invalid column name') !== false;
    }
});

// ============================================
// 4. AUTH TESTS
// ============================================
echo "\n[Auth Tests]\n";

test('Auth class loads', function() {
    require_once __DIR__ . '/includes/auth.php';
    return class_exists('Auth');
});

test('Admin user exists and password verified', function() {
    $user = Database::fetch("SELECT * FROM users WHERE username = 'admin1'");
    if (!$user) return 'admin1 user not found';
    return password_verify('password', $user['password_hash']);
});

test('CEO user exists and password verified', function() {
    $user = Database::fetch("SELECT * FROM users WHERE username = 'ceo1'");
    if (!$user) return 'ceo1 user not found';
    return password_verify('password', $user['password_hash']);
});

test('Lab Manager user exists and password verified', function() {
    $user = Database::fetch("SELECT * FROM users WHERE username = 'lab1'");
    if (!$user) return 'lab1 user not found';
    return password_verify('password', $user['password_hash']);
});

test('Regular user exists and password verified', function() {
    $user = Database::fetch("SELECT * FROM users WHERE username = 'user1'");
    if (!$user) return 'user1 user not found';
    return password_verify('password', $user['password_hash']);
});

test('All user roles assigned correctly', function() {
    $counts = Database::fetchAll(
        "SELECT r.name, COUNT(u.id) as cnt 
         FROM users u JOIN roles r ON u.role_id = r.id 
         GROUP BY r.name ORDER BY r.name"
    );
    $roles = [];
    foreach ($counts as $row) $roles[$row['name']] = $row['cnt'];
    
    if (($roles['admin'] ?? 0) != 2) return "Expected 2 admins, got " . ($roles['admin'] ?? 0);
    if (($roles['ceo'] ?? 0) != 2) return "Expected 2 CEOs, got " . ($roles['ceo'] ?? 0);
    if (($roles['lab_manager'] ?? 0) != 4) return "Expected 4 lab managers, got " . ($roles['lab_manager'] ?? 0);
    if (($roles['user'] ?? 0) != 6) return "Expected 6 regular users, got " . ($roles['user'] ?? 0);
    return true;
});

// ============================================
// 5. FILE STRUCTURE TESTS
// ============================================
echo "\n[File Structure Tests]\n";

$requiredFiles = [
    'index.php', 'composer.json', 'composer.lock',
    'includes/config.php', 'includes/database.php', 'includes/auth.php',
    'includes/i18n.php', 'includes/qr_generator.php',
    'pages/login.php', 'pages/register.php', 'pages/dashboard.php',
    'pages/chemicals.php', 'pages/containers.php', 'pages/locations.php',
    'pages/borrow.php', 'pages/users.php', 'pages/reports.php',
    'pages/qr-scanner.php', 'pages/ai-assistant.php',
    'api/auth.php', 'api/chemicals.php', 'api/containers.php',
    'api/dashboard.php', 'api/borrow.php', 'api/alerts.php',
    'api/locations.php', 'api/ai_assistant.php',
    'ar/view_ar.php',
    'lang/en.php', 'lang/th.php',
    'sql/setup_database.sql'
];

foreach ($requiredFiles as $file) {
    test("File exists: {$file}", function() use ($file) {
        return file_exists(__DIR__ . '/' . $file);
    });
}

test('Upload directories exist', function() {
    $dirs = ['assets/uploads/qr_codes', 'assets/uploads/labels', 'assets/logs'];
    foreach ($dirs as $dir) {
        if (!is_dir(__DIR__ . '/' . $dir)) return "Missing directory: {$dir}";
    }
    return true;
});

// ============================================
// 6. QR GENERATOR TESTS  
// ============================================
echo "\n[QR Generator Tests]\n";

test('QRGenerator class loads', function() {
    require_once __DIR__ . '/includes/qr_generator.php';
    return class_exists('QRGenerator');
});

test('Endroid QR code library available', function() {
    return class_exists('Endroid\\QrCode\\QrCode');
});

test('QR code can be generated', function() {
    $path = QRGenerator::generate('TEST-QR-CODE-001', 1);
    return !empty($path) && file_exists(__DIR__ . '/assets/uploads/' . $path);
});

// ============================================
// 7. COMPOSER DEPENDENCY TESTS
// ============================================
echo "\n[Dependency Tests]\n";

test('firebase/php-jwt loaded', function() {
    return class_exists('Firebase\\JWT\\JWT');
});

test('PHPMailer loaded', function() {
    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
});

test('vlucas/phpdotenv loaded', function() {
    return class_exists('Dotenv\\Dotenv');
});

test('endroid/qr-code loaded', function() {
    return class_exists('Endroid\\QrCode\\QrCode');
});

// ============================================
// 8. DATA INTEGRITY TESTS
// ============================================
echo "\n[Data Integrity Tests]\n";

test('All containers reference valid chemicals', function() {
    $orphans = Database::fetch(
        "SELECT COUNT(*) as cnt FROM containers c 
         LEFT JOIN chemicals ch ON c.chemical_id = ch.id 
         WHERE ch.id IS NULL"
    );
    return $orphans['cnt'] == 0 ? true : "{$orphans['cnt']} containers with invalid chemical_id";
});

test('All containers reference valid owners', function() {
    $orphans = Database::fetch(
        "SELECT COUNT(*) as cnt FROM containers c 
         LEFT JOIN users u ON c.owner_id = u.id 
         WHERE u.id IS NULL"
    );
    return $orphans['cnt'] == 0 ? true : "{$orphans['cnt']} containers with invalid owner_id";
});

test('All containers reference valid labs', function() {
    $orphans = Database::fetch(
        "SELECT COUNT(*) as cnt FROM containers c 
         LEFT JOIN labs l ON c.lab_id = l.id 
         WHERE l.id IS NULL"
    );
    return $orphans['cnt'] == 0 ? true : "{$orphans['cnt']} containers with invalid lab_id";
});

test('All users have valid roles', function() {
    $orphans = Database::fetch(
        "SELECT COUNT(*) as cnt FROM users u 
         LEFT JOIN roles r ON u.role_id = r.id 
         WHERE r.id IS NULL"
    );
    return $orphans['cnt'] == 0 ? true : "{$orphans['cnt']} users with invalid role_id";
});

test('Container remaining_percentage calculated correctly', function() {
    $bad = Database::fetch(
        "SELECT COUNT(*) as cnt FROM containers 
         WHERE initial_quantity > 0 
         AND ABS(remaining_percentage - (current_quantity / initial_quantity * 100)) > 1"
    );
    return $bad['cnt'] == 0 ? true : "{$bad['cnt']} containers with wrong remaining_percentage";
});

test('Chemical categories hierarchy intact', function() {
    $result = Database::fetch("SELECT COUNT(*) as cnt FROM chemical_categories");
    return $result['cnt'] == 7 ? true : "Expected 7 categories, got {$result['cnt']}";
});

test('Location hierarchy complete', function() {
    $buildings = Database::fetch("SELECT COUNT(*) as cnt FROM buildings");
    $rooms = Database::fetch("SELECT COUNT(*) as cnt FROM rooms");
    $cabinets = Database::fetch("SELECT COUNT(*) as cnt FROM cabinets");
    $shelves = Database::fetch("SELECT COUNT(*) as cnt FROM shelves");
    $slots = Database::fetch("SELECT COUNT(*) as cnt FROM slots");
    
    if ($buildings['cnt'] < 3) return "Only {$buildings['cnt']} buildings";
    if ($rooms['cnt'] < 6) return "Only {$rooms['cnt']} rooms";
    if ($cabinets['cnt'] < 12) return "Only {$cabinets['cnt']} cabinets";
    if ($shelves['cnt'] < 26) return "Only {$shelves['cnt']} shelves";
    if ($slots['cnt'] < 36) return "Only {$slots['cnt']} slots";
    return true;
});

// ============================================
// 9. URL PATH TESTS (verify no old chem_inventory paths)
// ============================================
echo "\n[URL Path Tests]\n";

$filesToCheck = [
    'pages/dashboard.php', 'pages/chemicals.php', 'pages/qr-scanner.php',
    'pages/ai-assistant.php', 'pages/login.php', 'pages/register.php',
    'includes/config.php', 'ar/view_ar.php'
];

foreach ($filesToCheck as $file) {
    test("No /chem_inventory/ in {$file}", function() use ($file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        if (strpos($content, '/chem_inventory/') !== false) {
            return "Still contains /chem_inventory/ path";
        }
        return true;
    });
}

// ============================================
// RESULTS SUMMARY
// ============================================
$totalTime = round((microtime(true) - $startTime) * 1000);

echo "\n" . str_repeat('=', 60) . "\n";
echo "  TEST RESULTS\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($testResults as $r) {
    $icon = $r['status'] === 'PASS' ? '✓' : '✗';
    $color = $r['status'] === 'PASS' ? '' : '';
    echo "  {$icon} [{$r['status']}] {$r['name']}";
    if ($r['message']) echo " - {$r['message']}";
    echo "\n";
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "  Total: " . ($passed + $failed) . " tests | ";
echo "Passed: {$passed} | Failed: {$failed} | ";
echo "Time: {$totalTime}ms\n";
echo str_repeat('-', 60) . "\n";

if ($failed === 0) {
    echo "\n  ★ ALL TESTS PASSED! System is ready. ★\n\n";
} else {
    echo "\n  ⚠ {$failed} test(s) failed. Check results above.\n\n";
}

// Cleanup test file
if (file_exists(__DIR__ . '/composer-setup.php')) {
    unlink(__DIR__ . '/composer-setup.php');
}

exit($failed > 0 ? 1 : 0);
