<?php
function fetchJson(string $url): ?array {
    $ctx = stream_context_create(['http'=>['timeout'=>15,'header'=>"User-Agent: ChemInventory/1.0\r\n"]]);
    $json = @file_get_contents($url, false, $ctx);
    return $json ? json_decode($json, true) : null;
}

function dumpSections(array $node, int $depth=0): void {
    $indent = str_repeat('  ', $depth);
    $head = $node['TOCHeading'] ?? '';
    if ($head) echo "{$indent}[{$head}]\n";
    foreach ($node['Information'] ?? [] as $info) {
        echo "{$indent}  INFO name=" . ($info['Name']??'?') . "\n";
        foreach ($info['Value']['StringWithMarkup'] ?? [] as $sw) {
            echo "{$indent}    val=" . substr($sw['String']??'',0,150) . "\n";
        }
    }
    foreach ($node['Section'] ?? [] as $child) dumpSections($child, $depth+1);
}

echo "=== Ethanol CID=702 ===\n";
usleep(200000);
$r2 = fetchJson("https://pubchem.ncbi.nlm.nih.gov/rest/pug_view/data/compound/702/JSON?heading=GHS+Classification");
if ($r2) dumpSections($r2['Record']);
else echo "No response\n";
