<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';

// Récupération des paramètres de dates
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois courant par défaut
$date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Dernier jour du mois courant par défaut

// Récupération des paramètres d'affichage
// Si le formulaire a été soumis (présence de date_debut ou date_fin), on utilise les valeurs des checkboxes
// Sinon, on affiche tout par défaut
$form_submitted = isset($_GET['date_debut']) || isset($_GET['date_fin']);

if ($form_submitted) {
    // Le formulaire a été soumis, on utilise les valeurs des checkboxes
    $afficher_cyber = isset($_GET['afficher_cyber']) && $_GET['afficher_cyber'] == '1';
    $afficher_transactions = isset($_GET['afficher_transactions']) && $_GET['afficher_transactions'] == '1';
    
    // Si aucune case n'est cochée, afficher les deux par défaut
    if (!$afficher_cyber && !$afficher_transactions) {
        $afficher_cyber = true;
        $afficher_transactions = true;
    }
} else {
    // Premier chargement de la page, afficher tout par défaut
    $afficher_cyber = true;
    $afficher_transactions = true;
}

// Validation des dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
    $date_debut = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
    $date_fin = date('Y-m-t');
}

// S'assurer que la date de fin est après la date de début
if (strtotime($date_fin) < strtotime($date_debut)) {
    $date_fin = $date_debut;
}

$recapitulatif = [];
$totaux_generaux = [
    'cyber' => [
        'especes' => 0,
        'cb' => 0,
        'cheque' => 0,
        'total' => 0
    ],
    'transactions' => [
        'especes' => 0,
        'cb' => 0,
        'cheque' => 0,
        'virement' => 0,
        'total' => 0
    ],
    'grand_total' => 0
];

