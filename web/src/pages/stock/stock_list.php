<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Ce fichier est destiné à être inclus dans index.php
// La connexion $pdo et la session sont gérées par index.php

// Fetch stock items from the database
$stockItems = [];
$searchTerm = trim($_GET['search_term'] ?? ''); // Get search term and trim whitespace
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1; // Get current page number
$itemsPerPage = 20; // Number of items per page (20 as requested)

// Sorting parameters
$sortableColumns = ['id', 'ref_acadia', 'ean_code', 'designation', 'prix_achat_ht', 'prix_vente_ttc', 'date_ajout', 'fournisseur', 'numero_commande', 'SN'];
$sortBy = $_GET['sort_by'] ?? 'date_ajout'; // Default sort column
$sortDir = $_GET['sort_dir'] ?? 'DESC'; // Default sort direction (newest first)

// Validate sortBy and sortDir
if (!in_array($sortBy, $sortableColumns)) {
    $sortBy = 'date_ajout'; // Fallback to default if invalid
}
if (strtoupper($sortDir) !== 'ASC' && strtoupper($sortDir) !== 'DESC') {
    $sortDir = 'DESC'; // Fallback to default if invalid
}

$totalItems = 0;
$totalPages = 1; // Default to 1 page even if no results
$offset = 0;

if (isset($pdo)) { // Assurez-vous que $pdo est disponible
    try {
        // First, get the total number of stock items matching the search (for pagination)
        $countSql = "SELECT COUNT(*) FROM Stock";
        $countParams = [];
        $searchTermLower = strtolower($searchTerm);

        if (!empty($searchTerm)) {
            $countSql .= " WHERE (LOWER(ref_acadia) LIKE :searchRef
                           OR LOWER(ean_code) LIKE :searchEan
                           OR LOWER(designation) LIKE :searchDesignation
                           OR LOWER(fournisseur) LIKE :searchFournisseur
                           OR LOWER(numero_commande) LIKE :searchCommande
                           OR LOWER(SN) LIKE :searchSN)";
            $likePattern = '%' . $searchTermLower . '%';
            $countParams[':searchRef'] = $likePattern;
            $countParams[':searchEan'] = $likePattern;
            $countParams[':searchDesignation'] = $likePattern;
            $countParams[':searchFournisseur'] = $likePattern;
            $countParams[':searchCommande'] = $likePattern;
            $countParams[':searchSN'] = $likePattern;
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalItems = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        if ($totalPages == 0) {
            $totalPages = 1;
        }

        // Adjust currentPage if out of bounds
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $offset = ($currentPage - 1) * $itemsPerPage;

        // Now, fetch the stock items for the current page
        $sql = "SELECT s.id, s.ref_acadia, s.ean_code, s.designation, s.prix_achat_ht, s.prix_vente_ttc, s.date_ajout, s.fournisseur, s.numero_commande, s.date_commande, s.SN,
                GROUP_CONCAT(DISTINCT CONCAT(d.file_path, '|', d.original_name) SEPARATOR ';') as documents
                FROM Stock s
                LEFT JOIN stock_documents d ON s.fournisseur = d.fournisseur AND s.numero_commande = d.numero_commande";
        $params = []; // Parameters for the main query

        if (!empty($searchTerm)) {
            $sql .= " WHERE (LOWER(s.ref_acadia) LIKE :searchRefFetch
                      OR LOWER(s.ean_code) LIKE :searchEanFetch
                      OR LOWER(s.designation) LIKE :searchDesignationFetch
                      OR LOWER(s.fournisseur) LIKE :searchFournisseurFetch
                      OR LOWER(s.numero_commande) LIKE :searchCommandeFetch
                      OR LOWER(s.SN) LIKE :searchSNFetch)";
            $likePatternFetch = '%' . $searchTermLower . '%';
            $params[':searchRefFetch'] = $likePatternFetch;
            $params[':searchEanFetch'] = $likePatternFetch;
            $params[':searchDesignationFetch'] = $likePatternFetch;
            $params[':searchFournisseurFetch'] = $likePatternFetch;
            $params[':searchCommandeFetch'] = $likePatternFetch;
            $params[':searchSNFetch'] = $likePatternFetch;
        }
        
        $sql .= " GROUP BY s.id "; // Important pour le GROUP_CONCAT
        $sql .= " ORDER BY " . $sortBy . " " . $sortDir; // $sortBy is whitelisted
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = (int)$itemsPerPage;
        $params[':offset'] = (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); 
        $stockItems = $stmt->fetchAll();

    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Erreur lors de la récupération des articles : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Erreur de configuration : la connexion à la base de données n'est pas disponible.</div>";
}
?>

