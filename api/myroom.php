<?php
/**
 * My Room API
 * Manages room storage structure, container placement, notes, and borrow requests.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=UTF-8');

// ── Auth ──────────────────────────────────────────────────────────
$user = Auth::requireAuth();
$uid  = (int)$user['id'];

// ── Helper: check user manages room ──────────────────────────────
function userManagesRoom(int $uid, int $roomId): bool {
    $row = Database::fetch(
        "SELECT id FROM user_room_access WHERE user_id = :uid AND room_id = :rid LIMIT 1",
        [':uid' => $uid, ':rid' => $roomId]
    );
    return (bool)$row;
}

// ── Helper: check user manages cabinet's room ────────────────────
function userManagesCabinet(int $uid, int $cabId): ?int {
    $row = Database::fetch(
        "SELECT c.room_id FROM cabinets c
         JOIN user_room_access ura ON ura.room_id = c.room_id AND ura.user_id = :uid
         WHERE c.id = :cid LIMIT 1",
        [':uid' => $uid, ':cid' => $cabId]
    );
    return $row ? (int)$row['room_id'] : null;
}

// ── Helper: check user manages shelf's cabinet's room ────────────
function userManagesShelf(int $uid, int $shelfId): ?int {
    $row = Database::fetch(
        "SELECT c.room_id FROM shelves s
         JOIN cabinets c ON c.id = s.cabinet_id
         JOIN user_room_access ura ON ura.room_id = c.room_id AND ura.user_id = :uid
         WHERE s.id = :sid LIMIT 1",
        [':uid' => $uid, ':sid' => $shelfId]
    );
    return $row ? (int)$row['room_id'] : null;
}

// ── Helper: check user manages slot's shelf's cabinet's room ─────
function userManagesSlot(int $uid, int $slotId): ?int {
    $row = Database::fetch(
        "SELECT c.room_id FROM slots sl
         JOIN shelves s ON s.id = sl.shelf_id
         JOIN cabinets c ON c.id = s.cabinet_id
         JOIN user_room_access ura ON ura.room_id = c.room_id AND ura.user_id = :uid
         WHERE sl.id = :slid LIMIT 1",
        [':uid' => $uid, ':slid' => $slotId]
    );
    return $row ? (int)$row['room_id'] : null;
}

// ── Helper: check if container type/material has a 3D model ──────
function has3DModel(string $type, ?string $material): bool {
    $bind = [':t' => $type];
    $w = "is_active = 1 AND container_type = :t";
    if ($material) {
        $w .= " AND (container_material = :m OR container_material IS NULL OR container_material = '')";
        $bind[':m'] = $material;
    }
    return (bool)Database::fetch("SELECT id FROM packaging_3d_models WHERE {$w} LIMIT 1", $bind);
}

// ── Helper: json response ─────────────────────────────────────────
function jsonOk($data = null): void {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
function jsonErr(string $error, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// ── Router ────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {

    // ════════════════ GET ACTIONS ════════════════════════════════

    if ($method === 'GET') {

        // ── GET: my_rooms ─────────────────────────────────────────
        if ($action === 'my_rooms') {
            $rows = Database::fetchAll(
                "SELECT ura.room_id, ura.is_primary,
                        r.code, r.name, r.floor,
                        b.code AS bld_code, b.name AS bld_name, b.shortname AS bld_short,
                        (SELECT COUNT(*) FROM containers c WHERE c.room_id = r.id AND c.is_active=1) AS total,
                        (SELECT COUNT(*) FROM containers c WHERE c.room_id = r.id AND c.is_active=1 AND c.cabinet_id IS NOT NULL) AS organized,
                        (SELECT COUNT(*) FROM containers c WHERE c.room_id = r.id AND c.is_active=1 AND c.cabinet_id IS NULL) AS unplaced,
                        (SELECT COUNT(*) FROM containers c WHERE c.room_id = r.id AND c.is_active=1 AND c.expiry_date IS NOT NULL AND c.expiry_date <= DATE_ADD(NOW(), INTERVAL 60 DAY) AND c.expiry_date >= NOW()) AS expiring_soon,
                        (SELECT COUNT(*) FROM containers c WHERE c.room_id = r.id AND c.is_active=1 AND c.expiry_date < NOW()) AS expired
                 FROM user_room_access ura
                 JOIN rooms r ON r.id = ura.room_id
                 JOIN buildings b ON b.id = r.building_id
                 WHERE ura.user_id = :uid
                 ORDER BY ura.is_primary DESC, b.code, r.code",
                [':uid' => $uid]
            );

            if (empty($rows)) {
                jsonErr('No rooms assigned', 404);
            }
            jsonOk($rows);
        }

        // ── GET: room_data ────────────────────────────────────────
        if ($action === 'room_data') {
            $roomId = (int)($_GET['room_id'] ?? 0);
            if (!$roomId) jsonErr('room_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            // Fetch cabinets
            $cabinets = Database::fetchAll(
                "SELECT c.*,
                    (SELECT COUNT(*) FROM containers ct WHERE ct.cabinet_id = c.id AND ct.is_active=1) AS container_count
                 FROM cabinets c WHERE c.room_id = :rid ORDER BY c.code",
                [':rid' => $roomId]
            );

            // For each cabinet fetch shelves, for each shelf fetch slots
            foreach ($cabinets as &$cab) {
                $shelves = Database::fetchAll(
                    "SELECT s.*,
                        (SELECT COUNT(*) FROM containers ct WHERE ct.shelf_id = s.id AND ct.is_active=1) AS container_count
                     FROM shelves s WHERE s.cabinet_id = :cid ORDER BY s.level, s.name",
                    [':cid' => (int)$cab['id']]
                );
                foreach ($shelves as &$sh) {
                    $slots = Database::fetchAll(
                        "SELECT sl.*,
                            (SELECT COUNT(*) FROM containers ct WHERE ct.slot_id = sl.id AND ct.is_active=1) AS container_count
                         FROM slots sl WHERE sl.shelf_id = :sid ORDER BY sl.position, sl.name",
                        [':sid' => (int)$sh['id']]
                    );
                    $sh['slots'] = $slots;
                }
                unset($sh);
                $cab['shelves'] = $shelves;
            }
            unset($cab);

            // Fetch containers
            $containers = Database::fetchAll(
                "SELECT ct.id, ct.bottle_code, ct.container_type, ct.container_material, ct.container_3d_model,
                        ct.initial_quantity, ct.current_quantity, ct.quantity_unit,
                        ct.expiry_date, ct.status, ct.cabinet_id, ct.shelf_id, ct.slot_id,
                        ct.owner_id, ct.chemical_id, ct.received_date,
                        ct.notes AS container_notes,
                        ch.name AS chem_name, ch.cas_number, ch.molecular_formula, ch.physical_state,
                        ch.hazard_pictograms, ch.signal_word,
                        u.first_name AS owner_fn, u.last_name AS owner_ln,
                        u.avatar_url AS owner_avatar_url,
                        CONCAT(TRIM(COALESCE(u.first_name,'')), ' ', TRIM(COALESCE(u.last_name,''))) AS owner_name,
                        cab.name AS cabinet_name, cab.code AS cabinet_code, cab.type AS cabinet_type,
                        sh.name AS shelf_name, sh.code AS shelf_code,
                        sl.name AS slot_name, sl.code AS slot_code,
                        rcn.nickname, rcn.notes AS room_note
                 FROM containers ct
                 LEFT JOIN chemicals ch ON ch.id = ct.chemical_id
                 LEFT JOIN users u ON u.id = ct.owner_id
                 LEFT JOIN cabinets cab ON cab.id = ct.cabinet_id
                 LEFT JOIN shelves sh ON sh.id = ct.shelf_id
                 LEFT JOIN slots sl ON sl.id = ct.slot_id
                 LEFT JOIN room_container_notes rcn ON rcn.room_id = :rid AND rcn.container_id = ct.id
                 WHERE ct.room_id = :rid2 AND ct.is_active = 1
                 ORDER BY cab.code, sh.level, sl.position, ct.bottle_code",
                [':rid' => $roomId, ':rid2' => $roomId]
            );

            foreach ($containers as &$ct) {
                $ct['has_3d'] = !empty($ct['container_3d_model'])
                    || has3DModel($ct['container_type'] ?? 'bottle', $ct['container_material'] ?? null);
                unset($ct['container_3d_model']); // not needed client-side
            }
            unset($ct);

            jsonOk([
                'cabinets'   => $cabinets,
                'containers' => $containers,
            ]);
        }

        // ── GET: get_room_admins ──────────────────────────────────
        if ($action === 'get_room_admins') {
            $roomId = (int)($_GET['room_id'] ?? 0);
            if (!$roomId) jsonErr('room_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $admins = Database::fetchAll(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, ura.is_primary
                 FROM user_room_access ura
                 JOIN users u ON u.id = ura.user_id
                 WHERE ura.room_id = :rid
                 ORDER BY ura.is_primary DESC, u.first_name",
                [':rid' => $roomId]
            );
            jsonOk($admins);
        }

        // ── GET: search_users ─────────────────────────────────────
        if ($action === 'search_users') {
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) jsonErr('Query too short');

            $rows = Database::fetchAll(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url
                 FROM users u
                 WHERE u.is_active = 1 AND (
                     u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q
                 )
                 LIMIT 20",
                [':q' => '%' . $q . '%']
            );
            jsonOk($rows);
        }

        jsonErr('Unknown action');
    }

    // ════════════════ POST ACTIONS ════════════════════════════════

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) jsonErr('Invalid JSON body');

        // ── POST: save_note ───────────────────────────────────────
        if ($action === 'save_note') {
            $roomId      = (int)($body['room_id'] ?? 0);
            $containerId = (int)($body['container_id'] ?? 0);
            $nickname    = trim($body['nickname'] ?? '');
            $notes       = trim($body['notes'] ?? '');

            if (!$roomId || !$containerId) jsonErr('room_id and container_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $cont = Database::fetch(
                "SELECT id FROM containers WHERE id = :id AND room_id = :rid AND is_active = 1",
                [':id' => $containerId, ':rid' => $roomId]
            );
            if (!$cont) jsonErr('Container not found in this room');

            Database::query(
                "INSERT INTO room_container_notes (room_id, container_id, nickname, notes, updated_by)
                 VALUES (:rid, :cid, :nick, :notes, :uid)
                 ON DUPLICATE KEY UPDATE
                   nickname   = VALUES(nickname),
                   notes      = VALUES(notes),
                   updated_by = VALUES(updated_by)",
                [
                    ':rid'   => $roomId,
                    ':cid'   => $containerId,
                    ':nick'  => $nickname ?: null,
                    ':notes' => $notes ?: null,
                    ':uid'   => $uid,
                ]
            );

            jsonOk(['saved' => true]);
        }

        // ── POST: place_container ─────────────────────────────────
        if ($action === 'place_container') {
            $containerId = (int)($body['container_id'] ?? 0);
            $roomId      = (int)($body['room_id'] ?? 0);
            $cabinetId   = !empty($body['cabinet_id']) ? (int)$body['cabinet_id'] : null;
            $shelfId     = !empty($body['shelf_id'])   ? (int)$body['shelf_id']   : null;
            $slotId      = !empty($body['slot_id'])    ? (int)$body['slot_id']    : null;

            if (!$containerId || !$roomId) jsonErr('container_id and room_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $cont = Database::fetch(
                "SELECT id FROM containers WHERE id = :id AND room_id = :rid AND is_active = 1",
                [':id' => $containerId, ':rid' => $roomId]
            );
            if (!$cont) jsonErr('Container not found in this room');

            if ($cabinetId) {
                $cab = Database::fetch(
                    "SELECT id FROM cabinets WHERE id = :cid AND room_id = :rid",
                    [':cid' => $cabinetId, ':rid' => $roomId]
                );
                if (!$cab) jsonErr('Cabinet not in this room');
            }

            Database::query(
                "UPDATE containers SET cabinet_id = :cab, shelf_id = :shelf, slot_id = :slot
                 WHERE id = :id AND room_id = :rid",
                [
                    ':cab'   => $cabinetId,
                    ':shelf' => $shelfId,
                    ':slot'  => $slotId,
                    ':id'    => $containerId,
                    ':rid'   => $roomId,
                ]
            );

            jsonOk(['placed' => true]);
        }

        // ── POST: add_cabinet ─────────────────────────────────────
        if ($action === 'add_cabinet') {
            $roomId = (int)($body['room_id'] ?? 0);
            $name   = trim($body['name'] ?? '');
            $code   = trim($body['code'] ?? '');
            $type   = $body['type'] ?? 'storage';

            if (!$roomId) jsonErr('room_id required');
            if (!$name)   jsonErr('Cabinet name required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $allowedTypes = ['storage','fume_hood','refrigerator','freezer','safety_cabinet','other'];
            if (!in_array($type, $allowedTypes)) $type = 'storage';

            Database::query(
                "INSERT INTO cabinets (room_id, name, code, type) VALUES (:rid, :name, :code, :type)",
                [':rid' => $roomId, ':name' => $name, ':code' => $code ?: null, ':type' => $type]
            );

            $newId = Database::fetch("SELECT LAST_INSERT_ID() AS id");
            jsonOk(['id' => (int)($newId['id'] ?? 0)]);
        }

        // ── POST: update_cabinet ──────────────────────────────────
        if ($action === 'update_cabinet') {
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            $code = trim($body['code'] ?? '');
            $type = $body['type'] ?? 'storage';

            if (!$id)   jsonErr('Cabinet id required');
            if (!$name) jsonErr('Cabinet name required');

            $roomId = userManagesCabinet($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);

            $allowedTypes = ['storage','fume_hood','refrigerator','freezer','safety_cabinet','other'];
            if (!in_array($type, $allowedTypes)) $type = 'storage';

            Database::query(
                "UPDATE cabinets SET name = :name, code = :code, type = :type WHERE id = :id",
                [':name' => $name, ':code' => $code ?: null, ':type' => $type, ':id' => $id]
            );

            jsonOk(['updated' => true]);
        }

        // ── POST: rename_cabinet ──────────────────────────────────
        if ($action === 'rename_cabinet') {
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (!$id)   jsonErr('id required');
            if (!$name) jsonErr('Name required');
            $roomId = userManagesCabinet($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);
            Database::query("UPDATE cabinets SET name = :name WHERE id = :id", [':name' => $name, ':id' => $id]);
            jsonOk(['renamed' => true]);
        }

        // ── POST: delete_cabinet ──────────────────────────────────
        if ($action === 'delete_cabinet') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonErr('Cabinet id required');

            $roomId = userManagesCabinet($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);

            // Move all containers inside to Unplaced (cabinet_id = NULL)
            $moved = Database::fetch(
                "SELECT COUNT(*) AS n FROM containers WHERE cabinet_id = :id AND is_active = 1",
                [':id' => $id]
            );
            if (($moved['n'] ?? 0) > 0) {
                Database::query(
                    "UPDATE containers SET cabinet_id = NULL, shelf_id = NULL, slot_id = NULL
                     WHERE cabinet_id = :id AND is_active = 1",
                    [':id' => $id]
                );
            }

            Database::query(
                "DELETE FROM slots WHERE shelf_id IN (SELECT id FROM shelves WHERE cabinet_id = :id)",
                [':id' => $id]
            );
            Database::query("DELETE FROM shelves WHERE cabinet_id = :id", [':id' => $id]);
            Database::query("DELETE FROM cabinets WHERE id = :id", [':id' => $id]);

            jsonOk(['deleted' => true, 'moved' => (int)($moved['n'] ?? 0)]);
        }

        // ── POST: add_shelf ───────────────────────────────────────
        if ($action === 'add_shelf') {
            $cabinetId = (int)($body['cabinet_id'] ?? 0);
            $name      = trim($body['name'] ?? '');
            $level     = (int)($body['level'] ?? 0);

            if (!$cabinetId) jsonErr('cabinet_id required');
            if (!$name)      jsonErr('Shelf name required');

            $roomId = userManagesCabinet($uid, $cabinetId);
            if (!$roomId) jsonErr('Access denied', 403);

            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));

            Database::query(
                "INSERT INTO shelves (cabinet_id, name, code, level) VALUES (:cid, :name, :code, :level)",
                [':cid' => $cabinetId, ':name' => $name, ':code' => $code ?: null, ':level' => $level]
            );

            $newId = Database::fetch("SELECT LAST_INSERT_ID() AS id");
            jsonOk(['id' => (int)($newId['id'] ?? 0)]);
        }

        // ── POST: rename_shelf ────────────────────────────────────
        if ($action === 'rename_shelf') {
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (!$id)   jsonErr('id required');
            if (!$name) jsonErr('Name required');
            $roomId = userManagesShelf($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);
            Database::query("UPDATE shelves SET name = :name WHERE id = :id", [':name' => $name, ':id' => $id]);
            jsonOk(['renamed' => true]);
        }

        // ── POST: delete_shelf ────────────────────────────────────
        if ($action === 'delete_shelf') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonErr('Shelf id required');

            $roomId = userManagesShelf($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);

            // Move containers to cabinet level (shelf_id = NULL, slot_id = NULL)
            $moved = Database::fetch(
                "SELECT COUNT(*) AS n FROM containers WHERE shelf_id = :id AND is_active = 1",
                [':id' => $id]
            );
            if (($moved['n'] ?? 0) > 0) {
                Database::query(
                    "UPDATE containers SET shelf_id = NULL, slot_id = NULL
                     WHERE shelf_id = :id AND is_active = 1",
                    [':id' => $id]
                );
            }

            Database::query("DELETE FROM slots WHERE shelf_id = :id", [':id' => $id]);
            Database::query("DELETE FROM shelves WHERE id = :id", [':id' => $id]);

            jsonOk(['deleted' => true, 'moved' => (int)($moved['n'] ?? 0)]);
        }

        // ── POST: add_slot ────────────────────────────────────────
        if ($action === 'add_slot') {
            $shelfId  = (int)($body['shelf_id'] ?? 0);
            $name     = trim($body['name'] ?? '');
            $position = (int)($body['position'] ?? 0);

            if (!$shelfId) jsonErr('shelf_id required');
            if (!$name)    jsonErr('Slot name required');

            $roomId = userManagesShelf($uid, $shelfId);
            if (!$roomId) jsonErr('Access denied', 403);

            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));

            Database::query(
                "INSERT INTO slots (shelf_id, name, code, position) VALUES (:sid, :name, :code, :pos)",
                [':sid' => $shelfId, ':name' => $name, ':code' => $code ?: null, ':pos' => $position]
            );

            $newId = Database::fetch("SELECT LAST_INSERT_ID() AS id");
            jsonOk(['id' => (int)($newId['id'] ?? 0)]);
        }

        // ── POST: rename_slot ─────────────────────────────────────
        if ($action === 'rename_slot') {
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (!$id)   jsonErr('id required');
            if (!$name) jsonErr('Name required');
            $roomId = userManagesSlot($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);
            Database::query("UPDATE slots SET name = :name WHERE id = :id", [':name' => $name, ':id' => $id]);
            jsonOk(['renamed' => true]);
        }

        // ── POST: delete_slot ─────────────────────────────────────
        if ($action === 'delete_slot') {
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonErr('Slot id required');

            $roomId = userManagesSlot($uid, $id);
            if (!$roomId) jsonErr('Access denied', 403);

            // Move containers to shelf level (slot_id = NULL)
            $moved = Database::fetch(
                "SELECT COUNT(*) AS n FROM containers WHERE slot_id = :id AND is_active = 1",
                [':id' => $id]
            );
            if (($moved['n'] ?? 0) > 0) {
                Database::query(
                    "UPDATE containers SET slot_id = NULL WHERE slot_id = :id AND is_active = 1",
                    [':id' => $id]
                );
            }

            Database::query("DELETE FROM slots WHERE id = :id", [':id' => $id]);

            jsonOk(['deleted' => true, 'moved' => (int)($moved['n'] ?? 0)]);
        }

        // ── POST: add_room_admin ──────────────────────────────────
        if ($action === 'add_room_admin') {
            $roomId    = (int)($body['room_id'] ?? 0);
            $targetUid = (int)($body['user_id'] ?? 0);
            if (!$roomId || !$targetUid) jsonErr('room_id and user_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $usr = Database::fetch("SELECT id FROM users WHERE id = :id AND is_active = 1", [':id' => $targetUid]);
            if (!$usr) jsonErr('User not found');

            Database::query(
                "INSERT IGNORE INTO user_room_access (user_id, room_id, is_primary) VALUES (:uid, :rid, 0)",
                [':uid' => $targetUid, ':rid' => $roomId]
            );
            jsonOk(['added' => true]);
        }

        // ── POST: remove_room_admin ───────────────────────────────
        if ($action === 'remove_room_admin') {
            $roomId    = (int)($body['room_id'] ?? 0);
            $targetUid = (int)($body['user_id'] ?? 0);
            if (!$roomId || !$targetUid) jsonErr('room_id and user_id required');
            if (!userManagesRoom($uid, $roomId)) jsonErr('Access denied', 403);

            $cnt = Database::fetch("SELECT COUNT(*) AS n FROM user_room_access WHERE room_id = :rid", [':rid' => $roomId]);
            if (($cnt['n'] ?? 0) <= 1) jsonErr('Cannot remove the only room admin');

            $target = Database::fetch(
                "SELECT is_primary FROM user_room_access WHERE user_id = :uid AND room_id = :rid",
                [':uid' => $targetUid, ':rid' => $roomId]
            );
            if ($target && $target['is_primary']) jsonErr('Cannot remove primary room admin');

            Database::query(
                "DELETE FROM user_room_access WHERE user_id = :uid AND room_id = :rid",
                [':uid' => $targetUid, ':rid' => $roomId]
            );
            jsonOk(['removed' => true]);
        }

        // ── POST: withdraw ────────────────────────────────────────────
        if ($action === 'withdraw') {
            $containerId = (int)($body['container_id'] ?? 0);
            $amount      = (float)($body['amount'] ?? 0);
            $purpose     = trim($body['purpose'] ?? '');

            if (!$containerId)  jsonErr('container_id required');
            if ($amount <= 0)   jsonErr('Amount must be positive');
            if (!$purpose)      jsonErr('Purpose required');

            $cont = Database::fetch(
                "SELECT id, owner_id, current_quantity FROM containers WHERE id = :id AND is_active = 1",
                [':id' => $containerId]
            );
            if (!$cont) jsonErr('Container not found');
            if ((int)$cont['owner_id'] !== $uid) jsonErr('Access denied: not the owner', 403);
            if ((float)$cont['current_quantity'] < $amount) jsonErr('Insufficient quantity');

            $newQty = max(0, round((float)$cont['current_quantity'] - $amount, 6));
            Database::query(
                "UPDATE containers SET current_quantity = :qty WHERE id = :id",
                [':qty' => $newQty, ':id' => $containerId]
            );
            jsonOk(['new_quantity' => $newQty, 'withdrawn' => $amount]);
        }

        // ── POST: restock ─────────────────────────────────────────────
        if ($action === 'restock') {
            $containerId = (int)($body['container_id'] ?? 0);
            $amount      = (float)($body['amount'] ?? 0);

            if (!$containerId) jsonErr('container_id required');
            if ($amount <= 0)  jsonErr('Amount must be positive');

            $cont = Database::fetch(
                "SELECT id, owner_id, current_quantity FROM containers WHERE id = :id AND is_active = 1",
                [':id' => $containerId]
            );
            if (!$cont) jsonErr('Container not found');
            if ((int)$cont['owner_id'] !== $uid) jsonErr('Access denied: not the owner', 403);

            $newQty = round((float)$cont['current_quantity'] + $amount, 6);
            Database::query(
                "UPDATE containers SET current_quantity = :qty WHERE id = :id",
                [':qty' => $newQty, ':id' => $containerId]
            );
            jsonOk(['new_quantity' => $newQty, 'added' => $amount]);
        }

        // ── POST: dispose ─────────────────────────────────────────────
        if ($action === 'dispose') {
            $containerId = (int)($body['container_id'] ?? 0);
            $reason      = trim($body['reason'] ?? '');

            if (!$containerId) jsonErr('container_id required');
            if (!$reason)      jsonErr('Reason required');

            $cont = Database::fetch(
                "SELECT id, owner_id FROM containers WHERE id = :id AND is_active = 1",
                [':id' => $containerId]
            );
            if (!$cont) jsonErr('Container not found');
            if ((int)$cont['owner_id'] !== $uid) jsonErr('Access denied: not the owner', 403);

            $note = "\n[" . date('Y-m-d') . ' จำหน่าย: ' . $reason . ']';
            Database::query(
                "UPDATE containers SET is_active = 0, current_quantity = 0,
                    container_notes = CONCAT(COALESCE(container_notes,''), :note)
                 WHERE id = :id",
                [':note' => $note, ':id' => $containerId]
            );
            jsonOk(['disposed' => true]);
        }

        // ── POST: borrow_request ──────────────────────────────────
        if ($action === 'borrow_request') {
            $containerId = (int)($body['container_id'] ?? 0);
            $quantity    = (float)($body['quantity'] ?? 0);
            $unit        = trim($body['unit'] ?? '');
            $purpose     = trim($body['purpose'] ?? '');

            if (!$containerId) jsonErr('container_id required');
            if (!$purpose)     jsonErr('Purpose is required');

            $cont = Database::fetch(
                "SELECT ct.id, ct.owner_id, ct.chemical_id, ct.quantity_unit, ct.room_id
                 FROM containers ct
                 WHERE ct.id = :id AND ct.is_active = 1",
                [':id' => $containerId]
            );
            if (!$cont) jsonErr('Container not found');

            $requestNumber = 'BR-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            Database::query(
                "INSERT INTO borrow_requests
                    (requester_id, owner_id, container_id, chemical_id, request_type,
                     requested_quantity, quantity_unit, purpose, status, created_at)
                 VALUES
                    (:req, :owner, :cont, :chem, 'borrow',
                     :qty, :unit, :purpose, 'pending', NOW())",
                [
                    ':req'     => $uid,
                    ':owner'   => (int)$cont['owner_id'],
                    ':cont'    => $containerId,
                    ':chem'    => (int)$cont['chemical_id'],
                    ':qty'     => $quantity,
                    ':unit'    => $unit ?: ($cont['quantity_unit'] ?? 'unit'),
                    ':purpose' => $purpose,
                ]
            );

            $newId = Database::fetch("SELECT LAST_INSERT_ID() AS id");
            jsonOk([
                'id'             => (int)($newId['id'] ?? 0),
                'request_number' => $requestNumber,
            ]);
        }

        jsonErr('Unknown action');
    }

    jsonErr('Method not allowed', 405);

} catch (Exception $e) {
    jsonErr('Server error: ' . $e->getMessage(), 500);
}
