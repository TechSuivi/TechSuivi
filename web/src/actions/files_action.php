<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Inclure l'utilitaire de permissions
require_once __DIR__ . '/../utils/permissions_helper.php';

// Configuration des dossiers
$uploadsDir = __DIR__ . '/../uploads/';
$backupsDir = $uploadsDir . 'backups/';

// Créer le dossier de sauvegarde avec gestion d'erreurs améliorée
$result = createDirectoryWithPermissions($backupsDir, 0775, true);
if (!$result['success']) {
    $_SESSION['files_message'] = "⚠️ Problème de permissions : " . $result['message'];
    $_SESSION['files_message_type'] = 'warning';
}

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Fonction pour créer une archive ZIP récursivement
function createZipArchive($source, $destination, $basePath = '', $password = null, $excludes = []) {
    if (!extension_loaded('zip')) {
        throw new Exception('Extension ZIP non disponible');
    }
    
    // Nettoyer le chemin source pour éviter les soucis de trailing slash
    $source = realpath($source);
    if (!$source) {
        throw new Exception('Source invalide');
    }

    // Toujours essayer d'utiliser la commande système "zip" en priorité (plus robuste pour les chemins)
    $zipBinPath = shell_exec('which zip');
    $zipBin = $zipBinPath ? trim($zipBinPath) : '';
    
    if (!empty($zipBin)) {
        error_log("FilesBackup: System zip found at '$zipBin'");
        $currentDir = getcwd();
        
        // Construction des arguments mot de passe
        $pwdArg = !empty($password) ? "-P " . escapeshellarg($password) : "";
        
        // Construction des exclusions
        $excludeArg = "";
        if (!empty($excludes)) {
            foreach ($excludes as $ex) {
                // zip -x "pattern*"
                $excludeArg .= " -x " . escapeshellarg($ex . '*'); 
            }
        }
        
        if (empty($basePath)) {
            // Backup Full : on se place DANS source et on zippe tout (.)
            chdir($source);
            $cmd = sprintf("'%s' -r %s %s . %s", $zipBin, $pwdArg, escapeshellarg($destination), $excludeArg);
        } else {
            // Backup Folder : on se place dans le PARENT de source
            chdir(dirname($source));
            $folderToZip = basename($source);
            $cmd = sprintf("'%s' -r %s %s %s %s", $zipBin, $pwdArg, escapeshellarg($destination), escapeshellarg($folderToZip), $excludeArg);
        }
        
        error_log("Executing system zip: $cmd"); // Debug
        
        $output = [];
        $returnVar = -1;
        exec($cmd, $output, $returnVar);
        chdir($currentDir);
        
        if ($returnVar === 0 && file_exists($destination) && filesize($destination) > 0) {
             return true; // Succès complet via système
        } else {
             error_log("Zip command failed: $cmd | Return: $returnVar");
             // En cas d'échec, on nettoie et on laisse le fallback PHP essayer
             if (file_exists($destination)) {
                 @unlink($destination);
             }
        }
    }

    // Fallback : PHP ZipArchive (Moins robuste sur les chemins récursifs complexes mais standard)
    $zip = new ZipArchive();
    $flags = ZipArchive::CREATE;
    if (file_exists($destination)) {
        $flags |= ZipArchive::OVERWRITE;
    }

    if ($zip->open($destination, $flags) !== TRUE) {
        throw new Exception('Impossible de créer l\'archive ZIP (Erreur Open)');
    }
    
    if (!empty($password)) {
        $zip->setPassword($password);
    }
    
    $filesAdded = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        
        // Calcul du chemin relatif robuste
        $sourceLen = strlen($source);
        $relativePath = substr($filePath, $sourceLen);
        $relativePath = ltrim($relativePath, '/\\'); // Enlever le slash initial
        
        if ($basePath !== '') {
            $zipPath = $basePath . '/' . $relativePath;
        } else {
            $zipPath = $relativePath;
        }
        
        $zipPath = str_replace('\\', '/', $zipPath);
        
        // Gestion des exclusions (PHP fallback)
        $shouldExclude = false;
        if (!empty($excludes)) {
            foreach ($excludes as $ex) {
                // Vérification simple : si le chemin relatif commence par l'exclusion
                if (str_starts_with($zipPath, $ex)) {
                    $shouldExclude = true;
                    break;
                }
            }
        }
        if ($shouldExclude) continue;
        
        if ($file->isDir()) {
            $zip->addEmptyDir($zipPath);
        } elseif ($file->isFile()) {
            $zip->addFile($filePath, $zipPath);
            $filesAdded++;
            if (!empty($password) && defined('ZipArchive::EM_TRADITIONAL')) {
                $zip->setEncryptionName($zipPath, ZipArchive::EM_TRADITIONAL);
            }
        }
    }
    
    if ($filesAdded === 0) {
        $zip->close();
        @unlink($destination);
        throw new Exception("Aucun fichier à sauvegarder");
    }
    
    if (!$zip->close()) {
        throw new Exception("Erreur lors de la fermeture de l'archive ZIP");
    }
    
    return true;
}



