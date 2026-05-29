<?php
require_once __DIR__ . '/../includes/database.php';
$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");

// Create table
$db->exec("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    label VARCHAR(255),
    description TEXT,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Table created\n";

// Insert settings
$settings = [
    ['account_lockout_enabled', '1', 'boolean', 'security', 'เปิดใช้งานการล็อคบัญชี', 'ล็อคบัญชีเมื่อกรอกรหัสผ่านผิดเกินจำนวนครั้งที่กำหนด'],
    ['account_lockout_max_attempts', '5', 'integer', 'security', 'จำนวนครั้งสูงสุดที่กรอกผิดได้', 'จำนวนครั้งที่กรอกรหัสผ่านผิดก่อนล็อคบัญชี (ค่าเริ่มต้น 5)'],
    ['account_lockout_duration', '30', 'integer', 'security', 'ระยะเวลาล็อค (นาที)', 'ระยะเวลาที่ล็อคบัญชีเมื่อกรอกผิดเกินกำหนด'],
    ['session_timeout', '1440', 'integer', 'security', 'Session Timeout (นาที)', 'ระยะเวลา session หมดอายุ (นาที)'],
    ['allow_registration', '1', 'boolean', 'security', 'เปิดให้ลงทะเบียนเอง', 'อนุญาตให้ผู้ใช้ลงทะเบียนบัญชีเอง'],
    ['app_name_th', 'ระบบคลังสารเคมี AI', 'string', 'general', 'ชื่อระบบ (ไทย)', 'ชื่อระบบแสดงภาษาไทย'],
    ['app_name_en', 'SUT chemBot', 'string', 'general', 'ชื่อระบบ (EN)', 'System name in English'],
    ['org_name', 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี มทส.', 'string', 'general', 'ชื่อหน่วยงาน', 'ชื่อหน่วยงาน/องค์กรที่แสดงในระบบ'],
];

$stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, category, label, description) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), label=VALUES(label), description=VALUES(description)");
foreach ($settings as $s) {
    $stmt->execute($s);
}
echo count($settings) . " settings inserted\n";

// Verify
$rows = $db->query("SELECT setting_key, setting_value, label FROM system_settings ORDER BY category, setting_key")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  {$r['setting_key']} = {$r['setting_value']} ({$r['label']})\n";
}
echo "✅ Done\n";
