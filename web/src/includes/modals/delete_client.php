<!-- Modal de confirmation de suppression -->
<div id="deleteClientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content modal-sm shadow-sm">
        <div class="modal-header">
            <h3 class="modal-title">🗑️ Supprimer le client</h3>
            <span class="modal-close" onclick="closeDeleteClientModal()">&times;</span>
        </div>
        <div class="modal-body text-center" style="padding: 30px;">
            <div id="deleteClientAlerts"></div>
            <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
            <p class="text-md mb-10">
                Êtes-vous sûr de vouloir supprimer le client :
            </p>
            <strong id="client_delete_name" class="block text-lg text-danger mb-15"></strong>
            
            <input type="hidden" id="client_delete_id" name="id">
            <p class="text-xs text-muted">
                Cette action est irréversible et supprimera toutes les données associées.
            </p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteClientModal()">Annuler</button>
            <button type="button" class="btn btn-danger" onclick="executeDeleteClient()">Supprimer définitivement</button>
        </div>
    </div>
</div>