if (isset($pdo)) {
    try {
        // Générer la liste des jours entre les deux dates
        $periode = new DatePeriod(
            new DateTime($date_debut),
            new DateInterval('P1D'),
            (new DateTime($date_fin))->modify('+1 day')
        );

        foreach ($periode as $date) {
            $date_str = $date->format('Y-m-d');
            
            $recapitulatif[$date_str] = [
                'date' => $date_str,
                'cyber' => [
                    'especes' => 0,
                    'cb' => 0,
                    'cheque' => 0,
                    'total' => 0
                ],
                'transactions' => [
                    'especes' => 0,
                    'cb' => 0,
                    'cheque' => 0,
                    'virement' => 0,
                    'total' => 0
                ],
                'total_jour' => 0
            ];

            // Récupération des données cyber pour ce jour
            $stmt = $pdo->prepare("SELECT moyen_payement, SUM(tarif) as total FROM FC_cyber WHERE DATE(date_cyber) = ? GROUP BY moyen_payement");
            $stmt->execute([$date_str]);
            $cyber_data = $stmt->fetchAll();

            foreach ($cyber_data as $cyber) {
                $moyen = strtolower(trim($cyber['moyen_payement']));
                $montant = floatval($cyber['total']);
                
                if (strpos($moyen, 'espèce') !== false || strpos($moyen, 'espece') !== false || strpos($moyen, 'liquide') !== false) {
                    $recapitulatif[$date_str]['cyber']['especes'] += $montant;
                } elseif (strpos($moyen, 'cb') !== false || strpos($moyen, 'carte') !== false || strpos($moyen, 'bancaire') !== false) {
                    $recapitulatif[$date_str]['cyber']['cb'] += $montant;
                } elseif (strpos($moyen, 'chèque') !== false || strpos($moyen, 'cheque') !== false) {
                    $recapitulatif[$date_str]['cyber']['cheque'] += $montant;
                }
                $recapitulatif[$date_str]['cyber']['total'] += $montant;
            }

            // Récupération des données transactions pour ce jour
            $stmt = $pdo->prepare("SELECT type, SUM(montant) as total FROM FC_transactions WHERE DATE(date_transaction) = ? GROUP BY type");
            $stmt->execute([$date_str]);
            $transaction_data = $stmt->fetchAll();

            foreach ($transaction_data as $transaction) {
                $type = strtolower(trim($transaction['type']));
                $montant = floatval($transaction['total']);
                
                if (strpos($type, 'espèce') !== false || strpos($type, 'espece') !== false || strpos($type, 'liquide') !== false) {
                    $recapitulatif[$date_str]['transactions']['especes'] += $montant;
                } elseif (strpos($type, 'cb') !== false || strpos($type, 'carte') !== false || strpos($type, 'bancaire') !== false) {
                    $recapitulatif[$date_str]['transactions']['cb'] += $montant;
                } elseif (strpos($type, 'chèque') !== false || strpos($type, 'cheque') !== false) {
                    $recapitulatif[$date_str]['transactions']['cheque'] += $montant;
                } elseif (strpos($type, 'virement') !== false || strpos($type, 'transfer') !== false) {
                    $recapitulatif[$date_str]['transactions']['virement'] += $montant;
                }
                $recapitulatif[$date_str]['transactions']['total'] += $montant;
            }

            // Calcul du total du jour
            $recapitulatif[$date_str]['total_jour'] = $recapitulatif[$date_str]['cyber']['total'] + $recapitulatif[$date_str]['transactions']['total'];

            // Ajout aux totaux généraux
            $totaux_generaux['cyber']['especes'] += $recapitulatif[$date_str]['cyber']['especes'];
            $totaux_generaux['cyber']['cb'] += $recapitulatif[$date_str]['cyber']['cb'];
            $totaux_generaux['cyber']['cheque'] += $recapitulatif[$date_str]['cyber']['cheque'];
            $totaux_generaux['cyber']['total'] += $recapitulatif[$date_str]['cyber']['total'];

            $totaux_generaux['transactions']['especes'] += $recapitulatif[$date_str]['transactions']['especes'];
            $totaux_generaux['transactions']['cb'] += $recapitulatif[$date_str]['transactions']['cb'];
            $totaux_generaux['transactions']['cheque'] += $recapitulatif[$date_str]['transactions']['cheque'];
            $totaux_generaux['transactions']['virement'] += $recapitulatif[$date_str]['transactions']['virement'];
            $totaux_generaux['transactions']['total'] += $recapitulatif[$date_str]['transactions']['total'];

            $totaux_generaux['grand_total'] += $recapitulatif[$date_str]['total_jour'];
        }

    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>



<div class="tableau-recapitulatif">

<div class="no-print">
    <div class="flex-between-center mb-20">
        <h1 class="m-0">Tableau Récapitulatif</h1>
        <a href="index.php?page=dashboard_caisse" class="btn btn-secondary">
            ← Retour au tableau de bord
        </a>
    </div>
    
    <div class="card p-20 mb-20">
        <form method="GET" class="flex align-center gap-15 flex-wrap">
            <input type="hidden" name="page" value="tableau_recapitulatif">
            
            <div class="flex align-center gap-10">
                <label for="date_debut" class="font-bold">Du :</label>
                <input type="date" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>"
                       class="form-control">
            </div>
            
            <div class="flex align-center gap-10">
                <label for="date_fin" class="font-bold">Au :</label>
                <input type="date" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>"
                       class="form-control">
            </div>
            
            <div class="bg-secondary-light p-10 rounded-4 border border-light flex align-center gap-15">
                <span class="font-bold">Afficher :</span>
                <label class="flex align-center gap-5 cursor-pointer">
                    <input type="checkbox" name="afficher_cyber" value="1" <?= $afficher_cyber ? 'checked' : '' ?>
                           class="cursor-pointer">
                    💻 Cyber
                </label>
                <label class="flex align-center gap-5 cursor-pointer">
                    <input type="checkbox" name="afficher_transactions" value="1" <?= $afficher_transactions ? 'checked' : '' ?>
                           class="cursor-pointer">
                    💰 Transactions
                </label>
            </div>
            
            <div class="flex gap-10">
                <button type="submit" class="btn btn-primary">
                    Afficher
                </button>
                <button type="button" onclick="window.print()" class="btn btn-success">
                    🖨️ Imprimer
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error mb-15">
        <?= $errorMessage ?>
    </div>
<?php else: ?>

<div class="text-center mb-30 pb-10 border-b-2 border-accent">
    <h1 class="m-0">Récapitulatif des Règlements</h1>
    <h2 class="m-0 mt-5 text-muted">Du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?></h2>
</div>

<!-- Résumé statistique -->
<div class="stats-summary">
    <?php if ($afficher_cyber): ?>
        <div class="stat-card">
            <h4>Total Cyber</h4>
            <div class="stat-value"><?= number_format($totaux_generaux['cyber']['total'], 2) ?> €</div>
        </div>
    <?php endif; ?>
    <?php if ($afficher_transactions): ?>
        <div class="stat-card">
            <h4>Total Transactions</h4>
            <div class="stat-value"><?= number_format($totaux_generaux['transactions']['total'], 2) ?> €</div>
        </div>
    <?php endif; ?>
    <div class="stat-card">
        <h4>Grand Total</h4>
        <div class="stat-value"><?= number_format(
            ($afficher_cyber ? $totaux_generaux['cyber']['total'] : 0) +
            ($afficher_transactions ? $totaux_generaux['transactions']['total'] : 0), 2
        ) ?> €</div>
    </div>
</div>

<!-- Tableau récapitulatif -->
<table class="recap-table">
    <thead>
        <tr>
            <th rowspan="2" class="date-col">Date</th>
            <?php if ($afficher_cyber): ?>
                <th colspan="4" class="section-header">💻 Cyber</th>
            <?php endif; ?>
            <?php if ($afficher_transactions): ?>
                <th colspan="5" class="section-header">💰 Transactions</th>
            <?php endif; ?>
            <th rowspan="2">Total Jour</th>
        </tr>
        <tr>
            <?php if ($afficher_cyber): ?>
                <th>Espèces</th>
                <th>CB</th>
                <th>Chèque</th>
                <th>Total</th>
            <?php endif; ?>
            <?php if ($afficher_transactions): ?>
                <th>Espèces</th>
                <th>CB</th>
                <th>Chèque</th>
                <th>Virement</th>
                <th>Total</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recapitulatif as $jour): ?>
            <?php
            // Calculer le total jour en fonction des filtres
            $total_jour_filtre = 0;
            if ($afficher_cyber) $total_jour_filtre += $jour['cyber']['total'];
            if ($afficher_transactions) $total_jour_filtre += $jour['transactions']['total'];
            ?>
            <?php if ($total_jour_filtre > 0): // N'afficher que les jours avec des données ?>
                <tr>
                    <td class="date-col"><?= date('d/m/Y', strtotime($jour['date'])) ?></td>
                    
                    <?php if ($afficher_cyber): ?>
                        <!-- Cyber -->
                        <td class="montant <?= $jour['cyber']['especes'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['cyber']['especes'] > 0 ? number_format($jour['cyber']['especes'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['cyber']['cb'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['cyber']['cb'] > 0 ? number_format($jour['cyber']['cb'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['cyber']['cheque'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['cyber']['cheque'] > 0 ? number_format($jour['cyber']['cheque'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['cyber']['total'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['cyber']['total'] > 0 ? number_format($jour['cyber']['total'], 2) . ' €' : '-' ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if ($afficher_transactions): ?>
                        <!-- Transactions -->
                        <td class="montant <?= $jour['transactions']['especes'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['transactions']['especes'] > 0 ? number_format($jour['transactions']['especes'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['transactions']['cb'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['transactions']['cb'] > 0 ? number_format($jour['transactions']['cb'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['transactions']['cheque'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['transactions']['cheque'] > 0 ? number_format($jour['transactions']['cheque'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['transactions']['virement'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['transactions']['virement'] > 0 ? number_format($jour['transactions']['virement'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="montant <?= $jour['transactions']['total'] > 0 ? 'positif' : 'zero' ?>">
                            <?= $jour['transactions']['total'] > 0 ? number_format($jour['transactions']['total'], 2) . ' €' : '-' ?>
                        </td>
                    <?php endif; ?>
                    
                    <!-- Total jour -->
                    <td class="montant positif">
                        <?= number_format($total_jour_filtre, 2) ?> €
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Ligne de totaux -->
        <tr class="total-row">
            <td class="date-col">TOTAUX</td>
            
            <?php if ($afficher_cyber): ?>
                <!-- Totaux Cyber -->
                <td class="montant"><?= number_format($totaux_generaux['cyber']['especes'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['cyber']['cb'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['cyber']['cheque'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['cyber']['total'], 2) ?> €</td>
            <?php endif; ?>
            
            <?php if ($afficher_transactions): ?>
                <!-- Totaux Transactions -->
                <td class="montant"><?= number_format($totaux_generaux['transactions']['especes'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['transactions']['cb'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['transactions']['cheque'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['transactions']['virement'], 2) ?> €</td>
                <td class="montant"><?= number_format($totaux_generaux['transactions']['total'], 2) ?> €</td>
            <?php endif; ?>
            
            <!-- Grand total -->
            <td class="montant"><?= number_format(
                ($afficher_cyber ? $totaux_generaux['cyber']['total'] : 0) +
                ($afficher_transactions ? $totaux_generaux['transactions']['total'] : 0), 2
            ) ?> €</td>
        </tr>
    </tbody>
</table>

<?php if (empty(array_filter($recapitulatif, function($jour) { return $jour['total_jour'] > 0; }))): ?>
    <div class="p-40 text-center text-muted italic">
        Aucune donnée trouvée pour la période sélectionnée.
    </div>
<?php endif; ?>

<?php endif; ?>

</div>