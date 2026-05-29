<?php
/**
 * Location Management API v2 — Full Hierarchy
 * อาคาร > ชั้น > ห้อง > ตู้ > ชั้นวาง > ช่อง
 * Building > Floor > Room > Cabinet > Shelf > Slot
 * + Center Store (คลังกลาง) support
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action, $user);
            break;
        case 'POST':
            handlePost($action, $user);
            break;
        case 'PUT':
            handlePut($action, $user);
            break;
        case 'DELETE':
            handleDelete($action, $user);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════
// GET handlers
// ═══════════════════════════════════
function handleGet(string $action, array $user): void {
    switch ($action) {
        case 'stats':
            echo json_encode(['success' => true, 'data' => getLocationStats()]);
            break;
        case 'buildings':
            echo json_encode(['success' => true, 'data' => getBuildings()]);
            break;
        case 'floors':
            echo json_encode(['success' => true, 'data' => getFloors((int)$_GET['building_id'])]);
            break;
        case 'rooms':
            echo json_encode(['success' => true, 'data' => getRooms($_GET)]);
            break;
        case 'cabinets':
            echo json_encode(['success' => true, 'data' => getCabinets((int)$_GET['room_id'])]);
            break;
        case 'shelves':
            echo json_encode(['success' => true, 'data' => getShelves((int)$_GET['cabinet_id'])]);
            break;
        case 'slots':
            echo json_encode(['success' => true, 'data' => getSlots((int)$_GET['shelf_id'])]);
            break;
        case 'tree':
            echo json_encode(['success' => true, 'data' => getBuildingTree((int)$_GET['building_id'])]);
            break;
        case 'search':
            echo json_encode(['success' => true, 'data' => searchLocations($_GET['q'] ?? '')]);
            break;
        case 'breadcrumb':
            echo json_encode(['success' => true, 'data' => getBreadcrumb($_GET['type'] ?? '', (int)($_GET['id'] ?? 0))]);
            break;
        case 'detail':
            echo json_encode(['success' => true, 'data' => getDetail($_GET['type'] ?? '', (int)($_GET['id'] ?? 0))]);
            break;
        // Legacy compatibility
        case 'hierarchy':
        case '':
            if (isset($_GET['hierarchy']) || $action === 'hierarchy') {
                $data = getLocationHierarchy($_GET['lab_id'] ?? $user['lab_id'] ?? null);
                echo json_encode(['success' => true, 'data' => $data]);
            } elseif (isset($_GET['type'])) {
                echo json_encode(['success' => true, 'data' => getLocationsByType($_GET['type'], $_GET)]);
            } else {
                echo json_encode(['success' => true, 'data' => getBuildings()]);
            }
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
}

// ─── Stats ───
function getLocationStats(): array {
    $db = Database::getInstance();
    return [
        'buildings'    => (int)$db->query("SELECT COUNT(*) FROM buildings")->fetchColumn(),
        'rooms'        => (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
        'active_rooms' => (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status_text='พร้อมใช้งาน' OR status_text IS NULL")->fetchColumn(),
        'cabinets'     => (int)$db->query("SELECT COUNT(*) FROM cabinets")->fetchColumn(),
        'shelves'      => (int)$db->query("SELECT COUNT(*) FROM shelves")->fetchColumn(),
        'slots'        => (int)$db->query("SELECT COUNT(*) FROM slots")->fetchColumn(),
        'containers'   => (int)$db->query("SELECT COUNT(*) FROM containers WHERE status='active'")->fetchColumn(),
    ];
}

// ─── Buildings ───
function getBuildings(): array {
    return Database::fetchAll("
        SELECT b.*, 
               COUNT(DISTINCT r.id) as room_count,
               COUNT(DISTINCT r.floor) as floor_count,
               COUNT(DISTINCT c.id) as cabinet_count
        FROM buildings b
        LEFT JOIN rooms r ON r.building_id = b.id
        LEFT JOIN cabinets c ON c.room_id = r.id
        GROUP BY b.id
        ORDER BY LENGTH(b.shortname), b.shortname
    ");
}

// ─── Floors (virtual level) ───
function getFloors(int $buildingId): array {
    return Database::fetchAll("
        SELECT r.floor,
               COUNT(r.id) as room_count,
               COUNT(DISTINCT c.id) as cabinet_count,
               SUM(CASE WHEN r.status_text = 'พร้อมใช้งาน' OR r.status_text IS NULL THEN 1 ELSE 0 END) as active_rooms,
               SUM(CASE WHEN r.status_text = 'ปิดปรับปรุง' THEN 1 ELSE 0 END) as maintenance_rooms
        FROM rooms r
        LEFT JOIN cabinets c ON c.room_id = r.id
        WHERE r.building_id = :bid
        GROUP BY r.floor
        ORDER BY r.floor
    ", [':bid' => $buildingId]);
}

// ─── Rooms ───
function getRooms(array $filters): array {
    $buildingId = (int)($filters['building_id'] ?? 0);
    $floor = $filters['floor'] ?? null;
    $status = $filters['status'] ?? null;

    $where = "r.building_id = :bid";
    $params = [':bid' => $buildingId];

    if ($floor !== null && $floor !== '') {
        $where .= " AND r.floor = :floor";
        $params[':floor'] = (int)$floor;
    }
    if ($status === 'active')      $where .= " AND (r.status_text='พร้อมใช้งาน' OR r.status_text IS NULL)";
    elseif ($status === 'maintenance') $where .= " AND r.status_text='ปิดปรับปรุง'";
    elseif ($status === 'closed')  $where .= " AND r.status_text='ไม่เปิดให้บริการ'";

    return Database::fetchAll("
        SELECT r.*, b.name as building_name, b.shortname as building_shortname,
               COUNT(DISTINCT c.id) as cabinet_count
        FROM rooms r
        JOIN buildings b ON r.building_id = b.id
        LEFT JOIN cabinets c ON c.room_id = r.id
        WHERE $where
        GROUP BY r.id
        ORDER BY r.floor, r.code, r.name
    ", $params);
}

// ─── Cabinets ───
function getCabinets(int $roomId): array {
    return Database::fetchAll("
        SELECT c.*, r.name as room_name, r.code as room_code,
               COUNT(DISTINCT s.id) as shelf_count,
               (SELECT COUNT(*) FROM containers cn 
                JOIN slots sl ON cn.location_slot_id = sl.id 
                JOIN shelves sh ON sl.shelf_id = sh.id 
                WHERE sh.cabinet_id = c.id AND cn.status='active') as container_count
        FROM cabinets c
        JOIN rooms r ON c.room_id = r.id
        LEFT JOIN shelves s ON s.cabinet_id = c.id
        WHERE c.room_id = :rid
        GROUP BY c.id ORDER BY c.name
    ", [':rid' => $roomId]);
}

// ─── Shelves ───
function getShelves(int $cabinetId): array {
    return Database::fetchAll("
        SELECT s.*, c.name as cabinet_name,
               COUNT(DISTINCT sl.id) as slot_count,
               (SELECT COUNT(*) FROM containers cn 
                JOIN slots sl2 ON cn.location_slot_id = sl2.id 
                WHERE sl2.shelf_id = s.id AND cn.status='active') as container_count
        FROM shelves s
        JOIN cabinets c ON s.cabinet_id = c.id
        LEFT JOIN slots sl ON sl.shelf_id = s.id
        WHERE s.cabinet_id = :cid
        GROUP BY s.id ORDER BY s.level
    ", [':cid' => $cabinetId]);
}

// ─── Slots ───
function getSlots(int $shelfId): array {
    return Database::fetchAll("
        SELECT sl.*, s.name as shelf_name,
               cn.id as container_id, cn.container_number, cn.status as container_status,
               ch.name as chemical_name, ch.cas_number
        FROM slots sl
        JOIN shelves s ON sl.shelf_id = s.id
        LEFT JOIN containers cn ON cn.location_slot_id = sl.id AND cn.status='active'
        LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
        WHERE sl.shelf_id = :sid ORDER BY sl.position
    ", [':sid' => $shelfId]);
}

// ─── Building Tree ───
function getBuildingTree(int $buildingId): array {
    $building = Database::fetch("SELECT * FROM buildings WHERE id = :id", [':id' => $buildingId]);
    if (!$building) throw new Exception('Building not found');

    $floors = Database::fetchAll(
        "SELECT DISTINCT r.floor FROM rooms r WHERE r.building_id = :bid ORDER BY r.floor",
        [':bid' => $buildingId]
    );

    $tree = [];
    foreach ($floors as $f) {
        $floorNum = $f['floor'];
        $rooms = Database::fetchAll("
            SELECT r.*, COUNT(c.id) as cabinet_count
            FROM rooms r LEFT JOIN cabinets c ON c.room_id = r.id
            WHERE r.building_id = :bid AND r.floor = :floor
            GROUP BY r.id ORDER BY r.code, r.name
        ", [':bid' => $buildingId, ':floor' => $floorNum]);

        foreach ($rooms as &$room) {
            $room['cabinets'] = Database::fetchAll("
                SELECT c.*, COUNT(s.id) as shelf_count
                FROM cabinets c LEFT JOIN shelves s ON s.cabinet_id = c.id
                WHERE c.room_id = :rid GROUP BY c.id ORDER BY c.name
            ", [':rid' => $room['id']]);
        }
        $tree[] = ['floor' => $floorNum, 'room_count' => count($rooms), 'rooms' => $rooms];
    }
    $building['floors'] = $tree;
    return $building;
}

// ─── Search ───
function searchLocations(string $q): array {
    if (strlen($q) < 2) return [];
    $like = "%$q%";
    $params = [':q' => $like];

    $buildings = Database::fetchAll(
        "SELECT 'building' as type, id, name, name_en, shortname as code FROM buildings 
         WHERE name LIKE :q OR name_en LIKE :q OR shortname LIKE :q LIMIT 10", $params);

    $rooms = Database::fetchAll(
        "SELECT 'room' as type, r.id, r.name, r.name_en, r.code, r.floor,
                b.name as building_name, b.shortname as building_code
         FROM rooms r JOIN buildings b ON r.building_id = b.id
         WHERE r.name LIKE :q OR r.name_en LIKE :q OR r.code LIKE :q LIMIT 20", $params);

    $cabinets = Database::fetchAll(
        "SELECT 'cabinet' as type, c.id, c.name, c.code, r.name as room_name, b.name as building_name
         FROM cabinets c JOIN rooms r ON c.room_id = r.id JOIN buildings b ON r.building_id = b.id
         WHERE c.name LIKE :q OR c.code LIKE :q LIMIT 10", $params);

    return array_merge($buildings, $rooms, $cabinets);
}

// ─── Breadcrumb ───
function getBreadcrumb(string $type, int $id): array {
    $crumbs = [];
    if ($type === 'room') {
        $d = Database::fetch("SELECT r.name,r.floor, b.id as bid, b.name as bname, b.shortname
            FROM rooms r JOIN buildings b ON r.building_id=b.id WHERE r.id=:id", [':id'=>$id]);
        if ($d) {
            $crumbs[] = ['type'=>'building','id'=>$d['bid'],'name'=>$d['bname'],'code'=>$d['shortname']];
            $crumbs[] = ['type'=>'floor','floor'=>$d['floor'],'name'=>'ชั้น '.$d['floor']];
            $crumbs[] = ['type'=>'room','id'=>$id,'name'=>$d['name']];
        }
    } elseif ($type === 'cabinet') {
        $d = Database::fetch("SELECT c.name as cname, r.id as rid, r.name as rname, r.floor,
            b.id as bid, b.name as bname, b.shortname
            FROM cabinets c JOIN rooms r ON c.room_id=r.id JOIN buildings b ON r.building_id=b.id WHERE c.id=:id", [':id'=>$id]);
        if ($d) {
            $crumbs[] = ['type'=>'building','id'=>$d['bid'],'name'=>$d['bname'],'code'=>$d['shortname']];
            $crumbs[] = ['type'=>'floor','floor'=>$d['floor'],'name'=>'ชั้น '.$d['floor']];
            $crumbs[] = ['type'=>'room','id'=>$d['rid'],'name'=>$d['rname']];
            $crumbs[] = ['type'=>'cabinet','id'=>$id,'name'=>$d['cname']];
        }
    }
    return $crumbs;
}

// ─── Detail ───
function getDetail(string $type, int $id): ?array {
    switch ($type) {
        case 'building':
            $d = Database::fetch("SELECT * FROM buildings WHERE id=:id", [':id'=>$id]);
            if ($d) {
                $d['room_count'] = (int)Database::fetch("SELECT COUNT(*) as c FROM rooms WHERE building_id=:id", [':id'=>$id])['c'];
                $d['cabinet_count'] = (int)Database::fetch("SELECT COUNT(*) as c FROM cabinets c JOIN rooms r ON c.room_id=r.id WHERE r.building_id=:id", [':id'=>$id])['c'];
            }
            return $d;
        case 'room':
            return Database::fetch("SELECT r.*, b.name as building_name, b.shortname as building_code
                FROM rooms r JOIN buildings b ON r.building_id=b.id WHERE r.id=:id", [':id'=>$id]);
        case 'cabinet':
            return Database::fetch("SELECT c.*, r.name as room_name, r.code as room_code, b.name as building_name
                FROM cabinets c JOIN rooms r ON c.room_id=r.id JOIN buildings b ON r.building_id=b.id WHERE c.id=:id", [':id'=>$id]);
        default: return null;
    }
}

// ═══════════════════════════════════
// POST — Create
// ═══════════════════════════════════
function handlePost(string $action, array $user): void {
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    if ($roleLevel < 3) throw new Exception('Insufficient permission');

    $data = json_decode(file_get_contents('php://input'), true);
    $type = $data['type'] ?? $action;

    switch ($type) {
        case 'building':
            if ($roleLevel < 5) throw new Exception('Only admin can create buildings');
            $id = Database::insert('buildings', [
                'organization_id' => $data['organization_id'] ?? 1,
                'name' => $data['name'], 'name_en' => $data['name_en'] ?? null,
                'code' => $data['code'] ?? null, 'shortname' => $data['shortname'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
            break;
        case 'room':
            $id = Database::insert('rooms', [
                'building_id' => $data['building_id'], 'name' => $data['name'],
                'name_en' => $data['name_en'] ?? null, 'code' => $data['code'] ?? null,
                'room_number' => $data['room_number'] ?? $data['code'] ?? null,
                'room_type' => $data['room_type'] ?? null, 'floor' => $data['floor'] ?? 1,
                'area_sqm' => $data['area_sqm'] ?? null, 'capacity_persons' => $data['capacity_persons'] ?? null,
                'status_text' => $data['status_text'] ?? 'พร้อมใช้งาน',
            ]);
            break;
        case 'cabinet':
            $id = Database::insert('cabinets', [
                'room_id' => $data['room_id'], 'name' => $data['name'],
                'code' => $data['code'] ?? null, 'type' => $data['cabinet_type'] ?? 'storage',
                'capacity' => $data['capacity'] ?? null, 'dimensions' => $data['dimensions'] ?? null,
            ]);
            break;
        case 'shelf':
            $id = Database::insert('shelves', [
                'cabinet_id' => $data['cabinet_id'], 'name' => $data['name'],
                'code' => $data['code'] ?? null, 'level' => $data['level'] ?? 1,
                'capacity' => $data['capacity'] ?? null,
            ]);
            break;
        case 'slot':
            $id = Database::insert('slots', [
                'shelf_id' => $data['shelf_id'], 'name' => $data['name'],
                'code' => $data['code'] ?? null, 'position' => $data['position'] ?? 1,
            ]);
            break;
        default:
            throw new Exception('Invalid type');
    }
    echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => ucfirst($type) . ' created']);
}

// ═══════════════════════════════════
// PUT — Update
// ═══════════════════════════════════
function handlePut(string $action, array $user): void {
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    if ($roleLevel < 3) throw new Exception('Insufficient permission');

    $data = json_decode(file_get_contents('php://input'), true);
    $type = $data['type'] ?? $action ?? $_GET['type'] ?? '';
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) throw new Exception('ID required');

    $table = match($type) {
        'building' => 'buildings', 'room' => 'rooms', 'cabinet' => 'cabinets',
        'shelf' => 'shelves', 'slot' => 'slots',
        default => throw new Exception('Invalid type')
    };
    unset($data['type'], $data['id'], $data['created_at']);
    Database::update($table, $data, 'id = :id', [':id' => $id]);
    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' updated']);
}

// ═══════════════════════════════════
// DELETE
// ═══════════════════════════════════
function handleDelete(string $action, array $user): void {
    $roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
    if ($roleLevel < 3) throw new Exception('Insufficient permission');

    $type = $_GET['type'] ?? $action;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID required');

    $table = match($type) {
        'building' => 'buildings', 'room' => 'rooms', 'cabinet' => 'cabinets',
        'shelf' => 'shelves', 'slot' => 'slots',
        default => throw new Exception('Invalid type')
    };

    // Check children
    $childCheck = match($type) {
        'building' => ["rooms", "building_id"],
        'room' => ["cabinets", "room_id"],
        'cabinet' => ["shelves", "cabinet_id"],
        'shelf' => ["slots", "shelf_id"],
        'slot' => null,
        default => null
    };
    if ($childCheck) {
        $cnt = Database::fetch("SELECT COUNT(*) as c FROM {$childCheck[0]} WHERE {$childCheck[1]}=:id", [':id'=>$id])['c'];
        if ($cnt > 0) throw new Exception("Cannot delete: has $cnt child records");
    }
    if ($type === 'slot') {
        $cnt = Database::fetch("SELECT COUNT(*) as c FROM containers WHERE location_slot_id=:id AND status='active'", [':id'=>$id])['c'];
        if ($cnt > 0) throw new Exception("Cannot delete: slot has active containers");
    }

    Database::delete($table, 'id = :id', [':id' => $id]);
    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' deleted']);
}

// ═══════════════════════════════════
// Legacy: hierarchy for old locations page
// ═══════════════════════════════════
function getLocationHierarchy($labId): array {
    if (!$labId) {
        return Database::fetchAll("SELECT b.*, COUNT(r.id) as room_count FROM buildings b
            LEFT JOIN rooms r ON r.building_id=b.id GROUP BY b.id ORDER BY b.shortname");
    }
    $buildings = Database::fetchAll(
        "SELECT DISTINCT b.* FROM buildings b
         JOIN rooms r ON r.building_id=b.id JOIN cabinets c ON c.room_id=r.id
         JOIN shelves s ON s.cabinet_id=c.id JOIN slots sl ON sl.shelf_id=s.id
         JOIN containers cn ON cn.location_slot_id=sl.id
         WHERE cn.lab_id=:lid ORDER BY b.name", [':lid' => $labId]);

    foreach ($buildings as &$b) {
        $b['rooms'] = Database::fetchAll(
            "SELECT DISTINCT r.* FROM rooms r JOIN cabinets c ON c.room_id=r.id
             JOIN shelves s ON s.cabinet_id=c.id JOIN slots sl ON sl.shelf_id=s.id
             JOIN containers cn ON cn.location_slot_id=sl.id
             WHERE r.building_id=:bid AND cn.lab_id=:lid ORDER BY r.name",
            [':bid' => $b['id'], ':lid' => $labId]);
        foreach ($b['rooms'] as &$r) {
            $r['cabinets'] = Database::fetchAll(
                "SELECT c.*, (SELECT COUNT(*) FROM containers cn JOIN slots sl ON cn.location_slot_id=sl.id
                 JOIN shelves s ON sl.shelf_id=s.id WHERE s.cabinet_id=c.id AND cn.status='active') as container_count
                 FROM cabinets c WHERE c.room_id=:rid ORDER BY c.name", [':rid' => $r['id']]);
            foreach ($r['cabinets'] as &$c) {
                $c['shelves'] = Database::fetchAll(
                    "SELECT s.*, (SELECT COUNT(*) FROM containers cn JOIN slots sl ON cn.location_slot_id=sl.id
                     WHERE sl.shelf_id=s.id AND cn.status='active') as container_count
                     FROM shelves s WHERE s.cabinet_id=:cid ORDER BY s.level", [':cid' => $c['id']]);
            }
        }
    }
    return $buildings;
}

function getLocationsByType(string $type, array $filters): array {
    $parentId = $filters['parent_id'] ?? null;
    return match($type) {
        'buildings' => getBuildings(),
        'rooms' => getRooms(['building_id' => $filters['building_id'] ?? $parentId]),
        'cabinets' => getCabinets((int)($filters['room_id'] ?? $parentId ?? 0)),
        'shelves' => getShelves((int)($parentId ?? 0)),
        'slots' => getSlots((int)($parentId ?? 0)),
        'manufacturers' => Database::fetchAll("SELECT id,name FROM manufacturers ORDER BY name"),
        'funding' => Database::fetchAll("SELECT id,name,code FROM funding_sources ORDER BY name"),
        'departments' => Database::fetchAll("SELECT id,name,code,level FROM departments ORDER BY name"),
        default => throw new Exception('Invalid type')
    };
}
