<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// La connexion $pdo et la session sont gérées par index.php
$categories = [];
$errorMessage = '';
$messageType = '';
$sessionMessage = '';

if (isset($_SESSION['delete_message'])) {
    $sessionMessage = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']);
}
if (isset($_SESSION['edit_message'])) {
    $sessionMessage .= $_SESSION['edit_message'];
    unset($_SESSION['edit_message']);
}
if (isset($_SESSION['add_message'])) {
    $sessionMessage .= $_SESSION['add_message'];
    unset($_SESSION['add_message']);
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categorie = trim($_POST['categorie'] ?? '');
    $couleur = trim($_POST['couleur'] ?? '#3498db');
    $id = $_POST['id'] ?? null;
    
    if (empty($categorie)) {
        $errorMessage = "Le nom de la catégorie est obligatoire.";
        $messageType = 'error';
    } else {
        try {
            if ($id) {
                // Modification
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM helpdesk_cat WHERE CATEGORIE = :categorie AND ID != :id");
                $stmt->execute([':categorie' => $categorie, ':id' => $id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $errorMessage = 'Cette catégorie existe déjà.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE helpdesk_cat SET CATEGORIE = ?, couleur = ? WHERE ID = ?");
                    $stmt->execute([$categorie, $couleur, $id]);
                    $_SESSION['edit_message'] = "Catégorie modifiée avec succès.";
                    echo '<script>window.location.href = "index.php?page=helpdesk_categories";</script>';
                    exit();
                }
            } else {
                // Ajout
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM helpdesk_cat WHERE CATEGORIE = :categorie");
                $stmt->execute([':categorie' => $categorie]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $errorMessage = 'Cette catégorie existe déjà.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO helpdesk_cat (CATEGORIE, couleur) VALUES (?, ?)");
                    $stmt->execute([$categorie, $couleur]);
                    $_SESSION['add_message'] = "Catégorie ajoutée avec succès.";
                    echo '<script>window.location.href = "index.php?page=helpdesk_categories";</script>';
                    exit();
                }
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Récupération des catégories
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT ID, CATEGORIE, couleur FROM helpdesk_cat ORDER BY CATEGORIE ASC");
        $categories = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des catégories : " . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
    $messageType = 'error';
}

// Récupération des données pour modification
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $category) {
        if ($category['ID'] == $editId) {
            $editData = $category;
            break;
        }
    }
}
?>

<style>
/* Styles modernes pour la page catégories helpdesk */
.list-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.form-card h2 {
    margin: 0 0 20px 0;
    font-size: 1.1em;
    color: var(--text-color);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95em;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #e74c3c;
    box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 15px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-icon {
    font-size: 1.5em;
    flex-shrink: 0;
}

.categories-container {
    display: grid;
    gap: 12px;
}

.category-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 15px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.category-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.category-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.category-id {
    font-size: 0.85em;
    color: var(--text-muted);
    background: var(--input-bg);
    padding: 4px 10px;
    border-radius: 6px;
    min-width: 50px;
    text-align: center;
}

.category-name {
    font-weight: 600;
    font-size: 1.05em;
    color: var(--text-color);
}

.category-actions {
    display: flex;
    gap: 10px;
}

.btn-edit {
    background: #3498db;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85em;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-edit:hover {
    background: #2980b9;
}

.btn-delete {
    background: #e74c3c;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85em;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-delete:hover {
    background: #c0392b;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 3.5em;
    margin-bottom: 15px;
    opacity: 0.5;
}

.list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.list-title {
    font-size: 1.2em;
    font-weight: 600;
    color: var(--text-color);
}

.count-badge {
    background: var(--input-bg);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.9em;
    color: var(--text-muted);
}

/* Responsive */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .category-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .category-actions {
        width: 100%;
    }
    
    .btn-edit, .btn-delete {
        flex: 1;
        justify-content: center;
    }
}
</style>

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>📂</span>
            Catégories Helpdesk
        </h1>
    </div>

    <?php if ($sessionMessage): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✅</span>
            <div><?= htmlspecialchars($sessionMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠️</span>
            <div><?= htmlspecialchars($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'ajout/modification -->
    <div class="form-card">
        <h2><?= $editData ? '✏️ Modifier la catégorie' : '➕ Ajouter une catégorie' ?></h2>
        <form method="POST">
            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editData['ID']) ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="categorie">Nom de la catégorie</label>
                <input type="text" 
                       id="categorie" 
                       name="categorie" 
                       class="form-control"
                       required
                       placeholder="Ex: Support technique, Facturation..."
                       value="<?= htmlspecialchars($editData['CATEGORIE'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="couleur">Couleur</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" 
                           id="couleur" 
                           name="couleur" 
                           class="form-control"
                           style="width: 60px; padding: 2px; height: 40px; cursor: pointer;"
                           value="<?= htmlspecialchars($editData['couleur'] ?? '#3498db') ?>">
                    <span style="font-size: 0.9em; color: var(--text-muted);">(Choisir une couleur pour l'affichage)</span>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span><?= $editData ? '✅' : '➕' ?></span>
                    <?= $editData ? 'Modifier' : 'Ajouter' ?>
                </button>
                <?php if ($editData): ?>
                    <a href="index.php?page=helpdesk_categories" class="btn btn-secondary">
                        <span>❌</span>
                        Annuler
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste des catégories -->
    <div class="list-header">
        <div class="list-title">Liste des catégories</div>
        <div class="count-badge"><?= count($categories) ?> catégorie<?= count($categories) > 1 ? 's' : '' ?></div>
    </div>

    <?php if (empty($categories) && empty($errorMessage)): ?>
        <div class="empty-state">
            <div class="empty-icon">📂</div>
            <h3>Aucune catégorie trouvée</h3>
            <p>Ajoutez votre première catégorie helpdesk ci-dessus</p>
        </div>
    <?php else: ?>
        <div class="categories-container">
            <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <div class="category-info">
                        <div class="category-id">#<?= htmlspecialchars((string)($category['ID'] ?? '')) ?></div>
                        <div class="category-color-badge" style="width: 20px; height: 20px; border-radius: 50%; background-color: <?= htmlspecialchars($category['couleur'] ?? '#3498db') ?>; border: 1px solid rgba(0,0,0,0.1);" title="Couleur: <?= htmlspecialchars($category['couleur'] ?? '#3498db') ?>"></div>
                        <div class="category-name"><?= htmlspecialchars($category['CATEGORIE'] ?? '') ?></div>
                    </div>
                    <div class="category-actions">
                        <a href="index.php?page=helpdesk_categories&edit=<?= htmlspecialchars((string)($category['ID'])) ?>" class="btn-edit">
                            <span>✏️</span>
                            Modifier
                        </a>
                        <a href="actions/helpdesk_categories_delete.php?id=<?= htmlspecialchars((string)($category['ID'])) ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');" 
                           class="btn-delete">
                            <span>🗑️</span>
                            Supprimer
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>