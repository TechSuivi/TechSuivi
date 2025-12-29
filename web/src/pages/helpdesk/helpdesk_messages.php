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

<style>
:root {
    --emerald-start: #2ecc71;
    --emerald-end: #27ae60;
    --emerald-shadow: rgba(46, 204, 113, 0.3);
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--emerald-start) 0%, var(--emerald-end) 100%);
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px var(--emerald-shadow);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title {
    margin: 0;
    font-size: 1.8em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Controls Card */
.controls-card {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.controls-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.form-group {
    margin-bottom: 0;
    flex: 1;
    min-width: 200px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-muted);
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 0.95em;
    transition: all 0.2s;
    box-sizing: border-box; /* Ensure padding doesn't affect width */
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--emerald-start);
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
    outline: none;
}

/* Dark Mode Overrides */
body.dark .form-control {
    background-color: #2b2b2b;
    border-color: #444;
    color: #ecf0f1;
}

body.dark .form-control option {
    background-color: #2b2b2b;
    color: #ecf0f1;
}

body.dark .controls-card,
body.dark .stat-badge,
body.dark .message-card,
body.dark .modal-content,
body.dark .page-btn {
    background-color: #2b2b2b;
    border-color: #444;
}

body.dark .page-title,
body.dark .form-label,
body.dark .stat-label,
body.dark .message-title,
body.dark .message-content,
body.dark .modal-title,
body.dark .close-modal {
    color: #ecf0f1;
}

body.dark .text-muted,
body.dark .message-meta {
    color: #bdc3c7;
}

body.dark .page-btn:not(.active) {
    color: #ecf0f1;
}

body.dark .page-btn:hover:not(.active):not(:disabled) {
    background-color: #3e3e3e;
}

/* Stats Badges */
.stats-container {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-badge {
    flex: 1;
    background: var(--card-bg);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    transition: transform 0.2s;
}

.stat-badge:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 1.8em;
    font-weight: 700;
    color: var(--emerald-end);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.85em;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Message Cards */
.message-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.message-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-color: var(--emerald-start);
}

.message-card.done {
    border-left: 5px solid var(--emerald-end);
}

.message-card:not(.done) {
    border-left: 5px solid #e67e22; /* Orange for todo */
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.message-title {
    font-size: 1.1em;
    font-weight: 600;
    color: var(--text-color);
    margin: 0;
}

.message-meta {
    font-size: 0.85em;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 10px;
}

.message-content {
    color: var(--text-color);
    line-height: 1.6;
    margin-bottom: 15px;
    white-space: pre-wrap;
}

.message-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    border-top: 1px solid var(--border-color);
    padding-top: 15px;
}

.replies-container {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed var(--border-color);
}

.reply-card {
    background: var(--input-bg);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
}

.reply-meta {
    font-size: 0.8em;
    color: var(--text-muted);
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
}

.reply-content {
    font-size: 0.95em;
    color: var(--text-color);
    white-space: pre-wrap;
}

.reply-form {
    display: none; /* Removed inline form styles */
}

/* Buttons */
.btn-modern {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-size: 0.9em;
}

.btn-primary {
    background: linear-gradient(135deg, var(--emerald-start) 0%, var(--emerald-end) 100%);
    color: white;
    box-shadow: 0 2px 6px var(--emerald-shadow);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px var(--emerald-shadow);
}

