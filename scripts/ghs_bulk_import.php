<?php
/**
 * GHS Bulk Import CLI
 * Scans all chemicals and auto-imports GHS / Safety data from PubChem.
 *
 * Usage:
 *   php ghs_bulk_import.php [options]
 *
 * Options:
 *   --mode=missing      Only chemicals without GHS data (default)
 *   --mode=all          Re-import / overwrite everyone
 *   --mode=cas=XXXXX    Single chemical by CAS number
 *   --limit=N           Stop after N chemicals
 *   --delay=0.5         Seconds between PubChem calls (default 0.8)
 *   --dry-run           Do not write to DB
 *   --verbose           Print each sub-step
 *   --resume            Skip IDs already in progress log
 *   --log=path          Custom progress log file
 */

declare(strict_types=1);
define('SCRIPT_ROOT', dirname(__DIR__));
require_once SCRIPT_ROOT . '/includes/database.php';

// ── ANSI colours ─────────────────────────────────────────────────────────────
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

// ── helpers ───────────────────────────────────────────────────────────────────
function c(string $color, string $text): string { return $color . $text . R; }
function out(string $line): void { echo $line . PHP_EOL; }
function err(string $line): void { fwrite(STDERR, RD . $line . R . PHP_EOL); }

function progressBar(int $done, int $total, int $width = 38): string {
    $pct    = $total > 0 ? $done / $total : 0;
    $filled = (int)round($pct * $width);
    $bar    = str_repeat('█', $filled) . str_repeat('░', $width - $filled);
    return sprintf('%s%s%s %3d%%', CY, $bar, R, (int)round($pct * 100));
}

function parseArgs(array $argv): array {
    $opts = [
        'mode'    => 'missing',
        'limit'   => 0,
        'delay'   => 0.8,
        'dry_run' => false,
        'verbose' => false,
        'resume'  => false,
        'log'     => '',
        'cas_target' => '',
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run')  { $opts['dry_run'] = true; continue; }
        if ($arg === '--verbose')  { $opts['verbose'] = true; continue; }
        if ($arg === '--resume')   { $opts['resume']  = true; continue; }
        if (preg_match('/^--(\w+)=(.*)$/', $arg, $m)) {
            $k = str_replace('-', '_', $m[1]);
            $v = $m[2];
            if ($k === 'mode' && str_starts_with($v, 'cas=')) {
                $opts['mode']       = 'cas';
                $opts['cas_target'] = substr($v, 4);
            } elseif (isset($opts[$k])) {
                $opts[$k] = is_bool($opts[$k]) ? (bool)$v : (is_int($opts[$k]) ? (int)$v : (float)$v);
            }
            if ($k === 'mode' && in_array($v, ['missing','all'], true)) $opts['mode'] = $v;
            if ($k === 'log')   $opts['log'] = $v;
            if ($k === 'delay') $opts['delay'] = (float)$v;
            if ($k === 'limit') $opts['limit'] = (int)$v;
        }
    }
    return $opts;
}

// ── banner ────────────────────────────────────────────────────────────────────
function banner(): void {
    out('');
    out(c(MG, B . '  ╔══════════════════════════════════════════════════════╗'));
    out(c(MG, B . '  ║') . c(CY, B . '      SUT ChemBot · GHS Bulk Import Tool v2.0       ') . c(MG, B . '║'));
    out(c(MG, B . '  ║') . c(WH, '   Powered by PubChem REST API · Suranaree Univ.    ') . c(MG, B . '║'));
    out(c(MG, B . '  ╚══════════════════════════════════════════════════════╝'));
    out('');
}

// ── DB ────────────────────────────────────────────────────────────────────────
function fetchChemicalsToProcess(array $opts): array {
    $where = ['c.is_active = 1'];
    $params = [];

    if ($opts['mode'] === 'missing') {
        $where[] = 'NOT EXISTS(SELECT 1 FROM chemical_ghs_data g WHERE g.chemical_id = c.id)';
    } elseif ($opts['mode'] === 'cas') {
        $where[] = 'c.cas_number = :cas';
        $params[':cas'] = $opts['cas_target'];
    }

    $limit = $opts['limit'] > 0 ? ' LIMIT ' . $opts['limit'] : '';
    $sql = "SELECT c.id, c.name, c.cas_number, c.iupac_name
            FROM chemicals c
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.cas_number IS NULL, c.id" . $limit;

    return Database::fetchAll($sql, $params);
}

