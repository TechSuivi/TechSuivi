<!-- Modal d'ajout de client -->
<div id="addClientModal" class="modal-overlay" style="display: none; z-index: 2500;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Ajouter un nouveau client V4</h2>
            <span class="modal-close" onclick="closeAddClientModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="addClientAlerts"></div>
            <form id="addClientForm">
                <!-- Nom et Prénom sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_add_nom">Nom *</label>
                        <input type="text" id="client_add_nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="client_add_prenom">Prénom</label>
                        <input type="text" id="client_add_prenom" name="prenom" class="form-control">
                    </div>
                </div>
                
                <!-- Email sur toute la largeur -->
                <div class="form-group">
                    <label for="client_add_mail">Email</label>
                    <input type="email" id="client_add_mail" name="mail" class="form-control">
                </div>
                
                <!-- Adresse 1 sur toute la largeur -->
                <div class="form-group">
                    <label for="client_add_adresse1">Adresse 1</label>
                    <input type="text" id="client_add_adresse1" name="adresse1" class="form-control" data-minchars="3" data-autofirst>
                </div>
                
                <!-- Adresse 2 sur toute la largeur -->
                <div class="form-group">
                    <label for="client_add_adresse2">Adresse 2 (complément)</label>
                    <input type="text" id="client_add_adresse2" name="adresse2" class="form-control">
                </div>
                
                <!-- Code Postal et Ville sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_add_cp">Code Postal</label>
                        <input type="text" id="client_add_cp" name="cp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="client_add_ville">Ville</label>
                        <input type="text" id="client_add_ville" name="ville" class="form-control">
                    </div>
                </div>
                
                <!-- Téléphone et Portable sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_add_telephone">Téléphone</label>
                        <input type="tel" id="client_add_telephone" name="telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="client_add_portable">Portable</label>
                        <input type="tel" id="client_add_portable" name="portable" class="form-control">
                    </div>
                </div>
            </form>
            
            <!-- Section de vérification des doublons -->
            <div id="duplicateCheckSection" style="display: none; margin-top: 15px; padding: 15px; background: var(--hover-bg); border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0;">⚠️ Doublons potentiels :</h4>
                <div id="duplicatesContainer" style="max-height: 150px; overflow-y: auto;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddClientModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitAddClientForm()">
                <span>✓</span>
                Ajouter le client
            </button>
        </div>
    </div>
</div>

<style>
/* CSS Spécifique pour rendre l'autocomplétion visible dans la modal (Mode Sombre) */
#addClientModal .awesomplete > ul {
    z-index: 10000; /* Au-dessus de la modal (2500) */
    color: #333;    /* Texte noir sur fond blanc, même si le site est sombre */
}
</style>

<script>
// ===== LOGIQUE PARTAGÉE MODAL CLIENT =====
// Cette logique est désormais centralisée ici pour éviter la duplication.

