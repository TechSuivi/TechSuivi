<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

// Récupération des données agrégées
try {
    // On groupe par EAN et Designation identiques pour compter les quantités
    // On affiche aussi le prix unitaire (Moyen si variations, mais logiquement ça devrait être proche)
    $sql = "SELECT 
                ean_code, 
                designation, 
                fournisseur,
                COUNT(*) as quantite,
                SUM(prix_achat_ht) as total_ht,
                AVG(prix_achat_ht) as prix_moyen_ht,
                MIN(prix_achat_ht) as prix_min_ht,
                MAX(prix_achat_ht) as prix_max_ht
            FROM Stock 
            GROUP BY ean_code, designation 
            ORDER BY designation ASC";
    
    $stmt = $pdo->query($sql);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul du Grand Total
    $grandTotal = 0;
    foreach ($inventory as $item) {
        $grandTotal += $item['total_ht'];
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erreur lors de l'inventaire : " . htmlspecialchars($e->getMessage()) . "</div>";
    $inventory = [];
    $grandTotal = 0;
}
?>

<div class="inventory-container" style="padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📋 Inventaire Valorisé</h1>
        <div style="background-color: #28a745; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 0.9em; opacity: 0.9;">VALEUR TOTALE DU STOCK (HT)</div>
            <div style="font-size: 1.8em; font-weight: bold;"><?= number_format($grandTotal, 2, ',', ' ') ?> €</div>
        </div>
    </div>

    <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                <tr>
                    <th style="padding: 12px; text-align: left; color: #495057;">Désignation</th>
                    <th style="padding: 12px; text-align: left; color: #495057;">EAN</th>
                    <th style="padding: 12px; text-align: left; color: #495057;">Fournisseur (Dernier)</th>
                    <th style="padding: 12px; text-align: center; color: #495057;">Quantité</th>
                    <th style="padding: 12px; text-align: right; color: #495057;">Prix Unitaire HT</th>
                    <th style="padding: 12px; text-align: right; color: #495057;">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="6" style="padding: 20px; text-align: center; color: #6c757d;">Aucun article en stock.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f3f5;">
                            <td style="padding: 12px; font-weight: 500;"><?= htmlspecialchars($row['designation']) ?></td>
                            <td style="padding: 12px; font-family: monospace; color: #666;"><?= htmlspecialchars($row['ean_code']) ?></td>
                            <td style="padding: 12px; color: #666; font-size: 0.9em;"><?= htmlspecialchars($row['fournisseur']) ?></td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="background-color: #e3f2fd; color: #0d47a1; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                    <?= $row['quantite'] ?>
                                </span>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <?php 
                                    // S'il y a variation de prix pour le même produit, on l'indique
                                    if ($row['prix_min_ht'] != $row['prix_max_ht']) {
                                        echo '<span title="Prix variable (Min: '.number_format($row['prix_min_ht'], 2).' - Max: '.number_format($row['prix_max_ht'], 2).')">~ ' . number_format($row['prix_moyen_ht'], 2, ',', ' ') . ' €</span>';
                                    } else {
                                        echo number_format($row['prix_moyen_ht'], 2, ',', ' ') . ' €';
                                    }
                                ?>
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: bold; color: #2e7d32;">
                                <?= number_format($row['total_ht'], 2, ',', ' ') ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dde2e6;">
                    <td colspan="3" style="padding: 12px; text-align: right;">TOTAL GÉNÉRAL</td>
                    <td style="padding: 12px; text-align: center;">
                        <?= array_sum(array_column($inventory, 'quantite')) ?> unités
                    </td>
                    <td></td>
                    <td style="padding: 12px; text-align: right; color: #2e7d32; font-size: 1.1em;">
                        <?= number_format($grandTotal, 2, ',', ' ') ?> €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
