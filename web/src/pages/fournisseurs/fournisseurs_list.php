<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// La connexion $pdo et la session sont gérées par index.php
$fournisseurs = [];
$message = '';
$messageType = '';

// Traitement de l'ajout d'un fournisseur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fournisseur']) && isset($pdo)) {
    $fournisseur = trim($_POST['fournisseur'] ?? '');
    
    if (empty($fournisseur)) {
        $message = 'Le nom du fournisseur est obligatoire.';
        $messageType = 'error';
    } else {
        try {
            // Vérifier si le fournisseur existe déjà
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM fournisseur WHERE LOWER(Fournisseur) = LOWER(:fournisseur)");
            $checkStmt->execute([':fournisseur' => $fournisseur]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                $message = 'Ce fournisseur existe déjà dans la base de données.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fournisseur (Fournisseur) VALUES (:fournisseur)");
                if ($stmt->execute([':fournisseur' => $fournisseur])) {
                    $message = 'Fournisseur ajouté avec succès !';
                    $messageType = 'success';
                } else {
                    $message = 'Erreur lors de l\'ajout du fournisseur.';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            $message = 'Erreur de base de données : ' . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Traitement de la suppression d'un fournisseur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fournisseur']) && isset($pdo)) {
    $fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0);
    
    if ($fournisseur_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM fournisseur WHERE ID = :id");
            if ($stmt->execute([':id' => $fournisseur_id])) {
                $message = 'Fournisseur supprimé avec succès !';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la suppression du fournisseur.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Erreur de base de données : ' . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Récupération de la liste des fournisseurs
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT ID, Fournisseur FROM fournisseur ORDER BY Fournisseur ASC");
        $fournisseurs = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = 'Erreur lors de la récupération des fournisseurs : ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}
?>

<!-- Inline CSS Removed for Audit -->

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>🏢</span>
            Gestion des Fournisseurs
        </h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <span class="alert-icon"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'ajout -->
    <div class="add-form-card">
        <h2>➕ Ajouter un nouveau fournisseur</h2>
        <form method="POST" class="add-form">
            <div class="form-group">
                <label for="fournisseur">Nom du fournisseur</label>
                <input type="text" id="fournisseur" name="fournisseur" class="form-control" required placeholder="Ex: Fournisseur XYZ">
            </div>
            <button type="submit" name="add_fournisseur" class="btn btn-primary">
                <span>✅</span>
                Ajouter
            </button>
        </form>
    </div>

    <!-- Liste des fournisseurs -->
    <div class="list-header">
        <div class="list-title">Liste des fournisseurs</div>
        <div class="count-badge"><?= count($fournisseurs) ?> fournisseur<?= count($fournisseurs) > 1 ? 's' : '' ?></div>
    </div>

    <?php if (empty($fournisseurs)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏢</div>
            <h3>Aucun fournisseur trouvé</h3>
            <p>Ajoutez votre premier fournisseur ci-dessus</p>
        </div>
    <?php else: ?>
        <div class="suppliers-container">
            <?php foreach ($fournisseurs as $fournisseur): ?>
                <div class="supplier-card">
                    <div class="supplier-info">
                        <div class="supplier-id">#<?= htmlspecialchars($fournisseur['ID']) ?></div>
                        <div class="supplier-name"><?= htmlspecialchars($fournisseur['Fournisseur']) ?></div>
                    </div>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
                        <input type="hidden" name="fournisseur_id" value="<?= htmlspecialchars($fournisseur['ID']) ?>">
                        <button type="submit" name="delete_fournisseur" class="btn-delete">
                            <span>🗑️</span>
                            Supprimer
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>