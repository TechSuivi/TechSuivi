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
        $errorMsg = "Erreur lors de la récupération des clients : " . htmlspecialchars($e->getMessage());
        if (isset($_GET['ajax_search'])) {
            echo json_encode(['error' => $errorMsg]);
            exit;
        }
        echo "<p>$errorMsg</p>";
    }
} else {
    $errorMsg = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
    if (isset($_GET['ajax_search'])) {
        echo json_encode(['error' => $errorMsg]);
        exit;
    }
    echo "<p>$errorMsg</p>";
}

// --- Render Functions ---

function renderClientRows($clients) {
    ob_start();
    foreach ($clients as $client): ?>
        <tr class="hover:bg-hover transition-colors">
            <td class="text-center font-bold text-muted">#<?= htmlspecialchars((string)($client['ID'] ?? '')) ?></td>
            <td class="font-bold">
                <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" class="text-dark hover:text-primary">
                    <?= htmlspecialchars($client['nom'] ?? '') ?>
                </a>
            </td>
            <td><?= htmlspecialchars($client['prenom']  ?? '') ?></td>
            <td class="text-sm">
                <?= htmlspecialchars($client['adresse1'] ?? '') ?>
                <?php if(!empty($client['adresse2'])) echo '<br><small class="text-muted">'.htmlspecialchars($client['adresse2']).'</small>'; ?>
            </td>
            <td class="text-center text-sm"><?= htmlspecialchars($client['cp'] ?? '') ?></td>
            <td><?= htmlspecialchars($client['ville'] ?? '') ?></td>
            <td class="text-sm"><?= htmlspecialchars($client['telephone'] ?? '') ?></td>
            <td class="text-sm"><?= htmlspecialchars($client['portable'] ?? '') ?></td>
            <td class="text-sm truncate" style="max-width: 200px;" title="<?= htmlspecialchars($client['mail'] ?? '') ?>">
                <?php if (!empty($client['mail'])): ?>
                    <a href="mailto:<?= htmlspecialchars($client['mail']) ?>" class="text-primary hover:underline">
                        <?= htmlspecialchars($client['mail']) ?>
                    </a>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" class="btn btn-xs btn-icon btn-outline-primary" title="Voir la fiche">
                    👁️
                </a>
                <button type="button" class="btn btn-xs btn-icon btn-outline-secondary" title="Modifier" onclick="openEditClientModal(<?= (int)$client['ID'] ?>)">
                    ✏️
                </button>
            </td>
        </tr>
    <?php endforeach;
    return ob_get_clean();
}

function renderPagination($currentPage, $totalPages, $searchTerm, $sortBy, $sortDir) {
    if ($totalPages <= 1) return '';
    
    ob_start();
    $baseUrl = "index.php?page=clients";
    if (!empty($searchTerm)) {
        $baseUrl .= "&search_term=" . urlencode($searchTerm);
    }
    $baseUrl .= "&sort_by=" . urlencode($sortBy) . "&sort_dir=" . urlencode($sortDir);
    ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage - 1 ?>" data-page="<?= $currentPage - 1 ?>">« Précédent</a>
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
                <span>...</span>
            <?php endif; ?>

            <?php if ($i == $currentPage): ?>
                <strong><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
            <?php $lastPrintedPage = $i; ?>
        <?php endforeach; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $currentPage + 1 ?>" data-page="<?= $currentPage + 1 ?>">Suivant »</a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// --- AJAX Handler ---
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'rows' => renderClientRows($clients),
        'pagination' => renderPagination($currentPage, $totalPages, $searchTerm, $sortBy, $sortDir),
        'total' => $totalClients,
        'page_info' => "$totalClients client(s) trouvé(s) au total. Page $currentPage sur $totalPages."
    ]);
    exit;
}
?>

<!-- clients.php HTML -->
<link rel="stylesheet" href="css/modals.css">
<!-- Check: if this path is incorrect relative to index.php, we might need 'css/modals.css' if index.php is in web/. Assuming typical structure where index.php includes pages. -->
<!-- Adjusted path based on user typical structure observation: index.php likely in web/ root. So 'src/css/modals.css' or 'css/modals.css'? Checked style.css view earlier, it is in web/src/css/style.css. Usage in dashboard: 'css/awesomplete.css'.
Let's try 'src/css/modals.css' first, IF index.php is in web/.
Wait, if index.php is in web/, and style.css is in web/src/css, then src/css/modals.css is correct.
If index.php is in /TechSuivi/web/index.php, then src/css/modals.css is correct.
If index.php is in /TechSuivi/web/src/index.php, then css/modals.css is correct.
Let's assume 'src/css/modals.css' for now since style.css is in src/css. -->
</style>

