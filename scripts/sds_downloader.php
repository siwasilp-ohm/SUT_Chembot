<?php
/**
 * SDS Bulk Downloader CLI  v2.0
 * Auto-downloads Safety Data Sheet PDFs from multiple sources.
 *
 * Sources tried per chemical (in order):
 *   S1  PubChem SDS Aggregator   — aggregated links from 10+ providers
 *   S2  ChemSRC MSDS Library     — scrapes PDF links (HTML parser)
 *   S3  Sigma-Aldrich API        — product search + HTML→PDF fallback
 *   S4  Fisher Scientific        — SDS search + HTML→PDF fallback
 *   S5  LookChem SDS Library     — aggregator scrape
 *
 * Options:
 *   --mode=missing      Only chemicals with 0 SDS files (default)
 *   --mode=all          All chemicals (fill up to max-files limit)
 *   --mode=cas=XXXXX    Single chemical by CAS number or name keyword
 *   --limit=N           Stop after N chemicals
 *   --delay=2.0         Seconds between chemicals (default 2.0)
 *   --max-files=3       Max PDFs per chemical 1-5 (default 3)
 *   --dry-run           Simulate — no disk writes, no DB inserts
 *   --stats             Show DB statistics and exit
 *   --log=path          Custom log file path
 */

declare(strict_types=1);
@set_time_limit(0);
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

