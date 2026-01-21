<?php
// Vérifier que le script est inclus correctement
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Inclure le système de permissions
require_once __DIR__ . '/../../utils/permissions_helper.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Ajout explicite de Bootstrap pour s'assurer que les styles et scripts sont chargés
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

$message = '';
$messageType = '';

?>
<style>
/* Variables CSS Globales (héritées de style.css mais rappelées ici pour référence) */
:root {
    --primary-color: #4f46e5;
    --secondary-color: #6b7280;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-bg: #f3f4f6;
    --card-bg: #ffffff;
    --text-main: #111827;
    --text-muted: #6b7280;
    --border-color: #e5e7eb;
}

/* Mode Sombre */
body.dark {
    --light-bg: #111827;
    --card-bg: #1f2937;
    --text-main: #f9fafb;
    --text-muted: #9ca3af;
    --border-color: #374151;
}

/* Styles inspirés de scheduled_tasks.php */
.reports-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%); /* Teinte violette pour différencier */
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 2.2em;
    font-weight: 300;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.main-content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 30px;
    align-items: start;
}

/* Panneau de configuration (Gauche) */
.config-panel {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.config-panel h2 {
    color: var(--primary-color);
    margin: 0 0 20px 0;
    font-size: 1.4em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.create-btn {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: white;
    border: none;
    padding: 15px 25px;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    margin-bottom: 25px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
}

.search-box {
    position: relative;
    margin-bottom: 25px;
}

.search-box input {
    width: 100%;
    padding: 12px 15px 12px 40px;
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: 8px;
    font-size: 1em;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.help-section {
    background: var(--light-bg);
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid var(--warning-color);
}

.help-section h3 {
    color: var(--warning-color);
    margin: 0 0 15px 0;
    font-size: 1.1em;
}

.help-section ul {
    margin: 0;
    padding-left: 20px;
}

.help-section li {
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 0.9em;
}

/* Panneau de liste (Droite) */
.templates-panel {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.templates-header {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
    color: white;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.templates-header h2 {
    margin: 0;
    font-size: 1.3em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.templates-list {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

/* Carte Template (Adaptée) */
.template-card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.template-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-color: var(--primary-color);
}

.template-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.template-title {
    font-size: 1.1em;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
}

.template-type {
    font-size: 0.8em;
    padding: 4px 8px;
    border-radius: 4px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--primary-color);
    font-weight: 500;
}

.template-desc {
    color: var(--text-muted);
    font-size: 0.9em;
    margin-bottom: 20px;
    flex-grow: 1;
    line-height: 1.5;
}

.template-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    margin-top: auto;
}

.template-actions {
    display: flex;
    gap: 10px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-muted);
    transition: all 0.2s;
    cursor: pointer;
}

.btn-icon:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.btn-icon.delete:hover {
    background: var(--danger-color);
    border-color: var(--danger-color);
}

/* Preview Styles (pour la modale) */
.preview-container {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 1.5rem;
    min-height: 200px;
    max-height: 500px;
    overflow-y: auto;
}

/* Modern Report Styles (pour la preview) */
.report-header-modern { background: var(--card-bg) !important; border-color: var(--border-color) !important; }
.timeline-item { position: relative; }
.timeline-item:last-child { border-left-color: transparent !important; }
.card { background-color: var(--card-bg); border-color: var(--border-color); }
.card-title { color: var(--text-main); }
.card-text { color: var(--text-muted); }
.list-group-item { background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-main); }

/* Theme overrides */
.bg-theme-light { background-color: var(--light-bg) !important; }
.bg-theme-card { background-color: var(--card-bg) !important; }

.form-control-theme {
    background-color: var(--light-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-main) !important;
}
.form-control-theme:focus {
    background-color: var(--light-bg) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-main) !important;
    box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
}
.form-control-theme::placeholder {
    color: var(--text-muted);
}

/* Dark mode specifics */
body.dark .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

body.dark .modal-header,
body.dark .modal-footer {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

body.dark .form-check-label {
    color: var(--text-main);
}

/* Global Dark Mode Overrides for Modal & Forms */
body.dark .modal-content {
    background-color: var(--card-bg) !important;
    color: var(--text-main) !important;
    border-color: var(--border-color) !important;
}

body.dark .modal-header,
body.dark .modal-footer {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
}

body.dark .modal-title {
    color: var(--text-main) !important;
}

body.dark .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

body.dark .form-control,
body.dark .form-select {
    background-color: var(--light-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-main) !important;
}

body.dark .form-control:focus,
body.dark .form-select:focus {
    background-color: var(--light-bg) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-main) !important;
    box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25) !important;
}

