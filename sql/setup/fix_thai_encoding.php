<?php
/**
 * Fix Thai encoding - re-insert all Thai data with proper UTF-8 connection
 */
require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");

echo "=== Fixing Thai encoding ===\n\n";

// 1. Fix lab0 user
echo "1. Fixing lab0 user...\n";
$db->exec("UPDATE users SET 
    first_name = 'นพดล',
    last_name = 'พริ้งเพราะ',
    full_name_th = 'นายนพดล พริ้งเพราะ',
    department = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี',
    position = 'ผู้จัดการห้องปฏิบัติการ'
    WHERE username = 'lab0'");
echo "   OK\n";

// 2. Fix organization
echo "2. Fixing organization...\n";
$db->exec("UPDATE organizations SET 
    name = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี',
    description = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี มหาวิทยาลัยเทคโนโลยีสุรนารี'
    WHERE id = 1");
echo "   OK\n";

// 3. Fix buildings
echo "3. Fixing buildings...\n";
$buildings = [
    [1, 'อาคารเครื่องมือ 1', 'Facility Building 1', 'F1'],
    [2, 'อาคารเครื่องมือ 2', 'Facility Building 2', 'F2'],
    [3, 'อาคารเครื่องมือ 3', 'Facility Building 3', 'F3'],
    [4, 'อาคารเครื่องมือ 4', 'Facility Building 4', 'F4'],
    [5, 'อาคารเครื่องมือ 5', 'Facility Building 5', 'F5'],
    [6, 'อาคารเครื่องมือ 6', 'Facility Building 6', 'F6'],
    [7, 'อาคารเครื่องมือ 7', 'Facility Building 7', 'F7'],
    [9, 'อาคารเฉลิมพระเกียรติ 72 พรรษา (อาคารเครื่องมือ9)', 'Facility Building 9', 'F9'],
    [10, 'อาคารเครื่องมือ 10', 'Facility Building 10', 'F10'],
    [11, 'อาคารคลังสารเคมี', 'Chemical Storage', 'คลังสารเคมี'],
    [12, 'อาคารเครื่องมือ 6/1', 'Facility Building 6/1', 'F6/1'],
    [14, 'อาคารสัตว์ทดลอง', '320F', 'สัตว์ทดลอง'],
    [15, 'โรงประลอง', '1/2101', 'โรงประลอง'],
];
$stmt = $db->prepare("UPDATE buildings SET name = ?, name_en = ?, shortname = ? WHERE id = ?");
foreach ($buildings as $b) {
    $stmt->execute([$b[1], $b[2], $b[3], $b[0]]);
}
echo "   " . count($buildings) . " buildings updated\n";

// 4. Fix departments
echo "4. Fixing departments...\n";
$departments = [
    [1, 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 'ศูนย์'],
    [10, 'ฝ่ายบริการสัตว์ทดลองเพื่องานทางวิทยาศาสตร์', 'ฝ่าย'],
    [11, 'ฝ่ายบริหารงานทั่วไป', 'ฝ่าย'],
    [12, 'ฝ่ายพัฒนาและปรับปรุงห้องปฏิบัติการ', 'ฝ่าย'],
    [13, 'ฝ่ายวิเคราะห์ด้วยเครื่องมือ', 'ฝ่าย'],
    [14, 'ฝ่ายสนับสนุนโครงการวิจัยฯ', 'ฝ่าย'],
    [15, 'ฝ่ายห้องปฏิบัติการเทคโนโลยีการเกษตร', 'ฝ่าย'],
    [16, 'ฝ่ายห้องปฏิบัติการวิทยาศาสตร์และเทคโนโลยีสังคม', 'ฝ่าย'],
    [17, 'ฝ่ายห้องปฏิบัติการวิทยาศาสตร์สุขภาพ', 'ฝ่าย'],
    [18, 'ฝ่ายห้องปฏิบัติการวิศวกรรม', 'ฝ่าย'],
    [101, 'งานผลิตและเลี้ยงสัตว์เพื่องานทางวิทยาศาสตร์', 'งาน'],
    [102, 'งานควบคุมคุณภาพ สุขภาพสัตว์และตรวจวิเคราะห์', 'งาน'],
    [103, 'งานจัดซื้อวัสดุ อุปกรณ์ ครุภัณฑ์', 'งาน'],
    [104, 'งานพัฒนาเครื่องมือและสิ่งประดิษฐ์', 'งาน'],
    [105, 'งานซ่อมบำรุงเครื่องมือ', 'งาน'],
    [106, 'งานวิเคราะห์ทางเคมีและชีวเคมี', 'งาน'],
    [107, 'งานวิเคราะห์ด้วยกล้องจุลทรรศน์', 'งาน'],
    [108, 'งานวิเคราะห์ทางจุลชีววิทยา', 'งาน'],
    [109, 'งานทดสอบทางกายภาพ', 'งาน'],
    [110, 'งานวิเคราะห์น้ำ', 'งาน'],
    [111, 'งานความปลอดภัยและสิ่งแวดล้อมห้องปฏิบัติการ', 'งาน'],
    [112, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีอาหาร', 'งาน'],
    [113, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีการผลิตสัตว์', 'งาน'],
    [114, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีการผลิตพืช', 'งาน'],
    [115, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีชีวภาพ', 'งาน'],
    [116, 'งานกลุ่มห้องปฏิบัติการเคมี', 'งาน'],
    [117, 'งานกลุ่มห้องปฏิบัติการชีววิทยา', 'งาน'],
    [118, 'งานกลุ่มห้องปฏิบัติการชีวเคมี', 'งาน'],
    [119, 'งานห้องปฏิบัติการทันตกรรมและเวชนิทัศน์', 'งาน'],
    [120, 'งานกลุ่มห้องปฎิบัติการอนามัยสิ่งแวดล้อม', 'งาน'],
    [121, 'งานกลุ่มห้องปฏิบัติการทางการแพทย์ 1', 'งาน'],
    [122, 'งานกลุ่มห้องปฏิบัติการทางการแพทย์ 2', 'งาน'],
    [123, 'งานสรีรวิทยาทางการแพทย์', 'งาน'],
    [124, 'งานกลุ่มห้องปฏิบัติการชีวอนามัยและความปลอดภัย', 'งาน'],
    [125, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมเคมีฯ', 'งาน'],
    [126, 'งานกลุ่มวิศวกรรมโลหการและกระบวนการผลิต', 'งาน'],
    [127, 'งานกลุ่มห้องปฏิบัติการเครื่องจักรกลพื้นฐานฯ', 'งาน'],
    [128, 'งานกลุ่มห้องปฏิบัติการโยธาและขนส่ง', 'งาน'],
    [129, 'งานกลุ่มห้องปฏิบัติการวิศกรรมพอลิเมอร์', 'งาน'],
    [130, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีธรณีและเซรามิก', 'งาน'],
    [131, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมอุตสาหการ', 'งาน'],
    [132, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมเครื่องกลฯ', 'งาน'],
    [133, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมไฟฟ้า', 'งาน'],
];
$stmt = $db->prepare("UPDATE departments SET name = ?, level_label = ? WHERE id = ?");
foreach ($departments as $d) {
    $stmt->execute([$d[1], $d[2], $d[0]]);
}
echo "   " . count($departments) . " departments updated\n";

// 5. Fix funding sources
echo "5. Fixing funding sources...\n";
$funding = [
    [1, 'งบประมาณแผ่นดิน'],
    [2, 'งบรายได้'],
    [3, 'งบวิจัย'],
    [4, 'งบบริจาค'],
];
$stmt = $db->prepare("UPDATE funding_sources SET name = ? WHERE id = ?");
foreach ($funding as $f) {
    $stmt->execute([$f[1], $f[0]]);
}
echo "   " . count($funding) . " funding sources updated\n";

// 6. Fix containers added_by_name and inventory_name
echo "6. Fixing container Thai fields...\n";
$db->exec("UPDATE containers SET 
    added_by_name = 'นายนพดล พริ้งเพราะ',
    inventory_name = 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี'
    WHERE owner_id = 15");
echo "   OK\n";

// 7. Fix container_history Thai notes
echo "7. Fixing container_history notes...\n";
$historyFixes = [
    [31, 'created', 'รับเข้าคลัง - Carbon dioxide จาก Air Liquid'],
    [32, 'created', 'รับเข้าคลัง - Ribonuclease Inhibitor'],
    [33, 'created', 'รับเข้าคลัง - AccuPower PCR PreMix'],
    [34, 'created', 'รับเข้าคลัง - GF-1 Nucleic Acid Extraction Kit'],
    [35, 'created', 'รับเข้าคลัง - Taq DNA Polymerase'],
    [36, 'created', 'รับเข้าคลัง - TAE Buffer 50X'],
    [36, 'used', 'ใช้สำหรับ Gel Electrophoresis'],
    [37, 'created', 'รับเข้าคลัง - ViSafe Red'],
    [37, 'used', 'ใช้สำหรับย้อม DNA gel'],
    [38, 'created', 'รับเข้าคลัง - 100 bp DNA Ladder'],
    [38, 'used', 'ใช้สำหรับ size reference'],
    [39, 'created', 'รับเข้าคลัง - HinfI restriction enzyme'],
    [39, 'used', 'ใช้สำหรับ RFLP analysis'],
];
$stmt = $db->prepare("UPDATE container_history SET notes = ? WHERE container_id = ? AND action_type = ?");
foreach ($historyFixes as $h) {
    $stmt->execute([$h[2], $h[0], $h[1]]);
}
echo "   " . count($historyFixes) . " history records updated\n";

// 8. Fix alerts
echo "8. Fixing alerts...\n";
$db->exec("UPDATE alerts SET 
    title = 'สต็อกต่ำ', 
    message = 'ViSafe Red (BTL-0007) เหลือเพียง 60%'
    WHERE user_id = 15 AND container_id = 37 AND alert_type = 'low_stock'");
$db->exec("UPDATE alerts SET 
    title = 'สต็อกต่ำ', 
    message = '100 bp DNA Ladder (BTL-0008) เหลือเพียง 70%'
    WHERE user_id = 15 AND container_id = 38 AND alert_type = 'low_stock'");
$db->exec("UPDATE alerts SET 
    title = 'คำขอยืมใหม่', 
    message = 'นักวิจัย สอง ขอยืม TAE Buffer 50X 200 mL'
    WHERE user_id = 15 AND alert_type = 'borrow_request'");
echo "   OK\n";

// Verify
echo "\n=== Verification ===\n";
$user = $db->query("SELECT username, full_name_th, first_name, last_name, department FROM users WHERE username='lab0'")->fetch(PDO::FETCH_ASSOC);
echo "lab0: {$user['full_name_th']} ({$user['first_name']} {$user['last_name']})\n";
echo "dept: {$user['department']}\n";

$dept = $db->query("SELECT name FROM departments WHERE id=116")->fetch(PDO::FETCH_ASSOC);
echo "dept 116: {$dept['name']}\n";

$bldg = $db->query("SELECT name, shortname FROM buildings WHERE id=7")->fetch(PDO::FETCH_ASSOC);
echo "building 7: {$bldg['name']} ({$bldg['shortname']})\n";

$fs = $db->query("SELECT name FROM funding_sources WHERE id=1")->fetch(PDO::FETCH_ASSOC);
echo "funding 1: {$fs['name']}\n";

echo "\n✅ All Thai encoding fixed!\n";