define('SCRIPT_ROOT', dirname(__DIR__));
define('BROWSER_UA',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
define('SDS_SOURCES', [
    'chemblink'   => 'ChemBlink  (Alfa-Aesar / Sigma-Aldrich / TCI)',
    'ddg_cas'     => 'Web Search — CAS number  (DuckDuckGo)',
    'ddg_name'    => 'Web Search — Chemical name  (DuckDuckGo)',
    'pubchem'     => 'PubChem SDS Aggregator',
    'chemblink_x' => 'ChemBlink Extra Suppliers',
]);

require_once SCRIPT_ROOT . '/includes/database.php';

// ── ANSI ──────────────────────────────────────────────────────────────────────
const R  = "\033[0m";
const B  = "\033[1m";
const DM = "\033[2m";
const CY = "\033[96m";
const GN = "\033[92m";
const YL = "\033[93m";
const RD = "\033[91m";
const MG = "\033[95m";
const BL = "\033[94m";
const WH = "\033[97m";
const TL = "\033[38;5;51m";  // teal

function c(string $col, string $txt): string { return $col . $txt . R; }
function out(string $ln = ''): void           { echo $ln . PHP_EOL; }
function bar62(): void                        { out(MG . str_repeat('─', 66) . R); }

function progressBar(int $done, int $total, int $w = 30): string {
    $pct  = $total > 0 ? $done / $total : 0;
    $fill = (int)round($pct * $w);
    return sprintf('%s[%s%s]%s %3d%%', CY, str_repeat('#', $fill), str_repeat('.', $w - $fill), R, (int)round($pct * 100));
}

function padLine(string $text, int $width = 52): string {
    $visible = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    return $text . str_repeat(' ', max(0, $width - $visible));
}

// ── Arg parser ────────────────────────────────────────────────────────────────
function parseArgs(array $argv): array {
    $opts = [
        'mode'       => 'missing',
        'limit'      => 0,
        'delay'      => 2.0,
        'max_files'  => 3,
        'dry_run'    => false,
        'stats'      => false,
        'log'        => '',
        'cas_target' => '',
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') { $opts['dry_run'] = true; continue; }
        if ($arg === '--stats')   { $opts['stats']   = true; continue; }
        if (!preg_match('/^--([\w-]+)=(.*)$/', $arg, $m)) continue;
        $k = str_replace('-', '_', $m[1]);
        $v = $m[2];
        switch ($k) {
            case 'mode':
                if (str_starts_with($v, 'cas=')) { $opts['mode'] = 'cas'; $opts['cas_target'] = substr($v, 4); }
                else                              { $opts['mode'] = $v; }
                break;
            case 'limit':     $opts['limit']     = (int)$v;                 break;
            case 'delay':     $opts['delay']     = (float)$v;               break;
            case 'max_files': $opts['max_files'] = max(1, min(5, (int)$v)); break;
            case 'log':       $opts['log']       = $v;                      break;
        }
    }
    return $opts;
}

// ── DB helpers ────────────────────────────────────────────────────────────────
function getChemicalsToProcess(array $opts): array {
    $cols = 'id, name, cas_number, iupac_name, sds_url';
    if ($opts['mode'] === 'cas') {
        $key  = $opts['cas_target'];
        $rows = Database::fetchAll("SELECT $cols FROM chemicals WHERE is_active=1 AND cas_number=:k LIMIT 1", [':k' => $key]);
        if (!$rows) {
            $rows = Database::fetchAll("SELECT $cols FROM chemicals WHERE is_active=1 AND (name LIKE :n OR iupac_name LIKE :n) LIMIT 10", [':n' => "%$key%"]);
        }
    } elseif ($opts['mode'] === 'missing') {
        $rows = Database::fetchAll("SELECT $cols FROM chemicals c WHERE is_active=1 AND cas_number IS NOT NULL AND cas_number!='' AND NOT EXISTS (SELECT 1 FROM chemical_sds_files WHERE chemical_id=c.id) ORDER BY name");
    } else {
        $rows = Database::fetchAll("SELECT $cols FROM chemicals WHERE is_active=1 AND cas_number IS NOT NULL AND cas_number!='' ORDER BY name");
    }
    if ($opts['limit'] > 0) $rows = array_slice($rows ?: [], 0, $opts['limit']);
    return $rows ?: [];
}

function getAdminUserId(): int {
    $row = Database::fetch("SELECT id FROM users WHERE role_id=1 AND is_active=1 ORDER BY id LIMIT 1");
    return $row ? (int)$row['id'] : 1;
}

function existingSdsCount(int $chemId): int {
    $row = Database::fetch("SELECT COUNT(*) AS c FROM chemical_sds_files WHERE chemical_id=:id", [':id' => $chemId]);
    return $row ? (int)$row['c'] : 0;
}

function saveSdsRecord(int $chemId, array $info, bool $isPrimary, int $userId): ?int {
    try {
        return Database::insert('chemical_sds_files', [
            'chemical_id' => $chemId,
            'file_type'   => 'sds',
            'title'       => $info['title'],
            'description' => 'Auto-downloaded from ' . $info['source'],
            'file_path'   => $info['web_path'],
            'file_size'   => $info['file_size'],
            'mime_type'   => 'application/pdf',
            'language'    => 'en',
            'is_primary'  => $isPrimary ? 1 : 0,
            'uploaded_by' => $userId,
        ]);
    } catch (Throwable) {
        return null;
    }
}

// ── HTTP ──────────────────────────────────────────────────────────────────────
function httpGet(string $url, array $hdrs = [], int $timeout = 22): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_USERAGENT      => BROWSER_UA,
        CURLOPT_HTTPHEADER     => array_merge(['Accept-Language: en-US,en;q=0.9'], $hdrs),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $body && strlen($body) > 20) ? $body : null;
}

function toAbsUrl(string $href, string $base): string {
    if (str_starts_with($href, 'http'))  return $href;
    if (str_starts_with($href, '//'))    return 'https:' . $href;
    $p      = parse_url($base);
    $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    if (str_starts_with($href, '/'))     return $origin . $href;
    $dir    = rtrim(dirname($p['path'] ?? '/'), '/');
    return $origin . $dir . '/' . ltrim($href, '/');
}

function extractPdfLinksFromHtml(string $html, string $baseUrl): array {
    $links = [];
    // Direct .pdf href/src
    preg_match_all('/(?:href|src|action)=["\']([^"\'<>\s]*\.pdf(?:\?[^"\'<>\s]*)?)["\']/', $html, $m);
    foreach ($m[1] as $h) {
        $abs = toAbsUrl($h, $baseUrl);
        if (str_starts_with($abs, 'http')) $links[] = $abs;
    }
    // JS redirect to PDF
    preg_match_all('/(?:window\.open|location\.href)\s*[=(]\s*["\']([^"\']*\.pdf[^"\']*)["\']/', $html, $m2);
    foreach ($m2[1] as $h) {
        $abs = toAbsUrl($h, $baseUrl);
        if (str_starts_with($abs, 'http')) $links[] = $abs;
    }
    // Meta refresh
    if (preg_match('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\'][^;]+;\s*url=([^"\'>\s]+)/i', $html, $m3)) {
        $abs = toAbsUrl(trim($m3[1], '"\''), $baseUrl);
        if (str_starts_with($abs, 'http')) $links[] = $abs;
    }
    return array_values(array_unique($links));
}