function saveGhsToDb(int $chemId, array $ghs, array $exp, bool $dryRun): void {
    $signalWord = $ghs['signal_word'] ?: 'None';
    if (!in_array($signalWord, ['Danger','Warning','None'], true)) $signalWord = 'None';

    $hText = implode("\n", $ghs['h_statements'] ?? []);
    $pText = implode("\n", $ghs['p_statements'] ?? []);

    $fields = [
        'chemical_id'       => $chemId,
        'signal_word'       => $signalWord,
        'ghs_pictograms'    => json_encode($ghs['pictograms'] ?? []),
        'h_statements'      => json_encode($ghs['h_statements'] ?? []),
        'h_statements_text' => $hText,
        'p_statements'      => json_encode($ghs['p_statements'] ?? []),
        'p_statements_text' => $pText,
        'source'            => 'PubChem',
        'last_reviewed'     => date('Y-m-d'),
        'reviewed_by'       => 1,
    ];

    if ($dryRun) return;

    $existing = Database::fetch(
        "SELECT id FROM chemical_ghs_data WHERE chemical_id = :id",
        [':id' => $chemId]
    );
    if ($existing) {
        $upd = $fields; unset($upd['chemical_id']);
        Database::update('chemical_ghs_data', $upd, 'chemical_id = :id', [':id' => $chemId]);
    } else {
        Database::insert('chemical_ghs_data', $fields);
    }

    // Update denormalized columns on chemicals
    $chemUpd = [
        'signal_word'      => $signalWord === 'None' ? 'No signal word' : $signalWord,
        'hazard_pictograms'=> json_encode($ghs['pictograms'] ?? []),
    ];
    // Physical properties from experimental data
    $expMap = [
        'boiling_point' => 'boiling_point',
        'melting_point' => 'melting_point',
        'flash_point'   => 'flash_point',
        'density'       => 'density',
    ];
    foreach ($expMap as $expKey => $dbCol) {
        if (!empty($exp[$expKey])) {
            // Extract first numeric value
            preg_match('/[\-\d]+\.?\d*/', (string)$exp[$expKey], $num);
            if ($num) $chemUpd[$dbCol] = (float)$num[0];
        }
    }
    if (!empty($exp['solubility']))     $chemUpd['solubility']     = substr((string)$exp['solubility'], 0, 500);
    if (!empty($exp['vapor_pressure'])) $chemUpd['vapor_pressure'] = preg_match('/[\d.]+/', (string)$exp['vapor_pressure'], $m) ? (float)$m[0] : null;

    Database::update('chemicals', $chemUpd, 'id = :id', [':id' => $chemId]);
}

// ── PubChem functions (self-contained, no API file dependency) ────────────────
function pubchemFetch(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT      => 'SUT-ChemBot/2.0-CLI (Educational; Thailand)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $out  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno) return null;
        return ($out !== false && $code === 200) ? $out : null;
    }
    $ctx = stream_context_create(['http' => [
        'timeout'      => 12,
        'user_agent'   => 'SUT-ChemBot/2.0-CLI',
        'ignore_errors'=> false,
    ]]);
    return @file_get_contents($url, false, $ctx) ?: null;
}

function findPubchemSection(array $secs, string $heading): ?array {
    foreach ($secs as $s) {
        if (($s['TOCHeading'] ?? '') === $heading) return $s['Section'] ?? $s['Information'] ?? [];
        if (!empty($s['Section'])) {
            $found = findPubchemSection($s['Section'], $heading);
            if ($found !== null) return $found;
        }
    }
    return null;
}