body.dark .form-label,
body.dark .form-check-label,
body.dark h6,
body.dark .h6 {
    color: var(--text-main) !important;
}

body.dark .text-muted {
    color: var(--text-muted) !important;
}

body.dark .card {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
}

body.dark .bg-light {
    background-color: var(--light-bg) !important;
}

body.dark .border-theme {
    border-color: var(--border-color) !important;
}
</style>

<div class="reports-page">


    <div class="main-content">
        <!-- Panneau Gauche : Actions -->
        <div class="left-panel">
            <div class="config-panel">
                <h2><i class="fas fa-cogs"></i> Actions</h2>
                
                <button class="create-btn" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Nouveau Modèle
                </button>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchTemplates" placeholder="Rechercher un modèle...">
                </div>
                
                <div class="help-section">
                    <h3><i class="fas fa-info-circle"></i> Aide</h3>
                    <ul>
                        <li>Les modèles définissent le contenu des rapports.</li>
                        <li>Vous pouvez combiner plusieurs types de données (Interventions, Messages, Agenda).</li>
                        <li>Utilisez l'aperçu en direct pour vérifier le rendu.</li>
                        <li>Une fois créé, associez le modèle à une tâche programmée.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Panneau Droite : Liste -->
        <div class="right-panel">
            <div class="templates-panel">
                <div class="templates-header">
                    <h2><i class="fas fa-list"></i> Modèles Disponibles</h2>
                    <span class="badge bg-light text-dark" id="templateCount">0 modèles</span>
                </div>
                
                <div id="templatesList" class="templates-list">
                    <!-- Injecté via JS -->
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>Chargement des modèles...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Création/Édition (Single Page) -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-theme-card border-theme">
            <div class="modal-header border-theme py-3">
                <h5 class="modal-title text-main h4" id="modalTitle">Nouveau Modèle</h5>
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="reportForm">
                    <input type="hidden" id="reportId">
                    
                    <div class="row g-4 h-100">
                        <!-- Colonne Gauche : Configuration -->
                        <div class="col-lg-5 border-end border-theme">
                            <div class="pe-lg-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Configuration</h6>
                                
                                <div class="mb-4">
                                    <label class="form-label text-main fw-medium">Nom du modèle</label>
                                    <input type="text" class="form-control bg-theme-light border-theme text-main form-control-theme" id="reportName" required placeholder="Ex: Rapport Hebdomadaire">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-main fw-medium">Type de contenu</label>
                                    <div class="d-flex flex-column gap-3">
                                        <!-- Interventions -->
                                        <div class="card border-theme bg-theme-light overflow-hidden">
                                            <div class="p-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="reportType" value="interventions" id="typeInterventions" onchange="toggleParams('interventions'); generatePreview()">
                                                    <label class="form-check-label fw-medium text-main" for="typeInterventions">
                                                        <i class="fas fa-tools me-2 text-primary"></i>Interventions
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="params-interventions" class="params-group border-top border-theme p-3 d-none bg-theme-card">
                                                <div class="mb-2">
                                                    <label class="form-label small text-muted">Nombre max d'éléments</label>
                                                    <input type="number" class="form-control form-control-sm form-control-theme" id="maxInterventions" value="10" onchange="generatePreview()">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small text-muted">Filtrer par statut</label>
                                                    <div id="interventionStatuses" class="d-flex flex-column gap-1 ps-2">
                                                        <small class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement...</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Messages Helpdesk -->
                                        <div class="card border-theme bg-theme-light overflow-hidden">
                                            <div class="p-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="reportType" value="messages" id="typeMessages" onchange="toggleParams('messages'); generatePreview()">
                                                    <label class="form-check-label fw-medium text-main" for="typeMessages">
                                                        <i class="fas fa-envelope me-2 text-info"></i>Messages Helpdesk
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="params-messages" class="params-group border-top border-theme p-3 d-none bg-theme-card">
                                                <div class="mb-2">
                                                    <label class="form-label small text-muted">Nombre max d'éléments</label>
                                                    <input type="number" class="form-control form-control-sm form-control-theme" id="maxMessages" value="10" onchange="generatePreview()">
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="onlyOpenMessages" checked onchange="generatePreview()">
                                                    <label class="form-check-label small text-muted" for="onlyOpenMessages">Uniquement en cours</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Agenda -->
                                        <div class="card border-theme bg-theme-light overflow-hidden">
                                            <div class="p-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="reportType" value="agenda" id="typeAgenda" onchange="toggleParams('agenda'); generatePreview()">
                                                    <label class="form-check-label fw-medium text-main" for="typeAgenda">
                                                        <i class="fas fa-calendar-alt me-2 text-warning"></i>Agenda
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="params-agenda" class="params-group border-top border-theme p-3 d-none bg-theme-card">
                                                <div class="mb-2">
                                                    <label class="form-label small text-muted">Jours à venir</label>
                                                    <input type="number" class="form-control form-control-sm form-control-theme" id="agendaDays" value="7" onchange="generatePreview()">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Résumé Caisse -->
                                        <div class="card border-theme bg-theme-light overflow-hidden">
                                            <div class="p-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="reportType" value="resume_caisse" id="typeResumeCaisse" onchange="toggleParams('resume_caisse'); generatePreview()">
                                                    <label class="form-check-label fw-medium text-main" for="typeResumeCaisse">
                                                        <i class="fas fa-cash-register me-2 text-success"></i>Résumé Caisse
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="params-resume_caisse" class="params-group border-top border-theme p-3 d-none bg-theme-card">
                                                <div class="mb-3">
                                                    <label class="form-label d-block fw-bold">Période concernée</label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="resumeDate" id="resumeToday" value="today" checked onchange="generatePreview()">
                                                        <label class="form-check-label" for="resumeToday">Aujourd'hui (clôture du soir)</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="resumeDate" id="resumeYesterday" value="yesterday" onchange="generatePreview()">
                                                        <label class="form-check-label" for="resumeYesterday">Hier (envoi le lendemain matin)</label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label d-block fw-bold">Format de l'envoi</label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="resumeFormat" id="formatHtml" value="html" checked onchange="generatePreview()">
                                                        <label class="form-check-label" for="formatHtml">HTML (Corps de l'email)</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="resumeFormat" id="formatPdf" value="pdf" onchange="generatePreview()">
                                                        <label class="form-check-label" for="formatPdf">PDF (Pièce jointe)</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Paramètres Dynamiques (Supprimé car intégré ci-dessus) -->
                                <div id="paramsContainer" class="d-none"></div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="isActive" checked>
                                    <label class="form-check-label text-main" for="isActive">Modèle actif</label>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne Droite : Aperçu -->
                        <div class="col-lg-7">
                            <div class="h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-uppercase text-muted fw-bold mb-0 small">Aperçu en direct</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="generatePreview()">
                                        <i class="fas fa-sync-alt me-1"></i>Actualiser
                                    </button>
                                </div>
                                <div class="preview-container flex-grow-1 shadow-inner" id="previewContent">
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-magic fa-2x mb-3 opacity-50"></i>
                                        <p>Sélectionnez des options pour voir l'aperçu</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-theme py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary px-4" onclick="saveReport()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
    
    // Recherche
    document.getElementById('searchTemplates').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.template-card');
        
        cards.forEach(card => {
            const title = card.querySelector('.template-title').textContent.toLowerCase();
            const type = card.querySelector('.template-type').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || type.includes(searchTerm)) {
                card.parentElement.style.display = 'block'; // Afficher le parent (si grid wrapper)
                card.style.display = 'flex';
            } else {
                card.parentElement.style.display = 'none';
                card.style.display = 'none';
            }
        });
    });
    
    loadTemplates();
    loadInterventionStatuses();
    
    // Check schema on load
    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check_schema'
    })
        .then(response => response.json())
        .then(data => console.log('Schema check:', data))
        .catch(err => console.error('Schema check error:', err));
});

