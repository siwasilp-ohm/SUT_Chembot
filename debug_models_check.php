<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
$items = Database::fetchAll('SELECT id, label, source_type, embed_url, file_url, file_path FROM packaging_3d_models WHERE is_active=1 LIMIT 10');
header('Content-Type: text/plain');
foreach($items as $r) {
    echo 'ID:'.$r['id'].' src:'.$r['source_type'].' label:'.substr($r['label']??'',0,30)."\n";
    echo '  embed_url:'.substr($r['embed_url']??'',0,100)."\n";
    echo '  file_url:'.substr($r['file_url']??'',0,100)."\n";
    echo '  file_path:'.substr($r['file_path']??'',0,100)."\n";
    echo "\n";
}
echo "Total: ".count($items)."\n";