// ── Download to temp file, verify PDF, move to destination ───────────────────
function downloadVerifiedPdf(string $url, string $destDir, string $filename, string $referer = ''): ?array {
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    $tmpPath  = tempnam(sys_get_temp_dir(), 'sds_');
    $fp       = @fopen($tmpPath, 'wb');
    if (!$fp) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => BROWSER_UA,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/pdf,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . ($referer ?: 'https://www.google.com/'),
        ],
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    fclose($fp);

    if ($httpCode !== 200 || !file_exists($tmpPath)) { @unlink($tmpPath); return null; }

    $size  = filesize($tmpPath);
    if ($size < 512) { @unlink($tmpPath); return null; }

    $fh    = fopen($tmpPath, 'rb');
    $magic = fread($fh, 5);
    $chunk = ($size < 60000) ? fread($fh, $size - 5) : fread($fh, 60000);
    fclose($fh);

    // ── Case 1: direct PDF ────────────────────────────────────────────────────
    if ($magic === '%PDF-') {
        $dest = $destDir . DIRECTORY_SEPARATOR . $filename;
        if (@rename($tmpPath, $dest)) return ['path' => $dest, 'size' => $size];
        @unlink($tmpPath);
        return null;
    }

    // ── Case 2: HTML with embedded PDF link ───────────────────────────────────
    $htmlHead = $magic . $chunk;
    if (str_contains($htmlHead, '<html') || str_contains($htmlHead, '<!DOCTYPE') || str_contains($htmlHead, '<HTML')) {
        @unlink($tmpPath);
        $pdfLinks = extractPdfLinksFromHtml($htmlHead, $finalUrl);
        foreach (array_slice($pdfLinks, 0, 4) as $pdfUrl) {
            $result = downloadVerifiedPdfDirect($pdfUrl, $destDir, $filename, $finalUrl);
            if ($result) return $result;
        }
    }

    @unlink($tmpPath);
    return null;
}

// Direct download without HTML fallback (used for links found inside HTML)
function downloadVerifiedPdfDirect(string $url, string $destDir, string $filename, string $referer = ''): ?array {
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;
    $fp = @fopen($destPath, 'wb');
    if (!$fp) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => BROWSER_UA,
        CURLOPT_HTTPHEADER     => ['Accept: application/pdf,*/*', 'Referer: ' . $referer],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($code !== 200 || !file_exists($destPath)) { @unlink($destPath); return null; }
    $size = filesize($destPath);
    if ($size < 512) { @unlink($destPath); return null; }
    $fh  = fopen($destPath, 'rb'); $m = fread($fh, 5); fclose($fh);
    if ($m !== '%PDF-') { @unlink($destPath); return null; }
    return ['path' => $destPath, 'size' => $size];
}

// ── Source S1: PubChem ────────────────────────────────────────────────────────
function pubchemGetCid(string $cas, string $name): ?int {
    foreach (array_filter([$cas, $name]) as $q) {
        $body = httpGet('https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/' . urlencode($q) . '/cids/JSON');
        if ($body) {
            $cid = json_decode($body, true)['IdentifierList']['CID'][0] ?? null;
            if ($cid) return (int)$cid;
        }
        usleep(300_000);
    }
    return null;
}

function pubchemGetSdsLinks(int $cid): array {
    $body = httpGet("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/$cid/JSON?heading=Safety+Data+Sheet", [], 25);
    if (!$body) return [];
    $json = json_decode($body, true);
    $sec  = findSdsSection($json['Record']['Section'] ?? []);
    $out  = [];
    foreach ($sec['Information'] ?? [] as $info) {
        $url = $info['URL'] ?? '';
        if (!$url || !str_starts_with($url, 'http')) continue;
        $out[] = ['url' => $url, 'source' => $info['Name'] ?? 'PubChem', 'title' => ($info['Name'] ?? 'SDS') . ' Safety Data Sheet'];
    }
    return $out;
}

