<?php
/**
 * Chemical Data Enrichment Script
 * Fetches GHS/Safety/SDS data from PubChem API by CAS number
 * PubChem REST API: https://pubchem.ncbi.nlm.nih.gov/docs/pug-rest
 * 
 * Usage: php enrich_chemicals.php [batch_size] [start_offset]
 *   batch_size: how many chemicals to process (default 50)
 *   start_offset: skip first N chemicals (default 0)
 * 
 * Rate limit: PubChem allows 5 requests/second. We use 400ms delay.
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/database.php';

$batchSize  = (int)($argv[1] ?? 50);
$startOffset = (int)($argv[2] ?? 0);

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  Chemical Data Enrichment — PubChem API                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "Batch: $batchSize  |  Offset: $startOffset\n\n";

// Get chemicals that need enrichment (have CAS, no GHS data yet)
$chemicals = Database::fetchAll("
    SELECT c.id, c.cas_number, c.name, c.physical_state
    FROM chemicals c
    LEFT JOIN chemical_ghs_data g ON c.id = g.chemical_id
    WHERE c.is_active = 1
      AND c.cas_number IS NOT NULL AND c.cas_number != ''
      AND g.id IS NULL
    ORDER BY c.id
    LIMIT :lim OFFSET :off
", [':lim' => $batchSize, ':off' => $startOffset]);

$total = count($chemicals);
echo "Found $total chemicals to enrich.\n\n";

$stats = ['success' => 0, 'no_data' => 0, 'error' => 0, 'sds_links' => 0];

foreach ($chemicals as $idx => $chem) {
    $n = $idx + 1;
    $cas = trim($chem['cas_number']);
    $chemId = (int)$chem['id'];
    
    echo "[$n/$total] ID:$chemId CAS:$cas — " . mb_substr($chem['name'], 0, 45) . "... ";
    
    try {
        // Step 1: Get PubChem CID from CAS
        $cid = getCidFromCas($cas);
        if (!$cid) {
            echo "❌ No CID found\n";
            $stats['no_data']++;
            usleep(300000);
            continue;
        }
        
        // Step 2: Get GHS/Safety data from PubChem
        $ghsData = getGhsData($cid);
        
        if (!$ghsData) {
            echo "⚠️ No GHS data (CID:$cid)\n";
            $stats['no_data']++;
            usleep(200000);
            continue;
        }
        
        // Step 3: Build GHS record
        $ghs = buildGhsRecord($chemId, $cid, $ghsData, null);
        
        // Step 4: Insert into chemical_ghs_data
        insertGhsData($ghs);
        
        // Step 5: Update chemicals table with GHS summary
        updateChemicalGhs($chemId, $ghs);
        
        // Step 6: Add SDS links
        $sdsCount = addSdsLinks($chemId, $cid, $cas);
        $stats['sds_links'] += $sdsCount;
        
        echo "✅ GHS saved" . ($sdsCount ? " +{$sdsCount} SDS" : "") . "\n";
        $stats['success']++;
        
    } catch (Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
        $stats['error']++;
    }
    
    // Rate limiting: 250ms between requests
    usleep(250000);
}

echo "\n╔══════════════════════════════════════╗\n";
echo "║  RESULTS                             ║\n";
echo "╠══════════════════════════════════════╣\n";
echo "║  ✅ Success:   {$stats['success']}\n";
echo "║  ⚠️  No data:   {$stats['no_data']}\n";
echo "║  ❌ Errors:    {$stats['error']}\n";
echo "║  📄 SDS links: {$stats['sds_links']}\n";
echo "╚══════════════════════════════════════╝\n";

// Final stats
$ghsCount = Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data")['c'];
$sdsCount = Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
echo "\nDB totals: GHS records=$ghsCount, SDS files=$sdsCount\n";


// ═══════════════════════════════════════════════════════
// API FUNCTIONS
// ═══════════════════════════════════════════════════════

function httpGet($url, $retries = 2) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ChemInventory/1.0 (educational)\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    for ($i = 0; $i <= $retries; $i++) {
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            // Check for 404/error
            $status = 200;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $h, $m)) {
                        $status = (int)$m[1];
                    }
                }
            }
            if ($status === 200) return $body;
            if ($status === 404) return null;
            // Rate limited or server error — retry
            if ($status === 503 || $status === 429) {
                usleep(2000000); // wait 2s
                continue;
            }
            return null;
        }
        if ($i < $retries) usleep(1000000);
    }
    return null;
}

function getCidFromCas($cas) {
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/cids/JSON";
    $body = httpGet($url);
    if (!$body) return null;
    $data = json_decode($body, true);
    return $data['IdentifierList']['CID'][0] ?? null;
}

function getGhsData($cid) {
    // Get GHS Classification data from PubChem PUG View
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/{$cid}/JSON?heading=GHS+Classification";
    $body = httpGet($url);
    if (!$body) return null;
    return json_decode($body, true);
}

function getPropertyData($cid) {
    // Get basic safety properties
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/property/MolecularFormula,MolecularWeight,IUPACName/JSON";
    $body = httpGet($url);
    if (!$body) return null;
    return json_decode($body, true);
}

function buildGhsRecord($chemId, $cid, $ghsRaw, $propRaw) {
    $ghs = [
        'chemical_id' => $chemId,
        'ghs_pictograms' => [],
        'signal_word' => 'None',
        'h_statements' => [],
        'h_statements_text' => '',
        'p_statements' => [],
        'p_statements_text' => '',
        'safety_summary' => '',
        'source' => "PubChem CID:$cid",
    ];
    
    if ($ghsRaw) {
        // Parse GHS data from PUG View structure
        $sections = extractSections($ghsRaw);
        
        // Pictograms
        $ghs['ghs_pictograms'] = extractPictograms($sections);
        
        // Signal Word
        $sw = extractTextByHeading($sections, 'Signal');
        if ($sw) {
            if (stripos($sw, 'Danger') !== false) $ghs['signal_word'] = 'Danger';
            elseif (stripos($sw, 'Warning') !== false) $ghs['signal_word'] = 'Warning';
        }
        
        // Hazard Statements
        $hTexts = extractStatements($sections, 'Hazard Statement');
        if ($hTexts) {
            $ghs['h_statements_text'] = implode("\n", $hTexts);
            // Extract H-codes
            foreach ($hTexts as $ht) {
                if (preg_match('/H\d{3}[A-Za-z+]*/', $ht, $m)) {
                    $ghs['h_statements'][] = $m[0];
                }
            }
        }
        
        // Precautionary Statements
        $pTexts = extractStatements($sections, 'Precautionary Statement');
        if ($pTexts) {
            $ghs['p_statements_text'] = implode("\n", $pTexts);
            foreach ($pTexts as $pt) {
                if (preg_match('/P\d{3}[+P\d]*/', $pt, $m)) {
                    $ghs['p_statements'][] = $m[0];
                }
            }
        }
    }
    
    // Build safety summary
    $summaryParts = [];
    if (!empty($ghs['ghs_pictograms'])) {
        $picNames = array_map(function($p) {
            $names = [
                'GHS01'=>'Explosive','GHS02'=>'Flammable','GHS03'=>'Oxidizer',
                'GHS04'=>'Compressed Gas','GHS05'=>'Corrosive','GHS06'=>'Acute Toxicity',
                'GHS07'=>'Irritant/Harmful','GHS08'=>'Health Hazard','GHS09'=>'Environmental Hazard'
            ];
            return $names[$p] ?? $p;
        }, $ghs['ghs_pictograms']);
        $summaryParts[] = "Hazards: " . implode(', ', $picNames) . ".";
    }
    if ($ghs['signal_word'] !== 'None') {
        $summaryParts[] = "Signal: {$ghs['signal_word']}.";
    }
    if ($ghs['h_statements_text']) {
        // Take first 2 H-statements for summary
        $hLines = array_slice(explode("\n", $ghs['h_statements_text']), 0, 3);
        $summaryParts[] = implode('; ', $hLines) . ".";
    }
    $ghs['safety_summary'] = implode(' ', $summaryParts);
    
    // Generate first aid measures based on pictograms
    $ghs = addFirstAidFromPictograms($ghs);
    
    // Generate storage instructions
    $ghs = addStorageInstructions($ghs);
    
    return $ghs;
}