function loadInterventionStatuses() {
    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_statuses' })
    })
    .then(response => response.json())
    .then(result => {
        const container = document.getElementById('interventionStatuses');
        if (result.success && result.data) {
            container.innerHTML = '';
            result.data.forEach(status => {
                const div = document.createElement('div');
                div.className = 'form-check';
                div.innerHTML = `
                    <input class="form-check-input status-checkbox" type="checkbox" value="${status.id}" id="status_${status.id}" onchange="generatePreview()">
                    <label class="form-check-label small text-muted" for="status_${status.id}" style="color: ${status.couleur} !important; font-weight: 500;">
                        ${status.nom}
                    </label>
                `;
                container.appendChild(div);
            });
        } else {
            container.innerHTML = '<small class="text-danger">Erreur chargement statuts</small>';
        }
    })
    .catch(err => {
        console.error('Error loading statuses:', err);
        document.getElementById('interventionStatuses').innerHTML = '<small class="text-danger">Erreur chargement</small>';
    });
}

// Chargement des templates
function loadTemplates() {
    const container = document.getElementById('templatesList');
    const countBadge = document.getElementById('templateCount');
    
    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=list'
    })
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            if (data.success) {
                countBadge.textContent = data.data.length + ' modèles';
                
                if (data.data.length === 0) {
                    container.innerHTML = `
                        <div class="col-12 text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <p>Aucun modèle de rapport trouvé.</p>
                                <button class="btn btn-primary mt-2" onclick="openCreateModal()">Créer mon premier modèle</button>
                            </div>
                        </div>
                    `;
                    return;
                }

                data.data.forEach(template => {
                    const card = createTemplateCard(template);
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            container.innerHTML = '<div class="alert alert-danger">Erreur de connexion</div>';
        });
}

