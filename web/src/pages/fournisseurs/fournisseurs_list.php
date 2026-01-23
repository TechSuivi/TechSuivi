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

.add-form-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-sm);
}

.add-form-card h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.3rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
}

.add-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 500;
    color: var(--text-color);
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

.list-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--text-color);
}

.count-badge {
    background: var(--accent-color);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-muted);
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px dashed var(--border-color);
}
.empty-icon {
    font-size: 3rem;
    opacity: 0.5;
    margin-bottom: 10px;
}
</style>

<div class="container max-w-1600">
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

    <div class="grid-layout-custom">
        <!-- Colonne Gauche : Formulaire -->
        <div>
            <div class="add-form-card sticky top-20">
                <h2>➕ Ajouter un fournisseur</h2>
                <form method="POST" class="add-form">
                    <div class="form-group">
                        <label for="fournisseur">Nom du fournisseur</label>
                        <input type="text" id="fournisseur" name="fournisseur" class="form-control" required placeholder="Ex: Fournisseur XYZ">
                    </div>
                    <button type="submit" name="add_fournisseur" class="btn btn-primary w-full justify-center">
                        <span>✅</span>
                        Ajouter
                    </button>
                </form>
            </div>
        </div>

        <!-- Colonne Droite : Liste -->
        <div>
            <?php if (empty($fournisseurs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏢</div>
                    <h3>Aucun fournisseur trouvé</h3>
                    <p>Ajoutez votre premier fournisseur ci-contre</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Nom du Fournisseur</th>
                                <th class="text-right" style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <tr>
                                    <td class="text-muted">#<?= htmlspecialchars($fournisseur['ID']) ?></td>
                                    <td class="font-bold"><?= htmlspecialchars($fournisseur['Fournisseur']) ?></td>
                                    <td class="text-right">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
                                            <input type="hidden" name="fournisseur_id" value="<?= htmlspecialchars($fournisseur['ID']) ?>">
                                            <button type="submit" name="delete_fournisseur" class="btn-sm-action text-danger border-danger hover:bg-danger hover:text-white" title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
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