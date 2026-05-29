<?php
/**
 * Offline Enrichment — No PubChem API calls needed
 * ─────────────────────────────────────────────────
 * Uses data already in the database to fill in:
 *  1. Structure images (image_url) from known CIDs
 *  2. 3D model URLs (model_3d_url) from known CIDs  
 *  3. CAS-fallback images for chemicals without CID
 *  4. SDS links (20 sources) for ALL chemicals with CAS
 *
 * Safe to run anytime — even when PubChem IP is blocked
 * All URLs are deterministic (no API call needed)
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/database.php';

$db = Database::getInstance();

echo "╔══════════════════════════════════════════════════╗\n";
echo "║  OFFLINE ENRICHMENT (No API calls)              ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// ══════════════════════════════════════════════════════════
//  STEP 1: Extract CIDs from existing GHS records
// ══════════════════════════════════════════════════════════
echo "── Step 1: Extract CIDs from GHS records ──\n";

$ghsRecords = Database::fetchAll("
    SELECT g.chemical_id, g.source
    FROM chemical_ghs_data g
    WHERE g.source LIKE 'PubChem CID:%'
      AND g.source NOT LIKE '%No GHS%'
      AND g.source NOT LIKE '%No CID%'
      AND g.source NOT LIKE '%No data%'
");

$cidMap = []; // chemical_id => CID
foreach ($ghsRecords as $r) {
    if (preg_match('/PubChem CID:(\d+)/', $r['source'], $m)) {
        $cidMap[(int)$r['chemical_id']] = (int)$m[1];
    }
}
echo "   Found " . count($cidMap) . " chemicals with known CIDs\n\n";

// ══════════════════════════════════════════════════════════
//  STEP 2: Set structure images for chemicals WITH CID
// ══════════════════════════════════════════════════════════
echo "── Step 2: Set structure images (CID-based) ──\n";

$needImage = Database::fetchAll("
    SELECT id, cas_number FROM chemicals
    WHERE is_active = 1
      AND (image_url IS NULL OR image_url = '')
");

$imgSet = 0;
$m3dSet = 0;
foreach ($needImage as $chem) {
    $chemId = (int)$chem['id'];
    if (isset($cidMap[$chemId])) {
        $cid = $cidMap[$chemId];
        $db->prepare("UPDATE chemicals SET
            image_url = :img,
            model_3d_url = :m3d
            WHERE id = :id")
        ->execute([
            ':img' => "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/PNG?image_size=300x300",
            ':m3d' => "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=3D-Conformer",
            ':id'  => $chemId,
        ]);
        $imgSet++;
        $m3dSet++;
    } elseif (!empty($chem['cas_number'])) {
        // CAS-fallback image (may or may not work, but worth trying)
        $cas = trim($chem['cas_number']);
        $db->prepare("UPDATE chemicals SET
            image_url = :img
            WHERE id = :id")
        ->execute([
            ':img' => "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($cas) . "/PNG?image_size=300x300",
            ':id'  => $chemId,
        ]);
        $imgSet++;
    }
}
echo "   Set $imgSet structure images ($m3dSet with 3D model)\n\n";

// ══════════════════════════════════════════════════════════
//  STEP 3: Add SDS links for ALL chemicals with CAS
// ══════════════════════════════════════════════════════════
echo "── Step 3: Add SDS links (20 sources) ──\n";

$allChemicals = Database::fetchAll("
    SELECT id, cas_number, name FROM chemicals
    WHERE is_active = 1
      AND cas_number IS NOT NULL AND cas_number != ''
    ORDER BY id ASC
");

$totalSds = 0;
$chemsWithNewSds = 0;
$total = count($allChemicals);

foreach ($allChemicals as $idx => $chem) {
    $chemId = (int)$chem['id'];
    $cas = trim($chem['cas_number']);
    $cid = $cidMap[$chemId] ?? null;

    $cnt = addSdsLinks($chemId, $cid, $cas, $chem['name']);
    if ($cnt > 0) {
        $totalSds += $cnt;
        $chemsWithNewSds++;
    }

    // Progress every 500
    if (($idx + 1) % 500 === 0) {
        echo "   Progress: " . ($idx + 1) . "/$total  (SDS added: $totalSds)\n";
    }
}
echo "   Added $totalSds SDS links for $chemsWithNewSds chemicals\n\n";

// ══════════════════════════════════════════════════════════
//  STEP 4: Update sds_url on chemicals table
// ══════════════════════════════════════════════════════════
echo "── Step 4: Update best SDS URL ──\n";

$noSdsUrl = Database::fetchAll("
    SELECT id, cas_number FROM chemicals
    WHERE is_active = 1
      AND cas_number IS NOT NULL AND cas_number != ''
      AND (sds_url IS NULL OR sds_url = '' OR sds_url LIKE '%#section=%' OR sds_url LIKE '%misuse.ncbi%')
");
$sdsUrlFixed = 0;
foreach ($noSdsUrl as $c) {
    $casEnc = urlencode(trim($c['cas_number']));
    $bestUrl = "https://www.sigmaaldrich.com/US/en/search/{$casEnc}?focus=documents&page=1&perpage=30&sort=relevance&term={$casEnc}&type=sds";
    $db->prepare("UPDATE chemicals SET sds_url = :u WHERE id = :id")->execute([':u' => $bestUrl, ':id' => $c['id']]);
    $sdsUrlFixed++;
}
echo "   Fixed $sdsUrlFixed chemicals sds_url\n\n";

// ══════════════════════════════════════════════════════════
//  FINAL SUMMARY
// ══════════════════════════════════════════════════════════
$stats = [
    'total'     => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1")['c'],
    'withImage' => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url IS NOT NULL AND image_url != '' AND is_active=1")['c'],
    'cidImage'  => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url LIKE '%/cid/%' AND is_active=1")['c'],
    'casImage'  => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE image_url LIKE '%/name/%' AND is_active=1")['c'],
    'with3d'    => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE model_3d_url IS NOT NULL AND model_3d_url != '' AND is_active=1")['c'],
    'ghsReal'   => Database::fetch("SELECT COUNT(*) as c FROM chemical_ghs_data WHERE source LIKE 'PubChem CID:%' AND source NOT LIKE '%No GHS%' AND source NOT LIKE '%No CID%' AND source NOT LIKE '%No data%'")['c'],
    'formula'   => Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active=1")['c'],
    'sds'       => Database::fetch("SELECT COUNT(*) as c FROM chemical_sds_files")['c'],
];

echo "╔══════════════════════════════════════════════════╗\n";
echo "║  OFFLINE ENRICHMENT COMPLETE                    ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  Total Active:      {$stats['total']}\n";
echo "║  Structure Images:  {$stats['withImage']}  (CID: {$stats['cidImage']}, CAS: {$stats['casImage']})\n";
echo "║  3D Model URLs:     {$stats['with3d']}\n";
echo "║  GHS Records:       {$stats['ghsReal']}\n";
echo "║  With Formula:      {$stats['formula']}\n";
echo "║  SDS Links:         {$stats['sds']}\n";
echo "╚══════════════════════════════════════════════════╝\n";

// ══════════════════════════════════════════════════════════
//  SDS LINKS FUNCTION (same as enrich_batch_runner.php)
// ══════════════════════════════════════════════════════════
function addSdsLinks($chemId, $cid, $cas, $chemName) {
    $count = 0;
    $db = Database::getInstance();
    $casEnc = urlencode($cas);
    $casNoDash = str_replace('-', '', $cas);
    $nameEnc = urlencode($chemName);
    $nameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $chemName));
    $nameSlug = trim($nameSlug, '-');

    $links = [
        // 1. PubChem Compound Page (CID required)
        ['datasheet', "PubChem — Safety & Hazards (CID:{$cid})",
         "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}", 0, true],

        // 2. Sigma-Aldrich SDS Search
        ['sds', "Sigma-Aldrich — SDS Search ({$cas})",
         "https://www.sigmaaldrich.com/US/en/search/{$casEnc}?focus=documents&page=1&perpage=30&sort=relevance&term={$casEnc}&type=sds", 0, false],

        // 3. Fisher Scientific SDS Search
        ['sds', "Fisher Scientific — SDS Search ({$cas})",
         "https://www.fishersci.com/us/en/catalog/search/sdshome.html", 0, false],

        // 4. ChemicalBook
        ['sds', "ChemicalBook — Safety Data ({$cas})",
         "https://www.chemicalbook.com/Search_EN.aspx?keyword={$casEnc}", 1, false],

        // 5. PubChem LCSS (CID required)
        ['datasheet', "PubChem LCSS — Lab Chemical Safety (CID:{$cid})",
         "https://pubchem.ncbi.nlm.nih.gov/compound/{$cid}#section=Laboratory-Chemical-Safety-Summary", 0, true],

        // 6. ACS Common Chemistry
        ['datasheet', "ACS Common Chemistry ({$cas})",
         "https://commonchemistry.cas.org/detail?cas_rn={$casEnc}", 0, false],

        // 7. chemBlink SDS Collection
        ['sds', "chemBlink — SDS Collection ({$cas})",
         "https://www.chemblink.com/MSDS/{$casNoDash}MSDS.htm", 0, false],

        // 8. LookChem Safety Data
        ['sds', "LookChem — Safety Data ({$cas})",
         "https://www.lookchem.com/cas-{$cas}/", 0, false],

        // 9. Chemical Safety Global SDS Database
        ['sds', "Chemical Safety — Global SDS Database ({$cas})",
         "https://chemicalsafety.com/sds-search/", 0, false],

        // 10. TCI Chemicals
        ['sds', "TCI Chemicals — Product Search ({$cas})",
         "https://www.tcichemicals.com/US/en/search/?text={$casEnc}", 0, false],

        // 11. Thermo Fisher
        ['sds', "Thermo Fisher — Chemical Search ({$cas})",
         "https://www.thermofisher.com/search/browse/results?customGroup=Safety+Data+Sheets&personaId=DocSupport&query={$casEnc}", 0, false],

        // 12. VWR/Avantor
        ['sds', "VWR/Avantor — Chemical Search ({$cas})",
         "https://us.vwr.com/store/search?keyword={$casEnc}&type=sds", 0, false],

        // 13. Alfa Aesar
        ['sds', "Alfa Aesar — Chemical Search ({$cas})",
         "https://www.alfa.com/en/search/?q={$casEnc}", 0, false],

        // 14. CAMEO Chemicals (NOAA)
        ['datasheet', "CAMEO Chemicals (NOAA) — Response Guide ({$cas})",
         "https://cameochemicals.noaa.gov/search/simple?la=en&query={$casEnc}", 0, false],

        // 15. ECHA
        ['datasheet', "ECHA — European Chemicals Agency ({$cas})",
         "https://echa.europa.eu/search-for-chemicals?p_p_id=disssimplesearch_WAR_disssearchportlet&_disssimplesearch_WAR_disssearchportlet_searchCriteria={$casEnc}", 0, false],

        // 16. NIST WebBook
        ['datasheet', "NIST WebBook — Chemical Data ({$cas})",
         "https://webbook.nist.gov/cgi/cbook.cgi?ID={$casEnc}&Mask=FFFF", 0, false],

        // 17. ChemSpider (RSC)
        ['datasheet', "ChemSpider (RSC) — Chemical Profile ({$cas})",
         "https://www.chemspider.com/Search.aspx?q={$casEnc}", 0, false],

        // 18. WHO IPCS INCHEM
        ['datasheet', "WHO/IPCS — Chemical Safety Card ({$cas})",
         "https://inchem.org/pages/icsc.html", 0, false],

        // 19. Carl Roth
        ['sds', "Carl Roth — Safety Data Sheet ({$cas})",
         "https://www.carlroth.com/medias/SDB-{$cas}-EN.pdf", 0, false],

        // 20. Spectrum Chemical
        ['sds', "Spectrum Chemical — SDS Search ({$cas})",
         "https://www.spectrumchemical.com/catalogsearch/result/?q={$casEnc}", 0, false],
    ];

    foreach ($links as [$type, $title, $url, $isPrimary, $needsCid]) {
        if ($needsCid && !$cid) continue;
        $exists = Database::fetch(
            "SELECT id FROM chemical_sds_files WHERE chemical_id=:c AND file_url=:u",
            [':c' => $chemId, ':u' => $url]
        );
        if (!$exists) {
            $db->prepare("
                INSERT INTO chemical_sds_files
                    (chemical_id, file_type, title, file_url, language, uploaded_by, is_primary)
                VALUES (:c, :t, :ti, :u, 'en', 1, :p)
            ")->execute([':c' => $chemId, ':t' => $type, ':ti' => $title, ':u' => $url, ':p' => $isPrimary]);
            $count++;
        }
    }

    return $count;
}
