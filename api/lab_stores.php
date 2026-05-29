<?php
/**
 * Lab Stores API — คลังสารเคมีแยกตามฝ่าย/งาน
 *
 * GET  ?action=dashboard       Dashboard stats
 * GET  ?action=list            List all lab stores
 * GET  ?action=detail&id=N     Single store detail
 * GET  ?action=divisions       List distinct divisions (ฝ่าย)
 * GET  ?action=sections        List distinct sections (งาน) [optional: division=xxx]
 * GET  ?action=report          Report grouped by division/section
 * POST ?action=save            Create or update lab store
 * POST ?action=delete          Soft-delete
 * POST ?action=import_csv      Import from CSV
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user   = Auth::requireAuth();
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin   = $roleLevel >= 5;
$isManager = $roleLevel >= 3;

try {
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        switch ($action) {
            case 'dashboard':       echo json_encode(['success'=>true,'data'=>getDashboard()]); break;
            case 'list':            echo json_encode(['success'=>true,'data'=>listStores($_GET)]); break;
            case 'detail':          echo json_encode(['success'=>true,'data'=>getDetail((int)($_GET['id']??0))]); break;
            case 'divisions':       echo json_encode(['success'=>true,'data'=>getDivisions()]); break;
            case 'sections':        echo json_encode(['success'=>true,'data'=>getSections($_GET)]); break;
            case 'report':          echo json_encode(['success'=>true,'data'=>getReport($_GET)]); break;
            case 'drill_division':  echo json_encode(['success'=>true,'data'=>drillDivision($_GET)]); break;
            case 'drill_section':   echo json_encode(['success'=>true,'data'=>drillSection($_GET)]); break;
            case 'drill_store':     echo json_encode(['success'=>true,'data'=>drillStore($_GET)]); break;
            case 'drill_holder':    echo json_encode(['success'=>true,'data'=>drillHolder($_GET)]); break;
            case 'stock_detail':    echo json_encode(['success'=>true,'data'=>stockDetail($_GET)]); break;
            case 'hierarchy':       echo json_encode(['success'=>true,'data'=>getHierarchy()]); break;
            case 'export_csv':      exportCSV(); break;
            default: throw new Exception('Unknown action: '.$action);
        }
    } elseif ($method === 'POST') {
        if (!$isManager) throw new Exception('Permission denied', 403);
        // import_csv uses multipart form, others use JSON body
        if ($action === 'import_csv') {
            echo json_encode(['success'=>true,'data'=>importCSVFromFile()]);
        } else {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            switch ($action) {
                case 'save':        echo json_encode(['success'=>true,'data'=>saveStore($data, $user)]); break;
                case 'delete':      echo json_encode(['success'=>true,'data'=>deleteStore($data)]); break;
                case 'bulk_delete': echo json_encode(['success'=>true,'data'=>bulkDelete($data)]); break;
                case 'import_csv_legacy': echo json_encode(['success'=>true,'data'=>importCSV()]); break;
                default: throw new Exception('Unknown POST action: '.$action);
            }
        }
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    http_response_code($code);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

// ========== HELPERS: Live Stats Computation ==========

/**
 * SQL fragment to convert remaining_qty to kg equivalent
 */
function unitToKgSQL(string $qtyCol = 'cs.remaining_qty', string $unitCol = 'cs.unit'): string {
    return "{$qtyCol} *
        CASE WHEN {$unitCol} IN ('kg','Kg','KG','กิโลกรัม') THEN 1
             WHEN {$unitCol} IN ('g','G','gram','กรัม') THEN 0.001
             WHEN {$unitCol} IN ('mg','มิลลิกรัม') THEN 0.000001
             WHEN {$unitCol} IN ('L','l','Liter','ลิตร') THEN 1
             WHEN {$unitCol} IN ('mL','ml','ML','มิลลิลิตร') THEN 0.001
             ELSE 1 END";
}

/**
 * Build SQL conditions + params to find users that belong to a given division.
 * Returns ['sql' => '(condition)', 'params' => [...]]
 *
 * Mapping logic (based on real data):
 *  Pattern 1: user.department = division_name (ฝ่าย...), user.position = section_name (งาน...)
 *  Pattern 2: user.department = section_name (งาน...), typically for engineering divisions
 */
function buildUserMatchForDivision(string $division, string $prefix = 'dv'): array {
    // Get sections under this division
    $sections = Database::fetchAll(
        "SELECT DISTINCT section_name FROM lab_stores WHERE is_active = 1 AND division_name = :div",
        [':div' => $division]
    );
    
    $conditions = [];
    $params = [];

    // Pattern 1: u.department = division_name
    $conditions[] = "u.department = :{$prefix}_div";
    $params[":{$prefix}_div"] = $division;

    // Pattern 2: u.department matches any section_name under this division
    foreach ($sections as $i => $sec) {
        $conditions[] = "u.department = :{$prefix}_sec{$i}";
        $params[":{$prefix}_sec{$i}"] = $sec['section_name'];
    }

    return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
}

/**
 * Build SQL conditions + params to find users that belong to a specific section.
 * Returns ['sql' => '(condition)', 'params' => [...]]
 *
 * Mapping logic:
 *  Pattern 1: u.department = division_name AND u.position = section_name
 *  Pattern 2: u.department = section_name
 */
