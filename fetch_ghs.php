<?php
/**
 * fetch_ghs.php — Batch fetch GHS data from PubChem and update database
 * Usage: php fetch_ghs.php [--limit=N] [--offset=N] [--dry-run] [--all-chemicals]
 *
 * By default processes only chemicals in active containers.
 * --all-chemicals: process all chemicals in DB regardless of containers
 */

ini_set('max_execution_time', 7200);
set_time_limit(7200);

$pdo = new PDO('mysql:host=localhost;dbname=chem_inventory_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dryRun     = in_array('--dry-run', $argv);
$allChem    = in_array('--all-chemicals', $argv);
$limit      = 9999;
$offset     = 0;
foreach ($argv as $arg) {
    if (preg_match('/--limit=(\d+)/', $arg, $m))  $limit  = (int)$m[1];
    if (preg_match('/--offset=(\d+)/', $arg, $m)) $offset = (int)$m[1];
}

echo "=== PubChem GHS Batch Fetcher ===\n";
echo "Mode: " . ($dryRun ? 'DRY-RUN' : 'LIVE UPDATE') . " | limit=$limit offset=$offset\n\n";

// ── Fetch helpers ──────────────────────────────────────────────────────────
function fetchJson(string $url): ?array {
    static $errors = 0;
    $ctx = stream_context_create(['http' => [
        'timeout' => 20,
        'header'  => "User-Agent: SUT-ChemBot/1.0 (educational, contact: admin@sut.ac.th)\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if (!$body) { $errors++; return null; }
    $data = json_decode($body, true);
    // PubChem returns {"Fault":...} for not-found
    if (isset($data['Fault'])) return null;
    return $data;
}

function getCidByCas(string $cas): ?int {
    $data = fetchJson("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/cids/JSON");
    return $data['IdentifierList']['CID'][0] ?? null;
}

function getCidByName(string $name): ?int {
    $data = fetchJson("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($name) . "/cids/JSON");
    return $data['IdentifierList']['CID'][0] ?? null;
}

// ── Parse GHS section recursively ─────────────────────────────────────────
function extractGhsInfo(array $node, array &$hCodes, array &$hTexts, array &$pCodes, array &$signals): void {
    $head = strtolower($node['TOCHeading'] ?? '');
    $isGhs = str_contains($head, 'ghs') || str_contains($head, 'hazard identification');

    if ($isGhs || $head === '') {
        foreach ($node['Information'] ?? [] as $info) {
            $name = strtolower($info['Name'] ?? '');
            $vals = array_map(fn($sw) => $sw['String'] ?? '', $info['Value']['StringWithMarkup'] ?? []);
            $vals = array_filter($vals, fn($v) => trim($v) !== '');

            if (str_contains($name, 'hazard statement') || str_contains($name, 'h statement')) {
                foreach ($vals as $v) {
                    preg_match_all('/\bH\d{3}\b/i', $v, $m);
                    foreach ($m[0] as $h) {
                        $h = strtoupper($h);
                        // Skip if percentage is explicitly very low < 10%
                        if (preg_match('/H\d{3}\s*\(\s*([\d.]+)\s*%\s*\)/', $v, $pm) && (float)$pm[1] < 10) continue;
                        $hCodes[] = $h;
                        $hTexts[$h] = $v; // store first text for this code
                    }
                }
            }

            if (str_contains($name, 'pictogram')) {
                foreach ($vals as $v) {
                    preg_match_all('/GHS\d{2}/i', $v, $m);
                    foreach ($m[0] as $g) $pCodes[] = strtoupper($g);
                }
            }

            if (str_contains($name, 'signal')) {
                foreach ($vals as $v) {
                    $v = trim($v);
                    if (in_array($v, ['Danger','Warning','No signal word'])) $signals[] = $v;
                }
            }
        }
    }

    foreach ($node['Section'] ?? [] as $child) {
        extractGhsInfo($child, $hCodes, $hTexts, $pCodes, $signals);
    }
}

// ── Derive GHS pictogram codes from H-codes ────────────────────────────────
function deriveGhsPictograms(array $hCodes): array {
    $map = [
        'GHS01' => ['H200','H201','H202','H203','H204','H205','H240','H241','H242'],
        'GHS02' => ['H220','H221','H222','H223','H224','H225','H226','H228',
                    'H250','H251','H252','H260','H261','H243','H244','H245'],
        'GHS03' => ['H270','H271','H272'],
        'GHS04' => ['H280','H281','H282','H283'],
        'GHS05' => ['H290','H314','H318'],
        'GHS06' => ['H300','H301','H310','H311','H330','H331'],
        'GHS07' => ['H302','H304','H312','H315','H317','H319',
                    'H332','H334','H335','H336','H333'],
        'GHS08' => ['H340','H341','H350','H351','H360','H361','H362',
                    'H370','H371','H372','H373'],
        'GHS09' => ['H400','H410','H411','H412','H413'],
    ];
    $result = [];
    foreach ($map as $ghs => $codes) {
        if (array_intersect($hCodes, $codes)) $result[] = $ghs;
    }
    return array_values(array_unique($result));
}

// ── H-code → word-name pictogram (for chemicals.hazard_pictograms) ─────────
function deriveWordPictograms(array $hCodes): array {
    $map = [
        'explosive'     => ['H200','H201','H202','H203','H204','H205','H240','H241','H242'],
        'flammable'     => ['H220','H221','H222','H223','H224','H225','H226','H228',
                            'H250','H251','H252','H260','H261','H243','H244','H245'],
        'oxidizing'     => ['H270','H271','H272'],
        'gas_pressure'  => ['H280','H281','H282','H283'],
        'corrosive'     => ['H290','H314','H318'],
        'toxic'         => ['H300','H301','H310','H311','H330','H331'],
        'irritant'      => ['H302','H304','H312','H315','H317','H319',
                            'H332','H334','H335','H336','H333'],
        'health_hazard' => ['H340','H341','H350','H351','H360','H361','H362',
                            'H370','H371','H372','H373'],
        'environmental' => ['H400','H410','H411','H412','H413'],
    ];
    $result = [];
    foreach ($map as $word => $codes) {
        if (array_intersect($hCodes, $codes)) $result[] = $word;
    }
    return array_values(array_unique($result));
}

// ── GHS classifications string (e.g. "Flam. Liq. 2") ─────────────────────
function deriveGhsClass(array $hCodes): array {
    $classMap = [
        'H200'=>'Unst. Expl.','H201'=>'Expl. 1.1','H202'=>'Expl. 1.2','H203'=>'Expl. 1.3',
        'H204'=>'Expl. 1.4','H220'=>'Flam. Gas 1A','H221'=>'Flam. Gas 1B',
        'H222'=>'Flam. Aerosol 1','H223'=>'Flam. Aerosol 2',
        'H224'=>'Flam. Liq. 1','H225'=>'Flam. Liq. 2','H226'=>'Flam. Liq. 3',
        'H228'=>'Flam. Sol. 1',
        'H270'=>'Ox. Gas 1','H271'=>'Ox. Liq. 1','H272'=>'Ox. Liq. 2',
        'H280'=>'Press. Gas','H281'=>'Press. Gas (ref.)','H290'=>'Met. Corr. 1',
        'H300'=>'Acute Tox. 1 (oral)','H301'=>'Acute Tox. 3 (oral)','H302'=>'Acute Tox. 4 (oral)',
        'H310'=>'Acute Tox. 1 (skin)','H311'=>'Acute Tox. 3 (skin)','H312'=>'Acute Tox. 4 (skin)',
        'H314'=>'Skin Corr. 1','H315'=>'Skin Irrit. 2','H317'=>'Skin Sens. 1',
        'H318'=>'Eye Dam. 1','H319'=>'Eye Irrit. 2',
        'H330'=>'Acute Tox. 1 (inh.)','H331'=>'Acute Tox. 3 (inh.)','H332'=>'Acute Tox. 4 (inh.)',
        'H340'=>'Muta. 1B','H341'=>'Muta. 2','H350'=>'Carc. 1B','H351'=>'Carc. 2',
        'H360'=>'Repr. 1B','H361'=>'Repr. 2','H370'=>'STOT SE 1','H371'=>'STOT SE 2',
        'H372'=>'STOT RE 1','H373'=>'STOT RE 2',
        'H400'=>'Aquatic Acute 1','H410'=>'Aquatic Chronic 1','H411'=>'Aquatic Chronic 2',
        'H412'=>'Aquatic Chronic 3',
    ];
    $result = [];
    foreach ($hCodes as $h) { if (isset($classMap[$h])) $result[] = $classMap[$h]; }
    return array_values(array_unique($result));
}

// ── Load chemicals to process ──────────────────────────────────────────────
if ($allChem) {
    $sql = "SELECT id, cas_number, name, physical_state FROM chemicals WHERE is_active=1 AND cas_number IS NOT NULL AND cas_number != '' ORDER BY id LIMIT :lim OFFSET :off";
} else {
    // Only chemicals in active containers, missing GHS data
    $sql = "
        SELECT ch.id, ch.cas_number, ch.name, ch.physical_state,
               COUNT(ct.id) AS cnt
        FROM chemicals ch
        JOIN containers ct ON ct.chemical_id=ch.id AND ct.is_active=1 AND ct.current_quantity>0
        WHERE ch.cas_number IS NOT NULL AND ch.cas_number != '' AND ch.is_active=1
          AND (ch.hazard_statements IS NULL OR ch.hazard_statements IN ('[]','','null'))
        GROUP BY ch.id
        ORDER BY cnt DESC, ch.id
        LIMIT :lim OFFSET :off
    ";
}
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
$stmt->execute();
$chemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($chemicals);
echo "Chemicals to process: $total\n\n";

// ── Prepare DB statements ──────────────────────────────────────────────────
$stmtUpdateChem = $pdo->prepare("
    UPDATE chemicals SET
        hazard_statements  = :hstmt,
        hazard_pictograms  = :wpics,
        ghs_classifications= :gclass,
        signal_word        = :sw,
        updated_at         = NOW()
    WHERE id = :id
");

$stmtCheckGhs = $pdo->prepare("SELECT id FROM chemical_ghs_data WHERE chemical_id=:cid ORDER BY id LIMIT 1");
$stmtInsertGhs = $pdo->prepare("
    INSERT INTO chemical_ghs_data (chemical_id, ghs_pictograms, signal_word, h_statements, h_statements_text, source, created_at, updated_at)
    VALUES (:cid, :gpics, :sw, :hstmt, :htext, 'PubChem', NOW(), NOW())
");
$stmtUpdateGhs = $pdo->prepare("
    UPDATE chemical_ghs_data SET
        ghs_pictograms    = :gpics,
        signal_word       = :sw,
        h_statements      = :hstmt,
        h_statements_text = :htext,
        source            = 'PubChem',
        updated_at        = NOW()
    WHERE id = :id
");

// ── Process each chemical ──────────────────────────────────────────────────
$ok = 0; $skipped = 0; $notFound = 0; $n = 0;

foreach ($chemicals as $chem) {
    $n++;
    $cas  = trim($chem['cas_number']);
    $name = trim($chem['name']);
    $id   = (int)$chem['id'];
    $pct  = str_pad(round($n/$total*100), 3);

    echo "[{$pct}% {$n}/{$total}] ID={$id} CAS={$cas} {$name} ... ";
    flush();

    // Step 1: Resolve CAS → CID
    $cid = getCidByCas($cas);
    usleep(210000);

    if (!$cid) {
        // Fallback: search by name
        $cid = getCidByName($name);
        usleep(210000);
        if ($cid) echo "(by name, CID=$cid) ";
    }

    if (!$cid) {
        echo "NOT FOUND\n";
        $notFound++;
        continue;
    }

    // Step 2: Fetch GHS section
    $record = fetchJson("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/$cid/JSON?heading=GHS+Classification");
    usleep(210000);

    if (!$record || !isset($record['Record'])) {
        echo "NO GHS DATA (CID=$cid)\n";
        $skipped++;
        continue;
    }

    // Step 3: Parse
    $hCodesArr = []; $hTextsMap = []; $pCodesArr = []; $signalsArr = [];
    extractGhsInfo($record['Record'], $hCodesArr, $hTextsMap, $pCodesArr, $signalsArr);

    $hCodes  = array_values(array_unique($hCodesArr));
    $ghsPics = count($pCodesArr) ? array_values(array_unique($pCodesArr)) : deriveGhsPictograms($hCodes);
    $wordPics= deriveWordPictograms($hCodes);
    $ghsClass= deriveGhsClass($hCodes);
    $signal = '';
    if ($signalsArr) {
        $cnt = array_count_values($signalsArr);
        arsort($cnt);
        $signal = array_key_first($cnt);
    }

    // Build h_statements_text
    $hTextLines = [];
    foreach ($hCodes as $h) {
        if (isset($hTextsMap[$h])) $hTextLines[] = preg_replace('/\s*\(\s*[\d.]+\s*%.*?\)\s*/', '', $hTextsMap[$h]);
    }
    $hText = implode("\n", array_unique($hTextLines));

    // Build hStmt JSON (array of "H225: Highly Flammable...")
    $hStmtArr = array_values(array_unique($hTextLines));

    if (empty($hCodes)) {
        echo "NO H-CODES\n";
        $skipped++;
        continue;
    }

    echo "H=" . implode(',', array_slice($hCodes,0,5)) . (count($hCodes)>5?'...':'') . " PIC=" . implode(',', $ghsPics) . "\n";

    if ($dryRun) { $ok++; continue; }

    // Step 4: Update database
    $stmtUpdateChem->execute([
        ':hstmt'  => json_encode($hStmtArr, JSON_UNESCAPED_UNICODE),
        ':wpics'  => json_encode($wordPics,  JSON_UNESCAPED_UNICODE),
        ':gclass' => json_encode($ghsClass,  JSON_UNESCAPED_UNICODE),
        ':sw'     => $signal,
        ':id'     => $id,
    ]);

    $stmtCheckGhs->execute([':cid' => $id]);
    $existRow = $stmtCheckGhs->fetch();

    $ghsParams = [
        ':gpics' => json_encode($ghsPics,  JSON_UNESCAPED_UNICODE),
        ':sw'    => $signal,
        ':hstmt' => json_encode($hCodes,   JSON_UNESCAPED_UNICODE),
        ':htext' => $hText,
    ];

    if ($existRow) {
        $ghsParams[':id'] = $existRow['id'];
        $stmtUpdateGhs->execute($ghsParams);
    } else {
        $ghsParams[':cid'] = $id;
        $stmtInsertGhs->execute($ghsParams);
    }

    $ok++;
}

echo "\n";
echo "=== DONE ===\n";
echo "Updated : $ok\n";
echo "No H-codes / No GHS : $skipped\n";
echo "Not found in PubChem : $notFound\n";
echo "Total processed : $n / $total\n";
