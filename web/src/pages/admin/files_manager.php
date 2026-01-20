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

<div class="page-header">
    <h1>📁 Gestionnaire de Fichiers Uploads</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType === 'error' ? 'error' : ($messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info')) ?> mb-20">
        <?= $message ?>
    </div>
<?php endif; ?>

<!-- Statistiques globales -->
<div class="card border-left-blue mb-20">
    <h3 class="card-title text-info mb-15">📊 Statistiques du Dossier Uploads</h3>
    <div class="grid-4">
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
<div class="grid-2 dashboard-grid mb-30" style="grid-template-columns: 2fr 1fr;">

    <!-- Explorateur de fichiers -->
    <div class="card h-full">
        <h3 class="card-title mb-15 pb-10 border-b">
            📂 Explorateur de Fichiers
        </h3>
        
        <!-- Navigation & Actions -->
        <div class="flex flex-wrap justify-between items-center gap-10 p-10 mb-15 border rounded-4 bg-secondary">
            <!-- Partie Gauche : Chemin -->
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-10">
                    <strong>📍 Chemin :</strong>
                    <span class="bg-light px-5 py-2 rounded-4 border font-monospace">
                        /uploads/<?= htmlspecialchars($currentPath) ?>
                    </span>
                </div>
                <?php if (!empty($currentPath)): ?>
                    <div>
                        <a href="index.php?page=files_manager&path=<?= urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)) ?>" 
                           class="btn btn-sm-action">
                            ⬆️ Dossier parent
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Partie Droite : Actions Compactes -->
            <div class="flex flex-col gap-5 items-end">
                <!-- Nouveau Dossier -->
                <form method="post" action="actions/files_action.php" class="flex items-center">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
                    <input type="text" name="folder_name" placeholder="Nouveau dossier..." required class="form-control text-sm py-5 rounded-l-4 border-r-0 w-32">
                    <button type="submit" class="btn btn-info rounded-l-0 py-5 px-10 text-sm" title="Créer dossier">➕</button>
                </form>

                <!-- Upload -->
                <form method="post" action="actions/files_action.php" enctype="multipart/form-data" class="w-full">
                    <input type="hidden" name="action" value="upload_file">
                    <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
                    
                    <label for="file_upload" class="btn btn-success w-full py-5 text-sm cursor-pointer flex justify-center gap-5">
                        ⬆️ Uploader un fichier
                    </label>
                    <input id="file_upload" type="file" name="upload_file" required style="display: none;" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        
        <!-- Liste des fichiers -->
        <div class="border rounded-4" style="max-height: 500px; overflow-y: auto;">
            <form id="filesForm" method="post" action="actions/files_action.php">
                <input type="hidden" name="action" value="delete_batch">
                <input type="hidden" name="path" value="<?= htmlspecialchars($currentPath) ?>">
                
                <!-- Barre d'actions groupées -->
                <div id="batchActions" class="p-10 bg-soft-red border-b border-danger flex items-center gap-15 sticky top-0 z-10 hidden">
                    <span class="font-bold text-sm text-danger">Actions :</span>
                    <button type="submit" onclick="return confirm('Attention : Vous êtes sur le point de supprimer définitivement les éléments sélectionnés.\nConfirmer ?')" 
                            class="btn btn-danger py-5 px-10 text-xs flex items-center gap-5">
                        🗑️ Supprimer (<span id="selectedCount">0</span>)
                    </button>
                </div>

                <table class="table w-full text-sm m-0">
                    <thead>
                        <tr>
                            <th class="p-10 w-10 text-center">
                                <input type="checkbox" id="selectAll" title="Tout sélectionner" class="cursor-pointer">
                            </th>
                            <th class="p-10 text-left">Nom</th>
                            <th class="p-10 text-right w-24">Taille</th>
                            <th class="p-10 text-center w-36">Modifié</th>
                            <th class="p-10 text-center w-40">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" class="p-30 text-center italic text-muted">
                                    📭 Ce dossier est vide
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-secondary">
                                    <td class="p-10 text-center">
                                        <input type="checkbox" name="selected_files[]" value="<?= htmlspecialchars($item['name']) ?>" class="file-checkbox cursor-pointer">
                                    </td>
                                    <td class="p-10">
                                        <?php if ($item['type'] === 'directory'): ?>
                                            <a href="index.php?page=files_manager&path=<?= urlencode($item['path']) ?>" 
                                               class="font-bold no-underline text-color flex items-center gap-10">
                                                <span class="text-lg">📁</span> <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="flex items-center gap-10">
                                                <?= $item['icon'] ?> <?= htmlspecialchars($item['name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-10 text-right font-monospace">
                                        <?= $item['type'] === 'directory' ? '-' : formatFileSize($item['size']) ?>
                                    </td>
                                    <td class="p-10 text-center text-xs">
                                        <?= date('d/m/Y H:i', $item['modified']) ?>
                                    </td>
                                    <td class="p-10 text-center">
                                        <div class="flex justify-center gap-5">
                                            <?php if ($item['type'] === 'file'): ?>
                                                <a href="actions/files_action.php?action=download&file=<?= urlencode($item['path']) ?>" 
                                                   class="btn btn-success py-5 px-10 text-xs" title="Télécharger">
                                                    📥
                                                </a>
                                                <a href="actions/files_action.php?action=delete&file=<?= urlencode($item['path']) ?>" 
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')"
                                                   class="btn btn-danger py-5 px-10 text-xs" title="Supprimer">
                                                    🗑️
                                                </a>
                                            <?php else: ?>
                                                <a href="actions/files_action.php?action=delete_dir&dir=<?= urlencode($item['path']) ?>" 
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier et tout son contenu ?')"
                                                   class="btn btn-danger py-5 px-10 text-xs" title="Supprimer">
                                                    🗑️
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <!-- Statistiques du dossier actuel -->
        <div class="mt-10 pt-5 text-right text-xs text-muted border-t">
            <strong>Contenu :</strong> 
            <?= $dirCount ?> dossier(s), <?= $fileCount ?> fichier(s) | 
            <strong>Total :</strong> <?= formatFileSize($totalSize) ?>
        </div>
    </div>

    <!-- Actions de sauvegarde -->
    <div class="card h-full">
        <h3 class="card-title mb-15">💾 Sauvegarde des Fichiers</h3>
        
        <!-- Sauvegarde complète -->
        <div class="mb-20">
            <h4 class="mt-0 mb-10 text-success">📦 Sauvegarde Complète</h4>
            <p class="text-sm text-muted mb-10">
                Créer une archive ZIP de tout le dossier uploads.
            </p>
            <form method="post" action="actions/files_action.php">
                <input type="hidden" name="action" value="backup_full">
                
                <div class="mb-15">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="exclude_backups" value="1" checked class="mr-10">
                        Exclure le dossier 'backups' (Recommandé)
                    </label>
                    <div class="text-xs text-muted ml-20 mt-5">
                        Évite que la sauvegarde contienne les anciennes sauvegardes.
                    </div>
                </div>

                <div class="flex gap-10 flex-wrap">
                    <button type="submit" name="output_mode" value="server" class="btn btn-success flex-1">
                        💾 Serveur
                    </button>
                    <button type="submit" name="output_mode" value="download" class="btn btn-info flex-1">
                        📥 Télécharger
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Sauvegarde du dossier actuel -->
        <?php if (!empty($currentPath)): ?>
        <div class="mb-20 p-15 bg-secondary rounded-4 border">
            <h4 class="mt-0 mb-10 text-info">📁 Sauvegarde Dossier Actuel</h4>
            <p class="text-xs text-muted mb-10">
                Sauvegarder uniquement : <?= htmlspecialchars($currentPath) ?>
            </p>
            <form method="post" action="actions/files_action.php">
                <input type="hidden" name="action" value="backup_folder">
                <input type="hidden" name="folder" value="<?= htmlspecialchars($currentPath) ?>">
                
                <div class="flex gap-10 flex-wrap">
                    <button type="submit" name="output_mode" value="server" class="btn btn-info flex-1">
                        💾 Serveur
                    </button>
                    <button type="submit" name="output_mode" value="download" class="btn btn-primary flex-1">
                        📥 Télécharger
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Restauration -->
        <div class="mb-20 p-15 bg-soft-red rounded-4 border border-danger">
            <h4 class="mt-0 mb-10 text-danger">📤 Restauration d'Archive</h4>
            <p class="text-xs text-danger mb-10">
                Uploadez une archive ZIP pour restaurer des fichiers dans : <strong>/uploads/<?= htmlspecialchars($currentPath) ?></strong>
            </p>
            <form method="post" action="actions/files_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="restore_zip">
                <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentPath) ?>">
                <div class="mb-10">
                    <input type="file" name="restore_file" accept=".zip" required class="w-full">
                </div>
                <button type="submit" class="btn btn-warning w-full" onclick="return confirm('⚠️ Attention : Cela écrasera les fichiers existants s\'ils portent le même nom.\nÊtes-vous sûr de vouloir continuer ?')">
                    📤 Restaurer ICI
                </button>
            </form>
        </div>

        <!-- Historique des sauvegardes -->
        <div>
            <h4 class="mt-0 mb-10 text-muted">📋 Sauvegardes Récentes</h4>
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
                <p class="text-muted italic text-center">Aucune sauvegarde récente</p>
            <?php else: ?>
                <div class="flex flex-col gap-5">
                    <?php foreach ($backups as $backup): ?>
                        <div class="flex justify-between items-center p-5 border rounded-4 text-xs">
                            <div class="overflow-hidden truncate mr-5">
                                <strong><?= htmlspecialchars($backup['name']) ?></strong><br>
                                <span class="text-muted"><?= formatFileSize($backup['size']) ?> - <?= date('d/m/Y H:i', $backup['date']) ?></span>
                            </div>
                            <a href="actions/files_action.php?action=download&file=backups/<?= urlencode($backup['name']) ?>"
                               class="btn btn-success py-5 px-10" title="Télécharger">
                                 📥
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<p class="mt-30">
    <a href="index.php?page=settings&tab=sauvegarde" class="btn btn-sm-action">← Retour aux paramètres</a>
</p>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation pour les actions de suppression individuelles
    const deleteLinks = document.querySelectorAll('a[href*="action=delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });

    // Gestion de la sélection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    const batchActions = document.getElementById('batchActions');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateBatchActions() {
        const selectedCount = document.querySelectorAll('.file-checkbox:checked').length;
        selectedCountSpan.textContent = selectedCount;
        
        if (selectedCount > 0) {
            batchActions.classList.remove('hidden');
            batchActions.classList.add('flex');
            batchActions.style.display = 'flex'; // Ensure display flex overrides class hidden if redundant
        } else {
            batchActions.classList.add('hidden');
            batchActions.classList.remove('flex');
            batchActions.style.display = 'none';
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            fileCheckboxes.forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
            updateBatchActions();
        });
    }

    fileCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateBatchActions();
            // Si une case est décochée, on décoche "Tout sélectionner"
            if (!cb.checked) {
                selectAllCheckbox.checked = false;
            }
            // Si toutes sont cochées, on coche "Tout sélectionner"
            if (document.querySelectorAll('.file-checkbox:checked').length === fileCheckboxes.length) {
                selectAllCheckbox.checked = true;
            }
        });
    });
});
</script>