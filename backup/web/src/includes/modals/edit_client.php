<!-- Modal d'édition de client -->
<div id="editClientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Modifier le client V2</h2>
            <span class="modal-close" onclick="closeEditClientModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="editClientAlerts"></div>
            <form id="editClientForm">
                <input type="hidden" id="client_edit_id" name="id">
                
                <!-- Nom et Prénom sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_edit_nom">Nom *</label>
                        <input type="text" id="client_edit_nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="client_edit_prenom">Prénom</label>
                        <input type="text" id="client_edit_prenom" name="prenom" class="form-control">
                    </div>
                </div>
                
                <!-- Email sur toute la largeur -->
                <div class="form-group">
                    <label for="client_edit_mail">Email</label>
                    <input type="email" id="client_edit_mail" name="mail" class="form-control">
                </div>
                
                <!-- Adresse 1 sur toute la largeur -->
                <div class="form-group">
                    <label for="client_edit_adresse1">Adresse 1</label>
                    <input type="text" id="client_edit_adresse1" name="adresse1" class="form-control">
                </div>
                
                <!-- Adresse 2 sur toute la largeur -->
                <div class="form-group">
                    <label for="client_edit_adresse2">Adresse 2 (complément)</label>
                    <input type="text" id="client_edit_adresse2" name="adresse2" class="form-control">
                </div>
                
                <!-- Code Postal et Ville sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_edit_cp">Code Postal</label>
                        <input type="text" id="client_edit_cp" name="cp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="client_edit_ville">Ville</label>
                        <input type="text" id="client_edit_ville" name="ville" class="form-control">
                    </div>
                </div>
                
                <!-- Téléphone et Portable sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_edit_telephone">Téléphone</label>
                        <input type="tel" id="client_edit_telephone" name="telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="client_edit_portable">Portable</label>
                        <input type="tel" id="client_edit_portable" name="portable" class="form-control">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditClientModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitEditClientForm()" style="background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);">
                <span>✓</span>
                Enregistrer
            </button>
        </div>
    </div>
</div>
