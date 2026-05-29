<?php
/**
 * System Monitor — Pro Edition
 */
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }

$role = $user['role_name'] ?? 'user';
if (!in_array($role, ['admin', 'ceo', 'lab_manager'])) {
    header('HTTP/1.1 403 Forbidden');
    echo '<div style="font-family:sans-serif;text-align:center;padding:100px"><h2>Access Denied</h2></div>';
    exit;
}

$lang   = I18n::getCurrentLang();
$TH     = ($lang === 'th');
$action = $_GET['action'] ?? '';

/* ─── Utilities ─────────────────────────────────────────────────────────── */

function sm_bytes(string $v): int {
    $v = trim($v); $last = strtolower(substr($v, -1)); $n = (int)$v;
    if ($last === 'g') return $n * 1073741824;
    if ($last === 'm') return $n * 1048576;
    if ($last === 'k') return $n * 1024;
    return $n;
}
function sm_fmt(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1)    . ' MB';
    if ($b >= 1024)       return round($b / 1024, 1)       . ' KB';
    return $b . ' B';
}

/* ─── Data functions ─────────────────────────────────────────────────────── */

function sm_stats(): array {
    try {
        return [
            'chemicals'  => (int)(Database::fetch("SELECT COUNT(*) c FROM chemicals  WHERE is_active=1")['c']  ?? 0),
            'containers' => (int)(Database::fetch("SELECT COUNT(*) c FROM containers WHERE status='active'")['c'] ?? 0),
            'users'      => (int)(Database::fetch("SELECT COUNT(*) c FROM users      WHERE is_active=1")['c']  ?? 0),
            'alerts'     => (int)(Database::fetch("SELECT COUNT(*) c FROM alerts     WHERE is_read=0")['c']    ?? 0),
            'db_ok'      => true,
        ];
    } catch (Exception $e) {
        return ['chemicals' => 0, 'containers' => 0, 'users' => 0, 'alerts' => 0, 'db_ok' => false];
    }
}

function sm_dbStats(): array {
    try {
        $db = Database::fetch("SELECT DATABASE() db")['db'] ?? null;
        if (!$db) return ['size_mb' => 0, 'tables' => 0, 'table_list' => [], 'db_name' => '?'];
        $sz  = Database::fetch(
            "SELECT ROUND(SUM(data_length+index_length)/1048576,2) sz, COUNT(*) tc
             FROM information_schema.TABLES WHERE table_schema=?", [$db]
        );
        $tbl = Database::fetchAll(
            "SELECT TABLE_NAME nm,
                    ROUND((data_length+index_length)/1024,1) kb,
                    IFNULL(table_rows,0) rw
             FROM information_schema.TABLES WHERE table_schema=?
             ORDER BY (data_length+index_length) DESC LIMIT 25", [$db]
        );
        return [
            'size_mb'    => (float)($sz['sz']    ?? 0),
            'tables'     => (int)($sz['tc']       ?? 0),
            'table_list' => $tbl,
            'db_name'    => $db,
        ];
    } catch (Exception $e) {
        return ['size_mb' => 0, 'tables' => 0, 'table_list' => [], 'db_name' => '?'];
    }
}

function sm_health(): array {
    $lim = ini_get('memory_limit');
    $use = memory_get_usage(true);
    $pk  = memory_get_peak_usage(true);
    $lb  = sm_bytes($lim);
    try   { $mv = Database::fetch("SELECT VERSION() v")['v'] ?? '?'; $dok = true; }
    catch (Exception $e) { $mv = '?'; $dok = false; }
    return [
        'php'      => PHP_VERSION,
        'sapi'     => php_sapi_name(),
        'mem_lim'  => $lim,
        'mem_use'  => sm_fmt($use),
        'mem_peak' => sm_fmt($pk),
        'mem_pct'  => $lb > 0 ? round($use / $lb * 100, 1) : 0,
        'max_exec' => ini_get('max_execution_time') . 's',
        'upload'   => ini_get('upload_max_filesize'),
        'server'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'host'     => gethostname() ?: 'Unknown',
        'tz'       => date_default_timezone_get(),
        'now'      => date('Y-m-d H:i:s'),
        'mysql'    => $mv,
        'db_ok'    => $dok,
        'app_ver'  => defined('APP_VERSION') ? APP_VERSION : '2.0.0',
        'env'      => $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production',
    ];
}

function sm_logs(string $level = 'all', int $limit = 60, string $q = ''): array {
    $dir  = __DIR__ . '/../logs/';
    $logs = [];
    foreach (['combined.log', 'error.log', 'warning.log', 'info.log'] as $f) {
        $p = $dir . $f;
        if (!file_exists($p)) continue;
        foreach (array_slice(file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -300) as $line) {
            if (!preg_match('/\[([^\]]+)\] \[([^\]]+)\] (.+)/', $line, $m)) continue;
            $lv = strtoupper($m[2]);
            if ($level !== 'all' && strtolower($level) !== strtolower($lv)) continue;
            if ($q !== '' && stripos($m[3], $q) === false) continue;
            $logs[] = ['time' => $m[1], 'level' => $lv, 'message' => $m[3]];
        }
    }
    usort($logs, fn($a, $b) => strcmp($b['time'], $a['time']));
    return array_slice($logs, 0, $limit);
}

function sm_clearLogs(): array {
    $count = ErrorLogger::getInstance()->cleanOldLogs(7);
    return ['cleared' => $count, 'message' => "Cleared {$count} old log file(s)"];
}

/* ─── Backup helpers ─────────────────────────────────────────────────────── */

$SM_BACKUP_DIR = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

function sm_ensureBackupDir(): bool {
    global $SM_BACKUP_DIR;
    if (!is_dir($SM_BACKUP_DIR)) return @mkdir($SM_BACKUP_DIR, 0750, true);
    return true;
}

function sm_getAllTableNames(): array {
    $db = Database::fetch("SELECT DATABASE() d")['d'] ?? '';
    $rows = Database::fetchAll(
        "SELECT TABLE_NAME n, IFNULL(TABLE_ROWS,0) rw FROM information_schema.TABLES WHERE TABLE_SCHEMA=:db ORDER BY TABLE_NAME",
        [':db' => $db]
    );
    return $rows;
}

function sm_exportSQL(array $opts = []): array {
    global $SM_BACKUP_DIR;
    set_time_limit(600);

    $requestedTables = $opts['tables']   ?? [];
    $dataOnly   = (bool)($opts['data_only'] ?? false);
    $noData     = (bool)($opts['no_data']   ?? false);
    $label      = preg_replace('/[^a-z0-9_]/', '', strtolower($opts['label'] ?? 'full'));
    $splitBytes = max(0, (int)($opts['split_mb'] ?? 0)) * 1048576; // 0 = no split

    $allMeta  = sm_getAllTableNames();
    $allNames = array_column($allMeta, 'n');
    $tables   = !empty($requestedTables)
        ? array_values(array_intersect($requestedTables, $allNames))
        : $allNames;
    if (empty($tables)) throw new Exception('No tables selected');

    sm_ensureBackupDir();

    $pdo        = Database::getInstance();
    $db         = Database::fetch("SELECT DATABASE() d")['d'] ?? 'unknown';
    $mode       = $dataOnly ? 'Data Only' : ($noData ? 'Structure Only' : 'Full');
    $baseStamp  = date('Ymd_His');
    $baseLabel  = "backup_{$label}_{$baseStamp}";

    // Detect which objects are VIEWs (must use DROP VIEW, not DROP TABLE)
    $viewSet = [];
    $typeRows = $pdo->query(
        "SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($typeRows as $tr) {
        if ($tr['TABLE_TYPE'] === 'VIEW') $viewSet[$tr['TABLE_NAME']] = true;
    }

    // ── Part file management ────────────────────────────────────────
    $partFiles = [];   // [{filename, path}]
    $partNum   = 1;
    $partBytes = 0;
    $f         = null;

    $startPart = function() use (
        &$f, &$partNum, &$partBytes, &$partFiles,
        $SM_BACKUP_DIR, $baseLabel, $splitBytes, $db, $mode, $tables
    ) {
        // Close previous part
        if ($f !== null) {
            fwrite($f, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            fclose($f);
            $partNum++;
        }
        $sfx   = $splitBytes > 0 ? '_part' . str_pad($partNum, 3, '0', STR_PAD_LEFT) : '';
        $fname = "{$baseLabel}{$sfx}.sql";
        $fpath = $SM_BACKUP_DIR . $fname;
        $nf    = fopen($fpath, 'wb');
        if (!$nf) throw new Exception("Cannot create {$fname} — check /backups/ permissions");
        $partFiles[] = ['filename' => $fname, 'path' => $fpath];
        $f         = $nf;
        $partBytes = 0;

        $h  = "-- ================================================================\n";
        $h .= "-- ChemBot Database Backup\n";
        $h .= "-- Generated : " . date('Y-m-d H:i:s T') . "\n";
        $h .= "-- Database  : {$db}  |  Mode: {$mode}  |  Tables: " . count($tables) . "\n";
        if ($splitBytes > 0) $h .= "-- Part      : Part {$partNum}\n";
        $h .= "-- ================================================================\n\n";
        $h .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n\n";
        fwrite($f, $h);
        $partBytes += strlen($h);
    };

    $startPart();

    foreach ($tables as $table) {
        $hdr = "-- ── {$table} ──────────────\n";
        fwrite($f, $hdr);
        $partBytes += strlen($hdr);

        $isView = isset($viewSet[$table]);

        if (!$dataOnly) {
            $drop = $isView
                ? "DROP VIEW IF EXISTS `{$table}`;\n"
                : "DROP TABLE IF EXISTS `{$table}`;\n";
            fwrite($f, $drop);
            $partBytes += strlen($drop);
            $cr  = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $ddl = $cr[1] . ";\n\n";
            fwrite($f, $ddl);
            $partBytes += strlen($ddl);
        }

        if (!$noData && !$isView) {
            $stmt    = $pdo->query("SELECT * FROM `{$table}`");
            $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($allRows)) {
                $cols = '`' . implode('`, `', array_keys($allRows[0])) . '`';
                foreach (array_chunk($allRows, 200) as $chunk) {
                    // Build INSERT block
                    $block = "INSERT INTO `{$table}` ({$cols}) VALUES\n";
                    $last  = count($chunk) - 1;
                    foreach ($chunk as $ri => $row) {
                        $vals = array_map(function ($v) use ($pdo) {
                            if ($v === null) return 'NULL';
                            if (is_numeric($v) && !preg_match('/^0\d/', $v)) return $v;
                            return $pdo->quote($v);
                        }, array_values($row));
                        $block .= '  (' . implode(', ', $vals) . ')' . ($ri === $last ? ";\n" : ",\n");
                    }
                    // Split when block would overflow the target size (leave ≥ 4 KB headroom)
                    if ($splitBytes > 0 && $partBytes > 4096 && ($partBytes + strlen($block)) > $splitBytes) {
                        $startPart();
                        $cont = "-- (data continued from previous part)\n\n";
                        fwrite($f, $cont);
                        $partBytes += strlen($cont);
                    }
                    fwrite($f, $block . "\n");
                    $partBytes += strlen($block) + 1;
                }
            }
        }

        fwrite($f, "\n");
        $partBytes++;
    }

    fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($f);

    // ── Build result list ────────────────────────────────────────────
    $resultParts = [];
    foreach ($partFiles as $p) {
        $sz = file_exists($p['path']) ? filesize($p['path']) : 0;
        $resultParts[] = ['filename' => $p['filename'], 'size_fmt' => sm_fmt($sz), 'size' => $sz];
    }

    // ── ZIP all parts when there are multiple ────────────────────────
    $zipFilename = null;
    $zipSizeFmt  = null;
    if (count($resultParts) > 1 && class_exists('ZipArchive')) {
        $zipFilename = "{$baseLabel}_all_parts.zip";
        $zipPath     = $SM_BACKUP_DIR . $zipFilename;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($partFiles as $p) { $zip->addFile($p['path'], $p['filename']); }
            $zip->close();
            $zipSizeFmt = sm_fmt(filesize($zipPath));
        } else {
            $zipFilename = null;
        }
    }

    return [
        'parts'    => $resultParts,
        'total'    => count($resultParts),
        'zip'      => $zipFilename,
        'zip_size' => $zipSizeFmt,
        // Backward-compat single-file fields
        'filename' => $zipFilename ?? $resultParts[0]['filename'],
        'size_fmt' => $zipSizeFmt  ?? $resultParts[0]['size_fmt'],
        'tables'   => count($tables),
    ];
}