/* Header Button Specifics */
.page-header .btn-primary {
    background: white;
    color: var(--emerald-end);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.page-header .btn-primary:hover {
    background: #f8f9fa;
    color: var(--emerald-start);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-status-todo {
    background: #fff3cd;
    color: #e67e22;
    border: 1px solid #ffeeba;
}

.btn-status-done {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.btn-delete-icon {
    background: transparent;
    border: none;
    color: #e74c3c;
    font-size: 1.2em;
    cursor: pointer;
    opacity: 0.6;
    transition: all 0.2s;
    padding: 5px;
}

.btn-delete-icon:hover {
    opacity: 1;
    transform: scale(1.1);
}

/* Modal */
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
    max-width: 500px;
    width: 90%;
    border: 1px solid var(--border-color);
    animation: slideUp 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 15px;
}

.modal-title {
    font-size: 1.3em;
    font-weight: 600;
    color: var(--emerald-end);
    margin: 0;
}

.close-modal {
    font-size: 1.5em;
    color: var(--text-muted);
    cursor: pointer;
    line-height: 1;
}

.close-modal:hover {
    color: var(--text-color);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
}

.page-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.2s;
}

.page-btn.active {
    background: var(--emerald-end);
    color: white;
    border-color: var(--emerald-end);
}

.page-btn:hover:not(.active):not(:disabled) {
    background: var(--hover-bg);
    border-color: var(--emerald-start);
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="page-header">
    <h1 class="page-title">💬 Messages Helpdesk</h1>
    <?php if (!empty($selectedCategory) && !empty($selectedCategoryName)): ?>
        <button id="addMessageBtn" class="btn-modern btn-primary">
            <span>➕</span> Nouveau message
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($sessionMessage)): ?>
    <div class="alert alert-success">
        <?= $sessionMessage ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<?php if (empty($categories)): ?>
    <div class="controls-card" style="text-align: center;">
        <p>Aucune catégorie trouvée. <a href="index.php?page=helpdesk_categories">Créer des catégories</a> pour commencer.</p>
    </div>
<?php else: ?>
    
    <div class="controls-card">
        <div class="controls-row">
            <div class="form-group">
                <label class="form-label" for="categorySelect">Catégorie</label>
                <select id="categorySelect" class="form-control" onchange="window.location.href='index.php?page=messages&category=' + this.value">
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
            <div class="form-group" style="flex: 2; min-width: 300px;">
                <label class="form-label" for="searchInput">Recherche</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher dans les messages..." style="flex: 1;">
                    <label class="form-label" style="margin: 0; white-space: nowrap; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <input type="checkbox" id="searchAllCategories" style="width: auto; margin: 0;"> 
                        <span style="font-weight: normal; font-size: 0.9em;">Toutes catégories</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" style="flex: 0 0 100px;">
                <label class="form-label" for="limitSelect">Affichage</label>
                <select id="limitSelect" class="form-control">
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
        <div id="statsContainer" class="stats-container" style="display: none;">
            <div class="stat-badge">
                <div class="stat-number" id="statTotal">0</div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-badge">
                <div class="stat-number" id="statTodo" style="color: #e67e22;">0</div>
                <div class="stat-label">À faire</div>
            </div>
            <div class="stat-badge">
                <div class="stat-number" id="statDone" style="color: #27ae60;">0</div>
                <div class="stat-label">Terminés</div>
            </div>
        </div>

        <!-- Zone de chargement -->
        <div id="loadingIndicator" style="text-align: center; padding: 40px; display: none;">
            <div style="font-size: 2em;">⏳</div>
            <p style="color: var(--text-muted);">Chargement des messages...</p>
        </div>

        <!-- Liste des messages -->
        <div id="messagesContainer">
            <!-- Les messages seront chargés ici via AJAX -->
        </div>

        <!-- Pagination -->
        <div id="paginationContainer" class="pagination" style="display: none;">
            <!-- La pagination sera générée ici -->
        </div>
        
    <?php endif; ?>
    
<?php endif; ?>

<!-- Modal de réponse -->
<div id="replyMessageModal" class="custom-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Répondre au message</h2>
            <span class="close-modal" id="closeReplyMessageModal">&times;</span>
        </div>
        <div id="replyMessageAlert"></div>
        <div style="margin-bottom: 15px; padding: 10px; background: var(--input-bg); border-radius: 6px; border-left: 3px solid var(--emerald-end);">
            <strong id="replyMessageTitle" style="display: block; margin-bottom: 5px;"></strong>
            <div id="replyMessagePreview" style="font-size: 0.9em; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
        </div>
        <form id="replyMessageForm">
            <input type="hidden" id="replyMessageId" name="message_id">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="replyContent">Votre réponse</label>
                <textarea id="replyContent" name="message" rows="6" required class="form-control" placeholder="Écrivez votre réponse ici..."></textarea>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modern" style="background: transparent; border: 1px solid var(--border-color);" id="cancelReplyMessage">Annuler</button>
                <button type="submit" class="btn-modern btn-primary">Envoyer la réponse</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'ajout de message -->
<div id="addMessageModal" class="custom-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Nouveau message</h2>
            <span class="close-modal" id="closeAddMessageModal">&times;</span>
        </div>
        <div id="addMessageAlert"></div>
        <form id="addMessageForm">
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="messageCategory">Catégorie</label>
                <select id="messageCategory" name="categorie" required class="form-control">
                    <?php if (!empty($selectedCategory) && $selectedCategory !== 'all'): ?>
                        <option value="<?= htmlspecialchars($selectedCategory) ?>" selected><?= htmlspecialchars($selectedCategoryName) ?></option>
                    <?php else: ?>
                        <option value="" selected disabled>-- Choisir une catégorie --</option>
                    <?php endif; ?>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category['ID'] != $selectedCategory): ?>
                            <option value="<?= $category['ID'] ?>"><?= htmlspecialchars($category['CATEGORIE']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Client Search for Message -->
            <div class="form-group">
                <label for="msg_client_search" class="form-label">Client (Facultatif)</label>
                <div style="display: flex; gap: 8px; align-items: flex-start;">
                    <div class="client-search-container" style="flex: 1;">
                        <input type="text" id="msg_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)">
                        <input type="hidden" id="msg_id_client" name="id_client">
                        <div id="msg_client_suggestions" class="client-suggestions" style="position: absolute; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; width: 100%; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-top: 5px;"></div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="messageTitre">Titre</label>
                <input type="text" id="messageTitre" name="titre" required class="form-control" placeholder="Sujet du message">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="messageContent">Message</label>
                <textarea id="messageContent" name="message" rows="6" required class="form-control" placeholder="Détails de votre demande..."></textarea>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modern" style="background: transparent; border: 1px solid var(--border-color);" id="cancelAddMessage">Annuler</button>
                <button type="submit" class="btn-modern btn-primary">Ajouter le message</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmation -->
<div id="confirmationModal" class="custom-modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div style="font-size: 3em; margin-bottom: 15px;">⚠️</div>
        <h3 style="margin-top: 0; color: var(--text-color);">Confirmation</h3>
        <p id="confirmMessage" style="color: var(--text-muted); margin-bottom: 25px;">Êtes-vous sûr ?</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button onclick="closeConfirmModal()" class="btn-modern" style="background: var(--input-bg); border: 1px solid var(--border-color);">Annuler</button>
            <button id="confirmActionBtn" class="btn-modern btn-primary" style="background: #e74c3c;">Confirmer</button>
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
    const closeAddMessageModal = document.getElementById('closeAddMessageModal');
    const cancelAddMessage = document.getElementById('cancelAddMessage');
    const addMessageForm = document.getElementById('addMessageForm');
    
    // Reply Modal
    const replyMessageModal = document.getElementById('replyMessageModal');
    const closeReplyMessageModal = document.getElementById('closeReplyMessageModal');
    const cancelReplyMessage = document.getElementById('cancelReplyMessage');
    const replyMessageForm = document.getElementById('replyMessageForm');
    
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
                document.getElementById('messageTitre').focus();
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
        if (e.target === addMessageModal) closeModal(addMessageModal);
        if (e.target === replyMessageModal) closeModal(replyMessageModal);
        if (e.target === confirmModal) closeConfirmModal();
        
        // Hide suggestions
        const suggestions = document.getElementById('msg_client_suggestions');
        const searchInput = document.getElementById('msg_client_search');
        if (suggestions && e.target !== searchInput && e.target !== suggestions) {
            suggestions.style.display = 'none';
        }
    });
    
    // --- Client Search Logic ---
    const clientSearch = document.getElementById('msg_client_search');
    const clientId = document.getElementById('msg_id_client');
    const suggestions = document.getElementById('msg_client_suggestions');
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
                            div.style.padding = '8px 12px';
                            div.style.cursor = 'pointer';
                            div.style.borderBottom = '1px solid var(--border-color)';
                            div.style.fontSize = '0.9em';
                            div.textContent = client.label;
                            div.onmouseover = function() { this.style.backgroundColor = 'var(--hover-bg)'; };
                            div.onmouseout = function() { this.style.backgroundColor = 'transparent'; };
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
    
    // Add Message Form Submit
    if (addMessageForm) {
        addMessageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';
            
            fetch('actions/helpdesk_messages_add.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal(addMessageModal);
                    this.reset();
                    loadMessages(); 
                } else {
                    document.getElementById('addMessageAlert').innerHTML = 
                        `<div class="alert alert-error">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('addMessageAlert').innerHTML = 
                    `<div class="alert alert-error">Erreur de communication</div>`;
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // Reply Message Modal Listeners
    if (closeReplyMessageModal) {
        closeReplyMessageModal.addEventListener('click', () => closeModal(replyMessageModal));
    }
    
    if (cancelReplyMessage) {
        cancelReplyMessage.addEventListener('click', () => closeModal(replyMessageModal));
    }

    if (replyMessageForm) {
        replyMessageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';
            
            fetch('actions/helpdesk_reponses_add.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal(replyMessageModal);
                    this.reset();
                    loadMessages();
                } else {
                    document.getElementById('replyMessageAlert').innerHTML = 
                        `<div class="alert alert-error">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('replyMessageAlert').innerHTML = 
                    `<div class="alert alert-error">Erreur de communication</div>`;
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // --- Functions ---

    function openModal(modal) {
        modal.style.display = 'flex';
        // Reset alerts
        const alert = modal.querySelector('#addMessageAlert');
        if (alert) alert.innerHTML = '';
    }

    function closeModal(modal) {
        modal.style.display = 'none';
    }

    // Global functions for inline onclick handlers
    window.closeConfirmModal = function() {
        document.getElementById('confirmationModal').style.display = 'none';
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
            btn.style.background = '#e74c3c';
            btn.onclick = executeDelete;
        }
        
        modal.style.display = 'flex';
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
        document.getElementById('replyMessageId').value = id;
        document.getElementById('replyMessageTitle').textContent = title;
        document.getElementById('replyMessagePreview').textContent = messageSnippet;
        
        // Reset form and alerts
        document.getElementById('replyMessageForm').reset();
        document.getElementById('replyMessageAlert').innerHTML = '';
        
        const modal = document.getElementById('replyMessageModal');
        modal.style.display = 'flex';
        
        // Focus textarea
        setTimeout(() => {
            document.getElementById('replyContent').focus();
        }, 100);
    };

    window.toggleStatus = function(id, currentStatus) {
        const newStatus = currentStatus ? 0 : 1;
        
        fetch('actions/helpdesk_messages_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}&fait=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMessages();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur de communication');
        });
    };
    
    window.changePage = function(page) {
        currentPage = page;
        loadMessages();
    };

    function loadMessages() {
        if (!selectedCategory && !searchAllCategories) return;
        
        showLoading(true);
        
        const params = new URLSearchParams({
            page: currentPage,
            limit: currentLimit
        });
        
        if (selectedCategory === 'all') {
             params.append('search_all', 'true');
        } else if (!searchAllCategories) {
            params.append('category', selectedCategory);
        }
        
        if (currentSearch) {
            params.append('search', currentSearch);
        }
        
        if (searchAllCategories && selectedCategory !== 'all') {
            params.append('search_all', 'true');
        }
        
        fetch(`api/search_messages.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMessages(data.data);
                    updateStats(data.stats);
                    updatePagination(data.pagination);
                } else {
                    showError('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showError('Erreur de communication avec le serveur');
            })
            .finally(() => {
                showLoading(false);
            });
    }
    
    function showLoading(show) {
        const loadingIndicator = document.getElementById('loadingIndicator');
        const statsContainer = document.getElementById('statsContainer');
        const messagesContainer = document.getElementById('messagesContainer');
        const paginationContainer = document.getElementById('paginationContainer');
        
        if (show) {
            loadingIndicator.style.display = 'block';
            statsContainer.style.display = 'none';
            messagesContainer.innerHTML = '';
            paginationContainer.style.display = 'none';
        } else {
            loadingIndicator.style.display = 'none';
        }
    }
    
    function showError(msg) {
        const container = document.getElementById('messagesContainer');
        container.innerHTML = `<div class="alert alert-error">${msg}</div>`;
    }
    
    function displayMessages(messages) {
        const container = document.getElementById('messagesContainer');
        
        if (messages.length === 0) {
            container.innerHTML = `
                <div class="controls-card" style="text-align: center; padding: 40px;">
                    <div style="font-size: 3em; margin-bottom: 10px; opacity: 0.5;">📭</div>
                    <p style="color: var(--text-muted);">Aucun message trouvé.</p>
                </div>`;
            return;
        }
        
        container.innerHTML = messages.map(message => {
            const messageDate = new Date(message.DATE);
            const isDone = message.FAIT == 1;
            
            const dateFaitHtml = isDone && message.DATE_FAIT ?
                `<span style="color: #27ae60; margin-left: 10px;">✓ Terminé le ${new Date(message.DATE_FAIT).toLocaleString('fr-FR')}</span>` : '';
            
            const categoryHtml = searchAllCategories && message.CATEGORIE_NOM ?
                `<div style="font-size: 0.85em; color: ${message.CATEGORIE_COULEUR || 'var(--emerald-end)'}; margin-bottom: 8px; font-weight: 600;">
                    📁 ${escapeHtml(message.CATEGORIE_NOM)}
                 </div>` : '';
            
            const repliesHtml = message.REPLIES && message.REPLIES.length > 0 ? 
                `<div class="replies-container">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.9em; color: var(--text-muted);">Réponses (${message.REPLIES.length})</h4>
                    ${message.REPLIES.map(reply => `
                        <div class="reply-card">
                            <div class="reply-meta">
                                <span>📅 ${new Date(reply.DATE_REPONSE).toLocaleString('fr-FR')}</span>
                            </div>
                            <div class="reply-content">${escapeHtml(reply.MESSAGE).replace(/\n/g, '<br>')}</div>
                        </div>
                    `).join('')}
                </div>` : '';

            return `
                <div class="message-card ${isDone ? 'done' : ''}" data-id="${message.ID}">
                    ${categoryHtml}
                    <div class="message-header">
                        <h3 class="message-title">${escapeHtml(message.TITRE)}</h3>
                        <div class="message-meta">
                            <span>📅 ${messageDate.toLocaleString('fr-FR')}</span>
                            ${dateFaitHtml}
                        </div>
                    </div>
                    <div class="message-content">${escapeHtml(message.MESSAGE).replace(/\n/g, '<br>')}</div>
                    
                    ${repliesHtml}

                    <div class="message-actions">
                        <button class="btn-modern" style="background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-color);"
                                onclick="openReplyModal(${message.ID}, '${escapeHtml(message.TITRE).replace(/'/g, "\\'")}', '${escapeHtml(message.MESSAGE).substring(0, 50).replace(/'/g, "\\'").replace(/\n/g, ' ')}...')">
                            ↩️ Répondre
                        </button>
                        <button class="btn-modern ${isDone ? 'btn-status-done' : 'btn-status-todo'}"
                                onclick="toggleStatus(${message.ID}, ${message.FAIT})">
                            ${isDone ? '✅ Marquer comme à faire' : '⏳ Marquer comme fait'}
                        </button>
                        <button class="btn-delete-icon" title="Supprimer"
                                onclick="confirmAction('delete', ${message.ID}, ${message.CATEGORIE})">
                            &times;
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    function updateStats(stats) {
        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statTodo').textContent = stats.todo || 0;
        document.getElementById('statDone').textContent = stats.done || 0;
        document.getElementById('statsContainer').style.display = 'flex';
    }
    
    function updatePagination(pagination) {
        const container = document.getElementById('paginationContainer');
        
        if (pagination.total_pages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        let html = '';
        
        // Prev
        html += `<button class="page-btn" ${!pagination.has_prev ? 'disabled' : ''} 
                 onclick="changePage(${pagination.current_page - 1})">«</button>`;
        
        // Pages
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        if (startPage > 1) {
            html += `<button class="page-btn" onclick="changePage(1)">1</button>`;
            if (startPage > 2) html += `<span style="padding: 0 5px;">...</span>`;
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="page-btn ${i === pagination.current_page ? 'active' : ''}" 
                     onclick="changePage(${i})">${i}</button>`;
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) html += `<span style="padding: 0 5px;">...</span>`;
            html += `<button class="page-btn" onclick="changePage(${pagination.total_pages})">${pagination.total_pages}</button>`;
        }
        
        // Next
        html += `<button class="page-btn" ${!pagination.has_next ? 'disabled' : ''} 
                 onclick="changePage(${pagination.current_page + 1})">»</button>`;
        
        container.innerHTML = html;
        container.style.display = 'flex';
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Client Search Logic
    let messageSearchInitialized = false;
    function initMessageClientSearch() {
        if (messageSearchInitialized) return;
        
        const clientSearch = document.getElementById('msg_client_search');
        const clientId = document.getElementById('msg_id_client');
        const suggestions = document.getElementById('msg_client_suggestions');
        let searchTimeout;
        
        if (clientSearch) {
            clientSearch.addEventListener('input', function() {
                const term = this.value;
                clearTimeout(searchTimeout);
                
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
                                div.style.padding = '8px 12px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid var(--border-color)';
                                div.onmouseover = function() { this.style.backgroundColor = 'var(--input-bg)'; };
                                div.onmouseout = function() { this.style.backgroundColor = 'transparent'; };
                                
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
            
            messageSearchInitialized = true;
        }
    }
});
</script>