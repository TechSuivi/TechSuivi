<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$messages = [];
$categories = [];
$errorMessage = '';
$sessionMessage = '';
$selectedCategory = $_GET['category'] ?? '';
$selectedCategoryName = '';

// Messages de session
if (isset($_SESSION['delete_message'])) {
    $sessionMessage = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']);
}
if (isset($_SESSION['edit_message'])) {
    $sessionMessage .= $_SESSION['edit_message'];
    unset($_SESSION['edit_message']);
}
if (isset($_SESSION['add_message'])) {
    $sessionMessage .= $_SESSION['add_message'];
    unset($_SESSION['add_message']);
}

// Le traitement AJAX est maintenant géré par ajax/helpdesk_messages_ajax.php

if (isset($pdo)) {
    try {
        // Récupérer les catégories
        $stmt = $pdo->query("SELECT ID, CATEGORIE FROM helpdesk_cat ORDER BY CATEGORIE ASC");
        $categories = $stmt->fetchAll();
        
        // Si une catégorie est sélectionnée, récupérer ses messages
        if (!empty($selectedCategory)) {
            if ($selectedCategory === 'all') {
                $selectedCategoryName = "Tous les messages";
                
                // Récupérer tous les messages
                $stmt = $pdo->prepare("
                    SELECT 
                        m.ID, m.TITRE, m.MESSAGE, m.DATE, m.FAIT, m.DATE_FAIT,
                        c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_telephone, c.portable as client_portable, c.ID as id_client
                    FROM helpdesk_msg m 
                    LEFT JOIN clients c ON m.id_client = c.ID
                    ORDER BY m.FAIT ASC, m.DATE DESC
                ");
                $stmt->execute();
                $messages = $stmt->fetchAll();
            } elseif (is_numeric($selectedCategory)) {
                // Récupérer le nom de la catégorie
                $stmt = $pdo->prepare("SELECT CATEGORIE FROM helpdesk_cat WHERE ID = :id");
                $stmt->bindParam(':id', $selectedCategory, PDO::PARAM_INT);
                $stmt->execute();
                $selectedCategoryName = $stmt->fetchColumn();
                
                if ($selectedCategoryName) {
                    // Récupérer les messages de cette catégorie
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.ID, m.TITRE, m.MESSAGE, m.DATE, m.FAIT, m.DATE_FAIT,
                            c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_telephone, c.portable as client_portable, c.ID as id_client
                        FROM helpdesk_msg m 
                        LEFT JOIN clients c ON m.id_client = c.ID
                        WHERE m.CATEGORIE = :categorie 
                        ORDER BY m.FAIT ASC, m.DATE DESC
                    ");
                    $stmt->bindParam(':categorie', $selectedCategory, PDO::PARAM_INT);
                    $stmt->execute();
                    $messages = $stmt->fetchAll();
                } else {
                    $errorMessage = "Catégorie non trouvée.";
                }
            }
        }
        
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>

<div class="container container-center max-w-1200">
    <div class="page-header">
        <h1>
            <span>💬</span>
            Messages Helpdesk
        </h1>
        <?php if (!empty($selectedCategory) && !empty($selectedCategoryName)): ?>
            <button id="addMessageBtn" class="btn btn-success flex items-center gap-10">
                <span>➕</span>
                Nouveau message
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($sessionMessage)): ?>
        <div class="alert alert-success mb-20">
            <?= $sessionMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger mb-20">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="card bg-secondary border text-center p-20">
            <p>Aucune catégorie trouvée. <a href="index.php?page=helpdesk_categories" class="text-primary underline">Créer des catégories</a> pour commencer.</p>
        </div>
    <?php else: ?>
        
        <div class="card bg-secondary border p-20 rounded-12 shadow-sm mb-25">
            <div class="flex flex-wrap gap-20 items-end">
                <div class="flex-1 min-w-200">
                    <label class="block mb-8 font-bold text-muted" for="categorySelect">Catégorie</label>
                    <select id="categorySelect" class="form-control w-full p-10 border rounded-8 bg-input text-dark" onchange="window.location.href='index.php?page=messages&category=' + this.value">
                        <option value="">-- Choisir une catégorie --</option>
                        <option value="all" <?= $selectedCategory === 'all' ? 'selected' : '' ?>>Tous</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['ID'] ?>" <?= $selectedCategory == $category['ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['CATEGORIE']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($selectedCategory)): ?>
                <div class="flex-2 min-w-300">
                    <label class="block mb-8 font-bold text-muted" for="searchInput">Recherche</label>
                    <div class="flex gap-10 items-center">
                        <input type="text" id="searchInput" class="form-control flex-1 p-10 border rounded-8 bg-input text-dark" placeholder="Rechercher dans les messages...">
                        <label class="cursor-pointer flex items-center gap-6 whitespace-nowrap m-0">
                            <input type="checkbox" id="searchAllCategories" class="w-auto m-0"> 
                            <span class="font-normal text-sm">Toutes catégories</span>
                        </label>
                    </div>
                </div>
                
                <div class="w-100 flex-shrink-0">
                    <label class="block mb-8 font-bold text-muted" for="limitSelect">Affichage</label>
                    <select id="limitSelect" class="form-control w-full p-10 border rounded-8 bg-input text-dark">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($selectedCategory) && !empty($selectedCategoryName)): ?>

            <!-- Statistiques -->
            <div id="statsContainer" class="flex gap-15 mb-25 hidden">
                <div class="card bg-secondary border flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                    <div class="text-3xl font-bold text-success leading-tight" id="statTotal">0</div>
                    <div class="text-xs text-muted uppercase tracking-wide">Total</div>
                </div>
                <div class="card bg-secondary border flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                    <div class="text-3xl font-bold text-secondary leading-tight" id="statTodo">0</div>
                    <div class="text-xs text-muted uppercase tracking-wide">À faire</div>
                </div>
                <div class="card bg-secondary border flex-1 p-15 rounded-10 shadow-sm flex flex-col items-center hover:translate-y-2 transition-transform">
                    <div class="text-3xl font-bold text-success leading-tight" id="statDone">0</div>
                    <div class="text-xs text-muted uppercase tracking-wide">Terminés</div>
                </div>
            </div>

            <!-- Zone de chargement -->
            <div id="loadingIndicator" class="text-center p-40 hidden">
                <div class="text-4xl animate-pulse">⏳</div>
                <p class="text-muted mt-10">Chargement des messages...</p>
            </div>

            <!-- Liste des messages -->
            <div id="messagesContainer" class="flex flex-col gap-15">
                <!-- Les messages seront chargés ici via AJAX -->
            </div>


            <!-- Pagination -->
            <div id="paginationContainer" class="flex justify-center gap-8 mt-30 hidden">
                <!-- La pagination sera générée ici -->
            </div>
            
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<!-- Modal de réponse (Shared) -->
<?php include 'includes/modals/reply_message.php'; ?>

<!-- Modal d'ajout de message (Shared) -->
<?php include 'includes/modals/add_message.php'; ?>
<?php include 'includes/modals/add_client.php'; ?>

<!-- Modal de confirmation -->
<div id="confirmationModal" class="modal-overlay fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content bg-card rounded-12 shadow-2xl w-90 max-w-400 border border-border animate-slide-up overflow-hidden text-center p-20">
        <div class="text-5xl mb-15">⚠️</div>
        <h3 class="mt-0 text-dark mb-10">Confirmation</h3>
        <p id="confirmMessage" class="text-muted mb-25">Êtes-vous sûr ?</p>
        <div class="flex gap-10 justify-center">
            <button onclick="closeConfirmModal()" class="btn btn-secondary border border-border">Annuler</button>
            <button id="confirmActionBtn" class="btn btn-danger">Confirmer</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modals
    const addMessageModal = document.getElementById('addMessageModal');
    const confirmModal = document.getElementById('confirmationModal');
    
    // Buttons & Elements
    const addMessageBtn = document.getElementById('addMessageBtn');
    const closeAddMessageModal = document.getElementById('closeAddMessageModal'); // Not used in shared modal click listener
    const cancelAddMessage = document.getElementById('cancelAddMessage'); // Not used in shared modal
    const addMessageForm = document.getElementById('msg_add_form');
    
    // Reply Modal
    const replyMessageModal = document.getElementById('replyMessageModal');
    // const closeReplyMessageModal = ... (Removed, handled globally)
    // const cancelReplyMessage = ... (Removed, handled globally)
    const replyMessageForm = document.getElementById('msg_reply_form');
    
    // Search & Filters
    const searchInput = document.getElementById('searchInput');
    const limitSelect = document.getElementById('limitSelect');
    const searchAllCheckbox = document.getElementById('searchAllCategories');
    
    // State
    let currentPage = 1;
    let currentSearch = '';
    let currentLimit = 20;
    let searchAllCategories = false;
    let searchTimeout = null;
    let pendingAction = null;
    
    // Initial Data
    const selectedCategory = <?= json_encode($selectedCategory) ?>;
    
    // --- Initialization ---
    
    if (selectedCategory) {
        loadMessages();
    }
    
    // --- Event Listeners ---
    
    // Add Message Modal
    if (addMessageBtn) {
        addMessageBtn.addEventListener('click', () => {
            initMessageClientSearch();
            openModal(addMessageModal);
            setTimeout(() => {
                document.getElementById('msg_add_titre').focus();
            }, 100);
        });
    }
    
    if (closeAddMessageModal) {
        closeAddMessageModal.addEventListener('click', () => closeModal(addMessageModal));
    }
    
    if (cancelAddMessage) {
        cancelAddMessage.addEventListener('click', () => closeModal(addMessageModal));
    }
    
    // Close modals on outside click
    window.addEventListener('click', (e) => {
        /* DISABLED as per user request
        if (e.target === addMessageModal) closeModal(addMessageModal);
        if (e.target === replyMessageModal) closeModal(replyMessageModal);
        if (e.target === confirmModal) closeConfirmModal();
        */
        
        // Hide suggestions
        const suggestions = document.getElementById('msg_add_client_suggestions');
        const searchInput = document.getElementById('msg_add_client_search');
        if (suggestions && e.target !== searchInput && e.target !== suggestions) {
            suggestions.style.display = 'none';
        }
    });
    
    // --- Client Search Logic ---
    const clientSearch = document.getElementById('msg_add_client_search');
    const clientId = document.getElementById('msg_add_id_client');
    const suggestions = document.getElementById('msg_add_client_suggestions');
    let clientSearchTimeout;
    
    if (clientSearch) {
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            clearTimeout(clientSearchTimeout);
            
            if (term.length === 0) {
                 clientId.value = '';
                 suggestions.style.display = 'none';
                 return;
            }
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            clientSearchTimeout = setTimeout(() => {
                const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'p-10 cursor-pointer border-b border-border text-sm hover:bg-hover';
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
    }
    
    // Search with debounce
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = this.value.trim();
                currentPage = 1;
                loadMessages();
            }, 300);
        });
    }
    
    // Limit change
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            currentLimit = parseInt(this.value);
            currentPage = 1;
            loadMessages();
        });
    }
    
    // Search all categories toggle
    if (searchAllCheckbox) {
        searchAllCheckbox.addEventListener('change', function() {
            searchAllCategories = this.checked;
            currentPage = 1;
            loadMessages();
        });
    }
    
    // Add Message Form Submit logic moved to function
    // if (addMessageForm) { addMessageForm.addEventListener(...) } removed.

    // Reply Message Modal Listeners

    // Reply Message Form Submit logic moved to global function submitReplyMessageForm()
    // Listener removed.

    // --- Functions ---
    // Moved to global scope at the end
    // Global Helpers
    function openModal(modal) {
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            // Reset alerts
            const alert = modal.querySelector('#msg_add_alert');
            if (alert) alert.innerHTML = '';
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }

    // Global functions for inline onclick handlers
    window.closeReplyMessageModal = function() {
        const modal = document.getElementById('replyMessageModal');
        closeModal(modal);
    };

    window.closeConfirmModal = function() {
        const modal = document.getElementById('confirmationModal');
        closeModal(modal);
        pendingAction = null;
    };

    window.confirmAction = function(action, id, category) {
        pendingAction = { action, id, category };
        const modal = document.getElementById('confirmationModal');
        const msg = document.getElementById('confirmMessage');
        const btn = document.getElementById('confirmActionBtn');
        
        if (action === 'delete') {
            msg.textContent = 'Êtes-vous sûr de vouloir supprimer ce message ?';
            btn.textContent = 'Supprimer';
            btn.className = 'btn btn-danger';
            btn.onclick = executeDelete;
        }
        
        openModal(modal);
        return false; // Prevent default link behavior
    };

    function executeDelete() {
        if (!pendingAction) return;
        
        const { id, category } = pendingAction;
        
        fetch(`actions/helpdesk_messages_delete.php?id=${id}&category=${category}`)
            .then(response => {
                if (response.ok) {
                    closeConfirmModal();
                    loadMessages();
                } else {
                    alert('Erreur lors de la suppression');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur de communication');
            });
    }

    window.openReplyModal = function(id, title, messageSnippet) {
        document.getElementById('msg_reply_id').value = id;
        document.getElementById('msg_reply_title').textContent = title;
        document.getElementById('msg_reply_preview').textContent = messageSnippet;
        
        // Reset form and alerts
        document.getElementById('msg_reply_form').reset();
        const alertDiv = document.getElementById('msg_reply_alert');
        if(alertDiv) alertDiv.innerHTML = '';
        
        const modal = document.getElementById('replyMessageModal');
        openModal(modal);
    };

    function loadMessages() {
        const container = document.getElementById('messagesContainer');
        const loading = document.getElementById('loadingIndicator');
        const stats = document.getElementById('statsContainer');
        const pagination = document.getElementById('paginationContainer');
        
        loading.style.display = 'block';
        loading.classList.remove('hidden');
        container.style.display = 'none';
        
        let url = `ajax/helpdesk_messages_ajax.php?category=${selectedCategory}`;
        url += `&page=${currentPage}`;
        url += `&limit=${currentLimit}`;
        if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;
        if (searchAllCategories) url += `&all_categories=1`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                loading.classList.add('hidden');
                
                if (data.error) {
                    container.innerHTML = `<div class='alert alert-danger'>${data.error}</div>`;
                    container.style.display = 'block';
                    return;
                }
                
                // Update stats
                document.getElementById('statTotal').textContent = data.stats.total;
                document.getElementById('statTodo').textContent = data.stats.todo;
                document.getElementById('statDone').textContent = data.stats.done;
                stats.style.display = 'flex';
                stats.classList.remove('hidden');
                
                if (data.messages.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state text-center p-40">
                            <div class="text-4xl opacity-50 mb-10">📭</div>
                            <h3>Aucun message trouvé</h3>
                            <p class="text-muted">Essayez de modifier vos critères de recherche.</p>
                        </div>
                    `;
                    container.style.display = 'block';
                    pagination.style.display = 'none';
                    return;
                }
                
                // Render messages
                let html = '';
                data.messages.forEach(msg => {
                    const statusClass = msg.FAIT == 1 ? 'done border-l-4 border-l-success' : 'border-l-4 border-l-secondary';
                    const statusText = msg.FAIT == 1 ? 'Terminé' : 'À faire';
                    const statusBtnClass = msg.FAIT == 1 ? 'btn-success' : 'btn-secondary';
                    const toggleAction = msg.FAIT == 1 ? 0 : 1;
                    const toggleLabel = msg.FAIT == 1 ? 'Marquer à faire' : 'Marquer fait';
                    const toggleIcon = msg.FAIT == 1 ? '↩️' : '✅';
                    
                    // Client Info
                    let clientHtml = '';
                    if (msg.client_nom) {
                        clientHtml = `
                            <div class="text-xs text-muted mb-5 flex items-center gap-5">
                                👤 <a href="index.php?page=clients_view&id=${msg.id_client}" class="text-primary hover:underline">${msg.client_nom} ${msg.client_prenom}</a>
                                ${msg.client_telephone ? `📞 ${msg.client_telephone}` : ''}
                            </div>
                        `;
                    }
                    
                    // Replies
                    let repliesHtml = '';
                    if (msg.replies && msg.replies.length > 0) {
                        repliesHtml = '<div class="mt-15 pt-15 border-t border-dashed border-border">';
                        msg.replies.forEach(reply => {
                            repliesHtml += `
                                <div class="bg-input rounded-8 p-12 mb-10 border border-border">
                                    <div class="flex justify-between text-xs text-muted mb-5">
                                        <span>👤 ${reply.auteur || 'Utilisateur'}</span>
                                        <span>${reply.date_formatted}</span>
                                    </div>
                                    <div class="whitespace-pre-wrap text-sm text-dark">${reply.message}</div>
                                </div>
                            `;
                        });
                        repliesHtml += '</div>';
                    }
                    
                    html += `
                        <div class="card bg-secondary border p-20 rounded-12 shadow-sm transition-all hover:translate-y-2 hover:shadow-md ${statusClass}">
                            <div class="flex justify-between items-start mb-12">
                                <h3 class="m-0 text-lg font-bold text-dark">${msg.TITRE}</h3>
                                <div class="text-xs text-muted flex items-center gap-10">
                                    <span>📅 ${msg.date_formatted}</span>
                                </div>
                            </div>
                            
                            ${clientHtml}
                            
                            <div class="whitespace-pre-wrap text-dark leading-normal mb-15">${msg.MESSAGE}</div>
                            
                            <div class="flex justify-between gap-10 border-t border-border pt-15">
                                <div class="flex gap-5">
                                    <button onclick="changeStatus(${msg.ID}, ${toggleAction})" class="btn btn-xs ${statusBtnClass} flex items-center gap-5">
                                        <span>${toggleIcon}</span> ${statusText}
                                    </button>
                                </div>
                                <div class="flex gap-5">
                                    <button onclick="openReplyModal(${msg.ID}, '${msg.TITRE.replace(/'/g, "\\'")}', '${msg.MESSAGE.substring(0, 50).replace(/'/g, "\\'").replace(/\n/g, ' ')}...')" class="btn btn-xs btn-primary flex items-center gap-5">
                                        <span>↩️</span> Répondre
                                    </button>
                                    <button onclick="return confirmAction('delete', ${msg.ID}, ${selectedCategory})" class="btn btn-xs btn-danger bg-transparent border-0 text-danger hover:scale-110 transition-transform p-5" title="Supprimer">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                            
                            ${repliesHtml}
                        </div>
                    `;
                });
                
                container.innerHTML = html;
                container.style.display = 'flex';
                container.classList.remove('hidden');
                
                // Render Pagination
                renderPagination(data.pagination);
                pagination.style.display = 'flex';
                pagination.classList.remove('hidden');
            })
            .catch(err => {
                loading.style.display = 'none';
                console.error("Fetch error:", err);
                container.innerHTML = `<div class='alert alert-danger'>Erreur de chargement. Vérifiez la console.</div>`;
                container.style.display = 'block';
            });
    }

    function renderPagination(paginationData) {
        const container = document.getElementById('paginationContainer');
        container.innerHTML = '';
        
        if (paginationData.total_pages <= 1) return;
        
        const createBtn = (page, text, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = `w-36 h-36 flex items-center justify-center rounded-8 border border-border bg-card text-dark cursor-pointer transition-all hover:bg-hover ${active ? 'bg-success text-white border-success' : ''}`;
            if (active) {
                btn.style.backgroundColor = 'var(--success-color)';
                btn.style.color = 'white';
                btn.style.borderColor = 'var(--success-color)';
            }
            btn.innerHTML = text;
            btn.disabled = disabled;
            if (!disabled && !active) {
                btn.onclick = () => {
                    currentPage = page;
                    loadMessages();
                };
            }
            return btn;
        };
        
        // Prev
        container.appendChild(createBtn(currentPage - 1, '«', false, currentPage === 1));
        
        // Pages
        for (let i = 1; i <= paginationData.total_pages; i++) {
            // Show first, last, current, and surrounding
            if (i === 1 || i === paginationData.total_pages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                container.appendChild(createBtn(i, i, i === currentPage));
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.className = 'flex items-center justify-center w-20 text-muted';
                container.appendChild(dots);
            }
        }
        
        // Next
        container.appendChild(createBtn(currentPage + 1, '»', false, currentPage === paginationData.total_pages));
    }
    
    // Add these to global scope for onclick handlers in generated HTML
    window.changeStatus = function(id, newStatus) {
        fetch(`actions/helpdesk_messages_status.php?id=${id}&status=${newStatus}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                } else {
                    alert('Erreur: ' + (data.error || 'Impossible de changer le statut'));
                }
            })
            .catch(err => console.error("Status error:", err));
    };
});
</script>