function extractSections($data, $depth = 0) {
    $results = [];
    if (!is_array($data)) return $results;
    
    // Handle Record → Section
    if (isset($data['Record']['Section'])) {
        return extractSections($data['Record']['Section'], $depth);
    }
    
    foreach ($data as $item) {
        if (!is_array($item)) continue;
        
        $heading = $item['TOCHeading'] ?? '';
        
        // Collect information from this section
        if (isset($item['Information'])) {
            foreach ($item['Information'] as $info) {
                $name = $info['Name'] ?? $heading;
                $value = '';
                if (isset($info['Value']['StringWithMarkup'])) {
                    foreach ($info['Value']['StringWithMarkup'] as $swm) {
                        $value .= ($swm['String'] ?? '') . "\n";
                    }
                }
                if (isset($info['Value']['Number'])) {
                    $value = implode(', ', $info['Value']['Number']);
                }
                // Check for pictogram URLs
                $markup = [];
                if (isset($info['Value']['StringWithMarkup'])) {
                    foreach ($info['Value']['StringWithMarkup'] as $swm) {
                        if (isset($swm['Markup'])) {
                            $markup = array_merge($markup, $swm['Markup']);
                        }
                    }
                }
                $results[] = [
                    'heading' => $heading,
                    'name' => $name,
                    'value' => trim($value),
                    'markup' => $markup,
                ];
            }
        }
        
        // Recurse into sub-sections
        if (isset($item['Section'])) {
            $sub = extractSections($item['Section'], $depth + 1);
            $results = array_merge($results, $sub);
        }
    }
    
    return $results;
}

