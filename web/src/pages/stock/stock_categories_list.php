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
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#3498db');
    $default_margin = $_POST['default_margin'] ?? 30.00;
    $id = $_POST['id'] ?? null;
    
    if (empty($name)) {
        $errorMessage = "Le nom de la catégorie est obligatoire.";
    } else {
        try {
            if ($id) {
                // Modification
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_categories WHERE name = :name AND id != :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $errorMessage = 'Cette catégorie existe déjà.';
                } else {
                    $stmt = $pdo->prepare("UPDATE stock_categories SET name = ?, color = ?, default_margin = ? WHERE id = ?");
                    $stmt->execute([$name, $color, $default_margin, $id]);
                    $_SESSION['edit_message'] = "Catégorie modifiée avec succès.";
                    echo '<script>window.location.href = "index.php?page=stock_categories_list";</script>';
                    exit();
                }
            } else {
                // Ajout
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_categories WHERE name = :name");
                $stmt->execute([':name' => $name]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $errorMessage = 'Cette catégorie existe déjà.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO stock_categories (name, color, default_margin) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $color, $default_margin]);
                    $_SESSION['add_message'] = "Catégorie ajoutée avec succès.";
                    echo '<script>window.location.href = "index.php?page=stock_categories_list";</script>';
                    exit();
                }
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
        }
    }
}

// Récupération des catégories
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM stock_categories ORDER BY name ASC");
        $categories = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des catégories (Table manquante ?) : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Récupération des données pour modification
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $category) {
        if ($category['id'] == $editId) {
            $editData = $category;
            break;
        }
    }
}
?>

<div class="container container-center max-w-800">
    <div class="page-header">
        <h1>
            <span>📦</span>
            Catégories Stock
        </h1>
    </div>

    <?php if ($sessionMessage): ?>
        <div class="alert alert-success mb-20">
            <span class="mr-10">✅</span>
            <div><?= htmlspecialchars($sessionMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger mb-20">
            <span class="mr-10">⚠️</span>
            <div><?= htmlspecialchars($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'ajout/modification -->
    <div class="card bg-secondary border mb-30">
        <h2 class="mt-0 mb-20 text-lg flex items-center gap-10">
            <?= $editData ? '✏️ Modifier la catégorie' : '➕ Ajouter une catégorie' ?>
        </h2>
        <form method="POST">
            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">
            <?php endif; ?>
            
            <div class="mb-20">
                <label for="name" class="block mb-8 font-bold">Nom de la catégorie</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-control w-full p-10 border rounded-8 bg-input text-dark"
                       required
                       placeholder="Ex: Composants, Périphériques, Câbles..."
                       value="<?= htmlspecialchars($editData['name'] ?? '') ?>">
            </div>

            <div class="mb-20">
                <label for="default_margin" class="block mb-8 font-bold">Marge par défaut (%)</label>
                <div class="flex items-center gap-10">
                    <input type="number" 
                           id="default_margin" 
                           name="default_margin" 
                           class="form-control w-100 p-10 border rounded-8 bg-input text-dark"
                           step="0.01"
                           min="0"
                           value="<?= htmlspecialchars($editData['default_margin'] ?? '30.00') ?>">
                    <span class="text-sm text-muted opacity-80">%</span>
                </div>
            </div>

            <div class="mb-20">
                <label for="color" class="block mb-8 font-bold">Couleur</label>
                <div class="flex items-center gap-10">
                    <input type="color" 
                           id="color" 
                           name="color" 
                           class="h-40 w-60 p-2 cursor-pointer border rounded-4 bg-input"
                           value="<?= htmlspecialchars($editData['color'] ?? '#3498db') ?>">
                    <span class="text-sm text-muted opacity-80">(Choisir une couleur pour l'affichage)</span>
                </div>
            </div>
            
            <div class="flex gap-15 pt-15 border-t border-border">
                <button type="submit" class="btn btn-primary">
                    <span><?= $editData ? '✅' : '➕' ?></span>
                    <?= $editData ? 'Modifier' : 'Ajouter' ?>
                </button>
                <?php if ($editData): ?>
                    <a href="index.php?page=stock_categories_list" class="btn btn-secondary">
                        <span>❌</span>
                        Annuler
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste des catégories -->
    <div class="flex justify-between items-center mb-20">
        <div class="text-lg font-bold">Liste des catégories</div>
        <div class="bg-light px-12 py-4 rounded-12 text-sm text-muted font-bold shadow-sm border border-border">
            <?= count($categories) ?> catégorie<?= count($categories) > 1 ? 's' : '' ?>
        </div>
    </div>

    <?php if (empty($categories) && empty($errorMessage)): ?>
        <div class="card text-center p-40 border-dashed">
            <div class="text-4xl mb-15 opacity-50">📦</div>
            <h3 class="mt-0">Aucune catégorie trouvée</h3>
            <p class="text-muted">Ajoutez votre première catégorie de stock ci-dessus</p>
        </div>
    <?php else: ?>
        <div class="grid gap-12">
            <?php foreach ($categories as $category): ?>
                <div class="card p-15 flex items-center justify-between gap-15 hover:shadow-md transition-transform transform hover:translate-x-1 border">
                    <div class="flex items-center gap-15 flex-1">
                        <div class="w-20 h-20 rounded-50 shadow-sm border border-black-opacity-10" style="background-color: <?= htmlspecialchars($category['color'] ?? '#3498db') ?>;" title="Couleur: <?= htmlspecialchars($category['color'] ?? '#3498db') ?>"></div>
                        <div class="font-bold text-lg"><?= htmlspecialchars($category['name'] ?? '') ?></div>
                        <div class="text-xs bg-light border px-5 py-2 rounded text-muted font-bold ml-10">Marge: <?= htmlspecialchars($category['default_margin'] ?? '30.00') ?>%</div>
                    </div>
                    <div class="flex gap-10">
                        <a href="index.php?page=stock_categories_list&edit=<?= htmlspecialchars((string)($category['id'])) ?>" class="btn btn-sm btn-primary flex items-center gap-5">
                            <span>✏️</span>
                            <span class="hidden sm:inline">Modifier</span>
                        </a>
                        <!-- Suppression pas implémentée pour l'instant (nécessite action séparée) -->
                        <!-- 
                        <a href="actions/stock_categories_delete.php?id=<?= htmlspecialchars((string)($category['id'])) ?>" 
                           onclick="return confirm('Êtes-vous sûr ?');" 
                           class="btn btn-sm btn-danger flex items-center gap-5">
                            <span>🗑️</span>
                        </a> 
                        -->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