function findSdsSection(array $secs): array {
    foreach ($secs as $s) {
        if (str_contains(strtolower($s['TOCHeading'] ?? ''), 'safety data sheet')) return $s;
        if (!empty($s['Section'])) { $r = findSdsSection($s['Section']); if ($r) return $r; }
    }
    return [];
}

// ── Source S1: ChemBlink MSDS page (Alfa-Aesar / Sigma-Aldrich / TCI) ────────
function chemblinkGetLinks(string $cas): array {
    $html = httpGet("https://www.chemblink.com/en/MSDS/{$cas}MSDS.htm", [
        'Accept: text/html,application/xhtml+xml',
        'Referer: https://www.chemblink.com/',
    ], 20);
    if (!$html) return [];
    // ChemBlink hosts mirror PDFs at /MSDSFiles/{cas}{Supplier}.pdf
    preg_match_all('/https?:\/\/www\.chemblink\.com\/MSDSFiles\/[^"\'<>\s]+\.pdf/i', $html, $m);
    $out = [];
    foreach (array_unique($m[0]) as $url) {
        // Extract supplier name: e.g. /MSDSFiles/7647-01-0Alfa-Aesar.pdf → Alfa-Aesar
        $supplier = preg_replace('/.*\/MSDSFiles\/[^A-Z]*([A-Za-z][A-Za-z0-9\-]+)\.pdf$/', '$1', $url);
        $out[] = [
            'url'     => $url,
            'source'  => "ChemBlink/$supplier",
            'title'   => "$cas SDS — $supplier",
            'referer' => 'https://www.chemblink.com/',
        ];
    }
    return $out;
}

// ── Source S2 & S3: DuckDuckGo HTML search (finds direct PDF URLs) ────────────
function ddgSearchLinks(string $query, string $label): array {
    $html = httpGet(
        'https://html.duckduckgo.com/html/?q=' . urlencode('"' . $query . '" SDS filetype:pdf'),
        ['Accept: text/html,application/xhtml+xml', 'Referer: https://duckduckgo.com/'],
        25
    );
    if (!$html) return [];
    // DDG encodes destination URLs in uddg= parameter
    preg_match_all('/uddg=([^"&\s]+)/i', $html, $m);
    $out = [];
    foreach (array_unique(array_map('urldecode', $m[1])) as $url) {
        if (!preg_match('/\.pdf(\?|#|$)/i', $url)) continue;
        if (!str_starts_with($url, 'http')) continue;
        $host  = parse_url($url, PHP_URL_HOST) ?? 'web';
        $out[] = ['url' => $url, 'source' => "$label ($host)", 'title' => "$query SDS PDF"];
    }
    return $out;
}

// ── Source S5: ChemBlink extra supplier URL patterns (direct, no HTML fetch) ──
function chemblinkExtraLinks(string $cas): array {
    $suppliers = ['Fisher', 'Merck', 'Fluka', 'Acros', 'Aldrich', 'Strem', 'Mallinckrodt', 'Lancaster'];
    $out = [];
    foreach ($suppliers as $sup) {
        $out[] = [
            'url'     => "https://www.chemblink.com/MSDSFiles/{$cas}{$sup}.pdf",
            'source'  => "ChemBlink/$sup",
            'title'   => "$cas SDS — $sup",
            'referer' => 'https://www.chemblink.com/',
        ];
    }
    return $out;
}

