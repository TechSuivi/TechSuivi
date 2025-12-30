<!-- Modal Visualisation Message -->
<div id="viewMessageModal" class="modal-overlay" style="display: none; z-index: 1000;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="viewMessageTitle">Titre du message V2</h3>
            <span class="close-modal" onclick="closeViewMessageModal()" style="font-size: 24px; cursor: pointer;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">
                    <span id="viewMessageDate">Content Date</span>
                    <span id="viewMessageCategory" class="badge badge-green" style="background:#ddd; padding:2px 6px; border-radius:4px;">Catégorie</span>
                </div>
                <div id="viewMessageContent" style="white-space: pre-wrap; line-height: 1.6; color: var(--text-color); background: var(--input-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    Contenu du message...
                </div>
            </div>
            
            <div id="viewMessageReplies" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); display: none;">
                <h4 style="font-size: 1.1em; margin-bottom: 15px;">Réponses</h4>
                <div id="viewMessageRepliesList"></div>
            </div>

            <div style="text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <!-- Actions hidden by default, shown by specific page JS if needed -->
                <button id="viewMessageDeleteBtn" class="btn btn-secondary" style="background-color: #e74c3c; color: white; border: none; display: none;">Supprimer</button>
                <button id="viewMessageToggleBtn" class="btn btn-primary" style="display: none;"></button>
                
                <button type="button" class="btn btn-secondary" onclick="closeViewMessageModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>
