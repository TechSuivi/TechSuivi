<!-- Modal modification rapide commentaire -->
<div id="editCommentModal" class="modal-overlay" style="display: none;">
    <div class="modal-content shadow-sm" style="max-width: 600px; width: 90%;">
        <div class="modal-header">
            <h3 class="modal-title">📝 Modifier le commentaire</h3>
            <span class="modal-close" onclick="closeEditCommentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="editCommentAlerts"></div>
            <form id="editCommentForm">
                <input type="hidden" id="comment_client_id" name="id">
                <div class="form-group">
                    <label for="comment_text" class="form-label">Note / Commentaire</label>
                    <textarea id="comment_text" name="commentaire" class="form-control" rows="8" style="resize: vertical;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditCommentModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitEditCommentForm()">💾 Enregistrer</button>
        </div>
    </div>
</div>

<script>
function openEditCommentModal(clientId, currentComment) {
    document.getElementById('comment_client_id').value = clientId;
    document.getElementById('comment_text').value = currentComment;
    document.getElementById('editCommentModal').style.display = 'flex';
    document.getElementById('editCommentAlerts').innerHTML = '';
    setTimeout(() => document.getElementById('comment_text').focus(), 100);
}

function closeEditCommentModal() {
    document.getElementById('editCommentModal').style.display = 'none';
}

function submitEditCommentForm() {
    const form = document.getElementById('editCommentForm');
    const alertsDiv = document.getElementById('editCommentAlerts');
    const formData = new FormData(form);
    
    fetch('actions/client_update_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert-modal success"><span>✅</span> <div>${data.message}</div></div>`;
            setTimeout(() => window.location.reload(), 800);
        } else {
            alertsDiv.innerHTML = `<div class="alert-modal error"><span>⚠️</span> <div>${data.error}</div></div>`;
        }
    })
    .catch(error => {
        alertsDiv.innerHTML = `<div class="alert-modal error"><span>⚠️</span> <div>Erreur de communication.</div></div>`;
    });
}
</script>
