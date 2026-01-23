<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// La connexion $pdo et la session sont gérées par index.php
$moyens_paiement = [];
$errorMessage = '';
$messageType = '';
$sessionMessage = '';

// Gestion des messages de session
if (isset($_SESSION['moyens_paiement_message'])) {
    $sessionMessage = $_SESSION['moyens_paiement_message'];
    unset($_SESSION['moyens_paiement_message']);
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moyen = trim($_POST['moyen'] ?? '');
    $id = $_POST['id'] ?? null;
    
    if (empty($moyen)) {
        $errorMessage = "Le nom du moyen de paiement est obligatoire.";
        $messageType = 'error';
    } else {
        try {
            if ($id) {
                // Modification
                $stmt = $pdo->prepare("UPDATE FC_moyens_paiement SET moyen = ? WHERE id = ?");
                $stmt->execute([$moyen, $id]);
                $_SESSION['moyens_paiement_message'] = "Moyen de paiement modifié avec succès.";
            } else {
                // Ajout
                $stmt = $pdo->prepare("INSERT INTO FC_moyens_paiement (moyen) VALUES (?)");
                $stmt->execute([$moyen]);
                $_SESSION['moyens_paiement_message'] = "Moyen de paiement ajouté avec succès.";
            }
            echo '<script>window.location.href = "index.php?page=moyens_paiement";</script>';
            exit();
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Récupération des moyens de paiement
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT id, moyen FROM FC_moyens_paiement ORDER BY moyen ASC");
        $moyens_paiement = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des moyens de paiement : " . htmlspecialchars($e->getMessage());
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
    foreach ($moyens_paiement as $moyen) {
        if ($moyen['id'] == $editId) {
            $editData = $moyen;
            break;
        }
    }
}
?>

<!-- Custom Styles Moved to caisse.css -->



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
            <span>💳</span>
            Gestion des Moyens de Paiement
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

    <div class="grid-layout-custom">
        <!-- Colonne Gauche : Formulaire -->
        <div>
            <div class="card p-25 sticky top-20">
                <h2 class="text-lg m-0 mb-20"><?= $editData ? '✏️ Modifier' : '➕ Ajouter' ?></h2>
                <form method="POST">
                    <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid mb-20">
                        <label for="moyen" class="block font-medium mb-8">Moyen de paiement</label>
                        <input type="text" 
                               id="moyen" 
                               name="moyen" 
                               class="form-control"
                               required
                               placeholder="Ex: Carte bancaire..."
                               value="<?= htmlspecialchars($editData['moyen'] ?? '') ?>">
                    </div>
                    
                    <div class="flex gap-12 mt-15">
                        <button type="submit" class="btn btn-accent w-full justify-center">
                            <span><?= $editData ? '✅' : '➕' ?></span>
                            <?= $editData ? 'Modifier' : 'Ajouter' ?>
                        </button>
                        <?php if ($editData): ?>
                            <a href="index.php?page=moyens_paiement" class="btn btn-secondary">
                                ❌
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colonne Droite : Liste -->
        <div>
            <?php if (empty($moyens_paiement) && empty($errorMessage)): ?>
                <div class="empty-state-card text-center p-30 border-dashed">
                    <div class="empty-state-icon text-3xl mb-10">💳</div>
                    <h3>Aucun moyen de paiement configuré</h3>
                    <p class="text-muted">Ajoutez votre premier moyen de paiement ci-contre</p>
                </div>
            <?php elseif (!empty($moyens_paiement)): ?>
                <div class="table-responsive">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Moyen de paiement</th>
                                <th class="text-right" style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moyens_paiement as $moyen): ?>
                                <tr class="border-b border-border hover:bg-hover">
                                    <td class="p-10 text-muted">#<?= htmlspecialchars($moyen['id']) ?></td>
                                    <td class="p-10 font-bold"><?= htmlspecialchars($moyen['moyen']) ?></td>
                                    <td class="p-10 text-right">
                                        <a href="index.php?page=moyens_paiement&edit=<?= htmlspecialchars($moyen['id']) ?>" class="btn-sm-action text-info border-info hover:bg-info hover:text-white" title="Modifier">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
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