<div class="flex-between-center mb-20">
    <h1 class="m-0 text-xl font-bold text-dark flex items-center gap-10">
        📦 Liste du Stock
    </h1>
    <a href="index.php?page=stock_add" class="btn btn-primary">
        + Ajouter un produit
    </a>
</div>

<!-- Search Form -->
<div class="card p-20 mb-25">
    <form method="GET" action="index.php" class="flex gap-10 flex-wrap items-center">
        <input type="hidden" name="page" value="stock_list">
        <div class="flex-grow">
            <input type="text" name="search_term" class="form-control w-full p-10 border rounded bg-input text-color" placeholder="🔍 Rechercher par référence, EAN, désignation..." value="<?= htmlspecialchars($searchTerm) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php
        $clearSearchUrl = "index.php?page=stock_list";
        if (isset($_GET['sort_by'])) $clearSearchUrl .= "&sort_by=" . urlencode($_GET['sort_by']);
        if (isset($_GET['sort_dir'])) $clearSearchUrl .= "&sort_dir=" . urlencode($_GET['sort_dir']);
        ?>
        <?php if (!empty($searchTerm)): ?>
            <a href="<?= $clearSearchUrl ?>" class="btn btn-secondary">Effacer</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($stockItems) && $totalItems == 0): ?>
    <div class="card p-40 text-center text-muted">
        <h3 class="mt-0">Aucun article trouvé <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : 'dans le stock' ?>.</h3>
    </div>
<?php elseif (empty($stockItems) && $totalItems > 0 && !empty($searchTerm)): ?>
    <div class="alert alert-info">
        Aucun article trouvé pour la recherche "<?= htmlspecialchars($searchTerm) ?>" à cette page. Essayez une autre page ou effacez la recherche.
    </div>
