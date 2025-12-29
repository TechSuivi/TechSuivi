<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Inclure les fonctions utilitaires pour les permissions
require_once __DIR__ . '/../../utils/permissions_helper.php';

// Définir le répertoire de base des installeurs
$baseInstallDir = 'Download/Install/';
$fullBasePath = __DIR__ . '/../../' . $baseInstallDir;

// Gérer la navigation dans les sous-dossiers
$currentPath = $_GET['path'] ?? '';
$currentPath = trim($currentPath, '/'); // Nettoyer le chemin

// Sécurité : empêcher la navigation en dehors du répertoire de base
if (strpos($currentPath, '..') !== false || strpos($currentPath, '\\') !== false) {
    $currentPath = '';
}

$currentFullPath = $fullBasePath . $currentPath;
$currentRelativePath = $baseInstallDir . $currentPath;

// Créer le répertoire de base s'il n'existe pas
if (!is_dir($fullBasePath)) {
    if (!mkdir($fullBasePath, 0755, true)) {
        $error = "Impossible de créer le répertoire " . $baseInstallDir;
    } else {
        // S'assurer que les permissions sont correctes
        chmod($fullBasePath, 0755);
    }
}

// Créer le répertoire courant s'il n'existe pas
if (!is_dir($currentFullPath)) {
    if (!mkdir($currentFullPath, 0755, true)) {
        $error = "Impossible de créer le répertoire " . $currentRelativePath;
    } else {
        // S'assurer que les permissions sont correctes
        chmod($currentFullPath, 0755);
    }
}

