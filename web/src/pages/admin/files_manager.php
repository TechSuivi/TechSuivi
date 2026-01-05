<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

$message = '';
$messageType = '';

// Récupérer les messages de session
if (isset($_SESSION['files_message'])) {
    $message = $_SESSION['files_message'];
    $messageType = $_SESSION['files_message_type'] ?? 'info';
    unset($_SESSION['files_message'], $_SESSION['files_message_type']);
}

// Configuration des dossiers
$uploadsDir = __DIR__ . '/../../uploads/';
$currentPath = $_GET['path'] ?? '';

// Sécurité : empêcher la navigation en dehors du dossier uploads
$currentPath = str_replace(['../', '..\\'], '', $currentPath);
$fullPath = $uploadsDir . $currentPath;

// Vérifier que le chemin est valide
if (!is_dir($fullPath) || !str_starts_with(realpath($fullPath), realpath($uploadsDir))) {
    $currentPath = '';
    $fullPath = $uploadsDir;
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

// Fonction pour obtenir l'icône selon le type de fichier
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📄',
        'doc' => '📝', 'docx' => '📝',
        'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📽️', 'pptx' => '📽️',
        'txt' => '📄',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️',
        'mp4' => '🎥', 'avi' => '🎥', 'mov' => '🎥',
        'mp3' => '🎵', 'wav' => '🎵',
        'zip' => '📦', 'rar' => '📦', '7z' => '📦',
        'sql' => '💾',
        'php' => '💻', 'html' => '💻', 'css' => '💻', 'js' => '💻',
    ];
    return $icons[$extension] ?? '📄';
}

// Scanner le dossier actuel
$items = [];
$totalSize = 0;
$fileCount = 0;
$dirCount = 0;

if (is_dir($fullPath)) {
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $fullPath . '/' . $file;
        $relativePath = $currentPath ? $currentPath . '/' . $file : $file;
        
        if (is_dir($filePath)) {
            $dirCount++;
            $items[] = [
                'name' => $file,
                'type' => 'directory',
                'size' => 0,
                'modified' => filemtime($filePath),
                'path' => $relativePath,
                'icon' => '📁'
            ];
        } else {
            $fileCount++;
            $size = filesize($filePath);
            $totalSize += $size;
            $items[] = [
                'name' => $file,
                'type' => 'file',
                'size' => $size,
                'modified' => filemtime($filePath),
                'path' => $relativePath,
                'icon' => getFileIcon($file)
            ];
        }
    }
}

// Trier les éléments (dossiers en premier, puis par nom)
usort($items, function($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'directory' ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

// Calculer les statistiques globales
function getDirectoryStats($dir) {
    $totalSize = 0;
    $fileCount = 0;
    $dirCount = 0;
    
    if (!is_dir($dir)) return ['size' => 0, 'files' => 0, 'dirs' => 0];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $totalSize += $file->getSize();
            $fileCount++;
        }
    }
    
    $dirIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($dirIterator as $file) {
        if ($file->isDir()) {
            $dirCount++;
        }
    }
    
    return ['size' => $totalSize, 'files' => $fileCount, 'dirs' => $dirCount];
}

$globalStats = getDirectoryStats($uploadsDir);
?>

<h1>📁 Gestionnaire de Fichiers Uploads</h1>

<?php if (!empty($message)): ?>
    <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; <?= $messageType === 'error' ? 'color: #721c24; border: 1px solid #f5c6cb; background-color: #f8d7da;' : ($messageType === 'success' ? 'color: #155724; border: 1px solid #c3e6cb; background-color: #d4edda;' : ($messageType === 'warning' ? 'color: #856404; border: 1px solid #ffeaa7; background-color: #fff3cd;' : 'color: #004085; border: 1px solid #b3d7ff; background-color: #cce7ff;')) ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<!-- Statistiques globales -->
<div style="background-color: #e3f2fd; border: 1px solid #2196f3; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #1976d2;">📊 Statistiques du Dossier Uploads</h3>
    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
        <div>
            <strong>📁 Dossiers :</strong> <?= number_format($globalStats['dirs']) ?>
        </div>
        <div>
            <strong>📄 Fichiers :</strong> <?= number_format($globalStats['files']) ?>
        </div>
        <div>
            <strong>💾 Taille totale :</strong> <?= formatFileSize($globalStats['size']) ?>
        </div>
        <div>
            <strong>📍 Dossier actuel :</strong> /uploads/<?= htmlspecialchars($currentPath) ?>
        </div>
    </div>
