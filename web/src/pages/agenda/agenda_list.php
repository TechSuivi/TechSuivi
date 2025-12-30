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

<style>
/* Modern Orange Theme for Agenda */
.agenda-page {
    background: var(--bg-color);
    color: var(--text-color);
}

.page-header {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
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

.agenda-container {
    max-width: 1400px;
    margin: 0 auto;
}

.agenda-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.agenda-stats {
    display: flex;
    gap: 12px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.stat-badge {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    color: white;
    padding: 10px 18px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
    transition: all 0.3s ease;
}

.stat-badge:hover {
    transform: translateY(-2px);
}

.stat-badge.completed { 
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%); 
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
}

.stat-badge.completed:hover {
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
}

.stat-badge.progress { 
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); 
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.stat-badge.progress:hover {
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
}

.stat-badge.postponed { 
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); 
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.stat-badge.postponed:hover {
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
}

.stat-badge.cancelled { 
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.stat-badge.cancelled:hover {
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
}

.controls-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.agenda-filters {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-group label {
    font-weight: 500;
    font-size: 0.95em;
}

.agenda-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .agenda-grid {
        grid-template-columns: 1fr;
    }
}

.agenda-item {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.agenda-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.agenda-item.completed {
    border-left: 5px solid #27ae60;
    opacity: 0.85;
}

.agenda-item.completed .agenda-title {
    text-decoration: line-through;
}

.agenda-item.completed .agenda-description {
    opacity: 0.8;
}

.priority-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    border-radius: 12px 12px 0 0;
}

.priority-urgent { background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%); }
.priority-high { background: linear-gradient(90deg, #f39c12 0%, #e67e22 100%); }
.priority-normal { background: linear-gradient(90deg, #3498db 0%, #2980b9 100%); }
.priority-low { background: linear-gradient(90deg, #95a5a6 0%, #7f8c8d 100%); }

.agenda-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    margin-top: 5px;
}

.agenda-title {
    font-size: 1.2em;
    font-weight: 600;
    color: var(--text-color);
    margin: 0 0 10px 0;
}

.agenda-date {
    font-size: 0.9em;
    color: var(--text-muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.agenda-description {
    color: var(--text-muted);
    line-height: 1.6;
    margin: 15px 0;
    font-size: 0.95em;
}

.agenda-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.status-badge, .priority-badge {
    padding: 5px 14px;
    border-radius: 15px;
    font-size: 0.75em;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-planned { background: #3498db; color: white; }
.status-progress { background: #f39c12; color: white; }
.status-completed { background: #27ae60; color: white; }
.status-postponed { background: #e67e22; color: white; }
.status-cancelled { background: #e74c3c; color: white; }

.agenda-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    align-items: center; /* Vertical align center */
}

.agenda-actions form {
    display: contents; /* Make form transparent to flex layout */
}

.btn-action {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
    white-space: nowrap;
    background: var(--input-bg);
    color: var(--text-color);
    border-color: var(--border-color);
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    height: 36px; /* Fixed height for alignment */
    box-sizing: border-box;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-action span {
    font-size: 1.1em;
}

/* Primary Action */
.btn-complete { 
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%); 
    color: white; 
    border: none;
}
.btn-complete:hover { 
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); 
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
}

/* Secondary Actions (Ghost/Outline style) */
.btn-edit { 
    color: #e67e22;
    border-color: #e67e22;
    background: transparent;
}
.btn-edit:hover { 
    background: #e67e22; 
    color: white;
}

.btn-postpone { 
    color: #3498db;
    border-color: #3498db;
    background: transparent;
}
.btn-postpone:hover { 
    background: #3498db; 
    color: white;
}

.btn-delete { 
    color: #e74c3c;
    border-color: #e74c3c;
    background: transparent;
}
.btn-delete:hover { 
    background: #e74c3c; 
    color: white;
}

.no-items {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-muted);
    font-style: italic;
    grid-column: 1 / -1;
}

.no-items h3 {
    color: var(--text-color);
    margin-bottom: 15px;
}

.search-box {
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.9em;
    min-width: 250px;
    background: var(--input-bg);
    color: var(--text-color);
    transition: all 0.3s ease;
}

.search-box:focus {
    outline: none;
    border-color: #e67e22;
    box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1);
}

.filter-select {
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.9em;
    background: var(--input-bg);
    color: var(--text-color);
    transition: all 0.3s ease;
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: #e67e22;
    box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1);
}

.btn-add {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    color: white;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(230, 126, 34, 0.4);
    text-decoration: none;
    color: white;
}

.postpone-form {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: var(--input-bg);
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

.postpone-form label {
    font-weight: 500;
    margin-right: 10px;
}

.postpone-form input[type="datetime-local"] {
    padding: 8px 12px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    margin-right: 10px;
    background: var(--card-bg);
    color: var(--text-color);
}

.postpone-form input[type="datetime-local"]:focus {
    outline: none;
    border-color: #e67e22;
    box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
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
.btn-icon-delete {
    background: transparent;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    padding: 0 5px;
    transition: all 0.2s;
    opacity: 0.4;
}
.btn-icon-delete:hover { opacity: 1; transform: scale(1.1); }

/* Missing Form Styles for Modal */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.95em; }

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1em;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: var(--input-bg);
    color: var(--text-color);
}
.form-control:focus {
    outline: none;
    border-color: #e67e22;
    box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(230, 126, 34, 0.4);
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}
.btn-secondary:hover { background: var(--hover-bg); }

.priority-color:hover {
    transform: scale(1.15);
    border-color: #e67e22;
    box-shadow: 0 0 0 2px rgba(230, 126, 34, 0.2);
}

/* Modal Styles */
.custom-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}

.modal-content {
    background-color: var(--card-bg);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    /* Removed restrictive width and alignment */
    width: 90%;
    border: 1px solid var(--border-color);
    animation: slideUp 0.3s ease;
}

.modal-icon {
    font-size: 3em;
    margin-bottom: 15px;
    display: block;
}

.modal-title {
    font-size: 1.2em;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--text-color);
}

.modal-message {
    color: var(--text-muted);
    margin-bottom: 25px;
    line-height: 1.5;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-modal {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-cancel {
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.btn-modal-cancel:hover {
    background: var(--hover-bg);
}

.btn-modal-confirm {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.btn-modal-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(231, 76, 60, 0.4);
}

.btn-modal-confirm.success {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Client Search Styles */
.client-search-container { position: relative; }
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
    z-index: 2000;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.client-suggestion-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}
.client-suggestion-item:hover { background: var(--hover-bg); }

.awesomplete { z-index: 1500; position: relative; }
.awesomplete > ul { z-index: 1500 !important; background: white; border: 1px solid #ccc; }

.modal-content { overflow: visible !important; } /* Allow suggestions to flow out */


</style>


<div class="agenda-page">
    <div class="page-header">
        <h1>
            <span>📅</span>
            Agenda & Planification
        </h1>
    </div>
    
    <div class="agenda-container">
        <div class="agenda-header">
            <div></div>
            <button onclick="openAddAgendaModal()" class="btn-add">
                <span>➕</span>
                Nouvel événement
            </button>
        </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

        <!-- Statistiques -->
        <div class="agenda-stats">
            <div class="stat-badge">
                📊 Total: <?= array_sum($stats) ?>
            </div>
            <div class="stat-badge">
                📋 Planifiés: <?= $stats['planifie'] ?? 0 ?>
            </div>
            <div class="stat-badge progress">
                ▶️ En cours: <?= $stats['en_cours'] ?? 0 ?>
            </div>
            <div class="stat-badge completed">
                ✅ Terminés: <?= $stats['termine'] ?? 0 ?>
            </div>
            <div class="stat-badge postponed">
                ⏰ Reportés: <?= $stats['reporte'] ?? 0 ?>
            </div>
        </div>

        <!-- Filtres -->
        <div class="controls-card">
            <div class="agenda-filters">
        <div class="filter-group">
            <label>Filtrer par statut:</label>
            <select class="filter-select" onchange="window.location.href='index.php?page=agenda_list&filter=' + this.value + '&search=<?= urlencode($search) ?>'">
                <option value="tous" <?= $filter === 'tous' ? 'selected' : '' ?>>Tous</option>
                <option value="planifie" <?= $filter === 'planifie' ? 'selected' : '' ?>>Planifiés</option>
                <option value="en_cours" <?= $filter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="termine" <?= $filter === 'termine' ? 'selected' : '' ?>>Terminés</option>
                <option value="reporte" <?= $filter === 'reporte' ? 'selected' : '' ?>>Reportés</option>
            </select>
        </div>
        
        <div class="filter-group">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="agenda_list">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <label>Rechercher:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Titre ou description..." class="search-box">
                <button type="submit" class="btn-action btn-edit">🔍</button>
            </form>
        </div>
    </div>

        <!-- Liste des tâches -->
        <div class="agenda-grid">
            <?php if (empty($agendaItems)): ?>
                <div class="no-items">
                    <h3>📭 Aucun événement trouvé</h3>
                    <p>Commencez par créer votre premier événement planifié !</p>
                    <button onclick="openAddAgendaModal()" class="btn-add" style="margin-top: 15px;">
                        <span>➕</span>
                        Créer un événement
                    </button>
                </div>
        <?php else: ?>
            <?php foreach ($agendaItems as $item): ?>
                <div class="agenda-item <?= $item['statut'] === 'termine' ? 'completed' : '' ?>">
                    <div class="priority-bar <?= getPriorityClass($item['priorite']) ?>"></div>
                    
                    <div class="agenda-header-content">
                        <div>
                            <h3 class="agenda-title"><?= htmlspecialchars($item['titre']) ?></h3>
                            <div class="agenda-date">
                                📅 <?= date('d/m/Y à H:i', strtotime($item['date_planifiee'])) ?>
                                <?php if (!empty($item['client_nom'])): ?>
                                    <span style="margin-left: 10px; color: var(--text-color); font-weight: 500;">
                                        👤 <a href="index.php?page=clients_view&id=<?= $item['id_client'] ?>" style="color: inherit; text-decoration: none; border-bottom: 1px dotted var(--text-muted); transition: color 0.2s; hover: color: var(--accent-color);"><?= htmlspecialchars($item['client_nom'] . ' ' . $item['client_prenom']) ?></a>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($item['client_telephone']) || !empty($item['client_portable'])): ?>
                                    <span style="margin-left: 10px; color: var(--text-muted); font-size: 0.9em;">
                                        <?php if (!empty($item['client_telephone'])): ?>
                                            📞 <?= htmlspecialchars($item['client_telephone']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item['client_portable'])): ?>
                                            📱 <?= htmlspecialchars($item['client_portable']) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-icon-delete" title="Supprimer"
                                onclick="submitAction('supprimer', <?= $item['id'] ?>, 'Êtes-vous sûr de vouloir supprimer cet événement ?')">
                            &times;
                        </button>
                    </div>

                    <p class="agenda-description">
                        <?= nl2br(htmlspecialchars($item['description'])) ?>
                    </p>
                    
                    <div class="agenda-meta">
                        <span class="status-badge <?= getStatusClass($item['statut']) ?>">
                            <?= getStatusLabel($item['statut']) ?>
                        </span>
                        
                        <span class="priority-badge <?= getPriorityClass($item['priorite']) ?>">
                            <?= getPriorityLabel($item['priorite']) ?>
                        </span>
                    </div>

                    <div class="agenda-actions">
                        <?php if ($item['statut'] !== 'termine' && $item['statut'] !== 'annule'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="terminer">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-action btn-complete">
                                    <span>✅</span> Terminer
                                </button>
                            </form>
                            
                            <button type="button" class="btn-action btn-postpone" 
                                    onclick="togglePostpone(<?= $item['id'] ?>)">
                                <span>⏰</span> Reporter
                            </button>
                        <?php endif; ?>
                        
                        <a href="index.php?page=agenda_edit&id=<?= $item['id'] ?>" class="btn-action btn-edit">
                            <span>✏️</span> Modifier
                        </a>
                    </div>
                    
                    <!-- Formulaire de report (caché par défaut) -->
                    <div id="postpone-form-<?= $item['id'] ?>" class="postpone-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="reporter">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <label>Nouvelle date :</label>
                                <input type="datetime-local" name="nouvelle_date" required 
                                       value="<?= date('Y-m-d\TH:i', strtotime($item['date_planifiee'] . ' + 1 day')) ?>">
                            </div>
                            <button type="submit" class="btn-action btn-postpone" style="width: 100%;">
                                Confirmer le report
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
<div id="confirmationModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="text-align: center; max-width: 400px; margin: 0 auto;">
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


<style>
/* Styles Modal (Copied from dashboard.php for consistency) */
.modal-overlay {
    position: fixed;
    z-index: 2000; /* Dashboard uses 1000, but ensuring top */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    animation: fadeIn 0.2s ease;
    display: none;
    align-items: center; /* Helper for centering */
    justify-content: center; /* Helper for centering */
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
    background: var(--card-bg);
    margin: auto; /* Dashboard uses 3% auto, but flex centering needs auto */
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 650px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    animation: slideInModal 0.3s ease;
    border: 1px solid var(--border-color);
    position: relative;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
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
    flex-shrink: 0;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    opacity: 0.8;
}

.modal-close:hover { opacity: 1; }

.modal-body {
    padding: 25px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-shrink: 0;
    background: var(--card-bg); /* Ensure background match */
    border-radius: 0 0 12px 12px;
}
</style>

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

// Global click handler for closing modals
window.onclick = function(event) {
    const confirmModal = document.getElementById('confirmationModal');
    const agendaModal = document.getElementById('addAgendaModal');
    
    if (event.target == confirmModal) {
        closeModal();
    }
    if (event.target == agendaModal) {
        closeAddAgendaModal();
    }
}
</script>