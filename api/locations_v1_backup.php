<?php
/**
 * Location Management API - Hierarchical Structure
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::requireAuth();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['hierarchy'])) {
                $data = getLocationHierarchy($_GET['lab_id'] ?? $user['lab_id']);
                echo json_encode(['success' => true, 'data' => $data]);
            } elseif (isset($_GET['type'])) {
                $data = getLocationsByType($_GET['type'], $_GET);
                echo json_encode(['success' => true, 'data' => $data]);
            } elseif (isset($_GET['id'])) {
                $data = getLocationDetails((int)$_GET['id'], $_GET['type'] ?? 'slot');
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                $data = listAllLocations($_GET);
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'POST':
            Auth::requirePermission('chemicals.manage');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = createLocation($data, $user);
            echo json_encode(['success' => true, 'data' => ['id' => $id]]);
            break;
            
        case 'PUT':
            Auth::requirePermission('chemicals.manage');
            $data = json_decode(file_get_contents('php://input'), true);
            updateLocation($data['type'], (int)$_GET['id'], $data, $user);
            echo json_encode(['success' => true, 'message' => 'Location updated']);
            break;
            
        case 'DELETE':
            Auth::requirePermission('chemicals.manage');
            deleteLocation($_GET['type'], (int)$_GET['id'], $user);
            echo json_encode(['success' => true, 'message' => 'Location deleted']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getLocationHierarchy(?int $labId): array {
    if (!$labId) {
        throw new Exception('Lab ID required');
    }
    
    $buildings = Database::fetchAll(
        "SELECT b.* FROM buildings b
         JOIN rooms r ON r.building_id = b.id
         JOIN cabinets c ON c.room_id = r.id
         JOIN shelves s ON s.cabinet_id = c.id
         JOIN slots sl ON sl.shelf_id = s.id
         JOIN containers cn ON cn.location_slot_id = sl.id
         WHERE cn.lab_id = :lab_id
         GROUP BY b.id
         ORDER BY b.name",
        [':lab_id' => $labId]
    );
    
    foreach ($buildings as &$building) {
        $building['rooms'] = Database::fetchAll(
            "SELECT r.* FROM rooms r
             JOIN cabinets c ON c.room_id = r.id
             JOIN shelves s ON s.cabinet_id = c.id
             JOIN slots sl ON sl.shelf_id = s.id
             JOIN containers cn ON cn.location_slot_id = sl.id
             WHERE r.building_id = :building_id AND cn.lab_id = :lab_id
             GROUP BY r.id",
            [':building_id' => $building['id'], ':lab_id' => $labId]
        );
        
        foreach ($building['rooms'] as &$room) {
            $room['cabinets'] = Database::fetchAll(
                "SELECT c.*, 
                        (SELECT COUNT(*) FROM containers cn 
                         JOIN slots sl ON cn.location_slot_id = sl.id 
                         JOIN shelves s ON sl.shelf_id = s.id 
                         WHERE s.cabinet_id = c.id AND cn.status = 'active') as container_count
                 FROM cabinets c
                 WHERE c.room_id = :room_id",
                [':room_id' => $room['id']]
            );
            
            foreach ($room['cabinets'] as &$cabinet) {
                $cabinet['shelves'] = Database::fetchAll(
                    "SELECT s.*,
                            (SELECT COUNT(*) FROM containers cn 
                             JOIN slots sl ON cn.location_slot_id = sl.id 
                             WHERE sl.shelf_id = s.id AND cn.status = 'active') as container_count
                     FROM shelves s
                     WHERE s.cabinet_id = :cabinet_id",
                    [':cabinet_id' => $cabinet['id']]
                );
                
                foreach ($cabinet['shelves'] as &$shelf) {
                    $shelf['slots'] = Database::fetchAll(
                        "SELECT sl.*,
                                (SELECT COUNT(*) FROM containers cn 
                                 WHERE cn.location_slot_id = sl.id AND cn.status = 'active') as container_count,
                                (SELECT c.name FROM containers cn 
                                 JOIN chemicals c ON cn.chemical_id = c.id
                                 WHERE cn.location_slot_id = sl.id AND cn.status = 'active' LIMIT 1) as chemical_name
                         FROM slots sl
                         WHERE sl.shelf_id = :shelf_id",
                        [':shelf_id' => $shelf['id']]
                    );
                }
            }
        }
    }
    
    return $buildings;
}

function getLocationsByType(string $type, array $filters): array {
    $parentId = $filters['parent_id'] ?? null;
    
    switch ($type) {
        case 'buildings':
            return Database::fetchAll("SELECT id, name, shortname, code FROM buildings ORDER BY name");
            
        case 'rooms':
            $buildingId = $filters['building_id'] ?? $parentId;
            if (!$buildingId) return [];
            return Database::fetchAll(
                "SELECT r.id, r.name, r.code, r.room_number, r.floor, b.name as building_name 
                 FROM rooms r 
                 JOIN buildings b ON r.building_id = b.id
                 WHERE r.building_id = :parent_id ORDER BY r.name",
                [':parent_id' => $buildingId]
            );
            
        case 'cabinets':
            $roomId = $filters['room_id'] ?? $parentId;
            if (!$roomId) return [];
            return Database::fetchAll(
                "SELECT c.id, c.name, c.code, c.type, r.name as room_name
                 FROM cabinets c
                 JOIN rooms r ON c.room_id = r.id
                 WHERE c.room_id = :parent_id ORDER BY c.name",
                [':parent_id' => $roomId]
            );
            
        case 'shelves':
            return Database::fetchAll(
                "SELECT s.*, c.name as cabinet_name
                 FROM shelves s
                 JOIN cabinets c ON s.cabinet_id = c.id
                 WHERE s.cabinet_id = :parent_id ORDER BY s.level",
                [':parent_id' => $parentId]
            );
            
        case 'slots':
            return Database::fetchAll(
                "SELECT sl.*, s.name as shelf_name,
                        (SELECT COUNT(*) FROM containers WHERE location_slot_id = sl.id AND status = 'active') as occupied
                 FROM slots sl
                 JOIN shelves s ON sl.shelf_id = s.id
                 WHERE sl.shelf_id = :parent_id ORDER BY sl.position",
                [':parent_id' => $parentId]
            );
            
        case 'manufacturers':
            return Database::fetchAll("SELECT id, name FROM manufacturers ORDER BY name");
            
        case 'funding':
            return Database::fetchAll("SELECT id, name, code FROM funding_sources ORDER BY name");
            
        case 'departments':
            return Database::fetchAll("SELECT id, name, code, level FROM departments ORDER BY name");
            
        default:
            throw new Exception('Invalid location type');
    }
}

function getLocationDetails(int $id, string $type): array {
    switch ($type) {
        case 'building':
            return Database::fetch("SELECT * FROM buildings WHERE id = :id", [':id' => $id]);
        case 'room':
            return Database::fetch(
                "SELECT r.*, b.name as building_name 
                 FROM rooms r JOIN buildings b ON r.building_id = b.id 
                 WHERE r.id = :id", 
                [':id' => $id]
            );
        case 'cabinet':
            return Database::fetch(
                "SELECT c.*, r.name as room_name, b.name as building_name
                 FROM cabinets c
                 JOIN rooms r ON c.room_id = r.id
                 JOIN buildings b ON r.building_id = b.id
                 WHERE c.id = :id",
                [':id' => $id]
            );
        case 'shelf':
            return Database::fetch(
                "SELECT s.*, c.name as cabinet_name, r.name as room_name
                 FROM shelves s
                 JOIN cabinets c ON s.cabinet_id = c.id
                 JOIN rooms r ON c.room_id = r.id
                 WHERE s.id = :id",
                [':id' => $id]
            );
        case 'slot':
            $slot = Database::fetch(
                "SELECT sl.*, s.name as shelf_name, c.name as cabinet_name, 
                        r.name as room_name, b.name as building_name,
                        r.floor_plan_svg, c.svg_data as cabinet_svg
                 FROM slots sl
                 JOIN shelves s ON sl.shelf_id = s.id
                 JOIN cabinets c ON s.cabinet_id = c.id
                 JOIN rooms r ON c.room_id = r.id
                 JOIN buildings b ON r.building_id = b.id
                 WHERE sl.id = :id",
                [':id' => $id]
            );
            
            // Get container in this slot
            $slot['container'] = Database::fetch(
                "SELECT cn.*, c.name as chemical_name, c.cas_number, c.hazard_pictograms
                 FROM containers cn
                 JOIN chemicals c ON cn.chemical_id = c.id
                 WHERE cn.location_slot_id = :slot_id AND cn.status = 'active'",
                [':slot_id' => $id]
            );
            
            return $slot;
            
        default:
            throw new Exception('Invalid location type');
    }
}

function createLocation(array $data, array $user): int {
    $type = $data['type'] ?? throw new Exception('Location type required');
    
    switch ($type) {
        case 'building':
            return Database::insert('buildings', [
                'organization_id' => $data['organization_id'] ?? $user['organization_id'],
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'address' => $data['address'] ?? null
            ]);
            
        case 'room':
            return Database::insert('rooms', [
                'building_id' => $data['building_id'],
                'lab_id' => $data['lab_id'] ?? null,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'floor' => $data['floor'] ?? 1,
                'safety_level' => $data['safety_level'] ?? 'general',
                'temperature_controlled' => $data['temperature_controlled'] ?? false,
                'humidity_controlled' => $data['humidity_controlled'] ?? false
            ]);
            
        case 'cabinet':
            return Database::insert('cabinets', [
                'room_id' => $data['room_id'],
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'type' => $data['cabinet_type'] ?? 'storage',
                'capacity' => $data['capacity'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'temperature_min' => $data['temperature_min'] ?? null,
                'temperature_max' => $data['temperature_max'] ?? null,
                'ventilation' => $data['ventilation'] ?? false,
                'fire_resistant' => $data['fire_resistant'] ?? false,
                'position_x' => $data['position_x'] ?? null,
                'position_y' => $data['position_y'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null
            ]);
            
        case 'shelf':
            return Database::insert('shelves', [
                'cabinet_id' => $data['cabinet_id'],
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'level' => $data['level'] ?? 1,
                'capacity' => $data['capacity'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'max_weight' => $data['max_weight'] ?? null
            ]);
            
        case 'slot':
            return Database::insert('slots', [
                'shelf_id' => $data['shelf_id'],
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'position' => $data['position'] ?? 1,
                'dimensions' => $data['dimensions'] ?? null,
                'position_x' => $data['position_x'] ?? null,
                'position_y' => $data['position_y'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null
            ]);
            
        default:
            throw new Exception('Invalid location type');
    }
}

function updateLocation(string $type, int $id, array $data, array $user): void {
    $table = match($type) {
        'building' => 'buildings',
        'room' => 'rooms',
        'cabinet' => 'cabinets',
        'shelf' => 'shelves',
        'slot' => 'slots',
        default => throw new Exception('Invalid location type')
    };
    
    unset($data['type']);
    Database::update($table, $data, 'id = :id', [':id' => $id]);
}

function deleteLocation(string $type, int $id, array $user): void {
    $table = match($type) {
        'building' => 'buildings',
        'room' => 'rooms',
        'cabinet' => 'cabinets',
        'shelf' => 'shelves',
        'slot' => 'slots',
        default => throw new Exception('Invalid location type')
    };
    
    // Check for associated containers
    $hasContainers = false;
    if ($type === 'slot') {
        $count = Database::fetch(
            "SELECT COUNT(*) as count FROM containers WHERE location_slot_id = :id",
            [':id' => $id]
        )['count'];
        $hasContainers = $count > 0;
    }
    
    if ($hasContainers) {
        throw new Exception('Cannot delete location with associated containers');
    }
    
    Database::delete($table, 'id = :id', [':id' => $id]);
}

function listAllLocations(array $filters): array {
    $labId = $filters['lab_id'] ?? null;
    
    return [
        'buildings' => Database::fetchAll("SELECT * FROM buildings ORDER BY name"),
        'rooms' => Database::fetchAll(
            "SELECT r.*, b.name as building_name FROM rooms r 
             JOIN buildings b ON r.building_id = b.id ORDER BY r.name"
        ),
        'cabinets' => Database::fetchAll(
            "SELECT c.*, r.name as room_name FROM cabinets c
             JOIN rooms r ON c.room_id = r.id ORDER BY c.name"
        ),
        'shelves' => Database::fetchAll(
            "SELECT s.*, c.name as cabinet_name FROM shelves s
             JOIN cabinets c ON s.cabinet_id = c.id ORDER BY c.name, s.level"
        ),
        'slots' => Database::fetchAll(
            "SELECT sl.*, s.name as shelf_name, c.name as cabinet_name 
             FROM slots sl
             JOIN shelves s ON sl.shelf_id = s.id
             JOIN cabinets c ON s.cabinet_id = c.id
             ORDER BY c.name, s.name, sl.position"
        )
    ];
}