// Vérifier et corriger les permissions si nécessaire
if (is_dir($currentFullPath) && !is_writable($currentFullPath)) {
    // Essayer de corriger les permissions
    @chmod($currentFullPath, 0755);
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_file':
                if (isset($_POST['filename']) && isset($_POST['file_content'])) {
                    $fileName = basename($_POST['filename']);
                    $filePath = $currentFullPath . '/' . $fileName;
                    
                    // Vérifier que c'est un fichier texte éditable
                    $editableExtensions = ['txt', 'ini', 'cfg', 'conf', 'log', 'xml', 'json', 'md', 'yml', 'yaml'];
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    if (in_array($extension, $editableExtensions)) {
                        // Vérifier les permissions du répertoire
                        if (!is_writable($currentFullPath)) {
                            // Essayer de corriger les permissions
                            if (!chmod($currentFullPath, 0755)) {
                                $error = "Permissions insuffisantes pour écrire dans le répertoire. Veuillez exécuter : chmod 755 " . $currentRelativePath;
                                break;
                            }
                        }
                        
                        // Vérifier si le fichier existe et ses permissions
                        if (file_exists($filePath) && !is_writable($filePath)) {
                            // Essayer de corriger les permissions du fichier
                            if (!chmod($filePath, 0644)) {
                                $error = "Permissions insuffisantes pour modifier le fichier. Veuillez exécuter : chmod 644 " . $currentRelativePath . '/' . $fileName;
                                break;
                            }
                        }
                        
                        // Essayer de sauvegarder le fichier
                        $result = file_put_contents($filePath, $_POST['file_content'], LOCK_EX);
                        if ($result !== false) {
                            // Définir les bonnes permissions sur le fichier créé/modifié
                            chmod($filePath, 0644);
                            $success = "Fichier sauvegardé avec succès : " . $fileName . " (" . $result . " octets écrits)";
                        } else {
                            $lastError = error_get_last();
                            $error = "Erreur lors de la sauvegarde du fichier : " . ($lastError ? $lastError['message'] : 'Erreur inconnue');
                        }
                    } else {
                        $error = "Ce type de fichier ne peut pas être édité.";
                    }
                }
                break;
                
            case 'upload':
                if (isset($_FILES['fichier_installeur']) && $_FILES['fichier_installeur']['error'] === UPLOAD_ERR_OK) {
                    $fileName = basename($_FILES['fichier_installeur']['name']);
                    $uploadPath = $currentFullPath . '/' . $fileName;
                    
                    if (move_uploaded_file($_FILES['fichier_installeur']['tmp_name'], $uploadPath)) {
                        $success = "Fichier installeur uploadé avec succès : " . $fileName;
                    } else {
                        $error = "Erreur lors de l'upload du fichier installeur.";
                    }
                } else {
                    $error = "Aucun fichier sélectionné ou erreur d'upload.";
                }
                break;
                
            case 'delete':
                if (isset($_POST['filename'])) {
                    $filePath = $currentFullPath . '/' . basename($_POST['filename']);
                    if (file_exists($filePath) && unlink($filePath)) {
                        $success = "Fichier supprimé avec succès : " . $_POST['filename'];
                    } else {
                        $error = "Erreur lors de la suppression du fichier.";
                    }
                }
                break;
                
            case 'delete_folder':
                if (isset($_POST['foldername'])) {
                    $folderPath = $currentFullPath . '/' . basename($_POST['foldername']);
                    if (is_dir($folderPath) && rmdir($folderPath)) {
                        $success = "Dossier supprimé avec succès : " . $_POST['foldername'];
                    } else {
                        $error = "Erreur lors de la suppression du dossier (vérifiez qu'il soit vide).";
                    }
                }
                break;
                
            case 'rename':
                if (isset($_POST['old_filename']) && isset($_POST['new_filename'])) {
                    $oldPath = $currentFullPath . '/' . basename($_POST['old_filename']);
                    $newPath = $currentFullPath . '/' . basename($_POST['new_filename']);
                    
                    if (file_exists($oldPath) && !file_exists($newPath)) {
                        if (rename($oldPath, $newPath)) {
                            $success = "Fichier renommé avec succès.";
                        } else {
                            $error = "Erreur lors du renommage du fichier.";
                        }
                    } else {
                        $error = "Fichier source inexistant ou fichier de destination déjà existant.";
                    }
                }
                break;
                
            case 'create_folder':
                if (isset($_POST['folder_name']) && !empty(trim($_POST['folder_name']))) {
                    $folderName = basename(trim($_POST['folder_name']));
                    $newFolderPath = $currentFullPath . '/' . $folderName;
                    
                    if (!file_exists($newFolderPath)) {
                        if (mkdir($newFolderPath, 0755)) {
                            $success = "Dossier créé avec succès : " . $folderName;
                        } else {
                            $error = "Erreur lors de la création du dossier.";
                        }
                    } else {
                        $error = "Un fichier ou dossier avec ce nom existe déjà.";
                    }
                }
                break;
        }
    }
}