// Fonction pour supprimer un dossier récursivement
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    // Vérifier les permissions
    if (!is_writable($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }
    
    return @rmdir($dir);
}

try {
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'download':
            $file = $_GET['file'] ?? '';
            $file = str_replace(['../', '..\\'], '', $file); // Sécurité
            $filePath = $uploadsDir . $file;
            
            if (!file_exists($filePath) || !is_file($filePath)) {
                throw new Exception('Fichier non trouvé');
            }
            
            // Vérifier que le fichier est dans le dossier uploads
            if (!str_starts_with(realpath($filePath), realpath($uploadsDir))) {
                throw new Exception('Accès non autorisé');
            }
            
            // Nettoyer les buffers de sortie
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Headers pour le téléchargement
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Expires: 0');
            
            // Envoyer le fichier
            readfile($filePath);
            exit();
            
        case 'delete':
            $file = $_GET['file'] ?? '';
            $file = str_replace(['../', '..\\'], '', $file); // Sécurité
            $filePath = $uploadsDir . $file;
            
            if (!file_exists($filePath) || !is_file($filePath)) {
                throw new Exception('Fichier non trouvé');
            }
            
            // Vérifier que le fichier est dans le dossier uploads
            if (!str_starts_with(realpath($filePath), realpath($uploadsDir))) {
                throw new Exception('Accès non autorisé');
            }
            
            // Vérifier les permissions avant de tenter la suppression
            if (!is_writable(dirname($filePath))) {
                throw new Exception('Permissions insuffisantes pour supprimer ce fichier');
            }
            
            if (@unlink($filePath)) {
                $_SESSION['files_message'] = "✅ Fichier supprimé avec succès : " . basename($filePath);
                $_SESSION['files_message_type'] = 'success';
            } else {
                throw new Exception('Impossible de supprimer le fichier. Vérifiez les permissions.');
            }
            
            // Rediriger vers le dossier parent
            $parentPath = dirname($file);
            $redirectPath = $parentPath === '.' ? '' : $parentPath;
            header('Location: ../index.php?page=files_manager&path=' . urlencode($redirectPath));
            exit();
            
        case 'delete_dir':
            $dir = $_GET['dir'] ?? '';
            $dir = str_replace(['../', '..\\'], '', $dir); // Sécurité
            $dirPath = $uploadsDir . $dir;
            
            if (!is_dir($dirPath)) {
                throw new Exception('Dossier non trouvé');
            }
            
            // Vérifier que le dossier est dans le dossier uploads
            if (!str_starts_with(realpath($dirPath), realpath($uploadsDir))) {
                throw new Exception('Accès non autorisé');
            }
            
            if (deleteDirectory($dirPath)) {
                $_SESSION['files_message'] = "✅ Dossier supprimé avec succès : " . basename($dirPath);
                $_SESSION['files_message_type'] = 'success';
            } else {
                throw new Exception('Impossible de supprimer le dossier');
            }
            
            // Rediriger vers le dossier parent
            $parentPath = dirname($dir);
            $redirectPath = $parentPath === '.' ? '' : $parentPath;
            header('Location: ../index.php?page=files_manager&path=' . urlencode($redirectPath));
            exit();

        case 'delete_batch':
            $files = $_POST['selected_files'] ?? [];
            $path = $_POST['path'] ?? '';
            $path = trim(str_replace(['../', '..\\'], '', $path), '/\\');
            
            $basePath = $uploadsDir . ($path ? $path . '/' : '');
            
            if (!is_dir($basePath) || !str_starts_with(realpath($basePath), realpath($uploadsDir))) {
                throw new Exception('Dossier cible invalide');
            }
            
            if (empty($files) || !is_array($files)) {
                throw new Exception('Aucun fichier sélectionné');
            }
            
            $deletedCount = 0;
            $errors = 0;
            
            foreach ($files as $name) {
                // Sécurité stricte sur le nom
                $name = basename($name);
                $targetPath = $basePath . $name;
                
                if (!file_exists($targetPath)) continue;
                
                // Vérification finale double check
                if (!str_starts_with(realpath($targetPath), realpath($uploadsDir))) continue;
                
                $currentSuccess = false;
                
                if (is_dir($targetPath)) {
                   if (deleteDirectory($targetPath)) $currentSuccess = true;
                } else {
                   if (@unlink($targetPath)) $currentSuccess = true;
                }
                
                if ($currentSuccess) $deletedCount++;
                else $errors++;
            }
            
            if ($errors > 0) {
                 $_SESSION['files_message'] = "⚠️ $deletedCount éléments supprimés, $errors erreurs.";
                 $_SESSION['files_message_type'] = 'warning';
            } else {
                 $_SESSION['files_message'] = "✅ $deletedCount éléments supprimés avec succès.";
                 $_SESSION['files_message_type'] = 'success';
            }
            
            header('Location: ../index.php?page=files_manager&path=' . urlencode($path));
            exit();
            
        case 'backup_full':
            set_time_limit(600); // 10 minutes
            ini_set('memory_limit', '1G');
            
            $outputMode = $_POST['output_mode'] ?? 'server';
            $excludeBackups = isset($_POST['exclude_backups']) && $_POST['exclude_backups'] == '1';
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFileName = "files_backup_full_{$timestamp}.zip";
            
            // Si téléchargement direct, on utilise un fichier temporaire
            if ($outputMode === 'download') {
                $tempDir = $uploadsDir . 'temp/';
                if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
                $backupPath = $tempDir . $backupFileName;
            } else {
                $backupPath = $backupsDir . $backupFileName;
            }
            
            $password = $_POST['backup_password'] ?? null;
            
            // Gestion des exclusions
            $excludes = [];
            if ($excludeBackups) {
                $excludes[] = 'backups/';
                // Exclure aussi le dossier temp si on est en train de créer le zip dedans
                $excludes[] = 'temp/'; 
            }
            
            createZipArchive($uploadsDir, $backupPath, '', $password, $excludes);
            
            if ($outputMode === 'download') {
                if (file_exists($backupPath)) {
                    // Headers pour le téléchargement
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
                    header('Content-Length: ' . filesize($backupPath));
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($backupPath);
                    
                    // Suppression après envoi
                    unlink($backupPath);
                    exit();
                } else {
                    throw new Exception("Erreur : Le fichier de sauvegarde n'a pas été créé.");
                }
            } else {
                $fileSize = formatFileSize(filesize($backupPath));
                $_SESSION['files_message'] = "✅ Sauvegarde complète créée avec succès : {$backupFileName} ({$fileSize})";
                $_SESSION['files_message_type'] = 'success';
                
                header('Location: ../index.php?page=files_manager');
                exit();
            }
            
        case 'backup_folder':
            set_time_limit(600); // 10 minutes
            ini_set('memory_limit', '1G');
            
            $folder = $_POST['folder'] ?? '';
            $folder = str_replace(['../', '..\\'], '', $folder); // Sécurité
            $folderPath = $uploadsDir . $folder;
            
            if (!is_dir($folderPath)) {
                throw new Exception('Dossier non trouvé');
            }
            
            // Vérifier que le dossier est dans le dossier uploads
            if (!str_starts_with(realpath($folderPath), realpath($uploadsDir))) {
                throw new Exception('Accès non autorisé');
            }
            
            $outputMode = $_POST['output_mode'] ?? 'server';
            
            $timestamp = date('Y-m-d_H-i-s');
            $folderName = str_replace(['/', '\\'], '_', $folder);
            $backupFileName = "files_backup_{$folderName}_{$timestamp}.zip";
            
            // Si téléchargement direct, on utilise un fichier temporaire
            if ($outputMode === 'download') {
                $tempDir = $uploadsDir . 'temp/';
                if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
                $backupPath = $tempDir . $backupFileName;
            } else {
                $backupPath = $backupsDir . $backupFileName;
            }
            
            $password = $_POST['backup_password'] ?? null;
            createZipArchive($folderPath, $backupPath, basename($folder), $password);
            
            if ($outputMode === 'download') {
                if (file_exists($backupPath)) {
                    // Headers pour le téléchargement
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
                    header('Content-Length: ' . filesize($backupPath));
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($backupPath);
                    
                    // Suppression après envoi
                    unlink($backupPath);
                    exit();
                } else {
                    throw new Exception("Erreur : Le fichier de sauvegarde n'a pas été créé.");
                }
            } else {
                $fileSize = formatFileSize(filesize($backupPath));
                $_SESSION['files_message'] = "✅ Sauvegarde du dossier créée avec succès : {$backupFileName} ({$fileSize})";
                $_SESSION['files_message_type'] = 'success';
                
                header('Location: ../index.php?page=files_manager&path=' . urlencode($folder));
                exit();
            }
            
        case 'create_folder':
            $folderName = trim($_POST['folder_name'] ?? '');
            $targetPath = $_POST['target_path'] ?? '';
            $targetPath = str_replace(['../', '..\\'], '', $targetPath);
            
            // Validation nom dossier
            if (empty($folderName) || preg_match('/[^a-zA-Z0-9_\-\.]/', $folderName)) {
                throw new Exception('Nom de dossier invalide (caractères alphanumériques, tirets et points uniquement)');
            }
            
            $basePath = $uploadsDir . ($targetPath ? $targetPath . '/' : '');
            $newDirPath = $basePath . $folderName;
            
            if (is_dir($newDirPath)) {
                throw new Exception('Ce dossier existe déjà');
            }
            
            // Vérification sécurité path
            if (!str_starts_with(realpath($basePath), realpath($uploadsDir))) {
                throw new Exception('Chemin non autorisé');
            }

            if (mkdir($newDirPath, 0775, true)) {
                 chmod($newDirPath, 0775);
                 $_SESSION['files_message'] = "✅ Dossier créé : " . $folderName;
                 $_SESSION['files_message_type'] = 'success';
            } else {
                 throw new Exception('Erreur lors de la création du dossier');
            }
            
            header('Location: ../index.php?page=files_manager&path=' . urlencode($targetPath));
            exit();

        case 'upload_file':
            if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload');
            }
            
            $targetPath = $_POST['target_path'] ?? '';
            $targetPath = str_replace(['../', '..\\'], '', $targetPath);
            $basePath = $uploadsDir . ($targetPath ? $targetPath . '/' : '');
            
             // Vérification sécurité path
            if (!is_dir($basePath) || !str_starts_with(realpath($basePath), realpath($uploadsDir))) {
                throw new Exception('Dossier cible invalide');
            }
            
            $fileName = basename($_FILES['upload_file']['name']);
            $targetFile = $basePath . $fileName;
            
            if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $targetFile)) {
                chmod($targetFile, 0644);
                $_SESSION['files_message'] = "✅ Fichier uploadé : " . $fileName;
                $_SESSION['files_message_type'] = 'success';
            } else {
                throw new Exception('Erreur lors de l\'enregistrement du fichier');
            }
            header('Location: ../index.php?page=files_manager&path=' . urlencode($targetPath));
            exit();

        case 'restore_zip':
            set_time_limit(600);
            ini_set('memory_limit', '1G');
            
            if (!isset($_FILES['restore_file']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload du fichier');
            }
            
            $zipFile = $_FILES['restore_file']['tmp_name'];
            
            // Récupérer le dossier cible
            $targetPath = $_POST['target_path'] ?? '';
            $targetPath = trim($targetPath, '/\\');
            $extractPath = $uploadsDir . ($targetPath ? $targetPath . '/' : '');
            
            // Correction double slash éventuel
            $extractPath = str_replace('//', '/', $extractPath);
            
            // Vérifier que le chemin cible existe et est dans uploads
            if (!is_dir($extractPath) || !str_starts_with(realpath($extractPath), realpath($uploadsDir))) {
                 $extractPath = $uploadsDir; // Fallback sur root
            }

            // Correction des permissions avant extraction
            if (is_dir($extractPath)) {
                @chmod($extractPath, 0775);
            }

            $password = $_POST['restore_password'] ?? null;
            $success = false;

            // Tentative avec la commande système 'unzip' si disponible (plus robuste)
            $zipBinPath = shell_exec('which unzip');
            $zipBin = $zipBinPath ? trim($zipBinPath) : '';
            
            if (!empty($zipBin)) {
                // Lister le contenu pour détecter la structure
                $listCmd = sprintf("'%s' -l %s", $zipBin, escapeshellarg($zipFile));
                exec($listCmd, $listOutput, $listReturn);
                
                $hasCommonDir = false;
                $commonDir = '';
                
                if ($listReturn === 0) {
                    // Analyse : est-ce que tous les fichiers commencent par "NomDossier/" ?
                    $firstFile = '';
                    foreach ($listOutput as $line) {
                        // Format unzip -l standard
                        if (preg_match('/^\s*\d+\s+[\d-]+\s+[\d:]+\s+(.+)$/', $line, $matches)) {
                            $name = trim($matches[1]);
                            if (str_ends_with($name, '/')) continue;
                            $firstFile = $name;
                            break;
                        }
                    }
                    
                    if ($firstFile) {
                        $parts = explode('/', $firstFile);
                        if (count($parts) > 1) {
                            $potentialCommonDir = $parts[0];
                            // Si le dossier commun correspond au dossier courant, on marque pour strip
                            $currentDirName = basename($extractPath);
                            if ($potentialCommonDir === $currentDirName) {
                                $commonDir = $potentialCommonDir;
                            }
                        }
                    }
                }

                $pwdArg = !empty($password) ? "-P " . escapeshellarg($password) : "";
                
                if (!empty($commonDir)) {
                    // Extraction avec structure intelligente
                    $tempExtractDir = $uploadsDir . 'temp_restore_' . uniqid();
                    mkdir($tempExtractDir);
                    
                    $cmd = sprintf("'%s' %s -o %s -d %s", $zipBin, $pwdArg, escapeshellarg($zipFile), escapeshellarg($tempExtractDir));
                    exec($cmd, $output, $returnVar);
                    
                    if ($returnVar === 0) {
                        // Si structure confirmée, on déplace le contenu
                        $sourceDir = $tempExtractDir . '/' . $commonDir;
                        if (is_dir($sourceDir)) {
                            $files = scandir($sourceDir);
                            foreach ($files as $file) {
                                if ($file === '.' || $file === '..') continue;
                                rename($sourceDir . '/' . $file, $extractPath . $file);
                            }
                        } else {
                             // Structure mixte, on déplace tout
                             $files = scandir($tempExtractDir);
                             foreach ($files as $file) {
                                 if ($file === '.' || $file === '..') continue;
                                 rename($tempExtractDir . '/' . $file, $extractPath . $file);
                             }
                        }
                        deleteDirectory($tempExtractDir); // Nettoyage
                        $success = true;
                    }
                } else {
                    // Extraction directe
                    $cmd = sprintf("'%s' %s -o %s -d %s", $zipBin, $pwdArg, escapeshellarg($zipFile), escapeshellarg($extractPath));
                    exec($cmd, $output, $returnVar);
                    if ($returnVar === 0) {
                        $success = true;
                    }
                }
            }

            // Fallback PHP ZipArchive
            if (!$success) {
                $zip = new ZipArchive();
                if ($zip->open($zipFile) !== TRUE) {
                    throw new Exception('Le fichier n\'est pas une archive ZIP valide');
                }
                
                if (!empty($password)) {
                    $zip->setPassword($password);
                }
                
                $currentDirName = basename($extractPath);
                $stripPrefix = $currentDirName . '/';
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $targetName = $filename;
                    
                    // Logic de strip
                    if (str_starts_with($filename, $stripPrefix)) {
                        $targetName = substr($filename, strlen($stripPrefix));
                    }
                    
                    if (empty($targetName) || str_ends_with($targetName, '/')) continue;
                    
                    // Extraction manuelle via Stream pour contrôler la destination
                    $destPath = $extractPath . $targetName;
                    
                    // TENTATIVE DE SUPPRESSION PREALABLE pour éviter les problèmes de droits
                    if (file_exists($destPath)) {
                        @unlink($destPath);
                    }
                    
                    $stream = $zip->getStream($filename);
                    if ($stream) {
                        $destDir = dirname($destPath);
                        if (!is_dir($destDir)) mkdir($destDir, 0775, true);
                        
                        $destStream = @fopen($destPath, 'wb');
                        if ($destStream) {
                            while (!feof($stream)) fwrite($destStream, fread($stream, 8192));
                            fclose($destStream);
                            fclose($stream);
                        } else {
                            // En cas d'échec fopen, on tente extractTo standard
                            $zip->extractTo($extractPath, $filename);
                        }
                    } else {
                        $zip->extractTo($extractPath, $filename);
                    }
                }
                $zip->close();
            }

            // Correction des permissions post-restauration
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        @chmod($item->getRealPath(), 0775);
                    } else {
                        @chmod($item->getRealPath(), 0644);
                    }
                }
                @chmod($extractPath, 0775);
            } catch (Exception $e) {
                error_log("Avertissement chmod post-restauration : " . $e->getMessage());
            }
            
            $msgPath = $targetPath ? "/uploads/" . $targetPath : "/uploads/ (Racine)";
            $_SESSION['files_message'] = "✅ Restauration effectuée avec succès dans : " . $msgPath;
            $_SESSION['files_message_type'] = 'success';
            
            header('Location: ../index.php?page=files_manager&path=' . urlencode($targetPath));
            exit();

        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    $_SESSION['files_message'] = "❌ Erreur : " . $e->getMessage();
    $_SESSION['files_message_type'] = 'error';
    
    // Redirection par défaut avec maintien du path
    $redirectPath = $_POST['target_path'] ?? $_POST['folder'] ?? $_GET['path'] ?? '';
    header('Location: ../index.php?page=files_manager&path=' . urlencode($redirectPath));
    exit();
}
?>