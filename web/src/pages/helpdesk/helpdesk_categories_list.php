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
    .grid-layout-custom {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }
    @media (min-width: 768px) {
        .grid-layout-custom {
            grid-template-columns: 1fr 2fr;
        }
    }
</style>

<div class="container max-w-1600">
    <div class="page-header">
        <h1>
            <span>📂</span>
            Catégories Helpdesk
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

    <div class="grid-layout-custom">
        <!-- Colonne Gauche : Formulaire -->
        <div>
            <div class="card bg-secondary border sticky top-20">
                <h2 class="mt-0 mb-20 text-lg flex items-center gap-10">
                    <?= $editData ? '✏️ Modifier' : '➕ Ajouter' ?>
                </h2>
                <form method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($editData['ID']) ?>">
                    <?php endif; ?>
                    
                    <div class="mb-20">
                        <label for="categorie" class="block mb-8 font-bold">Nom</label>
                        <input type="text" 
                               id="categorie" 
                               name="categorie" 
                               class="form-control w-full p-10 border rounded-8 bg-input text-dark"
                               required
                               placeholder="Ex: Support..."
                               value="<?= htmlspecialchars($editData['CATEGORIE'] ?? '') ?>">
                    </div>

                    <div class="mb-20">
                        <label for="couleur" class="block mb-8 font-bold">Couleur</label>
                        <div class="flex items-center gap-10">
                            <input type="color" 
                                   id="couleur" 
                                   name="couleur" 
                                   class="h-40 w-full p-2 cursor-pointer border rounded-4 bg-input"
                                   value="<?= htmlspecialchars($editData['couleur'] ?? '#3498db') ?>">
                        </div>
                    </div>
                    
                    <div class="flex gap-15 pt-15 border-t border-border">
                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <span><?= $editData ? '✅' : '➕' ?></span>
                            <?= $editData ? 'Enregistrer' : 'Ajouter' ?>
                        </button>
                        <?php if ($editData): ?>
                            <a href="index.php?page=helpdesk_categories" class="btn btn-secondary">
                                ❌
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colonne Droite : Liste -->
        <div>
            <?php if (empty($categories) && empty($errorMessage)): ?>
                <div class="card text-center p-40 border-dashed">
                    <div class="text-4xl mb-15 opacity-50">📂</div>
                    <h3 class="mt-0">Aucune catégorie trouvée</h3>
                    <p class="text-muted">Ajoutez votre première catégorie helpdesk ci-contre</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 50px;">Couleur</th>
                                <th>Nom de la catégorie</th>
                                <th class="text-right" style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr class="border-b border-border hover:bg-hover transition-colors">
                                    <td class="p-10 font-mono text-muted text-sm text-center">#<?= htmlspecialchars((string)($category['ID'] ?? '')) ?></td>
                                    <td class="p-10 text-center">
                                        <div class="rounded-full shadow-sm border border-black-opacity-10 mx-auto" style="width: 20px; height: 20px; background-color: <?= htmlspecialchars($category['couleur'] ?? '#3498db') ?>;" title="Couleur: <?= htmlspecialchars($category['couleur'] ?? '#3498db') ?>"></div>
                                    </td>
                                    <td class="p-10 font-bold"><?= htmlspecialchars($category['CATEGORIE'] ?? '') ?></td>
                                    <td class="p-10 text-right">
                                        <div class="flex justify-end gap-10">
                                            <a href="index.php?page=helpdesk_categories&edit=<?= htmlspecialchars((string)($category['ID'])) ?>" class="btn-sm-action text-info border-info hover:bg-info hover:text-white" title="Modifier">
                                                <i class="fas fa-pencil-alt"></i>
                                            </a>
                                            <a href="actions/helpdesk_categories_delete.php?id=<?= htmlspecialchars((string)($category['ID'])) ?>" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');" 
                                               class="btn-sm-action text-danger border-danger hover:bg-danger hover:text-white" title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
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
</div>