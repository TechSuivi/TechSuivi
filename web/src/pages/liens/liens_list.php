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

<!-- Inline CSS Removed for Audit -->

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>🔗</span>
            Liste des Liens
        </h1>
        <button onclick="openAddLinkModal()" class="btn btn-success flex items-center gap-10">
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
            <button onclick="openAddLinkModal()" class="btn btn-success mt-15">
                <span>➕</span> Ajouter un lien
            </button>
        </div>
    <?php else: ?>
        <div class="links-container">
            <?php foreach ($liens as $lien): ?>
                <div class="card border flex flex-col justify-between transition-transform hover:translate-y-2 hover:shadow-lg">
                    <div class="mb-15">
                        <div class="flex items-start justify-between gap-15 mb-10">
                            <div class="flex items-center gap-10 flex-1">
                                <span class="font-bold text-lg text-dark"><?= htmlspecialchars($lien['NOM'] ?? '') ?></span>
                                <span class="text-xs text-muted bg-light px-5 rounded-4">#<?= htmlspecialchars((string)($lien['ID'] ?? '')) ?></span>
                            </div>
                            <div class="flex gap-5">
                                <?php if (isset($lien['show_on_login']) && $lien['show_on_login']): ?>
                                    <span class="badge badge-success">📋 Public</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">🔒 Privé</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($lien['DESCRIPTION'])): ?>
                            <div class="text-muted text-sm mb-10 leading-normal">
                                <?= nl2br(htmlspecialchars($lien['DESCRIPTION'])) ?>
                            </div>
                        <?php endif; ?>

                        <div class="bg-light p-10 rounded-6 flex items-center gap-8 mb-10">
                            <span>🌐</span>
                            <a href="<?= htmlspecialchars($lien['URL'] ?? '') ?>" target="_blank" class="text-primary no-underline break-all hover:underline text-sm flex-1">
                                <?= htmlspecialchars($lien['URL'] ?? '') ?>
                            </a>
                        </div>
                    </div>

                    <div class="flex justify-end gap-10 pt-10 border-t border-border">
                        <button onclick="openEditLinkModal(this)" 
                                data-id="<?= htmlspecialchars((string)($lien['ID'])) ?>"
                                data-nom="<?= htmlspecialchars($lien['NOM'] ?? '') ?>"
                                data-url="<?= htmlspecialchars($lien['URL'] ?? '') ?>"
                                data-desc="<?= htmlspecialchars($lien['DESCRIPTION'] ?? '') ?>"
                                data-login="<?= isset($lien['show_on_login']) && $lien['show_on_login'] ? '1' : '0' ?>"
                                class="btn btn-primary btn-sm">
                            <span>✏️</span>
                            Modifier
                        </button>
                        <button onclick="openDeleteConfirm(<?= htmlspecialchars((string)($lien['ID'])) ?>)" 
                                class="btn btn-danger btn-sm">
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
<!-- Modal CSS Removed for Audit -->

<div id="addLinkModal" class="modal-overlay fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content">
        <div class="modal-header bg-gradient-primary">
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
            <button type="button" class="btn btn-secondary" onclick="closeAddLinkModal()">Annuler</button>
            <button type="button" class="btn btn-success" onclick="submitAddLinkForm()">Ajouter</button>
        </div>
    </div>
</div>


<!-- ========================================== -->
<!-- MODAL MODIFICATION LIEN -->
<!-- ========================================== -->
<div id="editLinkModal" class="modal-overlay fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content">
        <div class="modal-header bg-gradient-info">
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
            <button type="button" class="btn btn-secondary" onclick="closeEditLinkModal()">Annuler</button>
            <button type="button" class="btn btn-info" onclick="submitEditLinkForm()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL CONFIRMATION SUPPRESSION -->
<!-- ========================================== -->
<div id="deleteConfirmModal" class="modal-overlay fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header bg-gradient-danger">
            <h2 class="modal-title"><span>⚠️</span> Confirmation</h2>
            <span class="close-modal" onclick="closeDeleteConfirm()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce lien ?</p>
            <p style="font-size:0.9em; color:var(--text-muted);">Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirm()">Annuler</button>
            <button type="button" class="btn btn-danger" onclick="confirmDeleteAction()">Supprimer</button>
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
    
    const btn = document.querySelector('#addLinkModal .btn-success');
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
    
    const btn = document.querySelector('#editLinkModal .btn-info');
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
    
    const btn = document.querySelector('#deleteConfirmModal .btn-danger');
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

/* Global Click listener for all modals - DISABLED as per user request
window.onclick = function(event) {
    if (event.target == document.getElementById('addLinkModal')) closeAddLinkModal();
    if (event.target == document.getElementById('editLinkModal')) closeEditLinkModal();
    if (event.target == document.getElementById('deleteConfirmModal')) closeDeleteConfirm();
}
*/
</script>