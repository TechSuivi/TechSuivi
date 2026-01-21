<!-- Modal Ajout Agenda -->
<div id="addAgendaModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">📅 Nouvel événement V3</h2>
            <span class="modal-close" onclick="closeAddAgendaModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div id="agendaAlerts"></div>
            
            <form id="addAgendaForm">
                <!-- Client Search -->
                <div class="form-group">
                    <label for="agenda_client_search">Client (Facultatif)</label>
                    <div style="display: flex; gap: 8px; align-items: flex-start;">
                        <div class="client-search-container" style="flex: 1;">
                            <input type="text" id="agenda_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)">
                            <input type="hidden" id="agenda_id_client" name="id_client">
                            <div id="agenda_client_suggestions" class="client-suggestions"></div>
                        </div>
                        <!-- Note: Inline style for button kept for specific gradient, or could be replaced with .btn-client class if compatible -->
                        <button type="button" class="btn" onclick="openAddClientModal('agenda')" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; border: none; padding: 0 20px; white-space: nowrap; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; font-weight: 600; cursor: pointer;" title="Créer un nouveau client">
                            <span>➕</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="agenda_titre">Titre de l'événement *</label>
                    <input type="text" id="agenda_titre" name="titre" class="form-control" required placeholder="Ex: Réunion client...">
                </div>

                <div class="form-group">
                    <label for="agenda_desc">Description</label>
                    <textarea id="agenda_desc" name="description" class="form-control" rows="3" placeholder="Détails..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="agenda_date">Date et Heure *</label>
                        <input type="datetime-local" id="agenda_date" name="date_planifiee" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="agenda_rappel">Rappel (minutes)</label>
                        <input type="number" id="agenda_rappel" name="rappel_minutes" class="form-control" value="0" min="0">
                        <small class="form-hint" style="color: var(--text-muted); font-size: 0.8em;">0 = Aucun rappel</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="agenda_priorite">Priorité</label>
                        <select id="agenda_priorite" name="priorite" class="form-control">
                            <option value="basse">🟢 Basse</option>
                            <option value="normale" selected>🔵 Normale</option>
                            <option value="haute">🟠 Haute</option>
                            <option value="urgente">🔴 Urgente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="agenda_couleur">Couleur</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="color" id="agenda_couleur" name="couleur" value="#3498db" class="form-control" style="width: 50px; padding: 2px; height: 38px;">
                            <!-- Presets -->
                            <div class="priority-color" style="background:#3498db; width:25px; height:25px; border-radius:50%; cursor:pointer;" onclick="setAgendaColor('#3498db')"></div>
                            <div class="priority-color" style="background:#e74c3c; width:25px; height:25px; border-radius:50%; cursor:pointer;" onclick="setAgendaColor('#e74c3c')"></div>
                            <div class="priority-color" style="background:#2ecc71; width:25px; height:25px; border-radius:50%; cursor:pointer;" onclick="setAgendaColor('#2ecc71')"></div>
                            <div class="priority-color" style="background:#f39c12; width:25px; height:25px; border-radius:50%; cursor:pointer;" onclick="setAgendaColor('#f39c12')"></div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="statut" value="planifie">
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddAgendaModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitAddAgendaForm()">Enregistrer</button>
        </div>
    </div>
</div>
