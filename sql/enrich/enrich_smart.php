<?php
/**
 * Smart Multi-Source Enrichment v3
 * ─────────────────────────────────
 * Strategy: Use MULTIPLE API sources, not just PubChem
 *  
 *  Source 1: NCI/CADD CACTUS (cactus.nci.nih.gov) → formula, MW, IUPAC
 *            ✅ Separate from PubChem — different server, no shared block
 *            ✅ Simple plain-text API — fast, no JSON parsing needed
 *  
 *  Source 2: PubChem REST API → CID, GHS data
 *            ⚠️ May be blocked — if so, skip gracefully (don't wait)
 *  
 * Logic per chemical:
 *   1. Skip if already has formula + real GHS
 *   2. Get formula/MW from CACTUS (always works)
 *   3. Try PubChem for CID → if blocked, skip all PubChem steps
 *   4. If got CID → try GHS → set image/3d URLs
 *   5. Continue to next chemical
 *
 * Usage: php enrich_smart.php           (process all gaps)
 *        php enrich_smart.php formula   (formula only, skip GHS)
 *        php enrich_smart.php ghs       (GHS only, skip formula)
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/database.php';

$mode = $argv[1] ?? 'all';  // 'all', 'formula', 'ghs'
$batchSize = 500;
$db = Database::getInstance();

// ── Detect PubChem block status ──
$pubchemBlocked = false;
echo "🔍 Testing PubChem API...\n";
$testUrl = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/64-17-5/cids/JSON";
$testBody = fetchUrl($testUrl, 8);
if ($testBody === '__BLOCKED__' || $testBody === false) {
    echo "   ❌ PubChem is BLOCKED — will use CACTUS for formula, skip GHS\n";
    $pubchemBlocked = true;
    if ($mode === 'ghs') {
        echo "   Cannot do GHS-only mode while PubChem is blocked. Exiting.\n";
        exit(1);
    }
} else {
    $d = json_decode($testBody, true);
    $testCid = $d['IdentifierList']['CID'][0] ?? null;
    echo "   ✅ PubChem OK (Ethanol CID=$testCid)\n";
}

// ── Stats ──
$total = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND cas_number IS NOT NULL AND cas_number != ''")['c'];
$hasFormula = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND cas_number != '' AND molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A'")['c'];
$hasGhsReal = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'];

echo "\n📊 Current Status:\n";
echo "   With CAS:     $total\n";
echo "   Has Formula:  $hasFormula  (gap: " . ($total - $hasFormula) . ")\n";
echo "   Has Real GHS: $hasGhsReal  (gap: " . ($total - $hasGhsReal) . ")\n\n";

// ══════════════════════════════════════════════════════════════
//  PHASE 1: Formula + MW from NCI CACTUS (works even if PubChem blocked)
// ══════════════════════════════════════════════════════════════
if ($mode === 'all' || $mode === 'formula') {
    echo "══════════════════════════════════════════════════════\n";
    echo "  PHASE 1: Formula + MW from NCI CACTUS\n";
    echo "══════════════════════════════════════════════════════\n\n";

    $round = 0;
    $totalFormula = 0;
    $totalMw = 0;
    $totalFail = 0;

    while (true) {
        $round++;
        $chemicals = Database::fetchAll("
            SELECT id, cas_number, name FROM chemicals
            WHERE is_active = 1
              AND cas_number IS NOT NULL AND cas_number != ''
              AND (molecular_formula IS NULL OR molecular_formula = '')
            ORDER BY id ASC
            LIMIT :lim
        ", [':lim' => $batchSize]);

        if (empty($chemicals)) {
            echo "   ✅ All chemicals have formulas!\n\n";
            break;
        }

        $remaining = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND cas_number != '' AND (molecular_formula IS NULL OR molecular_formula = '')")['c'];
        echo "  Round $round — Remaining: $remaining\n";

        $roundSuccess = 0;
        foreach ($chemicals as $idx => $chem) {
            $cas = trim($chem['cas_number']);
            $chemId = (int)$chem['id'];
            $n = $idx + 1;
            $total_batch = count($chemicals);

            echo "  [$n/$total_batch] #$chemId CAS:$cas ";

            // Get formula from CACTUS
            $formula = fetchCactus($cas, 'formula');
            if ($formula && strlen($formula) < 100 && preg_match('/^[A-Za-z0-9().+\-\[\]]+$/', $formula)) {
                $roundSuccess++;
                $sets = ['molecular_formula = :mf'];
                $params = [':mf' => $formula, ':id' => $chemId];

                // Get MW
                $mw = fetchCactus($cas, 'mw');
                if ($mw && is_numeric(trim($mw))) {
                    $sets[] = 'molecular_weight = :mw';
                    $params[':mw'] = (float)trim($mw);
                    $totalMw++;
                }

                // Get IUPAC name (if we don't have one)
                $existing = Database::fetch("SELECT iupac_name FROM chemicals WHERE id=:id", [':id' => $chemId]);
                if (empty($existing['iupac_name'])) {
                    $iupac = fetchCactus($cas, 'iupac_name');
                    if ($iupac && strlen($iupac) < 400) {
                        $sets[] = 'iupac_name = :iupac';
                        $params[':iupac'] = trim($iupac);
                    }
                }

                $db->prepare("UPDATE chemicals SET " . implode(',', $sets) . " WHERE id = :id")->execute($params);
                echo "✓ $formula";
                $totalFormula++;
            } else {
                // Mark as N/A so we don't retry this chemical
                $db->prepare("UPDATE chemicals SET molecular_formula='N/A' WHERE id=:id")->execute([':id'=>$chemId]);
                echo "⊘";
                $totalFail++;
            }
            echo "\n";

            usleep(150000); // 150ms — CACTUS is generous with rate limits
        }

        echo "  → Formula: +$totalFormula  MW: +$totalMw  Skip: $totalFail\n\n";

        // If no new formulas were found this entire round, remaining ones are polymers/mixtures — stop
        if ($roundSuccess === 0) {
            echo "  ⚠️ No new formulas found in this round. Remaining are likely polymers/mixtures. Stopping.\n\n";
            break;
        }
    }

    $nowFormula = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A'")['c'];
    echo "  📊 Formulas now: $nowFormula (was: $hasFormula, added: " . ($nowFormula - $hasFormula) . ")\n";
    $naCount = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND molecular_formula = 'N/A'")['c'];
    echo "  📊 Marked N/A (polymers/mixtures): $naCount\n\n";
}

// ══════════════════════════════════════════════════════════════
//  PHASE 2: GHS + CID + Structure from PubChem (if not blocked)
// ══════════════════════════════════════════════════════════════
if (!$pubchemBlocked && ($mode === 'all' || $mode === 'ghs')) {
    echo "══════════════════════════════════════════════════════\n";
    echo "  PHASE 2: GHS + CID from PubChem\n";
    echo "══════════════════════════════════════════════════════\n\n";

    $round = 0;
    $totalGhs = 0;
    $totalImg = 0;
    $totalNoData = 0;
    $totalErr = 0;
    $consecutiveBlocks = 0;

    while (true) {
        $round++;

        // Get chemicals that need real GHS data
        $chemicals = Database::fetchAll("
            SELECT c.id, c.cas_number, c.name FROM chemicals c
            WHERE c.is_active = 1
              AND c.cas_number IS NOT NULL AND c.cas_number != ''
              AND NOT EXISTS (
                  SELECT 1 FROM chemical_ghs_data g
                  WHERE g.chemical_id = c.id
                    AND g.source LIKE 'PubChem CID:%'
                    AND g.source NOT LIKE '%No GHS%'
                    AND g.source NOT LIKE '%No CID%'
                    AND g.source NOT LIKE '%No data%'
              )
            ORDER BY c.id ASC
            LIMIT :lim
        ", [':lim' => $batchSize]);

        if (empty($chemicals)) {
            echo "   ✅ All chemicals have GHS data!\n\n";
            break;
        }

        $remaining = (int)Database::fetch("
            SELECT COUNT(*) as c FROM chemicals c
            WHERE c.is_active = 1 AND c.cas_number != ''
            AND NOT EXISTS (
                SELECT 1 FROM chemical_ghs_data g WHERE g.chemical_id = c.id
                AND g.source LIKE 'PubChem CID:%'
                AND g.source NOT LIKE '%No GHS%' AND g.source NOT LIKE '%No CID%' AND g.source NOT LIKE '%No data%'
            )
        ")['c'];

        echo "  Round $round — Need GHS: $remaining\n";

        foreach ($chemicals as $idx => $chem) {
            $cas = trim($chem['cas_number']);
            $chemId = (int)$chem['id'];
            $n = $idx + 1;
            $total_batch = count($chemicals);

            echo "  [$n/$total_batch] #$chemId CAS:$cas ";

            try {
                // Step 1: Get CID
                $cidBody = fetchUrl("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/cids/JSON", 15);
                
                if ($cidBody === '__BLOCKED__') {
                    $consecutiveBlocks++;
                    echo "🚫blocked";
                    if ($consecutiveBlocks >= 3) {
                        echo "\n  ⚠️ PubChem blocked after $n chemicals this round. Stopping GHS phase.\n";
                        goto endGhs;
                    }
                    echo "\n";
                    sleep(2);
                    continue;
                }
                $consecutiveBlocks = 0;

                if (!$cidBody) {
                    echo "⊘cid\n";
                    saveNoDataRecord($chemId, "PubChem: No CID for CAS $cas");
                    $totalNoData++;
                    usleep(300000);
                    continue;
                }

                $cidData = json_decode($cidBody, true);
                $cid = $cidData['IdentifierList']['CID'][0] ?? null;
                if (!$cid) {
                    echo "⊘cid\n";
                    saveNoDataRecord($chemId, "PubChem: No CID for CAS $cas");
                    $totalNoData++;
                    usleep(300000);
                    continue;
                }

                // Step 2: Set structure image + 3D (no API call — deterministic URL)
                $existImg = Database::fetch("SELECT image_url FROM chemicals WHERE id=:id", [':id'=>$chemId]);
                if (empty($existImg['image_url']) || strpos($existImg['image_url'], '/name/') !== false) {
                    $db->prepare("UPDATE chemicals SET image_url=:img, model_3d_url=:m3d WHERE id=:id")->execute([
                        ':img' => "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/PNG?image_size=300x300",
                        ':m3d' => "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=3D-Conformer",
                        ':id'  => $chemId,
                    ]);
                    $totalImg++;
                }

                // Step 3: Get GHS data
                $ghsBody = fetchUrl("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/{$cid}/JSON?heading=GHS+Classification", 15);
                if ($ghsBody === '__BLOCKED__') {
                    $consecutiveBlocks++;
                    echo "✓img 🚫ghs-blocked\n";
                    if ($consecutiveBlocks >= 3) {
                        echo "  ⚠️ PubChem blocked. Stopping GHS phase.\n";
                        goto endGhs;
                    }
                    continue;
                }
                $consecutiveBlocks = 0;

                if (!$ghsBody) {
                    echo "⊘ghs\n";
                    saveNoDataRecord($chemId, "PubChem CID:$cid - No GHS data");
                    $totalNoData++;
                    usleep(300000);
                    continue;
                }

                $ghsRaw = json_decode($ghsBody, true);
                if (!$ghsRaw) {
                    echo "⊘ghs-parse\n";
                    $totalNoData++;
                    usleep(300000);
                    continue;
                }

                // Step 4: Build + Save GHS
                $ghs = buildGhsRecord($chemId, $cid, $ghsRaw);
                saveGhsData($ghs);
                updateChemicalsTable($chemId, $ghs);

                echo "✓ CID:$cid " . count($ghs['ghs_pictograms']) . "pics\n";
                $totalGhs++;

            } catch (Exception $e) {
                echo "✗ " . substr($e->getMessage(), 0, 50) . "\n";
                $totalErr++;
            }

            usleep(350000); // 350ms between PubChem requests
        }

        echo "  → GHS:+$totalGhs  Img:+$totalImg  NoData:$totalNoData  Err:$totalErr\n\n";
    }
    endGhs:

    $nowGhs = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'];
    echo "\n  📊 Real GHS now: $nowGhs (was: $hasGhsReal, added: " . ($nowGhs - $hasGhsReal) . ")\n\n";
}

// ══════════════════════════════════════════════════════════════
//  FINAL SUMMARY
// ══════════════════════════════════════════════════════════════
$stats = [
    'formula'  => (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active=1")['c'],
    'ghsReal'  => (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'],
    'withImg'  => (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url IS NOT NULL AND image_url != '' AND is_active=1")['c'],
    'cidImg'   => (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url LIKE '%/cid/%' AND is_active=1")['c'],
    'with3d'   => (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE model_3d_url IS NOT NULL AND model_3d_url != '' AND is_active=1")['c'],
    'sds'      => (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'],
];

echo "╔══════════════════════════════════════════════════╗\n";
echo "║  SMART ENRICHMENT COMPLETE                      ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  Formulas:        {$stats['formula']}\n";
echo "║  Real GHS:        {$stats['ghsReal']}\n";
echo "║  Structure Img:   {$stats['withImg']}  (CID: {$stats['cidImg']})\n";
echo "║  3D Model URLs:   {$stats['with3d']}\n";
echo "║  SDS Links:       {$stats['sds']}\n";
echo "╚══════════════════════════════════════════════════╝\n";

// ╔═══════════════════════════════════════════════════════════╗
// ║  HELPER FUNCTIONS                                        ║
// ╚═══════════════════════════════════════════════════════════╝

/**
 * Generic HTTP GET with block detection
 */
