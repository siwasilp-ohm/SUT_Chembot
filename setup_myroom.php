<?php
/**
 * One-shot setup: create room_container_notes table.
 * Run once via browser: http://localhost/v1/setup_myroom.php
 * Delete after running.
 */
require_once __DIR__ . '/includes/database.php';

$log = [];

// ── 1. Create table ──────────────────────────────────────────────
try {
    Database::query("CREATE TABLE IF NOT EXISTS room_container_notes (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        room_id      INT NOT NULL,
        container_id INT NOT NULL,
        nickname     VARCHAR(100) DEFAULT NULL,
        notes        TEXT DEFAULT NULL,
        updated_by   INT NOT NULL,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY   uniq_rcn (room_id, container_id),
        KEY          idx_rcn_room (room_id),
        KEY          idx_rcn_cont (container_id),
        CONSTRAINT   fk_rcn_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        CONSTRAINT   fk_rcn_cont FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = ['ok', '✔ Table room_container_notes created/verified.'];
} catch (Exception $e) {
    $log[] = ['err', '✘ Error creating table: ' . $e->getMessage()];
}

// ── 2. Verify ─────────────────────────────────────────────────────
try {
    $check = Database::fetch("SELECT COUNT(*) AS n FROM room_container_notes");
    $log[] = ['ok', '✔ Current rows in room_container_notes: ' . ($check['n'] ?? 0)];
} catch (Exception $e) {
    $log[] = ['err', '✘ Could not query table: ' . $e->getMessage()];
}

// ── 3. Check containers table has is_active column ─────────────────
try {
    $col = Database::fetch("SHOW COLUMNS FROM containers LIKE 'is_active'");
    if ($col) {
        $log[] = ['ok', '✔ containers.is_active column exists.'];
    } else {
        $log[] = ['warn', '⚠ containers.is_active column NOT found — myroom.php queries may fail. Add it or adjust queries.'];
    }
} catch (Exception $e) {
    $log[] = ['err', '✘ Could not check containers columns: ' . $e->getMessage()];
}

// ── 4. Check user_room_access table ───────────────────────────────
try {
    $ura = Database::fetch("SELECT COUNT(*) AS n FROM user_room_access");
    $log[] = ['ok', '✔ user_room_access has ' . ($ura['n'] ?? 0) . ' rows.'];
} catch (Exception $e) {
    $log[] = ['warn', '⚠ user_room_access table issue: ' . $e->getMessage() . ' — run setup_room_access.php first.'];
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Room Setup</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', system-ui, -apple-system, monospace; background: #0f172a; color: #94a3b8; padding: 40px 24px; font-size: 14px; line-height: 1.6; min-height: 100vh; }
.container { max-width: 720px; margin: 0 auto; }
h2 { color: #6366f1; margin-bottom: 6px; font-size: 22px; display: flex; align-items: center; gap: 10px; }
h2 .icon { width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
.subtitle { color: #64748b; font-size: 12px; margin-bottom: 28px; }
.log-box { background: #1e293b; border-radius: 12px; border-left: 4px solid #6366f1; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,.3); }
.log-line { display: flex; align-items: flex-start; gap: 8px; padding: 4px 0; font-size: 13px; }
.log-line.ok { color: #22c55e; }
.log-line.warn { color: #f59e0b; }
.log-line.err { color: #f87171; }
.log-line .ico { flex-shrink: 0; width: 16px; }
.warn-box { background: #431407; border: 1px solid #92400e; border-radius: 8px; padding: 14px 18px; color: #fbbf24; font-size: 12px; }
.warn-box strong { color: #f59e0b; display: block; margin-bottom: 6px; font-size: 13px; }
code { background: #0f172a; padding: 2px 8px; border-radius: 4px; font-family: monospace; color: #a5b4fc; }
.next-steps { background: #0c1220; border: 1px solid #1e3a5f; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
.next-steps h3 { color: #60a5fa; font-size: 13px; margin-bottom: 10px; }
.next-steps ol { padding-left: 18px; font-size: 12px; color: #7dd3fc; line-height: 2; }
.next-steps a { color: #818cf8; text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
  <h2><span class="icon">🚪</span> My Room — Setup</h2>
  <p class="subtitle">One-shot setup script for the My Room feature. Run once, then delete this file.</p>

  <div class="log-box">
    <?php foreach ($log as [$type, $msg]): ?>
    <div class="log-line <?= $type ?>">
      <span class="ico"><?= $type === 'ok' ? '✔' : ($type === 'warn' ? '⚠' : '✘') ?></span>
      <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="next-steps">
    <h3>Next Steps</h3>
    <ol>
      <li>Ensure <code>user_room_access</code> is populated (run <a href="/v1/setup_room_access.php">setup_room_access.php</a> first if needed)</li>
      <li>Add <code>myroom</code> to your sidebar navigation in <code>includes/layout.php</code></li>
      <li>Visit <a href="/v1/pages/myroom.php">/v1/pages/myroom.php</a> to use the feature</li>
      <li>Delete this setup file: <code>setup_myroom.php</code></li>
    </ol>
  </div>

  <div class="warn-box">
    <strong>⚠ Security Warning</strong>
    Delete <code>setup_myroom.php</code> after running. Do not leave setup scripts accessible in production.
  </div>
</div>
</body>
</html>
