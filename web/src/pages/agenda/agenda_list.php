<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = '';
$messageType = '';

// Traitement des actions (marquer comme terminé, supprimer, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'terminer':
                    $stmt = $pdo->prepare("UPDATE agenda SET statut = 'termine', date_modification = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Tâche marquée comme terminée avec succès.";
                    $messageType = 'success';
                    break;

                case 'commencer':
                    $stmt = $pdo->prepare("UPDATE agenda SET statut = 'en_cours', date_modification = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Tâche marquée comme en cours.";
                    $messageType = 'success';
                    break;
                    
                case 'reporter':
                    if (isset($_POST['nouvelle_date'])) {
                        $stmt = $pdo->prepare("UPDATE agenda SET date_planifiee = ?, statut = 'reporte', date_modification = NOW() WHERE id = ?");
                        $stmt->execute([$_POST['nouvelle_date'], $id]);
                        $message = "Tâche reportée avec succès.";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'supprimer':
                    $stmt = $pdo->prepare("DELETE FROM agenda WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Tâche supprimée avec succès.";
                    $messageType = 'success';
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération : " . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Récupération des tâches avec filtres
$filter = $_GET['filter'] ?? 'tous';
$search = $_GET['search'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];

if ($filter !== 'tous') {
    $whereClause .= " AND a.statut = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $whereClause .= " AND (a.titre LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_telephone, c.portable as client_portable 
        FROM agenda a
        LEFT JOIN clients c ON a.id_client = c.ID
        $whereClause 
        ORDER BY 
            CASE 
                WHEN a.statut = 'termine' THEN 2
                WHEN a.statut = 'annule' THEN 3
                ELSE 1
            END,
            a.date_planifiee ASC
    ");
    $stmt->execute($params);
    $agendaItems = $stmt->fetchAll();
    
    // Statistiques
    $statsStmt = $pdo->query("
        SELECT 
            statut,
            COUNT(*) as count
        FROM agenda 
        GROUP BY statut
    ");
    $stats = [];
    while ($row = $statsStmt->fetch()) {
        $stats[$row['statut']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    $messageType = 'error';
    $agendaItems = [];
    $stats = [];
}

// Fonction pour obtenir la classe CSS selon la priorité
function getPriorityClass($priority) {
    switch ($priority) {
        case 'urgente': return 'priority-urgent';
        case 'haute': return 'priority-high';
        case 'normale': return 'priority-normal';
        case 'basse': return 'priority-low';
        default: return 'priority-normal';
    }
}

// Fonction pour obtenir la classe CSS selon le statut
function getStatusClass($status) {
    switch ($status) {
        case 'planifie': return 'status-planned';
        case 'en_cours': return 'status-progress';
        case 'termine': return 'status-completed';
        case 'reporte': return 'status-postponed';
        case 'annule': return 'status-cancelled';
        default: return 'status-planned';
    }
}

// Fonction pour formater le statut en français
function getStatusLabel($status) {
    switch ($status) {
        case 'planifie': return 'Planifié';
        case 'en_cours': return 'En cours';
        case 'termine': return 'Terminé';
        case 'reporte': return 'Reporté';
        case 'annule': return 'Annulé';
        default: return ucfirst($status);
    }
}

// Fonction pour formater la priorité en français
function getPriorityLabel($priority) {
    switch ($priority) {
        case 'basse': return 'Basse';
        case 'normale': return 'Normale';
        case 'haute': return 'Haute';
        case 'urgente': return 'Urgente';
        default: return ucfirst($priority);
    }
}
?>


<link rel="stylesheet" href="css/awesomplete.css">
<script src="js/awesomplete.min.js"></script>

<!-- Inline CSS Removed for Audit -->


<div class="agenda-page">
    <div class="page-header">
        <h1>
            <span>📅</span>
            Agenda & Planification
        </h1>
        <button onclick="openAddAgendaModal()" class="btn btn-success flex items-center gap-10">
            <span>➕</span>
            Nouvel événement
        </button>
    </div>
    
    <div class="agenda-container">
        <!-- Header removed, button moved up -->

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

        <!-- Statistiques -->
        <div class="flex gap-15 mb-25 overflow-x-auto pb-5">
            <div class="card bg-secondary border min-w-150 flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                <div class="text-3xl font-bold text-primary leading-tight"><?= array_sum($stats) ?></div>
                <div class="text-xs text-muted uppercase tracking-wide">Total</div>
            </div>
            <div class="card bg-secondary border min-w-150 flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                <div class="text-3xl font-bold text-info leading-tight"><?= $stats['planifie'] ?? 0 ?></div>
                <div class="text-xs text-muted uppercase tracking-wide">📋 Planifiés</div>
            </div>
            <div class="card bg-secondary border min-w-150 flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                <div class="text-3xl font-bold text-warning leading-tight"><?= $stats['en_cours'] ?? 0 ?></div>
                <div class="text-xs text-muted uppercase tracking-wide">▶️ En cours</div>
            </div>
            <div class="card bg-secondary border min-w-150 flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                <div class="text-3xl font-bold text-success leading-tight"><?= $stats['termine'] ?? 0 ?></div>
                <div class="text-xs text-muted uppercase tracking-wide">✅ Terminés</div>
            </div>
            <div class="card bg-secondary border min-w-150 flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                <div class="text-3xl font-bold text-muted leading-tight"><?= $stats['reporte'] ?? 0 ?></div>
                <div class="text-xs text-muted uppercase tracking-wide">⏰ Reportés</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card bg-secondary border p-20 rounded-12 shadow-sm mb-25">
            <div class="flex flex-wrap gap-20 items-end">
                <div class="flex-1 min-w-200">
                    <label class="block mb-8 font-bold text-muted">Filtrer par statut</label>
                    <select class="form-control w-full p-10 border rounded-8 bg-input text-dark" onchange="window.location.href='index.php?page=agenda_list&filter=' + this.value + '&search=<?= urlencode($search) ?>'">
                        <option value="tous" <?= $filter === 'tous' ? 'selected' : '' ?>>Tous</option>
                        <option value="planifie" <?= $filter === 'planifie' ? 'selected' : '' ?>>Planifiés</option>
                        <option value="en_cours" <?= $filter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $filter === 'termine' ? 'selected' : '' ?>>Terminés</option>
                        <option value="reporte" <?= $filter === 'reporte' ? 'selected' : '' ?>>Reportés</option>
                    </select>
                </div>
                
                <div class="flex-2 min-w-300">
                    <form method="GET" class="flex gap-10 items-end w-full m-0">
                        <input type="hidden" name="page" value="agenda_list">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <div class="w-full">
                            <label class="block mb-8 font-bold text-muted">Rechercher</label>
                            <div class="flex gap-10">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Titre, description..." class="form-control flex-1 p-10 border rounded-8 bg-input text-dark">
                                <button type="submit" class="btn btn-primary h-full px-20">🔍</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Liste des tâches -->
        <!-- Liste des tâches (Table View for density) -->
        <div class="card bg-secondary border rounded-12 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-input border-b border-border text-xs uppercase text-muted tracking-wide">
                            <th class="p-15 font-bold text-center w-100">Date</th>
                            <th class="p-15 font-bold">Événement</th>
                            <th class="p-15 font-bold w-150">Client</th>
                            <th class="p-15 font-bold text-center w-100">État / Priorité</th>
                            <th class="p-15 font-bold text-right w-140">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if (empty($agendaItems)): ?>
                            <tr>
                                <td colspan="5" class="p-40 text-center text-muted">
                                    <div class="text-4xl opacity-50 mb-10">📭</div>
                                    <h3 class="text-dark">Aucun événement trouvé</h3>
                                    <p class="mb-20">Commencez par créer votre premier événement planifié !</p>
                                    <button onclick="openAddAgendaModal()" class="btn btn-success">
                                        <span>➕</span> Créer un événement
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($agendaItems as $item): ?>
                                <?php 
                                    $isDone = $item['statut'] === 'termine';
                                    $isLate = !$isDone && strtotime($item['date_planifiee']) < time();
                                    
                                    // Couleur date : Rouge si retard, Vert si futur/aujourd'hui, Gris si terminé
                                    $dateClass = $isDone ? 'bg-input text-muted' : ($isLate ? 'bg-danger-light text-danger-dark' : 'bg-success-light text-success-dark');
                                    
                                    $statusBadgeClass = match($item['statut']) {
                                         'termine' => 'bg-success-light text-success-dark',
                                         'annule' => 'bg-danger-light text-danger-dark',
                                         'reporte' => 'bg-warning-light text-warning-dark',
                                         'en_cours' => 'bg-info-light text-info-dark',
                                         default => 'bg-primary-light text-primary-dark'
                                    };
                                    
                                    $priorityLabel = match($item['priorite']) {
                                        'urgente' => '🔥 Urgente',
                                        'haute' => '⚡ Haute',
                                        'basse' => '💤 Basse',
                                        default => 'Normale'
                                    };
                                    
                                    $rowClass = $item['statut'] === 'termine' ? 'opacity-75 bg-input' : 'hover:bg-hover transition-colors';
                                ?>
                                <tr class="border-b border-border last:border-0 <?= $rowClass ?>">
                                    <td class="p-15 text-center align-middle font-bold <?= $dateClass ?>">
                                        <div class="text-base"><?= date('d/m', strtotime($item['date_planifiee'])) ?></div>
                                        <div class="text-xs opacity-80"><?= date('H:i', strtotime($item['date_planifiee'])) ?></div>
                                    </td>
                                    
                                    <td class="p-15 align-middle">
                                        <!-- Clickable Header Area -->
                                        <div class="cursor-pointer group" onclick="toggleEventAccordion(<?= $item['id'] ?>)">
                                            <div class="flex items-center gap-10 mb-5">
                                                <div class="font-bold text-dark text-base truncate flex-1 leading-normal group-hover:text-primary transition-colors">
                                                    <?= htmlspecialchars($item['titre']) ?>
                                                </div>
                                                <span id="chevron-<?= $item['id'] ?>" class="text-muted text-xs transition-transform duration-200">
                                                    ▼
                                                </span>
                                            </div>

                                            <!-- Preview (Visible default) -->
                                            <div id="preview-<?= $item['id'] ?>" class="text-muted text-sm line-clamp-2 leading-relaxed group-hover:text-dark transition-colors">
                                                <?= nl2br(htmlspecialchars($item['description'])) ?>
                                            </div>
                                        </div>

                                        <!-- Accordion Content (Hidden default) - Not clickable for toggle -->
                                        <div id="details-<?= $item['id'] ?>" class="text-muted text-sm whitespace-pre-wrap leading-relaxed border-l-2 border-border pl-10 ml-5 mt-10" style="display: none;">
                                            <?= nl2br(htmlspecialchars($item['description'])) ?>
                                        </div>
                                        
                                        <!-- Formulaire de report (caché) -->
                                        <div id="postpone-form-<?= $item['id'] ?>" class="hidden mt-10 p-10 bg-input rounded border border-border">
                                            <form method="POST" class="flex gap-5 items-center">
                                                <input type="hidden" name="action" value="reporter">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="datetime-local" name="nouvelle_date" required 
                                                       value="<?= date('Y-m-d\TH:i', strtotime($item['date_planifiee'] . ' + 1 day')) ?>"
                                                       class="form-control p-5 text-xs w-auto">
                                                <button type="submit" class="btn btn-xs btn-primary">OK</button>
                                            </form>
                                        </div>
                                    </td>
                                    
                                    <td class="p-15 whitespace-nowrap align-middle">
                                        <?php if (!empty($item['client_nom'])): ?>
                                            <a href="index.php?page=clients_view&id=<?= $item['id_client'] ?>" class="text-primary font-medium hover:underline flex items-center gap-5">
                                                <span>👤</span> <?= htmlspecialchars($item['client_nom'] . ' ' . $item['client_prenom']) ?>
                                            </a>
                                            <?php if (!empty($item['client_telephone'])): ?>
                                                <div class="text-xs text-muted mt-2">📞 <?= htmlspecialchars($item['client_telephone']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="p-15 text-center align-middle">
                                        <div class="flex flex-col gap-5 items-center">
                                            <span class="px-10 py-3 rounded-full text-xs font-bold w-full max-w-100 <?= $statusBadgeClass ?>">
                                                <?= getStatusLabel($item['statut']) ?>
                                            </span>
                                            <span class="text-xs font-medium text-muted">
                                                <?= $priorityLabel ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="p-15 text-right whitespace-nowrap align-middle">
                                        <div class="flex justify-end gap-5">
                                            <?php if ($item['statut'] !== 'termine' && $item['statut'] !== 'annule'): ?>
                                                
                                                <?php if ($item['statut'] !== 'en_cours'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="commencer">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-icon btn-info text-white" title="Commencer">
                                                        ▶️
                                                    </button>
                                                </form>
                                                <?php endif; ?>

                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="terminer">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-icon btn-success" title="Terminer">
                                                        ✅
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="btn btn-xs btn-icon btn-warning text-white" 
                                                        onclick="togglePostpone(<?= $item['id'] ?>)" title="Reporter">
                                                    ⏰
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="index.php?page=agenda_edit&id=<?= $item['id'] ?>" class="btn btn-xs btn-icon btn-outline-primary" title="Modifier">
                                                ✏️
                                            </a>
                                            
                                            <button type="button" class="btn btn-xs btn-icon btn-outline-danger border-0" title="Supprimer"
                                                    onclick="submitAction('supprimer', <?= $item['id'] ?>, 'Êtes-vous sûr de vouloir supprimer cet événement ?')">
                                                ❌
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<!-- ========================================== -->
<!-- MODAL AJOUT ÉVÉNEMENT (POPUP) -->
<!-- ========================================== -->
<?php include 'includes/modals/add_agenda.php'; ?>

<!-- Formulaire caché pour les actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="id" id="actionId">
    <input type="hidden" name="action" id="actionType">
</form>

<!-- Custom Modal -->
<div id="confirmationModal" class="modal-overlay fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content text-center max-w-400 mx-auto">
        <span id="modalIcon" class="modal-icon">⚠️</span>
        <h3 id="modalTitle" class="modal-title">Confirmation</h3>
        <p id="modalMessage" class="modal-message">Êtes-vous sûr ?</p>
        <div class="modal-actions">
            <button onclick="closeModal()" class="btn-modal btn-modal-cancel">Annuler</button>
            <button onclick="confirmAction()" id="modalConfirmBtn" class="btn-modal btn-modal-confirm">Confirmer</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL AJOUT CLIENT (NESTED) -->
<!-- ========================================== -->
<?php include 'includes/modals/add_client.php'; ?>

<script>
    function toggleEventAccordion(id) {
        const details = document.getElementById('details-' + id);
        const preview = document.getElementById('preview-' + id);
        const chevron = document.getElementById('chevron-' + id);
        
        // Simple toggle based on current display style
        // If details is hidden, show it.
        if (details.style.display === 'none') {
            details.style.display = 'block';
            preview.style.display = 'none';
            chevron.style.transform = 'rotate(180deg)';
        } else {
            details.style.display = 'none';
            preview.style.display = ''; // Clear inline style to let CSS (.line-clamp-2) take over
            chevron.style.transform = 'rotate(0deg)';
        }
    }
</script>
<script>
let pendingAction = null;

function submitAction(action, id, message) {
    pendingAction = { action, id };
    
    const modal = document.getElementById('confirmationModal');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    
    modalMessage.textContent = message;
    
    // Personnaliser selon l'action
    if (action === 'supprimer') {
        modalIcon.textContent = '🗑️';
        confirmBtn.className = 'btn-modal btn-modal-confirm'; // Rouge
        confirmBtn.textContent = 'Supprimer';
    } else if (action === 'terminer') {
        modalIcon.textContent = '✅';
        confirmBtn.className = 'btn-modal btn-modal-confirm success'; // Vert
        confirmBtn.textContent = 'Terminer';
    }
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmationModal').style.display = 'none';
    pendingAction = null;
}

function confirmAction() {
    if (pendingAction) {
        document.getElementById('actionId').value = pendingAction.id;
        document.getElementById('actionType').value = pendingAction.action;
        document.getElementById('actionForm').submit();
    }
}

// Function to toggle postpone form display (renamed to match onclick call)
function togglePostpone(id) {
    const form = document.getElementById('postpone-form-' + id);
    if (form) {
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
}


// ===== GESTION RECHERCHE CLIENT =====
let clientSearchInitialized = false;

function initClientSearch() {
    if (clientSearchInitialized) return;
    
    const clientSearch = document.getElementById('agenda_client_search');
    const clientId = document.getElementById('agenda_id_client');
    const suggestions = document.getElementById('agenda_client_suggestions');
    let searchTimeout;
    
    if (clientSearch) {
        // Input listener
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            clearTimeout(searchTimeout);
            
            // Si le champ est vidé, on efface l'ID
            if (term.length === 0) {
                 clientId.value = '';
                 suggestions.style.display = 'none';
                 return;
            }
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'client-suggestion-item';
                            div.textContent = client.label;
                            div.onclick = function() {
                                clientSearch.value = client.value;
                                clientId.value = client.id;
                                suggestions.style.display = 'none';
                            };
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                })
                .catch(err => console.error("Search error:", err));
            }, 300);
        });
        
        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (e.target !== clientSearch && e.target !== suggestions) {
                suggestions.style.display = 'none';
            }
        });
        
        clientSearchInitialized = true;
    }
}

// ===== GESTION MODAL CLIENT NESTED =====
// ===== GESTION MODAL CLIENT NESTED (Via Shared add_client.php) =====
// La logique a été déplacée dans 'includes/modals/add_client.php'

// ===== GESTION MODAL AGENDA =====
function openAddAgendaModal() {
    initClientSearch();
    const modal = document.getElementById('addAgendaModal');
    const form = document.getElementById('addAgendaForm');
    const alerts = document.getElementById('agendaAlerts');
    
    modal.style.display = 'flex';
    form.reset();
    document.getElementById('agenda_id_client').value = ''; // Reset hidden field logic
    alerts.innerHTML = '';
    
    // Default date: Now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('agenda_date').value = now.toISOString().slice(0, 16);
    
    setTimeout(() => { document.getElementById('agenda_client_search').focus(); }, 100);
}

function closeAddAgendaModal() {
    document.getElementById('addAgendaModal').style.display = 'none';
}

function setAgendaColor(color) {
    document.getElementById('agenda_couleur').value = color;
}

function submitAddAgendaForm() {
    const form = document.getElementById('addAgendaForm');
    const alertsDiv = document.getElementById('agendaAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    if (!formData.get('titre')) {
        alertsDiv.innerHTML = '<div class="alert alert-error">Le titre est obligatoire.</div>';
        return;
    }
    
    fetch('actions/agenda_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success">Événement créé avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 1000);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-error">${errorMsg}</div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-error">Erreur de communication avec le serveur.</div>';
    });
}

// Global click handler for closing modals - DISABLED as per user request
window.onclick = function(event) {
    /*
    const confirmModal = document.getElementById('confirmationModal');
    const agendaModal = document.getElementById('addAgendaModal');
    
    if (event.target == confirmModal) {
        closeModal();
    }
    if (event.target == agendaModal) {
        closeAddAgendaModal();
    }
    */
}
</script>