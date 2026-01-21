<!-- Modal Visualisation Agenda -->
<div id="viewAgendaModal" class="modal-overlay" style="display: none; z-index: 1000;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);">
            <h3 class="modal-title" id="viewAgendaTitle" style="color: white; margin: 0;">Titre de l'événement V2</h3>
            <span class="close-modal" onclick="closeViewAgendaModal()" style="font-size: 24px; cursor: pointer; color: white;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                    <span id="viewAgendaDate" class="badge badge-agenda">Date</span>
                    <span id="viewAgendaStatus" class="badge badge-agenda">Statut</span>
                    <span id="viewAgendaPriority" class="badge badge-agenda">Priorité</span>
                </div>
                <div id="viewAgendaDescription" style="white-space: pre-wrap; line-height: 1.6; color: var(--text-color); background: var(--input-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    Description...
                </div>
            </div>
            <div style="text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <!-- Edit link hidden by default -->
                <a id="viewAgendaEditLink" href="#" class="btn btn-primary" style="display: none;">Modifier</a>
                
                <button type="button" class="btn btn-secondary" onclick="closeViewAgendaModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>
