<?php
require_once __DIR__ . '/../core/config.php';
require_login();
if (!is_admin()) { header('Location: ' . BASE_URL . '/'); exit; }

// ─── Handle Actions ───
$msg = '';
$msgType = '';

// Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'save_settings') {
        $pdo = db();
        $fields = ['site_name','base_url','max_upload_size','allowed_extensions','maintenance_mode',
                   'qr_base_url','qr_pattern_ar','qr_pattern_3d','qr_pattern_pano','qr_pattern_embed',
                   'qr_size','qr_color_dark','qr_color_light','qr_error_level','qr_logo_enabled',
                   'iframe_default_params','iframe_default_attrs','iframe_width','iframe_height',
                   'iframe_kiri_bg_theme','iframe_kiri_auto_spin'];
        foreach ($fields as $k) {
            if (isset($_POST[$k])) {
                $v = trim($_POST[$k]);
                $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v) ON DUPLICATE KEY UPDATE `value`=:v2");
                $stmt->execute([':k' => $k, ':v' => $v, ':v2' => $v]);
            }
        }
        $msg = 'บันทึกการตั้งค่าเรียบร้อย';
        $msgType = 'success';
    }

    if ($act === 'update_user') {
        $uid = (int)$_POST['user_id'];
        $role = $_POST['role'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        $display = trim($_POST['display_name']);
        $pdo = db();
        $pdo->prepare("UPDATE users SET role=?, display_name=?, is_active=? WHERE id=?")->execute([$role, $display, $active, $uid]);
        $msg = 'อัปเดตผู้ใช้เรียบร้อย';
        $msgType = 'success';
    }

    if ($act === 'reset_password') {
        $uid = (int)$_POST['user_id'];
        $newPass = trim($_POST['new_password']);
        if (strlen($newPass) >= 3) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
            $msg = 'รีเซ็ตรหัสผ่านเรียบร้อย';
            $msgType = 'success';
        } else {
            $msg = 'รหัสผ่านต้องมีอย่างน้อย 3 ตัวอักษร';
            $msgType = 'error';
        }
    }

    if ($act === 'backup_db') {
        // Generate SQL dump
        $pdo = db();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dump = "-- VRX Studio Database Backup\n";
        $dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Server: " . DB_HOST . "\n";
        $dump .= "-- Database: " . DB_NAME . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $dump .= $create[1] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`,`', $cols) . '`';
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, array_values($row));
                    $dump .= "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $vals) . ");\n";
                }
                $dump .= "\n";
            }
        }
        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="vrx_backup_' . date('Ymd_His') . '.sql"');
        echo $dump;
        exit;
    }

    if ($act === 'cleanup_deleted') {
        $pdo = db();
        $count = $pdo->exec("DELETE FROM files WHERE status='deleted' AND deleted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $msg = "ลบไฟล์ถาวร {$count} รายการ";
        $msgType = 'success';
    }
}

// ─── Load Data ───
$pdo = db();

// Settings
$settingsRaw = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = [
    'site_name'          => $settingsRaw['site_name'] ?? 'VRX Studio',
    'base_url'           => $settingsRaw['base_url'] ?? BASE_URL,
    'max_upload_size'    => $settingsRaw['max_upload_size'] ?? MAX_UPLOAD_SIZE,
    'allowed_extensions' => $settingsRaw['allowed_extensions'] ?? implode(',', ALLOWED_EXT),
    'maintenance_mode'   => $settingsRaw['maintenance_mode'] ?? '0',
    // QR
    'qr_base_url'        => $settingsRaw['qr_base_url'] ?? '',
    'qr_pattern_ar'      => $settingsRaw['qr_pattern_ar'] ?? '{origin}{base}/pages/ar.php?src={file_url}',
    'qr_pattern_3d'      => $settingsRaw['qr_pattern_3d'] ?? '{origin}{base}/pages/viewer.php?src={file_url}',
    'qr_pattern_pano'    => $settingsRaw['qr_pattern_pano'] ?? '{origin}{base}/pages/panorama.php?src={file_url}',
    'qr_pattern_embed'   => $settingsRaw['qr_pattern_embed'] ?? '{origin}{base}/pages/viewer.php?mode=embed&embed={embed_src}&id={id}',
    'qr_size'            => $settingsRaw['qr_size'] ?? '250',
    'qr_color_dark'      => $settingsRaw['qr_color_dark'] ?? '#000000',
    'qr_color_light'     => $settingsRaw['qr_color_light'] ?? '#ffffff',
    'qr_error_level'     => $settingsRaw['qr_error_level'] ?? 'M',
    'qr_logo_enabled'    => $settingsRaw['qr_logo_enabled'] ?? '0',
    // Iframe
    'iframe_default_params' => $settingsRaw['iframe_default_params'] ?? 'bg_theme=transparent&auto_spin_model=1',
    'iframe_default_attrs'  => $settingsRaw['iframe_default_attrs'] ?? 'frameborder="0" allowfullscreen mozallowfullscreen webkitallowfullscreen allow="autoplay; fullscreen;" execution-while-out-of-viewport execution-while-not-rendered',
    'iframe_width'          => $settingsRaw['iframe_width'] ?? '640',
    'iframe_height'         => $settingsRaw['iframe_height'] ?? '480',
    'iframe_kiri_bg_theme'  => $settingsRaw['iframe_kiri_bg_theme'] ?? 'transparent',
    'iframe_kiri_auto_spin' => $settingsRaw['iframe_kiri_auto_spin'] ?? '1',
];

// Users
$users = $pdo->query("SELECT id, uuid, username, email, display_name, role, is_active, last_login_at, created_at FROM users ORDER BY id")->fetchAll();

// System stats
$dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.TABLES WHERE table_schema='" . DB_NAME . "'")->fetchColumn();
$tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema='" . DB_NAME . "'")->fetchColumn();
$totalFiles = $pdo->query("SELECT COUNT(*) FROM files WHERE status='active'")->fetchColumn();
$totalDeleted = $pdo->query("SELECT COUNT(*) FROM files WHERE status='deleted'")->fetchColumn();
$totalStorage = $pdo->query("SELECT COALESCE(SUM(file_size),0) FROM files WHERE status='active'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Upload dir size
function dirSize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            $size += $f->getSize();
        }
    }
    return $size;
}
$uploadDiskSize = dirSize(UPLOAD_DIR);

// Disk free
$diskFree = @disk_free_space(BASE_PATH);
$diskTotal = @disk_total_space(BASE_PATH);

$phpVer = phpversion();
$mysqlVer = $pdo->query("SELECT VERSION()")->fetchColumn();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$phpMaxUpload = ini_get('upload_max_filesize');
$phpPostMax = ini_get('post_max_size');
$phpMemLimit = ini_get('memory_limit');
$phpMaxExec = ini_get('max_execution_time');

function fmtBytes($b) {
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b/1024, 1) . ' KB';
    if ($b < 1073741824) return round($b/1048576, 1) . ' MB';
    return round($b/1073741824, 1) . ' GB';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าระบบ — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
/* ─── Admin Layout ─── */
.admin-grid {
  display:grid; grid-template-columns:220px 1fr; gap:24px;
  min-height:calc(100vh - var(--header-h) - var(--bottom-nav-h) - 48px);
}
@media (max-width:768px) {
  .admin-grid { grid-template-columns:1fr; }
}

/* Sidebar */
.admin-sidebar {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:16px 0; height:fit-content;
  position:sticky; top:calc(var(--header-h) + 24px);
}
.admin-sidebar a {
  display:flex; align-items:center; gap:10px;
  padding:10px 20px; font-size:.85rem; color:var(--text-secondary);
  transition:all .15s; border-left:3px solid transparent;
}
.admin-sidebar a:hover {
  background:var(--bg-hover); color:var(--text);
}
.admin-sidebar a.active {
  color:var(--primary); background:rgba(108,92,231,.08);
  border-left-color:var(--primary); font-weight:600;
}
.admin-sidebar a svg { width:16px; height:16px; flex-shrink:0; }
.admin-sidebar .sidebar-label {
  font-size:.65rem; font-weight:700; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.8px;
  padding:16px 20px 6px; display:block;
}

/* Sections */
.admin-section { display:none; }
.admin-section.active { display:block; }
.admin-section-title {
  font-size:1.15rem; font-weight:700; margin-bottom:20px;
  display:flex; align-items:center; gap:10px;
}
.admin-section-title svg { width:22px; height:22px; color:var(--primary); }

/* Dashboard Cards */
.dash-grid {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;
  margin-bottom:24px;
}
.dash-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:18px;
  display:flex; flex-direction:column; gap:4px;
}
.dash-card .dash-value {
  font-size:1.6rem; font-weight:700; color:var(--text);
}
.dash-card .dash-label {
  font-size:.75rem; color:var(--text-muted); text-transform:uppercase;
  letter-spacing:.5px;
}
.dash-card .dash-icon {
  width:38px; height:38px; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  margin-bottom:8px;
}
.dash-card .dash-icon svg { width:18px; height:18px; }

/* Server Info */
.info-table {
  width:100%; border-collapse:collapse;
}
.info-table td {
  padding:10px 14px; border-bottom:1px solid var(--border);
  font-size:.85rem;
}
.info-table td:first-child {
  color:var(--text-muted); width:40%; font-weight:500;
}
.info-table td:last-child {
  color:var(--text); font-family:'Cascadia Code', 'Fira Code', monospace;
}

/* Settings Form */
.settings-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:24px; margin-bottom:20px;
}
.settings-card h3 {
  font-size:.95rem; font-weight:600; margin-bottom:16px;
  display:flex; align-items:center; gap:8px;
  padding-bottom:12px; border-bottom:1px solid var(--border);
}
.settings-row {
  display:grid; grid-template-columns:1fr 1fr; gap:16px;
}
@media (max-width:600px) { .settings-row { grid-template-columns:1fr; } }

.form-help {
  font-size:.72rem; color:var(--text-muted); margin-top:4px;
}

/* Users Table */
.users-table {
  width:100%; border-collapse:collapse; font-size:.85rem;
}
.users-table th {
  text-align:left; padding:10px 12px; font-size:.72rem;
  color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px;
  border-bottom:2px solid var(--border); font-weight:600;
}
.users-table td {
  padding:12px; border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.users-table tr:hover td { background:rgba(108,92,231,.03); }
.user-role-badge {
  display:inline-block; padding:2px 10px; border-radius:20px;
  font-size:.7rem; font-weight:600; text-transform:uppercase;
}
.user-role-badge.admin { background:rgba(108,92,231,.15); color:var(--primary); }
.user-role-badge.user { background:rgba(0,206,201,.15); color:var(--accent); }
.user-role-badge.viewer { background:rgba(160,160,192,.15); color:var(--text-secondary); }
.user-active { color:var(--success); }
.user-inactive { color:var(--danger); }

/* Progress Bar */
.progress-bar {
  height:8px; background:var(--bg-surface); border-radius:4px; overflow:hidden;
}
.progress-bar-fill {
  height:100%; border-radius:4px; transition:width .5s ease;
}

/* Alert */
.admin-alert {
  padding:12px 20px; border-radius:var(--radius); margin-bottom:20px;
  font-size:.85rem; display:flex; align-items:center; gap:8px;
  border:1px solid;
}
.admin-alert.success { background:rgba(0,184,148,.08); border-color:rgba(0,184,148,.3); color:var(--success); }
.admin-alert.error { background:rgba(225,112,85,.08); border-color:rgba(225,112,85,.3); color:var(--danger); }

/* Action Cards */
.action-grid {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;
}
.action-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:20px;
  display:flex; flex-direction:column; gap:8px;
}
.action-card h4 { font-size:.9rem; font-weight:600; display:flex; align-items:center; gap:8px; }
.action-card p { font-size:.78rem; color:var(--text-secondary); line-height:1.5; flex:1; }
.action-card .btn { align-self:flex-start; margin-top:8px; }

/* User edit modal */
.user-edit-modal {
  position:fixed; inset:0; z-index:300;
  background:rgba(0,0,0,.7); backdrop-filter:blur(6px);
  display:flex; align-items:center; justify-content:center; padding:20px;
}
.user-edit-form {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:28px;
  width:100%; max-width:420px;
}
.user-edit-form h3 { font-size:1rem; margin-bottom:20px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container">

<?php if ($msg): ?>
<div class="admin-alert <?= $msgType ?>">
  <i data-feather="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:16px;height:16px;"></i>
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="admin-grid">

  <!-- Sidebar -->
  <nav class="admin-sidebar">
    <span class="sidebar-label">ระบบ</span>
    <a href="#dashboard" class="active" onclick="switchTab('dashboard',this)">
      <i data-feather="bar-chart-2"></i> แดชบอร์ด
    </a>
    <a href="#server" onclick="switchTab('server',this)">
      <i data-feather="server"></i> เซิร์ฟเวอร์
    </a>
    <span class="sidebar-label">การตั้งค่า</span>
    <a href="#site" onclick="switchTab('site',this)">
      <i data-feather="globe"></i> ตั้งค่าเว็บ
    </a>
    <a href="#upload" onclick="switchTab('upload',this)">
      <i data-feather="upload-cloud"></i> อัปโหลด
    </a>
    <a href="#qr" onclick="switchTab('qr',this)">
      <i data-feather="maximize"></i> QR Code
    </a>
    <a href="#iframe" onclick="switchTab('iframe',this)">
      <i data-feather="code"></i> Iframe Config
    </a>
    <span class="sidebar-label">จัดการ</span>
    <a href="#users" onclick="switchTab('users',this)">
      <i data-feather="users"></i> ผู้ใช้งาน
    </a>
    <a href="#tools" onclick="switchTab('tools',this)">
      <i data-feather="tool"></i> เครื่องมือ
    </a>
  </nav>

  <!-- Content -->
  <div class="admin-content">

    <!-- ═══ Dashboard ═══ -->
    <div class="admin-section active" id="sec-dashboard">
      <div class="admin-section-title">
        <i data-feather="bar-chart-2"></i> แดชบอร์ดระบบ
      </div>

      <div class="dash-grid">
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(108,92,231,.12);color:var(--primary);">
            <i data-feather="file"></i>
          </div>
          <div class="dash-value"><?= number_format($totalFiles) ?></div>
          <div class="dash-label">ไฟล์ทั้งหมด</div>
        </div>
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(0,206,201,.12);color:var(--accent);">
            <i data-feather="users"></i>
          </div>
          <div class="dash-value"><?= $totalUsers ?></div>
          <div class="dash-label">ผู้ใช้งาน</div>
        </div>
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(0,184,148,.12);color:var(--success);">
            <i data-feather="hard-drive"></i>
          </div>
          <div class="dash-value"><?= fmtBytes($totalStorage) ?></div>
          <div class="dash-label">พื้นที่ใช้ (DB)</div>
        </div>
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(253,203,110,.12);color:var(--warning);">
            <i data-feather="folder"></i>
          </div>
          <div class="dash-value"><?= fmtBytes($uploadDiskSize) ?></div>
          <div class="dash-label">ไฟล์ในดิสก์</div>
        </div>
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(225,112,85,.12);color:var(--danger);">
            <i data-feather="trash-2"></i>
          </div>
          <div class="dash-value"><?= $totalDeleted ?></div>
          <div class="dash-label">ไฟล์ที่ลบ</div>
        </div>
        <div class="dash-card">
          <div class="dash-icon" style="background:rgba(116,185,255,.12);color:#74b9ff;">
            <i data-feather="database"></i>
          </div>
          <div class="dash-value"><?= $dbSize ?> MB</div>
          <div class="dash-label">ขนาด DB (<?= $tableCount ?> ตาราง)</div>
        </div>
      </div>

      <!-- Disk Usage -->
      <div class="settings-card">
        <h3><i data-feather="pie-chart" style="width:16px;height:16px;color:var(--primary);"></i> การใช้งานดิสก์</h3>
        <?php $diskPct = $diskTotal > 0 ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) : 0; ?>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px;">
          <span style="color:var(--text-secondary);">ใช้ไป <?= fmtBytes($diskTotal - $diskFree) ?> / <?= fmtBytes($diskTotal) ?></span>
          <span style="color:var(--text-muted);">ว่าง <?= fmtBytes($diskFree) ?></span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= $diskPct ?>%;background:<?= $diskPct > 90 ? 'var(--danger)' : ($diskPct > 70 ? 'var(--warning)' : 'var(--primary)') ?>;"></div>
        </div>
        <div style="text-align:right;font-size:.72rem;color:var(--text-muted);margin-top:4px;"><?= $diskPct ?>%</div>
      </div>
    </div>

    <!-- ═══ Server Info ═══ -->
    <div class="admin-section" id="sec-server">
      <div class="admin-section-title">
        <i data-feather="server"></i> ข้อมูลเซิร์ฟเวอร์
      </div>

      <div class="settings-card">
        <h3><i data-feather="cpu" style="width:16px;height:16px;color:var(--accent);"></i> สภาพแวดล้อม</h3>
        <table class="info-table">
          <tr><td>Web Server</td><td><?= htmlspecialchars($serverSoftware) ?></td></tr>
          <tr><td>PHP Version</td><td><?= $phpVer ?></td></tr>
          <tr><td>MySQL Version</td><td><?= $mysqlVer ?></td></tr>
          <tr><td>Document Root</td><td><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '') ?></td></tr>
          <tr><td>Base Path</td><td><?= BASE_PATH ?></td></tr>
          <tr><td>Base URL</td><td><?= BASE_URL ?></td></tr>
          <tr><td>OS</td><td><?= php_uname() ?></td></tr>
        </table>
      </div>

      <div class="settings-card">
        <h3><i data-feather="settings" style="width:16px;height:16px;color:var(--warning);"></i> PHP Configuration</h3>
        <table class="info-table">
          <tr><td>upload_max_filesize</td><td><?= $phpMaxUpload ?></td></tr>
          <tr><td>post_max_size</td><td><?= $phpPostMax ?></td></tr>
          <tr><td>memory_limit</td><td><?= $phpMemLimit ?></td></tr>
          <tr><td>max_execution_time</td><td><?= $phpMaxExec ?>s</td></tr>
          <tr><td>Session Path</td><td><?= session_save_path() ?: 'default' ?></td></tr>
          <tr><td>Loaded Extensions</td><td style="font-size:.75rem;line-height:1.8;"><?= implode(', ', get_loaded_extensions()) ?></td></tr>
        </table>
      </div>

      <div class="settings-card">
        <h3><i data-feather="database" style="width:16px;height:16px;color:#74b9ff;"></i> ฐานข้อมูล</h3>
        <table class="info-table">
          <tr><td>Host</td><td><?= DB_HOST ?></td></tr>
          <tr><td>Database</td><td><?= DB_NAME ?></td></tr>
          <tr><td>ขนาด</td><td><?= $dbSize ?> MB</td></tr>
          <tr><td>จำนวนตาราง</td><td><?= $tableCount ?></td></tr>
          <?php
          $tables = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024,1) AS size_kb FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . DB_NAME . "' ORDER BY TABLE_NAME")->fetchAll();
          foreach ($tables as $t): ?>
          <tr><td style="padding-left:28px;font-size:.78rem;">📄 <?= $t['TABLE_NAME'] ?></td><td><?= number_format($t['TABLE_ROWS']) ?> rows · <?= $t['size_kb'] ?> KB</td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

    <!-- ═══ Site Settings ═══ -->
    <div class="admin-section" id="sec-site">
      <div class="admin-section-title">
        <i data-feather="globe"></i> ตั้งค่าเว็บไซต์
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <div class="settings-card">
          <h3><i data-feather="edit-3" style="width:16px;height:16px;color:var(--primary);"></i> ทั่วไป</h3>
          <div class="settings-row">
            <div class="form-group">
              <label>ชื่อเว็บไซต์</label>
              <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>">
              <div class="form-help">ชื่อที่แสดงบน header และ title</div>
            </div>
            <div class="form-group">
              <label>Base URL</label>
              <input type="text" name="base_url" value="<?= htmlspecialchars($settings['base_url']) ?>">
              <div class="form-help">เช่น /vrx หรือ /myapp (ไม่ต้อง / ตามท้าย)</div>
            </div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="shield" style="width:16px;height:16px;color:var(--warning);"></i> โหมด</h3>
          <div class="form-group">
            <label>โหมดซ่อมบำรุง (Maintenance)</label>
            <select name="maintenance_mode">
              <option value="0" <?= $settings['maintenance_mode'] === '0' ? 'selected' : '' ?>>ปิด — ใช้งานปกติ</option>
              <option value="1" <?= $settings['maintenance_mode'] === '1' ? 'selected' : '' ?>>เปิด — เฉพาะ Admin เข้าได้</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i data-feather="save" style="width:16px;height:16px;"></i> บันทึกการตั้งค่า
        </button>
      </form>
    </div>

    <!-- ═══ Upload Settings ═══ -->
    <div class="admin-section" id="sec-upload">
      <div class="admin-section-title">
        <i data-feather="upload-cloud"></i> ตั้งค่าอัปโหลด
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <div class="settings-card">
          <h3><i data-feather="hard-drive" style="width:16px;height:16px;color:var(--accent);"></i> ขีดจำกัด</h3>
          <div class="settings-row">
            <div class="form-group">
              <label>ขนาดไฟล์สูงสุด (bytes)</label>
              <input type="number" name="max_upload_size" value="<?= $settings['max_upload_size'] ?>">
              <div class="form-help">ปัจจุบัน: <?= fmtBytes((int)$settings['max_upload_size']) ?> · PHP limit: <?= $phpMaxUpload ?></div>
            </div>
            <div class="form-group">
              <label>ค่า Preset</label>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=max_upload_size]').value=52428800">50 MB</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=max_upload_size]').value=104857600">100 MB</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=max_upload_size]').value=262144000">250 MB</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=max_upload_size]').value=524288000">500 MB</button>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="file-plus" style="width:16px;height:16px;color:var(--success);"></i> นามสกุลที่อนุญาต</h3>
          <div class="form-group">
            <label>นามสกุลไฟล์ (คั่นด้วย comma)</label>
            <input type="text" name="allowed_extensions" value="<?= htmlspecialchars($settings['allowed_extensions']) ?>" style="font-family:monospace;">
            <div class="form-help">ปัจจุบัน: <?= htmlspecialchars($settings['allowed_extensions']) ?></div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="info" style="width:16px;height:16px;color:var(--text-muted);"></i> ข้อมูลปัจจุบัน</h3>
          <table class="info-table">
            <tr><td>Upload Directory</td><td><?= UPLOAD_DIR ?></td></tr>
            <tr><td>ขนาดไฟล์ในดิสก์</td><td><?= fmtBytes($uploadDiskSize) ?></td></tr>
            <tr><td>PHP upload_max_filesize</td><td><?= $phpMaxUpload ?></td></tr>
            <tr><td>PHP post_max_size</td><td><?= $phpPostMax ?></td></tr>
          </table>
        </div>

        <button type="submit" class="btn btn-primary">
          <i data-feather="save" style="width:16px;height:16px;"></i> บันทึกการตั้งค่า
        </button>
      </form>
    </div>

    <!-- ═══ QR Code Settings ═══ -->
    <div class="admin-section" id="sec-qr">
      <div class="admin-section-title">
        <i data-feather="maximize"></i> ตั้งค่า QR Code
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <div class="settings-card">
          <h3><i data-feather="link" style="width:16px;height:16px;color:var(--primary);"></i> Base URL ของ QR</h3>
          <div class="form-group">
            <label>QR Base URL (เว้นว่าง = ใช้ URL ของเซิร์ฟเวอร์ปัจจุบัน)</label>
            <input type="url" name="qr_base_url" value="<?= htmlspecialchars($settings['qr_base_url']) ?>" placeholder="เช่น https://yourdomain.com">
            <div class="form-help">ถ้าต้องการใช้ domain จริงแทน localhost ให้กรอกที่นี่ เช่น https://studio.vrx.co.th หรือใช้ ngrok URL</div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="code" style="width:16px;height:16px;color:var(--accent);"></i> รูปแบบ URL</h3>
          <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;">
            ตัวแปรที่ใช้ได้:
            <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;font-size:.72rem;">{origin}</code> = โดเมน
            <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;font-size:.72rem;">{base}</code> = base path
            <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;font-size:.72rem;">{file_url}</code> = URL ของไฟล์
            <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;font-size:.72rem;">{file_url_abs}</code> = URL เต็มของไฟล์
          </p>

          <div class="form-group">
            <label>📦 AR Viewer URL Pattern</label>
            <input type="text" name="qr_pattern_ar" value="<?= htmlspecialchars($settings['qr_pattern_ar']) ?>" style="font-family:monospace;font-size:.82rem;">
            <div class="form-help">ใช้กับโมเดล 3D ที่ต้องการแสดง AR — <code>{file_url}</code>, <code>{id}</code></div>
          </div>
          <div class="form-group">
            <label>🔮 3D Viewer URL Pattern</label>
            <input type="text" name="qr_pattern_3d" value="<?= htmlspecialchars($settings['qr_pattern_3d']) ?>" style="font-family:monospace;font-size:.82rem;">
            <div class="form-help">ใช้กับโมเดล 3D ที่ดูใน 3D viewer — <code>{file_url}</code>, <code>{id}</code></div>
          </div>
          <div class="form-group">
            <label>🌄 Panorama URL Pattern</label>
            <input type="text" name="qr_pattern_pano" value="<?= htmlspecialchars($settings['qr_pattern_pano']) ?>" style="font-family:monospace;font-size:.82rem;">
            <div class="form-help">ใช้กับภาพพาโนรามา — <code>{file_url}</code>, <code>{id}</code></div>
          </div>
          <div class="form-group">
            <label>💻 Embed URL Pattern</label>
            <input type="text" name="qr_pattern_embed" value="<?= htmlspecialchars($settings['qr_pattern_embed']) ?>" style="font-family:monospace;font-size:.82rem;">
            <div class="form-help">ใช้กับ Embed (Sketchfab, YouTube, ฯลฯ) — <code>{embed_src}</code> = embed_src จาก DB, <code>{id}</code> = file ID</div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="image" style="width:16px;height:16px;color:var(--success);"></i> รูปแบบ QR Code</h3>
          <div class="settings-row">
            <div class="form-group">
              <label>ขนาด (px)</label>
              <input type="number" name="qr_size" value="<?= $settings['qr_size'] ?>" min="100" max="600" step="50">
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=qr_size]').value=150">150</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=qr_size]').value=200">200</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=qr_size]').value=250">250</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=qr_size]').value=300">300</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="document.querySelector('[name=qr_size]').value=400">400</button>
              </div>
            </div>
            <div class="form-group">
              <label>Error Correction Level</label>
              <select name="qr_error_level">
                <option value="L" <?= $settings['qr_error_level']==='L'?'selected':'' ?>>L (7%)</option>
                <option value="M" <?= $settings['qr_error_level']==='M'?'selected':'' ?>>M (15%)</option>
                <option value="Q" <?= $settings['qr_error_level']==='Q'?'selected':'' ?>>Q (25%)</option>
                <option value="H" <?= $settings['qr_error_level']==='H'?'selected':'' ?>>H (30%)</option>
              </select>
              <div class="form-help">ยิ่งสูงยิ่งทนความเสียหาย แต่ QR จะซับซ้อนขึ้น</div>
            </div>
          </div>
          <div class="settings-row">
            <div class="form-group">
              <label>สีดำ (Foreground)</label>
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="color" name="qr_color_dark" value="<?= $settings['qr_color_dark'] ?>" style="width:50px;height:36px;padding:2px;cursor:pointer;">
                <input type="text" value="<?= $settings['qr_color_dark'] ?>" style="flex:1;font-family:monospace;" 
                       oninput="this.previousElementSibling.value=this.value" 
                       onfocus="this.previousElementSibling.oninput=function(){document.querySelector('[name=qr_color_dark]').nextElementSibling.value=this.value}">
              </div>
            </div>
            <div class="form-group">
              <label>สีพื้นหลัง (Background)</label>
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="color" name="qr_color_light" value="<?= $settings['qr_color_light'] ?>" style="width:50px;height:36px;padding:2px;cursor:pointer;">
                <input type="text" value="<?= $settings['qr_color_light'] ?>" style="flex:1;font-family:monospace;"
                       oninput="this.previousElementSibling.value=this.value">
              </div>
            </div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="eye" style="width:16px;height:16px;color:var(--text-muted);"></i> ตัวอย่าง Preview</h3>
          <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div id="qr-admin-preview" style="background:#fff;padding:12px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;min-width:150px;min-height:150px;">
              <span style="color:#999;font-size:.8rem;">กด Preview ด้านล่าง</span>
            </div>
            <div>
              <button type="button" class="btn btn-sm btn-outline" onclick="previewQR()">
                <i data-feather="maximize" style="width:14px;height:14px;"></i> Preview QR
              </button>
              <div style="margin-top:8px;font-size:.75rem;color:var(--text-muted);">
                แสดงตัวอย่าง QR จากการตั้งค่าปัจจุบัน
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i data-feather="save" style="width:16px;height:16px;"></i> บันทึกการตั้งค่า
        </button>
      </form>
    </div>

    <!-- ═══ Iframe Config ═══ -->
    <div class="admin-section" id="sec-iframe">
      <div class="admin-section-title">
        <i data-feather="code"></i> Iframe Config (Kiri Engine)
      </div>

      <!-- Save settings form -->
      <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <div class="settings-card">
          <h3><i data-feather="settings" style="width:16px;height:16px;color:var(--primary);"></i> Kiri Engine Parameters</h3>
          <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;">
            กำหนดค่าพารามิเตอร์ที่จะต่อท้าย URL ของ iframe จาก Kiri Engine โดยอัตโนมัติ<br>
            <em>userId จะถูกเก็บไว้จาก URL ต้นฉบับ (เปลี่ยนตามแหล่งที่มา)</em>
          </p>
          <div class="settings-row">
            <div class="form-group">
              <label>Background Theme</label>
              <select name="iframe_kiri_bg_theme">
                <option value="transparent" <?= $settings['iframe_kiri_bg_theme']==='transparent'?'selected':'' ?>>transparent</option>
                <option value="dark" <?= $settings['iframe_kiri_bg_theme']==='dark'?'selected':'' ?>>dark</option>
                <option value="light" <?= $settings['iframe_kiri_bg_theme']==='light'?'selected':'' ?>>light</option>
                <option value="gradient" <?= $settings['iframe_kiri_bg_theme']==='gradient'?'selected':'' ?>>gradient</option>
              </select>
            </div>
          </div>
          <div class="settings-row">
            <div class="form-group">
              <label>Auto Spin Model</label>
              <select name="iframe_kiri_auto_spin">
                <option value="1" <?= $settings['iframe_kiri_auto_spin']==='1'?'selected':'' ?>>เปิด</option>
                <option value="0" <?= $settings['iframe_kiri_auto_spin']==='0'?'selected':'' ?>>ปิด</option>
              </select>
            </div>
            <div class="form-group">
              <label>พารามิเตอร์เพิ่มเติม (key=value, คั่นด้วย &amp;)</label>
              <input type="text" name="iframe_default_params" value="<?= htmlspecialchars($settings['iframe_default_params']) ?>" style="font-family:monospace;font-size:.82rem;">
              <div class="form-help">เช่น userId=1665127&amp;bg_theme=transparent&amp;auto_spin_model=1</div>
            </div>
          </div>
        </div>

        <div class="settings-card">
          <h3><i data-feather="layout" style="width:16px;height:16px;color:var(--accent);"></i> Iframe Attributes</h3>
          <div class="form-group">
            <label>Default Attributes</label>
            <textarea name="iframe_default_attrs" rows="3" style="font-family:monospace;font-size:.8rem;"><?= htmlspecialchars($settings['iframe_default_attrs']) ?></textarea>
            <div class="form-help">คุณสมบัติที่จะเพิ่มในแท็ก iframe เช่น allowfullscreen, allow="autoplay; fullscreen;"</div>
          </div>
          <div class="settings-row">
            <div class="form-group">
              <label>Width (px)</label>
              <input type="number" name="iframe_width" value="<?= $settings['iframe_width'] ?>" min="100" max="1920">
            </div>
            <div class="form-group">
              <label>Height (px)</label>
              <input type="number" name="iframe_height" value="<?= $settings['iframe_height'] ?>" min="100" max="1080">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-bottom:24px;">
          <i data-feather="save" style="width:16px;height:16px;"></i> บันทึกการตั้งค่า
        </button>
      </form>

      <!-- ── Tool: Parse & Transform iframe ── -->
      <div class="settings-card">
        <h3><i data-feather="scissors" style="width:16px;height:16px;color:var(--warning);"></i> ตัดแต่ง Iframe Code</h3>
        <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:12px;">
          วาง iframe code ดิบจาก Kiri Engine ระบบจะตัด parameter เก่าออก เหลือแค่ link ต้นและชื่อโปรเจกต์ และต่อท้ายด้วยพารามิเตอร์ที่ตั้งไว้
        </p>

        <div class="form-group">
          <label>วาง Iframe Code ดิบ (หนึ่งหรือหลายอัน)</label>
          <textarea id="iframe-raw-input" rows="6" style="font-family:monospace;font-size:.78rem;width:100%;" 
                    placeholder='วาง <iframe> code ที่นี่... สามารถวางหลายอันได้'></textarea>
        </div>

        <button type="button" class="btn btn-sm btn-primary" onclick="parseIframes()" style="margin-bottom:16px;">
          <i data-feather="zap" style="width:14px;height:14px;"></i> ตัดแต่ง &amp; สร้างใหม่
        </button>

        <!-- Parsed results -->
        <div id="iframe-parsed-results" style="display:none;">
          <h4 style="font-size:.88rem;margin-bottom:10px;color:var(--text);">📋 ผลลัพธ์</h4>
          <div id="iframe-parsed-list"></div>
        </div>
      </div>

      <!-- ── Tool: Bulk update existing embeds ── -->
      <div class="settings-card">
        <h3><i data-feather="refresh-cw" style="width:16px;height:16px;color:var(--success);"></i> อัปเดต Embed ทั้งหมดในระบบ</h3>
        <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:12px;">
          ปรับ embed_src ของไฟล์ที่มี provider เป็น "kiri" โดยตัด parameter เก่าออกและต่อท้ายด้วยค่าจากการตั้งค่าด้านบน
        </p>

        <?php
        $embedFiles = $pdo->query("SELECT id, name, embed_src, embed_provider FROM files WHERE source_type='embed' AND status='active' ORDER BY id DESC")->fetchAll();
        ?>

        <?php if ($embedFiles): ?>
        <div style="max-height:300px;overflow-y:auto;margin-bottom:12px;">
          <table class="info-table" style="width:100%;">
            <thead><tr>
              <th style="width:40px;">ID</th>
              <th>ชื่อ</th>
              <th>Provider</th>
              <th>embed_src (ปัจจุบัน)</th>
            </tr></thead>
            <tbody>
            <?php foreach($embedFiles as $ef): ?>
              <tr>
                <td><?= $ef['id'] ?></td>
                <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ef['name'] ?? '') ?></td>
                <td><span class="badge"><?= htmlspecialchars($ef['embed_provider'] ?? '-') ?></span></td>
                <td style="font-family:monospace;font-size:.72rem;word-break:break-all;"><?= htmlspecialchars($ef['embed_src'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline" style="color:var(--warning);border-color:var(--warning);" onclick="bulkUpdateKiri()">
          <i data-feather="refresh-cw" style="width:14px;height:14px;"></i> อัปเดต Kiri Embed ทั้งหมด (<?= count($embedFiles) ?> รายการ)
        </button>
        <div id="bulk-update-result" style="margin-top:10px;"></div>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:.85rem;">ยังไม่มีไฟล์ embed ในระบบ</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ Users ═══ -->
    <div class="admin-section" id="sec-users">
      <div class="admin-section-title">
        <i data-feather="users"></i> จัดการผู้ใช้งาน
      </div>

      <div class="settings-card" style="overflow-x:auto;">
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>ชื่อผู้ใช้</th>
              <th>ชื่อแสดง</th>
              <th>อีเมล</th>
              <th>บทบาท</th>
              <th>สถานะ</th>
              <th>เข้าล่าสุด</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td style="color:var(--text-muted);">#<?= $u['id'] ?></td>
              <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
              <td><?= htmlspecialchars($u['display_name'] ?? '—') ?></td>
              <td style="font-size:.78rem;color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="user-role-badge <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td>
                <?php if ($u['is_active']): ?>
                  <span class="user-active">● Active</span>
                <?php else: ?>
                  <span class="user-inactive">● Inactive</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.78rem;color:var(--text-muted);"><?= $u['last_login_at'] ?? '—' ?></td>
              <td>
                <button class="btn btn-sm btn-outline" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                  <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                </button>
                <button class="btn btn-sm btn-outline" style="color:var(--warning);" onclick="resetPassword(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username']) ?>')">
                  <i data-feather="key" style="width:14px;height:14px;"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ Tools ═══ -->
    <div class="admin-section" id="sec-tools">
      <div class="admin-section-title">
        <i data-feather="tool"></i> เครื่องมือระบบ
      </div>

      <div class="action-grid">
        <div class="action-card">
          <h4><i data-feather="download-cloud" style="width:16px;height:16px;color:var(--primary);"></i> Backup ฐานข้อมูล</h4>
          <p>ดาวน์โหลดไฟล์ .sql ของฐานข้อมูลทั้งหมด รวมโครงสร้างตารางและข้อมูล</p>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="backup_db">
            <button type="submit" class="btn btn-sm btn-primary">
              <i data-feather="database" style="width:14px;height:14px;"></i> ดาวน์โหลด Backup
            </button>
          </form>
        </div>

        <div class="action-card">
          <h4><i data-feather="trash-2" style="width:16px;height:16px;color:var(--danger);"></i> ล้างไฟล์ที่ลบ</h4>
          <p>ลบถาวรไฟล์ที่อยู่ในถังขยะเกิน 7 วัน (ปัจจุบัน: <?= $totalDeleted ?> ไฟล์ในถังขยะ)</p>
          <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันลบถาวร?')">
            <input type="hidden" name="action" value="cleanup_deleted">
            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger);">
              <i data-feather="trash" style="width:14px;height:14px;"></i> ล้างถังขยะ
            </button>
          </form>
        </div>

        <div class="action-card">
          <h4><i data-feather="refresh-cw" style="width:16px;height:16px;color:var(--accent);"></i> ล้าง Cache</h4>
          <p>ล้าง session cache และ PHP opcache (ถ้าเปิดใช้)</p>
          <button class="btn btn-sm btn-outline" onclick="clearCache()">
            <i data-feather="zap" style="width:14px;height:14px;"></i> ล้าง Cache
          </button>
        </div>

        <div class="action-card">
          <h4><i data-feather="activity" style="width:16px;height:16px;color:var(--success);"></i> ตรวจสอบระบบ</h4>
          <p>ตรวจสอบสถานะ PHP, MySQL, Upload directory และ permissions</p>
          <button class="btn btn-sm btn-outline" onclick="runHealthCheck()">
            <i data-feather="check-circle" style="width:14px;height:14px;"></i> ตรวจสอบ
          </button>
          <div id="health-results" style="margin-top:12px;display:none;"></div>
        </div>
      </div>
    </div>

  </div><!-- /admin-content -->
</div><!-- /admin-grid -->
</div><!-- /container -->
</div><!-- /page-content -->

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>

<!-- User Edit Modal (hidden) -->
<div id="user-edit-modal" class="user-edit-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <form class="user-edit-form" method="POST">
    <input type="hidden" name="action" value="update_user">
    <input type="hidden" name="user_id" id="ue-id">
    <h3>✏️ แก้ไขผู้ใช้: <span id="ue-name"></span></h3>
    <div class="form-group">
      <label>ชื่อแสดง</label>
      <input type="text" name="display_name" id="ue-display">
    </div>
    <div class="form-group">
      <label>บทบาท</label>
      <select name="role" id="ue-role">
        <option value="admin">Admin</option>
        <option value="user">User</option>
        <option value="viewer">Viewer</option>
      </select>
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="is_active" id="ue-active" style="width:18px;height:18px;accent-color:var(--primary);">
        เปิดใช้งาน
      </label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
      <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('user-edit-modal').style.display='none'">ยกเลิก</button>
      <button type="submit" class="btn btn-sm btn-primary"><i data-feather="check" style="width:14px;height:14px;"></i> บันทึก</button>
    </div>
  </form>
</div>

<!-- Reset Password Modal -->
<div id="pwd-modal" class="user-edit-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <form class="user-edit-form" method="POST">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" id="pw-id">
    <h3>🔑 รีเซ็ตรหัสผ่าน: <span id="pw-name"></span></h3>
    <div class="form-group">
      <label>รหัสผ่านใหม่</label>
      <input type="text" name="new_password" placeholder="รหัสผ่านใหม่..." required>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
      <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('pwd-modal').style.display='none'">ยกเลิก</button>
      <button type="submit" class="btn btn-sm btn-primary"><i data-feather="key" style="width:14px;height:14px;"></i> รีเซ็ต</button>
    </div>
  </form>
</div>

<script src="<?= BASE_URL ?>/third_party/qrcode.min.js"></script>
<script>
// Tab switching
function switchTab(id, el) {
  document.querySelectorAll('.admin-section').forEach(function(s){ s.classList.remove('active'); });
  document.querySelectorAll('.admin-sidebar a').forEach(function(a){ a.classList.remove('active'); });
  var sec = document.getElementById('sec-' + id);
  if (sec) sec.classList.add('active');
  if (el) el.classList.add('active');
  feather.replace();
}

// Check hash on load
var hash = window.location.hash.replace('#','');
if (hash && document.getElementById('sec-' + hash)) {
  var link = document.querySelector('.admin-sidebar a[href="#' + hash + '"]');
  switchTab(hash, link);
}

// User edit
function editUser(u) {
  document.getElementById('ue-id').value = u.id;
  document.getElementById('ue-name').textContent = u.username;
  document.getElementById('ue-display').value = u.display_name || '';
  document.getElementById('ue-role').value = u.role;
  document.getElementById('ue-active').checked = u.is_active == 1;
  document.getElementById('user-edit-modal').style.display = 'flex';
  feather.replace();
}

// Password reset
function resetPassword(id, name) {
  document.getElementById('pw-id').value = id;
  document.getElementById('pw-name').textContent = name;
  document.getElementById('pwd-modal').style.display = 'flex';
  feather.replace();
}

// Clear cache
function clearCache() {
  if (typeof opcache_reset === 'function') opcache_reset();
  alert('ล้าง Cache เรียบร้อย');
}

// Health check
function runHealthCheck() {
  var el = document.getElementById('health-results');
  el.style.display = 'block';
  el.innerHTML = '<div style="color:var(--text-muted);font-size:.82rem;">กำลังตรวจสอบ...</div>';

  var checks = [];
  checks.push({ name: 'PHP Version', status: '<?= version_compare($phpVer, "8.0", ">=") ? "ok" : "warn" ?>', detail: '<?= $phpVer ?>' });
  checks.push({ name: 'MySQL', status: 'ok', detail: '<?= $mysqlVer ?>' });
  checks.push({ name: 'Upload Dir', status: '<?= is_writable(UPLOAD_DIR) ? "ok" : "fail" ?>', detail: '<?= is_writable(UPLOAD_DIR) ? "Writable" : "Not writable!" ?>' });
  checks.push({ name: 'Disk Space', status: '<?= ($diskFree > 1073741824) ? "ok" : (($diskFree > 104857600) ? "warn" : "fail") ?>', detail: '<?= fmtBytes($diskFree) ?> free' });
  checks.push({ name: 'PDO MySQL', status: '<?= extension_loaded("pdo_mysql") ? "ok" : "fail" ?>', detail: '<?= extension_loaded("pdo_mysql") ? "Loaded" : "Missing!" ?>' });
  checks.push({ name: 'JSON ext', status: '<?= extension_loaded("json") ? "ok" : "fail" ?>', detail: '<?= extension_loaded("json") ? "Loaded" : "Missing!" ?>' });
  checks.push({ name: 'GD/Imagick', status: '<?= (extension_loaded("gd") || extension_loaded("imagick")) ? "ok" : "warn" ?>', detail: '<?= extension_loaded("gd") ? "GD" : (extension_loaded("imagick") ? "Imagick" : "None") ?>' });

  var html = '<div style="display:flex;flex-direction:column;gap:4px;margin-top:8px;">';
  checks.forEach(function(c) {
    var icon = c.status === 'ok' ? '✅' : (c.status === 'warn' ? '⚠️' : '❌');
    html += '<div style="font-size:.78rem;display:flex;gap:6px;align-items:center;">' + icon + ' <strong>' + c.name + '</strong> <span style="color:var(--text-muted);">— ' + c.detail + '</span></div>';
  });
  html += '</div>';
  el.innerHTML = html;
}

// Iframe Parser
function parseIframes() {
  var raw = document.getElementById('iframe-raw-input').value.trim();
  if (!raw) { alert('กรุณาวาง iframe code'); return; }

  // Get current settings from form
  var bgTheme = document.querySelector('[name=iframe_kiri_bg_theme]').value;
  var autoSpin = document.querySelector('[name=iframe_kiri_auto_spin]').value;
  var extraParams = document.querySelector('[name=iframe_default_params]').value;
  var defaultAttrs = document.querySelector('[name=iframe_default_attrs]').value;
  var iframeW = document.querySelector('[name=iframe_width]').value;
  var iframeH = document.querySelector('[name=iframe_height]').value;

  // Build override params from settings (bg_theme + auto_spin_model + extras)
  var overrideMap = {};
  overrideMap['bg_theme'] = bgTheme;
  overrideMap['auto_spin_model'] = autoSpin;
  if (extraParams) {
    extraParams.split('&').forEach(function(p) {
      var kv = p.split('=');
      if (kv[0]) overrideMap[kv[0]] = kv[1] || '';
    });
  }

  // Find all iframe tags in raw input
  var iframeRegex = /<iframe[^>]*>[\s\S]*?<\/iframe>/gi;
  var matches = raw.match(iframeRegex);
  if (!matches || !matches.length) {
    // Maybe it's just URLs, one per line
    matches = raw.split('\n').filter(function(l) { return l.trim().length > 0; }).map(function(l) {
      return '<iframe src="' + l.trim() + '"></iframe>';
    });
  }

  var results = [];
  matches.forEach(function(iframeStr) {
    // Extract src
    var srcMatch = iframeStr.match(/src=["']([^"']+)["']/);
    if (!srcMatch) return;
    var fullUrl = srcMatch[1];

    // Extract title
    var titleMatch = iframeStr.match(/title=["']([^"']+)["']/);
    var title = titleMatch ? titleMatch[1] : '';

    // Strip query params from URL, keep originals
    var urlParts = fullUrl.split('?');
    var baseUrl = urlParts[0];
    var origQuery = urlParts[1] || '';

    // Replace sharemodel → embed (case-insensitive), keep /share/ intact
    baseUrl = baseUrl.replace(/\/sharemodel(\/|$)/gi, '/embed$1');

    // Parse original params (keep userId etc), override with settings
    var paramMap = {};
    if (origQuery) {
      origQuery.split('&').forEach(function(p) {
        var kv = p.split('=');
        if (kv[0]) paramMap[kv[0]] = kv[1] || '';
      });
    }
    // Apply overrides (bg_theme, auto_spin_model, etc) — keep userId from original
    Object.keys(overrideMap).forEach(function(k) { paramMap[k] = overrideMap[k]; });
    var newParams = Object.keys(paramMap).map(function(k) { return k + '=' + paramMap[k]; }).join('&');

    // Build new URL
    var newUrl = baseUrl + '?' + newParams;

    // Build clean iframe
    var newIframe = '<iframe src="' + newUrl + '"';
    if (title) newIframe += ' title="' + title + '"';
    newIframe += ' ' + defaultAttrs;
    newIframe += ' width="' + iframeW + '" height="' + iframeH + '"';
    newIframe += '></iframe>';

    results.push({
      title: title || baseUrl.split('/').pop(),
      originalUrl: fullUrl,
      baseUrl: baseUrl,
      newUrl: newUrl,
      newIframe: newIframe
    });
  });

  // Display results
  var container = document.getElementById('iframe-parsed-results');
  var list = document.getElementById('iframe-parsed-list');
  container.style.display = 'block';

  if (!results.length) {
    list.innerHTML = '<div style="color:var(--danger);font-size:.85rem;">ไม่พบ iframe หรือ URL ในข้อมูล</div>';
    return;
  }

  var html = '';
  results.forEach(function(r, i) {
    html += '<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;">';
    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><strong style="font-size:.88rem;">' + (i+1) + '. ' + r.title + '</strong></div>';
    html += '<div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;"><b>Original:</b> <span style="word-break:break-all;">' + r.originalUrl + '</span></div>';
    html += '<div style="font-size:.72rem;color:var(--success);margin-bottom:4px;"><b>Base URL:</b> ' + r.baseUrl + '</div>';
    html += '<div style="font-size:.72rem;color:var(--primary);margin-bottom:8px;"><b>New URL:</b> <span style="word-break:break-all;">' + r.newUrl + '</span></div>';
    html += '<div style="margin-bottom:8px;"><label style="font-size:.72rem;font-weight:600;color:var(--text-secondary);">โค้ด iframe ใหม่:</label>';
    html += '<textarea readonly style="width:100%;font-family:monospace;font-size:.72rem;height:60px;margin-top:4px;" onclick="this.select()">' + r.newIframe.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</textarea></div>';
    html += '<div style="display:flex;gap:6px;">';
    html += '<button type="button" class="btn btn-sm btn-outline" onclick="copyText(this.parentElement.previousElementSibling.querySelector(\'textarea\').value.replace(/&lt;/g,\'<\').replace(/&gt;/g,\'>\'))">';
    html += '<i data-feather="copy" style="width:12px;height:12px;"></i> คัดลอก</button>';
    html += '<button type="button" class="btn btn-sm btn-primary" onclick="saveAsEmbed(\'' + encodeURIComponent(r.newUrl) + '\',\'' + encodeURIComponent(r.title) + '\')">';
    html += '<i data-feather="save" style="width:12px;height:12px;"></i> บันทึกเป็น Embed</button>';
    html += '</div></div>';
  });
  list.innerHTML = html;
  feather.replace();
}

function copyText(text) {
  text = text.replace(/&lt;/g,'<').replace(/&gt;/g,'>');
  navigator.clipboard.writeText(text).then(function() { alert('คัดลอกแล้ว!'); });
}

function saveAsEmbed(encodedUrl, encodedTitle) {
  var url = decodeURIComponent(encodedUrl);
  var title = decodeURIComponent(encodedTitle);
  var defaultAttrs = document.querySelector('[name=iframe_default_attrs]').value;
  var iframeW = document.querySelector('[name=iframe_width]').value;
  var iframeH = document.querySelector('[name=iframe_height]').value;
  var code = '<iframe src="' + url + '" title="' + title + '" ' + defaultAttrs + ' width="' + iframeW + '" height="' + iframeH + '"></iframe>';

  fetch('<?= BASE_URL ?>/api/index.php?action=files', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: title || 'Kiri Embed',
      source_type: 'embed',
      embed_src: url,
      embed_code: code,
      embed_provider: 'kiri',
      category: 'embed',
      visibility: 'public'
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.error) alert('Error: ' + d.error);
    else alert('บันทึกเรียบร้อย! ID: ' + (d.data && d.data.id));
  })
  .catch(function(e) { alert('Error: ' + e.message); });
}

function bulkUpdateKiri() {
  if (!confirm('อัปเดต embed_src ของไฟล์ embed ทั้งหมด?\nระบบจะตัด query string เก่าออกและต่อพารามิเตอร์ใหม่ตามการตั้งค่า')) return;

  var bgTheme = document.querySelector('[name=iframe_kiri_bg_theme]').value;
  var autoSpin = document.querySelector('[name=iframe_kiri_auto_spin]').value;
  var overrides = 'bg_theme=' + bgTheme + '&auto_spin_model=' + autoSpin;
  var extra = document.querySelector('[name=iframe_default_params]').value;
  if (extra) {
    extra.split('&').forEach(function(p) {
      var key = p.split('=')[0];
      if (key && overrides.indexOf(key + '=') === -1) overrides += '&' + p;
    });
  }

  var resultEl = document.getElementById('bulk-update-result');
  resultEl.innerHTML = '<span style="color:var(--text-muted);font-size:.82rem;">กำลังอัปเดต...</span>';

  fetch('<?= BASE_URL ?>/api/index.php?action=bulk_update_embeds', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ overrides: overrides })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.error) {
      resultEl.innerHTML = '<span style="color:var(--danger);font-size:.82rem;">❌ ' + d.error + '</span>';
    } else {
      resultEl.innerHTML = '<span style="color:var(--success);font-size:.82rem;">✅ อัปเดตแล้ว ' + (d.data && d.data.updated || 0) + ' รายการ</span>';
      setTimeout(function(){ location.reload(); }, 1500);
    }
  })
  .catch(function(e) {
    resultEl.innerHTML = '<span style="color:var(--danger);font-size:.82rem;">❌ ' + e.message + '</span>';
  });
}

// QR Preview
function previewQR() {
  var container = document.getElementById('qr-admin-preview');
  if (!container) return;
  container.innerHTML = '';
  var size = parseInt(document.querySelector('[name=qr_size]').value) || 250;
  var dark = document.querySelector('[name=qr_color_dark]').value || '#000000';
  var light = document.querySelector('[name=qr_color_light]').value || '#ffffff';
  var level = document.querySelector('[name=qr_error_level]').value || 'M';
  var pattern = document.querySelector('[name=qr_pattern_ar]').value || '{origin}{base}/pages/ar.php?src={file_url}';
  // Replace placeholders with sample values
  var sampleUrl = pattern.replace('{origin}', window.location.origin)
    .replace('{base}', '<?= BASE_URL ?>')
    .replace('{file_url_abs}', window.location.origin + '<?= BASE_URL ?>/uploads/sample.glb')
    .replace('{file_url}', '<?= BASE_URL ?>/uploads/sample.glb');
  var lvlMap = { L: 1, M: 0, Q: 3, H: 2 };
  var previewSize = Math.min(size, 250);
  try {
    new QRCode(container, {
      text: sampleUrl,
      width: previewSize,
      height: previewSize,
      colorDark: dark,
      colorLight: light,
      correctLevel: lvlMap[level] !== undefined ? lvlMap[level] : 0
    });
  } catch(e) {
    container.innerHTML = '<span style="color:red;font-size:.8rem;">Error: ' + e.message + '</span>';
  }
}

feather.replace();
</script>
</body>
</html>