(function() {
    // Variable pour savoir qui a appelé la modal
    var nestedClientSource = 'intervention';

    // Fonction globale d'ouverture
    window.openAddClientModal = function(source) {
        if (!source) source = 'intervention';
        console.log("Opening Add Client Modal (Shared)", source);
        nestedClientSource = source;
        
        var modal = document.getElementById('addClientModal');
        if (modal) {
            modal.style.display = 'flex';
            
            // Reset form and alerts
            var form = document.getElementById('addClientForm');
            if (form) form.reset();
            
            var alerts = document.getElementById('addClientAlerts');
            if (alerts) alerts.innerHTML = '';
            
            // Reset duplicate section
            var dupSection = document.getElementById('duplicateCheckSection');
            var dupContainer = document.getElementById('duplicatesContainer');
            if (dupSection) dupSection.style.display = 'none';
            if (dupContainer) dupContainer.innerHTML = '';
            
            // Focus on first field
            setTimeout(function() {
                var input = document.getElementById('client_add_nom');
                if (input) input.focus();
            }, 100);
        } else {
            console.error("addClientModal not found");
            alert("Erreur: La fenêtre 'Ajout Client' est introuvable.");
        }
    };
    
    // Alias pour compatibilité
    window.openNestedClientModal = window.openAddClientModal;

    // Fonction globale de fermeture
    window.closeAddClientModal = function() {
        var modal = document.getElementById('addClientModal');
        if (modal) modal.style.display = 'none';
    };

    // Fonction globale de soumission
    window.submitAddClientForm = function() {
        var form = document.getElementById('addClientForm');
        var alertsDiv = document.getElementById('addClientAlerts');
        if (!form || !alertsDiv) return;
        
        var formData = new FormData(form);
        alertsDiv.innerHTML = '';
        
        if (!formData.get('nom')) {
            alertsDiv.innerHTML = '<div class="alert alert-error">Le nom est obligatoire.</div>';
            return;
        }
        
        var submitBtn = form.querySelector('button[onclick="submitAddClientForm()"]');
        if(submitBtn) submitBtn.disabled = true;

        fetch('actions/client_add.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                alertsDiv.innerHTML = '<div class="alert alert-success">Client créé avec succès !</div>';
                
                // --- LOGIQUE DE CALLBACK SELON LA SOURCE ---
                if (nestedClientSource === 'agenda') {
                     var idInput = document.getElementById('agenda_id_client');
                     var searchInput = document.getElementById('agenda_client_search');
                     if(idInput) idInput.value = data.client_id;
                     if(searchInput) searchInput.value = (formData.get('nom') + ' ' + (formData.get('prenom') || '')).trim();
                } 
                else if (nestedClientSource === 'message') {
                     var idInput = document.getElementById('msg_add_id_client');
                     var searchInput = document.getElementById('msg_add_client_search');
                     if(idInput) idInput.value = data.client_id;
                     if(searchInput) searchInput.value = (formData.get('nom') + ' ' + (formData.get('prenom') || '')).trim();
                } 
                else if (nestedClientSource === 'intervention') {
                     var idInput = document.getElementById('interv_id_client');
                     var searchInput = document.getElementById('interv_client_search');
                     if(idInput) idInput.value = data.client_id;
                     if(searchInput) searchInput.value = (formData.get('nom') + ' ' + (formData.get('prenom') || '')).trim();
                }
                
                setTimeout(function() {
                    window.closeAddClientModal();
                    if(submitBtn) submitBtn.disabled = false;
                }, 1000);
            } else {
                var errorMsg = data.message || data.error || 'Erreur inconnue';
                if (data.errors && Array.isArray(data.errors)) {
                    errorMsg = data.errors.join('<br>');
                }
                alertsDiv.innerHTML = '<div class="alert alert-error">' + errorMsg + '</div>';
                if(submitBtn) submitBtn.disabled = false;
            }
        })
        .catch(function(error) {
            console.error(error);
            alertsDiv.innerHTML = '<div class="alert alert-error">Erreur de communication avec le serveur</div>';
            if(submitBtn) submitBtn.disabled = false;
        });
    };

    // Initialisation Autocomplétion et Doublons au chargement
    // On utilise un setTimeout pour s'assurer que le DOM est prêt même si le script est exécuté immédiatement
    setTimeout(function() {
        var adresseInput = document.getElementById('client_add_adresse1');
        
        // Autocomplétion Adresse (Awesomplete)
        if (adresseInput && window.Awesomplete && !adresseInput.classList.contains('awesomplete-processed')) {
            adresseInput.classList.add('awesomplete-processed');
            var awesomplete = new Awesomplete(adresseInput, { minChars: 3, maxItems: 10, autoFirst: true });
            var addressesData = {};
            
            adresseInput.addEventListener('input', function() {
                var query = this.value;
                if (query.length >= 3) {
                    fetch('api/get_addresses.php?q=' + encodeURIComponent(query))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data && data.features) {
                            var list = [];
                            addressesData = {};
                            data.features.forEach(function(f) {
                                list.push(f.properties.label);
                                addressesData[f.properties.label] = f.properties;
                            });
                            awesomplete.list = list;
                        }
                    });
                }
            });
            
            adresseInput.addEventListener('awesomplete-selectcomplete', function(e) {
                var data = addressesData[e.text.value];
                if (data) {
                    if (data.postcode) document.getElementById('client_add_cp').value = data.postcode;
                    if (data.city) document.getElementById('client_add_ville').value = data.city;
                }
            });
        }
        
        // Vérification Doublons & Format Téléphone
        var clientTel = document.getElementById('client_add_telephone');
        var clientPortable = document.getElementById('client_add_portable');
        var clientNom = document.getElementById('client_add_nom');
        var clientPrenom = document.getElementById('client_add_prenom');
        
        function formatPhone(input) {
            var val = input.value.replace(/\D/g, '');
            var formatted = '';
            for(var i=0; i<val.length && i<10; i++) {
                if(i>0 && i%2===0) formatted += ' ';
                formatted += val[i];
            }
            input.value = formatted;
        }
        
        var checkTimeout;
        function checkDuplicates() {
            var nom = clientNom ? clientNom.value.trim() : '';
            var prenom = clientPrenom ? clientPrenom.value.trim() : '';
            var telephone = clientTel ? clientTel.value.replace(/\s/g,'') : '';
            var portable = clientPortable ? clientPortable.value.replace(/\s/g,'') : '';
            
            if (!nom && !telephone && !portable) {
                var section = document.getElementById('duplicateCheckSection');
                if (section) section.style.display = 'none';
                return;
            }
            
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(function() {
                // Noter l'usage de 'utils/' si on est à la racine, mais attention aux sous-dossiers.
                // On suppose ici que 'utils/' est accessible depuis la page qui inclut ce fichier.
                fetch('utils/check_duplicate_client.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nom: nom, prenom: prenom, telephone: telephone, portable: portable })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    var section = document.getElementById('duplicateCheckSection');
                    var container = document.getElementById('duplicatesContainer');
                    
                    if (data.duplicates && data.duplicates.length > 0) {
                        var html = '';
                        data.duplicates.forEach(function(dup) {
                            html += '<div style="padding: 8px; border-bottom: 1px solid var(--border-color); font-size: 0.9em;">' +
                                '<strong>' + dup.nom + ' ' + (dup.prenom || '') + '</strong><br>' +
                                (dup.telephone ? 'Tel: ' + dup.telephone : '') + ' ' + (dup.portable ? 'Port: ' + dup.portable : '') +
                            '</div>';
                        });
                        if (container) container.innerHTML = html;
                        if (section) section.style.display = 'block';
                    } else {
                        if (section) section.style.display = 'none';
                    }
                });
            }, 500);
        }
        
        if(clientTel) clientTel.addEventListener('input', function() { formatPhone(this); checkDuplicates(); });
        if(clientPortable) clientPortable.addEventListener('input', function() { formatPhone(this); checkDuplicates(); });
        if(clientNom) clientNom.addEventListener('input', checkDuplicates);
        if(clientPrenom) clientPrenom.addEventListener('input', checkDuplicates);
        
    }, 500); // Délai de sécurité pour être sûr que le HTML est rendu
})();
</script>
