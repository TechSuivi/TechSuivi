<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$credits = [];
$errorMessage = '';
$sessionMessage = '';

// Gestion des messages de session
if (isset($_SESSION['credits_message'])) {
    $sessionMessage = $_SESSION['credits_message'];
    unset($_SESSION['credits_message']);
}

// Récupération des crédits clients
if (isset($pdo)) {
    try {
        // Vérifier d'abord si les tables existent
        $stmt = $pdo->query("SHOW TABLES LIKE 'FC_cyber_credits'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            $errorMessage = "⚠️ <strong>Tables manquantes :</strong> Les tables de crédits n'existent pas encore.<br><br>
                           <strong>Solution :</strong> Vous devez d'abord exécuter le script SQL suivant dans votre base de données :<br>
                           <code style='background-color: #f0f0f0; padding: 5px; border-radius: 3px;'>db/add_cyber_credits_table.sql</code><br><br>
                           Ce script créera les tables <code>FC_cyber_credits</code> et <code>FC_cyber_credits_historique</code> nécessaires au fonctionnement du système de crédits.";
        } else {
            $stmt = $pdo->query("
                SELECT c.*, 
                       COUNT(h.id) as nb_mouvements,
                       MAX(h.date_mouvement) as derniere_activite
                FROM FC_cyber_credits c 
                LEFT JOIN FC_cyber_credits_historique h ON c.id = h.credit_id 
                WHERE c.actif = 1 
                GROUP BY c.id 
                ORDER BY c.nom_client ASC
            ");
            $credits = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des crédits : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Fonction pour formater le solde avec couleur
function formatSolde($solde) {
    $color = $solde > 0 ? 'var(--success-color, #28a745)' : ($solde < 0 ? 'var(--danger-color, #dc3545)' : 'var(--text-secondary)');
    return '<span style="color: ' . $color . '; font-weight: bold;">' . number_format($solde, 2) . ' €</span>';
}
?>

<h1>Fiche de Caisse - Crédits Clients</h1>

<div style="margin-bottom: 20px;">
    <a href="index.php?page=cyber_credits_add" class="button-like" style="text-decoration: none; padding: 8px 15px; background-color: var(--accent-color); color: white; border-radius: 4px; margin-right: 10px;">
        ➕ Nouveau crédit client
    </a>
    <a href="index.php?page=cyber_list" style="color: var(--accent-color); text-decoration: none;">
        ← Retour aux sessions cyber
    </a>
</div>

<?php if (!empty($sessionMessage)): ?>
    <div style="color: green; margin-bottom: 15px; padding: 10px; border: 1px solid green; background-color: #e6ffe6; border-radius: 4px;">
        <?= htmlspecialchars($sessionMessage) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div style="color: red; margin-bottom: 15px; padding: 15px; border: 1px solid red; background-color: #ffe6e6; border-radius: 4px;">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<?php if (empty($credits) && empty($errorMessage)): ?>
    <div style="text-align: center; padding: 40px; background-color: var(--card-bg); border-radius: 8px;">
        <p style="color: var(--text-secondary); font-size: 18px; margin-bottom: 20px;">Aucun crédit client enregistré</p>
        <a href="index.php?page=cyber_credits_add" class="button-like" style="text-decoration: none; padding: 12px 24px; background-color: var(--accent-color); color: white; border-radius: 4px;">
            Créer le premier crédit client
        </a>
    </div>
<?php elseif (!empty($credits)): ?>
    <div style="background-color: var(--card-bg); border-radius: 8px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: var(--accent-color); color: white;">
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Client</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Solde</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Mouvements</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Dernière activité</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Créé le</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($credits as $credit): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; font-weight: bold;">
                            <?= htmlspecialchars($credit['nom_client']) ?>
                            <?php if (!empty($credit['notes'])): ?>
                                <br><small style="color: var(--text-secondary); font-weight: normal;">
                                    <?= htmlspecialchars(substr($credit['notes'], 0, 50)) ?>
                                    <?= strlen($credit['notes']) > 50 ? '...' : '' ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?= formatSolde($credit['solde_actuel']) ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background-color: var(--secondary-color); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                <?= $credit['nb_mouvements'] ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center; color: var(--text-secondary);">
                            <?php if ($credit['derniere_activite']): ?>
                                <?= date('d/m/Y H:i', strtotime($credit['derniere_activite'])) ?>
                            <?php else: ?>
                                Aucune
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center; color: var(--text-secondary);">
                            <?= date('d/m/Y', strtotime($credit['date_creation'])) ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                <a href="index.php?page=cyber_credits_add&id=<?= $credit['id'] ?>" 
                                   style="padding: 4px 8px; background-color: var(--secondary-color); color: white; text-decoration: none; border-radius: 3px; font-size: 12px;"
                                   title="Modifier">
                                    ✏️ Modifier
                                </a>
                                <a href="index.php?page=cyber_credits_history&id=<?= $credit['id'] ?>" 
                                   style="padding: 4px 8px; background-color: var(--info-color, #17a2b8); color: white; text-decoration: none; border-radius: 3px; font-size: 12px;"
                                   title="Historique">
                                    📊 Historique
                                </a>
                                <?php if ($credit['solde_actuel'] > 0): ?>
                                    <a href="index.php?page=cyber_add&credit_id=<?= $credit['id'] ?>" 
                                       style="padding: 4px 8px; background-color: var(--success-color, #28a745); color: white; text-decoration: none; border-radius: 3px; font-size: 12px;"
                                       title="Utiliser le crédit">
                                        💳 Utiliser
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Statistiques -->
    <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <?php
        $total_credits = count($credits);
        $total_solde = array_sum(array_column($credits, 'solde_actuel'));
        $credits_positifs = count(array_filter($credits, function($c) { return $c['solde_actuel'] > 0; }));
        $credits_vides = count(array_filter($credits, function($c) { return $c['solde_actuel'] <= 0; }));
        ?>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: var(--accent-color);"><?= $total_credits ?></div>
            <div style="color: var(--text-secondary);">Total clients</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: var(--success-color, #28a745);"><?= number_format($total_solde, 2) ?> €</div>
            <div style="color: var(--text-secondary);">Solde total</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: var(--info-color, #17a2b8);"><?= $credits_positifs ?></div>
            <div style="color: var(--text-secondary);">Crédits actifs</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: var(--text-secondary);"><?= $credits_vides ?></div>
            <div style="color: var(--text-secondary);">Crédits épuisés</div>
        </div>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background-color: var(--card-bg); border-radius: 8px;">
    <h3>Aide - Système de crédits</h3>
    <ul>
        <li><strong>Installation :</strong> Exécutez d'abord le script <code>db/add_cyber_credits_table.sql</code> dans votre base de données</li>
        <li><strong>Nouveau crédit :</strong> Créez un compte crédit pour un client avec un solde initial</li>
        <li><strong>Modifier :</strong> Ajustez le solde ou les informations du client</li>
        <li><strong>Historique :</strong> Consultez tous les mouvements de crédit d'un client</li>
        <li><strong>Utiliser :</strong> Créez directement une session cyber en déduisant du crédit</li>
        <li><strong>Solde positif :</strong> Le client a du crédit disponible</li>
        <li><strong>Solde négatif :</strong> Le client doit de l'argent (découvert)</li>
    </ul>
</div>