// ── Per-source gathering with live display ─────────────────────────────────────
function gatherAndDownload(array $chem, array $opts, int $userId, string $logFile): string {
    $chemId   = (int)$chem['id'];
    $cas      = trim($chem['cas_number'] ?? '');
    $name     = trim($chem['name']       ?? '');
    $iupac    = trim($chem['iupac_name'] ?? '');
    $sdsUrl   = trim($chem['sds_url']    ?? '');
    $dryRun   = $opts['dry_run'];
    $maxFiles = $opts['max_files'];

    $existCount = $dryRun ? 0 : existingSdsCount($chemId);
    $slotsLeft  = $maxFiles - $existCount;
    if ($slotsLeft <= 0) return 'SKIP';

    $uploadDir  = SCRIPT_ROOT . '/uploads/sds/' . $chemId;
    $webBase    = '/v1/uploads/sds/' . $chemId;
    $downloaded = 0;
    $savedSrcs  = [];
    $isPrimary  = ($existCount === 0);
    $cid        = null;

    // Pre-fetch CID (shared across sources)
    out(c(DM, '  Resolving PubChem CID...'));
    $cid = pubchemGetCid($cas, $iupac ?: $name);
    out(c($cid ? TL : DM, '  PubChem CID: ' . ($cid ? "$cid" : 'not found')));
    out('');

    $sourceNum  = 0;
    $totalSrc   = count(SDS_SOURCES);

    foreach (SDS_SOURCES as $srcKey => $srcName) {
        $sourceNum++;

        if ($downloaded >= $slotsLeft) {
            out(c(DM, "  [S$sourceNum/$totalSrc] $srcName"));
            out(c(DM, "         (max $maxFiles files reached — skipping remaining sources)"));
            out('');
            continue;
        }

        out(c(BL . B, "  [S$sourceNum/$totalSrc] ") . c(WH, $srcName));

        // ── Gather candidate links for this source ───────────────────────────
        $candidates = match($srcKey) {
            'chemblink'   => chemblinkGetLinks($cas),
            'ddg_cas'     => ddgSearchLinks($cas, 'CAS-Search'),
            'ddg_name'    => ddgSearchLinks($iupac ?: $name, 'Name-Search'),
            'pubchem'     => $cid ? pubchemGetSdsLinks($cid) : [],
            'chemblink_x' => chemblinkExtraLinks($cas),
            default       => [],
        };

        // Include DB sds_url for first source only
        if ($sourceNum === 1 && $sdsUrl && str_starts_with($sdsUrl, 'http')) {
            array_unshift($candidates, ['url' => $sdsUrl, 'source' => 'DB sds_url', 'title' => $name . ' SDS']);
        }

        $n = count($candidates);
        if ($n === 0) {
            out(c(DM, "         No links found from this source"));
            out('');
            continue;
        }

        out(c(TL, "         Found $n link(s) — trying to download..."));

        // ── Download each candidate ──────────────────────────────────────────
        $dlNum = 0;
        foreach ($candidates as $cand) {
            if ($downloaded >= $slotsLeft) break;
            $dlNum++;

            $srcLabel = $cand['source'];
            $referer  = $cand['referer'] ?? '';
            $urlShort = mb_strimwidth($cand['url'], 0, 58, '..');

            echo "         " . c(DM, "[$dlNum] ") . padLine(c(WH, $srcLabel), 30) . c(DM, " ...");

            if ($dryRun) {
                echo "  " . c(YL, "[DRY-RUN]") . PHP_EOL;
                $downloaded++;
                $savedSrcs[] = $srcLabel;
                $isPrimary   = false;
                continue;
            }

            $safeSource = preg_replace('/[^\w-]/', '_', $srcLabel);
            $filename   = "sds_{$safeSource}_" . date('Ymd') . '_' . ($existCount + $downloaded + 1) . '.pdf';

            $result = downloadVerifiedPdf($cand['url'], $uploadDir, $filename, $referer);
            if (!$result) {
                echo "  " . c(YL, "[skip — not a PDF]") . PHP_EOL;
                continue;
            }

            $id = saveSdsRecord($chemId,
                array_merge($cand, ['web_path' => "$webBase/$filename", 'file_size' => $result['size']]),
                $isPrimary, $userId
            );

            if ($id) {
                $kb = round($result['size'] / 1024, 1);
                echo "  " . c(GN . B, "[SAVED]") . c(GN, "  $kb KB  (db#$id)") . PHP_EOL;
                $downloaded++;
                $savedSrcs[] = $srcLabel;
                $isPrimary   = false;
            } else {
                echo "  " . c(RD, "[DB error]") . PHP_EOL;
            }
        }
        out('');
    }

    // ── Result ────────────────────────────────────────────────────────────────
    if ($downloaded === 0) {
        if ($logFile) file_put_contents($logFile, "[NOTFOUND] $cas | $name\n", FILE_APPEND);
        return 'NOTFOUND';
    }
    $srcList = implode(', ', array_unique($savedSrcs));
    if ($logFile) file_put_contents($logFile, "[OK] $cas | $name | files=$downloaded | $srcList\n", FILE_APPEND);
    return 'OK:' . $downloaded;
}

