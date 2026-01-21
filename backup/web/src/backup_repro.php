<?php
// backup_repro.php
// Simulates the logic inside files_action.php for backup creation

$uploadsDir = __DIR__ . '/uploads/';
$folder = 'interventions';

// Simulate "Backup Folder" action
$source = $uploadsDir . $folder;

// 1. Check realpath behavior
$realSource = realpath($source);

echo "Uploads Dir: $uploadsDir\n";
echo "Source Input: $source\n";
echo "Real Source: $realSource\n";

if (!file_exists($source)) {
    // Create dummy structure for test
    if (!is_dir($uploadsDir)) mkdir($uploadsDir);
    if (!is_dir($source)) mkdir($source);
    touch($source . '/test_file.txt');
    $realSource = realpath($source);
}

// 2. Simulate PHP ZipArchive Logic
echo "\n--- PHP Logic Simulation ---\n";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($realSource, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$basePath = basename($folder); // "interventions"
echo "Base Path: '$basePath'\n";

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $filePath = $file->getRealPath();
    
    // Logic from files_action.php line 44: $source = realpath($source);
    // Line 113: $relativePath = substr($filePath, strlen($source) + 1);
    
    // Simulate legacy behavior if source had trailing slash or not?
    // In files_action.php: $source = realpath($source);
    
    $relativePath = substr($filePath, strlen($realSource) + 1);
    
    // Line 116
    if ($basePath !== '') {
        $zipPath = $basePath . '/' . $relativePath;
    } else {
        $zipPath = $relativePath;
    }
    
    echo "File: " . basename($filePath) . "\n";
    echo "  Zip : $zipPath\n";
    
    // Check for weird concatenations
    // Check what happens if realSource is diff from what we expect
}
?>
