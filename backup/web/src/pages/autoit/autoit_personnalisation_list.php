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
                $stmt = $pdo->prepare("INSERT INTO autoit_personnalisation (nom, type_registre, fichier_reg_nom, fichier_reg_path, ligne_registre, description, OS, defaut) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_registre'],
                    $_POST['fichier_reg_nom'] ?? null,
                    $_POST['fichier_reg_path'] ?? null,
                    $_POST['ligne_registre'] ?? null,
                    $_POST['description'] ?? null,
                    $_POST['OS'],
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Personnalisation ajoutée avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_personnalisation SET nom=?, type_registre=?, fichier_reg_nom=?, fichier_reg_path=?, ligne_registre=?, description=?, OS=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_registre'],
                    $_POST['fichier_reg_nom'] ?? null,
                    $_POST['fichier_reg_path'] ?? null,
                    $_POST['ligne_registre'] ?? null,
                    $_POST['description'] ?? null,
                    $_POST['OS'],
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Personnalisation modifiée avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM autoit_personnalisation WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Personnalisation supprimée avec succès !";
                break;
        }
    }
}

// Gestion de l'upload de fichiers .reg (optionnel)
if (isset($_FILES['fichier_reg_upload']) && $_FILES['fichier_reg_upload']['error'] === UPLOAD_ERR_OK) {
    $uploadBaseDir = 'uploads/';
    $uploadSubDir = 'uploads/autoit/personnalisation/';
    
    // Vérifier que le dossier uploads existe et est accessible en écriture
    if (!is_dir($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'existe pas. Vous pouvez saisir le chemin manuellement.";
    } elseif (!is_writable($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'est pas accessible en écriture. Vous pouvez saisir le chemin manuellement.";
    } else {
        $fileName = basename($_FILES['fichier_reg_upload']['name']);
        
        // Vérifier l'extension .reg
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension === 'reg') {
            // Créer le sous-dossier s'il n'existe pas
            if (!is_dir($uploadSubDir)) {
                $result = createDirectoryWithPermissions($uploadSubDir);
                if (!$result['success']) {
                    $uploadError = "Impossible de créer le dossier autoit/personnalisation. " . getPermissionErrorMessage($uploadSubDir);
                }
            }
            
            if (!isset($uploadError)) {
                $uploadPath = $uploadSubDir . $fileName;
                
                if (move_uploaded_file($_FILES['fichier_reg_upload']['tmp_name'], $uploadPath)) {
                    $uploadSuccess = "Fichier .reg uploadé avec succès dans : " . $uploadPath;
                    $uploadedFile = $uploadPath;
                } else {
                    $uploadError = "Erreur lors de l'upload du fichier. Vous pouvez saisir le chemin manuellement.";
                }
            }
        } else {
            $uploadError = "Seuls les fichiers .reg sont autorisés.";
        }
    }
}

// Récupération des personnalisations
$stmt = $pdo->query("SELECT * FROM autoit_personnalisation ORDER BY nom");
$personnalisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'une personnalisation pour édition
$editPersonnalisation = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_personnalisation WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editPersonnalisation = $stmt->fetch(PDO::FETCH_ASSOC);
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
            <h2><?= $editPersonnalisation ? 'Modifier la personnalisation' : 'Ajouter une personnalisation' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="autoit-form">
                <input type="hidden" name="action" value="<?= $editPersonnalisation ? 'edit' : 'add' ?>">
                <?php if ($editPersonnalisation): ?>
                    <input type="hidden" name="id" value="<?= $editPersonnalisation['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nom">Nom de la personnalisation :</label>
                    <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($editPersonnalisation['nom'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="type_registre">Type de registre :</label>
                    <select id="type_registre" name="type_registre" required onchange="toggleRegistreType()">
                        <option value="fichier_reg" <?= ($editPersonnalisation['type_registre'] ?? 'fichier_reg') === 'fichier_reg' ? 'selected' : '' ?>>Fichier .reg</option>
                        <option value="ligne_registre" <?= ($editPersonnalisation['type_registre'] ?? '') === 'ligne_registre' ? 'selected' : '' ?>>Ligne de registre</option>
                    </select>
                </div>
                
                <div id="fichier-reg-fields" style="display: <?= ($editPersonnalisation['type_registre'] ?? 'fichier_reg') === 'fichier_reg' ? 'block' : 'none' ?>">
                    <div class="form-group">
                        <label for="fichier_reg_upload">Upload fichier .reg (optionnel) :</label>
                        <input type="file" id="fichier_reg_upload" name="fichier_reg_upload" accept=".reg">
                        <small class="form-help">Si l'upload ne fonctionne pas, vous pouvez saisir le nom et le chemin manuellement ci-dessous.</small>
                        <?php if ($editPersonnalisation && $editPersonnalisation['fichier_reg_nom']): ?>
                            <p>Fichier actuel : <?= htmlspecialchars($editPersonnalisation['fichier_reg_nom']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="fichier_reg_nom">Nom du fichier .reg :</label>
                        <input type="text" id="fichier_reg_nom" name="fichier_reg_nom" value="<?= htmlspecialchars($editPersonnalisation['fichier_reg_nom'] ?? (isset($uploadedFile) ? $fileName : '')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fichier_reg_path">Chemin du fichier .reg :</label>
                        <input type="text" id="fichier_reg_path" name="fichier_reg_path" value="<?= htmlspecialchars($editPersonnalisation['fichier_reg_path'] ?? (isset($uploadedFile) ? $uploadedFile : '')) ?>">
                    </div>
                </div>
                
                <div id="ligne-registre-fields" style="display: <?= ($editPersonnalisation['type_registre'] ?? '') === 'ligne_registre' ? 'block' : 'none' ?>">
                    <!-- Assistant de création de ligne de registre -->
                    <div class="registry-assistant">
                        <h4>Assistant de création de ligne de registre</h4>
                        <div class="registry-builder">
                            <div class="form-row">
                                <div class="form-group form-col-2">
                                    <label for="reg_hkey">Clé racine :</label>
                                    <select id="reg_hkey">
                                        <option value="HKEY_CURRENT_USER">HKEY_CURRENT_USER</option>
                                        <option value="HKEY_LOCAL_MACHINE">HKEY_LOCAL_MACHINE</option>
                                        <option value="HKEY_CLASSES_ROOT">HKEY_CLASSES_ROOT</option>
                                        <option value="HKEY_USERS">HKEY_USERS</option>
                                        <option value="HKEY_CURRENT_CONFIG">HKEY_CURRENT_CONFIG</option>
                                    </select>
                                </div>
                                <div class="form-group form-col-2">
                                    <label for="reg_path">Chemin de la clé :</label>
                                    <input type="text" id="reg_path" placeholder="ex: Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group form-col-3">
                                    <label for="reg_name">Nom de la valeur :</label>
                                    <input type="text" id="reg_name" placeholder="ex: HideFileExt">
                                </div>
                                <div class="form-group form-col-3">
                                    <label for="reg_type">Type de valeur :</label>
                                    <select id="reg_type" onchange="updateValuePlaceholder()">
                                        <option value="dword">DWORD (32-bit)</option>
                                        <option value="qword">QWORD (64-bit)</option>
                                        <option value="sz">String (REG_SZ)</option>
                                        <option value="expand_sz">String étendue (REG_EXPAND_SZ)</option>
                                        <option value="multi_sz">Multi-String (REG_MULTI_SZ)</option>
                                        <option value="binary">Binaire (REG_BINARY)</option>
                                    </select>
                                </div>
                                <div class="form-group form-col-3">
                                    <label for="reg_value">Valeur :</label>
                                    <input type="text" id="reg_value" placeholder="ex: 00000000">
                                </div>
                            </div>
                            
                            <div class="form-actions" style="margin-top: 10px;">
                                <button type="button" class="btn btn-sm btn-info" onclick="generateRegistryLine()">Générer la ligne</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="clearRegistryBuilder()">Effacer</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ligne_registre">Ligne de base de registre :</label>
                        <textarea id="ligne_registre" name="ligne_registre" rows="4" placeholder="ex: [HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced]&#10;&quot;HideFileExt&quot;=dword:00000000"><?= htmlspecialchars($editPersonnalisation['ligne_registre'] ?? '') ?></textarea>
                        <small class="form-help">Format : [HKEY_...]\n"CleName"=dword:value ou "CleName"="StringValue"<br>
                        Vous pouvez utiliser l'assistant ci-dessus ou saisir directement la ligne de registre.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description :</label>
                    <textarea id="description" name="description" rows="3" placeholder="Description de cette personnalisation"><?= htmlspecialchars($editPersonnalisation['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="OS">Version de Windows :</label>
                    <select id="OS" name="OS" required>
                        <option value="">-- Sélectionner une version --</option>
                        <option value="7" <?= ($editPersonnalisation['OS'] ?? '') == '7' ? 'selected' : '' ?>>Windows 7</option>
                        <option value="8" <?= ($editPersonnalisation['OS'] ?? '') == '8' ? 'selected' : '' ?>>Windows 8</option>
                        <option value="81" <?= ($editPersonnalisation['OS'] ?? '') == '81' ? 'selected' : '' ?>>Windows 8.1</option>
                        <option value="10" <?= ($editPersonnalisation['OS'] ?? '') == '10' ? 'selected' : '' ?>>Windows 10</option>
                        <option value="11" <?= ($editPersonnalisation['OS'] ?? '') == '11' ? 'selected' : '' ?>>Windows 11</option>
                        <option value="0" <?= ($editPersonnalisation['OS'] ?? '') == '0' ? 'selected' : '' ?>>Toutes versions</option>
                    </select>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="defaut" value="1" <?= ($editPersonnalisation['defaut'] ?? 0) ? 'checked' : '' ?>>
                        Personnalisation par défaut
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $editPersonnalisation ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editPersonnalisation): ?>
                        <a href="index.php?page=autoit_personnalisation_list" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Colonne de droite : Liste -->
    <div class="layout-main">
        <div class="list-container">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Contenu</th>
                            <!-- <th>Description</th> -->
                            <th>Défaut</th>
                            <th>Version Windows</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personnalisations as $personnalisation): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($personnalisation['nom']) ?></strong>
                                <?php if ($personnalisation['description']): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($personnalisation['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $personnalisation['type_registre'] === 'fichier_reg' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= $personnalisation['type_registre'] === 'fichier_reg' ? 'Fichier .reg' : 'Ligne registre' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($personnalisation['type_registre'] === 'fichier_reg'): ?>
                                    <div class="file-info">
                                        <span class="file-name"><?= htmlspecialchars($personnalisation['fichier_reg_nom']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <code class="registry-preview"><?= htmlspecialchars(substr($personnalisation['ligne_registre'], 0, 50)) ?><?= strlen($personnalisation['ligne_registre']) > 50 ? '...' : '' ?></code>
                                    <button class="btn-xs btn-info" onclick="showFullRegistry(<?= $personnalisation['id'] ?>)">Voir complet</button>
                                <?php endif; ?>
                            </td>
                            <!-- <td><?= htmlspecialchars($personnalisation['description']) ?></td> -->
                            <td class="text-center">
                                <?php if ($personnalisation['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="opacity: 0.5;">Non</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $osLabels = [
                                    '7' => 'Windows 7',
                                    '8' => 'Windows 8',
                                    '81' => 'Windows 8.1',
                                    '10' => 'Windows 10',
                                    '11' => 'Windows 11',
                                    '0' => 'Toutes versions'
                                ];
                                $osValue = $personnalisation['OS'] ?? '0';
                                ?>
                                <span class="badge badge-os"><?= $osLabels[$osValue] ?? 'Non défini' ?></span>
                            </td>
                            <td class="actions">
                                <a href="index.php?page=autoit_personnalisation_list&edit=<?= $personnalisation['id'] ?>" class="btn-icon btn-primary" title="Modifier">✏️</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette personnalisation ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $personnalisation['id'] ?>">
                                    <button type="submit" class="btn-icon btn-danger" title="Supprimer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Ligne cachée pour afficher le registre complet -->
                        <?php if ($personnalisation['type_registre'] === 'ligne_registre'): ?>
                        <tr id="full-registry-<?= $personnalisation['id'] ?>" class="full-registry-row" style="display: none;">
                            <td colspan="6">
                                <div class="full-registry-container">
                                    <strong>Ligne de registre complète :</strong>
                                    <pre class="registry-full"><?= htmlspecialchars($personnalisation['ligne_registre']) ?></pre>
                                    <div style="margin-top: 10px;">
                                        <button class="btn btn-sm btn-secondary" onclick="hideFullRegistry(<?= $personnalisation['id'] ?>)">Masquer</button>
                                        <button class="btn btn-sm btn-success" onclick="copyRegistry(<?= $personnalisation['id'] ?>)">Copier</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
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
body.dark .list-container,
body.dark .full-registry-container {
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
body.dark .list-container h2,
body.dark .registry-assistant h4 {
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
    font-family: inherit;
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

.form-group textarea {
    resize: vertical;
    min-height: 80px;
    font-family: 'Courier New', monospace;
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

.btn-primary { background: var(--accent-color); color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-success { background: #28a745; color: white; }

.btn-sm { padding: 4px 8px; font-size: 0.8rem; }
.btn-xs { padding: 2px 6px; font-size: 10px; border-radius: 3px; border: none; cursor: pointer; margin-left: 5px; }

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
    vertical-align: top;
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
    display: inline-block;
}

.badge-primary { background: var(--accent-color); color: white; }
.badge-secondary { background: #6c757d; color: white; }
.badge-info { background: #17a2b8; color: white; }
.badge-success { background: #28a745; color: white; }
.badge-os { background: #17a2b8; color: white; }

.text-muted { color: #6c757d; }
body.dark .text-muted { color: #adb5bd; }
.small { font-size: 0.85rem; }
.text-center { text-align: center !important; }

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

/* Registry Specific Styles */
.registry-preview {
    background: rgba(0,0,0,0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    display: inline-block;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-name {
    font-family: monospace;
    background: rgba(0,0,0,0.05);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.full-registry-row {
    background: rgba(0,0,0,0.02);
}

.full-registry-container {
    padding: 15px;
    background: var(--bg-card, #fff);
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.registry-full {
    background: #2d3748;
    color: #e2e8f0;
    padding: 15px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 10px 0;
}

body.dark .registry-preview,
body.dark .file-name {
    background: rgba(255,255,255,0.1);
    color: #e9ecef;
}

body.dark .full-registry-row,
body.dark .registry-assistant {
    background: rgba(255,255,255,0.02);
}

body.dark .full-registry-container {
    background: #343a40;
    border-color: #495057;
}

/* Assistant de registre */
.registry-assistant {
    background: rgba(0,0,0,0.03);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

body.dark .registry-assistant {
    border-color: #444;
}

.registry-assistant h4 {
    margin: 0 0 15px 0;
    color: var(--text-color);
    font-size: 1rem;
}

.registry-builder {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-row {
    display: flex;
    gap: 15px;
    align-items: end;
}

.form-col-2 { flex: 1; }
.form-col-3 { flex: 1; }
.form-row .form-group { margin-bottom: 0; }

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    .form-row .form-group { margin-bottom: 15px; }
}

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

<script>
function toggleRegistreType() {
    const type = document.getElementById('type_registre').value;
    const fichierFields = document.getElementById('fichier-reg-fields');
    const ligneFields = document.getElementById('ligne-registre-fields');
    
    if (type === 'fichier_reg') {
        fichierFields.style.display = 'block';
        ligneFields.style.display = 'none';
    } else {
        fichierFields.style.display = 'none';
        ligneFields.style.display = 'block';
    }
}

function showFullRegistry(id) {
    document.getElementById('full-registry-' + id).style.display = 'table-row';
}

function hideFullRegistry(id) {
    document.getElementById('full-registry-' + id).style.display = 'none';
}

function copyRegistry(id) {
    const registryElement = document.querySelector('#full-registry-' + id + ' .registry-full');
    const text = registryElement.textContent;
    
    navigator.clipboard.writeText(text).then(function() {
        // Feedback visuel
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Copié !';
        button.style.background = '#28a745';
        
        setTimeout(function() {
            button.textContent = originalText;
            button.style.background = '#28a745';
        }, 2000);
    }).catch(function(err) {
        console.error('Erreur lors de la copie: ', err);
        alert('Erreur lors de la copie dans le presse-papiers');
    });
}

// Auto-fill file path when file is uploaded
if(document.getElementById('fichier_reg_upload')) {
    document.getElementById('fichier_reg_upload').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('fichier_reg_nom').value = fileName;
            document.getElementById('fichier_reg_path').value = 'uploads/autoit/personnalisation/' + fileName;
        }
    });
}

// Fonctions pour l'assistant de registre
function updateValuePlaceholder() {
    const regType = document.getElementById('reg_type').value;
    const regValue = document.getElementById('reg_value');
    
    switch(regType) {
        case 'dword': regValue.placeholder = 'ex: 00000000 (hexadécimal)'; break;
        case 'qword': regValue.placeholder = 'ex: 0000000000000000 (hexadécimal)'; break;
        case 'sz': regValue.placeholder = 'ex: Ma chaîne de caractères'; break;
        case 'expand_sz': regValue.placeholder = 'ex: %SystemRoot%\\system32'; break;
        case 'multi_sz': regValue.placeholder = 'ex: Ligne1\\0Ligne2\\0Ligne3'; break;
        case 'binary': regValue.placeholder = 'ex: 01,02,03,04 (hexadécimal)'; break;
        default: regValue.placeholder = '';
    }
}

function generateRegistryLine() {
    const hkey = document.getElementById('reg_hkey').value;
    const path = document.getElementById('reg_path').value.trim();
    const name = document.getElementById('reg_name').value.trim();
    const type = document.getElementById('reg_type').value;
    const value = document.getElementById('reg_value').value.trim();
    
    if (!path || !name || !value) {
        alert('Veuillez remplir tous les champs obligatoires (chemin, nom et valeur).');
        return;
    }
    
    let registryLine = `[${hkey}\\${path}]\n`;
    
    switch(type) {
        case 'dword': registryLine += `"${name}"=dword:${value.replace(/^0x/, '').padStart(8, '0')}`; break;
        case 'qword': registryLine += `"${name}"=hex(b):${formatHexBytes(value, 8)}`; break;
        case 'sz': registryLine += `"${name}"="${value}"`; break;
        case 'expand_sz': registryLine += `"${name}"=hex(2):${stringToHex(value)}`; break;
        case 'multi_sz': registryLine += `"${name}"=hex(7):${stringToHex(value.replace(/\\0/g, '\0') + '\0')}`; break;
        case 'binary': registryLine += `"${name}"=hex:${value.replace(/[^0-9a-fA-F,]/g, '').toLowerCase()}`; break;
    }
    
    document.getElementById('ligne_registre').value = registryLine;
}

function clearRegistryBuilder() {
    document.getElementById('reg_hkey').selectedIndex = 0;
    document.getElementById('reg_path').value = '';
    document.getElementById('reg_name').value = '';
    document.getElementById('reg_type').selectedIndex = 0;
    document.getElementById('reg_value').value = '';
    updateValuePlaceholder();
}

function formatHexBytes(hexString, byteCount) {
    let cleaned = hexString.replace(/[^0-9a-fA-F]/g, '').toLowerCase();
    cleaned = cleaned.padStart(byteCount * 2, '0');
    let bytes = [];
    for (let i = 0; i < cleaned.length; i += 2) {
        bytes.push(cleaned.substr(i, 2));
    }
    bytes.reverse();
    return bytes.join(',');
}

function stringToHex(str) {
    let hex = '';
    for (let i = 0; i < str.length; i++) {
        let charCode = str.charCodeAt(i);
        hex += charCode.toString(16).padStart(2, '0') + ',00,';
    }
    // L'ajout du null terminator final dépend du type, souvent géré ailleurs ou explicite
    return hex;
}
</script>