<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Fetch clients from the database
$clients = [];
$searchTerm = trim($_GET['search_term'] ?? '');
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$clientsPerPage = 10;

// Sorting parameters
$sortableColumns = ['ID', 'nom', 'prenom', 'cp', 'ville', 'mail'];
$sortBy = $_GET['sort_by'] ?? 'nom';
$sortDir = $_GET['sort_dir'] ?? 'ASC';

// Validate sortBy and sortDir
if (!in_array($sortBy, $sortableColumns)) {
    $sortBy = 'nom';
}
if (strtoupper($sortDir) !== 'ASC' && strtoupper($sortDir) !== 'DESC') {
    $sortDir = 'ASC';
}

$totalClients = 0;
$totalPages = 1;
$offset = 0;

if (isset($pdo)) {
    try {
        // Count total clients
        $countSql = "SELECT COUNT(*) FROM clients";
        $countParams = [];
        $searchTermLower = strtolower($searchTerm);

        if (!empty($searchTerm)) {
            $countSql .= " WHERE (LOWER(nom) LIKE :searchNom
                           OR LOWER(prenom) LIKE :searchPrenom
                           OR LOWER(mail) LIKE :searchMail
                           OR LOWER(ville) LIKE :searchVille)";
            $likePattern = '%' . $searchTermLower . '%';
            $countParams[':searchNom'] = $likePattern;
            $countParams[':searchPrenom'] = $likePattern;
            $countParams[':searchMail'] = $likePattern;
            $countParams[':searchVille'] = $likePattern;
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalClients = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalClients / $clientsPerPage);
        
        if ($totalPages == 0) {
            $totalPages = 1;
        }

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $offset = ($currentPage - 1) * $clientsPerPage;

        // Fetch clients
        $sql = "SELECT ID, nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail FROM clients";
        $params = [];

        if (!empty($searchTerm)) {
            $sql .= " WHERE (LOWER(nom) LIKE :searchNomFetch
                      OR LOWER(prenom) LIKE :searchPrenomFetch
                      OR LOWER(mail) LIKE :searchMailFetch
                      OR LOWER(ville) LIKE :searchVilleFetch)";
            $likePatternFetch = '%' . $searchTermLower . '%';
            $params[':searchNomFetch'] = $likePatternFetch;
            $params[':searchPrenomFetch'] = $likePatternFetch;
            $params[':searchMailFetch'] = $likePatternFetch;
            $params[':searchVilleFetch'] = $likePatternFetch;
        }
        
        $sql .= " ORDER BY " . $sortBy . " " . $sortDir;
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = (int)$clientsPerPage;
        $params[':offset'] = (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); 
        $clients = $stmt->fetchAll();

    } catch (PDOException $e) {
        echo "<p>Erreur lors de la récupération des clients : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
}
?>

<style>
/* Modern Purple Theme for Clients */
.client-page {
    background: var(--bg-color);
    color: var(--text-color);
}