<?php else: ?>
    <div class="flex-between-center mb-10 text-muted text-sm">
        <span><?= $totalItems ?> articles trouvés</span>
        <span>Page <?= $currentPage ?> sur <?= $totalPages ?></span>
    </div>

    <?php
    // Détection si on affiche une seule commande
    $uniqueOrders = [];
    foreach ($stockItems as $item) {
        if (!empty($item['numero_commande'])) {
            $key = $item['fournisseur'] . '|' . $item['numero_commande'];
            if (!isset($uniqueOrders[$key])) {
                $uniqueOrders[$key] = $item;
            }
        }
    }

    $singleOrderMode = (count($uniqueOrders) === 1);
    
    if ($singleOrderMode) {
        $orderData = reset($uniqueOrders);
        $orderNum = $orderData['numero_commande'];
        $supplier = $orderData['fournisseur'];
        $dateCmd = $orderData['date_commande'];
        $docsStrHeader = $orderData['documents'] ?? '';
    ?>
        <!-- Bloc d'action de commande (Design Unifié) -->
        <div class="card p-20 mb-25 border-l-4 border-l-primary flex flex-wrap justify-between items-center gap-20">
            <div class="flex flex-col gap-5">
                <h3 class="m-0 text-lg text-color flex items-center gap-10">
                    📦 Commande <span class="badge badge-secondary font-mono"><?= htmlspecialchars($orderNum) ?></span>
                </h3>
                <div class="text-muted flex items-center gap-15 text-sm">
                    <span>🏢 <strong class="text-color"><?= htmlspecialchars($supplier) ?></strong></span>
                    <?php if(!empty($dateCmd)): ?>
                        <span>📅 <?= date('d/m/Y', strtotime($dateCmd)) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Documents -->
                <?php if (!empty($docsStrHeader)): ?>
                <div class="mt-15 flex gap-10 flex-wrap">
                    <?php
                    $docs = explode(';', $docsStrHeader);
                    foreach ($docs as $docStr) {
                        $parts = explode('|', $docStr);
                        if (count($parts) >= 1) {
                            $path = $parts[0];
                            $name = $parts[1] ?? 'Doc';
                            if (file_exists(__DIR__ . '/../../' . $path)) {
                                echo '<a href="' . htmlspecialchars($path) . '" target="_blank" class="btn btn-xs btn-outline-secondary flex items-center gap-5">📄 ' . htmlspecialchars($name) . '</a>';
                            }
                        }
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-10">
                <a href="index.php?page=orders_edit&supplier=<?= urlencode($supplier) ?>&order=<?= urlencode($orderNum) ?>" class="btn btn-warning flex items-center gap-5">
                    ✏️ Modifier / Docs
                </a>
                <a href="index.php?page=stock_add&supplier=<?= urlencode($supplier) ?>&order=<?= urlencode($orderNum) ?>" class="btn btn-success flex items-center gap-5">
                    ➕ Ajouter Article
                </a>
            </div>
        </div>
    <?php } ?>

    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-muted uppercase text-xs">
                        <?php
                        function getSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm, $currentPageNum) {
                            $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                            $arrow = '';
                            $currentClass = 'text-inherit';
                            if ($currentSortBy === $columnKey) {
                                $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                                $currentClass = 'text-primary font-bold';
                            }
                            $url = "index.php?page=stock_list&sort_by={$columnKey}&sort_dir={$linkSortDir}";
                            if (!empty($currentSearchTerm)) {
                                $url .= "&search_term=" . urlencode($currentSearchTerm);
                            }
                            return "<a href='{$url}' class='{$currentClass} block hover:text-primary transition-colors'>{$columnDisplay}{$arrow}</a>";
                        }
                        ?>
                        <th class="p-15 font-semibold whitespace-nowrap text-center w-50"><?= getSortLink('id', '#', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap"><?= getSortLink('ref_acadia', 'Réf', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap"><?= getSortLink('ean_code', 'EAN', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap"><?= getSortLink('SN', 'SN', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap"><?= getSortLink('designation', 'Désignation', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap text-right"><?= getSortLink('prix_achat_ht', 'Achat HT', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap text-right"><?= getSortLink('prix_vente_ttc', 'Vente TTC', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <th class="p-15 font-semibold whitespace-nowrap text-center"><a href="?page=stock_list&sort=date_ajout&dir=<?= $sortDir == 'ASC' ? 'DESC' : 'ASC' ?>&search=<?= urlencode($searchTerm) ?>" class="text-inherit hover:text-primary transition-colors">Ajouté le</a></th>
                        <th class="p-15 font-semibold whitespace-nowrap text-center">Date Cde</th>
                        <th class="p-15 font-semibold whitespace-nowrap"><a href="?page=stock_list&sort=fournisseur&dir=<?= $sortDir == 'ASC' ? 'DESC' : 'ASC' ?>&search=<?= urlencode($searchTerm) ?>" class="text-inherit hover:text-primary transition-colors">Fournisseur</a></th>
                        <th class="p-15 font-semibold whitespace-nowrap"><?= getSortLink('numero_commande', 'N° Cde', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                        <?php if (!$singleOrderMode): ?>
                            <th class="p-15 font-semibold whitespace-nowrap text-center">Docs</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php foreach ($stockItems as $item): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-15 text-center text-muted"><?= htmlspecialchars((string)($item['id'] ?? '')) ?></td>
                            <td class="p-15 font-mono text-primary font-bold"><?= htmlspecialchars($item['ref_acadia'] ?? '') ?></td>
                            <td class="p-15 font-mono text-muted"><?= htmlspecialchars($item['ean_code'] ?? '') ?></td>
                            <td class="p-15 font-mono text-warning text-xs">
                                <?php 
                                $sn = $item['SN'] ?? '';
                                if ($sn !== '' && $sn !== '0') {
                                    echo htmlspecialchars($sn);
                                }
                                ?>
                            </td>
                            <td class="p-15 font-medium max-w-xs break-words"><?= htmlspecialchars($item['designation'] ?? '') ?></td>
                            <td class="p-15 text-right font-mono text-muted"><?= htmlspecialchars(number_format((float)($item['prix_achat_ht'] ?? 0), 2, ',', ' ')) ?> €</td>
                            <td class="p-15 text-right font-mono text-success font-bold text-base"><?= htmlspecialchars(number_format((float)($item['prix_vente_ttc'] ?? 0), 2, ',', ' ')) ?> €</td>
                            <td class="p-15 text-center text-muted text-xs"><?= htmlspecialchars($item['date_ajout'] ? date('d/m/Y', strtotime($item['date_ajout'])) : '') ?></td>
                            <td class="p-15 text-center text-muted text-xs"><?= htmlspecialchars(!empty($item['date_commande']) ? date('d/m/Y', strtotime($item['date_commande'])) : '-') ?></td>
                            <td class="p-15 text-info"><?= htmlspecialchars($item['fournisseur'] ?? '') ?></td>
                            <td class="p-15 font-mono text-color"><?= htmlspecialchars($item['numero_commande'] ?? '') ?></td>

                            <?php if (!$singleOrderMode): ?>
                            <td class="p-15 text-center">
                                <?php 
                                $docsStr = $item['documents'] ?? '';
                                if (!empty($docsStr)) {
                                    $docs = explode(';', $docsStr);
                                    foreach ($docs as $docStr) {
                                        $parts = explode('|', $docStr);
                                        if (count($parts) >= 1) {
                                            $path = $parts[0];
                                            $name = $parts[1] ?? 'Doc';
                                            
                                            // Vérif existance
                                            if (file_exists(__DIR__ . '/../../' . $path)) {
                                                 echo '<a href="' . htmlspecialchars($path) . '" target="_blank" title="' . htmlspecialchars($name) . '" class="text-lg opacity-70 hover:opacity-100 transition-opacity mr-5 no-underline">📄</a>';
                                            } else {
                                                 echo '<a href="#" onclick="return false;" title="Fichier introuvable: ' . htmlspecialchars($name) . '" class="text-lg opacity-30 cursor-not-allowed mr-5 no-underline">❌</a>';
                                            }
                                        }
                                    }
                                } else {
                                    echo '<span class="text-muted opacity-30">—</span>';
                                }
                                ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Links -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center mt-20 gap-5">
        <?php
        $baseUrl = "index.php?page=stock_list";
        if (!empty($searchTerm)) {
            $baseUrl .= "&search_term=" . urlencode($searchTerm);
        }
        $baseUrl .= "&sort_by=" . urlencode($sortBy) . "&sort_dir=" . urlencode($sortDir);

        if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>" class="btn btn-sm btn-secondary">&laquo; Précédent</a>
        <?php endif; ?>

        <?php
        $pageWindow = 2; 
        $pagesToDisplay = [];

        if ($totalPages >= 1) $pagesToDisplay[1] = true;

        $windowStart = max(1, $currentPage - $pageWindow);
        $windowEnd = min($totalPages, $currentPage + $pageWindow);

        for ($i = $windowStart; $i <= $windowEnd; $i++) {
            if ($i > 0) $pagesToDisplay[$i] = true;
        }

        if ($totalPages >= 1) $pagesToDisplay[$totalPages] = true;
        
        ksort($pagesToDisplay);

        $lastPrintedPage = 0;
        foreach (array_keys($pagesToDisplay) as $i):
            if ($lastPrintedPage > 0 && $i > $lastPrintedPage + 1): ?>
                <span class="btn btn-sm btn-disabled opacity-50">...</span>
            <?php endif; ?>

            <?php if ($i == $currentPage): ?>
                <strong class="btn btn-sm btn-primary"><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" class="btn btn-sm btn-secondary"><?= $i ?></a>
            <?php endif; ?>
            <?php $lastPrintedPage = $i; ?>
        <?php endforeach; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>" class="btn btn-sm btn-secondary">Suivant &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>