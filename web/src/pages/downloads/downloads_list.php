<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$downloads = [];
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
        $stmt = $pdo->query("SELECT ID, NOM, DESCRIPTION, URL, show_on_login FROM download ORDER BY NOM ASC");
        $downloads = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des téléchargements : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>

<div class="list-page">
    <div class="page-header">
        <h1>
            <span>📥</span>
            Liste des Téléchargements
        </h1>
        <button onclick="openAddDownloadModal()" class="btn btn-success flex items-center gap-10">
            <span>➕</span>
            Ajouter un téléchargement
        </button>
    </div>

    <?php if (!empty($sessionMessage)): ?>
        <div class="alert alert-success mb-20">
            <span class="mr-10">✅</span>
            <div><?= $sessionMessage ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger mb-20">
            <span class="mr-10">⚠️</span>
            <div><?= $errorMessage ?></div>
        </div>
    <?php endif; ?>

    <?php if (empty($downloads) && empty($errorMessage)): ?>
        <div class="card text-center p-30 border-dashed">
            <div class="text-4xl mb-20 opacity-50">📥</div>
            <h3 class="mt-0 text-dark">Aucun téléchargement trouvé</h3>
            <p class="text-muted">Commencez par ajouter votre premier fichier téléchargeable</p>
            <button onclick="openAddDownloadModal()" class="btn btn-success mt-15">
                <span>➕</span> Ajouter un téléchargement
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-15">
            <?php foreach ($downloads as $download): ?>
                <div class="card border flex flex-col justify-between transition-transform hover:translate-y-2 hover:shadow-lg">
                    <div class="mb-15">
                        <div class="flex items-start justify-between gap-15 mb-10">
                            <div class="flex items-center gap-10 flex-1">
                                <span class="font-bold text-lg text-dark"><?= htmlspecialchars($download['NOM'] ?? '') ?></span>
                                <span class="text-xs text-muted bg-light px-5 rounded-4">#<?= htmlspecialchars((string)($download['ID'] ?? '')) ?></span>
                            </div>
                            <div class="flex gap-5">
                                <?php if (isset($download['show_on_login']) && $download['show_on_login']): ?>
                                    <span class="badge badge-success">📋 Public</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">🔒 Privé</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($download['DESCRIPTION'])): ?>
                            <div class="text-muted text-sm mb-10 leading-normal">
                                <?= nl2br(htmlspecialchars($download['DESCRIPTION'])) ?>
                            </div>
                        <?php endif; ?>

                        <div class="bg-light p-10 rounded-6 flex items-center gap-8 mb-10">
                            <span>📦</span>
                            <a href="<?= htmlspecialchars($download['URL'] ?? '') ?>" target="_blank" class="text-primary no-underline break-all hover:underline text-sm flex-1">
                                <?= htmlspecialchars($download['URL'] ?? '') ?>
                            </a>
                        </div>
                    </div>

                    <div class="flex justify-end gap-10 pt-10 border-t border-border">
                        <button onclick="openEditDownloadModal(this)" 
                                data-id="<?= htmlspecialchars((string)($download['ID'])) ?>"
                                data-nom="<?= htmlspecialchars($download['NOM'] ?? '') ?>"
                                data-url="<?= htmlspecialchars($download['URL'] ?? '') ?>"
                                data-desc="<?= htmlspecialchars($download['DESCRIPTION'] ?? '') ?>"
                                data-login="<?= isset($download['show_on_login']) && $download['show_on_login'] ? '1' : '0' ?>"
                                class="btn btn-primary btn-sm">
                            <span>✏️</span>
                            Modifier
                        </button>
                        <button onclick="openDeleteConfirm(<?= htmlspecialchars((string)($download['ID'])) ?>, '<?= htmlspecialchars($download['URL'] ?? '') ?>')" 
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
<!-- MODAL AJOUT TÉLÉCHARGEMENT (POPUP) -->
<!-- ========================================== -->
<div id="addDownloadModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">📥 Nouveau téléchargement</h3>
            <span class="modal-close" onclick="closeAddDownloadModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div id="downloadAlerts"></div>
            
            <form id="addDownloadForm">
                <div class="form-group">
                    <label for="dl_nom" class="form-label">Nom du fichier *</label>
                    <input type="text" id="dl_nom" name="nom" class="form-control" required placeholder="Ex: Manuel utilisateur...">
                </div>

                <div class="form-group">
                    <label class="form-label">Source du fichier *</label>
                    <div class="flex gap-20 mb-10 bg-light p-10 rounded-8 border border-border">
                        <label class="flex items-center gap-8 cursor-pointer m-0">
                            <input type="radio" name="source_type" value="url" checked onclick="toggleSourceType('url')">
                            <span>Lien externe (URL)</span>
                        </label>
                        <label class="flex items-center gap-8 cursor-pointer m-0">
                            <input type="radio" name="source_type" value="upload" onclick="toggleSourceType('upload')">
                            <span>Upload direct</span>
                        </label>
                    </div>
                </div>

                <div id="source_url_group" class="form-group">
                    <label for="dl_url" class="form-label">URL du téléchargement *</label>
                    <input type="url" id="dl_url" name="url" class="form-control" placeholder="https://...">
                </div>

                <div id="source_upload_group" class="form-group hidden">
                    <label for="dl_file" class="form-label">Choisir un fichier *</label>
                    <input type="file" id="dl_file" name="file" class="form-control" style="padding: 8px;">
                    <p class="text-xs text-muted mt-5">Taille max: <?= ini_get('upload_max_filesize') ?></p>
                </div>

                <div class="form-group">
                    <label for="dl_desc" class="form-label">Description</label>
                    <textarea id="dl_desc" name="description" class="form-control" rows="3" placeholder="Description optionnelle..."></textarea>
                </div>

                <div class="checkbox-group mb-20">
                    <input type="checkbox" id="add_dl_login" name="show_on_login" value="1">
                    <label for="add_dl_login" class="form-label">
                        <strong>Afficher sur la page de login</strong><br>
                        <small class="text-muted">Visible pour tous les visiteurs (public)</small>
                    </label>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddDownloadModal()">Annuler</button>
            <button type="button" class="btn btn-success" onclick="submitAddDownloadForm()">Ajouter</button>
        </div>
    </div>