.page-header {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
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

.search-controls {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input {
    flex: 1;
    min-width: 250px;
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
}

.search-input:focus {
    outline: none;
    border-color: #8e44ad;
    box-shadow: 0 0 0 4px rgba(142, 68, 173, 0.1);
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1em;
}

.btn-primary {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(142, 68, 173, 0.3);
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
    text-decoration: none;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    background: var(--card-bg);
    margin-bottom: 25px;
}

table thead {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
}

table thead th {
   padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

table tbody tr:hover {
    background: var(--hover-bg);
    transform: scale(1.001);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

table tbody td {
    padding: 12px 15px;
    color: var(--text-color);
}

table tbody tr:last-child {
    border-bottom: none;
}

table th a {
    color: white;
    text-decoration: none;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 25px;
    padding: 20px;
}

.pagination a, .pagination strong, .pagination span {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    border: 1px solid var(--border-color);
    background: var(--input-bg);
    color: var(--text-color);
    transition: all 0.2s ease;
    font-size: 0.9em;
}

.pagination a:hover {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
    border-color: #8e44ad;
    transform: translateY(-1px);
}

.pagination strong {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
    border-color: #8e44ad;
}

.info-text {
    color: var(--text-muted);
    margin: 15px 0;
    font-size: 0.95em;
}

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
    max-width: 600px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    animation: slideIn 0.3s ease;
    border: 1px solid var(--border-color);
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
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
    padding: 20px;
    max-height: 65vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Form Styles for Modal */
.form-group {
    margin-bottom: 15px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-row .form-group {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .modal-content {
        margin: 5% auto;
        width: 95%;
    }
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 0.9em;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 0.95em;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #8e44ad;
    box-shadow: 0 0 0 4px rgba(142, 68, 173, 0.1);
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
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
</style>

<div class="client-page">
    <div class="page-header">
        <h1>
            <span>👥</span>
            Liste des Clients
        </h1>
    </div>

    <div class="search-controls">
        <form method="GET" action="index.php" class="search-form">
            <input type="hidden" name="page" value="clients">
            <input type="text" 
                   name="search_term" 
                   class="search-input"
                   placeholder="Rechercher par nom, prénom, email, ville..." 
                   value="<?= htmlspecialchars($searchTerm) ?>">
            <button type="submit" class="btn btn-primary">
                <span>🔍</span>
                Rechercher
            </button>
            <?php if (!empty($searchTerm)): ?>
                <?php
                $clearSearchUrl = "index.php?page=clients";
                if (isset($_GET['sort_by'])) $clearSearchUrl .= "&sort_by=" . urlencode($_GET['sort_by']);
                if (isset($_GET['sort_dir'])) $clearSearchUrl .= "&sort_dir=" . urlencode($_GET['sort_dir']);
                ?>
                <a href="<?= $clearSearchUrl ?>" class="btn btn-secondary">
                    <span>✕</span>
                    Effacer
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" onclick="openAddClientModal()">
                <span>➕</span>
                Nouveau client
            </button>
        </form>
    </div>

    <!-- Modal d'ajout de client -->
    <div id="addClientModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Ajouter un nouveau client</h2>
                <span class="modal-close" onclick="closeAddClientModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="addClientAlerts"></div>
                <form id="addClientForm">
                    <!-- Nom et Prénom sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_nom">Nom *</label>
                            <input type="text" id="modal_nom" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_prenom">Prénom</label>
                            <input type="text" id="modal_prenom" name="prenom" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Email sur toute la largeur -->
                    <div class="form-group">
                        <label for="modal_mail">Email</label>
                        <input type="email" id="modal_mail" name="mail" class="form-control">
                    </div>
                    
                    <!-- Adresse 1 sur toute la largeur -->
                    <div class="form-group">
                        <label for="modal_adresse1">Adresse 1</label>
                        <input type="text" id="modal_adresse1" name="adresse1" class="form-control" data-minchars="3" data-autofirst>
                    </div>
                    
                    <!-- Adresse 2 sur toute la largeur -->
                    <div class="form-group">
                        <label for="modal_adresse2">Adresse 2 (complément)</label>
                        <input type="text" id="modal_adresse2" name="adresse2" class="form-control">
                    </div>
                    
                    <!-- Code Postal et Ville sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_cp">Code Postal</label>
                            <input type="text" id="modal_cp" name="cp" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="modal_ville">Ville</label>
                            <input type="text" id="modal_ville" name="ville" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Téléphone et Portable sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_telephone">Téléphone</label>
                            <input type="tel" id="modal_telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="modal_portable">Portable</label>
                            <input type="tel" id="modal_portable" name="portable" class="form-control">
                        </div>
                    </div>
                </form>
                
                <!-- Section de vérification des doublons -->
                <div id="duplicateCheckSection" style="display: none; margin-top: 15px; padding: 15px; background: var(--hover-bg); border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">⚠️ Doublons potentiels :</h4>
                    <div id="duplicatesContainer" style="max-height: 150px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddClientModal()">
                    <span>✕</span>
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="submitAddClientForm()">
                    <span>✓</span>
                    Ajouter le client
                </button>
            </div>
        </div>
    </div>

    <!-- Modal d'édition de client -->
    <div id="editClientModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Modifier le client</h2>
                <span class="modal-close" onclick="closeEditClientModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="editClientAlerts"></div>
                <div id="editClientLoading" style="text-align: center; padding: 20px; display: none;">
                    <p>Chargement des données...</p>
                </div>
                <form id="editClientForm" style="display: none;">
                    <input type="hidden" id="edit_client_id" name="id">
                    
                    <!-- Nom et Prénom sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_nom">Nom *</label>
                            <input type="text" id="edit_nom" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_prenom">Prénom</label>
                            <input type="text" id="edit_prenom" name="prenom" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Email sur toute la largeur -->
                    <div class="form-group">
                        <label for="edit_mail">Email</label>
                        <input type="email" id="edit_mail" name="mail" class="form-control">
                    </div>
                    
                    <!-- Adresse 1 sur toute la largeur -->
                    <div class="form-group">
                        <label for="edit_adresse1">Adresse 1</label>
                        <input type="text" id="edit_adresse1" name="adresse1" class="form-control">
                    </div>
                    
                    <!-- Adresse 2 sur toute la largeur -->
                    <div class="form-group">
                        <label for="edit_adresse2">Adresse 2 (complément)</label>
                        <input type="text" id="edit_adresse2" name="adresse2" class="form-control">
                    </div>
                    
                    <!-- Code Postal et Ville sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_cp">Code Postal</label>
                            <input type="text" id="edit_cp" name="cp" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_ville">Ville</label>
                            <input type="text" id="edit_ville" name="ville" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Téléphone et Portable sur la même ligne -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_telephone">Téléphone</label>
                            <input type="tel" id="edit_telephone" name="telephone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_portable">Portable</label>
                            <input type="tel" id="edit_portable" name="portable" class="form-control">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditClientModal()">
                    <span>✕</span>
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="submitEditClientForm()">
                    <span>✓</span>
                    Enregistrer
                </button>
            </div>
        </div>
    </div>

    <?php if (empty($clients) && $totalClients == 0): ?>
        <p class="info-text">Aucun client trouvé <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : 'dans la base de données' ?>.</p>
    <?php elseif (empty($clients) && $totalClients > 0 && !empty($searchTerm)): ?>
        <p class="info-text">Aucun client trouvé pour la recherche "<?= htmlspecialchars($searchTerm) ?>" à cette page. Essayez une autre page ou effacez la recherche.</p>
    <?php else: ?>
        <p class="info-text"><?= $totalClients ?> client(s) trouvé(s) au total. Page <?= $currentPage ?> sur <?= $totalPages ?>.</p>
        <table>
            <thead>
                <tr>
                    <?php
                    function getSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm, $currentPageNum) {
                        $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                        $arrow = '';
                        if ($currentSortBy === $columnKey) {
                            $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                        }
                        $url = "index.php?page=clients&sort_by={$columnKey}&sort_dir={$linkSortDir}";
                        if (!empty($currentSearchTerm)) {
                            $url .= "&search_term=" . urlencode($currentSearchTerm);
                        }
                        return "<a href='{$url}'>{$columnDisplay}{$arrow}</a>";
                    }
                    ?>
                    <th><?= getSortLink('ID', 'ID', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th><?= getSortLink('nom', 'Nom', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th><?= getSortLink('prenom', 'Prénom', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th>Adresse 1</th>
                    <th>Adresse 2</th>
                    <th><?= getSortLink('cp', 'Code Postal', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th><?= getSortLink('ville', 'Ville', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th>Téléphone</th>
                    <th>Portable</th>
                    <th><?= getSortLink('mail', 'Email', $sortBy, $sortDir, $searchTerm, $currentPage) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($client['ID'] ?? '')) ?></td>
                        <td>
                            <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">
                                <?= htmlspecialchars($client['nom'] ?? '') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($client['prenom']  ?? '') ?></td>
                        <td><?= htmlspecialchars($client['adresse1'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['adresse2'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['cp'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['ville'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['telephone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['portable'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['mail'] ?? '') ?></td>
                        <td>
                            <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px; background: #3498db;">
                                Voir
                            </a>
                            <button type="button" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" onclick="openEditClientModal(<?= (int)$client['ID'] ?>)">
                                Modifier
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $baseUrl = "index.php?page=clients";
            if (!empty($searchTerm)) {
                $baseUrl .= "&search_term=" . urlencode($searchTerm);
            }
            $baseUrl .= "&sort_by=" . urlencode($sortBy) . "&sort_dir=" . urlencode($sortDir);

            if ($currentPage > 1): ?>
                <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>">« Précédent</a>
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
                if  ($lastPrintedPage > 0 && $i > $lastPrintedPage + 1): ?>
                    <span>...</span>
                <?php endif; ?>

                <?php if ($i == $currentPage): ?>
                    <strong><?= $i ?></strong>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>&p=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php $lastPrintedPage = $i; ?>
            <?php endforeach; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>">Suivant »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../js/awesomplete.min.js"></script>
<link rel="stylesheet" href="../css/awesomplete.css" />
<script>
// Recherche instantanée / Live Search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search_term"]');
    const searchForm = searchInput ? searchInput.closest('form') : null;
    let searchTimeout;

    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function() {
            // Annuler le timeout précédent
            clearTimeout(searchTimeout);
            
            // Attendre 500ms après que l'utilisateur ait fini de taper
            searchTimeout = setTimeout(function() {
                // Soumettre le formulaire automatiquement
                searchForm.submit();
            }, 500); // Délai de 500ms (ajustable)
        });
    }
    
    // Initialiser l'autocomplétion d'adresse pour la modal si Awesomplete est disponible
    initAddressAutocomplete();
    initPhoneFormatting();
    initDuplicateCheck();
});

// Variables globales pour la modal
let awesompleteInstance = null;
let addressFetchTimeout = null;

// ===== GESTION DE LA MODAL =====
function openAddClientModal() {
    document.getElementById('addClientModal').style.display = 'block';
    document.getElementById('addClientForm').reset();
    document.getElementById('addClientAlerts').innerHTML = '';
    document.getElementById('duplicateCheckSection').style.display = 'none';
    // Focus sur le champ nom
    setTimeout(() => {
        document.getElementById('modal_nom').focus();
    }, 100);
}

function closeAddClientModal() {
    document.getElementById('addClientModal').style.display = 'none';
}

// Fermer avec Escape
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddClientModal();
        closeEditClientModal();
    }
});

// ===== GESTION DE LA MODAL D'ÉDITION =====
function openEditClientModal(clientId) {
    const modal = document.getElementById('editClientModal');
    const loading = document.getElementById('editClientLoading');
    const form = document.getElementById('editClientForm');
    const alerts = document.getElementById('editClientAlerts');
    
    modal.style.display = 'block';
    loading.style.display = 'block';
    form.style.display = 'none';
    alerts.innerHTML = '';
    
    // Charger les données du client
    fetch('api/client_get.php?id=' + clientId)
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            if (data.success) {
                const client = data.client;
                document.getElementById('edit_client_id').value = client.ID;
                document.getElementById('edit_nom').value = client.nom || '';
                document.getElementById('edit_prenom').value = client.prenom || '';
                document.getElementById('edit_mail').value = client.mail || '';
                document.getElementById('edit_adresse1').value = client.adresse1 || '';
                document.getElementById('edit_adresse2').value = client.adresse2 || '';
                document.getElementById('edit_cp').value = client.cp || '';
                document.getElementById('edit_ville').value = client.ville || '';
                document.getElementById('edit_telephone').value = client.telephone || '';
                document.getElementById('edit_portable').value = client.portable || '';
                
                // Formater les numéros de téléphone
                formatEditPhoneNumber(document.getElementById('edit_telephone'));
                formatEditPhoneNumber(document.getElementById('edit_portable'));
                
                form.style.display = 'block';
                
                // Focus sur le champ nom
                setTimeout(() => {
                    document.getElementById('edit_nom').focus();
                }, 100);
            } else {
                alerts.innerHTML = `<div class="alert alert-error">
                    <span>⚠️</span>
                    <div>${data.error || 'Erreur lors du chargement'}</div>
                </div>`;
            }
        })
        .catch(error => {
            loading.style.display = 'none';
            console.error('Erreur:', error);
            alerts.innerHTML = `<div class="alert alert-error">
                <span>⚠️</span>
                <div>Erreur de communication avec le serveur.</div>
            </div>`;
        });
}

function closeEditClientModal() {
    document.getElementById('editClientModal').style.display = 'none';
}

function formatEditPhoneNumber(inputElement) {
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

// Ajouter les événements de formatage pour les champs de la modal d'édition
document.addEventListener('DOMContentLoaded', function() {
    const editTelInput = document.getElementById('edit_telephone');
    const editPortableInput = document.getElementById('edit_portable');
    
    if (editTelInput) {
        editTelInput.addEventListener('input', function() { formatEditPhoneNumber(this); });
    }
    if (editPortableInput) {
        editPortableInput.addEventListener('input', function() { formatEditPhoneNumber(this); });
    }
});

function submitEditClientForm() {
    const form = document.getElementById('editClientForm');
    const alertsDiv = document.getElementById('editClientAlerts');
    
    // Validation côté client
    const nom = document.getElementById('edit_nom').value.trim();
    const telephone = document.getElementById('edit_telephone').value.trim();
    const portable = document.getElementById('edit_portable').value.trim();
    const mail = document.getElementById('edit_mail').value.trim();
    
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
        alertsDiv.innerHTML = `<div class="alert alert-error">
            <span>⚠️</span>
            <div>${errors.join('<br>')}</div>
        </div>`;
        return;
    }
    
    // Préparer les données du formulaire
    const formData = new FormData(form);
    
    // Envoyer via AJAX
    fetch('actions/client_edit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert alert-success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            
            // Recharger la page après 1 seconde pour voir les modifications
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-error">
                <span>⚠️</span>
                <div>${errorMsg}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert alert-error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}

// ===== FORMATAGE DES NUMÉROS DE TÉLÉPHONE =====
function initPhoneFormatting() {
    const telInput = document.getElementById('modal_telephone');
    const portableInput = document.getElementById('modal_portable');
    
    function formatPhoneNumber(inputElement) {
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
    
    function validatePhoneNumber(inputElement) {
        const value = inputElement.value.replace(/\D/g, '');
        if (value.length > 0 && value.length !== 10) {
            inputElement.style.borderColor = '#e74c3c';
        } else {
            inputElement.style.borderColor = '';
        }
    }
    
    if (telInput) {
        telInput.addEventListener('input', function() { formatPhoneNumber(this); });
        telInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
    if (portableInput) {
        portableInput.addEventListener('input', function() { formatPhoneNumber(this); });
        portableInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
}

// ===== AUTOCOMPLÉTION D'ADRESSE =====
function initAddressAutocomplete() {
    const adresse1Input = document.getElementById('modal_adresse1');
    const cpInput = document.getElementById('modal_cp');
    const villeInput = document.getElementById('modal_ville');
    
    if (!window.Awesomplete || !adresse1Input) {
        console.warn("Awesomplete n'est pas chargé ou le champ adresse n'existe pas.");
        return;
    }
    
    awesompleteInstance = new Awesomplete(adresse1Input, {
        minChars: 3,
        autoFirst: true,
        list: [],
        data: function (item, input) { 
            return { label: item.label, value: item.properties };
        },
        item: function (suggestionData, input) {
            return Awesomplete.ITEM(suggestionData.label, input);
        },
        replace: function(suggestionData) {
            this.input.value = suggestionData.value.name || '';
        }
    });
    
    adresse1Input.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 3) {
            awesompleteInstance.list = [];
            return;
        }
        
        clearTimeout(addressFetchTimeout);
        addressFetchTimeout = setTimeout(() => {
            fetch('../api/get_addresses.php?q=' + encodeURIComponent(query))
                .then(response => response.ok ? response.json() : Promise.reject('Erreur réseau'))
                .then(data => {
                    if (data.features && Array.isArray(data.features)) {
                        const suggestions = data.features.map(feature => ({
                            label: feature.properties.label || '', 
                            properties: feature.properties
                        }));
                        awesompleteInstance.list = suggestions;
                    } else {
                        awesompleteInstance.list = [];
                    }
                })
                .catch(error => {
                    console.error('Erreur autocomplétion adresse:', error);
                    awesompleteInstance.list = [];
                });
        }, 300);
    });
    
    adresse1Input.addEventListener('awesomplete-selectcomplete', function(event) {
        const selectedProperties = event.text.value;
        if (selectedProperties && typeof selectedProperties === 'object') {
            if (cpInput) cpInput.value = selectedProperties.postcode || '';
            if (villeInput) villeInput.value = selectedProperties.city || '';
        }
    });
}

// ===== VÉRIFICATION DES DOUBLONS =====
function initDuplicateCheck() {
    const nomInput = document.getElementById('modal_nom');
    let checkTimeout;
    
    if (nomInput) {
        nomInput.addEventListener('blur', function() {
            const nom = this.value.trim();
            if (nom.length < 2) return;
            
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(() => {
                checkDuplicates(nom);
            }, 300);
        });
    }
}

function checkDuplicates(nom) {
    fetch(`../utils/check_duplicate_client.php?nom=${encodeURIComponent(nom)}`)
        .then(response => response.json())
        .then(data => {
            const section = document.getElementById('duplicateCheckSection');
            const container = document.getElementById('duplicatesContainer');
            
            if (data.duplicates && data.duplicates.length > 0) {
                let html = '<table style="width:100%; border-collapse: collapse; font-size: 0.9em;">';
                html += '<tr><th style="text-align:left; padding:5px; border-bottom:1px solid var(--border-color);">Nom</th>';
                html += '<th style="text-align:left; padding:5px; border-bottom:1px solid var(--border-color);">Prénom</th>';
                html += '<th style="text-align:left; padding:5px; border-bottom:1px solid var(--border-color);">Ville</th></tr>';
                
                data.duplicates.forEach(dup => {
                    html += `<tr>
                        <td style="padding:5px;">${dup.nom || ''}</td>
                        <td style="padding:5px;">${dup.prenom || ''}</td>
                        <td style="padding:5px;">${dup.ville || ''}</td>
                    </tr>`;
                });
                html += '</table>';
                
                container.innerHTML = html;
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Erreur vérification doublons:', error);
        });
}

// ===== SOUMISSION DU FORMULAIRE =====
function submitAddClientForm() {
    const form = document.getElementById('addClientForm');
    const alertsDiv = document.getElementById('addClientAlerts');
    
    // Validation côté client
    const nom = document.getElementById('modal_nom').value.trim();
    const telephone = document.getElementById('modal_telephone').value.trim();
    const portable = document.getElementById('modal_portable').value.trim();
    const mail = document.getElementById('modal_mail').value.trim();
    
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
        alertsDiv.innerHTML = `<div class="alert alert-error">
            <span>⚠️</span>
            <div>${errors.join('<br>')}</div>
        </div>`;
        return;
    }
    
    // Préparer les données du formulaire
    const formData = new FormData(form);
    
    // Envoyer via AJAX
    fetch('actions/client_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert alert-success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            
            // Recharger la page après 1 seconde pour voir le nouveau client
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-error">
                <span>⚠️</span>
                <div>${errorMsg}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert alert-error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}
</script>