</div>

<!-- Interface principale -->
<div style="display: flex; gap: 20px; margin-bottom: 30px;">

<!-- Explorateur de fichiers -->
<div class="files-explorer bordered-section" style="flex: 2;">
    <h3 style="margin-top: 0;">📂 Explorateur de Fichiers</h3>
    
    <!-- Navigation & Actions -->
    <div class="files-navigation backup-section-content" style="margin-bottom: 15px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
        <!-- Partie Gauche : Chemin -->
        <div style="display: flex; flex-direction: column; gap: 5px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <strong>📍 Chemin :</strong>
                <span style="font-family: monospace; background-color: #e9ecef; padding: 2px 6px; border-radius: 3px;">
                    /uploads/<?= htmlspecialchars($currentPath) ?>
                </span>
            </div>
            <?php if (!empty($currentPath)): ?>
                <div>
                    <a href="index.php?page=files_manager&path=<?= urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)) ?>" 
                       style="background-color: #6c757d; color: white; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 12px; display: inline-flex; align-items: center;">
                        ⬆️ Dossier parent
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Partie Droite : Actions Compactes -->
        <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
            <!-- Nouveau Dossier -->
            <form method="post" action="actions/files_action.php" style="display: flex; gap: 2px; align-items: center;">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
                <input type="text" name="folder_name" placeholder="Nouveau dossier..." required style="padding: 4px; border: 1px solid #ced4da; border-radius: 3px 0 0 3px; font-size: 13px; width: 140px;">
                <button type="submit" class="backup-button backup-button-info" style="width: auto; margin: 0; padding: 4px 8px; border-radius: 0 3px 3px 0; font-size: 13px;" title="Créer dossier">➕</button>
            </form>

            <!-- Upload -->
            <form method="post" action="actions/files_action.php" enctype="multipart/form-data" style="width: 100%;">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
                
                <label for="file_upload" class="backup-button backup-button-success" style="width: 100%; box-sizing: border-box; margin: 0; padding: 4px 8px; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; text-align: center;">
                    ⬆️ Uploader un fichier
                </label>
                <input id="file_upload" type="file" name="upload_file" required style="display: none;" onchange="this.form.submit()">
            </form>
        </div>
    </div>
    
    <!-- Liste des fichiers -->
    <div class="files-table-container backup-section-content" style="max-height: 500px; overflow-y: auto;">
        <table class="files-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 10px; text-align: left;">Nom</th>
                    <th style="padding: 10px; text-align: right;">Taille</th>
                    <th style="padding: 10px; text-align: center;">Modifié</th>
                    <th style="padding: 10px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #6c757d; font-style: italic;">
                            📭 Dossier vide
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding: 8px;">
                                <?php if ($item['type'] === 'directory'): ?>
                                    <a href="index.php?page=files_manager&path=<?= urlencode($item['path']) ?>" 
                                       style="text-decoration: none; color: #007bff; font-weight: 500;">
                                        <?= $item['icon'] ?> <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span><?= $item['icon'] ?> <?= htmlspecialchars($item['name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 8px; text-align: right; font-family: monospace;">
                                <?= $item['type'] === 'directory' ? '-' : formatFileSize($item['size']) ?>
                            </td>
                            <td style="padding: 8px; text-align: center; font-size: 12px;">
                                <?= date('d/m/Y H:i', $item['modified']) ?>
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <?php if ($item['type'] === 'file'): ?>
                                    <a href="actions/files_action.php?action=download&file=<?= urlencode($item['path']) ?>" 
                                       style="background-color: #28a745; color: white; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 11px; margin-right: 5px;">
                                        📥 Télécharger
                                    </a>
                                    <a href="actions/files_action.php?action=delete&file=<?= urlencode($item['path']) ?>" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')"
                                       style="background-color: #dc3545; color: white; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 11px;">
                                        🗑️ Supprimer
                                    </a>
                                <?php else: ?>
                                    <a href="actions/files_action.php?action=delete_dir&dir=<?= urlencode($item['path']) ?>" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier et tout son contenu ?')"
                                       style="background-color: #dc3545; color: white; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 11px;">
                                        🗑️ Supprimer
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Statistiques du dossier actuel -->
    <div class="files-stats backup-section-content" style="margin-top: 15px; font-size: 13px;">
        <strong>📊 Dossier actuel :</strong> 
        <?= $dirCount ?> dossier(s), <?= $fileCount ?> fichier(s), <?= formatFileSize($totalSize) ?>
    </div>
</div>


<!-- Actions de sauvegarde -->
<div class="files-backup-section bordered-section">
    <h3>💾 Sauvegarde des Fichiers</h3>
    
    <!-- Sauvegarde complète -->
    <div class="backup-section-content">
        <h4>📦 Sauvegarde Complète</h4>
        <p class="backup-description">
            Créer une archive ZIP de tout le dossier uploads
        </p>
        <form method="post" action="actions/files_action.php">
            <input type="hidden" name="action" value="backup_full">
            <button type="submit" class="backup-button backup-button-success">
                📦 Créer Archive Complète
            </button>
        </form>
    </div>
    
    <!-- Sauvegarde du dossier actuel -->
    <?php if (!empty($currentPath)): ?>
    <div class="backup-section-content backup-section-info">
        <h4>📁 Sauvegarde Dossier Actuel</h4>
        <p class="backup-description">
            Sauvegarder uniquement : <?= htmlspecialchars($currentPath) ?>
        </p>
        <form method="post" action="actions/files_action.php">
            <input type="hidden" name="action" value="backup_folder">
            <input type="hidden" name="folder" value="<?= htmlspecialchars($currentPath) ?>">
            <button type="submit" class="backup-button backup-button-info">
                📁 Sauvegarder ce Dossier
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Restauration -->
    <div class="backup-section-content backup-section-warning">
        <h4>📤 Restauration d'Archive</h4>
        <p class="backup-description">
            Uploadez une archive ZIP pour restaurer des fichiers.
            Les fichiers seront extraits dans le dossier actuel : <strong>/uploads/<?= htmlspecialchars($currentPath) ?></strong>
        </p>
        <form method="post" action="actions/files_action.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="restore_zip">
            <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
            <div style="margin-bottom: 10px;">
                <input type="file" name="restore_file" accept=".zip" required style="width: 100%;">
            </div>
            <button type="submit" class="backup-button backup-button-warning" onclick="return confirm('⚠️ Attention : Cela écrasera les fichiers existants s\'ils portent le même nom.\nÊtes-vous sûr de vouloir continuer ?')">
                📤 Restaurer ICI
            </button>
        </form>
    </div>

    <!-- Historique des sauvegardes -->
    <div class="backup-section-content">
        <h4>📋 Sauvegardes Récentes</h4>
        <?php
        $backupDir = __DIR__ . '/../../uploads/backups/';
        $backups = [];
        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            foreach ($files as $file) {
                if (strpos($file, 'files_backup_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                    $backups[] = [
                        'name' => $file,
                        'size' => filesize($backupDir . $file),
                        'date' => filemtime($backupDir . $file)
                    ];
                }
            }
            usort($backups, function($a, $b) { return $b['date'] - $a['date']; });
            $backups = array_slice($backups, 0, 5); // 5 plus récentes
        }
        ?>
        
        <?php if (empty($backups)): ?>
            <p class="backup-empty-message">Aucune sauvegarde récente</p>
        <?php else: ?>
            <div class="backup-history-list">
                <?php foreach ($backups as $backup): ?>
                    <div class="backup-history-item">
                        <div class="backup-info">
                            <strong><?= htmlspecialchars($backup['name']) ?></strong><br>
                            <span class="backup-details"><?= formatFileSize($backup['size']) ?> - <?= date('d/m/Y H:i', $backup['date']) ?></span>
                        </div>
                        <a href="actions/files_action.php?action=download&file=backups/<?= urlencode($backup['name']) ?>"
                           class="backup-download-btn">
                             📥
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<p style="margin-top: 30px;">
    <a href="index.php?page=settings&tab=sauvegarde">← Retour aux paramètres</a>
</p>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation pour les actions de suppression
    const deleteLinks = document.querySelectorAll('a[href*="action=delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });
});
</script>