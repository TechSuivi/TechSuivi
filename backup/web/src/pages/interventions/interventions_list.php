<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
// La session est démarrée dans index.php, donc $_SESSION est disponible.
$interventions = [];
$errorMessage = '';
$sessionMessage = '';

// Récupérer le terme de recherche et les paramètres de pagination
$searchTerm = trim($_GET['search'] ?? '');
$itemsPerPage = (int)($_GET['per_page'] ?? 20); // Par défaut 20 éléments par page
$currentPage = max(1, (int)($_GET['page_num'] ?? 1)); // Page courante (minimum 1)
$hideClosed = isset($_GET['hide_closed']) && $_GET['hide_closed'] === '1'; // Masquer les interventions clôturées

// Paramètres de tri
$sortBy = $_GET['sort_by'] ?? 'date'; // Colonne de tri par défaut: date
$sortDir = $_GET['sort_dir'] ?? 'DESC'; // Direction par défaut: DESC (plus récent d'abord)

// Valider les paramètres de tri
$allowedSortColumns = ['date', 'client', 'statut'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'date';
}

$allowedSortDirections = ['ASC', 'DESC'];
if (!in_array(strtoupper($sortDir), $allowedSortDirections)) {
    $sortDir = 'DESC';
}
$sortDir = strtoupper($sortDir);

// Valider le nombre d'éléments par page
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($itemsPerPage, $allowedPerPage)) {
    $itemsPerPage = 20; // Valeur par défaut si invalide
}

// Calculer l'offset pour la pagination
$offset = ($currentPage - 1) * $itemsPerPage;

if (isset($_SESSION['delete_message'])) {
    $sessionMessage = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']); // Efface le message pour qu'il ne s'affiche qu'une fois
}
if (isset($_SESSION['edit_message'])) { // Au cas où on ajouterait des messages pour l'édition
    $sessionMessage .= $_SESSION['edit_message']; // Concaténer si plusieurs messages
    unset($_SESSION['edit_message']);
}