function sm_backupList(): array {
    global $SM_BACKUP_DIR;
    sm_ensureBackupDir();
    $files = array_merge(
        glob($SM_BACKUP_DIR . '*.sql') ?: [],
        glob($SM_BACKUP_DIR . '*.zip') ?: []
    );
    $list = [];
    foreach ($files as $f) {
        $sz   = filesize($f);
        $name = basename($f);
        $list[] = [
            'filename' => $name,
            'size_fmt' => sm_fmt($sz),
            'size'     => $sz,
            'created'  => date('Y-m-d H:i:s', filemtime($f)),
            'mtime'    => filemtime($f),
            'type'     => str_ends_with($name, '.zip') ? 'zip' : 'sql',
        ];
    }
    usort($list, fn($a, $b) => $b['mtime'] - $a['mtime']);
    return $list;
}

function sm_safeBackupFilename(string $f): string {
    $f = basename($f);
    if (!preg_match('/^backup_[a-zA-Z0-9_]+\.(sql|zip)$/', $f)) throw new Exception('Invalid filename');
    return $f;
}

function sm_deleteBackup(string $filename): void {
    global $SM_BACKUP_DIR;
    $filename = sm_safeBackupFilename($filename);
    $path = $SM_BACKUP_DIR . $filename;
    if (!file_exists($path)) throw new Exception('File not found');
    unlink($path);
}

function sm_downloadBackup(string $filename): void {
    global $SM_BACKUP_DIR;
    $filename = sm_safeBackupFilename($filename);
    $path = $SM_BACKUP_DIR . $filename;
    if (!file_exists($path)) { http_response_code(404); exit('Not found'); }
    $mime = str_ends_with($filename, '.zip') ? 'application/zip' : 'application/octet-stream';
    header("Content-Type: {$mime}");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($path);
}

function sm_importSQL(string $tmpPath, string $origName): array {
    if (!preg_match('/\.sql$/i', $origName)) throw new Exception('Only .sql files are allowed');
    if (filesize($tmpPath) > 100 * 1024 * 1024) throw new Exception('File exceeds 100 MB limit');
    set_time_limit(300);
    $pdo = Database::getInstance();
    $sql = file_get_contents($tmpPath);
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0; SET NAMES utf8mb4");
    $statements = preg_split('/;\s*\n/', $sql);
    $executed = 0; $errors = [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
        try { $pdo->exec($stmt); $executed++; }
        catch (PDOException $e) { $errors[] = substr($e->getMessage(), 0, 150); if (count($errors) >= 10) break; }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    return ['executed' => $executed, 'errors' => count($errors), 'error_list' => $errors];
}

/* ─── Backup download (must run before isAjax — sends binary output) ─────── */

if ($action === 'backup_download') {
    if ($role !== 'admin') { http_response_code(403); exit('Forbidden'); }
    sm_downloadBackup($_GET['file'] ?? '');
    exit;
}

/* ─── AJAX dispatch ──────────────────────────────────────────────────────── */

if (isAjax()) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($action) {
            case 'get_stats':  echo json_encode(['success' => true, 'data' => sm_stats()]); break;
            case 'get_db':     echo json_encode(['success' => true, 'data' => sm_dbStats()]); break;
            case 'get_health': echo json_encode(['success' => true, 'data' => sm_health()]); break;
            case 'get_logs':
                echo json_encode(['success' => true, 'data' => sm_logs(
                    $_GET['level'] ?? 'all',
                    min(200, max(20, (int)($_GET['limit'] ?? 60))),
                    trim($_GET['q'] ?? '')
                )]);
                break;
            case 'clear_logs':
                $r = sm_clearLogs();
                echo json_encode(['success' => true] + $r);
                break;
            case 'backup_export':
                if ($role !== 'admin') throw new Exception('Forbidden');
                $opts = json_decode(file_get_contents('php://input'), true) ?: [];
                $r = sm_exportSQL($opts);
                echo json_encode(['success' => true] + $r);
                break;
            case 'backup_list':
                echo json_encode(['success' => true, 'data' => sm_backupList()]);
                break;
            case 'backup_delete':
                if ($role !== 'admin') throw new Exception('Forbidden');
                $body = json_decode(file_get_contents('php://input'), true) ?: [];
                sm_deleteBackup($body['filename'] ?? '');
                echo json_encode(['success' => true]);
                break;
            case 'backup_import':
                if ($role !== 'admin') throw new Exception('Forbidden');
                if (empty($_FILES['sql_file'])) throw new Exception('No file uploaded');
                $f = $_FILES['sql_file'];
                if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error: ' . $f['error']);
                $r = sm_importSQL($f['tmp_name'], $f['name']);
                echo json_encode(['success' => true] + $r);
                break;
            case 'backup_tables':
                echo json_encode(['success' => true, 'data' => sm_getAllTableNames()]);
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ─── Pre-render ─────────────────────────────────────────────────────────── */

$H  = sm_health();
$DB = sm_dbStats();
$ST = sm_stats();

$memPct   = $H['mem_pct'];
$memColor = $memPct > 80 ? '#dc2626' : ($memPct > 60 ? '#d97706' : '#16a34a');

// Overall health status
$sysStatus = 'ok';
if (!$ST['db_ok'] || !$H['db_ok'])  $sysStatus = 'err';
elseif ($ST['alerts'] > 5 || $memPct > 80) $sysStatus = 'warn';

$statusLabel = match($sysStatus) {
    'ok'   => $TH ? 'ระบบปกติ'    : 'Healthy',
    'warn' => $TH ? 'ต้องตรวจสอบ' : 'Attention',
    'err'  => $TH ? 'พบปัญหา'     : 'Issue Detected',
};
$statusColor = match($sysStatus) {
    'ok' => '#22c55e', 'warn' => '#f59e0b', 'err' => '#ef4444',
};

Layout::head($TH ? 'ตรวจสอบระบบ' : 'System Monitor');
?>
<style>
:root{
  --sm:#1e3a5f;--sm-r:14px;--sm-rs:10px;
  --sm-sh:0 1px 4px rgba(0,0,0,.06);--sm-shm:0 6px 24px rgba(0,0,0,.11);
}

/* ── Hero ─────────────────────────────────────────────────────────────── */
.sm-hero{
  background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1d4ed8 100%);
  border-radius:var(--sm-r);padding:24px 28px;color:#fff;
  display:flex;align-items:center;gap:20px;margin-bottom:20px;
  position:relative;overflow:hidden;
}
.sm-hero::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat
}
.sm-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.14);
  backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;
  font-size:22px;flex-shrink:0;position:relative;}
.sm-hero-info{position:relative;flex:1;min-width:0}
.sm-hero-info h2{font-size:20px;font-weight:800;margin:0 0 4px}
.sm-hero-info p{font-size:12px;opacity:.72;margin:0 0 10px}
.sm-hero-pills{display:flex;gap:7px;flex-wrap:wrap}
.sm-hero-pill{
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;
  background:rgba(255,255,255,.14);backdrop-filter:blur(4px);
  border:1px solid rgba(255,255,255,.18);
}
.sm-hero-pill .dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.sm-hero-meta{margin-left:auto;display:flex;gap:24px;flex-shrink:0;position:relative}
.sm-hero-c{text-align:center}
.sm-hero-c .v{font-size:28px;font-weight:900;line-height:1}
.sm-hero-c .lb{font-size:10px;opacity:.65;margin-top:3px;text-transform:uppercase;letter-spacing:.5px}
.sm-hero-sep{width:1px;background:rgba(255,255,255,.18)}

