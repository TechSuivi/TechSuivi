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
        $message = '<p style="color: red;">' . implode('<br>', $errors) . '</p>';
    } else {
        try {
            $sql = "INSERT INTO clients (nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail) 
                    VALUES (:nom, :prenom, :adresse1, :adresse2, :cp, :ville, :telephone, :portable, :mail)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':adresse1', $adresse1);
            $stmt->bindParam(':adresse2', $adresse2);
            $stmt->bindParam(':cp', $cp);
            $stmt->bindParam(':ville', $ville);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':portable', $portable);
            $stmt->bindParam(':mail', $mail);

            if ($stmt->execute()) {
                $message = '<p style="color: green;">Client ajouté avec succès !</p>';
            } else {
                $message = '<p style="color: red;">Erreur lors de l\'ajout du client.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<p style="color: red;">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</p>';
}
?>

<!-- Lien vers Awesomplete CSS -->
<link rel="stylesheet" href="../css/awesomplete.css" />

<style>
/* Modern Purple Theme for Add Client */
.client-page {
    background: var(--bg-color);
    color: var(--text-color);
}

.page-header {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
    max-width: 700px;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.form-row .form-group {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95em;
}

.form-control, .form-control.awesomplete {
    width: 100% !important;
    max-width: 100% !important;
    padding: 12px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #8e44ad;
    box-shadow: 0 0 0 4px rgba(142, 68, 173, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    margin-top: 25px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1em;
}

.btn-primary {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(142, 68, 173, 0.3);
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<div class="client-page">
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
            <span style="font-size: 1.5em;"><?= $alertIcon ?></span>
            <div><?= $cleanMessage ?></div>
        </div>
    <?php endif; ?>

<!-- Structure de la Popup/Modal (initialement cachée) -->
<div id="confirmationModal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);">
    <div style="margin: 10% auto; padding: 20px; border: 1px solid #888; width: 60%; max-width: 700px; border-radius: 8px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);">
    <!-- background-color: #fefefe; retiré d'ici -->
        <span id="closeModal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        <h2>Confirmer l'ajout du client</h2>
        <div id="clientSummary">
            <!-- Le résumé du client sera injecté ici par JavaScript -->
        </div>
        <hr>
        <h3>Doublons potentiels :</h3>
        <div id="duplicateClientsTableContainer" style="max-height: 200px; overflow-y: auto;">
            <!-- Le tableau des doublons sera injecté ici -->
        </div>
        <div style="margin-top: 20px; text-align: right;">
            <button type="button" id="cancelAddClient" style="padding: 10px 15px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">Annuler</button>
            <button type="button" id="confirmAddClient" style="padding: 10px 15px; background-color: var(--accent-color); color: white; border: none; border-radius: 4px; cursor: pointer;">Confirmer l'ajout</button>
        </div>
    </div>
</div>

    <div class="form-card">
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
    <div id="formJsErrors" style="color: red; margin-top: 10px;"></div>
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
            errorSpan.style.color = 'red';
            errorSpan.style.fontSize = '0.8em';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '2px';
            inputElement.parentNode.appendChild(errorSpan);
        }

        if (value.length > 0 && value.length !== 10) {
            inputElement.style.borderColor = 'red';
            errorSpan.textContent = 'Le numéro doit contenir 10 chiffres.';
        } else {
            inputElement.style.borderColor = '#ccc'; // Couleur par défaut
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
                nomInput.style.borderColor = 'red';
            } else {
                nomInput.style.borderColor = '#ccc';
            }

            if (telephone === '' && portable === '') {
                jsErrors.push('Au moins un numéro de téléphone (fixe ou portable) est obligatoire.');
                telInput.style.borderColor = 'red';
                portableInput.style.borderColor = 'red';
            } else {
                // Si au moins un est rempli, on peut réinitialiser les bordures
                // La validation de format individuelle (10 chiffres) est déjà gérée par 'blur'
                if (telephone !== '') telInput.style.borderColor = '#ccc';
                if (portable !== '') portableInput.style.borderColor = '#ccc';
            }
            
            // Valider l'email s'il est rempli (non bloquant pour la popup, mais bon à vérifier)
            const emailVal = mailInput.value.trim();
            if (emailVal !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                 jsErrors.push('L\'adresse email fournie n\'est pas valide.');
                 mailInput.style.borderColor = 'red';
            } else if (emailVal !== '') {
                 mailInput.style.borderColor = '#ccc';
            }


            if (jsErrors.length > 0) {
                document.getElementById('formJsErrors').innerHTML = jsErrors.join('<br>');
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
                        duplicatesTableContainer.innerHTML = `<p style="color:red;">Erreur: ${data.error}</p>`;
                    } else if (data.duplicates && data.duplicates.length > 0) {
                        let tableHtml = '<table style="width:100%; border-collapse: collapse;"><thead><tr>';
                        tableHtml += '<th style="border:1px solid #ddd; padding:8px; text-align:left;">ID</th>';
                        tableHtml += '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Nom</th>';
                        tableHtml += '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Prénom</th>';
                        tableHtml += '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Ville</th>';
                        tableHtml += '<th style="border:1px solid #ddd; padding:8px; text-align:left;">CP</th>';
                        tableHtml += '</tr></thead><tbody>';
                        data.duplicates.forEach(dup => {
                            tableHtml += '<tr>';
                            tableHtml += `<td style="border:1px solid #ddd; padding:8px;">${dup.ID || ''}</td>`;
                            tableHtml += `<td style="border:1px solid #ddd; padding:8px;">${dup.nom || ''}</td>`;
                            tableHtml += `<td style="border:1px solid #ddd; padding:8px;">${dup.prenom || ''}</td>`;
                            tableHtml += `<td style="border:1px solid #ddd; padding:8px;">${dup.ville || ''}</td>`;
                            tableHtml += `<td style="border:1px solid #ddd; padding:8px;">${dup.cp || ''}</td>`;
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
                    duplicatesTableContainer.innerHTML = '<p style="color:red;">Impossible de vérifier les doublons.</p>';
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

    // Fermer la modal si on clique en dehors
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

});
</script>