function parsePubchemGhs(?array $root): array {
    $result = ['signal_word'=>'','pictograms'=>[],'h_statements'=>[],'p_statements'=>[],'source_count'=>0];
    $secs   = findPubchemSection($root['Record']['Section'] ?? [], 'GHS Classification');
    if (empty($secs)) return $result;

    $pics=[]; $swords=[]; $hstmts=[]; $pstmts=[];

    // PubChem currently returns GHS Classification as a flat Information[] (Name key per item).
    // Legacy format used sub-sections with TOCHeading — handle both.
    $isFlat = isset($secs[0]['Name']) || isset($secs[0]['ReferenceNumber']);

    if ($isFlat) {
        foreach ($secs as $info) {
            $name = $info['Name'] ?? '';
            foreach ($info['Value']['StringWithMarkup'] ?? [] as $swm) {
                $str = trim($swm['String'] ?? '');
                if (stripos($name, 'Pictogram') !== false) {
                    foreach ($swm['Markup'] ?? [] as $mk) {
                        if (preg_match('/GHS(\d{2})/i', $mk['URL']   ?? '', $m)) $pics[] = 'GHS' . str_pad($m[1],2,'0',STR_PAD_LEFT);
                        if (preg_match('/GHS(\d{2})/i', $mk['Extra'] ?? '', $m)) $pics[] = 'GHS' . str_pad($m[1],2,'0',STR_PAD_LEFT);
                    }
                }
                if ($name === 'Signal' && $str) {
                    if (stripos($str, 'Danger')  !== false) $swords[] = 'Danger';
                    elseif (stripos($str, 'Warning') !== false) $swords[] = 'Warning';
                }
                if (stripos($name, 'Hazard Statement') !== false && $str && preg_match('/H\d{3}/', $str)) {
                    $hstmts[] = $str;
                }
                if (stripos($name, 'Precautionary') !== false && $str) {
                    preg_match_all('/P\d{3}[+\d]*/', $str, $m);
                    $pstmts = array_merge($pstmts, $m[0]);
                }
            }
        }
        $result['source_count'] = count(array_filter($secs, fn($i) => ($i['Name'] ?? '') === 'Signal'));
    } else {
        // Legacy sub-section format (TOCHeading per field)
        foreach ($secs as $sec) {
            $h = $sec['TOCHeading'] ?? '';
            foreach ($sec['Information'] ?? [] as $info) {
                foreach ($info['Value']['StringWithMarkup'] ?? [] as $swm) {
                    $str = trim($swm['String'] ?? '');
                    if (stripos($h, 'Pictogram') !== false) {
                        foreach ($swm['Markup'] ?? [] as $mk) {
                            if (preg_match('/GHS(\d{2})/i', $mk['URL'] ?? '', $m)) $pics[] = 'GHS' . str_pad($m[1],2,'0',STR_PAD_LEFT);
                        }
                    }
                    if (stripos($h, 'Signal') !== false && $str) {
                        if (stripos($str, 'Danger') !== false) $swords[] = 'Danger';
                        elseif (stripos($str, 'Warning') !== false) $swords[] = 'Warning';
                    }
                    if (stripos($h, 'Hazard Statement') !== false && $str && preg_match('/H\d{3}/', $str)) $hstmts[] = $str;
                    if (stripos($h, 'Precautionary') !== false && $str) {
                        preg_match_all('/P\d{3}[+\d]*/', $str, $m);
                        $pstmts = array_merge($pstmts, $m[0]);
                    }
                }
            }
        }
        $result['source_count'] = count(array_filter($secs, fn($s) => stripos($s['TOCHeading'] ?? '', 'Signal') !== false));
    }

    $result['pictograms']   = array_values(array_unique($pics));
    sort($result['pictograms']);
    $result['signal_word']  = in_array('Danger', $swords) ? 'Danger' : (in_array('Warning', $swords) ? 'Warning' : ($swords[0] ?? ''));
    $result['h_statements'] = array_values(array_unique($hstmts)); sort($result['h_statements']);
    $result['p_statements'] = array_values(array_unique($pstmts)); sort($result['p_statements']);
    return $result;
}

function parsePubchemExp(?array $root): array {
    $result = [];
    $secs   = findPubchemSection($root['Record']['Section'] ?? [], 'Experimental Properties');
    if ($secs === null) return $result;

    $map = [
        'Boiling Point'   => 'boiling_point',
        'Melting Point'   => 'melting_point',
        'Flash Point'     => 'flash_point',
        'Density'         => 'density',
        'Solubility'      => 'solubility',
        'Vapor Pressure'  => 'vapor_pressure',
    ];

    foreach ($secs as $sec) {
        $head = $sec['TOCHeading'] ?? '';
        foreach ($map as $label => $key) {
            if (stripos($head, $label) === false) continue;
            foreach ($sec['Information'] ?? [] as $info) {
                $vals = $info['Value']['StringWithMarkup'] ?? [];
                if (!empty($vals[0]['String'])) {
                    $result[$key] = $vals[0]['String'];
                    break 2;
                }
            }
        }
    }
    return $result;
}

function fetchFromPubchem(string $query, bool $verbose): ?array {
    $query = trim($query);
    if (!$query) return null;

    if ($verbose) out(c(DM, "    → PubChem lookup: " . $query));

    $enc = rawurlencode($query);
    $raw = pubchemFetch("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/{$enc}/JSON");
    if (!$raw) return null;

    $pc  = json_decode($raw, true);
    $cid = $pc['PC_Compounds'][0]['id']['id']['cid'] ?? null;
    if (!$cid) return null;

    if ($verbose) out(c(DM, "    → CID: {$cid}"));
    usleep(300000); // 0.3s

    $ghsRaw = pubchemFetch("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/{$cid}/JSON?heading=Safety+and+Hazards");
    $ghs    = parsePubchemGhs(json_decode((string)$ghsRaw, true));
    usleep(300000);

    $expRaw = pubchemFetch("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/{$cid}/JSON?heading=Experimental+Properties");
    $exp    = parsePubchemExp(json_decode((string)$expRaw, true));

    $propRaw = pubchemFetch("https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/property/MolecularFormula,MolecularWeight/JSON");
    $props   = json_decode((string)$propRaw, true)['PropertyTable']['Properties'][0] ?? [];

    return ['cid'=>$cid, 'ghs'=>$ghs, 'experimental'=>$exp, 'props'=>$props];
}

