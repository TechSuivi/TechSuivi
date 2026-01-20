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
                
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $orders = $stmt->fetchAll();

    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Erreur lors de la récupération des commandes : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Erreur de configuration : la connexion à la base de données n'est pas disponible.</div>";
}
?>

<div class="flex-between-center mb-20">
    <h1 class="text-color m-0 text-2xl">📋 Liste des Commandes</h1>
</div>

<!-- Search Form -->
<form method="GET" action="index.php" class="card p-20 flex items-center gap-10 border border-border rounded shadow-sm mb-25">
    <input type="hidden" name="page" value="orders_list">
    <input type="text" name="search_term" class="form-control flex-1 max-w-400 p-10 border rounded text-sm bg-input text-color" placeholder="🔍 Rechercher par n° de commande ou fournisseur..." value="<?= htmlspecialchars($searchTerm) ?>">
    <button type="submit" class="btn btn-primary">Rechercher</button>
    <?php
    $clearSearchUrl = "index.php?page=orders_list";
    if (isset($_GET['sort_by'])) $clearSearchUrl .= "&sort_by=" . urlencode($_GET['sort_by']);
    if (isset($_GET['sort_dir'])) $clearSearchUrl .= "&sort_dir=" . urlencode($_GET['sort_dir']);
    ?>
    <?php if (!empty($searchTerm)): ?>
        <a href="<?= $clearSearchUrl ?>" class="btn btn-secondary text-decoration-none">Effacer</a>
    <?php endif; ?>
</form>

<?php if (empty($orders) && $totalItems == 0): ?>
    <div class="card p-40 text-center text-muted border border-border rounded">
        <h3>Aucune commande trouvée <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : '' ?>.</h3>
    </div>
<?php else: ?>
    <div class="flex-between-center mb-10 text-muted text-sm">
        <span><?= $totalItems ?> commandes trouvées</span>
        <span>Page <?= $currentPage ?> sur <?= $totalPages ?></span>
    </div>

    <div class="card p-0 overflow-hidden shadow-sm">
        <table class="w-full border-separate border-spacing-0">
            <thead>
                <tr>
                    <?php
                    function getOrderSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm) {
                        $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                        $arrow = '';
                        $activeClass = "";
                        if ($currentSortBy === $columnKey) {
                            $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                            $activeClass = "text-primary";
                        }
                        $url = "index.php?page=orders_list&sort_by={$columnKey}&sort_dir={$linkSortDir}";
                        if (!empty($currentSearchTerm)) {
                            $url .= "&search_term=" . urlencode($currentSearchTerm);
                        }
                        return "<a href='{$url}' class='text-dark no-underline block hover:text-primary {$activeClass}'>{$columnDisplay}{$arrow}</a>";
                    }
                    ?>
                    <th class="p-15 text-left font-bold border-b border-border text-xs uppercase tracking-wide text-muted"><?= getOrderSortLink('numero_commande', 'N° Commande', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="p-15 text-left font-bold border-b border-border text-xs uppercase tracking-wide text-muted"><?= getOrderSortLink('fournisseur', 'Fournisseur', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="p-15 text-left font-bold border-b border-border text-xs uppercase tracking-wide text-muted"><?= getOrderSortLink('date_commande', 'Date', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="p-15 text-center font-bold border-b border-border text-xs uppercase tracking-wide text-muted"><?= getOrderSortLink('nb_articles', 'Nb Articles', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="p-15 text-right font-bold border-b border-border text-xs uppercase tracking-wide text-muted"><?= getOrderSortLink('total_ht', 'Total HT', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="p-15 text-left font-bold border-b border-border text-xs uppercase tracking-wide text-muted">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-hover cursor-pointer transition-colors" onclick="window.location.href='index.php?page=stock_list&search_term=<?= urlencode($order['numero_commande']) ?>'" title="Voir les détails de la commande">
                        <td class="p-12 border-b border-border font-mono font-bold text-color"><?= htmlspecialchars($order['numero_commande']) ?></td>
                        <td class="p-12 border-b border-border font-medium text-info"><?= htmlspecialchars($order['fournisseur'] ?? '-') ?></td>
                        <td class="p-12 border-b border-border text-muted">
                            <?php 
                            if (!empty($order['date_commande'])) {
                                echo htmlspecialchars(date('d/m/Y', strtotime($order['date_commande']))); 
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="p-12 border-b border-border text-center font-bold text-color"><?= htmlspecialchars($order['nb_articles']) ?></td>
                        <td class="p-12 border-b border-border text-right font-bold text-success font-sans"><?= htmlspecialchars(number_format((float)($order['total_ht'] ?? 0), 2, ',', ' ')) ?> €</td>
                        <td class="p-12 border-b border-border text-center whitespace-nowrap">
                            <a href="index.php?page=stock_list&search_term=<?= urlencode($order['numero_commande']) ?>" class="no-underline mr-10 hover:opacity-80 transition-opacity" title="Voir la liste">🔍</a>
                            <a href="index.php?page=orders_edit&supplier=<?= urlencode($order['fournisseur']) ?>&order=<?= urlencode($order['numero_commande']) ?>" class="no-underline mr-10 hover:opacity-80 transition-opacity" title="Modifier (Date/Fichiers)">✏️</a>
                            <a href="index.php?page=stock_add&supplier=<?= urlencode($order['fournisseur']) ?>&order=<?= urlencode($order['numero_commande']) ?>" class="no-underline hover:opacity-80 transition-opacity" title="Ajouter un article à cette commande">➕</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-20 text-center flex justify-center gap-5">
        <?php if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>" class="btn btn-sm btn-secondary">&laquo; Précédent</a>
        <?php endif; ?>

        <?php
        $pageWindow = 2; 
        $windowStart = max(1, $currentPage - $pageWindow);
        $windowEnd = min($totalPages, $currentPage + $pageWindow);

        if ($windowStart > 1) echo '<span class="px-10 py-5 text-muted opacity-50">...</span>';

        for ($i = $windowStart; $i <= $windowEnd; $i++):
            if ($i == $currentPage): ?>
                <strong class="btn btn-sm btn-primary"><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" class="btn btn-sm btn-secondary"><?= $i ?></a>
            <?php endif;
        endfor;

        if ($windowEnd < $totalPages) echo '<span class="px-10 py-5 text-muted opacity-50">...</span>';
        
        if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>" class="btn btn-sm btn-secondary">Suivant &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
