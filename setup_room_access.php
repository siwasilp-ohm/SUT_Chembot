<?php
/**
 * One-shot setup: create user_room_access table and seed 3 rooms per user.
 * Run once via browser: http://localhost/v1/setup_room_access.php
 * Delete after running.
 */
require_once __DIR__ . '/includes/database.php';

$log = [];

// ── 1. Create table ──────────────────────────────────────────────
Database::query("CREATE TABLE IF NOT EXISTS user_room_access (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    room_id     INT NOT NULL,
    is_primary  TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uniq_user_room   (user_id, room_id),
    KEY         idx_ura_room_id  (room_id),
    KEY         idx_ura_user_pri (user_id, is_primary),
    CONSTRAINT  fk_ura_user FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT  fk_ura_room FOREIGN KEY (room_id) REFERENCES rooms(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$log[] = '✔ Table user_room_access ready.';

// ── 2. Clean old labs ────────────────────────────────────────────
Database::query("DELETE FROM user_lab_access");
$log[] = '✔ Cleared user_lab_access.';
Database::query("DELETE FROM labs");
$log[] = '✔ Cleared labs table (5 placeholder labs removed).';

// ── 3. Get top-80 rooms with containers, ordered by container count ──
$rooms = Database::fetchAll(
    "SELECT r.id, r.code, r.name, b.code AS bld_code, r.floor,
            COUNT(c.id) AS cnt
     FROM rooms r
     JOIN buildings b ON b.id = r.building_id
     LEFT JOIN containers c ON c.room_id = r.id
     GROUP BY r.id, r.code, r.name, b.code, r.floor
     HAVING cnt > 0
     ORDER BY cnt DESC
     LIMIT 80"
);
$roomIds   = array_column($rooms, 'id');
$roomCount = count($roomIds);
$log[] = "✔ Found {$roomCount} rooms with containers.";

// ── 4. Get all users ──────────────────────────────────────────────
$users = Database::fetchAll("SELECT id, username FROM users ORDER BY id");
$log[] = "✔ Found " . count($users) . " users.";

// ── 5. Clear existing room access ────────────────────────────────
Database::query("DELETE FROM user_room_access");
$log[] = '✔ Cleared previous user_room_access entries.';

// ── 6. Assign 3 rooms per user (round-robin, no repeats per user) ──
$assigned = 0;
foreach ($users as $i => $user) {
    $slots = [];
    for ($j = 0; $j < 3; $j++) {
        $idx = (($i * 3) + $j) % $roomCount;
        // ensure no duplicates (rare edge case when roomCount < 3)
        while (in_array($roomIds[$idx], $slots)) {
            $idx = ($idx + 1) % $roomCount;
        }
        $slots[] = $roomIds[$idx];
    }
    foreach ($slots as $k => $roomId) {
        Database::query(
            "INSERT IGNORE INTO user_room_access (user_id, room_id, is_primary)
             VALUES (:uid, :rid, :ip)",
            [':uid' => (int)$user['id'], ':rid' => (int)$roomId, ':ip' => ($k === 0 ? 1 : 0)]
        );
        $assigned++;
    }
}
$log[] = "✔ Assigned {$assigned} room slots to " . count($users) . " users (3 each).";

// ── 7. Verify ─────────────────────────────────────────────────────
$check = Database::fetch("SELECT COUNT(*) AS n FROM user_room_access");
$log[] = "✔ Total rows in user_room_access: " . ($check['n'] ?? 0);

?><!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>Room Access Setup</title>
<style>
body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:32px;font-size:14px}
h2{color:#6366f1;margin-bottom:20px}.ok{color:#22c55e}.warn{color:#f59e0b}
pre{background:#1e293b;padding:16px;border-radius:8px;border-left:4px solid #6366f1;line-height:1.8}
</style></head><body>
<h2>⚡ user_room_access Setup</h2>
<pre><?php foreach ($log as $l) echo '<span class="ok">' . htmlspecialchars($l) . '</span>' . "\n"; ?></pre>
<p class="warn">⚠ Delete this file: <code>setup_room_access.php</code></p>
</body></html>
