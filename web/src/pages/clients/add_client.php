<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$message = ''; // Pour les messages de succès ou d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    // Récupération et nettoyage des données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $adresse1 = trim($_POST['adresse1'] ?? '');
    $adresse2 = trim($_POST['adresse2'] ?? '');
    $cp = trim($_POST['cp'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $portable = trim($_POST['portable'] ?? '');
    $mail = trim($_POST['mail'] ?? '');

    // Validation des champs obligatoires
    $errors = [];
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    }
    if (empty($telephone) && empty($portable)) {
        $errors[] = 'Au moins un numéro de téléphone (fixe ou portable) est obligatoire.';
    }
    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }

    if (!empty($errors)) {
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
    } else {
        try {
            // ... (keep logic) ...
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Client ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du client.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<div class="alert alert-error">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</div>';
}
?>

<!-- Lien vers Awesomplete CSS -->
<link rel="stylesheet" href="../css/awesomplete.css" />

<!-- Style interne supprimé - Utilisation de style.css global -->

<div>
    <div class="page-header">
        <h1>
            <span>➕</span>
            Ajouter un nouveau client
        </h1>
    </div>

    <?php if ($message): ?>
        <?php
        $isSuccess = strpos($message, 'succès') !== false || strpos($message, 'green') !== false;
        $alertClass = $isSuccess ? 'alert-success' : 'alert-error';
        $alertIcon = $isSuccess ? '✅' : '⚠️';
        $cleanMessage = strip_tags($message, '<a><br>');
        ?>
        <div class="alert <?= $alertClass ?>">
            <span class="alert-icon"><?= $alertIcon ?></span>
            <div><?= $cleanMessage ?></div>
        </div>
    <?php endif; ?>

<!-- Structure de la Popup/Modal (initialement cachée) -->
<!-- Structure de la Modal de confirmation -->
<div id="confirmationModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer l'ajout du client</h2>
            <span id="closeModal" class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="clientSummary">
                <!-- Résumé injecté par JS -->
            </div>
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--border-color);">
            <h3>Doublons potentiels :</h3>
            <div id="duplicateClientsTableContainer" style="max-height: 200px; overflow-y: auto;">
                <!-- Tableau doublons injecté par JS -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="cancelAddClient" class="btn btn-secondary">Annuler</button>
            <button type="button" id="confirmAddClient" class="btn btn-primary">Confirmer l'ajout</button>
        </div>
    </div>
</div>

    <div class="card" style="max-width: 700px;">
        <form id="addClientForm" action="index.php?page=add_client" method="POST">
            <!-- Nom et Prénom sur la même ligne -->
            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="form-control">
                </div>
            </div>
            
            <!-- Email sur toute la largeur -->
            <div class="form-group">
                <label for="mail">Email</label>
                <input type="email" id="mail" name="mail" class="form-control">
            </div>
            
            <!-- Adresse 1 sur toute la largeur -->
            <div class="form-group">
                <label for="adresse1">Adresse 1</label>
                <input type="text" id="adresse1" name="adresse1" class="form-control awesomplete" data-minchars="3" data-autofirst>
            </div>
            
            <!-- Adresse 2 sur toute la largeur -->
            <div class="form-group">
                <label for="adresse2">Adresse 2 (complément)</label>
                <input type="text" id="adresse2" name="adresse2" class="form-control">
            </div>
            
            <!-- Code Postal et Ville sur la même ligne -->
            <div class="form-row">
                <div class="form-group">
                    <label for="cp">Code Postal</label>
                    <input type="text" id="cp" name="cp" class="form-control">
                </div>
                <div class="form-group">
                    <label for="ville">Ville</label>
                    <input type="text" id="ville" name="ville" class="form-control">
                </div>
            </div>
            
            <!-- Téléphone et Portable sur la même ligne -->
            <div class="form-row">
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" class="form-control">
                </div>
                <div class="form-group">
                    <label for="portable">Portable</label>
                    <input type="tel" id="portable" name="portable" class="form-control">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span>✓</span>
                    Ajouter le client
                </button>
                <a href="index.php?page=clients" class="btn btn-secondary">
                    <span>✕</span>
                    Annuler
                </a>
            </div>
        </form>
    </div>
    <div id="formJsErrors" class="alert alert-error" style="display: none;"></div>
