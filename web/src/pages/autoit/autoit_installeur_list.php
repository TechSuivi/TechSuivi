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

<div class="container container-center max-w-1600">
    <div class="flex items-center justify-between mb-20 bg-light p-20 rounded shadow-sm border border-border">
        <div>
            <h1 class="m-0 text-xl font-bold">Gestion des Installeurs</h1>
            <p class="text-sm text-muted m-0 mt-5">Explorez et gérez les fichiers d'installation dans le répertoire <code class="bg-dark text-white rounded px-5"><?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code></p>
        </div>
        <button onclick="togglePermissionsHelp()" class="btn btn-sm btn-info flex items-center gap-5">🔧 Permissions</button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success mb-20"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-20"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Fil d'Ariane -->
    <div class="mb-20">
        <nav class="flex items-center gap-5 bg-card p-10 px-15 rounded border border-border text-sm flex-wrap">
            <?php foreach ($breadcrumb as $index => $crumb): ?>
                <?php if ($index === count($breadcrumb) - 1): ?>
                    <span class="font-bold text-dark flex items-center gap-5">📁 <?= htmlspecialchars($crumb['name']) ?></span>
                <?php else: ?>
                    <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($crumb['path']) ?>" class="text-primary hover:underline flex items-center gap-5 font-bold">
                        📁 <?= htmlspecialchars($crumb['name']) ?>
                    </a>
                    <span class="text-muted">›</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Actions rapides -->
    <div class="flex gap-10 mb-20 flex-wrap">
        <button onclick="toggleCreateFolder()" class="btn btn-success flex items-center gap-5">📁 Nouveau Dossier</button>
        <button onclick="toggleUploadForm()" class="btn btn-primary flex items-center gap-5">📤 Uploader Fichier</button>
        <?php if (!empty($currentPath)): ?>
            <a href="index.php?page=autoit_installeur_list&path=<?= urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)) ?>" class="btn btn-secondary flex items-center gap-5">⬆️ Dossier Parent</a>
        <?php endif; ?>
    </div>

    <!-- Aide pour les permissions (masquée par défaut) -->
    <div id="permissions-help" class="card bg-info-subtle border border-info p-20 mb-20 hidden">
        <h3 class="mt-0 text-info">🔧 Résolution des problèmes de permissions</h3>
        <div class="mb-15">
            <p><strong>Si vous rencontrez des erreurs de permissions :</strong></p>
            <div class="bg-input p-10 rounded border border-border font-mono text-sm mb-10">
                <code class="block mb-5">chmod -R 755 <?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code>
                <code class="block">chown -R www-data:www-data <?= htmlspecialchars($currentRelativePath ?: $baseInstallDir) ?></code>
            </div>
            <div class="bg-card p-15 rounded border border-border">
                <h4 class="mt-0 mb-10 text-sm font-bold uppercase text-muted">État actuel des permissions :</h4>
                <ul class="list-none p-0 m-0 text-sm">
                    <li class="border-b border-border py-5">📁 Répertoire : <?= is_writable($currentFullPath) ? '<span class="text-success font-bold">✅ Écriture autorisée</span>' : '<span class="text-danger font-bold">❌ Écriture refusée</span>' ?></li>
                    <li class="border-b border-border py-5">🔍 Chemin complet : <code class="bg-light px-5 rounded"><?= htmlspecialchars($currentFullPath) ?></code></li>
                    <li class="border-b border-border py-5">👤 Propriétaire : <?= function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($currentFullPath))['name'] ?? 'Inconnu' : 'Non disponible' ?></li>
                    <li class="py-5">🔐 Permissions : <?= substr(sprintf('%o', fileperms($currentFullPath)), -4) ?></li>
                </ul>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="button" onclick="togglePermissionsHelp()" class="btn btn-sm btn-secondary">Fermer</button>
        </div>
    </div>

    <!-- Formulaire de création de dossier (masqué par défaut) -->
    <div id="create-folder-form" class="card bg-secondary border p-20 mb-20 hidden">
        <h3 class="mt-0 mb-15 text-lg border-b border-border pb-10">Créer un nouveau dossier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_folder">
            
            <div class="mb-15">
                <label for="folder_name" class="block mb-5 font-bold">Nom du dossier :</label>
                <input type="text" id="folder_name" name="folder_name" required class="form-control w-full p-8 border rounded bg-input text-dark" placeholder="Nom du nouveau dossier">
            </div>
            
            <div class="flex gap-10">
                <button type="submit" class="btn btn-success flex items-center gap-5">📁 Créer</button>
                <button type="button" onclick="toggleCreateFolder()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>

    <!-- Formulaire d'upload (masqué par défaut) -->
    <div id="upload-form" class="card bg-secondary border p-20 mb-20 hidden">
        <h3 class="mt-0 mb-15 text-lg border-b border-border pb-10">Uploader un fichier</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            
            <div class="mb-15">
                <label for="fichier_installeur" class="block mb-5 font-bold">Sélectionner un fichier :</label>
                <input type="file" id="fichier_installeur" name="fichier_installeur" required class="form-control w-full p-8 border rounded bg-input text-dark">
                <small class="text-muted text-xs mt-5 block">Tous les formats de fichiers sont acceptés</small>
            </div>
            
            <div class="flex gap-10">
                <button type="submit" class="btn btn-primary flex items-center gap-5">📤 Uploader</button>
                <button type="button" onclick="toggleUploadForm()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>

    <!-- Formulaire de renommage (si applicable) -->
    <?php if ($renameFile): ?>
    <div class="card bg-secondary border p-20 mb-20">
        <h3 class="mt-0 mb-15 text-lg border-b border-border pb-10">Renommer le fichier</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="old_filename" value="<?= htmlspecialchars($renameFile) ?>">
            
            <div class="mb-15">
                <label for="new_filename" class="block mb-5 font-bold">Nouveau nom :</label>
                <input type="text" id="new_filename" name="new_filename" required class="form-control w-full p-8 border rounded bg-input text-dark" value="<?= htmlspecialchars($renameFile) ?>">
            </div>
            
            <div class="flex gap-10">
                <button type="submit" class="btn btn-primary flex items-center gap-5">✏️ Renommer</button>
                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Éditeur de fichier texte (si applicable) -->
    <?php if ($editFile): ?>
    <div class="card bg-secondary border p-0 overflow-hidden mb-20 border-l-4 border-l-primary shadow-lg">
        <div class="bg-light p-10 px-20 border-b border-border flex justify-between items-center">
            <h3 class="m-0 text-base font-bold flex items-center gap-8">📝 Édition : <?= htmlspecialchars($editFile) ?></h3>
            <span class="text-xs text-muted font-mono"><?= formatFileSize(strlen($editFileContent)) ?></span>
        </div>
        <form method="POST" class="p-20">
            <input type="hidden" name="action" value="save_file">
            <input type="hidden" name="filename" value="<?= htmlspecialchars($editFile) ?>">
            
            <div class="mb-15">
                <div class="flex gap-5 mb-10 flex-wrap bg-light p-5 rounded border border-border">
                    <button type="button" onclick="insertText('[Section]')" class="btn btn-xs btn-secondary">📁 Section</button>
                    <button type="button" onclick="insertText('key=value')" class="btn btn-xs btn-secondary">🔑 Clé=Valeur</button>
                    <button type="button" onclick="insertText(';Commentaire')" class="btn btn-xs btn-secondary">💬 Commentaire</button>
                    <button type="button" onclick="formatContent()" class="btn btn-xs btn-info">🎨 Formater</button>
                </div>
                <textarea id="file_content" name="file_content" rows="20" class="form-control w-full p-15 border rounded bg-dark text-light font-mono text-sm leading-relaxed" spellcheck="false" style="min-height: 400px;"><?= htmlspecialchars($editFileContent) ?></textarea>
                <div class="bg-info-subtle text-info text-xs p-10 rounded mt-10 border border-info flex items-center gap-10">
                    <span>💡</span>
                    <span><strong>Astuce :</strong> Ctrl+S pour sauvegarder rapidement. Formats supportés: ini, txt, cfg, ...</span>
                </div>
            </div>
            
            <div class="flex gap-10">
                <button type="submit" class="btn btn-success flex items-center gap-5">💾 Sauvegarder</button>
                <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>" class="btn btn-secondary flex items-center gap-5">❌ Annuler</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Liste des fichiers et dossiers -->
    <div class="card bg-secondary border p-20">
        <h3 class="mt-0 mb-15 text-lg border-b border-border pb-10">Contenu du dossier (<?= count($items) ?> élément<?= count($items) > 1 ? 's' : '' ?>)</h3>
        
        <?php if (empty($items)): ?>
            <div class="text-center p-40 opacity-50">
                <div class="text-4xl mb-10">📁</div>
                <p>Ce dossier est vide.</p>
                <p class="text-sm">Utilisez les boutons ci-dessus pour ajouter des fichiers.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-left border-b border-border text-muted uppercase text-xs">
                            <th class="p-10">📄 Nom</th>
                            <th class="p-10">📏 Taille</th>
                            <th class="p-10">🗓️ Modifié</th>
                            <th class="p-10">🔧 Type</th>
                            <th class="p-10 text-right">⚡ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-10 align-middle">
                                <?php if ($item['is_directory']): ?>
                                    <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath ? $currentPath . '/' . $item['name'] : $item['name']) ?>" class="text-warning font-bold no-underline hover:underline flex items-center gap-8">
                                        <span class="text-xl">📁</span> <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="flex items-center gap-8 font-medium">
                                        <span class="text-xl opacity-70">📄</span> <?= htmlspecialchars($item['name']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-middle text-sm text-muted font-mono">
                                <?= $item['is_directory'] ? '-' : formatFileSize($item['size']) ?>
                            </td>
                            <td class="p-10 align-middle text-sm text-muted">
                                <?= date('d/m/Y H:i', $item['modified']) ?>
                            </td>
                            <td class="p-10 align-middle">
                                <?php if ($item['is_directory']): ?>
                                    <span class="badge badge-warning">DOSSIER</span>
                                <?php else: ?>
                                    <span class="badge <?= getFileTypeBadgeClass($item['extension']) ?>">
                                        <?= strtoupper($item['extension']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-middle text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-5">
                                    <?php if ($item['is_directory']): ?>
                                        <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath ? $currentPath . '/' . $item['name'] : $item['name']) ?>" class="btn btn-xs btn-info">📂 Ouvrir</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier ? Il doit être vide.')">
                                            <input type="hidden" name="action" value="delete_folder">
                                            <input type="hidden" name="foldername" value="<?= htmlspecialchars($item['name']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger p-5 bg-transparent text-danger hover:scale-110" title="Supprimer">🗑️</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="<?= $currentRelativePath . '/' . urlencode($item['name']) ?>" class="btn btn-xs btn-secondary p-5" download title="Télécharger">📥</a>
                                        <?php if (isEditableFile($item['name'])): ?>
                                            <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>&edit=<?= urlencode($item['name']) ?>" class="btn btn-xs btn-success p-5" title="Éditer">📝</a>
                                        <?php endif; ?>
                                        <a href="index.php?page=autoit_installeur_list&path=<?= urlencode($currentPath) ?>&rename=<?= urlencode($item['name']) ?>" class="btn btn-xs btn-warning p-5" title="Renommer">✏️</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($item['name']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger p-5 bg-transparent text-danger hover:scale-110" title="Supprimer">🗑️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
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

<script>
function toggleCreateFolder() {
    const form = document.getElementById('create-folder-form');
    const uploadForm = document.getElementById('upload-form');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        uploadForm.classList.add('hidden');
        document.getElementById('folder_name').focus();
    } else {
        form.classList.add('hidden');
    }
}

function toggleUploadForm() {
    const form = document.getElementById('upload-form');
    const createForm = document.getElementById('create-folder-form');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        createForm.classList.add('hidden');
        document.getElementById('fichier_installeur').focus();
    } else {
        form.classList.add('hidden');
    }
}

function togglePermissionsHelp() {
    const help = document.getElementById('permissions-help');
    
    if (help.classList.contains('hidden')) {
        help.classList.remove('hidden');
    } else {
        help.classList.add('hidden');
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
            this.style.height = Math.max(400, this.scrollHeight) + 'px';
        });
    }
});
</script>