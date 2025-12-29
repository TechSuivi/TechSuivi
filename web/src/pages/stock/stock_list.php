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
        echo "<p>Erreur lors de la récupération des articles : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
}
?>

<style>
    /* Table Styling */
    .stock-table {
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
    .stock-table thead {
        background-color: #f1f3f5;
    }
    .stock-table th {
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        font-size: 0.85em;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }
    .stock-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
        color: inherit;
    }
    .stock-table tr:last-child td {
        border-bottom: none;
    }
    .stock-table tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s;
    }
    
    /* Specific Column Styling */
    .col-id { width: 50px; text-align: center; color: #868e96; }
    .col-ref { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #d63384; font-weight: bold; }
    .col-ean { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #6610f2; }
    .col-sn { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #fd7e14; font-size: 0.9em; }
    .col-designation { font-weight: 500; }
    .col-price { text-align: right; font-weight: bold; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .col-price-buy { color: #666; }
    .col-price-sell { color: #198754; font-size: 1.05em; }
    .col-date { text-align: center; font-size: 0.9em; color: #868e96; }
    .col-supplier { color: #0d6efd; font-weight: 500; }
    .col-cmd { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #495057; }
    
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
        transition: border-color 0.2s;
    }
    .search-input:focus {
        border-color: #4dabf7;
        outline: none;
        box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
    }
    .btn-search {
        padding: 10px 20px;
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-search:hover { background-color: #0b5ed7; }
    .btn-clear {
        padding: 10px 15px;
        background-color: #e9ecef;
        color: #495057;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
        transition: background-color 0.2s;
    }
    .btn-clear:hover { background-color: #dee2e6; color: #212529; }

    /* DARK MODE SUPPORT */
    /* Correction: Utilisation de body.dark comme défini dans style.css */
    body.dark .stock-table,
    body.dark .search-container,
    body.dark .no-results-box {
        background-color: var(--card-bg) !important;
        color: var(--text-color) !important;
        border-color: var(--border-color) !important;
    }
    body.dark .stock-table thead {
        background-color: #343a40; /* Garder un header distinct */
    }
    body.dark .stock-table th {
        color: #adb5bd;
        border-bottom-color: var(--border-color);
    }
    body.dark .stock-table td {
        border-bottom-color: #343a40;
        color: var(--text-color);
    }
    body.dark .stock-table tr:hover {
        background-color: var(--hover-color) !important;
    }
    body.dark .search-input {
        background-color: #343a40;
        border-color: var(--border-color);
        color: var(--text-color);
    }
    body.dark .col-ref { color: #ff8787; } /* Rouge plus clair */
    body.dark .col-ean { color: #b197fc; } /* Violet plus clair */
    body.dark .col-supplier { color: #74c0fc; } /* Bleu plus clair */
    body.dark .col-price-buy { color: #adb5bd; }
    body.dark .col-price-sell { color: #69db7c; } /* Vert plus clair */
    body.dark .col-id { color: #6c757d; }
    body.dark h1 { color: var(--text-color) !important; }
    body.dark .btn-clear { background-color: var(--border-color); color: var(--text-color); }
    body.dark .btn-clear:hover { background-color: #343a40; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 style="color: #343a40; font-size: 24px; margin: 0;">📦 Liste du Stock</h1>
    <a href="index.php?page=stock_add" style="padding: 10px 20px; background-color: #198754; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">+ Ajouter un produit</a>
</div>

<!-- Search Form -->
<form method="GET" action="index.php" class="search-container">
    <input type="hidden" name="page" value="stock_list">
    <input type="text" name="search_term" class="search-input" placeholder="🔍 Rechercher par référence, EAN, désignation..." value="<?= htmlspecialchars($searchTerm) ?>">
    <input type="submit" value="Rechercher" class="btn-search">
    <?php
    $clearSearchUrl = "index.php?page=stock_list";
    if (isset($_GET['sort_by'])) $clearSearchUrl .= "&sort_by=" . urlencode($_GET['sort_by']);
    if (isset($_GET['sort_dir'])) $clearSearchUrl .= "&sort_dir=" . urlencode($_GET['sort_dir']);
    ?>
    <?php if (!empty($searchTerm)): ?>
        <a href="<?= $clearSearchUrl ?>" class="btn-clear">Effacer</a>
    <?php endif; ?>
</form>

<?php if (empty($stockItems) && $totalItems == 0): ?>
    <div style="text-align: center; padding: 40px; color: #868e96; background: #fff; border-radius: 8px;">
        <h3>Aucun article trouvé <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : 'dans le stock' ?>.</h3>
    </div>
<?php elseif (empty($stockItems) && $totalItems > 0 && !empty($searchTerm)): ?>
    <p>Aucun article trouvé pour la recherche "<?= htmlspecialchars($searchTerm) ?>" à cette page. Essayez une autre page ou effacez la recherche.</p>
<?php else: ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; color: #6c757d; font-size: 0.9em;">
        <span><?= $totalItems ?> articles trouvés</span>
        <span>Page <?= $currentPage ?> sur <?= $totalPages ?></span>
    </div>

    <table class="stock-table">
        <thead>
            <tr>
                <?php
                function getSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm, $currentPageNum) {
                    $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                    $arrow = '';
                    $style = "color: inherit; text-decoration: none; display: block;";
                    if ($currentSortBy === $columnKey) {
                        $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                        $style .= " color: #0d6efd;";
                    }
                    $url = "index.php?page=stock_list&sort_by={$columnKey}&sort_dir={$linkSortDir}";
                    if (!empty($currentSearchTerm)) {
                        $url .= "&search_term=" . urlencode($currentSearchTerm);
                    }
                    return "<a href='{$url}' style='{$style}'>{$columnDisplay}{$arrow}</a>";
                }
                ?>
                <th class="col-id"><?= getSortLink('id', '#', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-ref"><?= getSortLink('ref_acadia', 'Réf', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-ean"><?= getSortLink('ean_code', 'EAN', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-sn"><?= getSortLink('SN', 'SN', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-designation"><?= getSortLink('designation', 'Désignation', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-price"><?= getSortLink('prix_achat_ht', 'Achat HT', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-price"><?= getSortLink('prix_vente_ttc', 'Vente TTC', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th class="col-date"><a href="?page=stock_list&sort=date_ajout&dir=<?= $sortDir == 'ASC' ? 'DESC' : 'ASC' ?>&search=<?= urlencode($searchTerm) ?>" style="color: inherit; text-decoration: none;">Ajouté le</a></th>
                <th class="col-date">Date Cde</th>
                <th><a href="?page=stock_list&sort=fournisseur&dir=<?= $sortDir == 'ASC' ? 'DESC' : 'ASC' ?>&search=<?= urlencode($searchTerm) ?>" style="color: inherit; text-decoration: none;">Fournisseur</a></th>
                <th class="col-cmd"><?= getSortLink('numero_commande', 'N° Cde', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                <th style="text-align: center;">Docs</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stockItems as $item): ?>
                <tr>
                    <td class="col-id"><?= htmlspecialchars((string)($item['id'] ?? '')) ?></td>
                    <td class="col-ref"><?= htmlspecialchars($item['ref_acadia'] ?? '') ?></td>
                    <td class="col-ean"><?= htmlspecialchars($item['ean_code'] ?? '') ?></td>
                    <td class="col-sn">
                        <?php 
                        $sn = $item['SN'] ?? '';
                        if ($sn !== '' && $sn !== '0') {
                            echo htmlspecialchars($sn);
                        }
                        ?>
                    </td>
                    <td class="col-designation" style="max-width: 300px; word-wrap: break-word;"><?= htmlspecialchars($item['designation'] ?? '') ?></td>
                    <td class="col-price col-price-buy"><?= htmlspecialchars(number_format((float)($item['prix_achat_ht'] ?? 0), 2, ',', ' ')) ?> €</td>
                    <td class="col-price col-price-sell"><?= htmlspecialchars(number_format((float)($item['prix_vente_ttc'] ?? 0), 2, ',', ' ')) ?> €</td>
                    <td class="col-date"><?= htmlspecialchars($item['date_ajout'] ? date('d/m/Y', strtotime($item['date_ajout'])) : '') ?></td>
                    <td class="col-date"><?= htmlspecialchars(!empty($item['date_commande']) ? date('d/m/Y', strtotime($item['date_commande'])) : '-') ?></td>
                    <td class="col-supplier"><?= htmlspecialchars($item['fournisseur'] ?? '') ?></td>
                    <td class="col-cmd"><?= htmlspecialchars($item['numero_commande'] ?? '') ?></td>


                    <td style="text-align: center;">
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
                                         echo '<a href="' . htmlspecialchars($path) . '" target="_blank" title="' . htmlspecialchars($name) . '" style="text-decoration: none; font-size: 1.2em; margin-right: 5px; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">📄</a>';
                                    } else {
                                         echo '<a href="#" onclick="return false;" title="Fichier introuvable: ' . htmlspecialchars($name) . '" style="text-decoration: none; cursor: not-allowed; margin-right: 5px; opacity: 0.3;">❌</a>';
                                    }
                                }
                            }
                        } else {
                            echo '<span style="color: #e9ecef;">—</span>';
                        }
                        ?>
                    </td>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination Links -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 20px; text-align: center;">
        <?php
        $baseUrl = "index.php?page=stock_list";
        if (!empty($searchTerm)) {
            $baseUrl .= "&search_term=" . urlencode($searchTerm);
        }
        $baseUrl .= "&sort_by=" . urlencode($sortBy) . "&sort_dir=" . urlencode($sortDir);

        if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;">&laquo; Précédent</a>
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
                <span style="padding: 5px 10px; margin: 0 2px;">...</span>
            <?php endif; ?>

            <?php if ($i == $currentPage): ?>
                <strong style="padding: 5px 10px; margin: 0 2px; border: 1px solid #007bff; background-color: #007bff; color: white;"><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;"><?= $i ?></a>
            <?php endif; ?>
            <?php $lastPrintedPage = $i; ?>
        <?php endforeach; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>" style="padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none;">Suivant &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>