function fetchUrl($url, $timeout = 10) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ChemInventory/1.0 (educational research)\r\nAccept: application/json,text/plain,*/*\r\n",
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => false,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return false;

    // Check HTTP status
    $status = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $h, $m)) $status = (int)$m[1];
        }
    }

    // NCBI block detection
    if ($status === 302 || strpos($body, 'misuse.ncbi') !== false || strpos($body, 'Access Denied') !== false) {
        return '__BLOCKED__';
    }

    if ($status === 200) return $body;
    if ($status === 404) return null;

    return null;
}

/**
 * NCI CACTUS Chemical Identifier Resolver
 * https://cactus.nci.nih.gov/chemical/structure
 * Simple plain-text responses — very fast
 */
function fetchCactus($cas, $property) {
    $url = "https://cactus.nci.nih.gov/chemical/structure/" . urlencode($cas) . "/{$property}";
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ChemInventory/1.0 (educational)\r\n",
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;

    $status = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $h, $m)) $status = (int)$m[1];
        }
    }
    if ($status !== 200) return null;

    $body = trim($body);
    // CACTUS returns "Page not found" or HTML on error
    if (stripos($body, '<html') !== false || stripos($body, 'not found') !== false) return null;

    return $body;
}

/**
 * Save GHS no-data placeholder
 */
