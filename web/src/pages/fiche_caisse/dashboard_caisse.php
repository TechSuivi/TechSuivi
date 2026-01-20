<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$stats = [
    'sessions_aujourd_hui' => 0,
    'recettes_aujourd_hui' => 0,
    'total_transactions' => 0,
    'solde_total' => 0,
    'moyens_paiement' => 0
];

$errorMessage = '';

if (isset($pdo)) {
    try {
        // Sessions cyber d'aujourd'hui
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(COALESCE(tarif, 0) + COALESCE(imp, 0)) as recettes FROM FC_cyber WHERE DATE(date_cyber) = CURDATE()");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['sessions_aujourd_hui'] = $result['count'] ?? 0;
        $stats['recettes_aujourd_hui'] = $result['recettes'] ?? 0;
        
        // Total des transactions
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM FC_transactions");
        $result = $stmt->fetch();
        $stats['total_transactions'] = $result['count'] ?? 0;
        
        // Calcul du solde total (entrées - sorties)
        $stmt = $pdo->query("SELECT 
            SUM(CASE WHEN type = 'entree' THEN montant ELSE 0 END) as entrees,
            SUM(CASE WHEN type = 'sortie' THEN montant ELSE 0 END) as sorties
            FROM FC_transactions");
        $result = $stmt->fetch();
        $entrees = $result['entrees'] ?? 0;
        $sorties = $result['sorties'] ?? 0;
        $stats['solde_total'] = $entrees - $sorties;
        
        // Nombre de moyens de paiement configurés
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM FC_moyens_paiement");
        $result = $stmt->fetch();
        $stats['moyens_paiement'] = $result['count'] ?? 0;
        
        // Statistiques des feuilles de caisse
        $stats['feuilles_caisse'] = 0;
        $stats['derniere_feuille'] = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM FC_feuille_caisse");
            $result = $stmt->fetch();
            $stats['feuilles_caisse'] = $result['count'] ?? 0;
            
            // Dernière feuille de caisse
            $stmt = $pdo->query("SELECT total_caisse, date_comptage FROM FC_feuille_caisse ORDER BY date_comptage DESC LIMIT 1");
            $result = $stmt->fetch();
            if ($result) {
                $stats['derniere_feuille'] = $result['total_caisse'];
                $stats['date_derniere_feuille'] = $result['date_comptage'];
            }
        } catch (PDOException $e) {
            // Table n'existe peut-être pas encore
        }
        
        // Dernières sessions cyber
        $stmt = $pdo->query("SELECT nom, ha, hd, imp, imp_c, tarif, date_cyber FROM FC_cyber ORDER BY date_cyber DESC LIMIT 5");
        $dernieres_sessions = $stmt->fetchAll();
        
        // Dernières transactions
        // Dernières transactions
        $stmt = $pdo->query("
            SELECT t.nom, t.montant, t.type, t.date_transaction, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
            ORDER BY t.date_transaction DESC LIMIT 5
        ");
        $dernieres_transactions = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des statistiques : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Fonction pour afficher le prix d'une session
function calculerPrixSession($ha, $hd, $imp, $imp_c, $tarif) {
    // Le prix est maintenant directement stocké dans tarif
    return $tarif ?? 0;
}
?>

<h1>Tableau de Bord - Fiche de Caisse</h1>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
<?php else: ?>

<!-- Statistiques principales -->
<div class="stats-grid">
    <div class="card text-center border-left-green">
        <h3 class="card-title text-green">Sessions Aujourd'hui</h3>
        <p class="kpi-value text-green">
            <?= $stats['sessions_aujourd_hui'] ?>
        </p>
    </div>
    
    <div class="card text-center border-left-blue">
        <h3 class="card-title text-blue">Recettes Aujourd'hui</h3>
        <p class="kpi-value text-blue">
            <?= number_format($stats['recettes_aujourd_hui'], 2) ?> €
        </p>
    </div>
    
    <div class="card text-center border-left-orange">
        <h3 class="card-title text-orange">Total Transactions</h3>
        <p class="kpi-value text-orange">
            <?= $stats['total_transactions'] ?>
        </p>
    </div>
    
    <?php $soldeClass = $stats['solde_total'] >= 0 ? 'text-green' : 'text-red'; 
          $borderClass = $stats['solde_total'] >= 0 ? 'border-left-green' : 'border-left-red'; ?>
    <div class="card text-center <?= $borderClass ?>">
        <h3 class="card-title <?= $soldeClass ?>">Solde Total</h3>
        <p class="kpi-value <?= $soldeClass ?>">
            <?= number_format($stats['solde_total'], 2) ?> €
        </p>
    </div>
    
    <div class="card text-center border-left-purple">
        <h3 class="card-title text-purple">Dernière Feuille</h3>
        <p class="kpi-value text-purple">
            <?= number_format($stats['derniere_feuille'], 2) ?> €
        </p>
        <?php if (isset($stats['date_derniere_feuille'])): ?>
            <small class="text-muted">
                <?= date('d/m/Y', strtotime($stats['date_derniere_feuille'])) ?>
            </small>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<div class="card mb-25">
    <h2>Actions Rapides</h2>
    
    <!-- Rapports & Analyses -->
    <div class="mb-25">
        <h3 class="section-header-sm">
            📈 Rapports & Analyses
        </h3>
        <div class="flex-wrap gap-15" style="display: flex;">
            <a href="index.php?page=resume_journalier" class="button-like bg-orange">
                📄 Résumé Journalier
            </a>
            <a href="index.php?page=tableau_recapitulatif" class="button-like bg-brown">
                📈 Tableau Récapitulatif
            </a>
        </div>
    </div>
    
    <!-- Gestion de Caisse -->
    <div class="mb-25">
        <h3 class="section-header-sm">
            📋 Gestion de Caisse
        </h3>
        <div class="flex-wrap gap-15" style="display: flex;">
            <a href="index.php?page=feuille_caisse_add" class="button-like bg-purple">
                📋 Nouvelle Feuille de Caisse
            </a>
            <a href="index.php?page=feuille_caisse_list" class="button-like bg-deep-purple">
                📚 Historique des Feuilles
            </a>
        </div>
    </div>
    
    <!-- Sessions & Transactions -->
    <div class="mb-25">
        <h3 class="section-header-sm">
            💻 Sessions & Transactions
        </h3>
        <div class="flex-wrap gap-15" style="display: flex;">
            <a href="index.php?page=cyber_add" class="button-like bg-accent">
                ➕ Nouvelle Session Cyber
            </a>
            <a href="index.php?page=transaction_add" class="button-like bg-green">
                💸 Nouvelle Transaction
            </a>
            <a href="index.php?page=moyens_paiement" class="button-like bg-blue">
                ⚙️ Moyens de Paiement
            </a>
        </div>
    </div>
</div>

<div class="two-col-grid">
    <!-- Dernières sessions -->
    <div class="card">
        <h3>Dernières Sessions Cyber</h3>
        <?php if (!empty($dernieres_sessions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Horaires</th>
                        <th class="text-right">Prix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieres_sessions as $session): ?>
                        <tr>
                            <td><?= htmlspecialchars($session['nom'] ?: 'Anonyme') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($session['date_cyber'])) ?></td>
                            <td>
                                <?php if ($session['ha'] && $session['hd']): ?>
                                    <?= date('H:i', strtotime($session['ha'])) ?> - <?= date('H:i', strtotime($session['hd'])) ?>
                                <?php else: ?>
                                    Impression
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-weight-bold">
                                <?= number_format(calculerPrixSession($session['ha'], $session['hd'], $session['imp'], $session['imp_c'], $session['tarif']), 2) ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-center mt-15">
                <a href="index.php?page=cyber_list" class="text-accent">Voir toutes les sessions →</a>
            </p>
        <?php else: ?>
            <p>Aucune session enregistrée.</p>
        <?php endif; ?>
    </div>
    
    <!-- Dernières transactions -->
    <div class="card">
        <h3>Dernières Transactions</h3>
        <?php if (!empty($dernieres_transactions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieres_transactions as $transaction): ?>
                        <tr>
                            <td>
                                <?php if (!empty($transaction['client_nom'])): ?>
                                    <span title="Client lié">👤 <?= htmlspecialchars($transaction['client_nom'] . ' ' . ($transaction['client_prenom'] ?? '')) ?></span>
                                <?php else: ?>
                                    <?= htmlspecialchars($transaction['nom']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($transaction['date_transaction'])) ?></td>
                            <td>
                                <?php $typeClass = $transaction['type'] === 'entree' ? 'bg-green' : ($transaction['type'] === 'sortie' ? 'bg-red' : 'bg-secondary'); ?>
                                <span class="badge <?= $typeClass ?>">
                                    <?= htmlspecialchars(ucfirst($transaction['type'])) ?>
                                </span>
                            </td>
                            <td class="text-right font-weight-bold <?= $transaction['type'] === 'entree' ? 'text-green' : ($transaction['type'] === 'sortie' ? 'text-red' : '') ?>">
                                <?= $transaction['type'] === 'sortie' ? '-' : '' ?><?= number_format($transaction['montant'], 2) ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-center mt-15">
                <a href="index.php?page=transactions_list" class="text-accent">Voir toutes les transactions →</a>
            </p>
        <?php else: ?>
            <p>Aucune transaction enregistrée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Tarifs en vigueur -->
<div class="card mt-25">
    <h3>Tarifs Cyber Café</h3>
    <div class="details-split-grid">
        <div>
            <strong>Temps de connexion :</strong>
            <ul style="margin: 5px 0;">
                <li>10 minutes : 0,50 €</li>
                <li>Quart d'heure : 0,75 €</li>
            </ul>
        </div>
        <div>
            <strong>Impressions :</strong>
            <ul style="margin: 5px 0;">
                <li>Noir et blanc : 0,20 € / page</li>
                <li>Couleur : 0,30 € / page</li>
            </ul>
        </div>
    </div>
</div>

<?php endif; ?>