// ── Banner ────────────────────────────────────────────────────────────────────
function printBanner(): void {
    out('');
    out(MG . B . '  o================================================================o' . R);
    out(MG . B . '  |' . R . CY . B . '       SUT ChemBot ─ SDS Bulk Downloader  v2.0              ' . R . MG . B . '|' . R);
    out(MG . B . '  |' . R . DM .     '   S1 ChemBlink  S2 DDG-CAS  S3 DDG-Name  S4 PubChem  S5 CBX' . R . MG . B . '|' . R);
    out(MG . B . '  o================================================================o' . R);
    out('');
}

// ─────────────────────────────────────────────────────────────────────────────
$opts = parseArgs($argv);
printBanner();

// ── Stats mode ────────────────────────────────────────────────────────────────
if ($opts['stats']) {
    $total   = Database::fetch("SELECT COUNT(*) AS c FROM chemicals WHERE is_active=1")['c'] ?? 0;
    $withSds = Database::fetch("SELECT COUNT(DISTINCT chemical_id) AS c FROM chemical_sds_files")['c'] ?? 0;
    $files   = Database::fetch("SELECT COUNT(*) AS c FROM chemical_sds_files")['c'] ?? 0;
    $sizeMb  = Database::fetch("SELECT ROUND(SUM(file_size)/1048576,1) AS s FROM chemical_sds_files")['s'] ?? '0';
    $noSds   = $total - $withSds;
    out(MG . B . '  o================================================================o' . R);
    out(MG . B . '  |' . R . B . WH . str_pad('  SDS FILE STATISTICS', 66) . R . MG . B . '|' . R);
    out(MG . B . '  |' . str_repeat(' ', 66) . '|' . R);
    foreach ([
        ['Total active chemicals', c(WH, (string)$total)],
        ['With SDS files',         c(GN, (string)$withSds)],
        ['Missing SDS',            c(YL, (string)$noSds)],
        ['Total PDF files',        c(CY, (string)$files)],
        ['Total disk usage',       c(WH, "$sizeMb MB")],
    ] as [$lbl, $val]) {
        out(MG . B . sprintf('  | %-26s : %-34s |', $lbl, $val) . R);
    }
    out(MG . B . '  |' . str_repeat(' ', 66) . '|' . R);
    out(MG . B . '  o================================================================o' . R);
    out('');
    exit(0);
}

if (!in_array($opts['mode'], ['missing', 'all', 'cas'], true)) {
    echo RD . "  [ERROR] Unknown mode: {$opts['mode']}" . R . PHP_EOL; exit(1);
}

// Log file
if (!$opts['log']) $opts['log'] = SCRIPT_ROOT . '/scripts/sds_download_' . date('Ymd_His') . '.log';
if (!$opts['dry_run']) file_put_contents($opts['log'], "=== SDS Log " . date('Y-m-d H:i:s') . " mode={$opts['mode']} ===\n");

out(c(DM, "  Mode       : {$opts['mode']}" . ($opts['cas_target'] ? " ({$opts['cas_target']})" : '')));
out(c(DM, "  Max files  : {$opts['max_files']} per chemical | Delay: {$opts['delay']}s | Sources: " . implode(', ', array_keys(SDS_SOURCES))));
if ($opts['dry_run']) out(c(YL, '  [DRY-RUN]  No downloads — no DB writes'));
out('');

$userId    = getAdminUserId();
$chemicals = getChemicalsToProcess($opts);
$total     = count($chemicals);

if ($total === 0) { out(c(YL, '  No chemicals found to process.')); exit(0); }
out(c(GN, "  Found $total chemical(s) to process."));
out('');

// ── Main loop ─────────────────────────────────────────────────────────────────
$tStart = time();
$stats  = ['ok' => 0, 'notfound' => 0, 'skip' => 0, 'err' => 0, 'files' => 0];