</div>


<!-- ========================================== -->
<!-- MODAL MODIFICATION TÉLÉCHARGEMENT -->
<!-- ========================================== -->
<div id="editDownloadModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Modifier le téléchargement</h3>
            <span class="modal-close" onclick="closeEditDownloadModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div id="editDownloadAlerts"></div>
            
            <form id="editDownloadForm">
                <input type="hidden" id="edit_dl_id" name="id">
                
                <div class="form-group">
                    <label for="edit_dl_nom" class="form-label">Nom du fichier *</label>
                    <input type="text" id="edit_dl_nom" name="nom" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_dl_url" class="form-label">URL du téléchargement *</label>
                    <input type="url" id="edit_dl_url" name="url" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_dl_desc" class="form-label">Description</label>
                    <textarea id="edit_dl_desc" name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="checkbox-group mb-20">
                    <input type="checkbox" id="edit_dl_login" name="show_on_login" value="1">
                    <label for="edit_dl_login" class="form-label">
                        <strong>Afficher sur la page de login</strong><br>
                        <small class="text-muted">Visible pour tous les visiteurs (public)</small>
                    </label>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditDownloadModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitEditDownloadForm()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL CONFIRMATION SUPPRESSION -->
<!-- ========================================== -->
<div id="deleteConfirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: var(--danger-color);">
            <h3 class="modal-title">⚠️ Confirmation</h3>
            <span class="modal-close" onclick="closeDeleteConfirm()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce téléchargement ?</p>
            
            <div id="delete_file_option" class="mt-15 p-12 bg-light border border-danger-subtle rounded-8 hidden">
                <div class="checkbox-group">
                    <input type="checkbox" id="confirm_delete_file" name="delete_file" value="1">
                    <label for="confirm_delete_file" class="form-label text-danger">
                        <strong>Supprimer également le fichier sur le disque</strong><br>
                        <small class="opacity-80">Ceci supprimera définitivement le fichier dans /uploads/downloads/</small>
                    </label>
                </div>
            </div>
            
            <p class="text-sm text-muted mt-15">Cette action est irréversible.</p>
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
function openAddDownloadModal() {
    const modal = document.getElementById('addDownloadModal');
    const form = document.getElementById('addDownloadForm');
    const alerts = document.getElementById('downloadAlerts');
    
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    form.reset();
    alerts.innerHTML = '';
    
    // Reset toggle
    toggleSourceType('url');
    
    setTimeout(() => { document.getElementById('dl_nom').focus(); }, 100);
}

