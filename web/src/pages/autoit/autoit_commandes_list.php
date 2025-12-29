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
                $stmt = $pdo->prepare("INSERT INTO autoit_commandes (nom, commande, description, defaut) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['commande'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Commande ajoutée avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_commandes SET nom=?, commande=?, description=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['commande'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Commande modifiée avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM autoit_commandes WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Commande supprimée avec succès !";
                break;
        }
    }
}

// Récupération des commandes
$stmt = $pdo->query("SELECT * FROM autoit_commandes ORDER BY nom");
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'une commande pour édition
$editCommande = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_commandes WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCommande = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="autoit-layout">
    <!-- Colonne de gauche : Formulaire -->
    <div class="layout-sidebar">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><?= $editCommande ? 'Modifier la commande' : 'Ajouter une commande' ?></h2>
            <form method="POST" class="autoit-form">
                <input type="hidden" name="action" value="<?= $editCommande ? 'edit' : 'add' ?>">
                <?php if ($editCommande): ?>
                    <input type="hidden" name="id" value="<?= $editCommande['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nom">Nom de la commande :</label>
                    <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($editCommande['nom'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="commande">Commande :</label>
                    <textarea id="commande" name="commande" rows="4" required placeholder="ex: powershell -Command &quot;Get-Process | Where-Object {$_.ProcessName -eq 'chrome'} | Stop-Process&quot;"><?= htmlspecialchars($editCommande['commande'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="description">Description :</label>
                    <textarea id="description" name="description" rows="3" placeholder="Description de ce que fait cette commande"><?= htmlspecialchars($editCommande['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="defaut" value="1" <?= ($editCommande['defaut'] ?? 0) ? 'checked' : '' ?>>
                        Commande par défaut
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $editCommande ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editCommande): ?>
                        <a href="index.php?page=autoit_commandes_list" class="btn btn-secondary">Annuler</a>
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
                            <th>Commande</th>
                            <th>Description</th>
                            <th>Défaut</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($commande['nom']) ?></strong></td>
                            <td>
                                <code class="command-preview"><?= htmlspecialchars(substr($commande['commande'], 0, 50)) ?><?= strlen($commande['commande']) > 50 ? '...' : '' ?></code>
                                <button class="btn-xs btn-info" onclick="showFullCommand(<?= $commande['id'] ?>)">Voir complet</button>
                            </td>
                            <td>
                                <?php if ($commande['description']): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($commande['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($commande['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="opacity: 0.5;">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="index.php?page=autoit_commandes_list&edit=<?= $commande['id'] ?>" class="btn-icon btn-primary" title="Modifier">✏️</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $commande['id'] ?>">
                                    <button type="submit" class="btn-icon btn-danger" title="Supprimer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Ligne cachée pour afficher la commande complète -->
                        <tr id="full-command-<?= $commande['id'] ?>" class="full-command-row" style="display: none;">
                            <td colspan="5">
                                <div class="full-command-container">
                                    <strong>Commande complète :</strong>
                                    <pre class="command-full"><?= htmlspecialchars($commande['commande']) ?></pre>
                                    <div style="margin-top: 10px;">
                                        <button class="btn btn-sm btn-secondary" onclick="hideFullCommand(<?= $commande['id'] ?>)">Masquer</button>
                                        <button class="btn btn-sm btn-success" onclick="copyCommand(<?= $commande['id'] ?>)">Copier</button>
                                    </div>
                                </div>
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
body.dark .list-container,
body.dark .full-command-container {
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

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

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

body.dark .data-table tbody tr:hover,
body.dark .full-command-row {
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

.text-muted { color: #6c757d; }
body.dark .text-muted { color: #adb5bd; }
.small { font-size: 0.85rem; }

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

/* Specific Command Styles */
.command-preview {
    background: rgba(0,0,0,0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    display: inline-block;
}

.full-command-row {
    background: rgba(0,0,0,0.02);
}

.full-command-container {
    padding: 15px;
    background: var(--bg-card, #fff);
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.command-full {
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

body.dark .command-preview {
    background: rgba(255,255,255,0.1);
    color: #e9ecef;
}

body.dark .full-command-row {
    background: rgba(255,255,255,0.02);
}

body.dark .full-command-container {
    background: #343a40;
    border-color: #495057;
}

.text-center { text-align: center !important; }
</style>

<script>
function showFullCommand(id) {
    document.getElementById('full-command-' + id).style.display = 'table-row';
}

function hideFullCommand(id) {
    document.getElementById('full-command-' + id).style.display = 'none';
}

function copyCommand(id) {
    const commandElement = document.querySelector('#full-command-' + id + ' .command-full');
    const text = commandElement.textContent;
    
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
</script>