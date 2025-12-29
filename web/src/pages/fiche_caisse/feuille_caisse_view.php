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
            <td style='padding: 8px; border-bottom: 1px solid #eee;'>$libelle</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>$nombre</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>" . number_format($valeur, 2) . " €</td>
            <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;'>" . number_format($total, 2) . " €</td>
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
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?= htmlspecialchars($error) ?>
    </div>
    <a href="index.php?page=feuille_caisse_list" style="background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px;">
        ↩️ Retour à la liste
    </a>
<?php else: ?>

<!-- En-tête avec informations principales -->
<div style="background-color: var(--card-bg); padding: 25px; border-radius: 8px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0; color: var(--accent-color);">
                📅 <?= date('d/m/Y', strtotime($feuille['created_at'] ?? $feuille['date_comptage'] ?? 'now')) ?>
            </h2>
            <p style="margin: 5px 0 0 0; color: #666;">
                Créée le <?= date('d/m/Y à H:i', strtotime($feuille['created_at'])) ?>
                <?php if ($feuille['updated_at'] !== $feuille['created_at']): ?>
                    <br>Modifiée le <?= date('d/m/Y à H:i', strtotime($feuille['updated_at'])) ?>
                <?php endif; ?>
            </p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 14px; color: #666;">TOTAL CAISSE</div>
            <div style="font-size: 36px; font-weight: bold; color: #4CAF50;">
                <?= number_format($feuille['total_caisse'], 2) ?> €
            </div>
        </div>
    </div>
    
    <!-- Résumé des totaux -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
        <div style="background-color: #e3f2fd; padding: 12px; border-radius: 4px; text-align: center; border-left: 3px solid #2196F3;">
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">📊 Pièces Comptées</div>
            <div style="font-size: 18px; font-weight: bold; color: #2196F3;">
                <?= number_format($feuille['total_pieces'], 2) ?> €
            </div>
        </div>
        <div style="background-color: #e8f5e8; padding: 12px; border-radius: 4px; text-align: center; border-left: 3px solid #4CAF50;">
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">📊 Billets Comptés</div>
            <div style="font-size: 18px; font-weight: bold; color: #4CAF50;">
                <?= number_format($feuille['total_billets'], 2) ?> €
            </div>
        </div>
        <?php if ($feuille['total_retrait_especes'] > 0): ?>
        <div style="background-color: #fff3e0; padding: 12px; border-radius: 4px; text-align: center; border-left: 3px solid #FF9800;">
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">🏦 Retraits Banque</div>
            <div style="font-size: 18px; font-weight: bold; color: #FF9800;">
                <?= number_format($feuille['total_retrait_especes'], 2) ?> €
            </div>
        </div>
        <?php endif; ?>
        <div style="background-color: #f3e5f5; padding: 12px; border-radius: 4px; text-align: center; border-left: 3px solid #9C27B0;">
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">💰 Espèces Totales</div>
            <div style="font-size: 18px; font-weight: bold; color: #9C27B0;">
                <?= number_format($feuille['total_especes'], 2) ?> €
            </div>
        </div>
        <div style="background-color: #fff8e1; padding: 12px; border-radius: 4px; text-align: center; border-left: 3px solid #FFC107;">
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">📄 Chèques</div>
            <div style="font-size: 18px; font-weight: bold; color: #FFC107;">
                <?= number_format($feuille['montant_cheques'], 2) ?> €
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <!-- Détail des pièces -->
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px;">
        <h3 style="color: var(--accent-color); margin-top: 0; border-bottom: 2px solid var(--accent-color); padding-bottom: 10px;">
            🪙 Détail des Pièces - 📊 Comptage
        </h3>
        <table style="width: 100%; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">Valeur</th>
                    <th style="padding: 10px; text-align: center;">Quantité</th>
                    <th style="padding: 10px; text-align: right;">Unitaire</th>
                    <th style="padding: 10px; text-align: right;">Total</th>
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
                <tr style="background-color: #e9ecef; font-weight: bold;">
                    <td style="padding: 10px;">TOTAL PIÈCES</td>
                    <td style="padding: 10px; text-align: center;">-</td>
                    <td style="padding: 10px; text-align: right;">-</td>
                    <td style="padding: 10px; text-align: right; color: var(--accent-color);">
                        <?= number_format($feuille['total_pieces'], 2) ?> €
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Détail des billets -->
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px;">
        <h3 style="color: var(--accent-color); margin-top: 0; border-bottom: 2px solid var(--accent-color); padding-bottom: 10px;">
            💵 Détail des Billets - 📊 Comptage
        </h3>
        <table style="width: 100%; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">Valeur</th>
                    <th style="padding: 10px; text-align: center;">Quantité</th>
                    <th style="padding: 10px; text-align: right;">Unitaire</th>
                    <th style="padding: 10px; text-align: right;">Total</th>
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
                <tr style="background-color: #e9ecef; font-weight: bold;">
                    <td style="padding: 10px;">TOTAL BILLETS</td>
                    <td style="padding: 10px; text-align: center;">-</td>
                    <td style="padding: 10px; text-align: right;">-</td>
                    <td style="padding: 10px; text-align: right; color: var(--accent-color);">
                        <?= number_format($feuille['total_billets'], 2) ?> €
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Section des retraits bancaires -->
<?php if ($feuille['total_retrait_especes'] > 0): ?>
<div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #FF9800;">
    <h3 style="color: #FF9800; margin-top: 0; border-bottom: 2px solid #FF9800; padding-bottom: 10px;">
        🏦 Détail des Retraits Bancaires
    </h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Retraits pièces -->
        <?php if ($feuille['total_retrait_pieces'] > 0): ?>
        <div>
            <h4 style="color: #FF9800; margin-bottom: 10px;">🪙 Pièces retirées</h4>
            <table style="width: 100%; font-size: 14px;">
                <thead>
                    <tr style="background-color: #fff3e0;">
                        <th style="padding: 8px; text-align: left;">Valeur</th>
                        <th style="padding: 8px; text-align: center;">Quantité</th>
                        <th style="padding: 8px; text-align: right;">Total</th>
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
                                <td style='padding: 6px;'>{$piece['libelle']}</td>
                                <td style='padding: 6px; text-align: center;'>{$feuille[$piece['field']]}</td>
                                <td style='padding: 6px; text-align: right; font-weight: bold;'>" . number_format($total, 2) . " €</td>
                            </tr>";
                        }
                    }
                    ?>
                    <tr style="background-color: #fff3e0; font-weight: bold;">
                        <td style="padding: 8px;">TOTAL PIÈCES</td>
                        <td style="padding: 8px; text-align: center;">-</td>
                        <td style="padding: 8px; text-align: right; color: #FF9800;">
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
            <h4 style="color: #FF9800; margin-bottom: 10px;">💵 Billets retirés</h4>
            <table style="width: 100%; font-size: 14px;">
                <thead>
                    <tr style="background-color: #fff3e0;">
                        <th style="padding: 8px; text-align: left;">Valeur</th>
                        <th style="padding: 8px; text-align: center;">Quantité</th>
                        <th style="padding: 8px; text-align: right;">Total</th>
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
                                <td style='padding: 6px;'>{$billet['libelle']}</td>
                                <td style='padding: 6px; text-align: center;'>{$feuille[$billet['field']]}</td>
                                <td style='padding: 6px; text-align: right; font-weight: bold;'>" . number_format($total, 2) . " €</td>
                            </tr>";
                        }
                    }
                    ?>
                    <tr style="background-color: #fff3e0; font-weight: bold;">
                        <td style="padding: 8px;">TOTAL BILLETS</td>
                        <td style="padding: 8px; text-align: center;">-</td>
                        <td style="padding: 8px; text-align: right; color: #FF9800;">
                            <?= number_format($feuille['total_retrait_billets'], 2) ?> €
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Total des retraits -->
    <div style="background-color: #fff3e0; padding: 15px; border-radius: 4px; margin-top: 15px; text-align: center;">
        <div style="font-size: 16px; font-weight: bold; color: #FF9800;">
            🏦 TOTAL RETRAITS BANCAIRES : <?= number_format($feuille['total_retrait_especes'], 2) ?> €
        </div>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">
            Montant retiré de la caisse pour dépôt en banque
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chèques et notes -->
<?php if ($feuille['montant_cheques'] > 0 || !empty($feuille['notes'])): ?>
<div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="color: var(--accent-color); margin-top: 0;">💳 Informations Complémentaires</h3>
    
    <?php if ($feuille['montant_cheques'] > 0): ?>
    <div style="margin-bottom: 15px;">
        <strong>Chèques (<?= $feuille['nb_cheques'] ?? 0 ?> chèque(s)) :</strong>
        <span style="font-size: 18px; font-weight: bold; color: #ffc107; margin-left: 10px;">
            <?= number_format($feuille['montant_cheques'], 2) ?> €
        </span>
        
        <?php if (!empty($feuille['cheques_details'])): ?>
            <?php $cheques_details = json_decode($feuille['cheques_details'], true); ?>
            <?php if ($cheques_details): ?>
                <div style="margin-top: 10px;">
                    <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: var(--secondary-bg, #f8f9fa);">
                                <th style="padding: 8px; text-align: left; border: 1px solid var(--border-color, #ddd);">Montant</th>
                                <th style="padding: 8px; text-align: left; border: 1px solid var(--border-color, #ddd);">Émetteur</th>
                                <th style="padding: 8px; text-align: left; border: 1px solid var(--border-color, #ddd);">N° chèque</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques_details as $cheque): ?>
                                <tr>
                                    <td style="padding: 8px; border: 1px solid var(--border-color, #ddd); font-weight: bold; color: #ffc107;">
                                        <?= number_format($cheque['montant'], 2) ?> €
                                    </td>
                                    <td style="padding: 8px; border: 1px solid var(--border-color, #ddd);">
                                        <?= htmlspecialchars($cheque['emetteur'] ?: 'Non renseigné') ?>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid var(--border-color, #ddd);">
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
    <div style="margin-bottom: 15px;">
        <strong>🔍 Contrôle avec feuille précédente :</strong>
        
        <?php if (isset($feuille['ajustement_especes'])): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 10px 0;">
            <div style="background-color: #e7f3ff; padding: 10px; border-radius: 4px; border-left: 3px solid #2196F3;">
                <div style="font-size: 12px; color: #666;">Solde de départ</div>
                <div style="font-size: 16px; font-weight: bold; color: #2196F3;">
                    <?php
                    // Calculer le solde de base (solde attendu - ajustement)
                    $solde_base = $feuille['solde_precedent'] - ($feuille['ajustement_especes'] ?? 0);
                    echo number_format($solde_base, 2);
                    ?> €
                </div>
            </div>
            <div style="background-color: <?= ($feuille['ajustement_especes'] ?? 0) >= 0 ? '#e8f5e8' : '#ffe8e8' ?>; padding: 10px; border-radius: 4px; border-left: 3px solid <?= ($feuille['ajustement_especes'] ?? 0) >= 0 ? '#4CAF50' : '#F44336' ?>;">
                <div style="font-size: 12px; color: #666;">Ajustement</div>
                <div style="font-size: 16px; font-weight: bold; color: <?= ($feuille['ajustement_especes'] ?? 0) >= 0 ? '#4CAF50' : '#F44336' ?>;">
                    <?= (($feuille['ajustement_especes'] ?? 0) >= 0 ? '+' : '') . number_format($feuille['ajustement_especes'] ?? 0, 2) ?> €
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px; border-left: 4px solid #2196F3;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; text-align: center;">
                <div>
                    <div style="font-size: 12px; color: #666;">Espèces attendues</div>
                    <div style="font-size: 16px; font-weight: bold; color: #2196F3;">
                        <?= number_format($feuille['solde_precedent'], 2) ?> €
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #666;">Espèces comptées</div>
                    <div style="font-size: 16px; font-weight: bold; color: #FF9800;">
                        <?= number_format($feuille['total_especes'], 2) ?> €
                    </div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #666;">Écart</div>
                    <?php
                    $ecart = $feuille['total_especes'] - $feuille['solde_precedent'];
                    $couleur_ecart = $ecart > 0 ? '#4CAF50' : ($ecart < 0 ? '#F44336' : '#2196F3');
                    ?>
                    <div style="font-size: 18px; font-weight: bold; color: <?= $couleur_ecart ?>;">
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
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px; border-left: 4px solid var(--accent-color);">
            <?= nl2br(htmlspecialchars($feuille['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Récapitulatif final -->
<div style="background-color: #f8f9fa; padding: 25px; border-radius: 8px; border: 2px solid #4CAF50; margin-bottom: 20px;">
    <h3 style="color: #4CAF50; margin-top: 0; text-align: center;">💰 RÉCAPITULATIF FINAL</h3>
    <div style="display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: center;">
        <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>📊 Total Pièces Comptées :</span>
                <strong style="color: #2196F3;"><?= number_format($feuille['total_pieces'], 2) ?> €</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>📊 Total Billets Comptés :</span>
                <strong style="color: #4CAF50;"><?= number_format($feuille['total_billets'], 2) ?> €</strong>
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>🏦 Total Retraits Banque :</span>
                <strong style="color: #FF9800;">-<?= number_format($feuille['total_retrait_especes'], 2) ?> €</strong>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <span>💰 Total Espèces en Caisse :</span>
                <strong style="color: #9C27B0;"><?= number_format($feuille['total_especes'], 2) ?> €</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <span>📄 Total Chèques :</span>
                <strong style="color: #FFC107;"><?= number_format($feuille['montant_cheques'], 2) ?> €</strong>
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #666;">
                <span>💵 Espèces nettes (après retraits) :</span>
                <strong style="color: #28a745;"><?= number_format($feuille['total_especes'] - $feuille['total_retrait_especes'], 2) ?> €</strong>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; padding-top: 15px; border-top: 2px solid #4CAF50;">
                <span style="color: #4CAF50;">🏪 TOTAL CAISSE :</span>
                <span style="color: #4CAF50;"><?= number_format($feuille['total_caisse'], 2) ?> €</span>
            </div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 48px; font-weight: bold; color: #4CAF50;">
                <?= number_format($feuille['total_caisse'], 0) ?>€
            </div>
            <?php if ($feuille['total_retrait_especes'] > 0): ?>
            <div style="font-size: 14px; color: #FF9800; margin-top: 5px;">
                (<?= number_format($feuille['total_retrait_especes'], 0) ?>€ retirés)
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Actions -->
<div style="text-align: center;" class="no-print">
    <button onclick="window.print()" style="background-color: #17a2b8; color: white; padding: 12px 20px; border: none; border-radius: 4px; margin-right: 10px; cursor: pointer;">
        🖨️ Imprimer
    </button>
    <a href="index.php?page=feuille_caisse_add&date=<?= $feuille['date_comptage'] ?? date('Y-m-d') ?>"
       style="background-color: #ffc107; color: #212529; padding: 12px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;">
        ✏️ Modifier cette feuille
    </a>
    <a href="index.php?page=feuille_caisse_list"
       style="background-color: var(--accent-color); color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;">
        📋 Liste des feuilles
    </a>
    <a href="index.php?page=dashboard_caisse"
       style="background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px;">
        ↩️ Dashboard
    </a>
</div>

<style>
/* Amélioration du mode sombre */
body.dark {
    background-color: #1a202c !important;
    color: #e2e8f0 !important;
}

body.dark div[style*="background-color: var(--card-bg)"],
body.dark div[style*="background-color: #f8f9fa"] {
    background-color: #2d3748 !important;
    color: #e2e8f0 !important;
}

body.dark table {
    background-color: #2d3748 !important;
    color: #e2e8f0 !important;
}

body.dark th {
    background-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

body.dark td {
    background-color: #2d3748 !important;
    color: #e2e8f0 !important;
    border-color: #4a5568 !important;
}

body.dark tr[style*="background-color: #e9ecef"] {
    background-color: #374151 !important;
}

body.dark tr[style*="background-color: #f8f9fa"] {
    background-color: #374151 !important;
}

body.dark h1, body.dark h2, body.dark h3 {
    color: #e2e8f0 !important;
}

body.dark p, body.dark span, body.dark div {
    color: #e2e8f0 !important;
}

body.dark small {
    color: #a0aec0 !important;
}

/* Forcer les couleurs pour les montants */
body.dark div[style*="color: #17a2b8"] {
    color: #63b3ed !important;
}

body.dark div[style*="color: #28a745"] {
    color: #68d391 !important;
}

body.dark div[style*="color: #007bff"] {
    color: #63b3ed !important;
}

body.dark div[style*="color: #ffc107"] {
    color: #f6e05e !important;
}

body.dark div[style*="color: #4CAF50"] {
    color: #68d391 !important;
}

body.dark div[style*="color: var(--accent-color)"] {
    color: var(--accent-color) !important;
}

/* Récapitulatif final */
body.dark div[style*="background-color: #f8f9fa"][style*="border: 2px solid #4CAF50"] {
    background-color: #2d3748 !important;
    border-color: #68d391 !important;
}

body.dark div[style*="border-top: 2px solid #4CAF50"] {
    border-top-color: #68d391 !important;
}

body.dark div[style*="border-top: 1px solid #ddd"] {
    border-top-color: #4a5568 !important;
}

@media print {
    /* Masquer tous les éléments non essentiels */
    .no-print, .sidebar, header, .menu, nav, button {
        display: none !important;
    }
    
    /* Réinitialiser le body pour l'impression */
    body {
        background: white !important;
        color: black !important;
        font-family: Arial, sans-serif !important;
        font-size: 9pt !important;
        margin: 0 !important;
        padding: 10px !important;
        line-height: 1.2 !important;
    }
    
    /* Masquer le contenu principal et afficher seulement la feuille */
    .content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    /* Réinitialiser tous les éléments */
    * {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
    }
    
    /* Styles pour les tableaux */
    table, th, td {
        border: 1px solid black !important;
        background: white !important;
        color: black !important;
    }
    
    /* Titres */
    h1, h2, h3 {
        color: black !important;
        page-break-after: avoid;
        margin: 5px 0 !important;
        font-size: 11pt !important;
    }
    
    /* Réduire les espacements */
    div, p {
        margin: 2px 0 !important;
        padding: 2px !important;
    }
    
    /* Tableaux compacts */
    table {
        font-size: 8pt !important;
    }
    
    th, td {
        padding: 3px !important;
    }
    
    /* Conteneurs */
    div[style*="background-color"] {
        background: white !important;
        border: 1px solid #000 !important;
        margin-bottom: 10px !important;
    }
    
    /* Éviter les coupures de page */
    .feuille-caisse-container, table {
        page-break-inside: avoid;
    }
    
    /* Afficher le titre d'impression */
    .print-only {
        display: block !important;
    }
}
</style>

<?php endif; ?>