// ── main ──────────────────────────────────────────────────────────────────────
@set_time_limit(0);
$opts = parseArgs($argv);

// Enable ANSI on Windows
if (PHP_OS_FAMILY === 'Windows') {
    @shell_exec('reg add HKCU\Console /v VirtualTerminalLevel /t REG_DWORD /d 1 /f > NUL 2>&1');
}

banner();

$logFile = $opts['log'] ?: dirname(__FILE__) . '/ghs_import_' . date('Ymd_His') . '.log';
$logFh   = fopen($logFile, 'a');
function logLine(string $line): void { global $logFh; if ($logFh) fwrite($logFh, strip_tags($line) . PHP_EOL); }

// Load resume set
$resumeIds = [];
if ($opts['resume'] && file_exists($logFile)) {
    foreach (file($logFile) as $ln) {
        if (preg_match('/^\[OK\]\s+id=(\d+)/', $ln, $m)) $resumeIds[$m[1]] = true;
        if (preg_match('/^\[SKIP\]\s+id=(\d+)/', $ln, $m)) $resumeIds[$m[1]] = true;
    }
}

// Mode label
$modeLabel = match($opts['mode']) {
    'missing' => 'Missing GHS only',
    'all'     => 'All chemicals (overwrite)',
    'cas'     => 'CAS: ' . $opts['cas_target'],
    default   => '?'
};

out(c(WH, B . '  Mode  : ') . c(YL, $modeLabel));
out(c(WH, B . '  Limit : ') . c(YL, $opts['limit'] > 0 ? (string)$opts['limit'] : 'unlimited'));
out(c(WH, B . '  Delay : ') . c(YL, $opts['delay'] . 's per chemical'));
out(c(WH, B . '  Dry run: ') . ($opts['dry_run'] ? c(YL,'YES — no DB writes') : c(GN,'NO — will write to DB')));
out(c(WH, B . '  Log   : ') . c(DM, $logFile));
out('');

// Fetch chemical list
out(c(CY, '  Querying database…'));
$chemicals = fetchChemicalsToProcess($opts);
$total     = count($chemicals);

if ($total === 0) {
    out(c(GN, '  ✓ No chemicals to process.'));
    exit(0);
}

out(c(GN, "  Found {$total} chemicals to process."));
out('');

// Stats
$stats = ['ok'=>0,'skip'=>0,'nofound'=>0,'err'=>0,'total'=>$total];
$startTime = microtime(true);

