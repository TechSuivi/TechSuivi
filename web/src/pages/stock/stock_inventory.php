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

<div class="container container-center p-20">
    
    <div class="flex-between-center mb-20">
        <h1 class="text-color m-0 text-2xl">📋 Inventaire Valorisé</h1>
        <div class="bg-gradient-success text-white p-15 rounded-12 shadow-md">
            <div class="text-xs opacity-90 uppercase tracking-wider font-bold">VALEUR TOTALE DU STOCK (HT)</div>
            <div class="text-2xl font-bold"><?= number_format($grandTotal, 2, ',', ' ') ?> €</div>
        </div>
    </div>

    <div class="card p-0 overflow-hidden shadow-sm">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="p-12 text-left text-muted font-bold border-b border-border">Désignation</th>
                    <th class="p-12 text-left text-muted font-bold border-b border-border">EAN</th>
                    <th class="p-12 text-left text-muted font-bold border-b border-border">Fournisseur (Dernier)</th>
                    <th class="p-12 text-center text-muted font-bold border-b border-border">Quantité</th>
                    <th class="p-12 text-right text-muted font-bold border-b border-border">Prix Unitaire HT</th>
                    <th class="p-12 text-right text-muted font-bold border-b border-border">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="6" class="p-20 text-center text-muted">Aucun article en stock.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $row): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-12 font-medium text-color"><?= htmlspecialchars($row['designation']) ?></td>
                            <td class="p-12 font-mono text-muted text-sm"><?= htmlspecialchars($row['ean_code']) ?></td>
                            <td class="p-12 text-muted text-sm"><?= htmlspecialchars($row['fournisseur']) ?></td>
                            <td class="p-12 text-center">
                                <span class="badge badge-info">
                                    <?= $row['quantite'] ?>
                                </span>
                            </td>
                            <td class="p-12 text-right text-color">
                                <?php 
                                    // S'il y a variation de prix pour le même produit, on l'indique
                                    if ($row['prix_min_ht'] != $row['prix_max_ht']) {
                                        echo '<span title="Prix variable (Min: '.number_format($row['prix_min_ht'], 2).' - Max: '.number_format($row['prix_max_ht'], 2).')">~ ' . number_format($row['prix_moyen_ht'], 2, ',', ' ') . ' €</span>';
                                    } else {
                                        echo number_format($row['prix_moyen_ht'], 2, ',', ' ') . ' €';
                                    }
                                ?>
                            </td>
                            <td class="p-12 text-right font-bold text-success">
                                <?= number_format($row['total_ht'], 2, ',', ' ') ?> €
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="font-bold border-t-2 border-border bg-input">
                <tr>
                    <td colspan="3" class="p-12 text-right text-color">TOTAL GÉNÉRAL</td>
                    <td class="p-12 text-center text-color">
                        <?= array_sum(array_column($inventory, 'quantite')) ?> unités
                    </td>
                    <td></td>
                    <td class="p-12 text-right text-success text-lg">
                        <?= number_format($grandTotal, 2, ',', ' ') ?> €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