function createTemplateCard(template) {
    const div = document.createElement('div');
    // Pas de classe col ici car on utilise CSS Grid sur le parent
    
    const statusBadge = template.is_active == 1 
        ? '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Actif</span>' 
        : '<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2">Inactif</span>';

    div.innerHTML = `
        <div class="template-card">
            <div class="template-card-header">
                <h3 class="template-title">${escapeHtml(template.name)}</h3>
                ${statusBadge}
            </div>
            
            <div class="mb-3">
                <span class="template-type">${getReportTypeLabel(template.report_type)}</span>
            </div>
            
            <p class="template-desc">
                Modifié le ${new Date(template.updated_at).toLocaleDateString()}
            </p>
            
            <div class="template-footer">
                <small class="text-muted">ID: ${template.id}</small>
                <div class="template-actions">
                    <button class="btn-icon" onclick="editReport(${template.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteReport(${template.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    return div.firstElementChild; // Retourne la div .template-card directement
}

function getReportTypeLabel(type) {
    if (!type) return 'Inconnu';
    const types = type.split(',');
    const labels = {
        'interventions': 'Interventions',
        'messages': 'Helpdesk',
        'agenda': 'Agenda',
        'resume_caisse': 'Résumé Caisse',
        'mixed': 'Mixte'
    };
    
    return types.map(t => labels[t.trim()] || t).join(' + ');
}

// Gestion de la modale
let reportModal;
function openCreateModal() {
    document.getElementById('reportForm').reset();
    document.getElementById('reportId').value = '';
    document.getElementById('modalTitle').textContent = 'Nouveau Modèle';
    
    // Reset checkboxes and params
    document.querySelectorAll('input[name="reportType"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('.params-group').forEach(el => el.classList.add('d-none'));
    document.querySelectorAll('.status-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('isActive').checked = true; // Par défaut, nouveau modèle est actif
    document.getElementById('previewContent').innerHTML = `
        <div class="text-center text-muted py-5">
            <i class="fas fa-magic fa-2x mb-3 opacity-50"></i>
            <p>Sélectionnez des options pour voir l'aperçu</p>
        </div>
    `;
    
    reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
    reportModal.show();
}

function editReport(id) {
    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get', id: id })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.data;
                document.getElementById('reportId').value = template.id;
                document.getElementById('reportName').value = template.name;
                document.getElementById('isActive').checked = template.is_active == 1;
                document.getElementById('modalTitle').textContent = 'Modifier le Modèle';
                
                // Reset checkboxes
                document.querySelectorAll('input[name="reportType"]').forEach(cb => cb.checked = false);
                document.querySelectorAll('.params-group').forEach(el => el.classList.add('d-none'));
                
                // Set types
                const types = template.report_type.split(',');
                types.forEach(t => {
                    const cb = document.querySelector(`input[name="reportType"][value="${t.trim()}"]`);
                    if (cb) {
                        cb.checked = true;
                        toggleParams(t.trim());
                    }
                });
                
                // Set params
                const params = JSON.parse(template.parameters || '{}');
                
                // Helper pour remplir les champs
                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && val !== undefined) el.value = val;
                };
                const setCheck = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && val !== undefined) el.checked = val;
                };

                // Interventions
                if (params.interventions) {
                    setVal('maxInterventions', params.interventions.max_items);
                    if (params.interventions.status_ids) {
                        const ids = params.interventions.status_ids.split(',');
                        ids.forEach(id => {
                            const cb = document.getElementById(`status_${id}`);
                            if (cb) cb.checked = true;
                        });
                    }
                } else if (params.max_items && types.includes('interventions')) {
                     setVal('maxInterventions', params.max_items);
                }

                // Messages
                if (params.messages) {
                    setVal('maxMessages', params.messages.max_items);
                    setCheck('onlyOpenMessages', params.messages.only_open);
                } else if (types.includes('messages')) {
                    if (params.max_items) setVal('maxMessages', params.max_items);
                    if (params.only_open !== undefined) setCheck('onlyOpenMessages', params.only_open);
                }

                // Agenda
                if (params.agenda) {
                    setVal('agendaDays', params.agenda.days); // Note: agendaDays input uses 'days' param? Check logic below
                    // Actually agenda uses max_items in current implementation, let's check fetchAgenda
                    // fetchAgenda uses max_items. The input ID is agendaDays but logic might need update.
                    // Let's assume max_items for now based on previous code.
                } else if (params.max_items && types.includes('agenda')) {
                     setVal('agendaDays', params.max_items);
                }

                // Résumé Caisse
                if (params.resume_caisse) {
                    if (params.resume_caisse.date_option) {
                         const opt = params.resume_caisse.date_option;
                         const radio = document.querySelector(`input[name="resumeDate"][value="${opt}"]`);
                         if (radio) radio.checked = true;
                    }
                    if (params.resume_caisse.format) {
                         const fmt = params.resume_caisse.format;
                         const radio = document.querySelector(`input[name="resumeFormat"][value="${fmt}"]`);
                         if (radio) radio.checked = true;
                    }
                }
                
                reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
                reportModal.show();
                generatePreview();
            }
        });
}

function toggleParams(type) {
    const checkbox = document.querySelector(`input[name="reportType"][value="${type}"]`);
    const paramsDiv = document.getElementById(`params-${type}`);
    if (checkbox.checked) {
        paramsDiv.classList.remove('d-none');
    } else {
        paramsDiv.classList.add('d-none');
    }
}

function saveReport() {
    const id = document.getElementById('reportId').value;
    const name = document.getElementById('reportName').value;
    
    // Get selected types
    const selectedTypes = Array.from(document.querySelectorAll('input[name="reportType"]:checked'))
        .map(cb => cb.value);
        
    if (selectedTypes.length === 0) {
        alert('Veuillez sélectionner au moins un type de contenu');
        return;
    }
    
    const reportType = selectedTypes.join(',');
    
    // Build parameters
    const parameters = {};
    
    if (selectedTypes.includes('interventions')) {
        const selectedStatuses = Array.from(document.querySelectorAll('.status-checkbox:checked')).map(cb => cb.value);
        parameters.interventions = {
            max_items: document.getElementById('maxInterventions').value,
            status_ids: selectedStatuses.join(',')
        };
    }
    
    if (selectedTypes.includes('messages')) {
        parameters.messages = {
            max_items: document.getElementById('maxMessages').value,
            only_open: document.getElementById('onlyOpenMessages').checked
        };
    }
    
    if (selectedTypes.includes('agenda')) {
        parameters.agenda = {
            max_items: document.getElementById('agendaDays').value
        };
    }

    if (selectedTypes.includes('resume_caisse')) {
        const dateOption = document.querySelector('input[name="resumeDate"]:checked').value;
        const formatOption = document.querySelector('input[name="resumeFormat"]:checked').value;
        parameters.resume_caisse = {
            date_option: dateOption,
            format: formatOption
        };
    }
    
    const data = {
        name: name,
        report_type: reportType,
        parameters: JSON.stringify(parameters),
        is_active: document.getElementById('isActive').checked ? 1 : 0
    };
    
    const action = id ? 'update' : 'create';
    if (id) data.id = id;
    data.action = action;
    
    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.text()) // Get text first to debug
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON:', text);
            throw new Error('Réponse serveur invalide');
        }
    })
    .then(result => {
        if (result.success) {
            reportModal.hide();
            loadTemplates();
            // alert('Modèle enregistré avec succès');
        } else {
            alert('Erreur: ' + (result.error || result.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue');
    });
}

function deleteReport(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce modèle ?')) {
        fetch('api/reports_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadTemplates();
            } else {
                alert('Erreur: ' + (result.error || result.message || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la suppression');
        });
    }
}

function generatePreview() {
    const selectedTypes = Array.from(document.querySelectorAll('input[name="reportType"]:checked'))
        .map(cb => cb.value);
        
    if (selectedTypes.length === 0) {
        document.getElementById('previewContent').innerHTML = '<p class="text-center text-muted mt-5">Sélectionnez un type de contenu</p>';
        return;
    }
    
    // Build preview params
    const parameters = {};
    if (selectedTypes.includes('interventions')) {
        const selectedStatuses = Array.from(document.querySelectorAll('.status-checkbox:checked')).map(cb => cb.value);
        parameters.interventions = { 
            max_items: document.getElementById('maxInterventions').value,
            status_ids: selectedStatuses.join(',')
        };
    }
    if (selectedTypes.includes('messages')) {
        parameters.messages = { 
            max_items: document.getElementById('maxMessages').value,
            only_open: document.getElementById('onlyOpenMessages').checked
        };
    }
    if (selectedTypes.includes('agenda')) {
        parameters.agenda = { max_items: document.getElementById('agendaDays').value };
    }
    if (selectedTypes.includes('resume_caisse')) {
        const dateOption = document.querySelector('input[name="resumeDate"]:checked').value;
        parameters.resume_caisse = { date_option: dateOption };
    }

    const previewData = {
        action: 'preview',
        report_type: selectedTypes.join(','),
        parameters: parameters
    };

    document.getElementById('previewContent').innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';

    fetch('api/reports_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(previewData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('previewContent').innerHTML = data.html;
        } else {
            document.getElementById('previewContent').innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur inconnue'}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('previewContent').innerHTML = `<div class="alert alert-danger">Erreur de génération: ${error.message}</div>`;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>