/* ── Stats ────────────────────────────────────────────────────────────── */
.sm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px}
.sm-stat{
  background:#fff;border-radius:var(--sm-rs);padding:14px 16px;
  display:flex;align-items:center;gap:12px;
  box-shadow:var(--sm-sh);border:1.5px solid var(--border);
  cursor:pointer;transition:all .18s;position:relative;overflow:hidden;
}
.sm-stat::before{
  content:'';position:absolute;top:0;right:0;width:52px;height:52px;
  border-radius:0 var(--sm-rs) 0 52px;opacity:.05;background:currentColor;pointer-events:none;
}
.sm-stat:hover{transform:translateY(-2px);box-shadow:var(--sm-shm)}
.sm-stat:active{transform:translateY(-1px)}
.sm-stat-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:14px;flex-shrink:0}
.sm-stat-v{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.sm-stat-l{font-size:10px;color:var(--c3);margin-top:2px}
.sm-stat-pulse{animation:sm-pulse 2s ease-in-out infinite}
@keyframes sm-pulse{0%,100%{opacity:1}50%{opacity:.5}}

/* ── Layout ───────────────────────────────────────────────────────────── */
.sm-main{display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start;margin-bottom:16px}
.sm-col{display:flex;flex-direction:column;gap:16px}

/* ── Card ─────────────────────────────────────────────────────────────── */
.sm-card{background:#fff;border-radius:var(--sm-r);box-shadow:var(--sm-sh);border:1.5px solid var(--border);overflow:hidden}
.sm-card-hdr{
  padding:12px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;
  background:linear-gradient(to bottom,#fafafa,#f5f7fa);
}
.sm-card-hdr-title{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--c1);flex:1}
.sm-card-hdr-ic{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.sm-card-body{padding:14px 16px}

/* ── Logs ─────────────────────────────────────────────────────────────── */
.sm-log-toolbar{padding:10px 14px;border-bottom:1px solid var(--border);display:flex;gap:7px;align-items:center;flex-wrap:wrap;background:#fafafa}
.sm-search{flex:1;min-width:120px;height:32px;padding:0 10px 0 32px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:inherit;color:var(--c1);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;transition:border-color .15s}
.sm-search:focus{outline:none;border-color:#3b82f6}
.sm-lvl-sel{height:32px;padding:0 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:inherit;color:var(--c1);background:#fff;cursor:pointer;transition:border-color .15s}
.sm-lvl-sel:focus{outline:none;border-color:#3b82f6}
.sm-lim-sel{height:32px;padding:0 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:inherit;color:var(--c1);background:#fff;cursor:pointer}
.sm-icon-btn{width:32px;height:32px;border:1.5px solid var(--border);border-radius:8px;background:#fff;color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .15s;flex-shrink:0}
.sm-icon-btn:hover{border-color:#3b82f6;color:#3b82f6}
.sm-icon-btn.spin i{animation:fa-spin 1s linear infinite}
.sm-refresh-cd{font-size:10px;color:var(--c3);white-space:nowrap;margin-left:auto}
.sm-log-body{max-height:420px;overflow-y:auto;padding:8px}
.sm-log-foot{padding:8px 14px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#fafafa}
.sm-log-count{font-size:11px;color:var(--c3)}

.sm-log-entry{
  display:flex;align-items:flex-start;gap:8px;
  padding:7px 10px;border-radius:8px;margin-bottom:4px;
  font-size:11.5px;border-left:3px solid transparent;
  background:#f8fafc;transition:background .1s;cursor:pointer;
}
.sm-log-entry:last-child{margin-bottom:0}
.sm-log-entry:hover{background:#f1f5f9}
.sm-log-entry.DEBUG   {border-left-color:#06b6d4}
.sm-log-entry.INFO    {border-left-color:#10b981}
.sm-log-entry.WARNING {border-left-color:#f59e0b;background:#fffbeb}
.sm-log-entry.WARNING:hover{background:#fef3c7}
.sm-log-entry.ERROR   {border-left-color:#ef4444;background:#fef2f2}
.sm-log-entry.ERROR:hover{background:#fee2e2}
.sm-log-entry.CRITICAL{border-left-color:#dc2626;background:#fee2e2}
.sm-log-meta{display:flex;align-items:center;gap:5px;flex-shrink:0;padding-top:1px}
.sm-log-time{font-size:10px;color:var(--c3);white-space:nowrap;font-family:'Consolas','Monaco',monospace}
.sm-log-badge{display:inline-block;padding:1px 6px;border-radius:4px;font-size:9.5px;font-weight:800;letter-spacing:.3px}
.sm-log-badge.DEBUG   {background:#cffafe;color:#0891b2}
.sm-log-badge.INFO    {background:#dcfce7;color:#16a34a}
.sm-log-badge.WARNING {background:#fef3c7;color:#d97706}
.sm-log-badge.ERROR   {background:#fee2e2;color:#dc2626}
.sm-log-badge.CRITICAL{background:#fee2e2;color:#991b1b}
.sm-log-msg{color:var(--c1);font-family:'Consolas','Monaco',monospace;font-size:11px;flex:1;word-break:break-word;line-height:1.45}
.sm-log-copy{opacity:0;font-size:10px;color:var(--c3);background:none;border:none;cursor:pointer;padding:2px 5px;border-radius:4px;transition:all .15s;flex-shrink:0}
.sm-log-entry:hover .sm-log-copy{opacity:1}
.sm-log-copy:hover{background:#e2e8f0;color:var(--c1)}

/* ── Health / Info rows ───────────────────────────────────────────────── */
.sm-info-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:12px;gap:12px}
.sm-info-row:last-child{border-bottom:none}
.sm-info-k{color:var(--c2);flex-shrink:0}
.sm-info-v{color:var(--c1);font-weight:600;font-family:'Consolas','Monaco',monospace;font-size:11.5px;text-align:right;word-break:break-all}
.sm-info-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px}
.sm-info-ok  {background:#dcfce7;color:#16a34a}
.sm-info-warn{background:#fef3c7;color:#d97706}
.sm-info-err {background:#fee2e2;color:#dc2626}

/* Memory bar */
.sm-mem-bar{height:6px;background:#e2e8f0;border-radius:6px;overflow:hidden;margin-top:4px}
.sm-mem-fill{height:100%;border-radius:6px;transition:width .4s}

/* ── Quick Actions ────────────────────────────────────────────────────── */
.sm-action-btn{
  display:flex;align-items:center;gap:10px;padding:11px 14px;
  border-radius:10px;border:1.5px solid var(--border);background:#fff;
  text-decoration:none;color:var(--c1);font-size:13px;font-weight:600;
  transition:all .15s;cursor:pointer;font-family:inherit;width:100%;text-align:left;
}
.sm-action-btn:hover{border-color:currentColor;background:var(--bg);transform:translateX(3px)}
.sm-action-btn .sa-ic{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.sm-action-btn .sa-arr{margin-left:auto;font-size:11px;color:var(--c3)}
.sm-action-btn.danger{color:#dc2626;border-color:#fecaca}
.sm-action-btn.danger:hover{background:#fef2f2;border-color:#f87171}

/* ── DB Tables section ────────────────────────────────────────────────── */
.sm-db-section{background:#fff;border-radius:var(--sm-r);box-shadow:var(--sm-sh);border:1.5px solid var(--border);overflow:hidden}
.sm-db-toggle{display:flex;align-items:center;gap:8px;padding:12px 16px;cursor:pointer;user-select:none;background:linear-gradient(to bottom,#fafafa,#f5f7fa);border-bottom:1px solid transparent;transition:border-color .15s}
.sm-db-toggle:hover{background:#f1f5f9}
.sm-db-toggle.open{border-bottom-color:var(--border)}
.sm-db-content{display:none}
.sm-db-content.open{display:block}
.sm-db-table{width:100%;border-collapse:collapse;font-size:12px}
.sm-db-table th{padding:8px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--border);background:#fafafa}
.sm-db-table th:last-child,.sm-db-table td:last-child{text-align:right}
.sm-db-table td{padding:9px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.sm-db-table tr:last-child td{border-bottom:none}
.sm-db-table tr:hover td{background:#f8fafc}
.sm-db-tname{font-family:'Consolas','Monaco',monospace;font-size:11.5px;color:var(--c1);font-weight:600}
.sm-db-rows{color:var(--c2)}
.sm-db-size-bar{display:flex;align-items:center;gap:8px;justify-content:flex-end}
.sm-db-bar{width:60px;height:5px;background:#e2e8f0;border-radius:5px;overflow:hidden;flex-shrink:0}
.sm-db-bar-fill{height:100%;background:#3b82f6;border-radius:5px}
.sm-db-kb{color:var(--c2);font-size:11px;white-space:nowrap;min-width:55px;text-align:right}

/* ── Toast ────────────────────────────────────────────────────────────── */
.sm-toast{position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;
  padding:11px 18px;border-radius:10px;font-size:13px;font-weight:500;
  box-shadow:0 4px 20px rgba(0,0,0,.2);z-index:9999;
  display:flex;align-items:center;gap:8px;
  transform:translateY(80px);opacity:0;transition:transform .25s,opacity .25s;max-width:320px}
.sm-toast.show{transform:none;opacity:1}
.sm-toast.ok {background:#064e3b}
.sm-toast.err{background:#7f1d1d}
.sm-toast.warn{background:#78350f}

/* ── Empty ────────────────────────────────────────────────────────────── */
.sm-empty{text-align:center;padding:40px 20px;color:var(--c3)}
.sm-empty i{font-size:32px;opacity:.35;display:block;margin-bottom:10px}
.sm-empty p{margin:0;font-size:13px}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media(max-width:960px){
  .sm-main{grid-template-columns:1fr}
  .sm-hero-meta{display:none}
}
@media(max-width:640px){
  .sm-stats{display:flex;overflow-x:auto;gap:8px;padding-bottom:6px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .sm-stats::-webkit-scrollbar{display:none}
  .sm-stat{min-width:130px;flex-shrink:0}
  .sm-hero{padding:18px 20px}
}

/* ── Backup section ───────────────────────────────────────────────────── */
.sm-bk-section{margin-top:16px}
.sm-bk-body{padding:16px}
.sm-bk-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
@media(max-width:960px){.sm-bk-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.sm-bk-grid{grid-template-columns:1fr}}

.sm-bk-panel{border:1.5px solid var(--border);border-radius:12px;overflow:hidden}
.sm-bk-panel-hdr{
  font-size:12px;font-weight:700;color:var(--c1);
  padding:10px 14px;background:linear-gradient(to bottom,#fafafa,#f5f7fa);
  border-bottom:1px solid var(--border);letter-spacing:.2px;
}
.sm-bk-panel-body{padding:14px}
.sm-bk-panel-sub{font-size:10.5px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin:12px 0 6px}

/* Export presets */
.sm-bk-presets{display:grid;grid-template-columns:1fr 1fr 1fr;gap:7px;padding:14px}
.sm-bk-preset{
  display:flex;flex-direction:column;align-items:center;gap:5px;
  padding:12px 8px;border:1.5px solid var(--border);border-radius:10px;
  background:#fff;cursor:pointer;font-family:inherit;font-size:11px;font-weight:700;
  color:var(--c2);transition:all .15s;text-align:center;line-height:1.3;
}
.sm-bk-preset:hover{border-color:#16a34a;color:#16a34a;background:#f0fdf4;transform:translateY(-1px)}
.sm-bk-preset.loading{opacity:.6;pointer-events:none}
.sm-bk-preset i{font-size:18px;display:block;margin-bottom:3px}
.sm-bk-preset.full  i{color:#2563eb}
.sm-bk-preset.data  i{color:#7c3aed}
.sm-bk-preset.schema i{color:#ea580c}

/* Table list */
.sm-bk-tbl-wrap{padding:0 14px 14px}
.sm-bk-tbl-list{max-height:160px;overflow-y:auto;border:1.5px solid var(--border);border-radius:8px;padding:4px}
.sm-bk-tbl-row{display:flex;align-items:center;gap:8px;padding:5px 6px;border-radius:6px;font-size:11.5px;cursor:pointer}
.sm-bk-tbl-row:hover{background:#f8fafc}
.sm-bk-tbl-row input[type=checkbox]{cursor:pointer;accent-color:#2563eb}
.sm-bk-tbl-nm{font-family:'Consolas','Monaco',monospace;flex:1;color:var(--c1);font-weight:500}
.sm-bk-tbl-rw{font-size:10px;color:var(--c3);white-space:nowrap}
.sm-bk-sel-actions{display:flex;gap:7px;padding:8px 14px 14px;align-items:center}
.sm-bk-sel-lnk{font-size:11px;color:#2563eb;cursor:pointer;text-decoration:underline;white-space:nowrap}
.sm-bk-exp-btn{
  display:flex;align-items:center;gap:6px;padding:8px 14px;
  background:#16a34a;color:#fff;border:none;border-radius:8px;
  font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;
  transition:background .15s;white-space:nowrap;
}
.sm-bk-exp-btn:hover{background:#15803d}
.sm-bk-exp-btn:disabled{opacity:.6;pointer-events:none}

/* Drop zone */
.sm-bk-drop-wrap{padding:14px}
.sm-bk-dropzone{
  position:relative;border:2px dashed #cbd5e1;border-radius:10px;
  padding:24px 16px;text-align:center;cursor:pointer;
  transition:border-color .15s,background .15s;color:var(--c3);
}
.sm-bk-dropzone i{font-size:28px;display:block;margin-bottom:8px;color:#94a3b8}
.sm-bk-dropzone p{margin:4px 0;font-size:12px}
.sm-bk-dropzone:hover,.sm-bk-dropzone.drag-over{border-color:#3b82f6;background:#eff6ff}
.sm-bk-dropzone.drag-over i{color:#3b82f6}
.sm-bk-import-warn{
  margin:0 14px 14px;padding:8px 12px;border-radius:8px;
  background:#fef3c7;color:#92400e;font-size:11px;display:flex;gap:6px;align-items:flex-start;
  border:1px solid #fde68a;line-height:1.45;
}
.sm-bk-import-warn i{flex-shrink:0;margin-top:1px}
.sm-bk-import-result{
  margin:0 14px 14px;padding:10px 12px;border-radius:8px;font-size:12px;border:1.5px solid;
}
.sm-bk-import-result.ok {background:#f0fdf4;color:#14532d;border-color:#86efac}
.sm-bk-import-result.err{background:#fef2f2;color:#7f1d1d;border-color:#fca5a5}
.sm-bk-import-result .ir-row{display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid rgba(0,0,0,.05)}
.sm-bk-import-result .ir-row:last-child{border-bottom:none;padding-top:4px}

/* History */
.sm-bk-hist-body{padding:10px}
.sm-bk-hist-empty{text-align:center;padding:28px 16px;color:var(--c3);font-size:12px}
.sm-bk-hist-empty i{font-size:24px;opacity:.3;display:block;margin-bottom:8px}
.sm-bk-file{
  display:flex;align-items:center;gap:8px;padding:8px 10px;
  border:1.5px solid var(--border);border-radius:9px;margin-bottom:7px;background:#fff;
  transition:border-color .12s;
}
.sm-bk-file:last-child{margin-bottom:0}
.sm-bk-file:hover{border-color:#3b82f6;background:#f8fafc}
.sm-bk-file-ic{width:34px;height:34px;border-radius:8px;background:#dbeafe;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sm-bk-file-info{flex:1;min-width:0}
.sm-bk-file-name{font-size:11.5px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'Consolas','Monaco',monospace}
.sm-bk-file-meta{font-size:10px;color:var(--c3);margin-top:2px}
.sm-bk-file-acts{display:flex;gap:5px;flex-shrink:0}
.sm-bk-file-btn{
  width:28px;height:28px;border:1.5px solid var(--border);border-radius:7px;
  background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:11px;color:var(--c2);transition:all .12s;
}
.sm-bk-file-btn:hover{border-color:#3b82f6;color:#3b82f6}
.sm-bk-file-btn.del:hover{border-color:#ef4444;color:#ef4444}

/* Progress */
.sm-bk-prog{padding:12px 14px;background:#f8fafc;border-top:1px solid var(--border)}
.sm-bk-prog-bar{height:6px;background:#e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:6px}
.sm-bk-prog-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#6366f1);border-radius:6px;transition:width .3s;animation:sm-bk-flow 1.5s linear infinite}
@keyframes sm-bk-flow{0%{background-position:0 0}100%{background-position:200px 0}}
.sm-bk-prog-txt{font-size:11px;color:var(--c3);text-align:center}

/* Split size bar */
.sm-bk-split-bar{
  display:flex;align-items:center;gap:8px;padding:9px 14px;
  background:#f8fafc;border-bottom:1px solid var(--border);font-size:11.5px;
}
.sm-bk-split-bar label{color:var(--c2);font-weight:600;flex-shrink:0}
.sm-bk-split-sel{
  height:28px;padding:0 8px;border:1.5px solid var(--border);border-radius:7px;
  font-size:11.5px;font-family:inherit;color:var(--c1);background:#fff;cursor:pointer;
  transition:border-color .15s;
}
.sm-bk-split-sel:focus{outline:none;border-color:#3b82f6}
.sm-bk-split-hint{font-size:10px;color:var(--c3);flex:1}

/* Result overlay */
.sm-bk-ov{
  position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);
  z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;
}
.sm-bk-ov-box{
  background:#fff;border-radius:18px;width:500px;max-width:94vw;
  max-height:88vh;overflow-y:auto;
  box-shadow:0 28px 72px rgba(0,0,0,.32);
}
.sm-bk-ov-hdr{
  padding:20px 22px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:12px;
  position:sticky;top:0;background:#fff;z-index:1;
}
.sm-bk-ov-close{
  margin-left:auto;width:30px;height:30px;border:none;
  background:#f1f5f9;border-radius:8px;cursor:pointer;
  font-size:13px;color:#64748b;transition:background .15s;
}
.sm-bk-ov-close:hover{background:#e2e8f0}
.sm-bk-ov-zip{
  padding:12px 22px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;
  display:flex;align-items:center;gap:12px;
}
.sm-bk-ov-zip-dl{
  padding:7px 14px;background:#16a34a;color:#fff;border:none;
  border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;
  font-family:inherit;white-space:nowrap;transition:background .15s;
}
.sm-bk-ov-zip-dl:hover{background:#15803d}
.sm-bk-ov-parts{padding:14px 22px}
.sm-bk-ov-part{
  display:flex;align-items:center;gap:8px;padding:8px 10px;
  border:1.5px solid var(--border);border-radius:9px;margin-bottom:7px;
}
.sm-bk-ov-part:last-child{margin-bottom:0}
.sm-bk-ov-part-ic{
  width:30px;height:30px;border-radius:8px;background:#dbeafe;color:#2563eb;
  display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;
}
.sm-bk-ov-part-dl{
  padding:5px 10px;background:#2563eb;color:#fff;border:none;border-radius:7px;
  font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;
}
.sm-bk-ov-part-dl:hover{background:#1d4ed8}
.sm-bk-ov-note{
  padding:10px 22px 16px;font-size:11px;color:#92400e;
  display:flex;gap:8px;align-items:flex-start;
}
.sm-bk-ov-note i{flex-shrink:0;margin-top:1px;color:#d97706}
</style>

<?php Layout::sidebar('system-monitor'); Layout::beginContent(); ?>

<!-- Toast -->
<div class="sm-toast" id="smToast"></div>

<!-- ── Hero ──────────────────────────────────────────────────────────── -->
<div class="sm-hero">
    <div class="sm-hero-ic"><i class="fas fa-server"></i></div>
    <div class="sm-hero-info">
        <h2><?= $TH ? 'ตรวจสอบระบบ' : 'System Monitor' ?></h2>
        <p><?= $TH ? 'ศูนย์กลางตรวจสอบประสิทธิภาพ สถานะ และบันทึกระบบ' : 'Central hub for system performance, health, and logs' ?></p>
        <div class="sm-hero-pills">
            <span class="sm-hero-pill">
                <span class="dot" style="background:<?= htmlspecialchars($statusColor) ?>"></span>
                <?= htmlspecialchars($statusLabel) ?>
            </span>
            <span class="sm-hero-pill"><i class="fab fa-php" style="font-size:11px"></i> PHP <?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?></span>
            <span class="sm-hero-pill"><i class="fas fa-database" style="font-size:10px"></i> <?= htmlspecialchars($H['mysql'] !== '?' ? 'MySQL '.$H['mysql'] : 'DB ?') ?></span>
            <span class="sm-hero-pill"><i class="fas fa-memory" style="font-size:10px"></i> <?= $H['mem_pct'] ?>%</span>
        </div>
    </div>
    <div class="sm-hero-meta">
        <div class="sm-hero-c"><div class="v" id="hmChemicals"><?= $ST['chemicals'] ?></div><div class="lb"><?= $TH ? 'สารเคมี' : 'Chemicals' ?></div></div>
        <div class="sm-hero-sep"></div>
        <div class="sm-hero-c"><div class="v" id="hmContainers"><?= $ST['containers'] ?></div><div class="lb"><?= $TH ? 'ภาชนะ' : 'Containers' ?></div></div>
        <div class="sm-hero-sep"></div>
        <div class="sm-hero-c"><div class="v" id="hmAlerts" style="color:<?= $ST['alerts'] > 0 ? '#f87171' : 'inherit' ?>"><?= $ST['alerts'] ?></div><div class="lb"><?= $TH ? 'แจ้งเตือน' : 'Alerts' ?></div></div>
    </div>
</div>

<!-- ── Stat Cards ────────────────────────────────────────────────────── -->
<div class="sm-stats">
    <div class="sm-stat" onclick="window.location='/v1/pages/settings.php'">
        <div class="sm-stat-ic" style="background:<?= $ST['db_ok']?'#dcfce7':'#fee2e2' ?>;color:<?= $ST['db_ok']?'#16a34a':'#dc2626' ?>">
            <i class="fas fa-<?= $ST['db_ok']?'database':'exclamation-triangle' ?>"></i>
        </div>
        <div>
            <div class="sm-stat-v" style="font-size:14px;font-weight:700;color:<?= $ST['db_ok']?'#16a34a':'#dc2626' ?>">
                <?= $ST['db_ok'] ? ($TH?'เชื่อมต่อ':'Online') : ($TH?'ขัดข้อง':'Offline') ?>
            </div>
            <div class="sm-stat-l">Database</div>
        </div>
    </div>
    <div class="sm-stat" onclick="window.location='/v1/pages/stock.php'">
        <div class="sm-stat-ic" style="background:#dbeafe;color:#2563eb"><i class="fas fa-flask"></i></div>
        <div>
            <div class="sm-stat-v" id="stChemicals"><?= $ST['chemicals'] ?></div>
            <div class="sm-stat-l"><?= $TH?'สารเคมี':'Chemicals' ?></div>
        </div>
    </div>
    <div class="sm-stat" onclick="window.location='/v1/pages/stock.php'">
        <div class="sm-stat-ic" style="background:#d1fae5;color:#059669"><i class="fas fa-box-open"></i></div>
        <div>
            <div class="sm-stat-v" id="stContainers"><?= $ST['containers'] ?></div>
            <div class="sm-stat-l"><?= $TH?'ภาชนะ (active)':'Containers' ?></div>
        </div>
    </div>
    <div class="sm-stat" onclick="window.location='/v1/pages/alerts.php'">
        <div class="sm-stat-ic" style="background:<?= $ST['alerts']>0?'#fee2e2':'#f1f5f9' ?>;color:<?= $ST['alerts']>0?'#dc2626':'#64748b' ?>">
            <i class="fas fa-bell<?= $ST['alerts']>0?' sm-stat-pulse':'' ?>"></i>
        </div>
        <div>
            <div class="sm-stat-v" id="stAlerts" style="color:<?= $ST['alerts']>0?'#dc2626':'var(--c1)' ?>"><?= $ST['alerts'] ?></div>
            <div class="sm-stat-l"><?= $TH?'แจ้งเตือนค้าง':'Pending Alerts' ?></div>
        </div>
    </div>
    <div class="sm-stat" onclick="window.location='/v1/pages/users.php'">
        <div class="sm-stat-ic" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-users"></i></div>
        <div>
            <div class="sm-stat-v" id="stUsers"><?= $ST['users'] ?></div>
            <div class="sm-stat-l"><?= $TH?'ผู้ใช้งาน':'Active Users' ?></div>
        </div>
    </div>
    <div class="sm-stat">
        <div class="sm-stat-ic" style="background:#fff7ed;color:#ea580c"><i class="fas fa-hdd"></i></div>
        <div>
            <div class="sm-stat-v" style="font-size:16px"><?= number_format($DB['size_mb'], 1) ?> <span style="font-size:11px;font-weight:500;color:var(--c3)">MB</span></div>
            <div class="sm-stat-l"><?= $TH?'ขนาดฐานข้อมูล · '.$DB['tables'].' tables':'DB Size · '.$DB['tables'].' tables' ?></div>
        </div>
    </div>
</div>

<!-- ── Main Grid ─────────────────────────────────────────────────────── -->
<div class="sm-main">

    <!-- Left: System Logs -->
    <div class="sm-card">
        <div class="sm-card-hdr">
            <div class="sm-card-hdr-title">
                <div class="sm-card-hdr-ic" style="background:#dbeafe;color:#2563eb"><i class="fas fa-list-alt"></i></div>
                <?= $TH ? 'บันทึกระบบ' : 'System Logs' ?>
            </div>
            <span class="sm-refresh-cd" id="smRefreshCd"></span>
        </div>
        <div class="sm-log-toolbar">
            <input class="sm-search" type="text" id="smLogSearch" placeholder="<?= $TH?'ค้นหา...':'Search logs...' ?>" autocomplete="off">
            <select class="sm-lvl-sel" id="smLogLevel">
                <option value="all"><?= $TH?'ทุกระดับ':'All Levels' ?></option>
                <option value="debug">Debug</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="critical">Critical</option>
            </select>
            <select class="sm-lim-sel" id="smLogLimit">
                <option value="30">30</option>
                <option value="60" selected>60</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
            <button class="sm-icon-btn" id="smRefreshBtn" onclick="loadLogs(true)" title="<?= $TH?'รีเฟรช':'Refresh' ?>">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div class="sm-log-body" id="smLogBody">
            <div class="sm-empty"><i class="fas fa-spinner fa-spin"></i><p><?= $TH?'กำลังโหลด...':'Loading...' ?></p></div>
        </div>
        <div class="sm-log-foot">
            <span class="sm-log-count" id="smLogCount">—</span>
            <?php if ($role === 'admin'): ?>
            <button class="sm-action-btn danger" style="width:auto;padding:6px 12px;font-size:11px" onclick="clearLogs()">
                <i class="fas fa-trash" style="font-size:11px"></i>
                <?= $TH?'ล้างล็อกเก่า (>7 วัน)':'Clear Old Logs (>7 days)' ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right column -->
    <div class="sm-col">

        <!-- System Health -->
        <div class="sm-card">
            <div class="sm-card-hdr">
                <div class="sm-card-hdr-title">
                    <div class="sm-card-hdr-ic" style="background:#d1fae5;color:#059669"><i class="fas fa-heartbeat"></i></div>
                    <?= $TH ? 'สุขภาพระบบ' : 'System Health' ?>
                </div>
                <span class="sm-info-badge <?= $H['db_ok']?'sm-info-ok':'sm-info-err' ?>">
                    <i class="fas fa-circle" style="font-size:6px"></i>
                    <?= $H['db_ok'] ? ($TH?'ปกติ':'Normal') : ($TH?'มีปัญหา':'Error') ?>
                </span>
            </div>
            <div class="sm-card-body">

                <!-- Memory usage bar -->
                <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border)">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span style="font-size:12px;font-weight:600;color:var(--c1)"><?= $TH?'หน่วยความจำ':'Memory Usage' ?></span>
                        <span style="font-size:11px;color:<?= htmlspecialchars($memColor) ?>;font-weight:700"><?= $H['mem_pct'] ?>%</span>
                    </div>
                    <div class="sm-mem-bar">
                        <div class="sm-mem-fill" style="width:<?= min(100,$H['mem_pct']) ?>%;background:<?= htmlspecialchars($memColor) ?>"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:10px;color:var(--c3)">
                        <span><?= $TH?'ใช้งาน':'Used' ?>: <?= htmlspecialchars($H['mem_use']) ?></span>
                        <span><?= $TH?'สูงสุด':'Peak' ?>: <?= htmlspecialchars($H['mem_peak']) ?></span>
                        <span><?= $TH?'ขีดจำกัด':'Limit' ?>: <?= htmlspecialchars($H['mem_lim']) ?></span>
                    </div>
                </div>

                <!-- PHP Info -->
                <div class="sm-info-row">
                    <span class="sm-info-k">PHP Version</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['php']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">SAPI</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['sapi']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k"><?= $TH?'เวลาทำงานสูงสุด':'Max Exec Time' ?></span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['max_exec']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">Upload Max</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['upload']) ?></span>
                </div>

                <!-- Server Info -->
                <div class="sm-info-row" style="margin-top:4px;padding-top:12px;border-top:1px solid var(--border)">
                    <span class="sm-info-k">Server</span>
                    <span class="sm-info-v" style="font-size:10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($H['server']) ?>"><?= htmlspecialchars($H['server']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">Hostname</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['host']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">Timezone</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['tz']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k"><?= $TH?'เวลาเซิร์ฟเวอร์':'Server Time' ?></span>
                    <span class="sm-info-v" id="smServerTime"><?= htmlspecialchars($H['now']) ?></span>
                </div>

                <!-- DB Info -->
                <div class="sm-info-row" style="margin-top:4px;padding-top:12px;border-top:1px solid var(--border)">
                    <span class="sm-info-k">MySQL Version</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['mysql']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">Database</span>
                    <span class="sm-info-v"><?= htmlspecialchars($DB['db_name']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k"><?= $TH?'ตาราง':'Tables' ?></span>
                    <span class="sm-info-v"><?= $DB['tables'] ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">DB Size</span>
                    <span class="sm-info-v"><?= number_format($DB['size_mb'], 2) ?> MB</span>
                </div>

                <!-- App Info -->
                <div class="sm-info-row" style="margin-top:4px;padding-top:12px;border-top:1px solid var(--border)">
                    <span class="sm-info-k">App Version</span>
                    <span class="sm-info-v"><?= htmlspecialchars($H['app_ver']) ?></span>
                </div>
                <div class="sm-info-row">
                    <span class="sm-info-k">Environment</span>
                    <span class="sm-info-v">
                        <span class="sm-info-badge <?= $H['env']==='production'?'sm-info-ok':'sm-info-warn' ?>">
                            <?= htmlspecialchars($H['env']) ?>
                        </span>
                    </span>
                </div>

            </div>
        </div>

        <!-- Quick Actions -->
        <?php if ($role === 'admin'): ?>
        <div class="sm-card">
            <div class="sm-card-hdr">
                <div class="sm-card-hdr-title">
                    <div class="sm-card-hdr-ic" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-bolt"></i></div>
                    <?= $TH ? 'การจัดการด่วน' : 'Quick Actions' ?>
                </div>
            </div>
            <div class="sm-card-body" style="display:flex;flex-direction:column;gap:8px">
                <a href="/v1/pages/settings.php" class="sm-action-btn" style="color:#2563eb">
                    <div class="sa-ic" style="background:#dbeafe;color:#2563eb"><i class="fas fa-sliders-h"></i></div>
                    <?= $TH?'ตั้งค่าระบบ':'System Settings' ?>
                    <i class="fas fa-chevron-right sa-arr"></i>
                </a>
                <a href="/v1/pages/users.php" class="sm-action-btn" style="color:#059669">
                    <div class="sa-ic" style="background:#d1fae5;color:#059669"><i class="fas fa-users-cog"></i></div>
                    <?= $TH?'จัดการผู้ใช้':'User Management' ?>
                    <i class="fas fa-chevron-right sa-arr"></i>
                </a>
                <a href="/v1/pages/alerts.php" class="sm-action-btn" style="color:#d97706">
                    <div class="sa-ic" style="background:#fef3c7;color:#d97706"><i class="fas fa-bell"></i></div>
                    <?= $TH?'จัดการแจ้งเตือน':'Alert Management' ?>
                    <?php if ($ST['alerts'] > 0): ?>
                    <span style="margin-left:auto;margin-right:4px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;padding:1px 7px;border-radius:10px"><?= $ST['alerts'] ?></span>
                    <?php else: ?>
                    <i class="fas fa-chevron-right sa-arr"></i>
                    <?php endif; ?>
                </a>
                <a href="/v1/pages/page-access.php" class="sm-action-btn" style="color:#e11d48">
                    <div class="sa-ic" style="background:#ffe4e6;color:#e11d48"><i class="fas fa-shield-alt"></i></div>
                    <?= $TH?'สิทธิ์การเข้าถึง':'Page Access Control' ?>
                    <i class="fas fa-chevron-right sa-arr"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── DB Tables ─────────────────────────────────────────────────────── -->
<?php if (!empty($DB['table_list'])): ?>
<div class="sm-db-section">
    <div class="sm-db-toggle" id="smDbToggle" onclick="toggleDbPanel()">
        <div class="sm-card-hdr-ic" style="background:#fff7ed;color:#ea580c"><i class="fas fa-table"></i></div>
        <span style="font-size:13px;font-weight:700;color:var(--c1)"><?= $TH?'ตารางฐานข้อมูล':'Database Tables' ?></span>
        <span style="font-size:11px;color:var(--c3);margin-left:6px"><?= $DB['tables'] ?> <?= $TH?'ตาราง':'tables' ?> · <?= number_format($DB['size_mb'],1) ?> MB</span>
        <i class="fas fa-chevron-down" id="smDbChev" style="margin-left:auto;color:var(--c3);font-size:11px;transition:transform .25s"></i>
    </div>
    <div class="sm-db-content" id="smDbContent">
        <?php
        $maxKb = max(1, array_reduce($DB['table_list'], fn($c, $r) => max($c, (float)$r['kb']), 0));
        ?>
        <table class="sm-db-table">
            <thead>
                <tr>
                    <th><?= $TH?'ชื่อตาราง':'Table Name' ?></th>
                    <th><?= $TH?'แถว (ประมาณ)':'Rows (est.)' ?></th>
                    <th><?= $TH?'ขนาด':'Size' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($DB['table_list'] as $t):
                    $pct = round((float)$t['kb'] / $maxKb * 100);
                    $kb  = (float)$t['kb'];
                    $sizeStr = $kb >= 1024 ? number_format($kb/1024,1).' MB' : $kb.' KB';
                ?>
                <tr>
                    <td><span class="sm-db-tname"><?= htmlspecialchars($t['nm']) ?></span></td>
                    <td class="sm-db-rows"><?= number_format((int)$t['rw']) ?></td>
                    <td>
                        <div class="sm-db-size-bar">
                            <div class="sm-db-bar"><div class="sm-db-bar-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="sm-db-kb"><?= $sizeStr ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Backup Management ───────────────────────────────────────────────── -->
<div class="sm-card sm-bk-section">
    <div class="sm-card-hdr">
        <div class="sm-card-hdr-title">
            <div class="sm-card-hdr-ic" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-database"></i></div>
            <?= $TH ? 'จัดการ Backup ฐานข้อมูล' : 'Database Backup & Restore' ?>
        </div>
        <span id="smBkStatusBadge"></span>
    </div>
    <div class="sm-bk-body">
        <div class="sm-bk-grid">

            <!-- ── Export ───────────── -->
            <div class="sm-bk-panel">
                <div class="sm-bk-panel-hdr"><i class="fas fa-download" style="margin-right:6px;color:#2563eb"></i><?= $TH?'ส่งออก / สำรองข้อมูล':'Export / Backup' ?></div>
                <!-- Split size selector -->
                <div class="sm-bk-split-bar">
                    <label for="smBkSplitSel"><i class="fas fa-cut" style="margin-right:4px;color:#7c3aed"></i><?= $TH?'แบ่งไฟล์ไม่เกิน:':'Split file at:' ?></label>
                    <select class="sm-bk-split-sel" id="smBkSplitSel">
                        <option value="0"><?= $TH?'ไม่แบ่ง (ไฟล์เดียว)':'No split (single file)' ?></option>
                        <option value="5">5 MB / part</option>
                        <option value="10" selected>10 MB / part</option>
                        <option value="20">20 MB / part</option>
                        <option value="50">50 MB / part</option>
                    </select>
                    <span class="sm-bk-split-hint"><?= $TH?'เหมาะสำหรับนำเข้า phpMyAdmin':'Recommended for phpMyAdmin import' ?></span>
                </div>
                <!-- Quick presets -->
                <div class="sm-bk-presets">
                    <button class="sm-bk-preset full" id="bkBtnFull" onclick="bkExport('full')" title="<?= $TH?'สำรองทั้งหมด (โครงสร้าง + ข้อมูล)':'Structure + Data' ?>">
                        <i class="fas fa-database"></i>
                        <?= $TH?'สำรองเต็ม':'Full Backup' ?>
                    </button>
                    <button class="sm-bk-preset data" id="bkBtnData" onclick="bkExport('data')" title="<?= $TH?'เฉพาะข้อมูล ไม่มีโครงสร้าง':'Data rows only, no CREATE TABLE' ?>">
                        <i class="fas fa-table"></i>
                        <?= $TH?'เฉพาะข้อมูล':'Data Only' ?>
                    </button>
                    <button class="sm-bk-preset schema" id="bkBtnSchema" onclick="bkExport('schema')" title="<?= $TH?'เฉพาะโครงสร้างตาราง':'CREATE TABLE only, no rows' ?>">
                        <i class="fas fa-sitemap"></i>
                        <?= $TH?'โครงสร้างอย่างเดียว':'Schema Only' ?>
                    </button>
                </div>
                <!-- Custom table picker -->
                <div class="sm-bk-tbl-wrap">
                    <div class="sm-bk-panel-sub"><?= $TH?'เลือกตาราง (Custom)':'Custom Table Selection' ?></div>
                    <div class="sm-bk-tbl-list" id="smBkTblList">
                        <div style="text-align:center;padding:16px;font-size:11px;color:var(--c3)"><i class="fas fa-spinner fa-spin"></i></div>
                    </div>
                    <div class="sm-bk-sel-actions">
                        <span class="sm-bk-sel-lnk" onclick="bkSelAll(true)"><?= $TH?'เลือกทั้งหมด':'All' ?></span>
                        <span class="sm-bk-sel-lnk" onclick="bkSelAll(false)"><?= $TH?'ยกเลิกทั้งหมด':'None' ?></span>
                        <span id="smBkSelCount" style="font-size:11px;color:var(--c3);flex:1;text-align:right">0 <?= $TH?'ตาราง':'tables' ?></span>
                    </div>
                    <div style="padding:0 0 14px">
                        <button class="sm-bk-exp-btn" id="smBkCustomExport" onclick="bkExportCustom()" style="width:100%;justify-content:center" disabled>
                            <i class="fas fa-download"></i> <?= $TH?'ส่งออกตารางที่เลือก':'Export Selected Tables' ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Import ───────────── -->
            <div class="sm-bk-panel">
                <div class="sm-bk-panel-hdr"><i class="fas fa-upload" style="margin-right:6px;color:#7c3aed"></i><?= $TH?'นำเข้า / กู้คืน':'Import / Restore' ?></div>
                <div class="sm-bk-drop-wrap">
                    <div class="sm-bk-dropzone" id="smBkDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p style="font-weight:600"><?= $TH?'ลาก & วาง ไฟล์ .sql ที่นี่':'Drag & drop .sql file here' ?></p>
                        <p style="opacity:.65;font-size:11px"><?= $TH?'หรือคลิกเพื่อเลือกไฟล์':'or click to browse' ?></p>
                        <input type="file" id="smBkFileInput" accept=".sql" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%">
                    </div>
                </div>
                <div id="smBkImportResult" style="display:none"></div>
                <div class="sm-bk-import-warn">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= $TH?'การนำเข้าจะเขียนทับข้อมูลเดิม กรุณา Backup ก่อนดำเนินการ!':'Import overwrites existing data. Always backup first!' ?></span>
                </div>
            </div>

            <!-- ── History ──────────── -->
            <div class="sm-bk-panel">
                <div class="sm-bk-panel-hdr" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="fas fa-history" style="margin-right:6px;color:#ea580c"></i><?= $TH?'ประวัติ Backup':'Backup History' ?></span>
                    <button class="sm-icon-btn" onclick="bkLoadHistory()" title="<?= $TH?'รีเฟรช':'Refresh' ?>" style="width:26px;height:26px;font-size:11px">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="sm-bk-hist-body" id="smBkHistory">
                    <div class="sm-bk-hist-empty"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>

        </div><!-- /sm-bk-grid -->
    </div>
</div>

<script>
/* ─── State ─────────────────────────────────────────────────────────── */
const TH = <?= $TH ? 'true' : 'false' ?>;
let smRefreshTimer = null;
let smCountdown    = 60;
let smSearchTimer  = null;

/* ─── Init ──────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    loadLogs();
    startAutoRefresh();
    tickServerTime();
});

/* ─── Toast ─────────────────────────────────────────────────────────── */
function smToast(msg, type = 'ok') {
    const t = document.getElementById('smToast');
    const ic = type === 'ok' ? 'fa-check-circle' : (type === 'warn' ? 'fa-exclamation-circle' : 'fa-times-circle');
    t.className = 'sm-toast ' + type;
    t.innerHTML = `<i class="fas ${ic}"></i> ${msg}`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3400);
}

/* ─── Auto-refresh ───────────────────────────────────────────────────── */
function startAutoRefresh() {
    smCountdown = 60;
    updateCountdown();
    smRefreshTimer = setInterval(() => {
        smCountdown--;
        updateCountdown();
        if (smCountdown <= 0) {
            loadLogs();
            smCountdown = 60;
        }
    }, 1000);
}

function updateCountdown() {
    const el = document.getElementById('smRefreshCd');
    if (el) el.textContent = TH ? `รีเฟรชใน ${smCountdown}s` : `Refresh in ${smCountdown}s`;
}

/* ─── Logs ───────────────────────────────────────────────────────────── */
function loadLogs(manual = false) {
    if (manual) { smCountdown = 60; updateCountdown(); }
    const level = document.getElementById('smLogLevel').value;
    const limit = document.getElementById('smLogLimit').value;
    const q     = document.getElementById('smLogSearch').value.trim();
    const btn   = document.getElementById('smRefreshBtn');

    btn.classList.add('spin');
    const url = `?action=get_logs&level=${encodeURIComponent(level)}&limit=${limit}&q=${encodeURIComponent(q)}`;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            btn.classList.remove('spin');
            const body  = document.getElementById('smLogBody');
            const count = document.getElementById('smLogCount');
            if (!d.success || !d.data) {
                body.innerHTML = `<div class="sm-empty"><i class="fas fa-exclamation-triangle"></i><p>Error loading logs</p></div>`;
                return;
            }
            const logs = d.data;
            const n    = logs.length;
            count.textContent = TH ? `${n} รายการ` : `${n} entries`;
            if (n === 0) {
                body.innerHTML = `<div class="sm-empty"><i class="fas fa-check-circle" style="color:#10b981"></i><p>${TH?'ไม่พบบันทึก':'No logs found'}</p></div>`;
                return;
            }
            body.innerHTML = logs.map(log => {
                const lv  = (log.level || 'INFO').toUpperCase();
                const msg = escH(log.message);
                const t   = escH(log.time);
                return `<div class="sm-log-entry ${lv}" onclick="copyLog('${escAttr(log.time+' ['+lv+'] '+log.message)}')">
                    <div class="sm-log-meta">
                        <span class="sm-log-badge ${lv}">${lv}</span>
                    </div>
                    <div>
                        <div class="sm-log-time">${t}</div>
                        <div class="sm-log-msg">${msg}</div>
                    </div>
                    <button class="sm-log-copy" onclick="event.stopPropagation();copyLog('${escAttr(log.time+' ['+lv+'] '+log.message)}')" title="${TH?'คัดลอก':'Copy'}">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('smRefreshBtn').classList.remove('spin');
            document.getElementById('smLogBody').innerHTML =
                `<div class="sm-empty"><i class="fas fa-wifi"></i><p>${TH?'ไม่สามารถโหลดได้':'Failed to load'}</p></div>`;
        });
}

/* Debounced search */
document.getElementById('smLogSearch').addEventListener('input', () => {
    clearTimeout(smSearchTimer);
    smSearchTimer = setTimeout(() => loadLogs(), 350);
});
document.getElementById('smLogLevel').addEventListener('change', () => loadLogs());
document.getElementById('smLogLimit').addEventListener('change', () => loadLogs());

/* ─── Copy log ───────────────────────────────────────────────────────── */
function copyLog(text) {
    navigator.clipboard?.writeText(text).then(() => smToast(TH ? 'คัดลอกแล้ว' : 'Copied to clipboard'));
}

/* ─── Clear logs ─────────────────────────────────────────────────────── */
function clearLogs() {
    const msg = TH ? 'ต้องการล้างล็อกที่เก่ากว่า 7 วันหรือไม่?' : 'Clear log files older than 7 days?';
    smConfirm(msg, () => {
        fetch('?action=clear_logs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    smToast((TH ? 'ล้างสำเร็จ: ' : 'Cleared: ') + (d.message || ''));
                    loadLogs();
                } else {
                    smToast(d.error || 'Error', 'err');
                }
            })
            .catch(() => smToast(TH ? 'เกิดข้อผิดพลาด' : 'Error clearing logs', 'err'));
    });
}

/* ─── Confirm dialog ─────────────────────────────────────────────────── */
function smConfirm(msg, cb) {
    const ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(4px);z-index:8000;display:flex;align-items:center;justify-content:center';
    ov.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:28px 28px 22px;width:380px;max-width:92vw;box-shadow:0 24px 64px rgba(0,0,0,.25)">
            <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:20px">
                <div style="width:44px;height:44px;border-radius:12px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px">${TH?'ยืนยันการดำเนินการ':'Confirm Action'}</div>
                    <div style="font-size:13px;color:#64748b;line-height:1.55">${escH(msg)}</div>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button id="smCfCancel" style="padding:9px 20px;border:1.5px solid #e2e8f0;background:#fff;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;color:#64748b">${TH?'ยกเลิก':'Cancel'}</button>
                <button id="smCfOk" style="padding:9px 20px;background:#dc2626;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;color:#fff">${TH?'ยืนยัน':'Confirm'}</button>
            </div>
        </div>`;
    document.body.appendChild(ov);
    const close = () => document.body.removeChild(ov);
    ov.addEventListener('click', e => { if (e.target === ov) close(); });
    document.getElementById('smCfCancel').onclick = close;
    document.getElementById('smCfOk').onclick = () => { close(); cb(); };
}

/* ─── DB Tables toggle ───────────────────────────────────────────────── */
function toggleDbPanel() {
    const toggle  = document.getElementById('smDbToggle');
    const content = document.getElementById('smDbContent');
    const chev    = document.getElementById('smDbChev');
    const open    = content.classList.toggle('open');
    toggle.classList.toggle('open', open);
    chev.style.transform = open ? 'rotate(180deg)' : '';
}

/* ─── Server clock tick ──────────────────────────────────────────────── */
function tickServerTime() {
    const el = document.getElementById('smServerTime');
    if (!el) return;
    let d = new Date(el.textContent.trim().replace(' ', 'T'));
    if (isNaN(d)) return;
    setInterval(() => {
        d = new Date(d.getTime() + 1000);
        const pad = n => String(n).padStart(2,'0');
        el.textContent = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }, 1000);
}

/* ─── Utilities ──────────────────────────────────────────────────────── */
function escH(t) {
    const d = document.createElement('div');
    d.textContent = String(t ?? '');
    return d.innerHTML;
}
function escAttr(t) {
    return String(t ?? '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

/* ─── Backup ─────────────────────────────────────────────────────────── */

// Load table list into checkboxes
async function bkLoadTables() {
    const el = document.getElementById('smBkTblList');
    try {
        const d = await fetch('?action=backup_tables', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
        if (!d.success || !d.data.length) { el.innerHTML = '<div style="padding:10px;font-size:11px;color:var(--c3)">No tables</div>'; return; }
        el.innerHTML = d.data.map(t => `
            <label class="sm-bk-tbl-row">
                <input type="checkbox" class="bk-tbl-cb" value="${escH(t.n)}" onchange="bkUpdateSelCount()">
                <span class="sm-bk-tbl-nm">${escH(t.n)}</span>
                <span class="sm-bk-tbl-rw">${Number(t.rw).toLocaleString()}</span>
            </label>`).join('');
        bkUpdateSelCount();
    } catch(e) { el.innerHTML = '<div style="padding:10px;font-size:11px;color:#dc2626">Failed to load tables</div>'; }
}

function bkUpdateSelCount() {
    const checked = document.querySelectorAll('.bk-tbl-cb:checked').length;
    const total   = document.querySelectorAll('.bk-tbl-cb').length;
    const el = document.getElementById('smBkSelCount');
    if (el) el.textContent = TH ? `${checked} / ${total} ตาราง` : `${checked} / ${total} tables`;
    const btn = document.getElementById('smBkCustomExport');
    if (btn) btn.disabled = checked === 0;
}

function bkSelAll(val) {
    document.querySelectorAll('.bk-tbl-cb').forEach(cb => cb.checked = val);
    bkUpdateSelCount();
}

// Get split_mb value from selector
function bkGetSplitMb() {
    const sel = document.getElementById('smBkSplitSel');
    return sel ? parseInt(sel.value, 10) : 0;
}

// Run a preset export: type = 'full' | 'data' | 'schema'
async function bkExport(type) {
    const btnMap  = { full: 'bkBtnFull', data: 'bkBtnData', schema: 'bkBtnSchema' };
    const iconMap = { full: 'fa-database', data: 'fa-table', schema: 'fa-sitemap' };
    const btn = document.getElementById(btnMap[type]);
    if (btn) { btn.classList.add('loading'); btn.querySelector('i').className = 'fas fa-spinner fa-spin'; }
    const opts = {
        label:    type,
        data_only: type === 'data',
        no_data:   type === 'schema',
        split_mb:  bkGetSplitMb(),
    };
    try {
        const d = await fetch('?action=backup_export', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify(opts),
        }).then(r => r.json());
        if (!d.success) throw new Error(d.error || 'Export failed');
        bkShowResult(d);
    } catch(e) {
        smToast(e.message, 'err');
    } finally {
        if (btn) {
            btn.classList.remove('loading');
            btn.querySelector('i').className = `fas ${iconMap[type]}`;
        }
    }
}

// Export only selected custom tables
async function bkExportCustom() {
    const tables = [...document.querySelectorAll('.bk-tbl-cb:checked')].map(cb => cb.value);
    if (!tables.length) return;
    const btn = document.getElementById('smBkCustomExport');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (TH ? 'กำลังส่งออก...' : 'Exporting...');
    try {
        const d = await fetch('?action=backup_export', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify({ label: 'custom', tables, split_mb: bkGetSplitMb() }),
        }).then(r => r.json());
        if (!d.success) throw new Error(d.error || 'Export failed');
        bkShowResult(d);
    } catch(e) {
        smToast(e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-download"></i> ${TH ? 'ส่งออกตารางที่เลือก' : 'Export Selected Tables'}`;
        bkUpdateSelCount();
    }
}

// Show export result — single file: toast+auto-download; multi-part: result dialog
function bkShowResult(d) {
    bkLoadHistory();
    if (d.total === 1) {
        smToast(`${TH ? 'Backup สำเร็จ' : 'Backup done'}: ${d.parts[0].filename} (${d.parts[0].size_fmt})`, 'ok');
        bkDownload(d.parts[0].filename);
        return;
    }
    // Multi-part result dialog
    const partsHtml = d.parts.map((p, i) => `
        <div class="sm-bk-ov-part">
            <div class="sm-bk-ov-part-ic"><i class="fas fa-file-code"></i></div>
            <div style="flex:1;min-width:0">
                <div style="font-size:11px;font-weight:700;font-family:'Consolas','Monaco',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escH(p.filename)}">${escH(p.filename)}</div>
                <div style="font-size:10px;color:#64748b">${p.size_fmt}</div>
            </div>
            <button class="sm-bk-ov-part-dl" onclick="bkDownload('${escAttr(p.filename)}')">
                <i class="fas fa-download"></i> Part ${i + 1}
            </button>
        </div>`).join('');

    const ov = document.createElement('div');
    ov.className = 'sm-bk-ov';
    ov.innerHTML = `
        <div class="sm-bk-ov-box">
            <div class="sm-bk-ov-hdr">
                <div style="width:42px;height:42px;border-radius:11px;background:#f0fdf4;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div style="font-size:15px;font-weight:800;color:#0f172a">${TH ? 'Export สำเร็จ' : 'Export Complete'}</div>
                    <div style="font-size:12px;color:#64748b">${d.total} ${TH ? 'ไฟล์' : 'files'} &bull; ${d.tables} ${TH ? 'ตาราง' : 'tables'}</div>
                </div>
                <button class="sm-bk-ov-close" onclick="this.closest('.sm-bk-ov').remove()">✕</button>
            </div>
            ${d.zip ? `
            <div class="sm-bk-ov-zip">
                <i class="fas fa-file-archive" style="font-size:20px;color:#16a34a;flex-shrink:0"></i>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:700;color:#14532d">${TH ? 'ดาวน์โหลดทั้งหมด (ZIP)' : 'Download All Parts (.zip)'}</div>
                    <div style="font-size:11px;color:#15803d;font-family:monospace">${escH(d.zip)} &bull; ${d.zip_size}</div>
                </div>
                <button class="sm-bk-ov-zip-dl" onclick="bkDownload('${escAttr(d.zip)}')">
                    <i class="fas fa-download"></i> ZIP
                </button>
            </div>` : ''}
            <div class="sm-bk-ov-parts">
                <div style="font-size:10.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">${TH ? 'ดาวน์โหลดแต่ละ Part' : 'Individual Parts'}</div>
                ${partsHtml}
            </div>
            <div class="sm-bk-ov-note">
                <i class="fas fa-info-circle"></i>
                <span>${TH
                    ? `นำเข้าไฟล์ตามลำดับ Part 001 → 002 → ... ใน phpMyAdmin เพื่อหลีกเลี่ยง script timeout`
                    : `Import files in order: Part 001 → 002 → … in phpMyAdmin to avoid script timeout.`
                }</span>
            </div>
        </div>`;
    document.body.appendChild(ov);
    ov.addEventListener('click', e => { if (e.target === ov) ov.remove(); });
}

// Load backup history
async function bkLoadHistory() {
    const el = document.getElementById('smBkHistory');
    el.innerHTML = '<div class="sm-bk-hist-empty"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const d = await fetch('?action=backup_list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
        if (!d.success || !d.data.length) {
            el.innerHTML = `<div class="sm-bk-hist-empty"><i class="fas fa-archive"></i><br>${TH ? 'ยังไม่มีไฟล์ Backup' : 'No backups yet'}</div>`;
            return;
        }
        el.innerHTML = d.data.map(f => {
            const isZip = f.type === 'zip';
            const ic  = isZip ? 'fa-file-archive' : 'fa-file-code';
            const icBg = isZip ? '#dcfce7' : '#dbeafe';
            const icCl = isZip ? '#16a34a' : '#2563eb';
            // Highlight part files with a subtle badge
            const isPart = /part\d{3}/.test(f.filename);
            const badge  = isPart ? `<span style="font-size:9px;font-weight:800;background:#f3e8ff;color:#7c3aed;padding:1px 5px;border-radius:4px;margin-left:4px">PART</span>` : '';
            return `
            <div class="sm-bk-file">
                <div class="sm-bk-file-ic" style="background:${icBg};color:${icCl}"><i class="fas ${ic}"></i></div>
                <div class="sm-bk-file-info">
                    <div class="sm-bk-file-name" title="${escH(f.filename)}">${escH(f.filename)}${badge}</div>
                    <div class="sm-bk-file-meta">${escH(f.size_fmt)} &bull; ${escH(f.created)}</div>
                </div>
                <div class="sm-bk-file-acts">
                    <button class="sm-bk-file-btn" onclick="bkDownload('${escAttr(f.filename)}')" title="${TH ? 'ดาวน์โหลด' : 'Download'}"><i class="fas fa-download"></i></button>
                    <button class="sm-bk-file-btn del" onclick="bkDelete('${escAttr(f.filename)}')" title="${TH ? 'ลบ' : 'Delete'}"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        el.innerHTML = `<div class="sm-bk-hist-empty" style="color:#dc2626">${TH ? 'โหลดไม่สำเร็จ' : 'Failed to load'}</div>`;
    }
}

// Trigger file download via hidden anchor
function bkDownload(filename) {
    const a = document.createElement('a');
    a.href = `?action=backup_download&file=${encodeURIComponent(filename)}`;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Delete a backup file
function bkDelete(filename) {
    const msg = TH ? `ลบไฟล์ "${filename}" หรือไม่?` : `Delete backup "${filename}"?`;
    smConfirm(msg, async () => {
        try {
            const d = await fetch('?action=backup_delete', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
                body: JSON.stringify({ filename })
            }).then(r => r.json());
            if (!d.success) throw new Error(d.error || 'Delete failed');
            smToast(TH ? 'ลบไฟล์แล้ว' : 'Backup deleted');
            bkLoadHistory();
        } catch(e) {
            smToast(e.message, 'err');
        }
    });
}

// Import SQL file
async function bkImport(file) {
    if (!file || !file.name.endsWith('.sql')) {
        smToast(TH ? 'รองรับเฉพาะไฟล์ .sql' : 'Only .sql files supported', 'err');
        return;
    }
    const resultEl = document.getElementById('smBkImportResult');
    resultEl.style.display = 'none';

    const msg = TH
        ? `นำเข้าไฟล์ "${file.name}" (${(file.size/1024).toFixed(0)} KB)?\nข้อมูลเดิมในตารางที่มีคำสั่ง INSERT อาจถูกเขียนทับ`
        : `Import "${file.name}" (${(file.size/1024).toFixed(0)} KB)?\nExisting rows may be overwritten.`;
    smConfirm(msg, async () => {
        const dropZone = document.getElementById('smBkDropZone');
        dropZone.querySelector('p').textContent = TH ? 'กำลังนำเข้า...' : 'Importing...';
        dropZone.querySelector('i').className = 'fas fa-spinner fa-spin';

        const fd = new FormData();
        fd.append('sql_file', file);
        try {
            const d = await fetch('?action=backup_import', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json());
            const ok = d.success && d.errors === 0;
            resultEl.className = 'sm-bk-import-result ' + (ok ? 'ok' : 'err');
            let html = `<div class="ir-row"><span>${TH?'คำสั่งที่รัน':'Statements run'}</span><strong>${d.executed ?? 0}</strong></div>`;
            html += `<div class="ir-row"><span>${TH?'ข้อผิดพลาด':'Errors'}</span><strong>${d.errors ?? 0}</strong></div>`;
            if (d.error_list?.length) html += `<div style="margin-top:6px;font-size:10.5px;opacity:.8">${d.error_list.slice(0,5).map(escH).join('<br>')}</div>`;
            html += `<div class="ir-row"><strong>${ok ? (TH?'✓ นำเข้าสำเร็จ':'✓ Import successful') : (TH?'⚠ มีบางข้อผิดพลาด':'⚠ Completed with errors')}</strong></div>`;
            resultEl.innerHTML = html;
            resultEl.style.display = 'block';
            smToast(ok ? (TH?'นำเข้าสำเร็จ':'Import successful') : (TH?'นำเข้าเสร็จ (มีข้อผิดพลาด)':'Import done with errors'), ok ? 'ok' : 'warn');
        } catch(e) {
            smToast(e.message, 'err');
        } finally {
            dropZone.querySelector('i').className = 'fas fa-cloud-upload-alt';
            dropZone.querySelector('p').textContent = TH ? 'ลาก & วาง ไฟล์ .sql ที่นี่' : 'Drag & drop .sql file here';
        }
    });
}

// Drop-zone wiring
document.addEventListener('DOMContentLoaded', () => {
    bkLoadTables();
    bkLoadHistory();

    const dz   = document.getElementById('smBkDropZone');
    const inp  = document.getElementById('smBkFileInput');

    inp.addEventListener('change', () => { if (inp.files[0]) bkImport(inp.files[0]); inp.value = ''; });

    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        const f = e.dataTransfer.files[0];
        if (f) bkImport(f);
    });
});
</script>

<?php Layout::endContent(); Layout::footer(); ?>
