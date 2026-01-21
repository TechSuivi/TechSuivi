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
/* Styles modernes pour la page fournisseurs */
.list-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%);
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

.add-form-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.add-form-card h2 {
    margin: 0 0 15px 0;
    font-size: 1.1em;
    color: var(--text-color);
}

.add-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.form-group {
    flex: 1;
    max-width: 400px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 0.9em;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
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
    border-color: #1abc9c;
    box-shadow: 0 0 0 4px rgba(26, 188, 156, 0.1);
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 188, 156, 0.3);
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
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

.suppliers-container {
    display: grid;
    gap: 12px;
}

.supplier-card {
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

.supplier-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.supplier-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.supplier-id {
    font-size: 0.85em;
    color: var(--text-muted);
    background: var(--input-bg);
    padding: 4px 10px;
    border-radius: 6px;
    min-width: 50px;
    text-align: center;
}

.supplier-name {
    font-weight: 600;
    font-size: 1.05em;
    color: var(--text-color);
}

.btn-delete {
    background: #e74c3c;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    font-size: 0.85em;
    font-weight: 500;
    cursor: pointer;
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
    .add-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-group {
        max-width: none;
    }
    
    .supplier-card {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

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