function toggleSourceType(type) {
    const urlGroup = document.getElementById('source_url_group');
    const uploadGroup = document.getElementById('source_upload_group');
    const urlInput = document.getElementById('dl_url');
    const fileInput = document.getElementById('dl_file');
    
    if (type === 'url') {
        urlGroup.classList.remove('hidden');
        uploadGroup.classList.add('hidden');
        urlInput.required = true;
        fileInput.required = false;
    } else {
        urlGroup.classList.add('hidden');
        uploadGroup.classList.remove('hidden');
        urlInput.required = false;
        fileInput.required = true;
    }
}

function closeAddDownloadModal() {
    document.getElementById('addDownloadModal').style.display = 'none';
    document.getElementById('addDownloadModal').classList.add('hidden');
}

function submitAddDownloadForm() {
    const form = document.getElementById('addDownloadForm');
    const alertsDiv = document.getElementById('downloadAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    if (!formData.get('nom')) {
        alertsDiv.innerHTML = '<div class="alert alert-danger mb-10">Le nom du fichier est obligatoire.</div>';
        return;
    }
    
    const sourceType = formData.get('source_type');
    if (sourceType === 'url' && !formData.get('url')) {
        alertsDiv.innerHTML = '<div class="alert alert-danger mb-10">L\'URL est obligatoire.</div>';
        return;
    }
    if (sourceType === 'upload' && (!formData.get('file') || formData.get('file').size === 0)) {
        alertsDiv.innerHTML = '<div class="alert alert-danger mb-10">Veuillez sélectionner un fichier à uploader.</div>';
        return;
    }
    
    const btn = document.querySelector('#addDownloadModal .btn-success');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Ajout...';
    btn.disabled = true;
    
    fetch('actions/downloads_add_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success mb-10">Ajouté avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 800);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-danger mb-10">${errorMsg}</div>`;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-danger mb-10">Erreur de communication avec le serveur.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// --- EDIT MODAL FUNCTIONS ---
function openEditDownloadModal(btn) {
    const modal = document.getElementById('editDownloadModal');
    const form = document.getElementById('editDownloadForm');
    const alerts = document.getElementById('editDownloadAlerts');
    
    // Populate form
    document.getElementById('edit_dl_id').value = btn.dataset.id;
    document.getElementById('edit_dl_nom').value = btn.dataset.nom;
    document.getElementById('edit_dl_url').value = btn.dataset.url;
    document.getElementById('edit_dl_desc').value = btn.dataset.desc;
    document.getElementById('edit_dl_login').checked = (btn.dataset.login === '1');
    
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    alerts.innerHTML = '';
}

function closeEditDownloadModal() {
    document.getElementById('editDownloadModal').style.display = 'none';
    document.getElementById('editDownloadModal').classList.add('hidden');
}

function submitEditDownloadForm() {
    const form = document.getElementById('editDownloadForm');
    const alertsDiv = document.getElementById('editDownloadAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    const btn = document.querySelector('#editDownloadModal .btn-primary');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Enregistrement...';
    btn.disabled = true;
    
    fetch('actions/downloads_edit_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success mb-10">Modifié avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 800);
        } else {
            alertsDiv.innerHTML = `<div class="alert alert-danger mb-10">${data.error || 'Erreur inconnue'}</div>`;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-danger mb-10">Erreur réseau.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// --- DELETE MODAL FUNCTIONS ---
function openDeleteConfirm(id, url) {
    deleteTargetId = id;
    const modal = document.getElementById('deleteConfirmModal');
    const fileOption = document.getElementById('delete_file_option');
    const checkbox = document.getElementById('confirm_delete_file');
    
    // Reset checkbox
    checkbox.checked = false;
    
    // Show option ONLY if it's a local file
    if (url && url.startsWith('/uploads/downloads/')) {
        fileOption.classList.remove('hidden');
        fileOption.style.display = 'block';
    } else {
        fileOption.classList.add('hidden');
        fileOption.style.display = 'none';
    }
    
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function closeDeleteConfirm() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    document.getElementById('deleteConfirmModal').classList.add('hidden');
    deleteTargetId = null;
}

function confirmDeleteAction() {
    if (!deleteTargetId) return;
    
    const deleteFile = document.getElementById('confirm_delete_file').checked;
    const formData = new FormData();
    formData.append('id', deleteTargetId);
    if (deleteFile) {
        formData.append('delete_file', '1');
    }
    
    fetch('actions/downloads_delete_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur: ' + (data.error || 'Impossible de supprimer'));
            closeDeleteConfirm();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur réseau lors de la suppression');
        closeDeleteConfirm();
    });
}
</script>