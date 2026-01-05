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
function createZipArchive($source, $destination, $basePath = '') {
    if (!extension_loaded('zip')) {
        throw new Exception('Extension ZIP non disponible');
    }
    
    // Nettoyer le chemin source pour éviter les soucis de trailing slash
    $source = realpath($source);
    if (!$source) {
        throw new Exception('Source invalide');
    }

    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Impossible de créer l\'archive ZIP');
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        
        // Calculer le chemin relatif par rapport à la source
        // strlen($source) + 1 pour sauter le slash séparateur après le nom du dossier source
        $relativePath = substr($filePath, strlen($source) + 1);
        
        // Ajouter le basePath si défini (pour créer un dossier racine dans le zip)
        if ($basePath !== '') {
            $zipPath = $basePath . '/' . $relativePath;
        } else {
            $zipPath = $relativePath;
        }
        
        // Normaliser les slashs pour le format ZIP (toujours /)
        $zipPath = str_replace('\\', '/', $zipPath);
        
        if ($file->isDir()) {
            $zip->addEmptyDir($zipPath);
        } elseif ($file->isFile()) {
            $zip->addFile($filePath, $zipPath);
        }
    }
    
    $zip->close();
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
            
            createZipArchive($uploadsDir, $backupPath, '');
            
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
            
            createZipArchive($folderPath, $backupPath, basename($folder));
            
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
            
            // Récupérer le chemin cible (optionnel)
            $targetPath = $_POST['target_path'] ?? '';
            $targetPath = str_replace(['../', '..\\'], '', $targetPath);
            $extractPath = $uploadsDir . $targetPath;
            
            // Vérifier que le chemin cible existe et est dans uploads
            if (!is_dir($extractPath) || !str_starts_with(realpath($extractPath), realpath($uploadsDir))) {
                 $extractPath = $uploadsDir; // Fallback sur root
            }

            // Validation basique (ZipArchive validera la structure)
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception('Le fichier n\'est pas une archive ZIP valide');
            }
            
            // Extraction
            // On extrait dans le dossier CIBLE
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                throw new Exception('Erreur lors de l\'extraction de l\'archive');
            }
            
            $zip->close();
            
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