</div>

<script src="../js/awesomplete.min.js"></script> <!-- Attribut 'async' retiré -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addClientForm = document.getElementById('addClientForm');
    const nomInput = document.getElementById('nom');
    const prenomInput = document.getElementById('prenom');
    const adresse1Input = document.getElementById('adresse1');
    const adresse2Input = document.getElementById('adresse2');
    const cpInput = document.getElementById('cp');
    const villeInput = document.getElementById('ville');
    const telInput = document.getElementById('telephone');
    const portableInput = document.getElementById('portable');
    const mailInput = document.getElementById('mail');

    const modal = document.getElementById('confirmationModal');
    const closeModalButton = document.getElementById('closeModal');
    const clientSummaryDiv = document.getElementById('clientSummary');
    const duplicatesTableContainer = document.getElementById('duplicateClientsTableContainer');
    const confirmAddButton = document.getElementById('confirmAddClient');
    const cancelAddButton = document.getElementById('cancelAddClient');

    let awesompleteInstance;
    let addressFetchTimeout;

    // --- Formatage et validation des numéros de téléphone ---
    function formatPhoneNumber(inputElement) {
        let value = inputElement.value.replace(/\D/g, ''); // Supprime tout ce qui n'est pas un chiffre
        let formattedValue = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 2 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        inputElement.value = formattedValue;
    }

    function validatePhoneNumber(inputElement) {
        const value = inputElement.value.replace(/\D/g, '');
        const errorSpanId = inputElement.id + '-error';
        let errorSpan = document.getElementById(errorSpanId);
        if (!errorSpan) {
            errorSpan = document.createElement('span');
            errorSpan.id = errorSpanId;
            errorSpan.style.color = 'var(--danger-color, red)';
            errorSpan.style.fontSize = '0.8em';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '2px';
            inputElement.parentNode.appendChild(errorSpan);
        }

        if (value.length > 0 && value.length !== 10) {
            inputElement.classList.add('error'); // Use class for validation state if possible
            inputElement.style.borderColor = 'var(--danger-color, red)'; // Fallback markup
            errorSpan.textContent = 'Le numéro doit contenir 10 chiffres.';
        } else {
            inputElement.classList.remove('error');
            inputElement.style.borderColor = ''; // Reset to default (CSS handled)
            errorSpan.textContent = '';
        }
    }

    if (telInput) {
        telInput.addEventListener('input', function() { formatPhoneNumber(this); });
        telInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
    if (portableInput) {
        portableInput.addEventListener('input', function() { formatPhoneNumber(this); });
        portableInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }

    // --- Autocomplétion d'adresse ---
    if (window.Awesomplete) {
        awesompleteInstance = new Awesomplete(adresse1Input, {
            minChars: 3,
            autoFirst: true,
            list: [], // Sera rempli par fetch
            // La fonction 'data' est utilisée par Awesomplete pour obtenir la valeur de chaque suggestion.
            // Nous voulons que la 'value' de l'AwesompleteItem soit notre objet properties complet.
            data: function (item, input) { 
                return { label: item.label, value: item.properties }; // item ici est {label, properties}
            },
            // La fonction 'item' est utilisée pour afficher chaque suggestion dans la liste.
            item: function (suggestionData, input) { // suggestionData ici est {label, value: {properties}}
                return Awesomplete.ITEM(suggestionData.label, input); // Affiche le label
            },
            // La fonction 'replace' est appelée quand une suggestion est sélectionnée.
            // Elle détermine ce qui est mis dans le champ input.
            replace: function(suggestionData) { // suggestionData ici est {label, value: {properties}}
                this.input.value = suggestionData.value.name || ''; // Met le nom de la rue (properties.name)
            }
        });

        adresse1Input.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < awesompleteInstance.minChars) {
                awesompleteInstance.list = [];
                return;
            }

            clearTimeout(addressFetchTimeout);
            addressFetchTimeout = setTimeout(() => {
                fetch('../api/get_addresses.php?q=' + encodeURIComponent(query))
                    .then(response => response.ok ? response.json() : Promise.reject('Réponse réseau non OK pour get_addresses'))
                    .then(data => {
                        console.log("Réponse de api/get_addresses.php:", JSON.stringify(data, null, 2));
                        if (data.error) {
                            console.error("Erreur API Adresse:", data.error);
                            awesompleteInstance.list = [];
                        } else if (data.features && Array.isArray(data.features)) {
                            const suggestions = data.features.map(feature => ({
                                label: feature.properties.label || '', 
                                properties: feature.properties // Stocke l'objet properties entier
                            }));
                            awesompleteInstance.list = suggestions;
                        } else {
                            awesompleteInstance.list = [];
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de la récupération des adresses:', error);
                        awesompleteInstance.list = [];
                    });
            }, 300);
        });

        adresse1Input.addEventListener('awesomplete-selectcomplete', function(event) {
            // event.text est l'AwesompleteItem. Sa propriété 'value' est ce que nous avons défini dans la fonction 'data'.
            // Donc, event.text.value est notre objet 'properties'.
            const selectedProperties = event.text.value;
            
            console.log("Objet event.text d'Awesomplete:", JSON.stringify(event.text, null, 2));
            console.log("Propriétés sélectionnées (event.text.value):", JSON.stringify(selectedProperties, null, 2));

            // selectedProperties devrait être directement l'objet properties de l'API
            if (selectedProperties && typeof selectedProperties === 'object') {
                const currentPostcode = selectedProperties.postcode || '';
                const currentCity = selectedProperties.city || '';

                console.log("CP à remplir:", currentPostcode);
                console.log("Ville à remplir:", currentCity);

                if (cpInput) {
                    cpInput.value = currentPostcode;
                }
                if (villeInput) {
                    villeInput.value = currentCity;
                    villeInput.readOnly = false;
                }
            } else {
                console.warn("Les propriétés sélectionnées ne sont pas un objet ou sont null:", selectedProperties);
            }
        });

    } else {
        console.warn("Awesomplete n'est pas chargé.");
    }

    // --- Logique de la Popup de confirmation ---
    if (addClientForm) {
        addClientForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche la soumission normale du formulaire
            document.getElementById('formJsErrors').textContent = ''; // Efface les erreurs précédentes

            // Validation JS des champs obligatoires avant d'ouvrir la popup
            const nom = nomInput.value.trim();
            const telephone = telInput.value.trim();
            const portable = portableInput.value.trim();
            let jsErrors = [];

            if (nom === '') {
                jsErrors.push('Le nom est obligatoire.');
            if (nom === '') {
                jsErrors.push('Le nom est obligatoire.');
                nomInput.style.borderColor = 'var(--danger-color, red)';
            } else {
                nomInput.style.borderColor = '';
            }

            if (telephone === '' && portable === '') {
                jsErrors.push('Au moins un numéro de téléphone (fixe ou portable) est obligatoire.');
                telInput.style.borderColor = 'var(--danger-color, red)';
                portableInput.style.borderColor = 'var(--danger-color, red)';
            } else {
                // Si au moins un est rempli, on peut réinitialiser les bordures
                // La validation de format individuelle (10 chiffres) est déjà gérée par 'blur'
                if (telephone !== '') telInput.style.borderColor = '';
                if (portable !== '') portableInput.style.borderColor = '';
            }
            
            // Valider l'email s'il est rempli (non bloquant pour la popup, mais bon à vérifier)
            const emailVal = mailInput.value.trim();
            if (emailVal !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                 jsErrors.push('L\'adresse email fournie n\'est pas valide.');
                 mailInput.style.borderColor = 'var(--danger-color, red)';
            } else if (emailVal !== '') {
                 mailInput.style.borderColor = '';
            }


            if (jsErrors.length > 0) {
                const errorDiv = document.getElementById('formJsErrors');
                errorDiv.innerHTML = jsErrors.join('<br>');
                errorDiv.style.display = 'block';
                return; // N'ouvre pas la popup s'il y a des erreurs JS
            }

            // Récupérer les données du formulaire pour le résumé
            const formData = new FormData(addClientForm);
            let summaryHtml = '<h4>Informations du nouveau client :</h4><ul>';
            summaryHtml += `<li><strong>Nom:</strong> ${formData.get('nom') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Prénom:</strong> ${formData.get('prenom') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Email:</strong> ${formData.get('mail') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Adresse:</strong> ${formData.get('adresse1') || ''} ${formData.get('adresse2') || ''}</li>`;
            summaryHtml += `<li><strong>CP:</strong> ${formData.get('cp') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Ville:</strong> ${formData.get('ville') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Téléphone:</strong> ${formData.get('telephone') || 'N/A'}</li>`;
            summaryHtml += `<li><strong>Portable:</strong> ${formData.get('portable') || 'N/A'}</li>`;
            summaryHtml += '</ul>';
            clientSummaryDiv.innerHTML = summaryHtml;

            // Appeler le script PHP pour vérifier les doublons
            const clientName = formData.get('nom');
            duplicatesTableContainer.innerHTML = 'Chargement des doublons potentiels...';
            
            fetch(`../utils/check_duplicate_client.php?nom=${encodeURIComponent(clientName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        duplicatesTableContainer.innerHTML = `<p style="color:var(--danger-color, red);">Erreur: ${data.error}</p>`;
                    } else if (data.duplicates && data.duplicates.length > 0) {
                        let tableHtml = '<table class="table"><thead><tr>';
                        tableHtml += '<th>ID</th>';
                        tableHtml += '<th>Nom</th>';
                        tableHtml += '<th>Prénom</th>';
                        tableHtml += '<th>Ville</th>';
                        tableHtml += '<th>CP</th>';
                        tableHtml += '</tr></thead><tbody>';
                        data.duplicates.forEach(dup => {
                            tableHtml += '<tr>';
                            tableHtml += `<td>${dup.ID || ''}</td>`;
                            tableHtml += `<td>${dup.nom || ''}</td>`;
                            tableHtml += `<td>${dup.prenom || ''}</td>`;
                            tableHtml += `<td>${dup.ville || ''}</td>`;
                            tableHtml += `<td>${dup.cp || ''}</td>`;
                            tableHtml += '</tr>';
                        });
                        tableHtml += '</tbody></table>';
                        duplicatesTableContainer.innerHTML = tableHtml;
                    } else {
                        duplicatesTableContainer.innerHTML = '<p>Aucun doublon potentiel trouvé avec ce nom.</p>';
                    }
                })
                .catch(error => {
                    console.error("Erreur fetch doublons:", error);
                    duplicatesTableContainer.innerHTML = '<p style="color:var(--danger-color, red);">Impossible de vérifier les doublons.</p>';
                });

            modal.style.display = 'block';
        });
    }

    if (closeModalButton) {
        closeModalButton.onclick = function() {
            modal.style.display = 'none';
        }
    }

    if (cancelAddButton) {
        cancelAddButton.onclick = function() {
            modal.style.display = 'none';
        }
    }

    if (confirmAddButton) {
        confirmAddButton.onclick = function() {
            // Soumettre réellement le formulaire
            if (addClientForm) {
                addClientForm.submit();
            }
        }
    }

    /* Fermer la modal si on clique en dehors - DESACTIVE à la demande de l'utilisateur
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    */

});
</script>