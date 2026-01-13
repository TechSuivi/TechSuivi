<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Ce fichier est destiné à être inclus dans index.php
// La connexion $pdo et la session sont gérées par index.php

$orders = [];
$searchTerm = trim($_GET['search_term'] ?? ''); // Terme de recherche
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1; // Page actuelle
$itemsPerPage = 20; // Nombre d'items par page

// Paramètres de tri
$sortableColumns = ['numero_commande', 'date_commande', 'fournisseur', 'nb_articles', 'total_ht'];
$sortBy = $_GET['sort_by'] ?? 'date_commande'; // Colonne par défaut
$sortDir = $_GET['sort_dir'] ?? 'DESC'; // Direction par défaut

// Validation des paramètres de tri
if (!in_array($sortBy, $sortableColumns)) {
    $sortBy = 'date_commande';
}
if (strtoupper($sortDir) !== 'ASC' && strtoupper($sortDir) !== 'DESC') {
    $sortDir = 'DESC';
}

$totalItems = 0;
$totalPages = 1;
$offset = 0;

if (isset($pdo)) {
    try {
        // Construction de la condition WHERE
        $whereClause = "WHERE numero_commande IS NOT NULL AND numero_commande != ''";
        $params = [];
        
        if (!empty($searchTerm)) {
            $whereClause .= " AND (LOWER(numero_commande) LIKE :searchCommande 
                              OR LOWER(fournisseur) LIKE :searchFournisseur)";
            $params[':searchCommande'] = '%' . strtolower($searchTerm) . '%';
            $params[':searchFournisseur'] = '%' . strtolower($searchTerm) . '%';
        }
        
        // 1. Compter le nombre total de commandes uniques (pour la pagination)
        // Note: COUNT(DISTINCT numero_commande) est une approximation si on regroupe par fournisseur aussi,
        // mais ici on suppose que numero_commande est unique par commande.
        // Si numero_commande peut être le même pour différents fournisseurs (peu probable mais possible),
        // il faudrait grouper par numero_commande, fournisseur.
        
        $countSql = "SELECT COUNT(*) FROM (
                        SELECT numero_commande 
                        FROM Stock 
                        $whereClause 
                        GROUP BY numero_commande, fournisseur
                     ) as sub";
                     
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        if ($totalPages == 0) $totalPages = 1;
        if ($currentPage > $totalPages) $currentPage = $totalPages;
        if ($currentPage < 1) $currentPage = 1;
        $offset = ($currentPage - 1) * $itemsPerPage;
        
        // 2. Récupérer les données agrégées
        $sql = "SELECT 
                    numero_commande, 
                    fournisseur, 
                    MAX(date_commande) as date_commande, 
                    COUNT(*) as nb_articles, 
                    SUM(prix_achat_ht) as total_ht 
                FROM Stock 
                $whereClause 
                GROUP BY numero_commande, fournisseur 
                ORDER BY $sortBy $sortDir 
                LIMIT :limit OFFSET :offset";
                
        // Ajout des params de pagination
        // PDO::PARAM_INT est nécessaire pour LIMIT/OFFSET dans certaines configs MySQL/PDO
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $orders = $stmt->fetchAll();

    } catch (PDOException $e) {
        echo "<p>Erreur lors de la récupération des commandes : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
}
?>

