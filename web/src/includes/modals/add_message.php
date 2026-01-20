<!-- Modal d'ajout de message (Shared) -->
<div id="addMessageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">💬 Nouveau message</h2>
            <span id="closeAddMessageModal" class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="msg_add_alert"></div>
            <form id="msg_add_form">
                <div class="form-group">
                    <label class="form-label" for="msg_add_category">Catégorie</label>
                    <select id="msg_add_category" name="categorie" required class="form-control">
                        <option value="" selected disabled>-- Choisir une catégorie --</option>
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
                    <div class="flex gap-10">
                        <div class="client-search-container flex-1">
                            <input type="text" id="msg_add_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)">
                            <input type="hidden" id="msg_add_id_client" name="id_client">
                            <div id="msg_add_client_suggestions" class="client-suggestions"></div>
                        </div>
                         <button type="button" class="btn btn-primary" style="padding: 0 15px;" onclick="openNestedClientModal('message')" title="Nouveau Client">
                            <span>➕</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="msg_add_titre">Titre</label>
                    <input type="text" id="msg_add_titre" name="titre" required class="form-control" placeholder="Sujet du message">
                </div>
                <div class="form-group">
                    <label class="form-label" for="msg_add_content">Message</label>
                    <textarea id="msg_add_content" name="message" rows="5" required class="form-control" placeholder="Détails de votre demande..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddMessageModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitAddMessageForm()">Ajouter le message</button>
        </div>
    </div>
</div>
