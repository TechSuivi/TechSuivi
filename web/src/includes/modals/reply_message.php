<!-- Modal de réponse (Shared) -->
<div id="replyMessageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">✉️ Répondre au message</h2>
            <span class="modal-close" onclick="closeReplyMessageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="msg_reply_alert"></div>
            <div class="p-10 mb-15 rounded border-l-3 border-success bg-hover/10">
                <strong id="msg_reply_title" class="block mb-5 text-sm"></strong>
                <div id="msg_reply_preview" class="text-xs text-muted truncate"></div>
            </div>
            <form id="msg_reply_form">
                <input type="hidden" id="msg_reply_id" name="message_id">
                <div class="form-group">
                    <label class="form-label" for="msg_reply_content">Votre réponse</label>
                    <textarea id="msg_reply_content" name="message" rows="5" required class="form-control" placeholder="Écrivez votre réponse ici..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeReplyMessageModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitReplyMessageForm()">Envoyer la réponse</button>
        </div>
    </div>
</div>