function extractPictograms($sections) {
    $codes = [];
    $ghsMap = [
        'Explod' => 'GHS01', 'Flame Over' => 'GHS03', 'Flame' => 'GHS02',
        'Gas Cylinder' => 'GHS04', 'Corrosion' => 'GHS05', 'Skull' => 'GHS06',
        'Exclamation' => 'GHS07', 'Health Hazard' => 'GHS08', 'Environment' => 'GHS09',
    ];
    
    foreach ($sections as $s) {
        if (stripos($s['heading'],'Pictogram') !== false || stripos($s['name'],'Pictogram') !== false) {
            $val = $s['value'];
            // Check markup for GHS image URLs
            if (!empty($s['markup'])) {
                foreach ($s['markup'] as $mk) {
                    $url = $mk['URL'] ?? $mk['Extra'] ?? '';
                    // URL like: .../GHS07.png
                    if (preg_match('/GHS0[1-9]/', $url, $m)) {
                        $codes[] = $m[0];
                    }
                }
            }
            // Check value text for pictogram names
            foreach ($ghsMap as $keyword => $code) {
                if (stripos($val, $keyword) !== false) {
                    $codes[] = $code;
                }
            }
        }
    }
    
    return array_values(array_unique($codes));
}

function extractTextByHeading($sections, $keyword) {
    foreach ($sections as $s) {
        if (stripos($s['heading'], $keyword) !== false || stripos($s['name'], $keyword) !== false) {
            return $s['value'];
        }
    }
    return '';
}

function extractStatements($sections, $keyword) {
    $texts = [];
    foreach ($sections as $s) {
        if (stripos($s['heading'], $keyword) !== false || stripos($s['name'], $keyword) !== false) {
            $lines = array_filter(explode("\n", $s['value']));
            $texts = array_merge($texts, $lines);
        }
    }
    return array_unique($texts);
}

function addFirstAidFromPictograms($ghs) {
    $pics = $ghs['ghs_pictograms'];
    
    // Inhalation
    if (array_intersect($pics, ['GHS06','GHS07','GHS08'])) {
        $ghs['first_aid_inhalation'] = 'Move to fresh air. If breathing is difficult, give oxygen. If not breathing, give artificial respiration. Seek medical attention immediately.';
    }
    
    // Skin
    if (array_intersect($pics, ['GHS05','GHS06','GHS07'])) {
        $ghs['first_aid_skin'] = 'Remove contaminated clothing. Rinse skin with plenty of water for at least 15 minutes. Seek medical attention if irritation persists.';
    }
    
    // Eye
    if (array_intersect($pics, ['GHS05','GHS07'])) {
        $ghs['first_aid_eye'] = 'Rinse cautiously with water for several minutes. Remove contact lenses if present. Continue rinsing for at least 15 minutes. Seek medical attention.';
    }
    
    // Ingestion
    if (array_intersect($pics, ['GHS05','GHS06','GHS07','GHS08'])) {
        $ghs['first_aid_ingestion'] = 'Do NOT induce vomiting. Rinse mouth with water. Seek medical attention immediately. If conscious, give small amounts of water to drink.';
    }
    
    return $ghs;
}

