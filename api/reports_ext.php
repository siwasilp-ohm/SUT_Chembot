<?php
/**
 * Extended Reports API
 *
 * GET ?action=yearly_movement  → Chemical movement grouped by Thai fiscal year
 * GET ?action=yearly_cost      → Chemical cost grouped by Thai fiscal year
 * GET ?action=room_report      → Detailed container list for a room/semester
 * GET ?action=rooms_list       → All rooms (for dropdown)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

$user = Auth::requireAuth();
$action = $_GET['action'] ?? '';

// Thai fiscal year expression (Oct = new FY)
// e.g. Oct 2019 → 2563, Sep 2020 → 2563
define('FY_EXPR', "CASE WHEN MONTH(%s) >= 10 THEN YEAR(%s)+544 ELSE YEAR(%s)+543 END");

function fy(string $col): string {
    return sprintf("CASE WHEN MONTH({$col}) >= 10 THEN YEAR({$col})+544 ELSE YEAR({$col})+543 END", $col, $col, $col);
}

// Convert various units to kg equivalents (rough)
define('KG_EXPR', "CASE
    WHEN unit IN ('kg','กิโลกรัม') THEN package_size
    WHEN unit IN ('g','กรัม')      THEN package_size/1000
    WHEN unit IN ('L','ลิตร')      THEN package_size
    WHEN unit IN ('mL','มิลลิลิตร') THEN package_size/1000
    ELSE 0 END");

define('REM_KG_EXPR', "CASE
    WHEN unit IN ('kg','กิโลกรัม') THEN remaining_qty
    WHEN unit IN ('g','กรัม')      THEN remaining_qty/1000
    WHEN unit IN ('L','ลิตร')      THEN remaining_qty
    WHEN unit IN ('mL','มิลลิลิตร') THEN remaining_qty/1000
    ELSE 0 END");

// LiqN2 name match
define('LIQ_N2_COND', "(chemical_name LIKE '%ไนโตรเจนเหลว%' OR chemical_name LIKE '%Liquid Nitrogen%' OR chemical_name LIKE '%Liq. N2%' OR chemical_name LIKE '%liquid N2%' OR chemical_name LIKE '%LN2%')");

try {
    switch ($action) {

        // ─── 1. Yearly Movement ───────────────────────────────────────────
        case 'yearly_movement':
            $fyCol = fy('created_at');
            $rows = Database::fetchAll("
                SELECT
                    {$fyCol} as fiscal_year,
                    ROUND(SUM(" . KG_EXPR . "), 2)                        as total_imported_kg,
                    ROUND(SUM(CASE WHEN " . LIQ_N2_COND . " THEN " . KG_EXPR . " ELSE 0 END), 2) as liq_n2_kg,
                    ROUND(SUM(CASE WHEN NOT(" . LIQ_N2_COND . ") THEN " . KG_EXPR . " ELSE 0 END), 2) as chem_imported_kg,
                    ROUND(SUM(" . REM_KG_EXPR . "), 2)                    as chem_remaining_kg
                FROM chemical_stock
                GROUP BY {$fyCol}
                ORDER BY fiscal_year
            ");

            // Also pull from containers table
            $fyColC = fy('cn.created_at');
            $rowsC = Database::fetchAll("
                SELECT
                    {$fyColC} as fiscal_year,
                    ROUND(SUM(CASE
                        WHEN cn.quantity_unit IN ('kg') THEN cn.initial_quantity
                        WHEN cn.quantity_unit IN ('g') THEN cn.initial_quantity/1000
                        WHEN cn.quantity_unit IN ('L','mL') THEN cn.initial_quantity/1000
                        ELSE 0 END), 2) as total_imported_kg,
                    0 as liq_n2_kg,
                    ROUND(SUM(CASE
                        WHEN cn.quantity_unit IN ('kg') THEN cn.initial_quantity
                        WHEN cn.quantity_unit IN ('g') THEN cn.initial_quantity/1000
                        WHEN cn.quantity_unit IN ('L','mL') THEN cn.initial_quantity/1000
                        ELSE 0 END), 2) as chem_imported_kg,
                    ROUND(SUM(CASE
                        WHEN cn.quantity_unit IN ('kg') THEN cn.current_quantity
                        WHEN cn.quantity_unit IN ('g') THEN cn.current_quantity/1000
                        WHEN cn.quantity_unit IN ('L','mL') THEN cn.current_quantity/1000
                        ELSE 0 END), 2) as chem_remaining_kg
                FROM containers cn
                WHERE cn.is_active = 1 AND cn.initial_quantity IS NOT NULL
                GROUP BY {$fyColC}
                ORDER BY fiscal_year
            ");

            // Merge by fiscal year
            $merged = [];
            foreach ($rows as $r) {
                $fy = (int)$r['fiscal_year'];
                $merged[$fy] = [
                    'fiscal_year'       => $fy,
                    'total_imported_kg' => (float)$r['total_imported_kg'],
                    'liq_n2_kg'         => (float)$r['liq_n2_kg'],
                    'chem_imported_kg'  => (float)$r['chem_imported_kg'],
                    'chem_remaining_kg' => (float)$r['chem_remaining_kg'],
                ];
            }
            foreach ($rowsC as $r) {
                $fy = (int)$r['fiscal_year'];
                if (isset($merged[$fy])) {
                    $merged[$fy]['total_imported_kg']  += (float)$r['total_imported_kg'];
                    $merged[$fy]['chem_imported_kg']   += (float)$r['chem_imported_kg'];
                    $merged[$fy]['chem_remaining_kg']  += (float)$r['chem_remaining_kg'];
                } else {
                    $merged[$fy] = [
                        'fiscal_year'       => $fy,
                        'total_imported_kg' => (float)$r['total_imported_kg'],
                        'liq_n2_kg'         => 0,
                        'chem_imported_kg'  => (float)$r['chem_imported_kg'],
                        'chem_remaining_kg' => (float)$r['chem_remaining_kg'],
                    ];
                }
            }
            ksort($merged);
            echo json_encode(['success' => true, 'data' => array_values($merged)]);
            break;

        // ─── 2. Yearly Cost ───────────────────────────────────────────────
        case 'yearly_cost':
            // chemical_stock has no cost column — containers only
            $fyColC = fy('cn.created_at');
            $liqCond = "(ch.name LIKE '%ไนโตรเจนเหลว%' OR ch.name LIKE '%Liquid Nitrogen%' OR ch.name LIKE '%Liq. N2%' OR ch.name LIKE '%liquid N2%' OR ch.name LIKE '%LN2%')";
            $rowsC = Database::fetchAll("
                SELECT
                    {$fyColC} as fiscal_year,
                    COUNT(cn.id)                                                                          as container_count,
                    ROUND(SUM(COALESCE(cn.cost, 0)), 2)                                                   as total_cost,
                    ROUND(SUM(CASE WHEN {$liqCond}  THEN COALESCE(cn.cost, 0) ELSE 0 END), 2)            as liq_n2_cost,
                    ROUND(SUM(CASE WHEN NOT({$liqCond}) THEN COALESCE(cn.cost, 0) ELSE 0 END), 2)        as chem_cost,
                    SUM(CASE WHEN cn.cost > 0 THEN 1 ELSE 0 END)                                          as priced_count
                FROM containers cn
                LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
                WHERE cn.is_active = 1
                GROUP BY {$fyColC}
                ORDER BY fiscal_year
            ");
            $rows = [];

            $merged = [];
            foreach ($rowsC as $r) {
                $fy = (int)$r['fiscal_year'];
                $merged[$fy] = [
                    'fiscal_year'     => $fy,
                    'container_count' => (int)$r['container_count'],
                    'total_cost'      => (float)$r['total_cost'],
                    'liq_n2_cost'     => (float)$r['liq_n2_cost'],
                    'chem_cost'       => (float)$r['chem_cost'],
                    'priced_count'    => (int)$r['priced_count'],
                ];
            }
            ksort($merged);
            echo json_encode(['success' => true, 'data' => array_values($merged)]);
            break;

        // ─── 2b. Cost by Room ─────────────────────────────────────────────
        case 'cost_by_room':
            $rooms = Database::fetchAll("
                SELECT
                    rm.id, rm.name, rm.code, rm.floor,
                    b.name       as building_name,
                    b.shortname  as building_short,
                    COUNT(cn.id)                                        as container_count,
                    ROUND(SUM(COALESCE(cn.cost, 0)), 2)                 as total_cost,
                    ROUND(AVG(CASE WHEN cn.cost > 0 THEN cn.cost END), 2) as avg_cost,
                    SUM(CASE WHEN cn.cost > 0 THEN 1 ELSE 0 END)        as priced_count
                FROM rooms rm
                LEFT JOIN buildings b  ON rm.building_id = b.id
                LEFT JOIN containers cn ON cn.room_id = rm.id AND cn.is_active = 1
                GROUP BY rm.id
                ORDER BY total_cost DESC, container_count DESC
            ");
            echo json_encode(['success' => true, 'data' => $rooms]);
            break;

        // ─── 2c. Cost by Chemical ─────────────────────────────────────────
        case 'cost_by_chemical':
            $chems = Database::fetchAll("
                SELECT
                    COALESCE(ch.name, '(ไม่ระบุสาร)')                    as chemical_name,
                    ch.cas_number,
                    COUNT(cn.id)                                           as container_count,
                    ROUND(SUM(COALESCE(cn.cost, 0)), 2)                    as total_cost,
                    ROUND(AVG(CASE WHEN cn.cost > 0 THEN cn.cost END), 2)  as avg_cost,
                    SUM(CASE WHEN cn.cost > 0 THEN 1 ELSE 0 END)           as priced_count
                FROM containers cn
                LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
                WHERE cn.is_active = 1
                GROUP BY ch.id, ch.name, ch.cas_number
                ORDER BY total_cost DESC, container_count DESC
                LIMIT 50
            ");
            echo json_encode(['success' => true, 'data' => $chems]);
            break;

        // ─── 3. Room Report ───────────────────────────────────────────────
        case 'room_report':
            $roomId   = (int)($_GET['room_id'] ?? 0); // 0 = all rooms
            $semester = (int)($_GET['semester'] ?? 0);
            $year     = (int)($_GET['year'] ?? 0);    // Thai BE year

            // Build semester date range filter
            $dateWhere = '';
            $dateBind  = [];
            if ($year > 0 && $semester > 0) {
                $ceYear = $year - 543;
                if ($semester === 1) {
                    $dateWhere = 'AND (cn.received_date BETWEEN :d1 AND :d2 OR cn.created_at BETWEEN :d3 AND :d4)';
                    $dateBind[':d1'] = "{$ceYear}-06-01"; $dateBind[':d2'] = "{$ceYear}-10-31";
                    $dateBind[':d3'] = "{$ceYear}-06-01"; $dateBind[':d4'] = "{$ceYear}-10-31";
                } else {
                    $dateWhere = 'AND (cn.received_date BETWEEN :d1 AND :d2 OR cn.created_at BETWEEN :d3 AND :d4)';
                    $dateBind[':d1'] = "{$ceYear}-11-01"; $dateBind[':d2'] = ($ceYear+1)."-02-28";
                    $dateBind[':d3'] = "{$ceYear}-11-01"; $dateBind[':d4'] = ($ceYear+1)."-02-28";
                }
            }

            // Room filter: 0 = all rooms, >0 = specific room
            $roomWhere = '';
            if ($roomId > 0) {
                $roomWhere = 'AND cn.room_id = :room_id';
                $dateBind[':room_id'] = $roomId;
            }

            $items = Database::fetchAll("
                SELECT
                    cn.id,
                    cn.bottle_code,
                    ch.name                                          as chemical_name,
                    ch.cas_number,
                    cn.grade,
                    cn.initial_quantity,
                    cn.current_quantity,
                    cn.quantity_unit,
                    cn.received_date,
                    cn.created_at,
                    CONCAT(u.first_name,' ',u.last_name)             as added_by,
                    rm.name                                          as room_name,
                    rm.code                                          as room_code,
                    b.shortname                                      as building_short,
                    CONCAT(u2.first_name,' ',u2.last_name)           as responsible_person,
                    cn.cost                                          as price,
                    ch.hazard_pictograms,
                    ch.signal_word,
                    gd.h_statements                                  as hazard_statements,
                    cn.expiry_date,
                    cn.notes
                FROM containers cn
                LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
                LEFT JOIN users u  ON cn.created_by = u.id
                LEFT JOIN users u2 ON cn.owner_id = u2.id
                LEFT JOIN rooms rm ON cn.room_id = rm.id
                LEFT JOIN buildings b ON rm.building_id = b.id
                LEFT JOIN chemical_ghs_data gd ON gd.chemical_id = ch.id
                WHERE cn.is_active = 1 {$roomWhere} {$dateWhere}
                ORDER BY b.shortname ASC, rm.name ASC, cn.bottle_code ASC, cn.created_at ASC
            ", $dateBind);

            // Room info (null when all rooms)
            $roomInfo = null;
            if ($roomId > 0) {
                $roomInfo = Database::fetch("
                    SELECT rm.id, rm.name, rm.code, rm.floor,
                           b.name as building_name, b.shortname as building_short
                    FROM rooms rm
                    LEFT JOIN buildings b ON rm.building_id = b.id
                    WHERE rm.id = :id
                ", [':id' => $roomId]);
            }

            foreach ($items as &$item) {
                $item['hazard_pictograms'] = json_decode($item['hazard_pictograms'] ?? '[]', true) ?? [];
            }

            echo json_encode(['success' => true, 'data' => $items, 'room' => $roomInfo, 'all_rooms' => $roomId === 0]);
            break;

        // ─── 4. Rooms List ────────────────────────────────────────────────
        case 'rooms_list':
            $rooms = Database::fetchAll("
                SELECT rm.id, rm.name, rm.code, rm.floor,
                       b.name as building_name, b.shortname as building_short,
                       COUNT(cn.id) as container_count
                FROM rooms rm
                LEFT JOIN buildings b ON rm.building_id = b.id
                LEFT JOIN containers cn ON cn.room_id = rm.id AND cn.is_active = 1
                GROUP BY rm.id
                ORDER BY b.shortname, rm.floor, rm.name
            ");
            echo json_encode(['success' => true, 'data' => $rooms]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
