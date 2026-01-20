<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$error = '';
$feuille = null;

// Récupération de l'ID
$id = $_GET['id'] ?? null;

if (!$id) {
    $error = "ID de feuille de caisse manquant.";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT *,
                   COALESCE(total_retrait_pieces, 0) as total_retrait_pieces,
                   COALESCE(total_retrait_billets, 0) as total_retrait_billets,
                   COALESCE(total_retrait_especes, 0) as total_retrait_especes
            FROM FC_feuille_caisse
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $feuille = $stmt->fetch();
        
        if (!$feuille) {
            $error = "Feuille de caisse introuvable.";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la récupération de la feuille : " . $e->getMessage();
    }
}

// Fonction pour afficher le détail des pièces/billets
function afficherDetail($nombre, $valeur, $libelle) {
    if ($nombre > 0) {
        $total = $nombre * $valeur;
        return "<tr>
            <td class='p-8 border-b'>$libelle</td>
            <td class='p-8 border-b text-center'>$nombre</td>
            <td class='p-8 border-b text-right'>" . number_format($valeur, 2) . " €</td>
            <td class='p-8 border-b text-right font-bold'>" . number_format($total, 2) . " €</td>
        </tr>";
    }
    return '';
}
?>



<h1 class="no-print">📊 Détail de la Feuille de Caisse</h1>

<!-- Titre pour l'impression -->
<div style="display: none;" class="print-only">
    <h1 style="text-align: center; margin: 0 0 10px 0; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 14pt;">
        FEUILLE DE CAISSE - <?= $feuille ? date('d/m/Y', strtotime($feuille['created_at'] ?? $feuille['date_comptage'] ?? 'now')) : 'N/A' ?>
    </h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
    <a href="index.php?page=feuille_caisse_list" class="btn btn-secondary">
        ↩️ Retour à la liste
    </a>
<?php else: ?>

<!-- En-tête avec informations principales -->
<div class="card p-25 mb-20">
    <div class="flex-between-center mb-20">
        <div>
            <h2 class="m-0 text-accent">
                📅 <?= date('d/m/Y', strtotime($feuille['created_at'] ?? $feuille['date_comptage'] ?? 'now')) ?>
            </h2>
            <p class="m-0 mt-5 text-muted">
                Créée le <?= date('d/m/Y à H:i', strtotime($feuille['created_at'])) ?>
                <?php if ($feuille['updated_at'] !== $feuille['created_at']): ?>
                    <br>Modifiée le <?= date('d/m/Y à H:i', strtotime($feuille['updated_at'])) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right">
            <div class="text-sm text-muted">TOTAL CAISSE</div>
            <div class="text-3xl font-bold text-green">
                <?= number_format($feuille['total_caisse'], 2) ?> €
            </div>
        </div>
    </div>
    
    <!-- Résumé des totaux -->
    <div class="summary-grid">
        <div class="summary-card blue">
            <div class="summary-card-title">📊 Pièces Comptées</div>
            <div class="summary-card-value">
                <?= number_format($feuille['total_pieces'], 2) ?> €
            </div>
        </div>
        <div class="summary-card green">
            <div class="summary-card-title">📊 Billets Comptés</div>
            <div class="summary-card-value">
                <?= number_format($feuille['total_billets'], 2) ?> €
            </div>
        </div>
        <?php if ($feuille['total_retrait_especes'] > 0): ?>
        <div class="summary-card orange">
            <div class="summary-card-title">🏦 Retraits Banque</div>
            <div class="summary-card-value">
                <?= number_format($feuille['total_retrait_especes'], 2) ?> €
            </div>
        </div>
        <?php endif; ?>
        <div class="summary-card purple">
            <div class="summary-card-title">💰 Espèces Totales</div>
            <div class="summary-card-value">
                <?= number_format($feuille['total_especes'], 2) ?> €
            </div>
        </div>
        <div class="summary-card yellow">
            <div class="summary-card-title">📄 Chèques</div>
            <div class="summary-card-value">
                <?= number_format($feuille['montant_cheques'], 2) ?> €
            </div>
        </div>
    </div>
</div>

