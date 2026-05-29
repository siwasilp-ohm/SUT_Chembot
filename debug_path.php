<?php
header('Content-Type: text/plain');
echo "--- Environment Debug ---\n";
echo "Hostname: " . gethostname() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current File: " . __FILE__ . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";

echo "\n--- Directory Listing (Document Root) ---\n";
$dir = $_SERVER['DOCUMENT_ROOT'];
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        echo " - " . (is_dir("$dir/$f") ? "[DIR] " : "") . $f . "\n";
    }
} else {
    echo "Document Root is not a directory or not accessible.\n";
}

echo "\n--- Directory Listing (v1) ---\n";
$v1 = $dir . '/v1';
if (is_dir($v1)) {
    $files = scandir($v1);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        echo " - " . (is_dir("$v1/$f") ? "[DIR] " : "") . $f . "\n";
    }
} else {
    echo "/v1 folder not found in Document Root.\n";
}
