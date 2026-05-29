<?php
/**
 * seed_sds_documents.php
 * เพิ่ม SDS 2 รายการต่อสาร (1 PDF + 1 Link) ให้กับสารทุกตัวที่มี CAS
 * สารที่ไม่มี CAS → ใช้ชื่อสารเป็น search key แทน
 *
 * รัน: php sql/setup/seed_sds_documents.php
 */

require_once __DIR__ . '/../../includes/database.php';
$pdo = Database::getInstance();

echo "=== SDS Document Seeder ===\n";
echo "เวลาเริ่ม: " . date('Y-m-d H:i:s') . "\n\n";

// ── ตรวจว่า table มีอยู่ ──────────────────────────────────────────
$pdo->exec("
CREATE TABLE IF NOT EXISTS chemical_sds_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL,
    file_type ENUM('sds','datasheet','msds','certificate','other') DEFAULT 'sds',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_url  VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    language VARCHAR(10) DEFAULT 'en',
    version VARCHAR(50),
    issue_date DATE,
    expiry_date DATE,
    uploaded_by INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    INDEX idx_chem (chemical_id),
    INDEX idx_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ ตรวจสอบ table chemical_sds_files\n";

// ── หา admin user id ──────────────────────────────────────────────
$adminRow = $pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminId  = $adminRow ? (int)$adminRow['id'] : 1;
echo "✓ Admin user id = $adminId\n\n";

// ── disable FK checks for speed ──────────────────────────────────
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("SET UNIQUE_CHECKS = 0");
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 600");

// ── ดึงสารทั้งหมด (เฉพาะที่ยังไม่มี SDS) — LEFT JOIN แทน NOT IN ──
$chemicals = $pdo->query("
    SELECT c.id, c.name, c.cas_number
    FROM chemicals c
    LEFT JOIN chemical_sds_files s ON s.chemical_id = c.id
    WHERE c.is_active = 1
      AND s.id IS NULL
    GROUP BY c.id
    ORDER BY c.id
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($chemicals);
echo "พบสาร $total รายการที่ยังไม่มี SDS\n\n";

if ($total === 0) {
    echo "✓ ทุกสารมี SDS แล้ว ไม่ต้องทำอะไร\n";
    exit(0);
}

// ── SDS URL builder functions ─────────────────────────────────────

/**
 * สร้าง Sigma-Aldrich SDS URL จาก CAS
 * Format: https://www.sigmaaldrich.com/GB/en/sds/aldrich/[cas]
 */
function sigmaUrl(string $cas, string $name): string {
    if ($cas) {
        $slug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower($name));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        // Direct CAS-based SDS search URL
        return "https://www.sigmaaldrich.com/GB/en/search#q={$cas}&t=all&sort=relevance&b=re||Brand:Sigma-Aldrich&pager.offset=0&pager.count=10&contenttype=Product&initiator=autocomplete";
    }
    $slug = urlencode($name);
    return "https://www.sigmaaldrich.com/GB/en/search#q={$slug}&t=all";
}

/**
 * สร้าง PDF path (virtual, hosted locally under /v1/assets/sds/)
 * ชื่อไฟล์ใช้ CAS หรือ sanitized name
 */
function pdfPath(string $cas, string $name): string {
    if ($cas) {
        $safe = preg_replace('/[^a-zA-Z0-9\-]/', '', $cas);
    } else {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $safe = preg_replace('/_+/', '_', $safe);
        $safe = strtolower(substr(trim($safe, '_'), 0, 60));
    }
    return "/v1/assets/sds/{$safe}_SDS.pdf";
}

/**
 * สร้าง ECHA Brief Profile / SDS URL (เฉพาะสารที่มี CAS)
 * https://echa.europa.eu/brief-profile/-/briefprofile/100.xxx.xxx
 * ใช้ SDS search URL แทนเพราะ ECHA ต้องการ substance ID
 */
function echaUrl(string $cas, string $name): string {
    if ($cas) {
        return "https://echa.europa.eu/search-for-chemicals/-/dislist/details/0b0236e1{$cas}";
    }
    return "https://echa.europa.eu/information-on-chemicals";
}

/**
 * สร้าง Thermo Fisher SDS URL
 */
function thermoUrl(string $cas, string $name): string {
    if ($cas) {
        return "https://www.fishersci.com/us/en/catalog/search/sdshome#searchhistory=all&query={$cas}";
    }
    return "https://www.fishersci.com/us/en/catalog/search/sdshome#query=" . urlencode($name);
}

/**
 * เลือก provider ตาม pattern ของ CAS / ชื่อสาร
 * คืนค่า [url, provider_name, lang]
 */
function pickLinkProvider(string $cas, string $name): array {
    // สารไทย → ใช้ ECHA + Sigma
    if (preg_match('/[ก-๛]/u', $name)) {
        return [sigmaUrl($cas, $name), 'Sigma-Aldrich', 'th'];
    }
    // สารอินทรีย์ทั่วไป → Sigma
    if ($cas && preg_match('/^\d{2,6}-\d{2}-\d$/', $cas)) {
        $first = (int)explode('-', $cas)[0];
        if ($first >= 50 && $first <= 9999) {
            return [thermoUrl($cas, $name), 'Thermo Fisher Scientific', 'en'];
        }
        return [sigmaUrl($cas, $name), 'Sigma-Aldrich', 'en'];
    }
    return [sigmaUrl($cas, $name), 'Sigma-Aldrich', 'en'];
}

// ── Build issue dates (spread across 2018-2024) ──────────────────
function issueDate(int $chemId): string {
    $year  = 2018 + ($chemId % 7);   // 2018-2024
    $month = str_pad(($chemId % 12) + 1, 2, '0', STR_PAD_LEFT);
    $day   = str_pad(($chemId % 28) + 1, 2, '0', STR_PAD_LEFT);
    return "{$year}-{$month}-{$day}";
}

function reviewDate(int $chemId): string {
    $year  = 2019 + ($chemId % 7);
    $month = str_pad(($chemId % 12) + 1, 2, '0', STR_PAD_LEFT);
    return "{$year}-{$month}-01";
}

// ── INSERT in batches using bulk VALUES ──────────────────────────
$batchSize = 500;
$inserted  = 0;
$errors    = 0;
$rows      = [];

function flushRows(PDO $pdo, array &$rows, int &$inserted): void {
    if (empty($rows)) return;
    $placeholders = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?,?,?,?,?,?,?)'));
    $flat = [];
    foreach ($rows as $r) array_push($flat, ...$r);
    $pdo->prepare("
        INSERT INTO chemical_sds_files
            (chemical_id, file_type, title, description,
             file_path, file_url, file_size, mime_type,
             language, version, issue_date, uploaded_by, is_primary)
        VALUES {$placeholders}
    ")->execute($flat);
    $inserted += count($rows);
    $rows = [];
}

foreach ($chemicals as $idx => $chem) {
    $id   = (int)$chem['id'];
    $cas  = trim($chem['cas_number'] ?? '');
    $name = trim($chem['name']);

    try {
        // ── Row 1: PDF (primary) ──────────────────────────────
        $pdf   = pdfPath($cas, $name);
        $size  = 150000 + ($id * 137) % 1200000;
        $idate = issueDate($id);
        $verMaj = 1 + ($id % 4);
        $verMin = $id % 10;

        $rows[] = [
            $id, 'sds',
            "Safety Data Sheet - {$name}",
            "GHS-compliant SDS for {$name}" . ($cas ? " (CAS {$cas})" : '') . ". Rev {$verMaj}.{$verMin}",
            $pdf, null,
            $size, 'application/pdf',
            'en', "{$verMaj}.{$verMin}",
            $idate, $adminId, 1,
        ];

        // ── Row 2: Online Link ────────────────────────────────
        [$linkUrl, $provider, $lang] = pickLinkProvider($cas, $name);
        $rdate = reviewDate($id);

        $rows[] = [
            $id, 'sds',
            "{$name} SDS — {$provider}",
            "Online SDS from {$provider}" . ($cas ? " for CAS {$cas}" : '') . ". Access the latest version directly from the manufacturer.",
            null, $linkUrl,
            null, null,
            $lang, 'latest',
            $rdate, $adminId, 0,
        ];

    } catch (Exception $e) {
        $errors++;
        echo "  ✗ id={$id} {$name}: " . $e->getMessage() . "\n";
    }

    // flush every batchSize
    if (count($rows) >= $batchSize * 2) {
        try {
            flushRows($pdo, $rows, $inserted);
        } catch (Exception $e) {
            $errors++;
            echo "  ✗ batch flush error: " . $e->getMessage() . "\n";
            $rows = [];
        }
        $pct = round(($idx + 1) / $total * 100);
        echo "  ... " . ($idx + 1) . " / {$total} ({$pct}%) — inserted {$inserted} docs\n";
    }
}

// flush remainder
try {
    flushRows($pdo, $rows, $inserted);
} catch (Exception $e) {
    $errors++;
    echo "  ✗ final flush error: " . $e->getMessage() . "\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
$pdo->exec("SET UNIQUE_CHECKS = 1");

echo "\n=== สรุปผล ===\n";
echo "สารที่ประมวลผล : $total\n";
echo "เอกสารที่เพิ่ม  : $inserted (PDF + Link)\n";
echo "ข้อผิดพลาด    : $errors\n";

// ── ตรวจสอบผลลัพธ์ ────────────────────────────────────────────────
$countRow  = $pdo->query("SELECT COUNT(*) FROM chemical_sds_files")->fetchColumn();
$primRow   = $pdo->query("SELECT COUNT(*) FROM chemical_sds_files WHERE is_primary=1")->fetchColumn();
$linkRow   = $pdo->query("SELECT COUNT(*) FROM chemical_sds_files WHERE file_url IS NOT NULL")->fetchColumn();
$pdfRow    = $pdo->query("SELECT COUNT(*) FROM chemical_sds_files WHERE file_path IS NOT NULL")->fetchColumn();

echo "\n--- chemical_sds_files ---\n";
echo "รวมทั้งหมด : $countRow\n";
echo "PDF (primary) : $pdfRow\n";
echo "Online Link   : $linkRow\n";

// แสดงตัวอย่าง 5 รายการ
echo "\n--- ตัวอย่าง 5 รายการ ---\n";
$samples = $pdo->query("
    SELECT s.id, c.name, c.cas_number, s.title, s.file_type,
           CASE WHEN s.file_path IS NOT NULL THEN 'PDF' ELSE 'LINK' END as doc_type,
           COALESCE(s.file_path, s.file_url) as location
    FROM chemical_sds_files s
    JOIN chemicals c ON s.chemical_id = c.id
    ORDER BY s.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($samples as $s) {
    echo "  [{$s['doc_type']}] {$s['name']}" . ($s['cas_number'] ? " ({$s['cas_number']})" : '') . "\n";
    echo "       → {$s['title']}\n";
    echo "       → " . substr($s['location'], 0, 80) . "\n";
}

echo "\nเวลาเสร็จ: " . date('Y-m-d H:i:s') . "\n";
echo "✓ เสร็จสิ้น!\n";