function saveNoDataRecord($chemId, $source) {
    $db = Database::getInstance();
    $db->prepare("
        INSERT INTO chemical_ghs_data
            (chemical_id, ghs_pictograms, signal_word, h_statements, h_statements_text,
             p_statements, p_statements_text, safety_summary, source)
        VALUES (:cid, '[]', 'None', '[]', '', '[]', '', '', :src)
        ON DUPLICATE KEY UPDATE source=VALUES(source), updated_at=NOW()
    ")->execute([':cid' => $chemId, ':src' => $source]);
}

// ── GHS building functions (same as enrich_batch_runner.php) ──

function buildGhsRecord($chemId, $cid, $raw) {
    $ghs = [
        'chemical_id'=>$chemId, 'ghs_pictograms'=>[], 'signal_word'=>'None',
        'h_statements'=>[], 'h_statements_text'=>'', 'p_statements'=>[], 'p_statements_text'=>'',
        'safety_summary'=>'', 'source'=>"PubChem CID:$cid",
    ];

    $sections = extractSections($raw);

    // Pictograms
    $ghsMap = ['Explod'=>'GHS01','Flame Over'=>'GHS03','Flame'=>'GHS02','Gas Cylinder'=>'GHS04',
        'Corrosion'=>'GHS05','Skull'=>'GHS06','Exclamation'=>'GHS07','Health Hazard'=>'GHS08','Environment'=>'GHS09'];
    foreach ($sections as $s) {
        if (stripos($s['heading'],'Pictogram')!==false || stripos($s['name'],'Pictogram')!==false) {
            if (!empty($s['markup'])) {
                foreach ($s['markup'] as $mk) {
                    $url = $mk['URL'] ?? $mk['Extra'] ?? '';
                    if (preg_match('/GHS0[1-9]/', $url, $m)) $ghs['ghs_pictograms'][] = $m[0];
                }
            }
            foreach ($ghsMap as $kw => $code) {
                if (stripos($s['value'], $kw) !== false) $ghs['ghs_pictograms'][] = $code;
            }
        }
    }
    $ghs['ghs_pictograms'] = array_values(array_unique($ghs['ghs_pictograms']));

    // Signal Word
    $sw = '';
    foreach ($sections as $s) {
        if (stripos($s['heading'],'Signal')!==false||stripos($s['name'],'Signal')!==false) { $sw = $s['value']; break; }
    }
    if (stripos($sw,'Danger')!==false) $ghs['signal_word']='Danger';
    elseif (stripos($sw,'Warning')!==false) $ghs['signal_word']='Warning';

    // H-statements
    foreach ($sections as $s) {
        if (stripos($s['heading'],'Hazard Statement')!==false||stripos($s['name'],'Hazard Statement')!==false) {
            $lines = array_filter(explode("\n", $s['value']));
            foreach ($lines as $l) {
                $ghs['h_statements_text'] .= ($ghs['h_statements_text']?"\n":'') . $l;
                if (preg_match('/H\d{3}[A-Za-z+]*/', $l, $m)) $ghs['h_statements'][] = $m[0];
            }
        }
    }

    // P-statements
    foreach ($sections as $s) {
        if (stripos($s['heading'],'Precautionary Statement')!==false||stripos($s['name'],'Precautionary Statement')!==false) {
            $lines = array_filter(explode("\n", $s['value']));
            foreach ($lines as $l) {
                $ghs['p_statements_text'] .= ($ghs['p_statements_text']?"\n":'') . $l;
                if (preg_match('/P\d{3}[+P\d]*/', $l, $m)) $ghs['p_statements'][] = $m[0];
            }
        }
    }

    // Safety summary
    $parts = [];
    if ($ghs['ghs_pictograms']) {
        $names = ['GHS01'=>'Explosive','GHS02'=>'Flammable','GHS03'=>'Oxidizer','GHS04'=>'Compressed Gas',
            'GHS05'=>'Corrosive','GHS06'=>'Acute Toxicity','GHS07'=>'Irritant','GHS08'=>'Health Hazard','GHS09'=>'Environmental'];
        $parts[] = 'Hazards: '.implode(', ', array_map(fn($p)=>$names[$p]??$p, $ghs['ghs_pictograms'])).'.';
    }
    if ($ghs['signal_word']!=='None') $parts[] = "Signal: {$ghs['signal_word']}.";
    $ghs['safety_summary'] = implode(' ', $parts);

    // First aid
    $pics = $ghs['ghs_pictograms'];
    if (array_intersect($pics,['GHS06','GHS07','GHS08']))
        $ghs['first_aid_inhalation'] = 'Move to fresh air. If breathing is difficult, give oxygen. Seek medical attention immediately.';
    if (array_intersect($pics,['GHS05','GHS06','GHS07']))
        $ghs['first_aid_skin'] = 'Remove contaminated clothing. Rinse skin with plenty of water for at least 15 minutes.';
    if (array_intersect($pics,['GHS05','GHS07']))
        $ghs['first_aid_eye'] = 'Rinse cautiously with water for several minutes. Remove contact lenses. Continue rinsing 15 min.';
    if (array_intersect($pics,['GHS05','GHS06','GHS07','GHS08']))
        $ghs['first_aid_ingestion'] = 'Do NOT induce vomiting. Rinse mouth with water. Seek medical attention immediately.';

    // Storage
    $stor = [];
    if (in_array('GHS01',$pics)) $stor[] = 'Keep away from heat/sparks/flames. Store separately.';
    if (in_array('GHS02',$pics)) $stor[] = 'Store in cool, well-ventilated area. Keep container tightly closed.';
    if (in_array('GHS03',$pics)) $stor[] = 'Keep away from combustible materials.';
    if (in_array('GHS04',$pics)) $stor[] = 'Protect from sunlight. Store in well-ventilated area.';
    if (in_array('GHS05',$pics)) $stor[] = 'Store in corrosion-resistant container.';
    if (in_array('GHS06',$pics)) $stor[] = 'Store locked up.';
    if (in_array('GHS09',$pics)) $stor[] = 'Avoid release to environment.';
    $ghs['storage_instructions'] = implode(' ', $stor);

    // Handling
    $hand = [];
    if (array_intersect($pics,['GHS05','GHS06','GHS07','GHS08'])) $hand[] = 'Wear appropriate PPE.';
    if (array_intersect($pics,['GHS02','GHS04'])) $hand[] = 'Use in well-ventilated area.';
    if (array_intersect($pics,['GHS06','GHS08'])) $hand[] = 'Avoid breathing fumes/vapors.';
    $ghs['handling_precautions'] = implode(' ', $hand);
    $ghs['disposal_instructions'] = 'Dispose in accordance with local regulations.';

    return $ghs;
}

function extractSections($data, $depth = 0) {
    $results = [];
    if (!is_array($data)) return $results;
    if (isset($data['Record']['Section'])) return extractSections($data['Record']['Section'], $depth);
    foreach ($data as $item) {
        if (!is_array($item)) continue;
        $heading = $item['TOCHeading'] ?? '';
        if (isset($item['Information'])) {
            foreach ($item['Information'] as $info) {
                $name = $info['Name'] ?? $heading;
                $value = '';
                $markup = [];
                if (isset($info['Value']['StringWithMarkup'])) {
                    foreach ($info['Value']['StringWithMarkup'] as $swm) {
                        $value .= ($swm['String'] ?? '') . "\n";
                        if (isset($swm['Markup'])) $markup = array_merge($markup, $swm['Markup']);
                    }
                }
                $results[] = ['heading'=>$heading, 'name'=>$name, 'value'=>trim($value), 'markup'=>$markup];
            }
        }
        if (isset($item['Section'])) $results = array_merge($results, extractSections($item['Section'], $depth + 1));
    }
    return $results;
}

function saveGhsData($ghs) {
    $db = Database::getInstance();
    $db->prepare("
        INSERT INTO chemical_ghs_data
            (chemical_id, ghs_pictograms, signal_word, h_statements, h_statements_text,
             p_statements, p_statements_text, safety_summary, handling_precautions,
             storage_instructions, disposal_instructions, first_aid_inhalation,
             first_aid_skin, first_aid_eye, first_aid_ingestion, source)
        VALUES (:cid,:pics,:sw,:hs,:ht,:ps,:pt,:ss,:hp,:si,:di,:fi,:fs,:fe,:fg,:src)
        ON DUPLICATE KEY UPDATE
            ghs_pictograms=VALUES(ghs_pictograms), signal_word=VALUES(signal_word),
            h_statements=VALUES(h_statements), h_statements_text=VALUES(h_statements_text),
            p_statements=VALUES(p_statements), p_statements_text=VALUES(p_statements_text),
            safety_summary=VALUES(safety_summary), handling_precautions=VALUES(handling_precautions),
            storage_instructions=VALUES(storage_instructions), disposal_instructions=VALUES(disposal_instructions),
            first_aid_inhalation=VALUES(first_aid_inhalation), first_aid_skin=VALUES(first_aid_skin),
            first_aid_eye=VALUES(first_aid_eye), first_aid_ingestion=VALUES(first_aid_ingestion),
            source=VALUES(source), updated_at=NOW()
    ")->execute([
        ':cid'=>$ghs['chemical_id'], ':pics'=>json_encode($ghs['ghs_pictograms']),
        ':sw'=>$ghs['signal_word'], ':hs'=>json_encode($ghs['h_statements']),
        ':ht'=>$ghs['h_statements_text'], ':ps'=>json_encode($ghs['p_statements']),
        ':pt'=>$ghs['p_statements_text'], ':ss'=>$ghs['safety_summary'],
        ':hp'=>$ghs['handling_precautions']??null, ':si'=>$ghs['storage_instructions']??null,
        ':di'=>$ghs['disposal_instructions']??null, ':fi'=>$ghs['first_aid_inhalation']??null,
        ':fs'=>$ghs['first_aid_skin']??null, ':fe'=>$ghs['first_aid_eye']??null,
        ':fg'=>$ghs['first_aid_ingestion']??null, ':src'=>$ghs['source'],
    ]);
}

