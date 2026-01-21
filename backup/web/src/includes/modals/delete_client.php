<!-- Modal de confirmation de suppression -->
<div id="deleteClientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <h2>🗑️ Confirmer la suppression V2</h2>
            <span class="modal-close" onclick="closeDeleteClientModal()">&times;</span>
        </div>
        <div class="modal-body" style="text-align: center; padding: 30px;">
            <div id="deleteClientAlerts"></div>
            <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
            <p style="font-size: 1.1em; margin-bottom: 10px;">
                Êtes-vous sûr de vouloir supprimer le client
            </p>
            <p id="client_delete_name" style="font-size: 1.3em; font-weight: bold; color: #e74c3c; margin-bottom: 15px;">
                <!-- Nom du client injecté par JS -->
            </p>
            <input type="hidden" id="client_delete_id" name="id">
            <p style="color: var(--text-muted); font-size: 0.9em;">
                Cette action est irréversible.
            </p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteClientModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="executeDeleteClient()" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                <span>🗑️</span>
                Supprimer
            </button>
        </div>
    </div>
</div>