// Récupération de la liste des fichiers et dossiers
$items = [];
if (is_dir($currentFullPath)) {
    $itemList = scandir($currentFullPath);
    foreach ($itemList as $item) {
        if ($item !== '.' && $item !== '..') {
            $itemPath = $currentFullPath . '/' . $item;
            $isDir = is_dir($itemPath);
            
            $items[] = [
                'name' => $item,
                'is_directory' => $isDir,
                'size' => $isDir ? 0 : filesize($itemPath),
                'modified' => filemtime($itemPath),
                'extension' => $isDir ? '' : pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Trier : dossiers d'abord, puis fichiers par nom
    usort($items, function($a, $b) {
        if ($a['is_directory'] && !$b['is_directory']) return -1;
        if (!$a['is_directory'] && $b['is_directory']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
}

// Générer le fil d'Ariane (breadcrumb)
function generateBreadcrumb($currentPath) {
    $breadcrumb = [];
    $breadcrumb[] = ['name' => 'Install', 'path' => ''];
    
    if (!empty($currentPath)) {
        $pathParts = explode('/', $currentPath);
        $buildPath = '';
        
        foreach ($pathParts as $part) {
            $buildPath .= ($buildPath ? '/' : '') . $part;
            $breadcrumb[] = ['name' => $part, 'path' => $buildPath];
        }
    }
    
    return $breadcrumb;
}

$breadcrumb = generateBreadcrumb($currentPath);

// Récupération d'un fichier pour renommage
$renameFile = null;
if (isset($_GET['rename'])) {
    $renameFile = basename($_GET['rename']);
}

// Récupération d'un fichier pour édition
$editFile = null;
$editFileContent = '';
if (isset($_GET['edit'])) {
    $editFile = basename($_GET['edit']);
    $editFilePath = $currentFullPath . '/' . $editFile;
    
    // Vérifier que c'est un fichier texte éditable
    $editableExtensions = ['txt', 'ini', 'cfg', 'conf', 'log', 'xml', 'json', 'md', 'yml', 'yaml', 'au3', 'php', 'js', 'css', 'html'];
    $extension = strtolower(pathinfo($editFile, PATHINFO_EXTENSION));
    
    if (file_exists($editFilePath) && in_array($extension, $editableExtensions)) {
        $editFileContent = file_get_contents($editFilePath);
        if ($editFileContent === false) {
            $error = "Impossible de lire le contenu du fichier.";
            $editFile = null;
        }
    } else {
        $error = "Ce fichier ne peut pas être édité ou n'existe pas.";
        $editFile = null;
    }
}

// Fonction pour vérifier si un fichier est éditable
function isEditableFile($filename) {
    $editableExtensions = ['txt', 'ini', 'cfg', 'conf', 'log', 'xml', 'json', 'md', 'yml', 'yaml', 'au3', 'php', 'js', 'css', 'html'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $editableExtensions);
}
?>

<h1>Gestion des Installeurs</h1>
<p class="description">Explorez et gérez les fichiers d'installation dans le répertoire <code><?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code></p>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Fil d'Ariane -->
<div class="breadcrumb-container">
    <nav class="breadcrumb">
        <?php foreach ($breadcrumb as $index => $crumb): ?>
            <?php if ($index === count($breadcrumb) - 1): ?>
                <span class="breadcrumb-current">📁 <?= htmlspecialchars($crumb['name']) ?></span>
            <?php else: ?>
                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($crumb['path']) ?>" class="breadcrumb-link">
                    📁 <?= htmlspecialchars($crumb['name']) ?>
                </a>
                <span class="breadcrumb-separator">›</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Actions rapides -->
<div class="quick-actions">
    <button onclick="toggleCreateFolder()" class="btn btn-success">📁 Nouveau Dossier</button>
    <button onclick="toggleUploadForm()" class="btn btn-primary">📤 Uploader Fichier</button>
    <?php if (!empty($currentPath)): ?>
        <a href="index.php?page=autoit_installeur_list&path=<?= urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)) ?>" class="btn btn-secondary">⬆️ Dossier Parent</a>
    <?php endif; ?>
    <button onclick="togglePermissionsHelp()" class="btn btn-info">🔧 Aide Permissions</button>
</div>

<!-- Aide pour les permissions (masquée par défaut) -->
<div id="permissions-help" class="form-container" style="display: none;">
    <h3>🔧 Résolution des problèmes de permissions</h3>
    <div class="permissions-info">
        <p><strong>Si vous rencontrez des erreurs de permissions :</strong></p>
        <div class="command-box">
            <h4>Commandes à exécuter sur le serveur :</h4>
            <code>chmod -R 755 <?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code><br>
            <code>chown -R www-data:www-data <?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code>
        </div>
        <div class="permissions-status">
            <h4>État actuel des permissions :</h4>
            <ul>
                <li>📁 Répertoire : <?= is_writable($currentFullPath) ? '✅ Écriture autorisée' : '❌ Écriture refusée' ?></li>
                <li>🔍 Chemin complet : <code><?= htmlspecialchars($currentFullPath) ?></code></li>
                <li>👤 Propriétaire : <?= function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($currentFullPath))['name'] ?? 'Inconnu' : 'Non disponible' ?></li>
                <li>🔐 Permissions : <?= substr(sprintf('%o', fileperms($currentFullPath)), -4) ?></li>
            </ul>
        </div>
    </div>
    <div class="form-actions">
        <button type="button" onclick="togglePermissionsHelp()" class="btn btn-secondary">Fermer</button>
    </div>
</div>

<!-- Formulaire de création de dossier (masqué par défaut) -->
<div id="create-folder-form" class="form-container" style="display: none;">
    <h3>Créer un nouveau dossier</h3>
    <form method="POST" class="installeur-form">
        <input type="hidden" name="action" value="create_folder">
        
        <div class="form-group">
            <label for="folder_name">Nom du dossier :</label>
            <input type="text" id="folder_name" name="folder_name" required placeholder="Nom du nouveau dossier">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">📁 Créer</button>
            <button type="button" onclick="toggleCreateFolder()" class="btn btn-secondary">Annuler</button>
        </div>
    </form>
</div>

<!-- Formulaire d'upload (masqué par défaut) -->
<div id="upload-form" class="form-container" style="display: none;">
    <h3>Uploader un fichier</h3>
    <form method="POST" enctype="multipart/form-data" class="installeur-form">
        <input type="hidden" name="action" value="upload">
        
        <div class="form-group">
            <label for="fichier_installeur">Sélectionner un fichier :</label>
            <input type="file" id="fichier_installeur" name="fichier_installeur" required>
            <small class="form-help">Tous les formats de fichiers sont acceptés</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">📤 Uploader</button>
            <button type="button" onclick="toggleUploadForm()" class="btn btn-secondary">Annuler</button>
        </div>
    </form>
</div>

<!-- Formulaire de renommage (si applicable) -->
<?php if ($renameFile): ?>
<div class="form-container">
    <h3>Renommer le fichier</h3>
    <form method="POST" class="installeur-form">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="old_filename" value="<?= htmlspecialchars($renameFile) ?>">
        
        <div class="form-group">
            <label for="new_filename">Nouveau nom :</label>
            <input type="text" id="new_filename" name="new_filename" required value="<?= htmlspecialchars($renameFile) ?>">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">✏️ Renommer</button>
            <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Éditeur de fichier texte (si applicable) -->
<?php if ($editFile): ?>
<div class="form-container editor-container">
    <h3>📝 Édition du fichier : <?= htmlspecialchars($editFile) ?></h3>
    <form method="POST" class="installeur-form">
        <input type="hidden" name="action" value="save_file">
        <input type="hidden" name="filename" value="<?= htmlspecialchars($editFile) ?>">
        
        <div class="form-group">
            <label for="file_content">Contenu du fichier :</label>
            <div class="editor-toolbar">
                <button type="button" onclick="insertText('[Section]')" class="btn btn-sm btn-secondary">📁 Section</button>
                <button type="button" onclick="insertText('key=value')" class="btn btn-sm btn-secondary">🔑 Clé=Valeur</button>
                <button type="button" onclick="insertText(';Commentaire')" class="btn btn-sm btn-secondary">💬 Commentaire</button>
                <button type="button" onclick="formatContent()" class="btn btn-sm btn-info">🎨 Formater</button>
                <span class="file-info">
                    📄 <?= htmlspecialchars($editFile) ?>
                    (<?= formatFileSize(strlen($editFileContent)) ?>)
                </span>
            </div>
            <textarea id="file_content" name="file_content" rows="20" class="code-editor" spellcheck="false"><?= htmlspecialchars($editFileContent) ?></textarea>
            <div class="editor-info">
                <small>
                    💡 Conseils :
                    • Utilisez Ctrl+S pour sauvegarder rapidement
                    • Les modifications sont sauvegardées immédiatement
                    • Formats supportés : ini, txt, cfg, conf, log, xml, json, md, yml, au3, php, js, css, html
                </small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">💾 Sauvegarder</button>
            <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>" class="btn btn-secondary">❌ Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Liste des fichiers et dossiers -->
<div class="list-container">
    <h3>Contenu du dossier (<?= count($items) ?> élément<?= count($items) > 1 ? 's' : '' ?>)</h3>
    
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <p>📁 Ce dossier est vide.</p>
            <p>Utilisez les boutons ci-dessus pour ajouter des fichiers ou créer des sous-dossiers.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>📄 Nom</th>
                        <th>📏 Taille</th>
                        <th>🗓️ Modifié</th>
                        <th>🔧 Type</th>
                        <th>⚡ Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if ($item['is_directory']): ?>
                                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath ? $currentPath . '/' . $item['name'] : $item['name']) ?>" class="folder-link">
                                    📁 <strong><?= htmlspecialchars($item['name']) ?></strong>
                                </a>
                            <?php else: ?>
                                📄 <strong><?= htmlspecialchars($item['name']) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $item['is_directory'] ? '-' : formatFileSize($item['size']) ?>
                        </td>
                        <td><?= date('d/m/Y H:i', $item['modified']) ?></td>
                        <td>
                            <?php if ($item['is_directory']): ?>
                                <span class="badge badge-folder">DOSSIER</span>
                            <?php else: ?>
                                <span class="badge <?= getFileTypeBadgeClass($item['extension']) ?>">
                                    <?= strtoupper($item['extension']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($item['is_directory']): ?>
                                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath ? $currentPath . '/' . $item['name'] : $item['name']) ?>" class="btn btn-sm btn-info">📂 Ouvrir</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier ? Il doit être vide.')">
                                    <input type="hidden" name="action" value="delete_folder">
                                    <input type="hidden" name="foldername" value="<?= htmlspecialchars($item['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Supprimer</button>
                                </form>
                            <?php else: ?>
                                <a href="<?= $currentRelativePath . '/' . urlencode($item['name']) ?>" class="btn btn-sm btn-info" download>📥 Télécharger</a>
                                <?php if (isEditableFile($item['name'])): ?>
                                    <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>&edit=<?= urlencode($item['name']) ?>" class="btn btn-sm btn-success">📝 Éditer</a>
                                <?php endif; ?>
                                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>&rename=<?= urlencode($item['name']) ?>" class="btn btn-sm btn-warning">✏️ Renommer</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($item['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// Fonctions utilitaires
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

function getFileTypeBadgeClass($extension) {
    $extension = strtolower($extension);
    switch ($extension) {
        case 'exe':
        case 'msi':
            return 'badge-primary';
        case 'zip':
        case 'rar':
        case '7z':
            return 'badge-info';
        case 'deb':
        case 'rpm':
            return 'badge-success';
        case 'dmg':
            return 'badge-warning';
        default:
            return 'badge-secondary';
    }
}
?>

<style>
.installeur-form {
    background: var(--bg-color);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.form-container {
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: var(--text-color);
}

.form-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--input-bg);
    color: var(--text-color);
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #6c757d;
}

.form-actions {
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
    font-weight: 500;
}

.btn-primary {
    background: var(--accent-color);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-color);
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--accent-color);
    color: white;
    font-weight: bold;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.badge-primary {
    background: var(--accent-color);
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.badge-info {
    background: #17a2b8;
    color: white;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-warning {
    background: #ffc107;
    color: #212529;
}

.badge-folder {
    background: #fd7e14;
    color: white;
}

.actions {
    white-space: nowrap;
}

.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.table-responsive {
    overflow-x: auto;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: #6c757d;
}

.description {
    margin-bottom: 20px;
    padding: 10px;
    background: #f8f9fa;
    border-left: 4px solid var(--accent-color);
    border-radius: 4px;
}

.breadcrumb-container {
    margin-bottom: 20px;
}

.breadcrumb {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    font-size: 14px;
}

.breadcrumb-link {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-link:hover {
    text-decoration: underline;
}

.breadcrumb-separator {
    margin: 0 8px;
    color: #6c757d;
}

.breadcrumb-current {
    color: #6c757d;
    font-weight: 600;
}

.quick-actions {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.folder-link {
    color: #fd7e14;
    text-decoration: none;
    font-weight: bold;
}

.folder-link:hover {
    text-decoration: underline;
}

code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 90%;
}

body.dark .description {
    background: #2c2c2c;
}

body.dark .breadcrumb {
    background: #2c2c2c;
    border-color: #444;
}

body.dark code {
    background: #2c2c2c;
    color: #e9ecef;
}

body.dark .folder-link {
    color: #ffa726;
}

/* Styles pour l'éditeur de fichiers */
.editor-container {
    border: 2px solid var(--accent-color);
}

.editor-toolbar {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.file-info {
    margin-left: auto;
    font-size: 12px;
    color: #6c757d;
    font-weight: bold;
}

.code-editor {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: #f8f9fa;
    color: var(--text-color);
    font-family: 'Courier New', Consolas, monospace;
    font-size: 14px;
    line-height: 1.4;
    resize: vertical;
    min-height: 300px;
}

.editor-info {
    margin-top: 10px;
    padding: 8px;
    background: #e9ecef;
    border-radius: 4px;
    font-size: 12px;
}


body.dark .editor-toolbar {
    background: #2c2c2c;
    border-color: #444;
}

body.dark .code-editor {
    background: #2c2c2c;
    color: #e9ecef;
    border-color: #444;
}

body.dark .editor-info {
    background: #333;
    color: #e9ecef;
}


/* Styles pour l'aide aux permissions */
.permissions-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
}

.command-box {
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 10px;
    margin: 10px 0;
}

.command-box code {
    display: block;
    margin: 5px 0;
    padding: 5px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 3px;
}

.permissions-status {
    margin-top: 15px;
}

.permissions-status ul {
    list-style: none;
    padding: 0;
}

.permissions-status li {
    padding: 5px 0;
    border-bottom: 1px solid #dee2e6;
}

.permissions-status li:last-child {
    border-bottom: none;
}

body.dark .permissions-info {
    background: #2c2c2c;
    border-color: #444;
}

body.dark .command-box {
    background: #333;
    border-color: #555;
}

body.dark .command-box code {
    background: #2c2c2c;
    border-color: #444;
    color: #e9ecef;
}

body.dark .permissions-status li {
    border-bottom-color: #444;
}

/* Responsive */
@media (max-width: 768px) {
    .quick-actions {
        flex-direction: column;
    }
    
    .btn {
        margin-bottom: 5px;
    }
    
    .editor-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .file-info {
        margin-left: 0;
        text-align: center;
    }
}
</style>

<script>
function toggleCreateFolder() {
    const form = document.getElementById('create-folder-form');
    const uploadForm = document.getElementById('upload-form');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        uploadForm.style.display = 'none';
        document.getElementById('folder_name').focus();
    } else {
        form.style.display = 'none';
    }
}

function toggleUploadForm() {
    const form = document.getElementById('upload-form');
    const createForm = document.getElementById('create-folder-form');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        createForm.style.display = 'none';
        document.getElementById('fichier_installeur').focus();
    } else {
        form.style.display = 'none';
    }
}

function togglePermissionsHelp() {
    const help = document.getElementById('permissions-help');
    const uploadForm = document.getElementById('upload-form');
    const createForm = document.getElementById('create-folder-form');
    
    if (help.style.display === 'none') {
        help.style.display = 'block';
        uploadForm.style.display = 'none';
        createForm.style.display = 'none';
    } else {
        help.style.display = 'none';
    }
}

// Fonctions pour l'éditeur de fichiers
function insertText(text) {
    const textarea = document.getElementById('file_content');
    if (textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const value = textarea.value;
        
        textarea.value = value.substring(0, start) + text + value.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.focus();
    }
}

function formatContent() {
    const textarea = document.getElementById('file_content');
    if (textarea) {
        let content = textarea.value;
        
        // Formatage basique pour les fichiers INI
        content = content.replace(/^\s*\[([^\]]+)\]\s*$/gm, '[$1]'); // Sections
        content = content.replace(/^\s*([^=\s]+)\s*=\s*(.*)$/gm, '$1=$2'); // Clés=valeurs
        content = content.replace(/^\s*;(.*)$/gm, ';$1'); // Commentaires
        
        textarea.value = content;
        textarea.focus();
    }
}


// Raccourci clavier Ctrl+S pour sauvegarder
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('file_content');
    if (textarea) {
        textarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const form = textarea.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
        
        // Auto-resize du textarea
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(300, this.scrollHeight) + 'px';
        });
    }
});
</script>