function updateChemicalsTable($chemId, $ghs) {
    $picMap = ['GHS01'=>'explosive','GHS02'=>'flammable','GHS03'=>'oxidizer','GHS04'=>'compressed_gas',
        'GHS05'=>'corrosive','GHS06'=>'toxic','GHS07'=>'irritant','GHS08'=>'health_hazard','GHS09'=>'environmental'];
    $legacyPics = array_map(fn($p)=>$picMap[$p]??$p, $ghs['ghs_pictograms']);
    $signalMap = ['Danger'=>'Danger','Warning'=>'Warning','None'=>'No signal word'];

    $sets = []; $params = [':id'=>$chemId];
    if ($ghs['ghs_pictograms']) { $sets[]='hazard_pictograms=:hp'; $params[':hp']=json_encode($legacyPics); }
    if ($ghs['signal_word']!=='None') { $sets[]='signal_word=:sw'; $params[':sw']=$signalMap[$ghs['signal_word']]??'No signal word'; }
    if ($ghs['h_statements_text']) { $sets[]='hazard_statements=:hs'; $params[':hs']=json_encode(explode("\n",$ghs['h_statements_text'])); }
    if ($ghs['p_statements_text']) { $sets[]='precautionary_statements=:ps'; $params[':ps']=json_encode(explode("\n",$ghs['p_statements_text'])); }
    if ($ghs['storage_instructions']??null) { $sets[]='storage_requirements=:sr'; $params[':sr']=$ghs['storage_instructions']; }
    if ($ghs['handling_precautions']??null) { $sets[]='handling_procedures=:hpr'; $params[':hpr']=$ghs['handling_precautions']; }
    if (($ghs['first_aid_inhalation']??null)) {
        $sets[]='first_aid_measures=:fam';
        $params[':fam']="Inhalation: ".($ghs['first_aid_inhalation']??'')."\nSkin: ".($ghs['first_aid_skin']??'')."\nEye: ".($ghs['first_aid_eye']??'')."\nIngestion: ".($ghs['first_aid_ingestion']??'');
    }
    if ($ghs['disposal_instructions']??null) { $sets[]='disposal_methods=:dm'; $params[':dm']=$ghs['disposal_instructions']; }

    if ($sets) {
        Database::getInstance()->prepare("UPDATE chemicals SET ".implode(',',$sets)." WHERE id=:id")->execute($params);
    }
}
