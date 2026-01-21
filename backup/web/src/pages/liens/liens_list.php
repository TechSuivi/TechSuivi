<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$liens = [];
$errorMessage = '';
$sessionMessage = '';

if (isset($_SESSION['delete_message'])) {
    $sessionMessage = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']);
}
if (isset($_SESSION['edit_message'])) {
    $sessionMessage .= $_SESSION['edit_message'];
    unset($_SESSION['edit_message']);
}

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT ID, NOM, DESCRIPTION, URL, show_on_login FROM liens ORDER BY NOM ASC");
        $liens = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des liens : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>

<style>
/* Styles modernes pour la page de liste des liens */
.list-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-add {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
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

.links-container {
    display: grid;
    gap: 15px;
}

.link-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.link-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.link-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
    gap: 15px;
}

.link-title {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.link-name {
    font-weight: 600;
    font-size: 1.1em;
    color: var(--text-color);
}

.link-id {
    font-size: 0.85em;
    color: var(--text-muted);
    background: var(--input-bg);
    padding: 2px 8px;
    border-radius: 4px;
}

.link-badges {
    display: flex;
    gap: 8px;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 500;
}

.badge-login-yes {
    background: #d4edda;
    color: #155724;
}

.badge-login-no {
    background: #e2e3e5;
    color: #6c757d;
}

.link-description {
    color: var(--text-muted);
    margin-bottom: 12px;
    line-height: 1.5;
}

.link-url {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background: var(--input-bg);
    border-radius: 6px;
    margin-bottom: 12px;
}

.link-url a {
    color: #3498db;
    text-decoration: none;
    word-break: break-all;
    flex: 1;
}

.link-url a:hover {
    text-decoration: underline;
}

.link-actions {
    display: flex;
    gap: 10px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.9em;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-edit {
    background: #3498db;
    color: white;
}

.btn-edit:hover {
    background: #2980b9;
}

.btn-delete {
    background: #e74c3c;
    color: white;
}

.btn-delete:hover {
    background: #c0392b;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .link-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .link-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>🔗</span>
            Liste des Liens
        </h1>
        <button onclick="openAddLinkModal()" class="btn-add" style="border: none; cursor: pointer; font-size: 1em;">
            <span>➕</span>
            Ajouter un lien
        </button>
    </div>

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

    <?php if (empty($liens) && empty($errorMessage)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔗</div>
            <h3>Aucun lien trouvé</h3>
            <p>Commencez par ajouter votre premier lien utile</p>
            <button onclick="openAddLinkModal()" class="btn-add" style="margin-top: 15px; border: none; cursor: pointer;">
                <span>➕</span> Ajouter un lien
            </button>
        </div>
    <?php else: ?>
        <div class="links-container">
            <?php foreach ($liens as $lien): ?>
                <div class="link-card">
                    <div class="link-header">
                        <div class="link-title">
                            <span class="link-name"><?= htmlspecialchars($lien['NOM'] ?? '') ?></span>
                            <span class="link-id">#<?= htmlspecialchars((string)($lien['ID'] ?? '')) ?></span>
                        </div>
                        <div class="link-badges">
                            <?php if (isset($lien['show_on_login']) && $lien['show_on_login']): ?>
                                <span class="badge badge-login-yes">📋 Visible sur login</span>
                            <?php else: ?>
                                <span class="badge badge-login-no">🔒 Privé</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($lien['DESCRIPTION'])): ?>
                        <div class="link-description">
                            <?= nl2br(htmlspecialchars($lien['DESCRIPTION'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="link-url">
                        <span>🌐</span>
                        <a href="<?= htmlspecialchars($lien['URL'] ?? '') ?>" target="_blank">
                            <?= htmlspecialchars($lien['URL'] ?? '') ?>
                        </a>
                    </div>

                    <div class="link-actions">
                        <button onclick="openEditLinkModal(this)" 
                                data-id="<?= htmlspecialchars((string)($lien['ID'])) ?>"
                                data-nom="<?= htmlspecialchars($lien['NOM'] ?? '') ?>"
                                data-url="<?= htmlspecialchars($lien['URL'] ?? '') ?>"
                                data-desc="<?= htmlspecialchars($lien['DESCRIPTION'] ?? '') ?>"
                                data-login="<?= isset($lien['show_on_login']) && $lien['show_on_login'] ? '1' : '0' ?>"
                                class="btn btn-edit" style="border:none; cursor:pointer;">
                            <span>✏️</span>
                            Modifier
                        </button>
                        <button onclick="openDeleteConfirm(<?= htmlspecialchars((string)($lien['ID'])) ?>)" 
                                class="btn btn-delete" style="border:none; cursor:pointer;">
                            <span>🗑️</span>
                            Supprimer
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- MODAL AJOUT LIEN (POPUP) -->
<!-- ========================================== -->
<style>
/* Modal Styles (Consistent with other pages) */
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
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    width: 90%;
    max-width: 600px;
    border: 1px solid var(--border-color);
    animation: slideUp 0.3s ease;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.2em;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    font-size: 1.5em;
    color: rgba(255,255,255,0.8);
    cursor: pointer;
    line-height: 1;
}
.close-modal:hover { color: white; }

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    background: var(--input-bg);
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Form Elements inside Modal */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-color); }
.required { color: #e74c3c; margin-left: 3px; }

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    box-sizing: border-box;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.checkbox-wrapper {
    background: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 12px;
}
.checkbox-label { display: flex; gap: 10px; cursor: pointer; align-items: flex-start; }
.checkbox-title { font-weight: 500; font-size: 0.95em; }
.checkbox-description { font-size: 0.85em; color: var(--text-muted); }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div id="addLinkModal" class="custom-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><span>🔗</span> Nouveau lien</h2>
            <span class="close-modal" onclick="closeAddLinkModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div id="linkAlerts"></div>
            
            <form id="addLinkForm">
                <div class="form-group">
                    <label for="link_nom">Nom du lien <span class="required">*</span></label>
                    <input type="text" id="link_nom" name="nom" class="form-control" required placeholder="Ex: Documentation...">
                </div>

                <div class="form-group">
                    <label for="link_url">URL <span class="required">*</span></label>
                    <input type="url" id="link_url" name="url" class="form-control" required placeholder="https://...">
                </div>

                <div class="form-group">
                    <label for="link_desc">Description</label>
                    <textarea id="link_desc" name="description" class="form-control" rows="3" placeholder="Description optionnelle..."></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-wrapper">
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_on_login" value="1">
                            <div>
                                <div class="checkbox-title">Afficher sur la page de login</div>
                                <div class="checkbox-description">Visible pour tous les visiteurs (public)</div>
                            </div>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddLinkModal()" style="background:transparent; border:1px solid var(--border-color); color:var(--text-color);">Annuler</button>
            <button type="button" class="btn btn-add" onclick="submitAddLinkForm()" style="border:none; cursor:pointer;">Ajouter</button>
        </div>
    </div>
</div>


<!-- ========================================== -->
<!-- MODAL MODIFICATION LIEN -->
<!-- ========================================== -->
<div id="editLinkModal" class="custom-modal">
    <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <h2 class="modal-title"><span>✏️</span> Modifier le lien</h2>
            <span class="close-modal" onclick="closeEditLinkModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div id="editLinkAlerts"></div>
            
            <form id="editLinkForm">
                <input type="hidden" id="edit_link_id" name="id">
                
                <div class="form-group">
                    <label for="edit_link_nom">Nom du lien <span class="required">*</span></label>
                    <input type="text" id="edit_link_nom" name="nom" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_link_url">URL <span class="required">*</span></label>
                    <input type="url" id="edit_link_url" name="url" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_link_desc">Description</label>
                    <textarea id="edit_link_desc" name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-wrapper">
                        <label class="checkbox-label">
                            <input type="checkbox" id="edit_link_login" name="show_on_login" value="1">
                            <div>
                                <div class="checkbox-title">Afficher sur la page de login</div>
                                <div class="checkbox-description">Visible pour tous les visiteurs (public)</div>
                            </div>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditLinkModal()" style="background:transparent; border:1px solid var(--border-color); color:var(--text-color);">Annuler</button>
            <button type="button" class="btn btn-edit" onclick="submitEditLinkForm()" style="border:none; cursor:pointer;">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL CONFIRMATION SUPPRESSION -->
<!-- ========================================== -->
<div id="deleteConfirmModal" class="custom-modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <h2 class="modal-title"><span>⚠️</span> Confirmation</h2>
            <span class="close-modal" onclick="closeDeleteConfirm()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce lien ?</p>
            <p style="font-size:0.9em; color:var(--text-muted);">Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirm()" style="background:transparent; border:1px solid var(--border-color); color:var(--text-color);">Annuler</button>
            <button type="button" class="btn btn-delete" onclick="confirmDeleteAction()" style="border:none; cursor:pointer;">Supprimer</button>
        </div>
    </div>
</div>

<script>
let deleteTargetId = null;

// --- ADD MODAL FUNCTIONS ---
function openAddLinkModal() {
    const modal = document.getElementById('addLinkModal');
    const form = document.getElementById('addLinkForm');
    const alerts = document.getElementById('linkAlerts');
    
    modal.style.display = 'flex';
    form.reset();
    alerts.innerHTML = '';
    
    setTimeout(() => { document.getElementById('link_nom').focus(); }, 100);
}

function closeAddLinkModal() {
    document.getElementById('addLinkModal').style.display = 'none';
}

function submitAddLinkForm() {
    const form = document.getElementById('addLinkForm');
    const alertsDiv = document.getElementById('linkAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    if (!formData.get('nom') || !formData.get('url')) {
        alertsDiv.innerHTML = '<div class="alert alert-error">Le nom et l\'URL sont obligatoires.</div>';
        return;
    }
    
    const btn = document.querySelector('#addLinkModal .btn-add');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Ajout...';
    btn.disabled = true;
    
    fetch('actions/liens_add_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success">Ajouté avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 800);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-error">${errorMsg}</div>`;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-error">Erreur de communication avec le serveur.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// --- EDIT MODAL FUNCTIONS ---
function openEditLinkModal(btn) {
    const modal = document.getElementById('editLinkModal');
    const form = document.getElementById('editLinkForm');
    const alerts = document.getElementById('editLinkAlerts');
    
    // Populate form
    document.getElementById('edit_link_id').value = btn.dataset.id;
    document.getElementById('edit_link_nom').value = btn.dataset.nom;
    document.getElementById('edit_link_url').value = btn.dataset.url;
    document.getElementById('edit_link_desc').value = btn.dataset.desc;
    document.getElementById('edit_link_login').checked = (btn.dataset.login === '1');
    
    modal.style.display = 'flex';
    alerts.innerHTML = '';
}

function closeEditLinkModal() {
    document.getElementById('editLinkModal').style.display = 'none';
}

function submitEditLinkForm() {
    const form = document.getElementById('editLinkForm');
    const alertsDiv = document.getElementById('editLinkAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    const btn = document.querySelector('#editLinkModal .btn-edit');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Enregistrement...';
    btn.disabled = true;
    
    fetch('actions/liens_edit_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success">Modifié avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 800);
        } else {
            alertsDiv.innerHTML = `<div class="alert alert-error">${data.error || 'Erreur inconnue'}</div>`;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-error">Erreur réseau.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// --- DELETE MODAL FUNCTIONS ---
function openDeleteConfirm(id) {
    deleteTargetId = id;
    document.getElementById('deleteConfirmModal').style.display = 'flex';
}

function closeDeleteConfirm() {
    deleteTargetId = null;
    document.getElementById('deleteConfirmModal').style.display = 'none';
}

function confirmDeleteAction() {
    if (!deleteTargetId) return;
    
    const btn = document.querySelector('#deleteConfirmModal .btn-delete');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Suppression...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('id', deleteTargetId);
    
    fetch('actions/liens_delete_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erreur lors de la suppression');
            closeDeleteConfirm();
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur réseau');
        closeDeleteConfirm();
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Global Click listener for all modals
window.onclick = function(event) {
    if (event.target == document.getElementById('addLinkModal')) closeAddLinkModal();
    if (event.target == document.getElementById('editLinkModal')) closeEditLinkModal();
    if (event.target == document.getElementById('deleteConfirmModal')) closeDeleteConfirm();
}
</script>