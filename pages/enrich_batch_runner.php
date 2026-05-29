<?php
/**
 * Batch Runner — runs enrichment in auto-continuing batches
 * Usage: php enrich_batch_runner.php
 * Automatically processes all chemicals with CAS that don't have GHS data yet
 * Processes 500 at a time, then restarts
 */
set_time_limit(0);
ini_set('memory_limit', '1512M');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$batchSize = 500;
$round = 0;

while (true) {
    $round++;
    
    // Count remaining
    $remaining = (int)Database::fetch("
        SELECT COUNT(*) as c FROM chemicals c
        LEFT JOIN chemical_ghs_data g ON c.id = g.chemical_id
        WHERE c.is_active = 1
          AND c.cas_number IS NOT NULL AND c.cas_number != ''
          AND g.id IS NULL
    ")['c'];
    
    $done = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data")['c'];
    $sdsDone = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
    
    echo "\n══════ Round $round ══════\n";
    echo "Done: $done GHS records, $sdsDone SDS links\n";
    echo "Remaining: $remaining chemicals\n";
    
    if ($remaining <= 0) {
        echo "\n🎉 ALL CHEMICALS ENRICHED!\n";
        break;
    }
    
    // Get next batch
    $chemicals = Database::fetchAll("
        SELECT c.id, c.cas_number, c.name
        FROM chemicals c
        LEFT JOIN chemical_ghs_data g ON c.id = g.chemical_id
        WHERE c.is_active = 1
          AND c.cas_number IS NOT NULL AND c.cas_number != ''
          AND g.id IS NULL
        ORDER BY c.id
        LIMIT :lim
    ", [':lim' => $batchSize]);
    
    $total = count($chemicals);
    $success = 0; $noData = 0; $errors = 0; $sdsAdded = 0;
    
    foreach ($chemicals as $idx => $chem) {
        $n = $idx + 1;
        $cas = trim($chem['cas_number']);
        $chemId = (int)$chem['id'];
        
        echo "  [$n/$total] CAS:$cas ";
        
        try {
            // Get CID
            $cid = getCidFromCas($cas);
            if (!$cid) { echo "⊘\n"; $noData++; usleep(200000); continue; }
            
            // Get GHS
            $ghsRaw = getGhsFromPubChem($cid);
            if (!$ghsRaw) { echo "⊘ghs\n"; $noData++; usleep(200000); continue; }
            
            // Build + Save
            $ghs = buildGhsRecord($chemId, $cid, $ghsRaw);
            saveGhsData($ghs);
            updateChemicalsTable($chemId, $ghs);
            $cnt = addSdsLinks($chemId, $cid, $cas);
            $sdsAdded += $cnt;
            
            echo "✓\n";
            $success++;
        } catch (Exception $e) {
            echo "✗ " . substr($e->getMessage(), 0, 50) . "\n";
            $errors++;
        }
        
        usleep(250000); // 250ms between requests
    }
    
    echo "  → Success:$success  No-data:$noData  Errors:$errors  SDS:+$sdsAdded\n";
}

// Final summary
$ghsCount = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data")['c'];
$sdsCount = (int)Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
$withPics = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE hazard_pictograms IS NOT NULL AND hazard_pictograms != '' AND hazard_pictograms != '[]' AND hazard_pictograms != 'null' AND is_active = 1")['c'];
echo "\n╔══════════════════════════════════════════╗\n";
echo "║  FINAL RESULTS                            ║\n";
echo "╠══════════════════════════════════════════╣\n";
echo "║  GHS Records:  $ghsCount\n";
echo "║  SDS Links:    $sdsCount\n";
echo "║  With Pictograms: $withPics\n";
echo "╚══════════════════════════════════════════╝\n";


// ═══════ Functions ═══════

function httpGet($url, $retries = 2) {
    $ctx = stream_context_create([
        'http' => ['method'=>'GET','header'=>"User-Agent: ChemInventory/1.0\r\n",'timeout'=>15,'ignore_errors'=>true],
        'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false]
    ]);
    for ($i = 0; $i <= $retries; $i++) {
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            $status = 200;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $h, $m)) $status = (int)$m[1];
                }
            }
            if ($status === 200) return $body;
            if ($status === 404) return null;
            if ($status === 503 || $status === 429) { usleep(3000000); continue; }
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
    
    // Signal
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

function addSdsLinks($chemId, $cid, $cas) {
    $count = 0;
    $links = [
        ['datasheet', "PubChem Safety (CID:$cid)", "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=Safety-and-Hazards"],
        ['sds', "Sigma-Aldrich SDS — $cas", "https://www.sigmaaldrich.com/US/en/sds/".urlencode($cas)],
        ['sds', "Fisher Scientific MSDS — $cas", "https://www.fishersci.com/store/msds?partNumber=".urlencode($cas)."&vendorId=VN00033897&countryCode=US&language=en"],
    ];
    
    $db = Database::getInstance();
    foreach ($links as [$type, $title, $url]) {
        $exists = Database::fetch("SELECT id FROM chemical_sds_files WHERE chemical_id=:c AND file_url=:u", [':c'=>$chemId, ':u'=>$url]);
        if (!$exists) {
            $db->prepare("INSERT INTO chemical_sds_files (chemical_id,file_type,title,file_url,language,uploaded_by,is_primary) VALUES (:c,:t,:ti,:u,'en',1,:p)")
               ->execute([':c'=>$chemId,':t'=>$type,':ti'=>$title,':u'=>$url,':p'=>($type==='sds'&&strpos($title,'Sigma')!==false)?1:0]);
            $count++;
        }
    }
    
    $db->prepare("UPDATE chemicals SET sds_url=:u WHERE id=:id AND (sds_url IS NULL OR sds_url='')")
       ->execute([':u'=>$links[0][2], ':id'=>$chemId]);
    
    return $count;
}
