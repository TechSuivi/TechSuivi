<!-- Modal Visualisation Message -->
<div id="viewMessageModal" class="modal-overlay fixed inset-0 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content max-w-600">
        <div class="modal-header">
            <h3 class="modal-title" id="viewMessageTitle">Titre du message V2</h3>
            <span class="close-modal text-2xl cursor-pointer" onclick="closeViewMessageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="mb-20">
                <div class="flex flex-between-center text-muted text-sm mb-15">
                    <span id="viewMessageDate">Content Date</span>
                    <span id="viewMessageCategory" class="badge badge-green px-6 py-2 rounded-sm bg-gray-200">Catégorie</span>
                </div>
                <div id="viewMessageContent" class="whitespace-pre-wrap leading-relaxed text-dark bg-input p-15 rounded border border-border">
                    Contenu du message...
                </div>
            </div>
            
            <div id="viewMessageReplies" class="mt-20 pt-20 border-t border-border hidden">
                <h4 class="text-lg mb-15">Réponses</h4>
                <div id="viewMessageRepliesList"></div>
            </div>

            <div class="text-right mt-20 flex justify-end gap-10">
                <!-- Actions hidden by default, shown by specific page JS if needed -->
                <button id="viewMessageDeleteBtn" class="btn btn-secondary bg-danger text-white border-none hidden">Supprimer</button>
                <button id="viewMessageToggleBtn" class="btn btn-primary hidden"></button>
                
                <button type="button" class="btn btn-secondary" onclick="closeViewMessageModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>
