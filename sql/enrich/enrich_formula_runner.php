<?php
/**
 * Formula Batch Runner — fetches molecular formula & weight from PubChem
 * Targets ALL chemicals that have a CAS number but no molecular_formula
 * This is separate from GHS enrichment, running faster (only 1 API call per chemical)
 * Usage: php enrich_formula_runner.php
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/database.php';

$batchSize = 500;
$round = 0;

while (true) {
    $round++;
    
    $remaining = (int)Database::fetch("
        SELECT COUNT(*) as c FROM chemicals
        WHERE is_active = 1
          AND cas_number IS NOT NULL AND cas_number != ''
          AND (molecular_formula IS NULL OR molecular_formula = '')
    ")['c'];
    
    $done = (int)Database::fetch("
        SELECT COUNT(*) as c FROM chemicals
        WHERE is_active = 1 AND molecular_formula IS NOT NULL AND molecular_formula != ''
    ")['c'];
    
    echo "\n══════ Formula Round $round ══════\n";
    echo "With formula: $done  |  Remaining: $remaining\n";
    
    if ($remaining <= 0) {
        echo "\n🎉 ALL CHEMICALS HAVE FORMULAS!\n";
        break;
    }
    
    $chemicals = Database::fetchAll("
        SELECT c.id, c.cas_number, c.name
        FROM chemicals c
        WHERE c.is_active = 1
          AND c.cas_number IS NOT NULL AND c.cas_number != ''
          AND (c.molecular_formula IS NULL OR c.molecular_formula = '')
        ORDER BY c.id
        LIMIT :lim
    ", [':lim' => $batchSize]);
    
    $total = count($chemicals);
    $success = 0; $noData = 0; $errors = 0;
    
    foreach ($chemicals as $idx => $chem) {
        $n = $idx + 1;
        $cas = trim($chem['cas_number']);
        $chemId = (int)$chem['id'];
        
        echo "  [$n/$total] CAS:$cas ";
        
        try {
            // Get CID from CAS
            $cid = getCidFromCas($cas);
            if (!$cid) {
                echo "⊘\n"; $noData++;
                usleep(200000); continue;
            }
            
            // Get properties
            $props = getPropertiesFromPubChem($cid);
            if (!$props || empty($props['MolecularFormula'])) {
                echo "⊘prop\n"; $noData++;
                usleep(200000); continue;
            }
            
            // Update
            $sets = []; $params = [':id' => $chemId];
            
            $sets[] = 'molecular_formula = :mf';
            $params[':mf'] = $props['MolecularFormula'];
            
            if (!empty($props['MolecularWeight'])) {
                $sets[] = 'molecular_weight = :mw';
                $params[':mw'] = (float)$props['MolecularWeight'];
            }
            
            Database::getInstance()->prepare(
                "UPDATE chemicals SET " . implode(',', $sets) . " WHERE id=:id"
            )->execute($params);
            
            echo "✓ {$props['MolecularFormula']} ({$props['MolecularWeight']})\n";
            $success++;
            
        } catch (Exception $e) {
            echo "✗ " . substr($e->getMessage(), 0, 60) . "\n";
            $errors++;
        }
        
        usleep(200000); // 200ms — only 2 API calls per chemical (CID + props)
    }
    
    echo "  → Success:$success  No-data:$noData  Errors:$errors\n";
    
    // If too many no-data, remaining won't shrink — break to avoid infinite loop
    if ($success === 0 && $noData >= $total) {
        // Mark remaining no-CID chemicals with placeholder formula
        echo "\n⚠ All remaining chemicals have no PubChem CID — marking as N/A\n";
        Database::getInstance()->prepare("
            UPDATE chemicals SET molecular_formula = 'N/A'
            WHERE is_active = 1
              AND cas_number IS NOT NULL AND cas_number != ''
              AND (molecular_formula IS NULL OR molecular_formula = '')
              AND id IN (
                  SELECT id FROM (
                      SELECT c.id FROM chemicals c
                      WHERE c.is_active = 1
                        AND c.cas_number IS NOT NULL AND c.cas_number != ''
                        AND (c.molecular_formula IS NULL OR c.molecular_formula = '')
                      LIMIT :lim
                  ) tmp
              )
        ")->execute([':lim' => $batchSize]);
        continue;
    }
}

// Final summary
$withFormula = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND is_active = 1")['c'];
$withMW = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_weight IS NOT NULL AND molecular_weight > 0 AND is_active = 1")['c'];
$totalActive = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active = 1")['c'];

echo "\n╔══════════════════════════════════════════╗\n";
echo "║  FORMULA ENRICHMENT RESULTS              ║\n";
echo "╠══════════════════════════════════════════╣\n";
echo "║  Total active:    $totalActive\n";
echo "║  With Formula:    $withFormula\n";
echo "║  With MW:         $withMW\n";
echo "║  Coverage:        " . round(($withFormula / max($totalActive,1)) * 100, 1) . "%\n";
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

function getPropertiesFromPubChem($cid) {
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/property/MolecularFormula,MolecularWeight,IUPACName/JSON";
    $body = httpGet($url);
    if (!$body) return null;
    $data = json_decode($body, true);
    return $data['PropertyTable']['Properties'][0] ?? null;
}