foreach ($chemicals as $i => $chem) {
    $idx    = $i + 1;
    $chemId = (int)$chem['id'];

    // Resume skip
    if ($opts['resume'] && isset($resumeIds[$chemId])) {
        $stats['skip']++;
        continue;
    }

    // Determine query
    $cas     = trim($chem['cas_number'] ?? '');
    $iupac   = trim($chem['iupac_name'] ?? '');
    $thname  = trim($chem['name'] ?? '');
    $query   = $cas ?: $iupac ?: $thname;

    // Short label for display
    $label   = ($cas ?: ($iupac ?: $thname));
    $display = mb_strimwidth($label, 0, 36, '…');

    // ETA
    $elapsed = microtime(true) - $startTime;
    $rate    = $idx > 1 ? $elapsed / ($idx - 1) : 0;
    $etaSec  = $rate > 0 ? (int)(($total - $idx) * $rate) : 0;
    $eta     = $etaSec > 3600 ? sprintf('%dh%02dm', intdiv($etaSec,3600), intdiv($etaSec%3600,60))
             : ($etaSec > 60   ? sprintf('%dm%02ds', intdiv($etaSec,60), $etaSec%60)
             : sprintf('%ds', $etaSec));

    // Progress line (overwrite current line)
    $bar   = progressBar($idx - 1, $total);
    $idxPad = str_pad((string)$idx, strlen((string)$total), ' ', STR_PAD_LEFT);
    echo "\r  " . $bar . c(DM, " {$idxPad}/{$total}") . "  " . c(WH, str_pad($display,38)) . c(DM, " ETA:{$eta}  ");

    // Try PubChem
    $result = null;
    $attempt = 0;
    while ($result === null && $attempt < 2) {
        $attempt++;
        try {
            $result = fetchFromPubchem($query, $opts['verbose']);
        } catch (Throwable $e) {
            $result = null;
            if ($opts['verbose']) { out(''); err("    PubChem error [{$attempt}]: " . $e->getMessage()); }
        }
        // If CAS failed, retry with IUPAC name
        if ($result === null && $attempt === 1 && $cas && $iupac) {
            $query = $iupac;
        }
    }

    if ($result === null) {
        $stats['nofound']++;
        if ($opts['verbose']) out('');
        if ($opts['verbose']) out(c(DM, "    [NOT FOUND] {$label}"));
        logLine("[NOTFOUND] id={$chemId} query={$label}");
        usleep((int)($opts['delay'] * 1000000 * 0.5));
        continue;
    }

    $ghs = $result['ghs'] ?? [];
    $exp = $result['experimental'] ?? [];

    // Skip if PubChem returned no pictograms and no signal word (low-value import)
    if (empty($ghs['pictograms']) && empty($ghs['signal_word']) && empty($ghs['h_statements'])) {
        $stats['nofound']++;
        logLine("[EMPTY] id={$chemId} cid={$result['cid']} query={$label}");
        usleep((int)($opts['delay'] * 1000000 * 0.3));
        continue;
    }

    try {
        saveGhsToDb($chemId, $ghs, $exp, $opts['dry_run']);
        $stats['ok']++;
        $pics = implode(',', $ghs['pictograms'] ?? []);
        logLine("[OK] id={$chemId} cid={$result['cid']} signal={$ghs['signal_word']} pics={$pics} query={$label}");
        if ($opts['verbose']) {
            out('');
            out(c(GN, "    ✓ ") . c(WH, $label) . c(DM, " → CID:{$result['cid']} signal:{$ghs['signal_word']} pics:{$pics}"));
        }
    } catch (Throwable $e) {
        $stats['err']++;
        logLine("[ERR] id={$chemId} " . $e->getMessage());
        if ($opts['verbose']) { out(''); err("    DB error for {$label}: " . $e->getMessage()); }
    }

    usleep((int)($opts['delay'] * 1000000));
}

// Final newline after progress bar
echo "\n";

// ── Summary ───────────────────────────────────────────────────────────────────
$elapsed  = microtime(true) - $startTime;
$mins     = intdiv((int)$elapsed, 60);
$secs     = (int)$elapsed % 60;
$timeStr  = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";

out('');
out(c(MG, B . '  ╔══════════════════════════════════════════════════════╗'));
out(c(MG, B . '  ║') . c(CY, B . '                    สรุปผล                           ') . c(MG, B . '║'));
out(c(MG, B . '  ╠══════════════════════════════════════════════════════╣'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%d%s', WH.B, 'สารที่ประมวลผลทั้งหมด', R, YL.B, $stats['total'], R) . str_repeat(' ', max(0,21-strlen((string)$stats['total']))) . c(MG, B . '║'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%d%s', GN.B, 'นำเข้าสำเร็จ', R, GN.B, $stats['ok'], R) . str_repeat(' ', max(0,33-strlen((string)$stats['ok']))) . c(MG, B . '║'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%d%s', YL.B, 'ข้ามแล้ว (resume)', R, YL.B, $stats['skip'], R) . str_repeat(' ', max(0,30-strlen((string)$stats['skip']))) . c(MG, B . '║'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%d%s', DM,   'ไม่พบใน PubChem', R, DM, $stats['nofound'], R) . str_repeat(' ', max(0,31-strlen((string)$stats['nofound']))) . c(MG, B . '║'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%d%s', RD.B, 'ข้อผิดพลาด', R, RD.B, $stats['err'], R) . str_repeat(' ', max(0,37-strlen((string)$stats['err']))) . c(MG, B . '║'));
out(c(MG, B . '  ║') . sprintf('  %s%-20s%s %s%s%s', CY.B, 'เวลาที่ใช้', R, CY.B, $timeStr, R) . str_repeat(' ', max(0,35-strlen($timeStr))) . c(MG, B . '║'));
out(c(MG, B . '  ╚══════════════════════════════════════════════════════╝'));
out('');
out(c(DM, "  Log saved → {$logFile}"));
out('');

logLine("=== DONE total={$stats['total']} ok={$stats['ok']} skip={$stats['skip']} nofound={$stats['nofound']} err={$stats['err']} time={$timeStr} ===");
if ($logFh) fclose($logFh);

exit($stats['err'] > 0 ? 1 : 0);
