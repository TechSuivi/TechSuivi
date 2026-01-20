<!-- Modal Visualisation Agenda -->
<div id="viewAgendaModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="viewAgendaTitle">📅 Titre de l'événement</h3>
            <span class="modal-close" onclick="closeViewAgendaModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="flex flex-wrap gap-10 mb-15">
                <span id="viewAgendaDate" class="badge">Date</span>
                <span id="viewAgendaStatus" class="badge">Statut</span>
                <span id="viewAgendaPriority" class="badge">Priorité</span>
            </div>
            
            <div id="viewAgendaDescription" class="p-15 rounded border border-border bg-hover/10 whitespace-pre-wrap leading-relaxed text-sm">
                Description...
            </div>
        </div>
        <div class="modal-footer">
            <!-- Edit link hidden by default -->
            <a id="viewAgendaEditLink" href="#" class="btn btn-primary" style="display: none;">Modifier</a>
            
            <button type="button" class="btn btn-secondary" onclick="closeViewAgendaModal()">Fermer</button>
        </div>
    </div>
</div>
