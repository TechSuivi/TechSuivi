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
    <p style="color: red;"><?= htmlspecialchars($errorMessage) ?></p>
<?php else: ?>

<!-- Statistiques principales -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #4CAF50;">
        <h3 style="margin: 0; color: #4CAF50;">Sessions Aujourd'hui</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: #4CAF50;">
            <?= $stats['sessions_aujourd_hui'] ?>
        </p>
    </div>
    
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #2196F3;">
        <h3 style="margin: 0; color: #2196F3;">Recettes Aujourd'hui</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: #2196F3;">
            <?= number_format($stats['recettes_aujourd_hui'], 2) ?> €
        </p>
    </div>
    
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #FF9800;">
        <h3 style="margin: 0; color: #FF9800;">Total Transactions</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: #FF9800;">
            <?= $stats['total_transactions'] ?>
        </p>
    </div>
    
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid <?= $stats['solde_total'] >= 0 ? '#4CAF50' : '#F44336' ?>;">
        <h3 style="margin: 0; color: <?= $stats['solde_total'] >= 0 ? '#4CAF50' : '#F44336' ?>;">Solde Total</h3>
        <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: <?= $stats['solde_total'] >= 0 ? '#4CAF50' : '#F44336' ?>;">
            <?= number_format($stats['solde_total'], 2) ?> €
        </p>
    </div>
    
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #9C27B0;">
        <h3 style="margin: 0; color: #9C27B0;">Dernière Feuille</h3>
        <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: #9C27B0;">
            <?= number_format($stats['derniere_feuille'], 2) ?> €
        </p>
        <?php if (isset($stats['date_derniere_feuille'])): ?>
            <small style="color: #666;">
                <?= date('d/m/Y', strtotime($stats['date_derniere_feuille'])) ?>
            </small>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-bottom: 30px;">
    <h2>Actions Rapides</h2>
    
    <!-- Rapports & Analyses -->
    <div style="margin-bottom: 20px;">
        <h3 style="font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">
            📈 Rapports & Analyses
        </h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="index.php?page=resume_journalier" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #FF5722; color: white; border-radius: 4px; font-size: 14px;">
                📄 Résumé Journalier
            </a>
            <a href="index.php?page=tableau_recapitulatif" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #795548; color: white; border-radius: 4px; font-size: 14px;">
                📈 Tableau Récapitulatif
            </a>
        </div>
    </div>
    
    <!-- Gestion de Caisse -->
    <div style="margin-bottom: 20px;">
        <h3 style="font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">
            📋 Gestion de Caisse
        </h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="index.php?page=feuille_caisse_add" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #9C27B0; color: white; border-radius: 4px; font-size: 14px;">
                📋 Nouvelle Feuille de Caisse
            </a>
            <a href="index.php?page=feuille_caisse_list" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #673AB7; color: white; border-radius: 4px; font-size: 14px;">
                📚 Historique des Feuilles
            </a>
        </div>
    </div>
    
    <!-- Sessions & Transactions -->
    <div style="margin-bottom: 20px;">
        <h3 style="font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">
            💻 Sessions & Transactions
        </h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="index.php?page=cyber_add" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: var(--accent-color); color: white; border-radius: 4px; font-size: 14px;">
                ➕ Nouvelle Session Cyber
            </a>
            <a href="index.php?page=transaction_add" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #4CAF50; color: white; border-radius: 4px; font-size: 14px;">
                💸 Nouvelle Transaction
            </a>
            <a href="index.php?page=moyens_paiement" class="button-like" style="text-decoration: none; padding: 10px 16px; background-color: #2196F3; color: white; border-radius: 4px; font-size: 14px;">
                ⚙️ Moyens de Paiement
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Dernières sessions -->
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px;">
        <h3>Dernières Sessions Cyber</h3>
        <?php if (!empty($dernieres_sessions)): ?>
            <table style="width: 100%; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Client</th>
                        <th style="text-align: left;">Date</th>
                        <th style="text-align: left;">Horaires</th>
                        <th style="text-align: right;">Prix</th>
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
                            <td style="text-align: right; font-weight: bold;">
                                <?= number_format(calculerPrixSession($session['ha'], $session['hd'], $session['imp'], $session['imp_c'], $session['tarif']), 2) ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align: center; margin-top: 15px;">
                <a href="index.php?page=cyber_list" style="color: var(--accent-color);">Voir toutes les sessions →</a>
            </p>
        <?php else: ?>
            <p>Aucune session enregistrée.</p>
        <?php endif; ?>
    </div>
    
    <!-- Dernières transactions -->
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px;">
        <h3>Dernières Transactions</h3>
        <?php if (!empty($dernieres_transactions)): ?>
            <table style="width: 100%; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Description</th>
                        <th style="text-align: left;">Date</th>
                        <th style="text-align: left;">Type</th>
                        <th style="text-align: right;">Montant</th>
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
                                <span style="padding: 2px 6px; border-radius: 8px; font-size: 11px; color: white;
                                             background-color: <?= $transaction['type'] === 'entree' ? 'green' : ($transaction['type'] === 'sortie' ? 'red' : 'gray') ?>;">
                                    <?= htmlspecialchars(ucfirst($transaction['type'])) ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: bold; color: <?= $transaction['type'] === 'entree' ? 'green' : ($transaction['type'] === 'sortie' ? 'red' : 'inherit') ?>;">
                                <?= $transaction['type'] === 'sortie' ? '-' : '' ?><?= number_format($transaction['montant'], 2) ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align: center; margin-top: 15px;">
                <a href="index.php?page=transactions_list" style="color: var(--accent-color);">Voir toutes les transactions →</a>
            </p>
        <?php else: ?>
            <p>Aucune transaction enregistrée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Tarifs en vigueur -->
<div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-top: 20px;">
    <h3>Tarifs Cyber Café</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
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