function buildUserMatchForSection(string $division, string $section, string $prefix = 'sc'): array {
    $conditions = [];
    $params = [];

    // Pattern 1: dept = division AND pos = section
    $conditions[] = "(u.department = :{$prefix}_div AND u.position = :{$prefix}_sec)";
    $params[":{$prefix}_div"] = $division;
    $params[":{$prefix}_sec"] = $section;

    // Pattern 2: dept = section (for engineering depts where dept IS the section)
    $conditions[] = "(u.department = :{$prefix}_secB)";
    $params[":{$prefix}_secB"] = $section;

    return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
}

/**
 * Get live stats (bottles, chemicals, weight) from chemical_stock for a given WHERE condition on users.
 * @param string $userCondSQL  e.g. "(u.department = :dv_div OR u.department = :dv_sec0)"
 * @param array  $params       named parameters for the condition
 * @return array {bottle_count, chemical_count, total_weight_kg, holder_count}
 */
function getLiveStats(string $userCondSQL, array $params): array {
    $kgExpr = unitToKgSQL();
    $row = Database::fetch("
        SELECT COUNT(cs.id) as bottle_count,
               COUNT(DISTINCT cs.chemical_name) as chemical_count,
               COALESCE(ROUND(SUM({$kgExpr}), 4), 0) as total_weight_kg,
               COUNT(DISTINCT u.id) as holder_count
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low') AND {$userCondSQL}",
        $params);
    return [
        'bottle_count'    => (int)($row['bottle_count'] ?? 0),
        'chemical_count'  => (int)($row['chemical_count'] ?? 0),
        'total_weight_kg' => round((float)($row['total_weight_kg'] ?? 0), 4),
        'holder_count'    => (int)($row['holder_count'] ?? 0)
    ];
}

