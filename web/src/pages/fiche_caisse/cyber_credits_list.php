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
// Fonction pour formater le solde avec couleur
function formatSolde($solde) {
    $class = $solde > 0 ? 'text-success' : ($solde < 0 ? 'text-danger' : 'text-muted');
    return '<span class="' . $class . ' font-bold">' . number_format($solde, 2) . ' €</span>';
}
?>



<div class="flex-between-center mb-20">
    <h1 class="m-0">Fiche de Caisse - Crédits Clients</h1>
    <div>
        <a href="index.php?page=cyber_credits_add" class="btn btn-accent mr-10">
            ➕ Nouveau crédit client
        </a>
        <a href="index.php?page=cyber_list" class="btn btn-secondary">
            ← Retour aux sessions cyber
        </a>
    </div>
</div>

<?php if (!empty($sessionMessage)): ?>
    <div class="alert alert-success mb-15">
        <?= htmlspecialchars($sessionMessage) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error mb-15">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<?php if (empty($credits) && empty($errorMessage)): ?>
    <div class="card p-25 text-center">
        <p class="text-muted text-lg mb-20">Aucun crédit client enregistré</p>
        <a href="index.php?page=cyber_credits_add" class="btn btn-accent">
            Créer le premier crédit client
        </a>
    </div>
<?php elseif (!empty($credits)): ?>
    <div class="card overflow-hidden">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-accent text-white">
                    <th class="p-12 text-left border-b border-light">Client</th>
                    <th class="p-12 text-center border-b border-light">Solde</th>
                    <th class="p-12 text-center border-b border-light">Mouvements</th>
                    <th class="p-12 text-center border-b border-light">Dernière activité</th>
                    <th class="p-12 text-center border-b border-light">Créé le</th>
                    <th class="p-12 text-center border-b border-light">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($credits as $credit): ?>
                    <tr class="border-b border-light hover:bg-secondary-light">
                        <td class="p-12 font-bold">
                            <?= htmlspecialchars($credit['nom_client']) ?>
                            <?php if (!empty($credit['notes'])): ?>
                                <br><small class="text-muted font-normal">
                                    <?= htmlspecialchars(substr($credit['notes'], 0, 50)) ?>
                                    <?= strlen($credit['notes']) > 50 ? '...' : '' ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="p-12 text-center">
                            <?= formatSolde($credit['solde_actuel']) ?>
                        </td>
                        <td class="p-12 text-center">
                            <span class="badge badge-secondary">
                                <?= $credit['nb_mouvements'] ?>
                            </span>
                        </td>
                        <td class="p-12 text-center text-muted">
                            <?php if ($credit['derniere_activite']): ?>
                                <?= date('d/m/Y H:i', strtotime($credit['derniere_activite'])) ?>
                            <?php else: ?>
                                Aucune
                            <?php endif; ?>
                        </td>
                        <td class="p-12 text-center text-muted">
                            <?= date('d/m/Y', strtotime($credit['date_creation'])) ?>
                        </td>
                        <td class="p-12 text-center">
                            <div class="flex-center gap-5 flex-wrap">
                                <a href="index.php?page=cyber_credits_add&id=<?= $credit['id'] ?>" 
                                   class="btn btn-xs btn-secondary"
                                   title="Modifier">
                                    ✏️ Modifier
                                </a>
                                <a href="index.php?page=cyber_credits_history&id=<?= $credit['id'] ?>" 
                                   class="btn btn-xs btn-info"
                                   title="Historique">
                                    📊 Historique
                                </a>
                                <?php if ($credit['solde_actuel'] > 0): ?>
                                    <a href="index.php?page=cyber_add&credit_id=<?= $credit['id'] ?>" 
                                       class="btn btn-xs btn-success"
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
    <div class="summary-grid mt-20">
        <?php
        $total_credits = count($credits);
        $total_solde = array_sum(array_column($credits, 'solde_actuel'));
        $credits_positifs = count(array_filter($credits, function($c) { return $c['solde_actuel'] > 0; }));
        $credits_vides = count(array_filter($credits, function($c) { return $c['solde_actuel'] <= 0; }));
        ?>
        
        <div class="card p-15 text-center">
            <div class="text-2xl font-bold text-accent"><?= $total_credits ?></div>
            <div class="text-muted">Total clients</div>
        </div>
        
        <div class="card p-15 text-center">
            <div class="text-2xl font-bold text-success"><?= number_format($total_solde, 2) ?> €</div>
            <div class="text-muted">Solde total</div>
        </div>
        
        <div class="card p-15 text-center">
            <div class="text-2xl font-bold text-info"><?= $credits_positifs ?></div>
            <div class="text-muted">Crédits actifs</div>
        </div>
        
        <div class="card p-15 text-center">
            <div class="text-2xl font-bold text-muted"><?= $credits_vides ?></div>
            <div class="text-muted">Crédits épuisés</div>
        </div>
    </div>
<?php endif; ?>

<div class="card p-20 mt-30">
    <h3 class="mt-0 text-accent">Aide - Système de crédits</h3>
    <ul class="pl-20 mt-10">
        <li class="mb-5"><strong>Installation :</strong> Exécutez d'abord le script <code>db/add_cyber_credits_table.sql</code> dans votre base de données</li>
        <li class="mb-5"><strong>Nouveau crédit :</strong> Créez un compte crédit pour un client avec un solde initial</li>
        <li class="mb-5"><strong>Modifier :</strong> Ajustez le solde ou les informations du client</li>
        <li class="mb-5"><strong>Historique :</strong> Consultez tous les mouvements de crédit d'un client</li>
        <li class="mb-5"><strong>Utiliser :</strong> Créez directement une session cyber en déduisant du crédit</li>
        <li class="mb-5"><strong>Solde positif :</strong> Le client a du crédit disponible</li>
        <li class="mb-5"><strong>Solde négatif :</strong> Le client doit de l'argent (découvert)</li>
    </ul>
</div>