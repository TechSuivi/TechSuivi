<!-- Modal d'ajout de message (Shared) -->
<div id="addMessageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Nouveau message V3</h2>
            <span id="closeAddMessageModal" class="modal-close">&times;</span>
        </div>
        <div id="msg_add_alert"></div>
        <form id="msg_add_form">
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="msg_add_category">Catégorie</label>
                <select id="msg_add_category" name="categorie" required class="form-control">
                    <option value="" selected disabled>-- Choisir une catégorie --</option>
                    <!-- Options populated by PHP or JS -->
                    <?php if (isset($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['ID'] ?>"><?= htmlspecialchars($category['CATEGORIE']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Client Search for Message -->
            <div class="form-group">
                <label for="msg_add_client_search" class="form-label">Client (Facultatif)</label>
                <div style="display: flex; gap: 8px; align-items: flex-start;">
                    <div class="client-search-container" style="flex: 1; position: relative;">
                        <input type="text" id="msg_add_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)">
                        <input type="hidden" id="msg_add_id_client" name="id_client">
                        <div id="msg_add_client_suggestions" class="client-suggestions" style="position: absolute; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; width: 100%; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-top: 5px;"></div>
                    </div>
                     <button type="button" class="btn-modern btn-primary" style="padding: 10px;" onclick="openNestedClientModal('message')" title="Nouveau Client">
                        <span>➕</span>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="msg_add_titre">Titre</label>
                <input type="text" id="msg_add_titre" name="titre" required class="form-control" placeholder="Sujet du message">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="msg_add_content">Message</label>
                <textarea id="msg_add_content" name="message" rows="6" required class="form-control" placeholder="Détails de votre demande..."></textarea>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeAddMessageModal()">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="submitAddMessageForm()">Ajouter le message</button>
            </div>
        </form>
    </div>
</div>