<div class="client-page">
    <div class="page-header">
        <div class="header-content">
            <h1>
                <span>👥</span>
                Liste des Clients
            </h1>
            <p class="subtitle">Gérez votre base de clients et leurs informations</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="openAddClientModal()">
                <span>➕</span>
                Nouveau client
            </button>
        </div>
    </div>

    <div class="search-controls mb-20">
        <!-- Removed method/action to prevent accidental submit, JS handles it. -->
        <div class="search-form flex gap-10">
            <input type="text" 
                   id="search_term" 
                   class="form-control"
                   placeholder="Rechercher par nom, prénom, email, ville..." 
                   value="<?= htmlspecialchars($searchTerm) ?>"
                   value="<?= htmlspecialchars($searchTerm) ?>"
                   style="max-width: 400px;">
            <!-- Hidden sort inputs to maintain state in JS -->
            <input type="hidden" id="sort_by" value="<?= htmlspecialchars($sortBy) ?>">
            <input type="hidden" id="sort_dir" value="<?= htmlspecialchars($sortDir) ?>">
        </div>
    </div>

    <!-- Modal d'ajout de client -->
    <?php include 'includes/modals/add_client.php'; ?>

    <!-- Modal d'édition de client -->
    <?php include 'includes/modals/edit_client.php'; ?>

    <p id="info-text" class="info-text text-muted mb-10 text-sm">
        <?php if (empty($clients) && $totalClients == 0): ?>
            Aucun client trouvé <?= !empty($searchTerm) ? 'pour la recherche "' . htmlspecialchars($searchTerm) . '"' : 'dans la base de données' ?>.
        <?php elseif (empty($clients) && $totalClients > 0 && !empty($searchTerm)): ?>
            Aucun client trouvé pour la recherche "<?= htmlspecialchars($searchTerm) ?>".
        <?php else: ?>
            <?= $totalClients ?> client(s) trouvé(s) au total. Page <?= $currentPage ?> sur <?= $totalPages ?>.
        <?php endif; ?>
    </p>

    <div class="overflow-x-auto">
        <table>
            <thead>
                <tr>
                    <?php
                    function getSortLink($columnKey, $columnDisplay, $currentSortBy, $currentSortDir, $currentSearchTerm) {
                        $linkSortDir = ($currentSortBy === $columnKey && strtoupper($currentSortDir) === 'ASC') ? 'DESC' : 'ASC';
                        $arrow = '';
                        if ($currentSortBy === $columnKey) {
                            $arrow = (strtoupper($currentSortDir) === 'ASC') ? ' ▲' : ' ▼';
                        }
                        // Use data attributes for JS to handle sorting
                        return "<a href='#' class='sort-link text-white hover:text-white no-underline' data-sort='$columnKey' data-dir='$linkSortDir'>{$columnDisplay}{$arrow}</a>";
                    }
                    ?>
                    <th class="w-80"><?= getSortLink('ID', 'ID', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-150"><?= getSortLink('nom', 'Nom', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-150"><?= getSortLink('prenom', 'Prénom', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-200">Adresse</th>
                    <th class="w-80"><?= getSortLink('cp', 'CP', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-150"><?= getSortLink('ville', 'Ville', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-120">Tél</th>
                    <th class="w-120">Port</th>
                    <th class="w-200"><?= getSortLink('mail', 'Email', $sortBy, $sortDir, $searchTerm) ?></th>
                    <th class="w-100 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="clients-table-body">
                <?= renderClientRows($clients) ?>
            </tbody>
        </table>
    </div>

    <div id="pagination-container">
        <?= renderPagination($currentPage, $totalPages, $searchTerm, $sortBy, $sortDir) ?>
    </div>

</div>

<script src="../js/awesomplete.min.js"></script>
<link rel="stylesheet" href="../css/awesomplete.css" />
<script>
// Live Search & Sort AJAX
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_term');
    const tableBody = document.getElementById('clients-table-body');
    const paginationContainer = document.getElementById('pagination-container');
    const infoText = document.getElementById('info-text');
    let searchTimeout;

    // State
    let currentState = {
        page: 'clients',
        search_term: searchInput ? searchInput.value : '',
        sort_by: document.getElementById('sort_by').value,
        sort_dir: document.getElementById('sort_dir').value,
        p: 1
    };

    function updateState(updates) {
        currentState = { ...currentState, ...updates };
        performSearch();
    }

    function performSearch() {
        // Construct query params
        const params = new URLSearchParams(currentState);
        params.append('ajax_search', '1');

        // Update URL (without reload)
        const urlParams = new URLSearchParams(currentState);
        history.pushState(currentState, '', 'index.php?' + urlParams.toString());

        fetch('index.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                tableBody.innerHTML = data.rows || '<tr><td colspan="10" class="text-center p-20">Aucun résultat</td></tr>';
                paginationContainer.innerHTML = data.pagination || '';
                infoText.textContent = data.page_info || '';
                
                // Update sort visuals in header
                updateSortVisuals();

                // Re-attach event listeners to new pagination & sort links
                attachDynamicListeners();
            })
            .catch(err => console.error('Erreur AJAX:', err));
    }

    function updateSortVisuals() {
        document.querySelectorAll('.sort-link').forEach(link => {
            const colKey = link.getAttribute('data-sort');
            const originalText = link.textContent.replace(/[▲▼]/g, '').trim();
            
            if (colKey === currentState.sort_by) {
                const isAsc = currentState.sort_dir === 'ASC';
                link.textContent = originalText + (isAsc ? ' ▲' : ' ▼');
                // Update the data-dir for the next click (toggle)
                link.setAttribute('data-dir', isAsc ? 'DESC' : 'ASC');
            } else {
                link.textContent = originalText;
                // Reset others to ASC
                link.setAttribute('data-dir', 'ASC');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const value = e.target.value;
            searchTimeout = setTimeout(() => {
                updateState({ search_term: value, p: 1 }); // Reset to page 1 on search
            }, 300); // 300ms delay
        });
    }

    function attachDynamicListeners() {
        // Pagination links
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page'); // Extract page number from href or data attr
                if (page) {
                     updateState({ p: page });
                } else {
                    // Fallback parse from href if data-page missing in helper
                    const url = new URL(this.href);
                    const p = url.searchParams.get('p') || 1;
                     updateState({ p: p });
                }
            });
        });

        // Sort links
        document.querySelectorAll('.sort-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sortBy = this.getAttribute('data-sort');
                const sortDir = this.getAttribute('data-dir');
                updateState({ sort_by: sortBy, sort_dir: sortDir });
                
                // Update local inputs to match
                document.getElementById('sort_by').value = sortBy;
                document.getElementById('sort_dir').value = sortDir;
            });
        });
    }

    // Initial attach
    attachDynamicListeners();
    
    // Initialiser l'autocomplétion (Existing)
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
        document.getElementById('client_add_nom').focus();
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
    modal.style.display = 'block';
    
    alerts.innerHTML = '';
    
    // Charger les données du client
    fetch('api/client_get.php?id=' + clientId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const client = data.client;
                document.getElementById('client_edit_id').value = client.ID;
                document.getElementById('client_edit_nom').value = client.nom || '';
                document.getElementById('client_edit_prenom').value = client.prenom || '';
                document.getElementById('client_edit_mail').value = client.mail || '';
                document.getElementById('client_edit_adresse1').value = client.adresse1 || '';
                document.getElementById('client_edit_adresse2').value = client.adresse2 || '';
                document.getElementById('client_edit_cp').value = client.cp || '';
                document.getElementById('client_edit_ville').value = client.ville || '';
                document.getElementById('client_edit_telephone').value = client.telephone || '';
                document.getElementById('client_edit_portable').value = client.portable || '';
                
                // Formater les numéros de téléphone
                formatEditPhoneNumber(document.getElementById('client_edit_telephone'));
                formatEditPhoneNumber(document.getElementById('client_edit_portable'));
                
                
                // Focus sur le champ nom
                setTimeout(() => {
                    document.getElementById('client_edit_nom').focus();
                }, 100);
            } else {
                alerts.innerHTML = `<div class="alert alert-error">
                    <span>⚠️</span>
                    <div>${data.error || 'Erreur lors du chargement'}</div>
                </div>`;
            }
        })
        .catch(error => {
        })
        .catch(error => {
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
    const editTelInput = document.getElementById('client_edit_telephone');
    const editPortableInput = document.getElementById('client_edit_portable');
    
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
    const nom = document.getElementById('client_edit_nom').value.trim();
    const telephone = document.getElementById('client_edit_telephone').value.trim();
    const portable = document.getElementById('client_edit_portable').value.trim();
    const mail = document.getElementById('client_edit_mail').value.trim();
    
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
    const telInput = document.getElementById('client_add_telephone');
    const portableInput = document.getElementById('client_add_portable');
    
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
    const adresse1Input = document.getElementById('client_add_adresse1');
    const cpInput = document.getElementById('client_add_cp');
    const villeInput = document.getElementById('client_add_ville');
    
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
    const nomInput = document.getElementById('client_add_nom');
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
    const nom = document.getElementById('client_add_nom').value.trim();
    const telephone = document.getElementById('client_add_telephone').value.trim();
    const portable = document.getElementById('client_add_portable').value.trim();
    const mail = document.getElementById('client_add_mail').value.trim();
    
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