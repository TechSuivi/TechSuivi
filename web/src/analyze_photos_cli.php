<?php
// analyze_photos_cli.php
// CLI Version of the analyzer
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "--- STARTING ANALYSIS ---\n";

try {
    $pdo = getDatabaseConnection();
    
    // Get all photos from DB
    $stmt = $pdo->query("SELECT * FROM intervention_photos");
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $uploadDir = __DIR__ . '/uploads/interventions/';
    $filesOnDisk = scandir($uploadDir);
    
    // Filter out . and ..
    $filesOnDisk = array_filter($filesOnDisk, function($f) { return $f !== '.' && $f !== '..'; });
    
    $missingCount = 0;
    $foundCount = 0;
    $fixable = [];
    
    echo "DB Photos: " . count($photos) . "\n";
    echo "Disk Files: " . count($filesOnDisk) . "\n";
    
    foreach ($photos as $p) {
        $filename = $p['filename']; // Expected: ID_uniqid.ext
        $path = $uploadDir . $filename;
        
        if (file_exists($path)) {
            $foundCount++;
        } else {
            $missingCount++;
            // Try to find a match on disk
            // Strategy: Extract uniqid from DB filename and search for it in disk files
            
            // Pattern: ID_uniqid.ext
            if (preg_match('/^([^_]+)_([^\.]+)\.(.+)$/', $filename, $matches)) {
                $uniqid = $matches[2];
                $ext = $matches[3];
                
                // Search for this uniqid in disk files
                $matchFound = false;
                foreach ($filesOnDisk as $diskFile) {
                    if (strpos($diskFile, $uniqid) !== false && strpos($diskFile, $ext) !== false) {
                        // Found a candidate!
                        $fixable[] = [
                            'id' => $p['id'],
                            'db_name' => $filename,
                            'disk_name' => $diskFile,
                            'path_old' => $uploadDir . $diskFile,
                            'path_new' => $uploadDir . $filename
                        ];
                        $matchFound = true;
                        break;
                    }
                }
                
                if (!$matchFound) {
                    echo "MISSING: {$filename} (UniqID: $uniqid) - No candidate found\n";
                }
            } else {
                echo "MISSING: {$filename} - specific format not recognized\n";
            }
        }
    }
    
    echo "\n--- SUMMARY ---\n";
    echo "OK: $foundCount\n";
    echo "Missing: $missingCount\n";
    echo "Fixable candidates: " . count($fixable) . "\n";
    
    if (count($fixable) > 0) {
        echo "\n--- APPLYING FIXES ---\n";
        foreach ($fixable as $item) {
            echo "Renaming: {$item['disk_name']} -> {$item['db_name']} ... ";
            if (rename($item['path_old'], $item['path_new'])) {
                echo "OK\n";
                
                // Also check for thumbnail
                // DB Thumb: thumb_DBNAME
                // Disk Thumb: likely thumb_DISKNAME
                $diskThumb = 'thumb_' . $item['disk_name'];
                $dbThumb = 'thumb_' . $item['db_name'];
                
                // Also try strict "interventions" prefix removal for thumb if not found
                
                if (file_exists($uploadDir . $diskThumb)) {
                    echo "  Renaming Thumb: $diskThumb -> $dbThumb ... ";
                     if (rename($uploadDir . $diskThumb, $uploadDir . $dbThumb)) {
                         echo "OK\n";
                     } else {
                         echo "FAIL\n";
                     }
                } else {
                    echo "  Thumb $diskThumb not found on disk.\n";
                }
                
            } else {
                echo "FAIL\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- DONE ---\n";
?>