function addStorageInstructions($ghs) {
    $pics = $ghs['ghs_pictograms'];
    $parts = [];
    
    if (in_array('GHS01', $pics)) $parts[] = 'Keep away from heat, sparks, open flames, hot surfaces. Store separately from other materials.';
    if (in_array('GHS02', $pics)) $parts[] = 'Keep away from heat, sparks, open flames. Store in a cool, well-ventilated area. Keep container tightly closed.';
    if (in_array('GHS03', $pics)) $parts[] = 'Keep away from combustible materials. Store in a cool, dry area.';
    if (in_array('GHS04', $pics)) $parts[] = 'Protect from sunlight. Store in a well-ventilated area.';
    if (in_array('GHS05', $pics)) $parts[] = 'Store in corrosion-resistant container with resistant inner liner. Store locked up.';
    if (in_array('GHS06', $pics)) $parts[] = 'Store locked up. Handle with appropriate personal protective equipment.';
    if (in_array('GHS08', $pics)) $parts[] = 'Store according to local regulations. Avoid exposure — obtain special instructions before use.';
    if (in_array('GHS09', $pics)) $parts[] = 'Avoid release to the environment. Store away from drains and water sources.';
    
    if ($parts) {
        $ghs['storage_instructions'] = implode(' ', $parts);
    }
    
    // Handling precautions
    $handling = [];
    if (array_intersect($pics, ['GHS05','GHS06','GHS07','GHS08'])) {
        $handling[] = 'Wear appropriate PPE (gloves, goggles, lab coat).';
    }
    if (array_intersect($pics, ['GHS02','GHS04'])) {
        $handling[] = 'Use in well-ventilated area. Keep away from ignition sources.';
    }
    if (array_intersect($pics, ['GHS06','GHS08'])) {
        $handling[] = 'Avoid breathing dust/fumes/gas/mist/vapors/spray.';
    }
    if (array_intersect($pics, ['GHS05'])) {
        $handling[] = 'Do not breathe dust or mist. Avoid contact with skin, eyes, and clothing.';
    }
    if ($handling) {
        $ghs['handling_precautions'] = implode(' ', $handling);
    }
    
    // Disposal
    $ghs['disposal_instructions'] = 'Dispose of contents/container in accordance with local/regional/national regulations. Do not pour into drains or waterways.';
    
    return $ghs;
}

function insertGhsData($ghs) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        INSERT INTO chemical_ghs_data 
            (chemical_id, ghs_pictograms, signal_word, h_statements, h_statements_text,
             p_statements, p_statements_text, safety_summary, handling_precautions,
             storage_instructions, disposal_instructions, first_aid_inhalation,
             first_aid_skin, first_aid_eye, first_aid_ingestion, source)
        VALUES
            (:cid, :pics, :sw, :hs, :ht, :ps, :pt, :ss, :hp, :si, :di, :fi, :fs, :fe, :fg, :src)
        ON DUPLICATE KEY UPDATE
            ghs_pictograms = VALUES(ghs_pictograms),
            signal_word = VALUES(signal_word),
            h_statements = VALUES(h_statements),
            h_statements_text = VALUES(h_statements_text),
            p_statements = VALUES(p_statements),
            p_statements_text = VALUES(p_statements_text),
            safety_summary = VALUES(safety_summary),
            handling_precautions = VALUES(handling_precautions),
            storage_instructions = VALUES(storage_instructions),
            disposal_instructions = VALUES(disposal_instructions),
            first_aid_inhalation = VALUES(first_aid_inhalation),
            first_aid_skin = VALUES(first_aid_skin),
            first_aid_eye = VALUES(first_aid_eye),
            first_aid_ingestion = VALUES(first_aid_ingestion),
            source = VALUES(source),
            updated_at = NOW()
    ");
    
    $stmt->execute([
        ':cid'  => $ghs['chemical_id'],
        ':pics' => json_encode($ghs['ghs_pictograms']),
        ':sw'   => $ghs['signal_word'],
        ':hs'   => json_encode($ghs['h_statements']),
        ':ht'   => $ghs['h_statements_text'],
        ':ps'   => json_encode($ghs['p_statements']),
        ':pt'   => $ghs['p_statements_text'],
        ':ss'   => $ghs['safety_summary'],
        ':hp'   => $ghs['handling_precautions'] ?? null,
        ':si'   => $ghs['storage_instructions'] ?? null,
        ':di'   => $ghs['disposal_instructions'] ?? null,
        ':fi'   => $ghs['first_aid_inhalation'] ?? null,
        ':fs'   => $ghs['first_aid_skin'] ?? null,
        ':fe'   => $ghs['first_aid_eye'] ?? null,
        ':fg'   => $ghs['first_aid_ingestion'] ?? null,
        ':src'  => $ghs['source'],
    ]);
}

