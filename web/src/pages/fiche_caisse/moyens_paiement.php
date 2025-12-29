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

<style>
/* Styles modernes pour la page moyens de paiement */
.list-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
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
    border-color: #f39c12;
    box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.1);
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
    background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(243, 156, 18, 0.3);
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

.payments-container {
    display: grid;
    gap: 12px;
}

.payment-card {
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

.payment-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.payment-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.payment-id {
    font-size: 0.85em;
    color: var(--text-muted);
    background: var(--input-bg);
    padding: 4px 10px;
    border-radius: 6px;
    min-width: 50px;
    text-align: center;
}

.payment-name {
    font-weight: 600;
    font-size: 1.05em;
    color: var(--text-color);
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
    
    .payment-card {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="list-page">
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

    <!-- Formulaire d'ajout/modification -->
    <div class="form-card">
        <h2><?= $editData ? '✏️ Modifier le moyen de paiement' : '➕ Ajouter un moyen de paiement' ?></h2>
        <form method="POST">
            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="moyen">Moyen de paiement</label>
                <input type="text" 
                       id="moyen" 
                       name="moyen" 
                       class="form-control"
                       required
                       placeholder="Ex: Carte bancaire, Espèces, Chèque..."
                       value="<?= htmlspecialchars($editData['moyen'] ?? '') ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span><?= $editData ? '✅' : '➕' ?></span>
                    <?= $editData ? 'Modifier' : 'Ajouter' ?>
                </button>
                <?php if ($editData): ?>
                    <a href="index.php?page=moyens_paiement" class="btn btn-secondary">
                        <span>❌</span>
                        Annuler
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste des moyens de paiement -->
    <div class="list-header">
        <div class="list-title">Liste des moyens de paiement</div>
        <div class="count-badge"><?= count($moyens_paiement) ?> moyen<?= count($moyens_paiement) > 1 ? 's' : '' ?></div>
    </div>

    <?php if (empty($moyens_paiement) && empty($errorMessage)): ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <h3>Aucun moyen de paiement configuré</h3>
            <p>Ajoutez votre premier moyen de paiement ci-dessus</p>
        </div>
    <?php elseif (!empty($moyens_paiement)): ?>
        <div class="payments-container">
            <?php foreach ($moyens_paiement as $moyen): ?>
                <div class="payment-card">
                    <div class="payment-info">
                        <div class="payment-id">#<?= htmlspecialchars($moyen['id']) ?></div>
                        <div class="payment-name"><?= htmlspecialchars($moyen['moyen']) ?></div>
                    </div>
                    <a href="index.php?page=moyens_paiement&edit=<?= htmlspecialchars($moyen['id']) ?>" class="btn-edit">
                        <span>✏️</span>
                        Modifier
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>