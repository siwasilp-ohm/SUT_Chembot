<?php
require_once __DIR__ . '/../includes/database.php';
$f = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE molecular_formula IS NOT NULL AND molecular_formula != '' AND molecular_formula != 'N/A' AND is_active=1")['c'];
echo "Formulas now: $f (was 923, added: " . ($f - 923) . ")\n";
$rem = (int)Database::fetch("SELECT COUNT(*) as c FROM chemicals WHERE is_active=1 AND cas_number != '' AND (molecular_formula IS NULL OR molecular_formula = '' OR molecular_formula = 'N/A')")['c'];
echo "Still need: $rem\n";
