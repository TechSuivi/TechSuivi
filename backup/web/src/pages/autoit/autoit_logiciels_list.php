<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Traitement des actions (ajout, modification, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO autoit_logiciels (nom, type_installation, commande_winget, fichier_nom, fichier_path, est_zip, commande_lancement, description, defaut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_installation'],
                    $_POST['commande_winget'] ?? null,
                    $_POST['fichier_nom'] ?? null,
                    $_POST['fichier_path'] ?? null,
                    isset($_POST['est_zip']) ? 1 : 0,
                    $_POST['commande_lancement'] ?? null,
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Logiciel ajouté avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_logiciels SET nom=?, type_installation=?, commande_winget=?, fichier_nom=?, fichier_path=?, est_zip=?, commande_lancement=?, description=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_installation'],
                    $_POST['commande_winget'] ?? null,
                    $_POST['fichier_nom'] ?? null,
                    $_POST['fichier_path'] ?? null,
                    isset($_POST['est_zip']) ? 1 : 0,
                    $_POST['commande_lancement'] ?? null,
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Logiciel modifié avec succès !";
                break;
                
            case 'delete':
                // Si la suppression du fichier est demandée
                if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
                    $stmt = $pdo->prepare("SELECT fichier_path FROM autoit_logiciels WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $fileData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fileData && !empty($fileData['fichier_path'])) {
                        // Construire le chemin absolu
                        $filePath = realpath(__DIR__ . '/../../' . $fileData['fichier_path']); // Correction du chemin
                        
                        // Sécurité basique pour éviter de supprimer n'importe quoi
                        if ($filePath && file_exists($filePath) && strpos($filePath, 'uploads') !== false) {
                            unlink($filePath);
                        }
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM autoit_logiciels WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Logiciel supprimé avec succès !";
                break;
        }
    }
}