// ========== DASHBOARD ==========
function getDashboard(): array {
    // Structure counts from lab_stores table
    $structureTotals = Database::fetch("
        SELECT COUNT(*) as total_stores,
               COUNT(DISTINCT division_name) as total_divisions,
               COUNT(DISTINCT section_name) as total_sections
        FROM lab_stores WHERE is_active = 1");

    // Live totals from chemical_stock
    $kgExpr = unitToKgSQL();
    $liveTotals = Database::fetch("
        SELECT COUNT(cs.id) as total_bottles,
               COUNT(DISTINCT cs.chemical_name) as total_chemicals,
               COALESCE(ROUND(SUM({$kgExpr}), 2), 0) as total_weight_kg,
               COUNT(DISTINCT u.id) as total_holders
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low')");

    $totals = [
        'total_stores'    => (int)($structureTotals['total_stores'] ?? 0),
        'total_divisions' => (int)($structureTotals['total_divisions'] ?? 0),
        'total_sections'  => (int)($structureTotals['total_sections'] ?? 0),
        'total_bottles'   => (int)($liveTotals['total_bottles'] ?? 0),
        'total_chemicals' => (int)($liveTotals['total_chemicals'] ?? 0),
        'total_weight_kg' => round((float)($liveTotals['total_weight_kg'] ?? 0), 2),
        'total_holders'   => (int)($liveTotals['total_holders'] ?? 0),
        'active_stores'   => (int)($structureTotals['total_stores'] ?? 0),
        'empty_stores'    => 0
    ];

    // Division summary — live from chemical_stock
    $divisions = Database::fetchAll("
        SELECT DISTINCT division_name FROM lab_stores WHERE is_active = 1 ORDER BY division_name");

    $divisionSummary = [];
    foreach ($divisions as $div) {
        $dn = $div['division_name'];
        $match = buildUserMatchForDivision($dn, 'dash');
        $live = getLiveStats($match['sql'], $match['params']);

        $structCounts = Database::fetch("
            SELECT COUNT(*) as store_count,
                   COUNT(DISTINCT section_name) as section_count
            FROM lab_stores WHERE is_active = 1 AND division_name = :div",
            [':div' => $dn]);

        $divisionSummary[] = [
            'division_name'   => $dn,
            'store_count'     => (int)($structCounts['store_count'] ?? 0),
            'section_count'   => (int)($structCounts['section_count'] ?? 0),
            'total_bottles'   => $live['bottle_count'],
            'total_chemicals' => $live['chemical_count'],
            'total_weight_kg' => round($live['total_weight_kg'], 2),
            'holder_count'    => $live['holder_count']
        ];
    }
    // Sort by weight desc
    usort($divisionSummary, fn($a, $b) => $b['total_weight_kg'] <=> $a['total_weight_kg']);

    // Top 10 sections by weight (sections = งาน, the real grouping level for stock)
    $allSections = Database::fetchAll("
        SELECT DISTINCT division_name, section_name FROM lab_stores WHERE is_active = 1");

    // Compute section stats once, reuse for topByWeight AND sectionSummary
    $sectionSummary = [];
    foreach ($allSections as $sec) {
        $match = buildUserMatchForSection($sec['division_name'], $sec['section_name'], 'top');
        $live = getLiveStats($match['sql'], $match['params']);
        $storeCount = Database::fetch("
            SELECT COUNT(*) as cnt FROM lab_stores 
            WHERE is_active = 1 AND division_name = :div AND section_name = :sec",
            [':div' => $sec['division_name'], ':sec' => $sec['section_name']])['cnt'] ?? 0;
        $sectionSummary[] = [
            'id'              => 0,
            'store_name'      => $sec['section_name'],
            'division_name'   => $sec['division_name'],
            'section_name'    => $sec['section_name'],
            'bottle_count'    => $live['bottle_count'],
            'chemical_count'  => $live['chemical_count'],
            'total_weight_kg' => round($live['total_weight_kg'], 2),
            'total_bottles'   => $live['bottle_count'],
            'total_chemicals' => $live['chemical_count'],
            'store_count'     => (int)$storeCount
        ];
    }
    usort($sectionSummary, fn($a, $b) => $b['total_weight_kg'] <=> $a['total_weight_kg']);
    $topByWeight = array_slice(array_filter($sectionSummary, fn($s) => $s['bottle_count'] > 0), 0, 10);

    return [
        'totals'          => $totals,
        'topByWeight'     => $topByWeight,
        'divisionSummary' => $divisionSummary,
        'sectionSummary'  => $sectionSummary
    ];
}

// ========== LIST ==========
function listStores(array $f): array {
    $where  = ['ls.is_active = 1'];
    $params = [];

    if (!empty($f['division'])) {
        $where[] = "ls.division_name = :div";
        $params[':div'] = $f['division'];
    }
    if (!empty($f['section'])) {
        $where[] = "ls.section_name = :sec";
        $params[':sec'] = $f['section'];
    }
    if (!empty($f['search'])) {
        $where[] = "(ls.store_name LIKE :s OR ls.division_name LIKE :s OR ls.section_name LIKE :s)";
        $params[':s'] = '%'.$f['search'].'%';
    }
    if (isset($f['has_stock']) && $f['has_stock'] === '1') {
        $where[] = "ls.bottle_count > 0";
    }

    $whereSQL = implode(' AND ', $where);
    $page    = max(1, (int)($f['page'] ?? 1));
    $perPage = min(100, max(10, (int)($f['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    $sortCol = $f['sort'] ?? 'total_weight_kg';
    $allowedSorts = ['id','store_name','center_name','division_name','section_name','bottle_count','chemical_count','total_weight_kg'];
    if (!in_array($sortCol, $allowedSorts)) $sortCol = 'total_weight_kg';
    $sortDir = (strtolower($f['sort_dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

    $rows = Database::fetchAll("
        SELECT ls.*
        FROM lab_stores ls
        WHERE {$whereSQL}
        ORDER BY {$sortCol} {$sortDir}
        LIMIT :lim OFFSET :off",
        array_merge($params, [':lim'=>$perPage, ':off'=>$offset]));

    // Enrich each store with live stats from chemical_stock (section-level)
    $sectionCache = [];
    foreach ($rows as &$row) {
        $cacheKey = $row['division_name'] . '|||' . $row['section_name'];
        if (!isset($sectionCache[$cacheKey])) {
            $match = buildUserMatchForSection($row['division_name'], $row['section_name'], 'ls');
            $sectionCache[$cacheKey] = getLiveStats($match['sql'], $match['params']);
        }
        $live = $sectionCache[$cacheKey];
        $row['bottle_count']    = $live['bottle_count'];
        $row['chemical_count']  = $live['chemical_count'];
        $row['total_weight_kg'] = round($live['total_weight_kg'], 2);
    }
    unset($row);

    $total = Database::fetch("SELECT COUNT(*) as cnt FROM lab_stores ls WHERE {$whereSQL}", $params)['cnt'] ?? 0;

    return [
        'items' => $rows,
        'pagination' => ['page'=>$page,'per_page'=>$perPage,'total'=>(int)$total,'total_pages'=>(int)ceil($total/$perPage)]
    ];
}

// ========== DETAIL ==========
function getDetail(int $id): array {
    $row = Database::fetch("SELECT * FROM lab_stores WHERE id = :id", [':id'=>$id]);
    if (!$row) throw new Exception('Store not found');

    // Live stats from chemical_stock via section matching
    $match = buildUserMatchForSection($row['division_name'], $row['section_name'], 'det');
    $live = getLiveStats($match['sql'], $match['params']);

    $row['bottle_count']    = $live['bottle_count'];
    $row['chemical_count']  = $live['chemical_count'];
    $row['total_weight_kg'] = round($live['total_weight_kg'], 4);
    $row['holder_count']    = $live['holder_count'];

    return $row;
}

/**
 * Recalculate and update lab_stores counts from chemical_stock data
 */
function recalcStoreCounts(int $storeId): void {
    $store = Database::fetch("SELECT division_name, section_name FROM lab_stores WHERE id = :id", [':id' => $storeId]);
    if (!$store) return;

    $match = buildUserMatchForSection($store['division_name'], $store['section_name'], 'rc');
    $live = getLiveStats($match['sql'], $match['params']);

    Database::update('lab_stores', [
        'bottle_count'    => $live['bottle_count'],
        'chemical_count'  => $live['chemical_count'],
        'total_weight_kg' => $live['total_weight_kg']
    ], 'id = :id', [':id' => $storeId]);
}

// ========== DIVISIONS ==========
function getDivisions(): array {
    $divs = Database::fetchAll("
        SELECT division_name,
               COUNT(*) as store_count
        FROM lab_stores WHERE is_active = 1
        GROUP BY division_name ORDER BY division_name");

    foreach ($divs as &$d) {
        $match = buildUserMatchForDivision($d['division_name'], 'gd');
        $live = getLiveStats($match['sql'], $match['params']);
        $d['total_bottles']   = $live['bottle_count'];
        $d['total_weight_kg'] = round($live['total_weight_kg'], 2);
    }
    unset($d);
    return $divs;
}

// ========== SECTIONS ==========
function getSections(array $f): array {
    $where = ['is_active = 1'];
    $params = [];
    if (!empty($f['division'])) {
        $where[] = "division_name = :div";
        $params[':div'] = $f['division'];
    }
    $w = implode(' AND ', $where);
    $rows = Database::fetchAll("
        SELECT section_name, division_name,
               COUNT(*) as store_count
        FROM lab_stores WHERE {$w}
        GROUP BY section_name, division_name ORDER BY section_name", $params);

    foreach ($rows as &$r) {
        $match = buildUserMatchForSection($r['division_name'], $r['section_name'], 'gs');
        $live = getLiveStats($match['sql'], $match['params']);
        $r['total_bottles']   = $live['bottle_count'];
        $r['total_weight_kg'] = round($live['total_weight_kg'], 2);
    }
    unset($r);
    return $rows;
}

// ========== REPORT ==========
function getReport(array $f): array {
    $groupBy = ($f['group_by'] ?? 'division') === 'section' ? 'section_name' : 'division_name';
    $where = ['is_active = 1'];
    $params = [];
    if (!empty($f['division'])) {
        $where[] = "division_name = :div";
        $params[':div'] = $f['division'];
    }
    $w = implode(' AND ', $where);

    // Get groups with live stats
    $rawGroups = Database::fetchAll("
        SELECT {$groupBy} as group_name,
               COUNT(*) as store_count
        FROM lab_stores WHERE {$w}
        GROUP BY {$groupBy}
        ORDER BY group_name", $params);

    $groups = [];
    foreach ($rawGroups as $g) {
        if ($groupBy === 'division_name') {
            $match = buildUserMatchForDivision($g['group_name'], 'rpt');
        } else {
            // section: need division context
            $divRow = Database::fetch("SELECT division_name FROM lab_stores WHERE is_active = 1 AND section_name = :sec LIMIT 1", [':sec' => $g['group_name']]);
            $match = buildUserMatchForSection($divRow['division_name'] ?? '', $g['group_name'], 'rpt');
        }
        $live = getLiveStats($match['sql'], $match['params']);
        $g['total_bottles']   = $live['bottle_count'];
        $g['total_chemicals'] = $live['chemical_count'];
        $g['total_weight_kg'] = round($live['total_weight_kg'], 2);
        $g['avg_weight_kg']   = $g['store_count'] > 0 ? round($live['total_weight_kg'] / $g['store_count'], 2) : 0;
        $g['max_weight_kg']   = $g['total_weight_kg']; // simplified
        $groups[] = $g;
    }
    usort($groups, fn($a, $b) => $b['total_weight_kg'] <=> $a['total_weight_kg']);

    $stores = Database::fetchAll("
        SELECT id, store_name, division_name, section_name
        FROM lab_stores WHERE {$w}
        ORDER BY {$groupBy}, store_name", $params);

    // Enrich stores with live stats
    $secCache = [];
    foreach ($stores as &$st) {
        $ck = $st['division_name'] . '|||' . $st['section_name'];
        if (!isset($secCache[$ck])) {
            $m = buildUserMatchForSection($st['division_name'], $st['section_name'], 'rps');
            $secCache[$ck] = getLiveStats($m['sql'], $m['params']);
        }
        $l = $secCache[$ck];
        $st['bottle_count']    = $l['bottle_count'];
        $st['chemical_count']  = $l['chemical_count'];
        $st['total_weight_kg'] = round($l['total_weight_kg'], 2);
    }
    unset($st);

    return ['groups'=>$groups, 'stores'=>$stores, 'group_by'=>$groupBy];
}

// ========== SAVE ==========
function saveStore(array $d, array $user): array {
    $required = ['center_name','division_name','section_name','store_name'];
    foreach ($required as $k) {
        if (empty(trim($d[$k] ?? ''))) throw new Exception("{$k} is required");
    }

    $fields = [
        'center_name'    => trim($d['center_name']),
        'division_name'  => trim($d['division_name']),
        'section_name'   => trim($d['section_name']),
        'store_name'     => trim($d['store_name']),
        'department_id'  => $d['department_id'] ?? null,
        'section_dept_id'=> $d['section_dept_id'] ?? null,
        'building_id'    => $d['building_id'] ?? null,
        'room_id'        => $d['room_id'] ?? null,
        'manager_id'     => $d['manager_id'] ?? null,
        'color'          => $d['color'] ?? null,
        'icon'           => $d['icon'] ?? null,
        'notes'          => $d['notes'] ?? null,
        'is_active'      => 1
    ];

    $id = (int)($d['id'] ?? 0);
    if ($id > 0) {
        Database::update('lab_stores', $fields, 'id = :id', [':id'=>$id]);
    } else {
        $id = Database::insert('lab_stores', $fields);
    }

    // Auto-compute counts from chemical_stock via users assigned to this store
    recalcStoreCounts($id);

    return ['id'=>$id];
}

// ========== DELETE ==========
function deleteStore(array $d): array {
    $id = (int)($d['id'] ?? 0);
    if (!$id) throw new Exception('id required');
    Database::update('lab_stores', ['is_active'=>0], 'id = :id', [':id'=>$id]);
    return ['deleted'=>$id];
}

// ========== IMPORT CSV ==========
function importCSV(): array {
    $csvPath = __DIR__ . '/../data/7.คลัง.csv';
    if (!file_exists($csvPath)) throw new Exception('CSV file not found');

    $handle = fopen($csvPath, 'r');
    if (!$handle) throw new Exception('Cannot open CSV');

    $inserted = 0;
    $skipped  = 0;
    $row      = 0;

    // Truncate existing data
    Database::query("DELETE FROM lab_stores WHERE 1=1");

    while (($line = fgetcsv($handle)) !== false) {
        $row++;
        // Skip header rows (first 3 lines)
        if ($row <= 3) continue;
        // Need at least 7 columns
        if (count($line) < 7) { $skipped++; continue; }

        $center  = trim($line[0]);
        $div     = trim($line[1]);
        $section = trim($line[2]);
        $store   = trim($line[3]);
        $bottles = (int)$line[4];
        $chems   = (int)$line[5];
        $weight  = (float)$line[6];

        if (!$center || !$div || !$section || !$store) { $skipped++; continue; }

        Database::insert('lab_stores', [
            'center_name'     => $center,
            'division_name'   => $div,
            'section_name'    => $section,
            'store_name'      => $store,
            'bottle_count'    => $bottles,
            'chemical_count'  => $chems,
            'total_weight_kg' => $weight,
            'is_active'       => 1
        ]);
        $inserted++;
    }
    fclose($handle);

    return ['inserted'=>$inserted, 'skipped'=>$skipped];
}

// ========== HIERARCHY (for dropdowns) ==========
function getHierarchy(): array {
    $centers = Database::fetchAll("
        SELECT DISTINCT center_name FROM lab_stores WHERE is_active = 1 ORDER BY center_name");
    $divisions = Database::fetchAll("
        SELECT DISTINCT center_name, division_name FROM lab_stores WHERE is_active = 1 ORDER BY center_name, division_name");
    $sections = Database::fetchAll("
        SELECT DISTINCT center_name, division_name, section_name FROM lab_stores WHERE is_active = 1 ORDER BY center_name, division_name, section_name");
    return [
        'centers'   => array_column($centers, 'center_name'),
        'divisions' => $divisions,
        'sections'  => $sections
    ];
}

// ========== EXPORT CSV ==========
function exportCSV(): void {
    $division = $_GET['division'] ?? '';
    $where = ['is_active = 1'];
    $params = [];
    if ($division) {
        $where[] = 'division_name = :div';
        $params[':div'] = $division;
    }
    $w = implode(' AND ', $where);
    $rows = Database::fetchAll("
        SELECT center_name, division_name, section_name, store_name,
               bottle_count, chemical_count, ROUND(total_weight_kg,2) as total_weight_kg, notes
        FROM lab_stores WHERE {$w}
        ORDER BY center_name, division_name, section_name, store_name", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lab_stores_export_'.date('Ymd_His').'.csv');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ศูนย์/สำนักวิชา','ฝ่าย/สาขาวิชา','งาน','ชื่อคลังสารเคมี','จำนวนขวด','จำนวนสารเคมี','ปริมาณรวม(kg)','หมายเหตุ']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['center_name'], $r['division_name'], $r['section_name'], $r['store_name'],
            $r['bottle_count'], $r['chemical_count'], $r['total_weight_kg'], $r['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// ========== BULK DELETE ==========
function bulkDelete(array $d): array {
    $ids = $d['ids'] ?? [];
    if (!is_array($ids) || !count($ids)) throw new Exception('ids array required');
    $cnt = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            Database::update('lab_stores', ['is_active'=>0], 'id = :id', [':id'=>$id]);
            $cnt++;
        }
    }
    return ['deleted'=>$cnt];
}

// ========== IMPORT CSV FROM FILE UPLOAD ==========
function importCSVFromFile(): array {
    // Accept multipart upload
    if (empty($_FILES['csv_file'])) {
        throw new Exception('No CSV file uploaded');
    }
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') throw new Exception('Only .csv files allowed');

    $mode = $_POST['import_mode'] ?? 'append'; // 'append' or 'replace'
    $skipRows = (int)($_POST['skip_rows'] ?? 1); // header rows to skip

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) throw new Exception('Cannot open uploaded file');

    // If replace mode, deactivate all existing
    if ($mode === 'replace') {
        Database::query("UPDATE lab_stores SET is_active = 0 WHERE 1=1");
    }

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $row      = 0;

    while (($line = fgetcsv($handle)) !== false) {
        $row++;
        if ($row <= $skipRows) continue;
        if (count($line) < 4) { $skipped++; continue; }

        $center  = trim($line[0] ?? '');
        $div     = trim($line[1] ?? '');
        $section = trim($line[2] ?? '');
        $store   = trim($line[3] ?? '');
        $bottles = isset($line[4]) ? (int)$line[4] : 0;
        $chems   = isset($line[5]) ? (int)$line[5] : 0;
        $weight  = isset($line[6]) ? (float)$line[6] : 0;
        $notes   = trim($line[7] ?? '');

        if (!$center || !$div || !$section || !$store) { $skipped++; continue; }

        // Check if exists (same center+div+section+store)
        $existing = Database::fetch("
            SELECT id FROM lab_stores
            WHERE center_name = :cn AND division_name = :dn AND section_name = :sn AND store_name = :st",
            [':cn'=>$center, ':dn'=>$div, ':sn'=>$section, ':st'=>$store]);

        $fields = [
            'center_name'     => $center,
            'division_name'   => $div,
            'section_name'    => $section,
            'store_name'      => $store,
            'bottle_count'    => $bottles,
            'chemical_count'  => $chems,
            'total_weight_kg' => $weight,
            'notes'           => $notes ?: null,
            'is_active'       => 1
        ];

        if ($existing) {
            Database::update('lab_stores', $fields, 'id = :id', [':id' => $existing['id']]);
            $updated++;
        } else {
            Database::insert('lab_stores', $fields);
            $inserted++;
        }
    }
    fclose($handle);

    return ['inserted'=>$inserted, 'updated'=>$updated, 'skipped'=>$skipped, 'total_rows'=>$row];
}

// ========== DRILL-DOWN: Division Level ==========
// Returns sections under a division with live stats from chemical_stock
function drillDivision(array $f): array {
    $division = trim($f['division'] ?? '');
    if (!$division) throw new Exception('division parameter required');

    // Structure info
    $structCounts = Database::fetch("
        SELECT COUNT(*) as store_count,
               COUNT(DISTINCT section_name) as section_count
        FROM lab_stores WHERE is_active = 1 AND division_name = :div",
        [':div' => $division]);

    // Live division totals
    $divMatch = buildUserMatchForDivision($division, 'dd');
    $divLive = getLiveStats($divMatch['sql'], $divMatch['params']);

    $totals = [
        'store_count'     => (int)($structCounts['store_count'] ?? 0),
        'section_count'   => (int)($structCounts['section_count'] ?? 0),
        'total_bottles'   => $divLive['bottle_count'],
        'total_chemicals' => $divLive['chemical_count'],
        'total_weight_kg' => round($divLive['total_weight_kg'], 2)
    ];

    // Sections with live stats
    $rawSections = Database::fetchAll("
        SELECT section_name, COUNT(*) as store_count
        FROM lab_stores WHERE is_active = 1 AND division_name = :div
        GROUP BY section_name",
        [':div' => $division]);

    $sections = [];
    foreach ($rawSections as $sec) {
        $secMatch = buildUserMatchForSection($division, $sec['section_name'], 'ds');
        $secLive = getLiveStats($secMatch['sql'], $secMatch['params']);
        $sections[] = [
            'section_name'    => $sec['section_name'],
            'store_count'     => (int)$sec['store_count'],
            'total_bottles'   => $secLive['bottle_count'],
            'total_chemicals' => $secLive['chemical_count'],
            'total_weight_kg' => round($secLive['total_weight_kg'], 2),
            'holder_count'    => $secLive['holder_count']
        ];
    }
    usort($sections, fn($a, $b) => $b['total_weight_kg'] <=> $a['total_weight_kg']);

    // Stores under this division with live section-level stats
    $rawStores = Database::fetchAll("
        SELECT id, store_name, section_name
        FROM lab_stores WHERE is_active = 1 AND division_name = :div
        ORDER BY section_name, store_name",
        [':div' => $division]);
    $secCache = [];
    foreach ($rawStores as &$st) {
        $ck = $st['section_name'];
        if (!isset($secCache[$ck])) {
            $m = buildUserMatchForSection($division, $ck, 'dss');
            $secCache[$ck] = getLiveStats($m['sql'], $m['params']);
        }
        $l = $secCache[$ck];
        $st['bottle_count']    = $l['bottle_count'];
        $st['chemical_count']  = $l['chemical_count'];
        $st['total_weight_kg'] = round($l['total_weight_kg'], 2);
    }
    unset($st);

    // Holders (users with chemicals under this division)
    $holders = Database::fetchAll("
        SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.department, u.position as section_name,
               COUNT(*) as bottle_count,
               COUNT(DISTINCT cs.chemical_name) as chemical_count,
               ROUND(SUM(cs.remaining_qty),2) as total_qty
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low') AND {$divMatch['sql']}
        GROUP BY u.id ORDER BY bottle_count DESC",
        $divMatch['params']);

    return [
        'division_name' => $division,
        'totals'        => $totals,
        'sections'      => $sections,
        'stores'        => $rawStores,
        'holders'       => $holders
    ];
}

// ========== DRILL-DOWN: Section Level ==========
// Returns stores + holders under a specific section within a division, all live from chemical_stock
function drillSection(array $f): array {
    $division = trim($f['division'] ?? '');
    $section  = trim($f['section'] ?? '');
    if (!$division || !$section) throw new Exception('division and section parameters required');

    // Live stats for this section
    $secMatch = buildUserMatchForSection($division, $section, 'sx');
    $secLive = getLiveStats($secMatch['sql'], $secMatch['params']);

    $storeCount = Database::fetch("
        SELECT COUNT(*) as cnt FROM lab_stores 
        WHERE is_active = 1 AND division_name = :div AND section_name = :sec",
        [':div' => $division, ':sec' => $section])['cnt'] ?? 0;

    $totals = [
        'store_count'     => (int)$storeCount,
        'total_bottles'   => $secLive['bottle_count'],
        'total_chemicals' => $secLive['chemical_count'],
        'total_weight_kg' => round($secLive['total_weight_kg'], 2),
        'holder_count'    => $secLive['holder_count']
    ];

    // Stores in this section (all share same section-level stats)
    $stores = Database::fetchAll("
        SELECT id, store_name
        FROM lab_stores WHERE is_active = 1 AND division_name = :div AND section_name = :sec
        ORDER BY store_name",
        [':div' => $division, ':sec' => $section]);

    // Enrich with section-level live stats
    foreach ($stores as &$st) {
        $st['bottle_count']    = $secLive['bottle_count'];
        $st['chemical_count']  = $secLive['chemical_count'];
        $st['total_weight_kg'] = round($secLive['total_weight_kg'], 2);
    }
    unset($st);

    // Holders
    $holders = Database::fetchAll("
        SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.department, u.position as section_name,
               COUNT(*) as bottle_count,
               COUNT(DISTINCT cs.chemical_name) as chemical_count,
               ROUND(SUM(cs.remaining_qty),2) as total_qty
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low') AND {$secMatch['sql']}
        GROUP BY u.id ORDER BY bottle_count DESC",
        $secMatch['params']);

    return [
        'division_name' => $division,
        'section_name'  => $section,
        'totals'        => $totals,
        'stores'        => $stores,
        'holders'       => $holders
    ];
}

// ========== DRILL-DOWN: Store Level ==========
// Returns a specific store's detail + holders, all live from chemical_stock via section matching
function drillStore(array $f): array {
    $storeId = (int)($f['id'] ?? 0);
    if (!$storeId) throw new Exception('store id required');

    $store = Database::fetch("SELECT * FROM lab_stores WHERE id = :id AND is_active = 1", [':id' => $storeId]);
    if (!$store) throw new Exception('Store not found');

    // Live stats from chemical_stock via section matching
    $secMatch = buildUserMatchForSection($store['division_name'], $store['section_name'], 'st');
    $secLive = getLiveStats($secMatch['sql'], $secMatch['params']);

    $store['bottle_count']    = $secLive['bottle_count'];
    $store['chemical_count']  = $secLive['chemical_count'];
    $store['total_weight_kg'] = round($secLive['total_weight_kg'], 4);

    // Holders
    $holders = Database::fetchAll("
        SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.department, u.position as section_name,
               COUNT(*) as bottle_count,
               COUNT(DISTINCT cs.chemical_name) as chemical_count,
               ROUND(SUM(cs.remaining_qty),2) as total_qty
        FROM chemical_stock cs
        JOIN users u ON cs.owner_user_id = u.id
        WHERE cs.status IN ('active','low') AND {$secMatch['sql']}
        GROUP BY u.id ORDER BY bottle_count DESC",
        $secMatch['params']);

    return [
        'store'   => $store,
        'holders' => $holders
    ];
}

// ========== STOCK DETAIL ==========
// Returns full detail of a chemical_stock item + linked chemical info + transaction history
function stockDetail(array $f): array {
    $stockId = (int)($f['id'] ?? 0);
    if (!$stockId) throw new Exception('stock id required');

    // Main stock record with owner + chemicals table join
    $stock = Database::fetch("
        SELECT cs.*,
               CONCAT(u.first_name, ' ', u.last_name) as owner_full_name,
               u.department as owner_department, u.position as owner_position, u.email as owner_email,
               c.name as chem_name, c.cas_number as chem_cas, c.molecular_formula, c.molecular_weight,
               c.physical_state, c.signal_word, c.hazard_pictograms, c.ghs_classifications,
               c.substance_type, c.substance_category, c.appearance,
               c.storage_requirements, c.handling_procedures, c.safety_info,
               c.sds_url, c.image_url,
               c.un_class, c.ghs_hazard_text
        FROM chemical_stock cs
        LEFT JOIN users u ON cs.owner_user_id = u.id
        LEFT JOIN chemicals c ON cs.chemical_id = c.id
        WHERE cs.id = :id",
        [':id' => $stockId]);

    if (!$stock) throw new Exception('Stock item not found');

    // Parse JSON fields from chemicals table
    foreach (['hazard_pictograms', 'ghs_classifications'] as $jf) {
        if (!empty($stock[$jf]) && is_string($stock[$jf])) {
            $decoded = json_decode($stock[$jf], true);
            $stock[$jf] = is_array($decoded) ? $decoded : [];
        } else {
            $stock[$jf] = [];
        }
    }

    // Transaction history for this stock item
    // Match by source_id + source_type='stock' OR by barcode = bottle_code
    $history = Database::fetchAll("
        SELECT ct.id, ct.txn_number, ct.txn_type, ct.status as txn_status,
               ct.quantity, ct.unit, ct.balance_after, ct.purpose, ct.project_name,
               ct.from_user_id, ct.to_user_id, ct.from_department, ct.to_department,
               ct.requires_approval, ct.approved_by, ct.approved_at, ct.approval_notes,
               ct.expected_return_date, ct.actual_return_date, ct.return_condition,
               ct.disposal_reason, ct.disposal_method,
               ct.created_at, ct.updated_at,
               CONCAT(fu.first_name, ' ', fu.last_name) as from_user_name,
               CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name,
               CONCAT(ab.first_name, ' ', ab.last_name) as approved_by_name
        FROM chemical_transactions ct
        LEFT JOIN users fu ON ct.from_user_id = fu.id
        LEFT JOIN users tu ON ct.to_user_id = tu.id
        LEFT JOIN users ab ON ct.approved_by = ab.id
        WHERE (ct.source_type = 'stock' AND ct.source_id = :sid)
           OR ct.barcode = :bc
        ORDER BY ct.created_at DESC",
        [':sid' => $stockId, ':bc' => $stock['bottle_code'] ?? '']);

    // Also get borrow records where this stock was involved
    $borrows = Database::fetchAll("
        SELECT ct.id, ct.txn_type, ct.status as txn_status,
               ct.quantity, ct.unit, ct.purpose, ct.project_name,
               ct.expected_return_date, ct.actual_return_date, ct.return_condition,
               ct.created_at,
               CONCAT(tu.first_name, ' ', tu.last_name) as borrower_name
        FROM chemical_transactions ct
        LEFT JOIN users tu ON ct.to_user_id = tu.id
        WHERE ct.txn_type IN ('borrow','return')
          AND ((ct.source_type = 'stock' AND ct.source_id = :sid2) OR ct.barcode = :bc2)
        ORDER BY ct.created_at DESC",
        [':sid2' => $stockId, ':bc2' => $stock['bottle_code'] ?? '']);

    return [
        'stock'   => $stock,
        'history' => $history,
        'borrows' => $borrows
    ];
}

// ========== DRILL-DOWN: Holder Level ==========
// Returns all chemicals held by a specific user with search/pagination
function drillHolder(array $f): array {
    $userId = (int)($f['user_id'] ?? 0);
    if (!$userId) throw new Exception('user_id required');

    // User info
    $user = Database::fetch("
        SELECT id, first_name, last_name, department, position, email, phone
        FROM users WHERE id = :id",
        [':id' => $userId]);
    if (!$user) throw new Exception('User not found');

    // Search / filter
    $where  = ["cs.status IN ('active','low')", 'cs.owner_user_id = :uid'];
    $params = [':uid' => $userId];

    if (!empty($f['search'])) {
        $where[] = '(cs.chemical_name LIKE :s OR cs.cas_no LIKE :s OR cs.bottle_code LIKE :s)';
        $params[':s'] = '%' . $f['search'] . '%';
    }

    $whereSQL = implode(' AND ', $where);

    // Pagination
    $page    = max(1, (int)($f['page'] ?? 1));
    $perPage = min(100, max(10, (int)($f['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    // Sort
    $sortCol = $f['sort'] ?? 'chemical_name';
    $allowed = ['chemical_name','cas_no','bottle_code','remaining_qty','unit','grade','status'];
    if (!in_array($sortCol, $allowed)) $sortCol = 'chemical_name';
    $sortDir = (strtolower($f['sort_dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $chemicals = Database::fetchAll("
        SELECT cs.id, cs.bottle_code, cs.chemical_name, cs.cas_no, cs.grade,
               cs.package_size, cs.remaining_qty, cs.unit, cs.status, cs.remaining_pct,
               cs.storage_location, cs.added_at
        FROM chemical_stock cs
        WHERE {$whereSQL}
        ORDER BY {$sortCol} {$sortDir}
        LIMIT :lim OFFSET :off",
        array_merge($params, [':lim' => $perPage, ':off' => $offset]));

    $total = Database::fetch("
        SELECT COUNT(*) as cnt FROM chemical_stock cs WHERE {$whereSQL}",
        $params)['cnt'] ?? 0;

    // Summary stats for this holder
    $stats = Database::fetch("
        SELECT COUNT(*) as total_bottles,
               COUNT(DISTINCT chemical_name) as unique_chemicals,
               ROUND(SUM(remaining_qty),2) as total_remaining,
               COUNT(DISTINCT unit) as unit_types,
               ROUND(AVG(remaining_pct),1) as avg_remaining_pct
        FROM chemical_stock cs WHERE cs.status IN ('active','low') AND cs.owner_user_id = :uid",
        [':uid' => $userId]);

    // Active borrows by this user (things they borrowed and haven't returned)
    $activeBorrows = Database::fetchAll("
        SELECT ct.id, ct.barcode, ct.quantity, ct.unit, ct.purpose,
               ct.created_at, ct.expected_return_date,
               cs.chemical_name, cs.cas_no
        FROM chemical_transactions ct
        LEFT JOIN chemical_stock cs ON ct.source_type = 'stock' AND ct.source_id = cs.id
        WHERE ct.txn_type = 'borrow' AND ct.status = 'completed'
          AND ct.to_user_id = :uid
          AND ct.id NOT IN (
              SELECT COALESCE(parent_txn_id, 0) FROM chemical_transactions
              WHERE txn_type = 'return' AND status = 'completed' AND parent_txn_id IS NOT NULL
          )
        ORDER BY ct.created_at DESC LIMIT 20",
        [':uid' => $userId]);

    return [
        'user'          => $user,
        'stats'         => $stats,
        'chemicals'     => $chemicals,
        'active_borrows'=> $activeBorrows,
        'pagination'    => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => (int)$total,
            'total_pages' => (int)ceil($total / $perPage)
        ]
    ];
}