<style>
    /* Réutilisation des styles du tableau stock */
    .orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        margin-top: 20px;
        font-size: 14px;
        color: #212529;
    }
    .orders-table thead {
        background-color: #f1f3f5;
    }
    .orders-table th {
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        font-size: 0.85em;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }
    .orders-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
        color: inherit;
    }
    .orders-table tr:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }
    
    .col-cmd { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: bold; color: #495057; }
    .col-supplier { color: #0d6efd; font-weight: 500; }
    .col-date { color: #868e96; }
    .col-count { text-align: center; font-weight: 600; }
    .col-total { text-align: right; font-weight: bold; color: #198754; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

    /* Search Box */
    .search-container {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #e9ecef;
    }
    .search-input {
        width: 100%;
        max-width: 400px;
        padding: 10px 15px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
    }
    .btn-search {
        padding: 10px 20px;
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-clear {
        padding: 10px 15px;
        background-color: #e9ecef;
        color: #495057;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
    }

    /* DARK MODE SUPPORT */
    body.dark .orders-table,
    body.dark .search-container {
        background-color: var(--card-bg) !important;
        color: var(--text-color) !important;
        border-color: var(--border-color) !important;
    }
    body.dark .orders-table thead {
        background-color: #343a40;
    }
    body.dark .orders-table th {
        color: #adb5bd;
        border-bottom-color: var(--border-color);
    }
    body.dark .orders-table td {
        border-bottom-color: #343a40;
        color: var(--text-color);
    }
    body.dark .orders-table tr:hover {
        background-color: var(--hover-color) !important;
    }
    body.dark .search-input {
        background-color: #343a40;
        border-color: var(--border-color);
        color: var(--text-color);
    }
    body.dark .col-cmd { color: #f8f9fa; }
    body.dark .col-supplier { color: #74c0fc; }
    body.dark .col-total { color: #69db7c; }
    body.dark h1 { color: var(--text-color) !important; }
    body.dark .btn-clear { background-color: var(--border-color); color: var(--text-color); }
    body.dark .btn-clear:hover { background-color: #343a40; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 style="color: #343a40; font-size: 24px; margin: 0;">📋 Liste des Commandes</h1>
</div>

<!-- Search Form -->
<form method="GET" action="index.php" class="search-container">
    <input type="hidden" name="page" value="orders_list">
    <input type="text" name="search_term" class="search-input" placeholder="🔍 Rechercher par n° de commande ou fournisseur..." value="<?= htmlspecialchars($searchTerm) ?>">
    <input type="submit" value="Rechercher" class="btn-search">
    <?php
    $clearSearchUrl = "index.php?page=orders_list";
    if (isset($_GET['sort_by'])) $clearSearchUrl .= "&sort_by=" . urlencode($_GET['sort_by']);
    if (isset($_GET['sort_dir'])) $clearSearchUrl .= "&sort_dir=" . urlencode($_GET['sort_dir']);
    ?>
    <?php if (!empty($searchTerm)): ?>
        <a href="<?= $clearSearchUrl ?>" class="btn-clear">Effacer</a>
    <?php endif; ?>
</form>

<?php if (empty($orders) && $totalItems == 0): ?>
    <div style="text-align: center; padding: 40px; color: #868e96; background: #fff; border-radius: 8px;">
        <h3>Aucune commande trouvée <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : '' ?>.</h3>
    </div>
<?php else: ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; color: #6c757d; font-size: 0.9em;">
        <span><?= $totalItems ?> commandes trouvées</span>
        <span>Page <?= $currentPage ?> sur <?= $totalPages ?></span>
    </div>

    <table class="orders-table">
        <thead>
            <tr>
                <?php
                function getOrderSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm) {
                    $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                    $arrow = '';
                    $style = "color: inherit; text-decoration: none; display: block;";
                    if ($currentSortBy === $columnKey) {
                        $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                        $style .= " color: #0d6efd;";
                    }
                    $url = "index.php?page=orders_list&sort_by={$columnKey}&sort_dir={$linkSortDir}";
                    if (!empty($currentSearchTerm)) {
                        $url .= "&search_term=" . urlencode($currentSearchTerm);
                    }
                    return "<a href='{$url}' style='{$style}'>{$columnDisplay}{$arrow}</a>";
                }
                ?>
                <th class="col-cmd"><?= getOrderSortLink('numero_commande', 'N° Commande', $sortBy, $sortDir, $searchTerm) ?></th>
                <th class="col-supplier"><?= getOrderSortLink('fournisseur', 'Fournisseur', $sortBy, $sortDir, $searchTerm) ?></th>
                <th class="col-date"><?= getOrderSortLink('date_commande', 'Date', $sortBy, $sortDir, $searchTerm) ?></th>
                <th class="col-count" style="text-align: center;"><?= getOrderSortLink('nb_articles', 'Nb Articles', $sortBy, $sortDir, $searchTerm) ?></th>
                <th class="col-total"><?= getOrderSortLink('total_ht', 'Total HT', $sortBy, $sortDir, $searchTerm) ?></th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr onclick="window.location.href='index.php?page=stock_list&search_term=<?= urlencode($order['numero_commande']) ?>'" title="Voir les détails de la commande">
                    <td class="col-cmd"><?= htmlspecialchars($order['numero_commande']) ?></td>
                    <td class="col-supplier"><?= htmlspecialchars($order['fournisseur'] ?? '-') ?></td>
                    <td class="col-date">
                        <?php 
                        if (!empty($order['date_commande'])) {
                            echo htmlspecialchars(date('d/m/Y', strtotime($order['date_commande']))); 
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td class="col-count"><?= htmlspecialchars($order['nb_articles']) ?></td>
                    <td class="col-total"><?= htmlspecialchars(number_format((float)($order['total_ht'] ?? 0), 2, ',', ' ')) ?> €</td>
                    <td style="text-align: center; white-space: nowrap;">
                        <a href="index.php?page=stock_list&search_term=<?= urlencode($order['numero_commande']) ?>" style="text-decoration: none; margin-right: 10px;" title="Voir la liste">🔍</a>
                        <a href="index.php?page=orders_edit&supplier=<?= urlencode($order['fournisseur']) ?>&order=<?= urlencode($order['numero_commande']) ?>" style="text-decoration: none; margin-right: 10px;" title="Modifier (Date/Fichiers)">✏️</a>
                        <a href="index.php?page=stock_add&supplier=<?= urlencode($order['fournisseur']) ?>&order=<?= urlencode($order['numero_commande']) ?>" style="text-decoration: none;" title="Ajouter un article à cette commande">➕</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 20px; text-align: center;">
        <?php
        $baseUrl = "index.php?page=orders_list";
        if (!empty($searchTerm)) {
            $baseUrl .= "&search_term=" . urlencode($searchTerm);
        }
        $baseUrl .= "&sort_by=" . urlencode($sortBy) . "&sort_dir=" . urlencode($sortDir);

        if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;">&laquo; Précédent</a>
        <?php endif; ?>

        <?php
        $pageWindow = 2; 
        $windowStart = max(1, $currentPage - $pageWindow);
        $windowEnd = min($totalPages, $currentPage + $pageWindow);

        if ($windowStart > 1) echo '<span style="padding: 5px 10px; margin: 0 2px;">...</span>';

        for ($i = $windowStart; $i <= $windowEnd; $i++):
            if ($i == $currentPage): ?>
                <strong style="padding: 5px 10px; margin: 0 2px; border: 1px solid #007bff; background-color: #007bff; color: white;"><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;"><?= $i ?></a>
            <?php endif;
        endfor;

        if ($windowEnd < $totalPages) echo '<span style="padding: 5px 10px; margin: 0 2px;">...</span>';
        
        if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;">Suivant &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
