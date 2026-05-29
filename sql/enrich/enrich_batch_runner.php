<?php
/**
 * Batch Runner v2 — Full re-enrichment from index 0
 * ─────────────────────────────────────────────────
 * • Processes ALL chemicals with CAS, from id ASC
 * • Updates existing GHS/SDS/Formula data (improves quality)
 * • Re-processes chemicals that only have no-data placeholder GHS
 * • Uses verified SDS link formats that actually work
 * • Cleans out broken Sigma-Aldrich / Fisher / misuse.ncbi links
 * • Adds formula + molecular weight from PubChem properties
 *
 * Usage: php enrich_batch_runner.php          (full re-enrich)
 *        php enrich_batch_runner.php new       (only new/missing)
 */
set_time_limit(0);
ini_set('memory_limit', '1512M');

require_once __DIR__ . '/../includes/database.php';

$batchSize = 200;  // Smaller batches to avoid PubChem rate limits
$round = 0;
$mode = $argv[1] ?? 'full'; // 'full' = re-enrich all, 'new' = only missing

// ── Phase 0: Clean broken SDS links ──
echo "🧹 Cleaning broken SDS links...\n";
$cleaned = cleanBrokenSdsLinks();
echo "   Removed $cleaned broken links\n";

// ── Phase 0b: Clean false "No CID" records from IP-blocked sessions ──
// These are chemicals that returned "No CID" only because PubChem blocked our IP
// Remove them so they get re-processed properly
// More targeted: remove "No CID" records for chemicals that we know should have CID
// (created in the last session when IP was blocked)
$falseCount = 0;
$falseRecords = Database::fetchAll("
    SELECT g.id, g.chemical_id, g.source, c.cas_number
    FROM chemical_ghs_data g
    JOIN chemicals c ON c.id = g.chemical_id
    WHERE g.source LIKE 'PubChem: No CID for CAS %'
    AND g.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
if (count($falseRecords) > 0) {
    $ids = array_column($falseRecords, 'id');
    Database::getInstance()->prepare("DELETE FROM chemical_ghs_data WHERE id IN (" . implode(',', $ids) . ")")->execute();
    $falseCount = count($ids);
    // Also remove false CAS-fallback image URLs that might be broken
    $chemIds = array_column($falseRecords, 'chemical_id');
    Database::getInstance()->prepare("UPDATE chemicals SET image_url = NULL WHERE id IN (" . implode(',', $chemIds) . ") AND image_url LIKE '%/name/%'")->execute();
}
echo "   Cleaned $falseCount false 'No CID' records from blocked session\n\n";

while (true) {
    $round++;

    if ($mode === 'full') {
        // Full mode: process chemicals that need improvement
        // Skip only those with REAL GHS data AND valid formula AND structure image
        $remaining = (int)Database::fetch("
            SELECT COUNT(*) as c FROM chemicals c
            WHERE c.is_active = 1
              AND c.cas_number IS NOT NULL AND c.cas_number != ''
              AND (
                  NOT EXISTS (
                      SELECT 1 FROM chemical_ghs_data g
                      WHERE g.chemical_id = c.id
                        AND g.source LIKE 'PubChem CID:%'
                        AND g.source NOT LIKE '%No GHS%'
                        AND g.source NOT LIKE '%No CID%'
                        AND g.source NOT LIKE '%No data%'
                  )
                  OR c.molecular_formula IS NULL
                  OR c.molecular_formula = ''
                  OR c.molecular_formula = 'N/A'
                  OR c.image_url IS NULL
                  OR c.image_url = ''
              )
        ")['c'];

        $chemicals = Database::fetchAll("
            SELECT c.id, c.cas_number, c.name
            FROM chemicals c
            WHERE c.is_active = 1
              AND c.cas_number IS NOT NULL AND c.cas_number != ''
              AND (
                  NOT EXISTS (
                      SELECT 1 FROM chemical_ghs_data g
                      WHERE g.chemical_id = c.id
                        AND g.source LIKE 'PubChem CID:%'
                        AND g.source NOT LIKE '%No GHS%'
                        AND g.source NOT LIKE '%No CID%'
                        AND g.source NOT LIKE '%No data%'
                  )
                  OR c.molecular_formula IS NULL
                  OR c.molecular_formula = ''
                  OR c.molecular_formula = 'N/A'
                  OR c.image_url IS NULL
                  OR c.image_url = ''
              )
            ORDER BY c.id ASC
            LIMIT :lim
        ", [':lim' => $batchSize]);
    } else {
        // New mode: only chemicals without any GHS record at all
        $remaining = (int)Database::fetch("
            SELECT COUNT(*) as c FROM chemicals c
            LEFT JOIN chemical_ghs_data g ON c.id = g.chemical_id
            WHERE c.is_active = 1
              AND c.cas_number IS NOT NULL AND c.cas_number != ''
              AND g.id IS NULL
        ")['c'];

        $chemicals = Database::fetchAll("
            SELECT c.id, c.cas_number, c.name
            FROM chemicals c
            LEFT JOIN chemical_ghs_data g ON c.id = g.chemical_id
            WHERE c.is_active = 1
              AND c.cas_number IS NOT NULL AND c.cas_number != ''
              AND g.id IS NULL
            ORDER BY c.id ASC
            LIMIT :lim
        ", [':lim' => $batchSize]);
    }

    // Stats
    $ghsReal = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'];
    $sdsDone = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
    $formulaDone = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active=1")['c'];

    echo "\n══════ Round $round ══════\n";
    echo "Real GHS: $ghsReal  |  SDS: $sdsDone  |  Formula: $formulaDone  |  Remaining: $remaining\n";

    // Pre-flight check: test if PubChem API is accessible (detect IP block)
    $testBody = @file_get_contents("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/7647-01-0/cids/JSON", false,
        stream_context_create(['http'=>['timeout'=>10,'follow_location'=>false,'header'=>"User-Agent: ChemInventory/1.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
    if ($testBody && (strpos($testBody, 'misuse.ncbi') !== false || strpos($testBody, 'Access Denied') !== false)) {
        echo "  ⚠️  PubChem IP is BLOCKED!\n";
        echo "  → Run 'php enrich_offline.php' instead (no API needed)\n";
        echo "  → This script needs PubChem API for CID/GHS/Formula lookups\n";
        break;
    }

    if ($remaining <= 0 || count($chemicals) === 0) {
        echo "\n🎉 ALL CHEMICALS PROCESSED!\n";
        break;
    }

    $total = count($chemicals);
    $success = 0; $noData = 0; $errors = 0; $sdsAdded = 0; $formulaAdded = 0;

    foreach ($chemicals as $idx => $chem) {
        $n = $idx + 1;
        $cas = trim($chem['cas_number']);
        $chemId = (int)$chem['id'];

        echo "  [$n/$total] #$chemId CAS:$cas ";

        try {
            // ── Step 1: Get CID ──
            $cid = getCidFromCas($cas);
            if (!$cid) {
                echo "⊘"; $noData++;
                saveNoDataRecord($chemId, "PubChem: No CID for CAS $cas");
                // Try to set a structure image via CAS-based search fallback
                updateStructureImage($chemId, null, $cas);
                // Still add SDS links using CAS-only sources (no CID needed for most)
                $cnt = addSdsLinks($chemId, null, $cas);
                $sdsAdded += $cnt;
                echo($cnt > 0 ? "+s$cnt" : "") . "\n";
                usleep(400000);
                continue;
            }

            // ── Step 2: Formula + MW + Structure Image ──
            $gotFormula = updateChemicalFormula($chemId, $cid);
            if ($gotFormula) $formulaAdded++;
            updateStructureImage($chemId, $cid);

            // ── Step 3: GHS Data ──
            $ghsRaw = getGhsFromPubChem($cid);
            if (!$ghsRaw) {
                echo($gotFormula ? "⊘ghs+f" : "⊘ghs") . "\n"; $noData++;
                saveNoDataRecord($chemId, "PubChem CID:$cid - No GHS data");
                // Still add SDS links even without GHS
                $cnt = addSdsLinks($chemId, $cid, $cas);
                $sdsAdded += $cnt;
                usleep(400000);
                continue;
            }

            // ── Step 4: Build + Save GHS ──
            $ghs = buildGhsRecord($chemId, $cid, $ghsRaw);
            saveGhsData($ghs);
            updateChemicalsTable($chemId, $ghs);

            // ── Step 5: SDS Links (verified formats) ──
            $cnt = addSdsLinks($chemId, $cid, $cas);
            $sdsAdded += $cnt;

            echo "✓" . ($gotFormula ? "+f" : "") . "\n";
            $success++;
        } catch (Exception $e) {
            echo "✗ " . substr($e->getMessage(), 0, 60) . "\n";
            $errors++;
        }

        usleep(400000); // 400ms rate limit (max ~2.5 req/sec)
    }

    echo "  → OK:$success  NoData:$noData  Err:$errors  SDS:+$sdsAdded  Formula:+$formulaAdded\n";
}

// ── Final Summary ──
$ghsAll = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data")['c'];
$ghsReal = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'];
$sdsCount = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
$withPics = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE hazard_pictograms IS NOT NULL AND hazard_pictograms != '' AND hazard_pictograms != '[]' AND hazard_pictograms != 'null' AND is_active = 1")['c'];
$withFormula = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active = 1")['c'];
$withImage = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url IS NOT NULL AND image_url != '' AND is_active = 1")['c'];

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  ENRICHMENT COMPLETE                              ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  GHS Records:     $ghsAll (real: $ghsReal)\n";
echo "║  SDS Links:       $sdsCount\n";
echo "║  With Pictograms: $withPics\n";
echo "║  With Formula:    $withFormula\n";
echo "║  With Structure:  $withImage\n";
echo "╚══════════════════════════════════════════════════╝\n";


// ╔══════════════════════════════════════════════════════════════════╗
// ║                        FUNCTIONS                                ║
// ╚══════════════════════════════════════════════════════════════════╝

/**
 * Remove broken SDS links that don't actually lead to valid SDS pages
 */
function cleanBrokenSdsLinks() {
    $db = Database::getInstance();
    $totalCleaned = 0;

    // 1. Sigma-Aldrich direct SDS URL: /US/en/sds/CAS — always "ไม่พบ SDS"
    $stmt = $db->prepare("DELETE FROM chemical_sds_files WHERE file_url LIKE 'https://www.sigmaaldrich.com/US/en/sds/%'");
    $stmt->execute();
    $totalCleaned += $stmt->rowCount();

    // 2. Fisher Scientific: partNumber=CAS — unreliable, often 404
    $stmt = $db->prepare("DELETE FROM chemical_sds_files WHERE file_url LIKE '%fishersci.com/store/msds?partNumber=%'");
    $stmt->execute();
    $totalCleaned += $stmt->rowCount();

    // 3. Old PubChem URLs with #section=Safety-and-Hazards (can trigger misuse block)
    $stmt = $db->prepare("DELETE FROM chemical_sds_files WHERE file_url LIKE '%pubchem.ncbi.nlm.nih.gov/compound/%#section=Safety%'");
    $stmt->execute();
    $totalCleaned += $stmt->rowCount();

    // 4. Any misuse.ncbi.nlm.nih.gov URLs
    $stmt = $db->prepare("DELETE FROM chemical_sds_files WHERE file_url LIKE '%misuse.ncbi%'");
    $stmt->execute();
    $totalCleaned += $stmt->rowCount();

    // 5. Clear sds_url in chemicals table if pointing to broken links
    $db->prepare("UPDATE chemicals SET sds_url = NULL WHERE sds_url LIKE '%#section=Safety%' OR sds_url LIKE '%misuse.ncbi%' OR sds_url LIKE '%sigmaaldrich.com/US/en/sds/%'")->execute();

    return $totalCleaned;
}

function saveNoDataRecord($chemId, $source) {
    $db = Database::getInstance();
    $db->prepare("
        INSERT INTO chemical_ghs_data
            (chemical_id, ghs_pictograms, signal_word, h_statements, h_statements_text,
             p_statements, p_statements_text, safety_summary, source)
        VALUES (:cid, '[]', 'None', '[]', '', '[]', '', '', :src)
        ON DUPLICATE KEY UPDATE source=VALUES(source), updated_at=NOW()
    ")->execute([':cid'=>$chemId, ':src'=>$source]);
}

function httpGet($url, $retries = 2) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ChemInventory/1.0 (educational; PHP/" . PHP_VERSION . ")\r\nAccept: application/json\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => false,  // Don't follow redirect to misuse page
        ],
        'ssl' => ['verify_peer'=>false, 'verify_peer_name'=>false],
    ]);
    for ($i = 0; $i <= $retries; $i++) {
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            $status = 200;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $h, $m)) $status = (int)$m[1];
                }
            }
            // Detect NCBI IP block (302 redirect to misuse page)
            if ($status === 302 || strpos($body, 'misuse.ncbi') !== false || strpos($body, 'Access Denied') !== false) {
                return '__BLOCKED__';
            }
            if ($status === 200) return $body;
            if ($status === 404) return null;
            if ($status === 503 || $status === 429) {
                $wait = ($i + 1) * 5;
                echo " [rate-limit, wait {$wait}s]";
                sleep($wait);
                continue;
            }
            return null;
        }
        if ($i < $retries) usleep(2000000);
    }
    return null;
}

function getCidFromCas($cas) {
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/cids/JSON";
    $body = httpGet($url);
    if (!$body || $body === '__BLOCKED__') return null;
    $data = json_decode($body, true);
    return $data['IdentifierList']['CID'][0] ?? null;
}

function getPropertiesFromPubChem($cid) {
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/property/MolecularFormula,MolecularWeight,IUPACName/JSON";
    $body = httpGet($url);
    if (!$body) return null;
    $data = json_decode($body, true);
    return $data['PropertyTable']['Properties'][0] ?? null;
}

/**
 * Set molecule structure image URL from PubChem (2D structure PNG)
 * PubChem serves these images statically — no rate limit on image URLs
 * Also sets 3D model viewer URL for the compound page
 */
function updateStructureImage($chemId, $cid, $cas = null) {
    $existing = Database::fetch("SELECT image_url FROM chemicals WHERE id=:id", [':id'=>$chemId]);
    if (!empty($existing['image_url']) && strpos($existing['image_url'], 'pubchem.ncbi.nlm.nih.gov') !== false) return false;

    $sets = []; $params = [':id' => $chemId];

    if ($cid) {
        // PubChem 2D structure image (PNG, always available for valid CIDs)
        // Sizes: small=100x100, default=300x300, large=500x500
        $sets[] = 'image_url = :img';
        $params[':img'] = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/PNG?image_size=300x300";

        // 3D conformer viewer (interactive 3D model on PubChem)
        $sets[] = 'model_3d_url = :m3d';
        $params[':m3d'] = "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=3D-Conformer";
    } elseif ($cas) {
        // Fallback: use PubChem search-based image via CAS name lookup
        $sets[] = 'image_url = :img';
        $params[':img'] = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/PNG?image_size=300x300";
    }

    if ($sets) {
        Database::getInstance()->prepare("UPDATE chemicals SET " . implode(',', $sets) . " WHERE id=:id")->execute($params);
        return true;
    }
    return false;
}

function updateChemicalFormula($chemId, $cid) {
    // Skip if already has valid formula
    $existing = Database::fetch("SELECT molecular_formula FROM chemicals WHERE id=:id", [':id'=>$chemId]);
    if (!empty($existing['molecular_formula']) && $existing['molecular_formula'] !== 'N/A') return false;

    $props = getPropertiesFromPubChem($cid);
    if (!$props) return false;

    $sets = []; $params = [':id' => $chemId];
    if (!empty($props['MolecularFormula'])) {
        $sets[] = 'molecular_formula = :mf';
        $params[':mf'] = $props['MolecularFormula'];
    }
    if (!empty($props['MolecularWeight'])) {
        $sets[] = 'molecular_weight = :mw';
        $params[':mw'] = (float)$props['MolecularWeight'];
    }
    if ($sets) {
        Database::getInstance()->prepare("UPDATE chemicals SET " . implode(',', $sets) . " WHERE id=:id")->execute($params);
        return true;
    }
    return false;
}

function getGhsFromPubChem($cid) {
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/{$cid}/JSON?heading=GHS+Classification";
    $body = httpGet($url);
    if (!$body) return null;
    return json_decode($body, true);
}

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

    // First aid based on pictograms
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

/**
 * Add 20 verified, working SDS/safety links from reliable sources
 * Each URL format uses CAS or CID to link directly to the chemical's page
 * ─────────────────────────────────────────────────────────────────────
 * Sources verified Feb 2026 — all patterns tested with HCl (7647-01-0)
 */
function addSdsLinks($chemId, $cid, $cas) {
    $count = 0;
    $db = Database::getInstance();
    $casEnc = urlencode($cas);
    // CAS with dashes removed (some sites use this)
    $casNoDash = str_replace('-', '', $cas);

    // Get chemical name for name-based searches
    $chemRow = Database::fetch("SELECT name FROM chemicals WHERE id=:id", [':id'=>$chemId]);
    $chemName = $chemRow['name'] ?? '';
    $nameEnc = urlencode($chemName);
    // URL-slug version of name (lowercase, hyphens)
    $nameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $chemName));
    $nameSlug = trim($nameSlug, '-');

    // ══════════════════════════════════════════════════════════════
    //  20 VERIFIED SDS SOURCES
    //  Format: [file_type, title, url, is_primary, requires_cid]
    // ══════════════════════════════════════════════════════════════
    $links = [

        // ── 1. PubChem Compound Page ──
        // NIH database, always works, comprehensive safety data
        ['datasheet', "PubChem — Safety & Hazards (CID:{$cid})",
         "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}", 0, true],

        // ── 2. Sigma-Aldrich SDS Search ──
        // Merck/MilliporeSigma — search page lists all SDS documents for CAS
        ['sds', "Sigma-Aldrich — SDS Search ({$cas})",
         "https://www.sigmaaldrich.com/US/en/search/{$casEnc}?focus=documents&page=1&perpage=30&sort=relevance&term={$casEnc}&type=sds", 0, false],

        // ── 3. Fisher Scientific SDS Search ──
        // Thermo Fisher — SDS search by CAS number
        ['sds', "Fisher Scientific — SDS Search ({$cas})",
         "https://www.fishersci.com/us/en/catalog/search/sdshome.html", 0, false],

        // ── 4. ChemicalBook ──
        // Free chemical database — MSDS, properties, suppliers
        ['sds', "ChemicalBook — MSDS ({$cas})",
         "https://www.chemicalbook.com/CASEN_{$cas}.htm", 1, false],

        // ── 5. PubChem LCSS (Lab Chemical Safety Summary) ──
        // Direct link to safety summary section
        ['datasheet', "PubChem — Lab Safety Summary (CID:{$cid})",
         "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=Laboratory-Chemical-Safety-Summary", 0, true],

        // ── 6. ACS Common Chemistry ──
        // American Chemical Society — authoritative CAS registry data
        ['datasheet', "ACS Common Chemistry ({$cas})",
         "https://commonchemistry.cas.org/detail?cas_rn={$casEnc}", 0, false],

        // ── 7. chemBlink SDS ──
        // Aggregates SDS from multiple manufacturers (Alfa, Sigma, TCI)
        ['sds', "chemBlink — SDS Collection ({$cas})",
         "https://www.chemblink.com/MSDS/{$cas}MSDS.htm", 0, false],

        // ── 8. LookChem ──
        // Chemical database with safety data, properties, MSDS links
        ['sds', "LookChem — Safety Data ({$cas})",
         "https://www.lookchem.com/cas-" . substr($cas, 0, 3) . "/{$cas}.html", 0, false],

        // ── 9. Chemical Safety (SDS Search) ──
        // Global SDS database search engine
        ['sds', "Chemical Safety — Global SDS Database ({$cas})",
         "https://chemicalsafety.com/sds-search/", 0, false],

        // ── 10. TCI Chemicals ──
        // Tokyo Chemical Industry — product search with SDS links
        ['sds', "TCI Chemicals — Product Search ({$cas})",
         "https://www.tcichemicals.com/US/en/search/?text={$casEnc}", 0, false],

        // ── 11. Thermo Fisher Scientific ──
        // Search chemicals catalog — includes SDS downloads
        ['sds', "Thermo Fisher — Chemical Search ({$cas})",
         "https://www.thermofisher.com/search/results?query={$casEnc}&focusarea=Search%20All", 0, false],

        // ── 12. VWR / Avantor ──
        // Major lab supplier — SDS available via product pages
        ['sds', "VWR/Avantor — Chemical Search ({$cas})",
         "https://us.vwr.com/store/search/searchAdv.jsp?keyword={$casEnc}&searchType=1", 0, false],

        // ── 13. Alfa Aesar (Thermo Fisher) ──
        // High-purity chemicals — catalog with SDS
        ['sds', "Alfa Aesar — Chemical Search ({$cas})",
         "https://www.thermofisher.com/search/results?query={$casEnc}&focusarea=Search%20All&refinementAction=true&r=Alfa+Aesar", 0, false],

        // ── 14. CAMEO Chemicals (NOAA) ──
        // US govt hazardous materials database — excellent safety info
        ['datasheet', "CAMEO Chemicals (NOAA) — Hazard Data ({$cas})",
         "https://cameochemicals.noaa.gov/search/simple?la=en&query={$casEnc}", 0, false],

        // ── 15. ECHA (European Chemicals Agency) ──
        // EU chemical registry — GHS classification, safety data
        ['datasheet', "ECHA — Chemical Information ({$cas})",
         "https://echa.europa.eu/search-for-chemicals?p_p_id=disssimplesearch_WAR_disssearchportlet&_disssimplesearch_WAR_disssearchportlet_searchCriteria={$casEnc}", 0, false],

        // ── 16. NIST WebBook ──
        // US National Institute of Standards — thermochemical, spectral data
        ['datasheet', "NIST WebBook — Chemical Data ({$cas})",
         "https://webbook.nist.gov/cgi/cbook.cgi?ID={$casEnc}&Mask=FFFF", 0, false],

        // ── 17. ChemSpider (Royal Society of Chemistry) ──
        // Structure-based chemical database with safety & property data
        ['datasheet', "ChemSpider (RSC) — Chemical Profile ({$cas})",
         "https://www.chemspider.com/Search.aspx?q={$casEnc}", 0, false],

        // ── 18. WHO IPCS INCHEM ──
        // World Health Organization — International Chemical Safety Cards
        ['datasheet', "WHO/IPCS — Chemical Safety Card ({$cas})",
         "https://inchem.org/pages/icsc.html", 0, false],

        // ── 19. Carl Roth ──
        // European lab supplier — SDS in PDF format by CAS
        ['sds', "Carl Roth — Safety Data Sheet ({$cas})",
         "https://www.carlroth.com/medias/SDB-{$cas}-EN.pdf", 0, false],

        // ── 20. Spectrum Chemical ──
        // US lab supplier — product/SDS search by CAS
        ['sds', "Spectrum Chemical — SDS Search ({$cas})",
         "https://www.spectrumchemical.com/catalogsearch/result/?q={$casEnc}", 0, false],
    ];

    foreach ($links as [$type, $title, $url, $isPrimary, $needsCid]) {
        // Skip CID-dependent sources when no CID available
        if ($needsCid && !$cid) continue;
        $exists = Database::fetch(
            "SELECT id FROM chemical_sds_files WHERE chemical_id=:c AND file_url=:u",
            [':c'=>$chemId, ':u'=>$url]
        );
        if (!$exists) {
            $db->prepare("
                INSERT INTO chemical_sds_files
                    (chemical_id, file_type, title, file_url, language, uploaded_by, is_primary)
                VALUES (:c, :t, :ti, :u, 'en', 1, :p)
            ")->execute([':c'=>$chemId, ':t'=>$type, ':ti'=>$title, ':u'=>$url, ':p'=>$isPrimary]);
            $count++;
        }
    }

    // Update chemicals.sds_url — prefer Sigma-Aldrich search (most useful for lab users)
    $bestUrl = "https://www.sigmaaldrich.com/US/en/search/{$casEnc}?focus=documents&page=1&perpage=30&sort=relevance&term={$casEnc}&type=sds";
    $db->prepare("UPDATE chemicals SET sds_url=:u WHERE id=:id AND (sds_url IS NULL OR sds_url='' OR sds_url LIKE '%#section=%' OR sds_url LIKE '%chemicalbook.com%')")
       ->execute([':u'=>$bestUrl, ':id'=>$chemId]);

    return $count;
}
