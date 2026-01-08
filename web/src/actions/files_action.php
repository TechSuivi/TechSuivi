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
function createZipArchive($source, $destination, $basePath = '', $password = null) {
    if (!extension_loaded('zip')) {
        throw new Exception('Extension ZIP non disponible');
    }
    
    // Nettoyer le chemin source pour éviter les soucis de trailing slash
    $source = realpath($source);
    if (!$source) {
        throw new Exception('Source invalide');
    }

    // Si un mot de passe est défini, on utilise la commande système "zip" (si disponible)
    if (!empty($password)) {
        error_log("FilesBackup: Password received (len=" . strlen($password) . ")");
        $zipBinPath = shell_exec('which zip');
        $zipBin = $zipBinPath ? trim($zipBinPath) : '';
        error_log("FilesBackup: Zip binary found at '$zipBin'");
        
        if (!empty($zipBin)) {
            $currentDir = getcwd();
            
            if (empty($basePath)) {
                // Backup Full : on se place dans source et on zippe tout
                chdir($source);
                $cmd = sprintf("'%s' -r -P %s %s .", $zipBin, escapeshellarg($password), escapeshellarg($destination));
            } else {
                // Backup Folder : on se place dans le parent
                chdir(dirname($source));
                $folderToZip = basename($source);
                $cmd = sprintf("zip -r -P %s %s %s", escapeshellarg($password), escapeshellarg($destination), escapeshellarg($folderToZip));
            }
            
            error_log("Trying system zip: $cmd"); // Debug
            
            $output = [];
            $returnVar = -1;
            exec($cmd, $output, $returnVar);
            chdir($currentDir);
            
            if ($returnVar === 0 && file_exists($destination) && filesize($destination) > 0) {
                 return true; // Succès
            } else {
                 error_log("Zip command failed: $cmd | Return: $returnVar");
                 if (file_exists($destination)) {
                     @unlink($destination);
                 }
            }
        }
    }

    // Fallback : PHP ZipArchive
    $zip = new ZipArchive();
    $flags = ZipArchive::CREATE;
    if (file_exists($destination)) {
        $flags |= ZipArchive::OVERWRITE;
    }

    if ($zip->open($destination, $flags) !== TRUE) {
        throw new Exception('Impossible de créer l\'archive ZIP (Erreur Open)');
    }
    
    if (!empty($password)) {
        // Pour une compatibilité maximale en fallback, on utilise setPassword (global)
        $zip->setPassword($password);
    }
    
    $filesAdded = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($source) + 1);
        
        if ($basePath !== '') {
            $zipPath = $basePath . '/' . $relativePath;
        } else {
            $zipPath = $relativePath;
        }
        
        $zipPath = str_replace('\\', '/', $zipPath);
        
        if ($file->isDir()) {
            $zip->addEmptyDir($zipPath);
        } elseif ($file->isFile()) {
            $zip->addFile($filePath, $zipPath);
            $filesAdded++;
            // On tente d'appliquer le chiffrement par fichier AUSSI si possible, sinon setPassword suffit souvent pour EM_TRADITIONAL
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
            
        case 'backup_full':
            set_time_limit(600); // 10 minutes
            ini_set('memory_limit', '1G');
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFileName = "files_backup_full_{$timestamp}.zip";
            $backupPath = $backupsDir . $backupFileName;
            
            $password = $_POST['backup_password'] ?? null;
            createZipArchive($uploadsDir, $backupPath, '', $password);
            
            $fileSize = formatFileSize(filesize($backupPath));
            $_SESSION['files_message'] = "✅ Sauvegarde complète créée avec succès : {$backupFileName} ({$fileSize})";
            $_SESSION['files_message_type'] = 'success';
            
            header('Location: ../index.php?page=files_manager');
            exit();
            
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
            
            $timestamp = date('Y-m-d_H-i-s');
            $folderName = str_replace(['/', '\\'], '_', $folder);
            $backupFileName = "files_backup_{$folderName}_{$timestamp}.zip";
            $backupPath = $backupsDir . $backupFileName;
            
            $password = $_POST['backup_password'] ?? null;
            createZipArchive($folderPath, $backupPath, basename($folder), $password);
            
            $fileSize = formatFileSize(filesize($backupPath));
            $_SESSION['files_message'] = "✅ Sauvegarde du dossier créée avec succès : {$backupFileName} ({$fileSize})";
            $_SESSION['files_message_type'] = 'success';
            
            header('Location: ../index.php?page=files_manager&path=' . urlencode($folder));
            exit();
            
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
            $fileType = mime_content_type($zipFile);
            
            // Nettoyage du chemin cible
            $targetPath = trim($targetPath, '/\\');
            $extractPath = $uploadsDir . ($targetPath ? $targetPath . '/' : '');
            
            // Correction double slash éventuel
            $extractPath = str_replace('//', '/', $extractPath);
            
            // Vérifier que le chemin cible existe et est dans uploads
            if (!is_dir($extractPath) || !str_starts_with(realpath($extractPath), realpath($uploadsDir))) {
                 $extractPath = $uploadsDir; // Fallback sur root
            }

            // Tenter de corriger les permissions AVANT l'extraction
            if (is_dir($extractPath)) {
                @chmod($extractPath, 0775);
            }

            // Validation basique (ZipArchive validera la structure)
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception('Le fichier n\'est pas une archive ZIP valide');
            }
            
            $password = $_POST['restore_password'] ?? null;
            if (!empty($password)) {
                $zip->setPassword($password);
            }
            
            // Extraction
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                throw new Exception('Erreur lors de l\'extraction de l\'archive (Vérifiez les permissions ou si le dossier est verrouillé)');
            }
            $zip->close();

            // Correction des permissions post-restauration (Récursif)
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        chmod($item->getRealPath(), 0775);
                    } else {
                        chmod($item->getRealPath(), 0644);
                    }
                }
                // Appliquer aussi au dossier racine d'extraction
                chmod($extractPath, 0775);
            } catch (Exception $e) {
                // On continue même si le chmod échoue partiellement
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