function updateChemicalGhs($chemId, $ghs) {
    $picMap = [
        'GHS01'=>'explosive','GHS02'=>'flammable','GHS03'=>'oxidizer',
        'GHS04'=>'compressed_gas','GHS05'=>'corrosive','GHS06'=>'toxic',
        'GHS07'=>'irritant','GHS08'=>'health_hazard','GHS09'=>'environmental'
    ];
    $legacyPics = array_map(function($p) use ($picMap) {
        return $picMap[$p] ?? $p;
    }, $ghs['ghs_pictograms']);
    
    $signalMap = ['Danger'=>'Danger','Warning'=>'Warning','None'=>'No signal word'];
    
    $updates = [];
    $params = [':id' => $chemId];
    
    if (!empty($ghs['ghs_pictograms'])) {
        $updates[] = 'hazard_pictograms = :hp';
        $params[':hp'] = json_encode($legacyPics);
    }
    if ($ghs['signal_word'] !== 'None') {
        $updates[] = 'signal_word = :sw';
        $params[':sw'] = $signalMap[$ghs['signal_word']] ?? 'No signal word';
    }
    if ($ghs['h_statements_text']) {
        $updates[] = 'hazard_statements = :hs';
        $params[':hs'] = json_encode(explode("\n", $ghs['h_statements_text']));
    }
    if ($ghs['p_statements_text']) {
        $updates[] = 'precautionary_statements = :ps';
        $params[':ps'] = json_encode(explode("\n", $ghs['p_statements_text']));
    }
    if ($ghs['storage_instructions'] ?? null) {
        $updates[] = 'storage_requirements = :sr';
        $params[':sr'] = $ghs['storage_instructions'];
    }
    if ($ghs['handling_precautions'] ?? null) {
        $updates[] = 'handling_procedures = :hpr';
        $params[':hpr'] = $ghs['handling_precautions'];
    }
    if ($ghs['first_aid_inhalation'] ?? null) {
        $updates[] = 'first_aid_measures = :fam';
        $params[':fam'] = "Inhalation: " . $ghs['first_aid_inhalation'] 
            . "\nSkin: " . ($ghs['first_aid_skin'] ?? '')
            . "\nEye: " . ($ghs['first_aid_eye'] ?? '')
            . "\nIngestion: " . ($ghs['first_aid_ingestion'] ?? '');
    }
    if ($ghs['disposal_instructions'] ?? null) {
        $updates[] = 'disposal_methods = :dm';
        $params[':dm'] = $ghs['disposal_instructions'];
    }
    
    if ($updates) {
        $sql = "UPDATE chemicals SET " . implode(', ', $updates) . " WHERE id = :id";
        $db = Database::getInstance();
        $db->prepare($sql)->execute($params);
    }
}

function addSdsLinks($chemId, $cid, $cas) {
    $count = 0;
    
    // PubChem SDS link
    $pubchemUrl = "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=Safety-and-Hazards";
    insertSdsLink($chemId, 'datasheet', "PubChem Safety Data (CID:{$cid})", $pubchemUrl, 'en');
    $count++;
    
    // Common SDS sources by CAS
    // Sigma-Aldrich / MilliporeSigma
    $sigmaUrl = "https://www.sigmaaldrich.com/US/en/sds/" . urlencode($cas);
    insertSdsLink($chemId, 'sds', "Sigma-Aldrich SDS — CAS {$cas}", $sigmaUrl, 'en');
    $count++;
    
    // Fisher Scientific
    $fisherUrl = "https://www.fishersci.com/store/msds?partNumber=" . urlencode($cas) . "&vendorId=VN00033897&countryCode=US&language=en";
    insertSdsLink($chemId, 'sds', "Fisher Scientific MSDS — CAS {$cas}", $fisherUrl, 'en');
    $count++;
    
    // Update chemicals table SDS URL  
    $db = Database::getInstance();
    $db->prepare("UPDATE chemicals SET sds_url = :url WHERE id = :id AND (sds_url IS NULL OR sds_url = '')")
       ->execute([':url' => $pubchemUrl, ':id' => $chemId]);
    
    return $count;
}

function insertSdsLink($chemId, $type, $title, $url, $lang) {
    // Check if already exists
    $existing = Database::fetch(
        "SELECT id FROM chemical_sds_files WHERE chemical_id = :cid AND file_url = :url",
        [':cid' => $chemId, ':url' => $url]
    );
    if ($existing) return;
    
    $db = Database::getInstance();
    $db->prepare("
        INSERT INTO chemical_sds_files (chemical_id, file_type, title, file_url, language, uploaded_by, is_primary)
        VALUES (:cid, :type, :title, :url, :lang, 1, :primary)
    ")->execute([
        ':cid' => $chemId,
        ':type' => $type,
        ':title' => $title,
        ':url' => $url,
        ':lang' => $lang,
        ':primary' => ($type === 'sds' && strpos($title, 'Sigma') !== false) ? 1 : 0,
    ]);
}