// Gestion de l'upload de fichiers (optionnel)
if (isset($_FILES['fichier_upload']) && $_FILES['fichier_upload']['error'] === UPLOAD_ERR_OK) {
    $uploadBaseDir = 'uploads/';
    $uploadSubDir = 'uploads/autoit/logiciels/';
    
    // Vérifier que le dossier uploads existe et est accessible en écriture
    if (!is_dir($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'existe pas. Vous pouvez saisir le chemin manuellement.";
    } elseif (!is_writable($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'est pas accessible en écriture. Vous pouvez saisir le chemin manuellement.";
    } else {
        // Créer le sous-dossier s'il n'existe pas
        if (!is_dir($uploadSubDir)) {
            $result = createDirectoryWithPermissions($uploadSubDir);
            if (!$result['success']) {
                $uploadError = "Impossible de créer le dossier autoit/logiciels. " . getPermissionErrorMessage($uploadSubDir);
            }
        }
        
        if (!isset($uploadError)) {
            $fileName = basename($_FILES['fichier_upload']['name']);
            $uploadPath = $uploadSubDir . $fileName;
            
            if (move_uploaded_file($_FILES['fichier_upload']['tmp_name'], $uploadPath)) {
                $uploadSuccess = "Fichier uploadé avec succès dans : " . $uploadPath;
                $uploadedFile = $uploadPath;
            } else {
                $uploadError = "Erreur lors de l'upload du fichier. Vous pouvez saisir le chemin manuellement.";
            }
        }
    }
}

// Récupération des logiciels
$stmt = $pdo->query("SELECT * FROM autoit_logiciels ORDER BY nom");
$logiciels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'un logiciel pour édition
$editLogiciel = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_logiciels WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editLogiciel = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="autoit-layout">
    <!-- Colonne de gauche : Formulaire -->
    <div class="layout-sidebar">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (isset($uploadSuccess)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($uploadSuccess) ?></div>
        <?php endif; ?>

        <?php if (isset($uploadError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><?= $editLogiciel ? 'Modifier le logiciel' : 'Ajouter un logiciel' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="autoit-form">
                <input type="hidden" name="action" value="<?= $editLogiciel ? 'edit' : 'add' ?>">
                <?php if ($editLogiciel): ?>
                    <input type="hidden" name="id" value="<?= $editLogiciel['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nom">Nom du logiciel :</label>
                    <input type="text" id="nom" name="nom" required value="<?= $editLogiciel['nom'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="type_installation">Type d'installation :</label>
                    <select id="type_installation" name="type_installation" required onchange="toggleInstallationType()">
                        <option value="winget" <?= ($editLogiciel['type_installation'] ?? '') === 'winget' ? 'selected' : '' ?>>Winget</option>
                        <option value="fichier" <?= ($editLogiciel['type_installation'] ?? '') === 'fichier' ? 'selected' : '' ?>>Fichier</option>
                    </select>
                </div>
                
                <div id="winget-fields" class="form-group" style="display: <?= ($editLogiciel['type_installation'] ?? 'winget') === 'winget' ? 'block' : 'none' ?>">
                    <label for="commande_winget">Commande Winget :</label>
                    <input type="text" id="commande_winget" name="commande_winget" value="<?= $editLogiciel['commande_winget'] ?? '' ?>" placeholder="ex: winget install --id=Google.Chrome">
                </div>
                
                <div id="fichier-fields" style="display: <?= ($editLogiciel['type_installation'] ?? 'winget') === 'fichier' ? 'block' : 'none' ?>">
                    <div class="form-group">
                        <label for="fichier_upload">Upload fichier (optionnel) :</label>
                        <input type="file" id="fichier_upload" name="fichier_upload">
                        <small class="form-help">Si l'upload ne fonctionne pas, vous pouvez saisir le nom et le chemin manuellement ci-dessous.</small>
                        <?php if ($editLogiciel && $editLogiciel['fichier_nom']): ?>
                            <p>Fichier actuel : <?= htmlspecialchars($editLogiciel['fichier_nom']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="fichier_nom">Nom du fichier :</label>
                        <input type="text" id="fichier_nom" name="fichier_nom" value="<?= $editLogiciel['fichier_nom'] ?? (isset($uploadedFile) ? $fileName : '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fichier_path">Chemin du fichier :</label>
                        <input type="text" id="fichier_path" name="fichier_path" value="<?= $editLogiciel['fichier_path'] ?? (isset($uploadedFile) ? $uploadedFile : '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="est_zip" <?= ($editLogiciel['est_zip'] ?? 0) ? 'checked' : '' ?>>
                            Fichier ZIP
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="commande_lancement">Commande de lancement :</label>
                        <input type="text" id="commande_lancement" name="commande_lancement" value="<?= $editLogiciel['commande_lancement'] ?? '' ?>" placeholder="ex: setup.exe /S">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description :</label>
                    <textarea id="description" name="description" rows="3"><?= $editLogiciel['description'] ?? '' ?></textarea>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="defaut" value="1" <?= ($editLogiciel['defaut'] ?? 0) ? 'checked' : '' ?>>
                        Logiciel par défaut
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $editLogiciel ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editLogiciel): ?>
                        <a href="index.php?page=autoit_logiciels_list" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Colonne de droite : Liste -->
    <div class="layout-main">
        <div class="list-container">
            <!-- <h2>Liste des logiciels</h2> -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Installation</th>
                            <!-- <th>Description</th> -->
                            <th>Défaut</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logiciels as $logiciel): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($logiciel['nom']) ?></strong>
                                <?php if ($logiciel['description']): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($logiciel['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $logiciel['type_installation'] === 'winget' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= ucfirst($logiciel['type_installation']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($logiciel['type_installation'] === 'winget'): ?>
                                    <code><?= htmlspecialchars($logiciel['commande_winget']) ?></code>
                                <?php else: ?>
                                    <div class="file-name"><?= htmlspecialchars($logiciel['fichier_nom']) ?></div>
                                    <?php if ($logiciel['est_zip']): ?>
                                        <span class="badge badge-info">ZIP</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <!-- <td><?= htmlspecialchars($logiciel['description']) ?></td> -->
                            <td class="text-center">
                                <?php if ($logiciel['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="opacity: 0.5;">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="index.php?page=autoit_logiciels_list&edit=<?= $logiciel['id'] ?>" class="btn-icon btn-primary" title="Modifier">✏️</a>
                                <button type="button" class="btn-icon btn-danger" onclick="confirmDelete(<?= $logiciel['id'] ?>)" title="Supprimer">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>

/* Layout Grid */
.autoit-layout {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 1200px) {
    .autoit-layout {
        grid-template-columns: 1fr;
    }
}

.form-container, .list-container {
    background: var(--bg-card, #fff);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Dark Mode Support for Containers */
body.dark .form-container, 
body.dark .list-container {
    background: #2c2c2c; /* Fallback if var(--bg-card) is not set */
    border-color: #444;
    color: #e9ecef;
}

.form-container h2, .list-container h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.25rem;
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
    color: var(--text-color);
}

body.dark .form-container h2, 
body.dark .list-container h2 {
    color: #fff;
}

.autoit-form {
    margin-bottom: 0;
    background: transparent;
    padding: 0;
    border: none;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--text-color);
}

body.dark .form-group label {
    color: #e9ecef;
}

/* Aggressive reset for form elements */
.form-group input,
.form-group select,
.form-group textarea,
.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="number"],
.form-group input[type="email"],
.form-group input[type="file"] {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--input-bg);
    color: var(--text-color);
    display: block !important;
    height: auto;
    margin: 0;
}

/* Fix for checkbox grouping to not stretch */
.checkbox-group input[type="checkbox"] {
    width: auto !important;
    display: inline-block !important;
    margin-right: 10px;
}

/* Specific Dark Mode overrides if variables aren't global */
body.dark .form-group input,
body.dark .form-group select,
body.dark .form-group textarea {
    background: #3b3b3b;
    border-color: #555;
    color: #fff;
}

body.dark .form-group input:focus,
body.dark .form-group select:focus,
body.dark .form-group textarea:focus {
    border-color: var(--accent-color);
    outline: none;
    background: #454545;
}

.form-actions {
    margin-top: 20px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background 0.2s;
}

.btn-primary {
    background: var(--accent-color);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-icon {
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px !important;
    height: 32px !important;
    padding: 0;
    font-size: 1.1rem;
    line-height: 1;
    transition: opacity 0.2s;
    background: transparent;
    box-sizing: border-box;
    text-decoration: none;
    vertical-align: middle;
}
.btn-icon:hover { opacity: 0.8; }
.btn-icon.btn-primary { background: #e3f2fd; color: #0d6efd; }
.btn-icon.btn-danger { background: #f8d7da; color: #dc3545; }

body.dark .btn-icon.btn-primary { background: rgba(13, 110, 253, 0.2); color: #6ea8fe; }
body.dark .btn-icon.btn-danger { background: rgba(220, 53, 69, 0.2); color: #ea868f; }

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

body.dark .data-table th,
body.dark .data-table td {
    border-color: #444;
}

.data-table th {
    background: transparent;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding-bottom: 8px;
}

body.dark .data-table th {
    color: #adb5bd;
}

.data-table tbody tr:hover {
    background-color: var(--hover-color, rgba(0,0,0,0.02));
}

body.dark .data-table tbody tr:hover {
    background-color: rgba(255,255,255,0.05);
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.badge-primary { background: var(--accent-color); color: white; }
.badge-secondary { background: #6c757d; color: white; }
.badge-info { background: #17a2b8; color: white; }
.badge-success { background: #28a745; color: white; }

.text-muted { color: #6c757d; }
body.dark .text-muted { color: #adb5bd; }
.small { font-size: 0.85rem; }

code {
    background: rgba(0,0,0,0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
}

body.dark code {
    background: rgba(255,255,255,0.1);
    color: #e9ecef;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    font-weight: normal;
    cursor: pointer;
    color: var(--text-color);
}
body.dark .checkbox-group label {
    color: #e9ecef;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
}

.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* Upload help text */
.form-help {
    display: block;
    margin-top: 5px;
    font-size: 0.8rem;
    color: #6c757d;
}
body.dark .form-help {
    color: #adb5bd;
}
</style>



<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirmer la suppression</h3>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce logiciel ?</p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteIdInput">
                <div class="checkbox-group" style="margin-top: 15px;">
                    <label>
                        <input type="checkbox" name="delete_file" id="deleteFileCheckbox" value="1">
                        Supprimer également le fichier sur le serveur ?
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
            <button class="btn btn-danger" onclick="submitDelete()">Supprimer</button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    backdrop-filter: blur(2px);
}

.modal-content {
    background-color: var(--bg-color);
    margin: 15% auto;
    padding: 0;
    border: 1px solid var(--border-color);
    width: 50%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: var(--text-color);
    text-decoration: none;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>
<script>
function toggleInstallationType() {
    const type = document.getElementById('type_installation').value;
    const wingetFields = document.getElementById('winget-fields');
    const fichierFields = document.getElementById('fichier-fields');
    
    if (type === 'winget') {
        wingetFields.style.display = 'block';
        fichierFields.style.display = 'none';
    } else {
        wingetFields.style.display = 'none';
        fichierFields.style.display = 'block';
    }
}

// Auto-fill file path when file is uploaded
if (document.getElementById('fichier_upload')) {
    document.getElementById('fichier_upload').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('fichier_nom').value = fileName;
            document.getElementById('fichier_path').value = 'uploads/autoit/logiciels/' + fileName;
        }
    });
}

// Delete Modal Logic
let deleteModal = document.getElementById('deleteModal');
let deleteIdInput = document.getElementById('deleteIdInput');
let deleteFileCheckbox = document.getElementById('deleteFileCheckbox');

function confirmDelete(id) {
    if (!deleteModal) return;
    deleteIdInput.value = id;
    deleteFileCheckbox.checked = false; // Reset checkbox
    deleteModal.style.display = 'block';
}

function closeDeleteModal() {
    if (!deleteModal) return;
    deleteModal.style.display = 'none';
}

function submitDelete() {
    // Submit the form
    document.getElementById('deleteForm').submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
}
</script>
