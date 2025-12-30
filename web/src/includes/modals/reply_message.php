<!-- Modal de réponse (Shared) -->
<div id="replyMessageModal" class="custom-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Répondre au message V2</h2>
            <span class="close-modal" onclick="closeReplyMessageModal()">&times;</span>
        </div>
        <div id="msg_reply_alert"></div>
        <div style="margin-bottom: 15px; padding: 10px; background: var(--input-bg); border-radius: 6px; border-left: 3px solid var(--emerald-end);">
            <strong id="msg_reply_title" style="display: block; margin-bottom: 5px;"></strong>
            <div id="msg_reply_preview" style="font-size: 0.9em; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
        </div>
        <form id="msg_reply_form">
            <input type="hidden" id="msg_reply_id" name="message_id">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="msg_reply_content">Votre réponse</label>
                <textarea id="msg_reply_content" name="message" rows="6" required class="form-control" placeholder="Écrivez votre réponse ici..."></textarea>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modern" style="background: transparent; border: 1px solid var(--border-color);" onclick="closeReplyMessageModal()">Annuler</button>
                <button type="button" class="btn-modern btn-primary" onclick="submitReplyMessageForm()">Envoyer la réponse</button>
            </div>
        </form>
    </div>
</div>
