<?php
require_once __DIR__ . '/../includes/database.php';

// 1) Test PubChem API accessibility
echo "=== PubChem API Test ===\n";
$url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/64-17-5/cids/JSON";
$ctx = stream_context_create(['http'=>['timeout'=>10,'follow_location'=>false,'header'=>"User-Agent: ChemInventory/1.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
$body = @file_get_contents($url, false, $ctx);
$blocked = false;
if ($body && (strpos($body, 'misuse.ncbi') !== false || strpos($body, 'Access Denied') !== false)) {
    echo "STATUS: ❌ BLOCKED\n\n";
    $blocked = true;
} elseif ($body) {
    $d = json_decode($body, true);
    $cid = $d['IdentifierList']['CID'][0] ?? null;
    echo "STATUS: ✅ OK (Ethanol CID=$cid)\n\n";
} else {
    echo "STATUS: ⚠️ No response (timeout?)\n\n";
}

// 2) Current stats
$total = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1")['c'];
$withCas = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE cas_number IS NOT NULL AND cas_number != '' AND is_active=1")['c'];
$withFormula = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active=1")['c'];
$ghsReal = Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'];
$ghsNoData = Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE '%No CID%' OR source LIKE '%No data%' OR source LIKE '%No GHS%'")['c'];
$ghsTotal = Database::fetch("SELECT COUNT(DISTINCT chemical_id) as c FROM chemical_ghs_data")['c'];
$noGhs = $total - $ghsTotal;
$sds = Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'];
$withImg = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url IS NOT NULL AND image_url != '' AND is_active=1")['c'];
$with3d = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE model_3d_url IS NOT NULL AND model_3d_url != '' AND is_active=1")['c'];

echo "=== Current Stats ===\n";
echo "Total Active:      $total\n";
echo "With CAS:          $withCas\n";
echo "With Formula:      $withFormula  (need: " . ($withCas - $withFormula) . ")\n";
echo "GHS Real:          $ghsReal\n";
echo "GHS No-data:       $ghsNoData\n";
echo "No GHS record:     $noGhs\n";
echo "SDS Links:         $sds\n";
echo "With Image:        $withImg\n";
echo "With 3D:           $with3d\n\n";

// 3) Breakdown: What needs processing?
// Chemicals with CAS but no real GHS
$needGhs = Database::fetch("
    SELECT COUNT(*) as c FROM chemicals c
    WHERE c.is_active = 1 AND c.cas_number IS NOT NULL AND c.cas_number != ''
    AND NOT EXISTS (
        SELECT 1 FROM chemical_ghs_data g WHERE g.chemical_id = c.id
        AND g.source LIKE 'PubChem CID:%'
        AND g.source NOT LIKE '%No GHS%' AND g.source NOT LIKE '%No CID%' AND g.source NOT LIKE '%No data%'
    )
")['c'];
// Chemicals with CAS but no formula
$needFormula = Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND cas_number IS NOT NULL AND cas_number != '' AND (molecular_formula IS NULL OR molecular_formula = '' OR molecular_formula = 'N/A')")['c'];

// False "No CID" from blocked session (within 48h)
$falseNoCid = Database::fetch("
    SELECT COUNT(*) as c FROM chemical_ghs_data 
    WHERE source LIKE 'PubChem: No CID for CAS %'
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
")['c'];

echo "=== Needs Processing ===\n";
echo "Need GHS:          $needGhs (with CAS, no real GHS)\n";
echo "Need Formula:      $needFormula (with CAS, no formula)\n";
echo "False No-CID:      $falseNoCid (from blocked sessions, last 48h)\n";

// 4) Show what CIDs we already know
$knownCids = Database::fetch("
    SELECT COUNT(*) as c FROM chemical_ghs_data 
    WHERE source LIKE 'PubChem CID:%' 
    AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'
")['c'];
echo "Known CIDs:        $knownCids\n";
