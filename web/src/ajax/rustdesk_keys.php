<?php
session_start();

// Empêcher l'affichage des erreurs PHP dans le retour (cela casse le JSON)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);
ob_start(); // Capture tout output parasite

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    ob_end_clean();
    die(json_encode(['error' => 'Non autorisé']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rustdeskDir = '/var/www/rustdesk_data';
$persistenceDir = __DIR__ . '/../uploads/keys';

// Créer les dossiers si manquants (Silent mode)
if (!is_dir($persistenceDir)) {
    @mkdir($persistenceDir, 0777, true);
    @chown($persistenceDir, 'www-data');
}
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    @chown($backupDir, 'www-data');
}

if ($action === 'download_keys') {
    ob_end_clean(); // On vide le buffer pour envoyer le zip propre
    $zipFile = sys_get_temp_dir() . '/rustdesk_keys_' . date('Y-m-d_His') . '.zip';
    $zip = new ZipArchive();

    // Collect files to zip first
    $filesToZip = [];
    $keyFiles = ['id_ed25519', 'id_ed25519.pub']; // Define the key files we are looking for

    foreach ($keyFiles as $name) {
        $paths = [
            $rustdeskDir . '/' . $name,
            $persistenceDir . '/' . $name,
            $backupDir . '/' . $name 
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $filesToZip[$name] = $path;
                break;
            }
        }
    }

    if (empty($filesToZip)) {
        die("Aucune clé trouvée.");
    }

    // Determine encryption method
    $password = $_GET['password'] ?? '';
    $zipBinPath = shell_exec('which zip');
    $zipBin = $zipBinPath ? trim($zipBinPath) : '';
    $useSystemZip = !empty($password) && !empty($zipBin);

    if ($useSystemZip) {
        // Use system zip
        // Command construction: "zip -j -P password output.zip file1 file2"
        $args = "";
        foreach ($filesToZip as $destName => $sourcePath) {
             // zip -j flattens, but we need to ensure right filename if source name differs
             // Here we assume source filename is what we want because $filesToZip keys match basename
             if (basename($sourcePath) !== $destName) {
                 $tmp = sys_get_temp_dir() . '/' . $destName;
                 copy($sourcePath, $tmp);
                 $sourcePath = $tmp;
             }
             $args .= " " . escapeshellarg($sourcePath);
        }
        
        $cmd = sprintf("'%s' -j -P %s %s %s", $zipBin, escapeshellarg($password), escapeshellarg($zipFile), $args);
        error_log("RDBackup running: $cmd");
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($zipFile) || filesize($zipFile) === 0) {
            error_log("RD Backup Zip failed: code $returnVar");
            if(file_exists($zipFile)) @unlink($zipFile);
            $useSystemZip = false; // Fallback
        }
    }

    if (!$useSystemZip) {
        // Fallback or No Password -> PHP ZipArchive
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            die(json_encode(['error' => 'Impossible de créer le zip']));
        }
        
        $filesAdded = 0;
        if (!empty($password)) {
             $encryptionMethod = defined('ZipArchive::EM_TRADITIONAL') ? ZipArchive::EM_TRADITIONAL : 1;
        }

        foreach ($filesToZip as $destName => $sourcePath) {
             $zip->addFile($sourcePath, $destName);
             $filesAdded++;
             if (!empty($password)) {
                 $zip->setEncryptionName($destName, $encryptionMethod);
             }
        }
        
        if ($filesAdded === 0) {
             $zip->close();
             @unlink($zipFile);
             die(json_encode(['error' => 'Aucune clé trouvée']));
        }
        
        if (!$zip->close()) {
            die(json_encode(['error' => 'Erreur fermeture ZIP']));
        }
    }

    if (file_exists($zipFile)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="rustdesk_keys_backup.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } else {
        die("Erreur création ZIP.");
    }
    }

} elseif ($action === 'upload_keys') {
    header('Content-Type: application/json');

    if (!isset($_FILES['key_files'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
        exit;
    }

    $count = 0;
    $errors = [];

    // Fonction Helper pour traiter un fichier valide
    $processValidFile = function($sourcePath, $fileName, $isMove = true) use ($persistenceDir, $rustdeskDir, &$count, &$errors) {
        if ($fileName !== 'id_ed25519' && $fileName !== 'id_ed25519.pub') {
            return;
        }
        $destPersistence = $persistenceDir . '/' . $fileName;
        
        $opResult = $isMove ? @move_uploaded_file($sourcePath, $destPersistence) : @copy($sourcePath, $destPersistence);
        
        if ($opResult) {
            @chmod($destPersistence, 0644);
            if (is_dir($rustdeskDir) && is_writable($rustdeskDir)) {
                 $destLive = $rustdeskDir . '/' . $fileName;
                 @copy($destPersistence, $destLive);
                 if ($fileName === 'id_ed25519') {
                     @chmod($destLive, 0600); @chmod($destPersistence, 0600);
                 } else {
                     @chmod($destLive, 0644);
                 }
            }
            $count++;
        } else {
             $errors[] = "Échec copie de $fileName";
        }
    };

    $password = $_POST['password'] ?? '';
    
    // Boucle sur les fichiers envoyés (supporte l'upload multiple)
    foreach ($_FILES['key_files']['name'] as $i => $name) {
        $tmpName = $_FILES['key_files']['tmp_name'][$i];
        $error = $_FILES['key_files']['error'][$i];

        if ($error === UPLOAD_ERR_OK) {
             $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
             
             // Si c'est un ZIP
             if ($ext === 'zip') {
                 $zip = new ZipArchive();
                 if ($zip->open($tmpName) === TRUE) {
                     if (!empty($password)) {
                         $zip->setPassword($password);
                     }
                     // Extract to temp folder
                     $extractPath = sys_get_temp_dir() . '/rd_restore_' . uniqid();
                     mkdir($extractPath);
                     
                     if ($zip->extractTo($extractPath)) {
                         $files = scandir($extractPath);
                         foreach ($files as $extractedFile) {
                             if ($extractedFile === '.' || $extractedFile === '..') continue;
                             $processValidFile($extractPath . '/' . $extractedFile, $extractedFile, false); // Copy, don't move
                         }
                     } else {
                         $errors[] = "Erreur extraction ZIP (Mot de passe incorrect ?)";
                     }
                     $zip->close();
                     // Cleanup temp dir
                     array_map('unlink', glob("$extractPath/*"));
                     rmdir($extractPath);
                 } else {
                     $errors[] = "ZIP invalide : $name";
                 }
             } else {
                 // Fichier normal
                 if ($name !== 'id_ed25519' && $name !== 'id_ed25519.pub') {
                     $errors[] = "$name ignoré (Nom invalide)";
                     continue;
                 }
                 $processValidFile($tmpName, $name, true); // Move
             }
        } else {
            $errors[] = "Erreur upload code $error";
        }
    }

    ob_end_clean(); // Clean buffer before JSON output
    if ($count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "$count fichier(s) importé(s) avec succès. Redémarrez Rustdesk pour appliquer.",
            'details' => $errors
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucun fichier valide importé.', 'details' => $errors]);
    }
    exit;
}

ob_end_clean();
echo json_encode(['error' => 'Action inconnue']);