<div class="grid-2 gap-20 mb-20">
    <!-- Détail des pièces -->
    <div class="card p-20">
        <h3 class="section-title">
            🪙 Détail des Pièces - 📊 Comptage
        </h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-secondary-light">
                    <th class="p-10 text-left">Valeur</th>
                    <th class="p-10 text-center">Quantité</th>
                    <th class="p-10 text-right">Unitaire</th>
                    <th class="p-10 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?= afficherDetail($feuille['pieces_001'], 0.01, '0,01 €') ?>
                <?= afficherDetail($feuille['pieces_002'], 0.02, '0,02 €') ?>
                <?= afficherDetail($feuille['pieces_005'], 0.05, '0,05 €') ?>
                <?= afficherDetail($feuille['pieces_010'], 0.10, '0,10 €') ?>
                <?= afficherDetail($feuille['pieces_020'], 0.20, '0,20 €') ?>
                <?= afficherDetail($feuille['pieces_050'], 0.50, '0,50 €') ?>
                <?= afficherDetail($feuille['pieces_100'], 1.00, '1,00 €') ?>
                <?= afficherDetail($feuille['pieces_200'], 2.00, '2,00 €') ?>
                <tr class="bg-secondary-light font-bold">
                    <td class="p-10">TOTAL PIÈCES</td>
                    <td class="p-10 text-center">-</td>
                    <td class="p-10 text-right">-</td>
                    <td class="p-10 text-right text-accent">
                        <?= number_format($feuille['total_pieces'], 2) ?> €
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Détail des billets -->
    <div class="card p-20">
        <h3 class="section-title">
            💵 Détail des Billets - 📊 Comptage
        </h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-secondary-light">
                    <th class="p-10 text-left">Valeur</th>
                    <th class="p-10 text-center">Quantité</th>
                    <th class="p-10 text-right">Unitaire</th>
                    <th class="p-10 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?= afficherDetail($feuille['billets_005'], 5, '5 €') ?>
                <?= afficherDetail($feuille['billets_010'], 10, '10 €') ?>
                <?= afficherDetail($feuille['billets_020'], 20, '20 €') ?>
                <?= afficherDetail($feuille['billets_050'], 50, '50 €') ?>
                <?= afficherDetail($feuille['billets_100'], 100, '100 €') ?>
                <?= afficherDetail($feuille['billets_200'], 200, '200 €') ?>
                <?= afficherDetail($feuille['billets_500'], 500, '500 €') ?>
                <tr class="bg-secondary-light font-bold">
                    <td class="p-10">TOTAL BILLETS</td>
                    <td class="p-10 text-center">-</td>
                    <td class="p-10 text-right">-</td>
                    <td class="p-10 text-right text-accent">
                        <?= number_format($feuille['total_billets'], 2) ?> €
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Section des retraits bancaires -->
<!-- Section des retraits bancaires -->
<?php if ($feuille['total_retrait_especes'] > 0): ?>
<div class="card p-20 mb-20 border-l-orange">
    <h3 class="section-title orange">
        🏦 Détail des Retraits Bancaires
    </h3>
    
    <div class="grid-2 gap-20">
        <!-- Retraits pièces -->
        <?php if ($feuille['total_retrait_pieces'] > 0): ?>
        <div>
            <h4 class="text-orange mb-10 mt-0">🪙 Pièces retirées</h4>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-orange-light">
                        <th class="p-8 text-left">Valeur</th>
                        <th class="p-8 text-center">Quantité</th>
                        <th class="p-8 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $retrait_pieces = [
                        ['field' => 'retrait_pieces_001', 'valeur' => 0.01, 'libelle' => '0,01 €'],
                        ['field' => 'retrait_pieces_002', 'valeur' => 0.02, 'libelle' => '0,02 €'],
                        ['field' => 'retrait_pieces_005', 'valeur' => 0.05, 'libelle' => '0,05 €'],
                        ['field' => 'retrait_pieces_010', 'valeur' => 0.10, 'libelle' => '0,10 €'],
                        ['field' => 'retrait_pieces_020', 'valeur' => 0.20, 'libelle' => '0,20 €'],
                        ['field' => 'retrait_pieces_050', 'valeur' => 0.50, 'libelle' => '0,50 €'],
                        ['field' => 'retrait_pieces_100', 'valeur' => 1.00, 'libelle' => '1,00 €'],
                        ['field' => 'retrait_pieces_200', 'valeur' => 2.00, 'libelle' => '2,00 €']
                    ];
                    foreach ($retrait_pieces as $piece) {
                        if (isset($feuille[$piece['field']]) && $feuille[$piece['field']] > 0) {
                            $total = $feuille[$piece['field']] * $piece['valeur'];
                            echo "<tr>
                                <td class='p-6 border-b'>{$piece['libelle']}</td>
                                <td class='p-6 border-b text-center'>{$feuille[$piece['field']]}</td>
                                <td class='p-6 border-b text-right font-bold'>" . number_format($total, 2) . " €</td>
                            </tr>";
                        }
                    }
                    ?>
                    <tr class="bg-orange-light font-bold">
                        <td class="p-8">TOTAL PIÈCES</td>
                        <td class="p-8 text-center">-</td>
                        <td class="p-8 text-right text-orange">
                            <?= number_format($feuille['total_retrait_pieces'], 2) ?> €
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Retraits billets -->
        <?php if ($feuille['total_retrait_billets'] > 0): ?>
        <div>
            <h4 class="text-orange mb-10 mt-0">💵 Billets retirés</h4>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-orange-light">
                        <th class="p-8 text-left">Valeur</th>
                        <th class="p-8 text-center">Quantité</th>
                        <th class="p-8 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $retrait_billets = [
                        ['field' => 'retrait_billets_005', 'valeur' => 5, 'libelle' => '5 €'],
                        ['field' => 'retrait_billets_010', 'valeur' => 10, 'libelle' => '10 €'],
                        ['field' => 'retrait_billets_020', 'valeur' => 20, 'libelle' => '20 €'],
                        ['field' => 'retrait_billets_050', 'valeur' => 50, 'libelle' => '50 €'],
                        ['field' => 'retrait_billets_100', 'valeur' => 100, 'libelle' => '100 €'],
                        ['field' => 'retrait_billets_200', 'valeur' => 200, 'libelle' => '200 €'],
                        ['field' => 'retrait_billets_500', 'valeur' => 500, 'libelle' => '500 €']
                    ];
                    foreach ($retrait_billets as $billet) {
                        if (isset($feuille[$billet['field']]) && $feuille[$billet['field']] > 0) {
                            $total = $feuille[$billet['field']] * $billet['valeur'];
                            echo "<tr>
                                <td class='p-6 border-b'>{$billet['libelle']}</td>
                                <td class='p-6 border-b text-center'>{$feuille[$billet['field']]}</td>
                                <td class='p-6 border-b text-right font-bold'>" . number_format($total, 2) . " €</td>
                            </tr>";
                        }
                    }
                    ?>
                    <tr class="bg-orange-light font-bold">
                        <td class="p-8">TOTAL BILLETS</td>
                        <td class="p-8 text-center">-</td>
                        <td class="p-8 text-right text-orange">
                            <?= number_format($feuille['total_retrait_billets'], 2) ?> €
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Total des retraits -->
    <div class="bg-orange-light p-15 rounded-4 mt-15 text-center">
        <div class="text-orange text-lg font-bold">
            🏦 TOTAL RETRAITS BANCAIRES : <?= number_format($feuille['total_retrait_especes'], 2) ?> €
        </div>
        <div class="text-xs text-muted mt-5">
            Montant retiré de la caisse pour dépôt en banque
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chèques et notes -->
<?php if ($feuille['montant_cheques'] > 0 || !empty($feuille['notes'])): ?>
<div class="card p-20 mb-20">
    <h3 class="section-title">💳 Informations Complémentaires</h3>
    
    <?php if ($feuille['montant_cheques'] > 0): ?>
    <div class="mb-15">
        <strong>Chèques (<?= $feuille['nb_cheques'] ?? 0 ?> chèque(s)) :</strong>
        <span class="text-xl font-bold text-yellow ml-10">
            <?= number_format($feuille['montant_cheques'], 2) ?> €
        </span>
        
        <?php if (!empty($feuille['cheques_details'])): ?>
            <?php $cheques_details = json_decode($feuille['cheques_details'], true); ?>
            <?php if ($cheques_details): ?>
                <div class="mt-10 overflow-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-secondary-light">
                                <th class="p-8 text-left border">Montant</th>
                                <th class="p-8 text-left border">Émetteur</th>
                                <th class="p-8 text-left border">N° chèque</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques_details as $cheque): ?>
                                <tr>
                                    <td class="p-8 border font-bold text-yellow">
                                        <?= number_format($cheque['montant'], 2) ?> €
                                    </td>
                                    <td class="p-8 border">
                                        <?= htmlspecialchars($cheque['emetteur'] ?: 'Non renseigné') ?>
                                    </td>
                                    <td class="p-8 border">
                                        <?= htmlspecialchars($cheque['numero'] ?: 'Non renseigné') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($feuille['solde_precedent']) && $feuille['solde_precedent'] > 0): ?>
    <div class="mb-15">
        <strong>🔍 Contrôle avec feuille précédente :</strong>
        
        <?php if (isset($feuille['ajustement_especes'])): ?>
        <div class="grid-2 gap-15 my-10">
            <div class="control-card-blue">
                <div class="text-xs text-muted">Solde de départ</div>
                <div class="text-lg font-bold text-blue">
                    <?php
                    // Calculer le solde de base (solde attendu - ajustement)
                    $solde_base = $feuille['solde_precedent'] - ($feuille['ajustement_especes'] ?? 0);
                    echo number_format($solde_base, 2);
                    ?> €
                </div>
            </div>
            <div class="<?= ($feuille['ajustement_especes'] ?? 0) >= 0 ? 'control-card-green' : 'control-card-red' ?>">
                <div class="text-xs text-muted">Ajustement</div>
                <div class="text-lg font-bold" style="color: <?= ($feuille['ajustement_especes'] ?? 0) >= 0 ? 'var(--success-color)' : 'var(--danger-color)' ?>;">
                    <?= (($feuille['ajustement_especes'] ?? 0) >= 0 ? '+' : '') . number_format($feuille['ajustement_especes'] ?? 0, 2) ?> €
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="bg-secondary-light p-15 rounded-4 mt-10 border-l-blue">
            <div class="grid-3 gap-15 text-center">
                <div>
                    <div class="text-xs text-muted">Espèces attendues</div>
                    <div class="text-lg font-bold text-blue">
                        <?= number_format($feuille['solde_precedent'], 2) ?> €
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Espèces comptées</div>
                    <div class="text-lg font-bold text-orange">
                        <?= number_format($feuille['total_especes'], 2) ?> €
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Écart</div>
                    <?php
                    $ecart = $feuille['total_especes'] - $feuille['solde_precedent'];
                    $couleur_ecart = $ecart > 0 ? '#4CAF50' : ($ecart < 0 ? '#F44336' : '#2196F3');
                    ?>
                    <div class="text-xl font-bold" style="color: <?= $couleur_ecart ?>;">
                        <?= $ecart >= 0 ? '+' : '' ?><?= number_format($ecart, 2) ?> €
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($feuille['notes'])): ?>
    <div>
        <strong>Notes :</strong>
        <div class="bg-secondary-light p-15 rounded-4 mt-10 border-l border-accent">
            <?= nl2br(htmlspecialchars($feuille['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Récapitulatif final -->
<div class="bg-secondary-light p-25 rounded-8 mb-20 border-2 border-green">
    <h3 class="mt-0 text-center text-green">💰 RÉCAPITULATIF FINAL</h3>
    <div class="flex flex-wrap gap-20 align-center">
        <div class="flex-1">
            <div class="flex-between mb-8">
                <span>📊 Total Pièces Comptées :</span>
                <strong class="text-blue"><?= number_format($feuille['total_pieces'], 2) ?> €</strong>
            </div>
            <div class="flex-between mb-8">
                <span>📊 Total Billets Comptés :</span>
                <strong class="text-green"><?= number_format($feuille['total_billets'], 2) ?> €</strong>
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div class="flex-between mb-8">
                <span>🏦 Total Retraits Banque :</span>
                <strong class="text-orange">-<?= number_format($feuille['total_retrait_especes'], 2) ?> €</strong>
            </div>
            <?php endif; ?>
            <div class="flex-between mb-10 pt-10 border-t">
                <span>💰 Total Espèces en Caisse :</span>
                <strong class="text-purple"><?= number_format($feuille['total_especes'], 2) ?> €</strong>
            </div>
            <div class="flex-between mb-15">
                <span>📄 Total Chèques :</span>
                <strong class="text-yellow"><?= number_format($feuille['montant_cheques'], 2) ?> €</strong>
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div class="flex-between mb-10 text-sm text-muted">
                <span>💵 Espèces nettes (après retraits) :</span>
                <strong class="text-success"><?= number_format($feuille['total_especes'] - $feuille['total_retrait_especes'], 2) ?> €</strong>
            </div>
            <?php endif; ?>
            <div class="flex-between text-xl font-bold pt-15 border-t-2 border-green">
                <span class="text-green">🏪 TOTAL CAISSE :</span>
                <span class="text-green"><?= number_format($feuille['total_caisse'], 2) ?> €</span>
            </div>
        </div>
        <div class="text-center">
            <div class="text-5xl font-bold text-green">
                <?= number_format($feuille['total_caisse'], 0) ?>€
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div class="text-sm text-orange mt-5">
                (<?= number_format($feuille['total_retrait_especes'], 0) ?>€ retirés)
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="text-center no-print">
    <button onclick="window.print()" class="btn btn-info mr-10">
        🖨️ Imprimer
    </button>
    <a href="index.php?page=feuille_caisse_add&date=<?= $feuille['date_comptage'] ?? date('Y-m-d') ?>"
       class="btn btn-warning mr-10">
        ✏️ Modifier cette feuille
    </a>
    <a href="index.php?page=feuille_caisse_list"
       class="btn btn-accent mr-10">
        📋 Liste des feuilles
    </a>
    <a href="index.php?page=dashboard_caisse"
       class="btn btn-secondary">
        ↩️ Dashboard
    </a>
</div>

<?php endif; ?>