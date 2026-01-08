<?php
// recover_photos.php
// Script to recover filenames mangled during restore
error_reporting(E_ALL);
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // 1. Load all valid photos from DB
    $stmt = $pdo->query("SELECT id, filename, original_filename FROM intervention_photos");
    $dbPhotos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dbMap = []; // suffix -> filename
    foreach ($dbPhotos as $p) {
        $dbMap[$p['filename']] = $p;
        
        // Also map by uniqid part if possible
        // ID_uniqid.ext
        if (preg_match('/_([a-zA-Z0-9]+)\./', $p['filename'], $matches)) {
            $uniqid = $matches[1];
            // Use uniqid as key (assuming fairly unique)
            $dbMap[$uniqid] = $p;
        }
    }
    
    $uploadDir = __DIR__ . '/uploads/interventions/';
    $files = scandir($uploadDir);
    
    echo "Scanning $uploadDir...\n";
    
    $renamedCount = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // Skip already correct files
        if (isset($dbMap[$file])) {
            continue;
        }
        
        // Candidates for recovery (mangled files)
        // Verify if it looks like a mangled file (starts with 'interventions' or contains known pattern)
        // User example: interventions5bd2e53b644.jpg
        // User example: interventions23B_695bd2e4906bd.jpg
        
        $targetFilename = null;
        
        // Strategy 1: Search for uniqid in the filename
        foreach ($dbMap as $key => $data) {
            // $key is either filename or uniqid
            // If key is short (uniqid), check if file contains it
            if (strlen($key) < 20 && strpos($file, $key) !== false) {
                 // Found a match!
                 $targetFilename = $data['filename'];
                 echo "MATCH FOUND: $file contains $key -> Target: $targetFilename\n";
                 break;
            }
        }
        
        // Strategy 2: If file starts with 'interventions' and has no match yet
        if (!$targetFilename && strpos($file, 'interventions') === 0) {
            // Try to strip 'interventions' and see if it helps (unlikely given data loss, but maybe prefix?)
            $stripped = substr($file, 13);
            if (isset($dbMap[$stripped])) {
                $targetFilename = $stripped;
                echo "PREFIX MATCH: $file -> $targetFilename\n";
            }
        }
        
        if ($targetFilename) {
            // Perform rename
            $oldPath = $uploadDir . $file;
            $newPath = $uploadDir . $targetFilename;
            
            if (!file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    echo "RECOVERED: $file -> $targetFilename\n";
                    $renamedCount++;
                } else {
                    echo "ERROR: Could not rename $file\n";
                }
            } else {
                echo "SKIPPED: Target $targetFilename already exists (Duplicate?)\n";
            }
        }
    }
    
    // Also cleanup thumbs if possible
    // Thumbs might be 'thumb_interventions...'
    // If we recovered the main file, we can try to find the orphan thumb
    
    echo "Recovery pass complete. $renamedCount files recovered.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
