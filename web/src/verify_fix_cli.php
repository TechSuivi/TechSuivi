<?php
// verify_fix_cli.php
require_once __DIR__ . '/config/database.php';

$uploadDir = __DIR__ . '/uploads/interventions/';
$files = scandir($uploadDir);

echo "Scanning $uploadDir...\n";

$count = 0;
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    // Check if starts with "interventions"
    if (strpos($file, 'interventions') === 0) {
        $newJsonName = substr($file, 13); // remove "interventions" (13 chars)
        
        echo "Found potentially bad file: $file -> Target: $newJsonName\n";
        $count++;
    }
}

echo "Total candidates found: $count\n";

if ($count > 0) {
    echo "Files detected with 'interventions' prefix. These likely need renaming.\n";
} else {
    echo "No files with 'interventions' prefix found.\n";
}
?>
