<!-- Modal d'édition de client -->
<div id="editClientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content shadow-sm" style="max-width: 800px; width: 90%;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Modifier le client</h3>
            <span class="modal-close" onclick="closeEditClientModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="editClientAlerts"></div>
            <form id="editClientForm">
                <input type="hidden" id="client_edit_id" name="id">
                
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="client_edit_nom" class="form-label">Nom *</label>
                        <input type="text" id="client_edit_nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group flex-1">
                        <label for="client_edit_prenom" class="form-label">Prénom</label>
                        <input type="text" id="client_edit_prenom" name="prenom" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="client_edit_mail" class="form-label">Email</label>
                    <input type="email" id="client_edit_mail" name="mail" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="client_edit_adresse1" class="form-label">Adresse 1</label>
                    <input type="text" id="client_edit_adresse1" name="adresse1" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="client_edit_adresse2" class="form-label">Adresse 2 (complément)</label>
                    <input type="text" id="client_edit_adresse2" name="adresse2" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="client_edit_cp" class="form-label">Code Postal</label>
                        <input type="text" id="client_edit_cp" name="cp" class="form-control">
                    </div>
                    <div class="form-group flex-1">
                        <label for="client_edit_ville" class="form-label">Ville</label>
                        <input type="text" id="client_edit_ville" name="ville" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="client_edit_telephone" class="form-label">Téléphone</label>
                        <input type="tel" id="client_edit_telephone" name="telephone" class="form-control">
                    </div>
                    <div class="form-group flex-1">
                        <label for="client_edit_portable" class="form-label">Portable</label>
                        <input type="tel" id="client_edit_portable" name="portable" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label for="client_edit_commentaire" class="form-label">Commentaire</label>
                    <textarea id="client_edit_commentaire" name="commentaire" class="form-control" rows="4" style="resize: vertical;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditClientModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitEditClientForm()">Enregistrer les modifications</button>
        </div>
    </div>
</div>