if (isset($pdo)) {
    try {
        // Vérifier si la table intervention_statuts existe
        $tableExists = false;
        try {
            $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
            $tableExists = true;
        } catch (PDOException $e) {
            // Table n'existe pas encore
        }
        
        // Construire la requête avec ou sans recherche et filtrage
        $whereConditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $whereConditions[] = "(LOWER(c.nom) LIKE :search1 OR LOWER(c.prenom) LIKE :search2 OR LOWER(CONCAT(c.nom, ' ', c.prenom)) LIKE :search3)";
            $searchPattern = '%' . strtolower($searchTerm) . '%';
            $params[':search1'] = $searchPattern;
            $params[':search2'] = $searchPattern;
            $params[':search3'] = $searchPattern;
        }
        
        if ($hideClosed) {
            $whereConditions[] = "i.en_cours = 1";
        }
        
        $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";
        
        // D'abord, compter le nombre total d'interventions
        $countSql = "
            SELECT COUNT(*) as total
            FROM inter i
            LEFT JOIN clients c ON i.id_client = c.ID
            $whereClause
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalInterventions = $countStmt->fetch()['total'];
        
        // Calculer le nombre total de pages
        $totalPages = ceil($totalInterventions / $itemsPerPage);
        
        // S'assurer que la page courante n'excède pas le nombre total de pages
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
            $offset = ($currentPage - 1) * $itemsPerPage;
        }
        
        // Ajouter les paramètres de pagination
        $params[':limit'] = $itemsPerPage;
        $params[':offset'] = $offset;
        
        // Construire la clause ORDER BY
        $orderByClause = '';
        switch ($sortBy) {
            case 'client':
                $orderByClause = "ORDER BY c.nom $sortDir, c.prenom $sortDir";
                break;
            case 'statut':
                if ($tableExists) {
                    $orderByClause = "ORDER BY s.nom $sortDir, i.date DESC";
                } else {
                    $orderByClause = "ORDER BY i.en_cours $sortDir, i.date DESC";
                }
                break;
            case 'date':
            default:
                $orderByClause = "ORDER BY i.date $sortDir";
                break;
        }
        
        if ($tableExists) {
            $sql = "
                SELECT
                    i.id,
                    i.id_client,
                    i.date,
                    i.en_cours,
                    i.statut_id,
                    i.info,
                    i.nettoyage,
                    i.info_log,
                    i.note_user,
                    CONCAT(c.nom, ' ', c.prenom) as client_nom,
                    s.nom as statut_nom,
                    s.couleur as statut_couleur
                FROM inter i
                LEFT JOIN clients c ON i.id_client = c.ID
                LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                $whereClause
                $orderByClause
                LIMIT :limit OFFSET :offset
            ";
        } else {
            // Fallback sans les statuts
            $sql = "
                SELECT
                    i.id,
                    i.id_client,
                    i.date,
                    i.en_cours,
                    i.info,
                    i.nettoyage,
                    i.info_log,
                    i.note_user,
                    CONCAT(c.nom, ' ', c.prenom) as client_nom
                FROM inter i
                LEFT JOIN clients c ON i.id_client = c.ID
                $whereClause
                $orderByClause
                LIMIT :limit OFFSET :offset
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $interventions = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des interventions : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>

<link rel="stylesheet" href="css/awesomplete.css">
<style>
/* Modern styles for interventions list page */
.list-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.controls-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.controls-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.controls-right {
    display: flex;
    align-items: center;
    gap: 15px;
    white-space: nowrap;
}

.btn-add {
    text-decoration: none;
    padding: 10px 20px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border-radius: 8px;
    white-space: nowrap;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
}

.search-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-input-wrapper {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 0.95em;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

.search-spinner {
    display: none;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #3498db;
}

.btn-clear {
    padding: 10px 16px;
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-clear:hover {
    background: var(--hover-bg);
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.filter-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #3498db;
}

.filter-group label {
    margin: 0;
    cursor: pointer;
    user-select: none;
    color: var(--text-color);
    font-weight: 500;
}

.select-per-page {
    padding: 8px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.3s ease;
}

.select-per-page:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

.pagination-info {
    text-align: center;
    margin-bottom: 15px;
    color: var(--text-muted);
    font-size: 0.9em;
}

.search-indicator {
    padding: 12px 16px;
    background: #e3f2fd;
    border-radius: 8px;
    border-left: 4px solid #3498db;
    margin-bottom: 20px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.search-indicator strong {
    color: #333;
}

.search-term {
    color: #3498db;
    font-weight: bold;
}

.pagination-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 20px 0;
}

.pagination-nav a,
.pagination-nav .page-number,
.pagination-nav .current-page,
.pagination-nav .disabled {
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.9em;
    transition: all 0.2s ease;
}

.pagination-nav a {
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.pagination-nav a:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
}

.pagination-nav .current-page {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    font-weight: bold;
    border: none;
}

.pagination-nav .disabled {
    background: var(--input-bg);
    color: var(--text-muted);
    opacity: 0.5;
    cursor: not-allowed;
    border: 1px solid var(--border-color);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-icon {
    font-size: 1.5em;
    flex-shrink: 0;
}

/* Modern Table Styles */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

table thead {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: white;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

table tbody tr:hover {
    background: var(--hover-bg, #f8f9fa);
    transform: scale(1.001);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

table tbody tr:last-child {
    border-bottom: none;
}

table td {
    padding: 12px 15px;
    color: var(--text-color);
    font-size: 0.95em;
}

table td a {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

table td a:hover {
    color: #2980b9;
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--card-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.empty-icon {
    font-size: 3.5em;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    color: var(--text-muted);
    margin: 10px 0;
}

.empty-state strong {
    color: #3498db;
}

.empty-state button,
.empty-state a {
    padding: 12px 24px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.empty-state button:hover,
.empty-state a:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
}

/* Sortable table headers */
table th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 25px;
}

table th.sortable:hover {
    background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
}

table th.sortable::after {
    content: '↕';
    position: absolute;
    right: 10px;
    opacity: 0.4;
    font-size: 0.8em;
}

table th.sortable.sort-active::after {
    opacity: 1;
}

table th.sortable.sort-asc::after {
    content: '↑';
}

table th.sortable.sort-desc::after {
    content: '↓';
}

/* Dark mode compatibility */
body.dark .search-indicator {
    background-color: #2b2b2b;
    border-left-color: #3498db;
    color: #fff;
}

body.dark .pagination-nav a:hover {
    background-color: #3498db;
    color: #fff;
}

@media (max-width: 768px) {
    .controls-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .controls-left,
    .controls-right {
        width: 100%;
        justify-content: space-between;
    }
    
    .search-input-wrapper {
        width: 100%;
    }
    
    table {
        font-size: 0.85em;
    }
    
    table th,
    table td {
        padding: 10px 8px;
    }
}
</style>

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>🔧</span>
            Liste des Interventions
        </h1>
    </div>

    <div class="controls-card">
        <div class="controls-row">
            <div class="controls-left">
                <button type="button" class="btn-add" onclick="openAddInterventionModal()">
                    <span>➕</span>
                    Ajouter une intervention
                </button>
                
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <input type="text"
                               id="search-input"
                               class="search-input"
                               placeholder="Rechercher par nom de client..."
                               value="<?= htmlspecialchars($searchTerm) ?>">
                        <div id="search-spinner" class="search-spinner">⏳</div>
                    </div>
                    <button id="clear-search" class="btn-clear" style="display: <?= !empty($searchTerm) ? 'block' : 'none' ?>;">
                        ✕ Effacer
                    </button>
                </div>
            </div>
            
            <div class="controls-right">
                <div class="filter-group">
                    <input type="checkbox" id="hide-closed" <?= $hideClosed ? 'checked' : '' ?>>
                    <label for="hide-closed">Masquer clôturées</label>
                </div>
                
                <select id="items-per-page" class="select-per-page">
                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $itemsPerPage == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
        </div>
    </div>

    <?php if (isset($totalInterventions) && $totalInterventions > 0): ?>
    <div class="pagination-info">
        <?php
        $startItem = $offset + 1;
        $endItem = min($offset + $itemsPerPage, $totalInterventions);
        ?>
        Affichage de <?= $startItem ?> à <?= $endItem ?> sur <?= $totalInterventions ?> intervention(s)
    </div>
    <?php endif; ?>

    <div id="search-indicator" class="search-indicator" style="display: <?= !empty($searchTerm) ? 'block' : 'none' ?>;">
        <strong>Recherche :</strong> "<span id="search-term-display" class="search-term"><?= htmlspecialchars($searchTerm) ?></span>"
        - <span id="search-count-display"><?= isset($totalInterventions) ? $totalInterventions : count($interventions) ?></span> intervention(s) trouvée(s)
    </div>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination-nav">
        <?php
        $urlParams = "page=interventions_list&per_page=$itemsPerPage";
        if (!empty($searchTerm)) $urlParams .= "&search=" . urlencode($searchTerm);
        if ($hideClosed) $urlParams .= "&hide_closed=1";
        if ($sortBy !== 'date' || $sortDir !== 'DESC') {
            $urlParams .= "&sort_by=$sortBy&sort_dir=$sortDir";
        }
        ?>
        
        <?php if ($currentPage > 1): ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $currentPage - 1 ?>">
                ← Précédent
            </a>
        <?php else: ?>
            <span class="disabled">
                ← Précédent
            </span>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);
        
        if ($endPage - $startPage < 4) {
            if ($startPage == 1) {
                $endPage = min($totalPages, $startPage + 4);
            } else {
                $startPage = max(1, $endPage - 4);
            }
        }
        ?>
        
        <?php if ($startPage > 1): ?>
            <a href="?<?= $urlParams ?>&page_num=1">1</a>
            <?php if ($startPage > 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="current-page"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $urlParams ?>&page_num=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
                <span>...</span>
            <?php endif; ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $totalPages ?>"><?= $totalPages ?></a>
        <?php endif; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $currentPage + 1 ?>">
                Suivant →
            </a>
        <?php else: ?>
            <span class="disabled">
                Suivant →
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sessionMessage)): ?>
    <div class="alert alert-success">
        <span class="alert-icon">✅</span>
        <div><?= $sessionMessage ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <span class="alert-icon">⚠️</span>
        <div><?= $errorMessage ?></div>
    </div>
    <?php endif; ?>

    <div id="interventions-container">
        <?php if (empty($interventions) && empty($errorMessage)): ?>
            <?php if (!empty($searchTerm)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <p style="font-size: 18px; margin-bottom: 10px;">Aucune intervention trouvée</p>
                    <p style="margin-bottom: 20px;">
                        Aucune intervention ne correspond à la recherche "<strong><?= htmlspecialchars($searchTerm) ?></strong>"
                    </p>
                    <button onclick="clearSearch()">
                        Voir toutes les interventions
                    </button>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <p style="font-size: 18px; margin-bottom: 10px;">Aucune intervention</p>
                    <p style="margin-bottom: 20px;">
                        Aucune intervention n'a encore été créée.
                    </p>
                    <button type="button" onclick="openAddInterventionModal()" style="padding: 10px 20px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        ➕ Créer la première intervention
                    </button>
                </div>
            <?php endif; ?>
        <?php elseif (!empty($interventions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php
                        // Fonction pour générer les liens de tri
                        function getSortUrl($column, $currentSortBy, $currentSortDir, $searchTerm, $hideClosed, $itemsPerPage) {
                            $newSortDir = ($currentSortBy === $column && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
                            $url = "?page=interventions_list&sort_by=$column&sort_dir=$newSortDir&per_page=$itemsPerPage";
                            if (!empty($searchTerm)) $url .= "&search=" . urlencode($searchTerm);
                            if ($hideClosed) $url .= "&hide_closed=1";
                            return $url;
                        }
                        
                        function getSortClass($column, $currentSortBy, $currentSortDir) {
                            $class = 'sortable';
                            if ($currentSortBy === $column) {
                                $class .= ' sort-active sort-' . strtolower($currentSortDir);
                            }
                            return $class;
                        }
                        ?>
                        <th class="<?= getSortClass('client', $sortBy, $sortDir) ?>">
                            <a href="<?= getSortUrl('client', $sortBy, $sortDir, $searchTerm, $hideClosed, $itemsPerPage) ?>" style="color: inherit; text-decoration: none; display: block;">
                                Client
                            </a>
                        </th>
                        <th class="<?= getSortClass('date', $sortBy, $sortDir) ?>">
                            <a href="<?= getSortUrl('date', $sortBy, $sortDir, $searchTerm, $hideClosed, $itemsPerPage) ?>" style="color: inherit; text-decoration: none; display: block;">
                                Date
                            </a>
                        </th>
                        <th class="<?= getSortClass('statut', $sortBy, $sortDir) ?>">
                            <a href="<?= getSortUrl('statut', $sortBy, $sortDir, $searchTerm, $hideClosed, $itemsPerPage) ?>" style="color: inherit; text-decoration: none; display: block;">
                                Statut
                            </a>
                        </th>
                        <th>Informations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interventions as $intervention): ?>
                        <tr>
                            <td><?= htmlspecialchars($intervention['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($intervention['client_nom'] ?? 'Client inconnu') ?></td>
                            <td><?= htmlspecialchars($intervention['date'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($intervention['statut_nom'])): ?>
                                    <span style="color: <?= htmlspecialchars($intervention['statut_couleur']) ?>; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?= htmlspecialchars($intervention['statut_couleur']) ?>;"></span>
                                        <?= htmlspecialchars($intervention['statut_nom']) ?>
                                    </span>
                                <?php else: ?>
                                    <?php if ($intervention['en_cours'] == 1): ?>
                                        <span style="color: orange; font-weight: bold;">En cours</span>
                                    <?php else: ?>
                                        <span style="color: green; font-weight: bold;">Clôturée</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars(substr($intervention['info'] ?? '', 0, 100)) ?>
                                    <?php if (strlen($intervention['info'] ?? '') > 100): ?>...<?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="index.php?page=interventions_view&id=<?= htmlspecialchars($intervention['id']) ?>" style="margin-right: 10px;">Voir</a>
                                <?php if ($intervention['en_cours'] == 1): ?>
                                    <a href="index.php?page=interventions_edit&id=<?= htmlspecialchars($intervention['id']) ?>" style="margin-right: 10px;">Modifier</a>
                                    <a href="actions/interventions_delete.php?id=<?= htmlspecialchars($intervention['id']) ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette intervention ?');" style="color: red; margin-right: 10px;">Supprimer</a>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-style: italic; margin-right: 10px;">Intervention clôturée</span>
                                <?php endif; ?>
                                <a href="pwa/?intervention_id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none; font-size: 12px;">📱</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination-nav">
        <?php if ($currentPage > 1): ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $currentPage - 1 ?>">
                ← Précédent
            </a>
        <?php else: ?>
            <span class="disabled">
                ← Précédent
            </span>
        <?php endif; ?>
        
        <?php
        $startPageBottom = max(1, $currentPage - 2);
        $endPageBottom = min($totalPages, $currentPage + 2);
        
        if ($endPageBottom - $startPageBottom < 4) {
            if ($startPageBottom == 1) {
                $endPageBottom = min($totalPages, $startPageBottom + 4);
            } else {
                $startPageBottom = max(1, $endPageBottom - 4);
            }
        }
        ?>
        
        <?php if ($startPageBottom > 1): ?>
            <a href="?<?= $urlParams ?>&page_num=1">1</a>
            <?php if ($startPageBottom > 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $startPageBottom; $i <= $endPageBottom; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="current-page"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $urlParams ?>&page_num=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($endPageBottom < $totalPages): ?>
            <?php if ($endPageBottom < $totalPages - 1): ?>
                <span>...</span>
            <?php endif; ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $totalPages ?>"><?= $totalPages ?></a>
        <?php endif; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="?<?= $urlParams ?>&page_num=<?= $currentPage + 1 ?>">
                Suivant →
            </a>
        <?php else: ?>
            <span class="disabled">
                Suivant →
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const searchSpinner = document.getElementById('search-spinner');
    const clearButton = document.getElementById('clear-search');
    const searchIndicator = document.getElementById('search-indicator');
    const searchTermDisplay = document.getElementById('search-term-display');
    const searchCountDisplay = document.getElementById('search-count-display');
    const interventionsContainer = document.getElementById('interventions-container');
    const itemsPerPageSelect = document.getElementById('items-per-page');
    const hideClosedCheckbox = document.getElementById('hide-closed');
    
    let searchTimeout;
    let currentSearchTerm = '<?= htmlspecialchars($searchTerm) ?>';
    let currentItemsPerPage = <?= $itemsPerPage ?>;
    let currentHideClosed = <?= $hideClosed ? 'true' : 'false' ?>;
    
    // Focus automatique si recherche active
    <?php if (!empty($searchTerm)): ?>
    searchInput.focus();
    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    <?php endif; ?>
    
    // Fonction pour effectuer la recherche
    function performSearch(searchTerm) {
        // Afficher le spinner
        searchSpinner.style.display = 'block';
        
        // Faire la requête AJAX
        fetch(`api/search_interventions.php?search=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateResults(data.data, searchTerm, data.count, data.has_statuts);
                    currentSearchTerm = searchTerm;
                    
                    // Mettre à jour l'URL sans recharger la page
                    const url = new URL(window.location);
                    if (searchTerm) {
                        url.searchParams.set('search', searchTerm);
                    } else {
                        url.searchParams.delete('search');
                    }
                    window.history.replaceState({}, '', url);
                } else {
                    console.error('Erreur de recherche:', data.message);
                }
            })
            .catch(error => {
                console.error('Erreur réseau:', error);
            })
            .finally(() => {
                // Cacher le spinner
                searchSpinner.style.display = 'none';
            });
    }
    
    // Fonction pour mettre à jour les résultats
    function updateResults(interventions, searchTerm, count, hasStatuts) {
        // Mettre à jour l'indicateur de recherche
        if (searchTerm) {
            searchIndicator.style.display = 'block';
            searchTermDisplay.textContent = searchTerm;
            searchCountDisplay.textContent = count;
            clearButton.style.display = 'block';
        } else {
            searchIndicator.style.display = 'none';
            clearButton.style.display = 'none';
        }
        
        // Générer le HTML des résultats
        let html = '';
        
        if (interventions.length === 0) {
            if (searchTerm) {
                html = `
                    <div class="no-results-message" style="text-align: center; padding: 40px; background-color: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                        <p style="font-size: 18px; color: var(--text-muted, #666); margin-bottom: 10px;">🔍 Aucune intervention trouvée</p>
                        <p style="color: var(--text-muted, #666); margin-bottom: 20px;">
                            Aucune intervention ne correspond à la recherche "<strong style="color: var(--accent-color, #007bff);">${escapeHtml(searchTerm)}</strong>"
                        </p>
                        <button onclick="clearSearch()"
                               style="padding: 8px 15px; background-color: var(--accent-color, #007bff); color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Voir toutes les interventions
                        </button>
                    </div>
                `;
            } else {
                html = `
                    <div class="no-results-message" style="text-align: center; padding: 40px; background-color: var(--bg-secondary, #f8f9fa); border-radius: 8px; border: 1px solid var(--border-color, #dee2e6);">
                        <p style="font-size: 18px; color: var(--text-muted, #666); margin-bottom: 10px;">📋 Aucune intervention</p>
                        <p style="color: var(--text-muted, #666); margin-bottom: 20px;">
                            Aucune intervention n'a encore été créée.
                        </p>
                        <button type="button" onclick="openAddInterventionModal()"
                           style="padding: 10px 20px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                            ➕ Créer la première intervention
                        </button>
                    </div>
                `;
            }
        } else {
            html = `
                <table style="color: var(--text-color, #333);">
                    <thead>
                        <tr>
                            <th style="color: var(--text-color, #333);">ID</th>
                            <th style="color: var(--text-color, #333);">Client</th>
                            <th style="color: var(--text-color, #333);">Date</th>
                            <th style="color: var(--text-color, #333);">Statut</th>
                            <th style="color: var(--text-color, #333);">Informations</th>
                            <th style="color: var(--text-color, #333);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            interventions.forEach(intervention => {
                let statutHtml = '';
                if (intervention.statut_nom) {
                    statutHtml = `
                        <span style="color: ${escapeHtml(intervention.statut_couleur)}; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: ${escapeHtml(intervention.statut_couleur)};"></span>
                            ${escapeHtml(intervention.statut_nom)}
                        </span>
                    `;
                } else {
                    if (intervention.en_cours == 1) {
                        statutHtml = '<span style="color: orange; font-weight: bold;">En cours</span>';
                    } else {
                        statutHtml = '<span style="color: green; font-weight: bold;">Clôturée</span>';
                    }
                }
                
                let actionsHtml = '';
                actionsHtml += `<a href="index.php?page=interventions_view&id=${escapeHtml(intervention.id)}" style="margin-right: 10px;">Voir</a>`;
                actionsHtml += `<a href="#" onclick="printIntervention('${escapeHtml(intervention.id)}'); return false;" style="margin-right: 10px;" title="Imprimer">🖨️</a>`;
                
                if (intervention.en_cours == 1) {
                    actionsHtml += `<a href="index.php?page=interventions_edit&id=${escapeHtml(intervention.id)}" style="margin-right: 10px;">Modifier</a>`;
                    actionsHtml += `<a href="actions/interventions_delete.php?id=${escapeHtml(intervention.id)}" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette intervention ?');" style="color: red; margin-right: 10px;">Supprimer</a>`;
                } else {
                    actionsHtml += '<span style="color: #6c757d; font-style: italic; margin-right: 10px;">Intervention clôturée</span>';
                }
                
                actionsHtml += `<a href="pwa/?intervention_id=${escapeHtml(intervention.id)}" target="_blank" style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none; font-size: 12px;">📱</a>`;
                
                const infoText = intervention.info || '';
                const truncatedInfo = infoText.length > 100 ? infoText.substring(0, 100) + '...' : infoText;
                
                html += `
                    <tr>
                        <td style="color: var(--text-color, #333);">${escapeHtml(intervention.id || '')}</td>
                        <td style="color: var(--text-color, #333);">${escapeHtml(intervention.client_nom || 'Client inconnu')}</td>
                        <td style="color: var(--text-color, #333);">${escapeHtml(intervention.date || '')}</td>
                        <td>${statutHtml}</td>
                        <td style="color: var(--text-color, #333);">
                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${escapeHtml(truncatedInfo)}
                            </div>
                        </td>
                        <td>${actionsHtml}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
        }
        
        interventionsContainer.innerHTML = html;
    }
    
    // Fonction pour échapper le HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Fonction pour effacer la recherche
    window.clearSearch = function() {
        searchInput.value = '';
        performSearch('');
    };
    
    // Événement de saisie avec délai
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(searchTerm);
        }, 300); // Délai de 300ms
    });
    
    // Bouton effacer
    clearButton.addEventListener('click', clearSearch);
    
    // Effacer avec Escape
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            clearSearch();
        }
    });
    
    // Placeholder dynamique
    const placeholders = [
        'Rechercher par nom de client...',
        'Ex: Dupont, Martin, Durand...',
        'Tapez le nom du client...'
    ];
    let placeholderIndex = 0;
    
    setInterval(function() {
        if (searchInput.value === '' && document.activeElement !== searchInput) {
            searchInput.placeholder = placeholders[placeholderIndex];
            placeholderIndex = (placeholderIndex + 1) % placeholders.length;
        }
    }, 3000);
    
    // Gestionnaire pour le changement du nombre d'éléments par page
    itemsPerPageSelect.addEventListener('change', function() {
        const newItemsPerPage = this.value;
        const currentUrl = new URL(window.location);
        
        // Mettre à jour les paramètres URL
        currentUrl.searchParams.set('per_page', newItemsPerPage);
        currentUrl.searchParams.set('page_num', '1'); // Retourner à la première page
        
        // Conserver le terme de recherche s'il existe
        if (currentSearchTerm) {
            currentUrl.searchParams.set('search', currentSearchTerm);
        }
        
        // Conserver l'état de la case à cocher
        if (currentHideClosed) {
            currentUrl.searchParams.set('hide_closed', '1');
        }
        
        // Rediriger vers la nouvelle URL
        window.location.href = currentUrl.toString();
    });
    
    // Gestionnaire pour la case à cocher "Masquer clôturées"
    hideClosedCheckbox.addEventListener('change', function() {
        const currentUrl = new URL(window.location);
        
        // Mettre à jour les paramètres URL
        currentUrl.searchParams.set('page_num', '1'); // Retourner à la première page
        
        // Conserver le terme de recherche s'il existe
        if (currentSearchTerm) {
            currentUrl.searchParams.set('search', currentSearchTerm);
        }
        
        // Conserver le nombre d'éléments par page
        currentUrl.searchParams.set('per_page', currentItemsPerPage);
        
        // Ajouter ou supprimer le paramètre hide_closed
        if (this.checked) {
            currentUrl.searchParams.set('hide_closed', '1');
        } else {
            currentUrl.searchParams.delete('hide_closed');
        }
        
        // Rediriger vers la nouvelle URL
        window.location.href = currentUrl.toString();
    });

    // Fonction pour imprimer une intervention
    window.printIntervention = function(id) {
        window.open('print_intervention.php?id=' + id, '_blank', 'width=1000,height=800');
    };
});
</script>

<!-- Modal d'ajout d'intervention -->
<div id="addInterventionModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Ajouter une intervention</h2>
            <span class="modal-close" onclick="closeAddInterventionModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="interventionAlerts"></div>
            <form id="addInterventionForm">
                <div class="form-group">
                    <label for="interv_client_search">Client *</label>
                    <div style="display: flex; gap: 8px; align-items: flex-start;">
                        <div class="client-search-container" style="flex: 1;">
                            <input type="text"
                                   id="interv_client_search"
                                   class="form-control"
                                   placeholder="Tapez pour rechercher un client..."
                                   required
                                   autocomplete="off">
                            <input type="hidden" id="interv_id_client" name="id_client">
                            <div id="interv_client_suggestions" class="client-suggestions"></div>
                        </div>
                        <button type="button" class="btn" onclick="openNestedClientModal()" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; border: none; padding: 0 20px; white-space: nowrap; height: 45px; display: flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'" title="Créer un nouveau client">
                            <span>➕</span> Nouveau client
                        </button>
                    </div>
                    <small class="form-hint" style="margin-top: 4px;">
                        Tapez au moins 2 caractères pour rechercher.
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="interv_date">Date et heure *</label>
                    <input type="datetime-local" 
                           id="interv_date" 
                           name="date" 
                           class="form-control"
                           required>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" 
                           id="interv_en_cours" 
                           name="en_cours" 
                           value="1" 
                           checked>
                    <label for="interv_en_cours">Intervention en cours</label>
                </div>
                
                <div class="form-group">
                    <label for="interv_info">Informations *</label>
                    <textarea id="interv_info" 
                              name="info" 
                              rows="6" 
                              class="form-control"
                              required 
                              placeholder="Décrivez l'intervention à effectuer..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddInterventionModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitAddInterventionForm()">
                <span>✓</span>
                Ajouter l'intervention
            </button>
        </div>
    </div>
</div>

<!-- Modal d'ajout de client (nested) - Version complète avec awesomplete -->
<div id="nestedClientModal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
            <h2>➕ Nouveau client</h2>
            <span class="modal-close" onclick="closeNestedClientModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="nestedClientAlerts"></div>
            <form id="nestedClientForm">
                <!-- Nom et Prénom sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_nom">Nom *</label>
                        <input type="text" id="nested_nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nested_prenom">Prénom</label>
                        <input type="text" id="nested_prenom" name="prenom" class="form-control">
                    </div>
                </div>
                
                <!-- Email sur toute la largeur -->
                <div class="form-group">
                    <label for="nested_mail">Email</label>
                    <input type="email" id="nested_mail" name="mail" class="form-control">
                </div>
                
                <!-- Adresse 1 sur toute la largeur avec awesomplete -->
                <div class="form-group">
                    <label for="nested_adresse1">Adresse 1</label>
                    <input type="text" id="nested_adresse1" name="adresse1" class="form-control" data-minchars="3" data-autofirst>
                </div>
                
                <!-- Adresse 2 sur toute la largeur -->
                <div class="form-group">
                    <label for="nested_adresse2">Adresse 2 (complément)</label>
                    <input type="text" id="nested_adresse2" name="adresse2" class="form-control">
                </div>
                
                <!-- Code Postal et Ville sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_cp">Code Postal</label>
                        <input type="text" id="nested_cp" name="cp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="nested_ville">Ville</label>
                        <input type="text" id="nested_ville" name="ville" class="form-control">
                    </div>
                </div>
                
                <!-- Téléphone et Portable sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_telephone">Téléphone</label>
                        <input type="tel" id="nested_telephone" name="telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="nested_portable">Portable</label>
                        <input type="tel" id="nested_portable" name="portable" class="form-control">
                    </div>
                </div>
            </form>
            
            <!-- Section de vérification des doublons -->
            <div id="nestedDuplicateCheckSection" style="display: none; margin-top: 15px; padding: 15px; background: var(--hover-bg); border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0;">⚠️ Doublons potentiels :</h4>
                <div id="nestedDuplicatesContainer" style="max-height: 150px; overflow-y: auto;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNestedClientModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitNestedClientForm()" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                <span>✓</span>
                Créer le client
            </button>
        </div>
    </div>
</div>


<style>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--card-bg);
    margin: 3% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    animation: slideInModal 0.3s ease;
    border: 1px solid var(--border-color);
}

@keyframes slideInModal {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.2em;
    font-weight: 500;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.modal-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 25px;
    max-height: 65vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.modal-footer .btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1em;
}

.modal-footer .btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
}

.modal-footer .btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.modal-footer .btn-secondary:hover {
    background: var(--hover-bg);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95em;
    color: var(--text-color);
}

.form-group .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-group .form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

.form-group textarea.form-control {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-hint {
    display: block;
    margin-top: 6px;
    font-size: 0.85em;
    color: var(--text-muted);
    line-height: 1.4;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.form-row .form-group {
    margin-bottom: 0;
}

.client-search-container {
    position: relative;
}

.client-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.client-suggestion-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.2s;
    color: var(--text-color);
}

.client-suggestion-item:hover {
    background: var(--hover-bg);
}

.client-suggestion-item:last-child {
    border-bottom: none;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #3498db;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
    user-select: none;
    font-weight: 500;
}

.alert-modal {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.alert-modal.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-modal.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .modal-content {
        margin: 5% auto;
        width: 95%;
    }
    .modal-body {
        padding: 20px;
    }
}

/* Fix Awesomplete z-index for nested modal */
.awesomplete {
    z-index: 1500;
    position: relative;
}
.awesomplete > ul {
    z-index: 1500 !important;
    background: white;
    border: 1px solid #ccc;
}
</style>

<script src="js/awesomplete.min.js"></script>
<script>
// ===== GESTION DE LA MODAL D'INTERVENTION =====
function openAddInterventionModal() {
    const modal = document.getElementById('addInterventionModal');
    const form = document.getElementById('addInterventionForm');
    const alerts = document.getElementById('interventionAlerts');
    
    modal.style.display = 'block';
    form.reset();
    alerts.innerHTML = '';
    
    // Set default date to now
    const now = new Date();
    const yyyy = now.getFullYear();
    const MM = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const HH = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('interv_date').value = `${yyyy}-${MM}-${dd}T${HH}:${mm}`;
    
    // Focus on client search
    setTimeout(() => {
        document.getElementById('interv_client_search').focus();
    }, 100);
}

function closeAddInterventionModal() {
    document.getElementById('addInterventionModal').style.display = 'none';
}

// ===== GESTION DE LA MODAL CLIENT NESTED =====
// ===== GESTION DE LA MODAL CLIENT NESTED =====
function openNestedClientModal() {
    document.getElementById('nestedClientModal').style.display = 'block';
    document.getElementById('nestedClientForm').reset();
    document.getElementById('nestedClientAlerts').innerHTML = '';
    
    // Vider la section des doublons
    const dupSection = document.getElementById('nestedDuplicateCheckSection');
    const dupContainer = document.getElementById('nestedDuplicatesContainer');
    if (dupSection) dupSection.style.display = 'none';
    if (dupContainer) dupContainer.innerHTML = '';
    
    // Initialiser Awesomplete si nécessaire
    if (typeof initNestedAddressAutocomplete === 'function') {
        initNestedAddressAutocomplete();
    }
}

function closeNestedClientModal() {
    document.getElementById('nestedClientModal').style.display = 'none';
    
    // Vider à la fermeture aussi pour être propre
    const dupSection = document.getElementById('nestedDuplicateCheckSection');
    const dupContainer = document.getElementById('nestedDuplicatesContainer');
    if (dupSection) dupSection.style.display = 'none';
    if (dupContainer) dupContainer.innerHTML = '';
}

// ===== AUTOCOMPLÉTION CLIENT =====
document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('interv_client_search');
    const clientId = document.getElementById('interv_id_client');
    const suggestions = document.getElementById('interv_client_suggestions');
    let searchTimeout;
    let selectedIndex = -1;
    
    if (clientSearch) {
        clientSearch.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (query === '') {
                clientId.value = '';
            }
            
            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch(`api/search_clients.php?q=${encodeURIComponent(query)}&limit=10`)
                    .then(response => response.json())
                    .then(data => {
                        suggestions.innerHTML = '';
                        selectedIndex = -1;
                        
                        if (data.length === 0) {
                            suggestions.innerHTML = '<div style="padding: 8px; color: var(--text-muted); font-style: italic;">Aucun client trouvé</div>';
                        } else {
                            data.forEach((client, index) => {
                                const div = document.createElement('div');
                                div.className = 'client-suggestion-item';
                                div.innerHTML = client.label;
                                div.dataset.id = client.id;
                                div.dataset.value = client.value;
                                div.dataset.index = index;
                                
                                div.addEventListener('click', function() {
                                    selectClient(this.dataset.id, this.dataset.value);
                                });
                                
                                suggestions.appendChild(div);
                            });
                        }
                        
                        suggestions.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Erreur recherche clients:', error);
                    });
            }, 300);
        });
        
        // Keyboard navigation
        clientSearch.addEventListener('keydown', function(e) {
            const items = suggestions.querySelectorAll('.client-suggestion-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    const item = items[selectedIndex];
                    selectClient(item.dataset.id, item.dataset.value);
                }
            } else if (e.key === 'Escape') {
                suggestions.style.display = 'none';
            }
        });
    }
    
    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.style.background = 'var(--hover-bg)';
            } else {
                item.style.background = '';
            }
        });
    }
    
    function selectClient(id, name) {
        clientId.value = id;
        clientSearch.value = name;
        suggestions.style.display = 'none';
        selectedIndex = -1;
    }
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (clientSearch && !clientSearch.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
});

// ===== SOUMISSION INTERVENTION =====
function submitAddInterventionForm() {
    const form = document.getElementById('addInterventionForm');
    const alertsDiv = document.getElementById('interventionAlerts');
    
    // Validation
    const clientId = document.getElementById('interv_id_client').value;
    const date = document.getElementById('interv_date').value;
    const info = document.getElementById('interv_info').value.trim();
    
    let errors = [];
    
    if (!clientId) {
        errors.push('Veuillez sélectionner un client.');
    }
    if (!date) {
        errors.push('La date est obligatoire.');
    }
    if (!info) {
        errors.push('Les informations sont obligatoires.');
    }
    
    if (errors.length > 0) {
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>${errors.join('<br>')}</div>
        </div>`;
        return;
    }
    
    // AJAX submission
    const formData = new FormData(form);
    
    fetch('actions/intervention_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert-modal success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            
            // Anciennement : window.location.reload();
            // Demande utilisateur : rediriger vers la fiche pour pouvoir l'imprimer
            // Anciennement : window.location.reload();
            // Demande utilisateur : rediriger vers la fiche pour pouvoir l'imprimer
            window.location.href = `index.php?page=interventions_view&id=${data.intervention_id}&auto_print=1`;
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert-modal error">
                <span>⚠️</span>
                <div>${errorMsg}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}

// ===== SOUMISSION CLIENT NESTED =====
function submitNestedClientForm() {
    const form = document.getElementById('nestedClientForm');
    const alertsDiv = document.getElementById('nestedClientAlerts');
    
    // Validation
    const nom = document.getElementById('nested_nom').value.trim();
    const telephone = document.getElementById('nested_telephone').value.trim();
    const portable = document.getElementById('nested_portable').value.trim();
    const mail = document.getElementById('nested_mail').value.trim();
    
    let errors = [];
    
    if (nom === '') {
        errors.push('Le nom est obligatoire.');
    }
    if (telephone === '' && portable === '') {
        errors.push('Au moins un numéro de téléphone est obligatoire.');
    }
    if (mail !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) {
        errors.push("L'adresse email n'est pas valide.");
    }
    
    if (errors.length > 0) {
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>${errors.join('<br>')}</div>
        </div>`;
        return;
    }
    
    const formData = new FormData(form);
    
    fetch('actions/client_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert-modal success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            
            // Pre-select the new client in intervention modal
            setTimeout(() => {
                const clientName = data.client.nom + (data.client.prenom ? ' ' + data.client.prenom : '');
                document.getElementById('interv_id_client').value = data.client_id;
                document.getElementById('interv_client_search').value = clientName;
                closeNestedClientModal();
            }, 800);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert-modal error">
                <span>⚠️</span>
                <div>${errorMsg}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}

// ===== FERMETURE AVEC ESCAPE =====
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const nestedModal = document.getElementById('nestedClientModal');
        const intervModal = document.getElementById('addInterventionModal');
        
        // Close nested modal first if open
        if (nestedModal && nestedModal.style.display === 'block') {
            closeNestedClientModal();
        } else if (intervModal && intervModal.style.display === 'block') {
            closeAddInterventionModal();
        }
    }
});

// ===== AWESOMPLETE POUR ADRESSE NESTED =====
// Initialisation directe car le script est chargé
if (window.Awesomplete) {
    initNestedAddressAutocomplete();
} else {
    // Fallback simple si jamais chargement lent (DOMContentLoaded le gèrera aussi)
    document.addEventListener('DOMContentLoaded', function() {
        if (window.Awesomplete) initNestedAddressAutocomplete();
    });
}

function initNestedAddressAutocomplete() {
    const adresseInput = document.getElementById('nested_adresse1');
    // Vérifier si déjà initialisé pour éviter doublons (si appelé plusieurs fois)
    if (adresseInput && window.Awesomplete && !adresseInput.classList.contains('awesomplete-processed')) {
        adresseInput.classList.add('awesomplete-processed');
        
        const awesomplete = new Awesomplete(adresseInput, {
            minChars: 3,
            maxItems: 10,
            autoFirst: true
        });
        
        // Stocker les données complètes des adresses pour les récupérer à la sélection
        let addressesData = {};
        
        adresseInput.addEventListener('input', function() {
            const query = this.value;
            if (query.length >= 3) {
                fetch(`api/get_addresses.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Adresses reçues:', data);
                        // Transformer le format GeoJSON en liste simple pour Awesomplete
                        if (data && data.features) {
                            const addresses = [];
                            addressesData = {}; // Réinitialiser le stockage
                            
                            data.features.forEach(feature => {
                                const label = feature.properties.label;
                                addresses.push(label);
                                addressesData[label] = feature.properties;
                            });
                            
                            awesomplete.list = addresses;
                        } else if (Array.isArray(data)) {
                            awesomplete.list = data;
                        }
                    })
                    .catch(error => console.error('Erreur autocomplétion:', error));
            }
        });
        
        adresseInput.addEventListener('awesomplete-selectcomplete', function(event) {
            const selectedLabel = event.text.value;
            const data = addressesData[selectedLabel];
            
            if (data) {
                // Remplissage précis avec les données de l'API
                if (data.postcode) document.getElementById('nested_cp').value = data.postcode;
                if (data.city) document.getElementById('nested_ville').value = data.city;
            } else {
                // Fallback ancienne méthode si données non trouvées
                const parts = selectedLabel.split(', ');
                if (parts.length >= 3) {
                    document.getElementById('nested_cp').value = parts[parts.length - 2] || '';
                    document.getElementById('nested_ville').value = parts[parts.length - 1] || '';
                }
            }
        });
    }
}

// ===== FORMATAGE TÉLÉPHONE NESTED =====
function formatNestedPhone(inputElement) {
    if (!inputElement) return;
    let value = inputElement.value.replace(/\D/g, '');
    let formattedValue = '';
    for (let i = 0; i < value.length && i < 10; i++) {
        if (i > 0 && i % 2 === 0) {
            formattedValue += ' ';
        }
        formattedValue += value[i];
    }
    inputElement.value = formattedValue;
}

document.addEventListener('DOMContentLoaded', function() {
    const nestedTel = document.getElementById('nested_telephone');
    const nestedPortable = document.getElementById('nested_portable');
    const nestedNom = document.getElementById('nested_nom');
    const nestedPrenom = document.getElementById('nested_prenom');
    
    if (nestedTel) {
        nestedTel.addEventListener('input', function() { formatNestedPhone(this); });
    }
    if (nestedPortable) {
        nestedPortable.addEventListener('input', function() { formatNestedPhone(this); });
    }
    
    // ===== VÉRIFICATION DOUBLONS NESTED =====
    let checkTimeout;
    function checkNestedDuplicates() {
        const nom = nestedNom ? nestedNom.value.trim() : '';
        const prenom = nestedPrenom ? nestedPrenom.value.trim() : '';
        const telephone = nestedTel ? nestedTel.value.trim().replace(/\s/g, '') : '';
        const portable = nestedPortable ? nestedPortable.value.trim().replace(/\s/g, '') : '';
        
        if (!nom && !prenom && !telephone && !portable) {
            document.getElementById('nestedDuplicateCheckSection').style.display = 'none';
            return;
        }
        
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            fetch('utils/check_duplicate_client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nom, prenom, telephone, portable })
            })
            .then(response => response.json())
            .then(data => {
                const section = document.getElementById('nestedDuplicateCheckSection');
                const container = document.getElementById('nestedDuplicatesContainer');
                
                if (data.duplicates && data.duplicates.length > 0) {
                    let html = '';
                    data.duplicates.forEach(dup => {
                        html += `<div style="padding: 8px; border-bottom: 1px solid var(--border-color); cursor: pointer;" onclick="window.open('index.php?page=clients_view&id=${dup.ID}', '_blank')">
                            <strong>${dup.nom} ${dup.prenom || ''}</strong><br>
                            ${dup.telephone ? 'Tel: ' + dup.telephone : ''} ${dup.portable ? 'Port: ' + dup.portable : ''}
                        </div>`;
                    });
                    container.innerHTML = html;
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            })
            .catch(error => console.error('Erreur vérification doublons:', error));
        }, 500);
    }
    
    if (nestedNom) nestedNom.addEventListener('input', checkNestedDuplicates);
    if (nestedPrenom) nestedPrenom.addEventListener('input', checkNestedDuplicates);
    if (nestedTel) nestedTel.addEventListener('input', checkNestedDuplicates);
    if (nestedPortable) nestedPortable.addEventListener('input', checkNestedDuplicates);
});
</script>