foreach ($chemicals as $idx => $chem) {
    $done   = $idx + 1;
    $cas    = $chem['cas_number'] ?? '—';
    $name   = mb_strimwidth($chem['name'] ?? '?', 0, 36, '..');
    $el     = time() - $tStart;
    $avg    = $done > 1 ? $el / ($done - 1) : 0;
    $eta    = $avg > 0 ? (int)($avg * ($total - $done)) : 0;
    $etaStr = sprintf('%02d:%02d', intdiv($eta, 60), $eta % 60);

    // ── Chemical header ───────────────────────────────────────────────────────
    bar62();
    out(progressBar($done - 1, $total) .
        '  ' . c(WH . B, "[$done/$total]") .
        '  ' . c(CY . B, str_pad($cas, 15)) .
        c(WH, $name) .
        ($total > 1 ? c(DM, "  ETA $etaStr") : ''));
    bar62();
    out('');

    $chemStart = time();
    $result    = 'ERR';
    try {
        $result = gatherAndDownload($chem, $opts, $userId, $opts['log']);
    } catch (Throwable $e) {
        out(c(RD, '  Exception: ' . $e->getMessage()));
        if ($opts['log']) file_put_contents($opts['log'], "[ERR] $cas | " . $e->getMessage() . "\n", FILE_APPEND);
    }

    $elapsed = time() - $chemStart;
    bar62();
    if (str_starts_with($result, 'OK:')) {
        $n = (int)substr($result, 3);
        $stats['ok']++; $stats['files'] += $n;
        out(c(GN . B, "  [OK]") . c(GN, "  $n SDS PDF(s) saved") . c(DM, "  ({$elapsed}s)"));
    } elseif ($result === 'NOTFOUND') {
        $stats['notfound']++;
        out(c(DM, "  [NOT FOUND]") . "  No downloadable SDS PDF found from any of " . count(SDS_SOURCES) . " sources" . c(DM, "  ({$elapsed}s)"));
    } elseif ($result === 'SKIP') {
        $stats['skip']++;
        out(c(YL, "  [SKIP]") . "  Already at max SDS files limit");
    } else {
        $stats['err']++;
        out(c(RD, "  [ERR]") . "  Unexpected error — check log");
    }
    out('');

    if ($idx < $total - 1 && $opts['delay'] > 0) {
        out(c(DM, "  Waiting {$opts['delay']}s..."));
        usleep((int)($opts['delay'] * 1_000_000));
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────
$elapsed = time() - $tStart;
$mins    = intdiv($elapsed, 60);
$secs    = $elapsed % 60;

out('');
out(MG . B . '  o================================================================o' . R);
out(MG . B . '  |' . R . B . WH . str_pad('  DOWNLOAD SUMMARY', 66) . R . MG . B . '|' . R);
out(MG . B . '  |' . str_repeat(' ', 66) . '|' . R);
foreach ([
    ['Chemicals processed',  c(WH, (string)$total)],
    ['Downloaded OK',        c(GN, (string)$stats['ok']) . '  (files: ' . c(GN, (string)$stats['files']) . ')'],
    ['Not found (all src)',  c(DM, (string)$stats['notfound'])],
    ['Skipped (at limit)',   c(YL, (string)$stats['skip'])],
    ['Errors',               c(RD, (string)$stats['err'])],
    ['Time elapsed',         "{$mins}m {$secs}s"],
] as [$lbl, $val]) {
    out(MG . B . sprintf('  | %-26s : %-34s |', $lbl, $val) . R);
}
out(MG . B . '  |' . str_repeat(' ', 66) . '|' . R);
out(MG . B . '  o================================================================o' . R);
out('');

if ($opts['log'] && !$opts['dry_run']) {
    file_put_contents($opts['log'], "=== DONE ok={$stats['ok']} files={$stats['files']} notfound={$stats['notfound']} err={$stats['err']} time={$mins}m{$secs}s\n", FILE_APPEND);
    out(c(DM, "  Log: {$opts['log']}"));
    out('');
}

exit($stats['err'] > 0 ? 1 : 0);
