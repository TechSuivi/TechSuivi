<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$message = ''; // Pour les messages de succès ou d'erreur
$client = null; // Pour stocker les données du client

// Récupérer l'ID du client depuis l'URL
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($clientId <= 0) {
    $message = '<p style="color: red;">ID client invalide.</p>';
} else {
    // Récupérer les données du client
    if (isset($pdo)) {
        try {
            $sql = "SELECT ID, nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail FROM clients WHERE ID = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch();
            
            if (!$client) {
                $message = '<p style="color: red;">Client non trouvé.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur lors de la récupération du client : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $message = '<p style="color: red;">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</p>';
    }
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo) && $client) {
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
            $sql = "UPDATE clients SET nom = :nom, prenom = :prenom, adresse1 = :adresse1, adresse2 = :adresse2, 
                    cp = :cp, ville = :ville, telephone = :telephone, portable = :portable, mail = :mail 
                    WHERE ID = :id";
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
            $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = '<p style="color: green;">Client modifié avec succès !</p>';
                // Recharger les données du client après modification
                $sql = "SELECT ID, nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail FROM clients WHERE ID = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
                $stmt->execute();
                $client = $stmt->fetch();
            } else {
                $message = '<p style="color: red;">Erreur lors de la modification du client.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
?>

<!-- Lien vers Awesomplete CSS -->
<link rel="stylesheet" href="../css/awesomplete.css" />

<style>
/* Modern Purple Theme for Edit Client */
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
    text-decoration: none;
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
            <span>✏️</span>
            Modifier le client
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

    <?php if ($client): ?>
        <div style="margin-bottom: 20px; display: flex; gap: 10px;">
            <a href="index.php?page=clients" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste des clients
            </a>
            <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" class="btn btn-secondary">
                <span>👁️</span>
                Voir le client
            </a>
        </div>

        <div class="form-card">
            <form id="editClientForm" action="index.php?page=edit_client&id=<?= htmlspecialchars($client['ID']) ?>" method="POST">
                <!-- Nom et Prénom sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-control" required value="<?= htmlspecialchars($client['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- Email sur toute la largeur -->
                <div class="form-group">
                    <label for="mail">Email</label>
                    <input type="email" id="mail" name="mail" class="form-control" value="<?= htmlspecialchars($client['mail'] ?? '') ?>">
                </div>
                
                <!-- Adresse 1 sur toute la largeur -->
                <div class="form-group">
                    <label for="adresse1">Adresse 1</label>
                    <input type="text" id="adresse1" name="adresse1" class="form-control awesomplete" data-minchars="3" data-autofirst value="<?= htmlspecialchars($client['adresse1'] ?? '') ?>">
                </div>
                
                <!-- Adresse 2 sur toute la largeur -->
                <div class="form-group">
                    <label for="adresse2">Adresse 2 (complément)</label>
                    <input type="text" id="adresse2" name="adresse2" class="form-control" value="<?= htmlspecialchars($client['adresse2'] ?? '') ?>">
                </div>
                
                <!-- Code Postal et Ville sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="cp">Code Postal</label>
                        <input type="text" id="cp" name="cp" class="form-control" value="<?= htmlspecialchars($client['cp'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" class="form-control" value="<?= htmlspecialchars($client['ville'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- Téléphone et Portable sur la même ligne -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" class="form-control" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="portable">Portable</label>
                        <input type="tel" id="portable" name="portable" class="form-control" value="<?= htmlspecialchars($client['portable'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span>
                        Modifier le client
                    </button>
                    <a href="index.php?page=clients" class="btn btn-secondary">
                        <span>✕</span>
                        Annuler
                    </a>
                </div>
            </form>
        </div>
        <div id="formJsErrors" style="color: red; margin-top: 10px;"></div>

    <?php else: ?>
        <div>
            <a href="index.php?page=clients" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste des clients
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="../js/awesomplete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editClientForm = document.getElementById('editClientForm');
    const nomInput = document.getElementById('nom');
    const prenomInput = document.getElementById('prenom');
    const adresse1Input = document.getElementById('adresse1');
    const adresse2Input = document.getElementById('adresse2');
    const cpInput = document.getElementById('cp');
    const villeInput = document.getElementById('ville');
    const telInput = document.getElementById('telephone');
    const portableInput = document.getElementById('portable');
    const mailInput = document.getElementById('mail');

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
        formatPhoneNumber(telInput); // Format initial value on load
        telInput.addEventListener('input', function() { formatPhoneNumber(this); });
        telInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
    if (portableInput) {
        formatPhoneNumber(portableInput); // Format initial value on load
        portableInput.addEventListener('input', function() { formatPhoneNumber(this); });
        portableInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }

    // --- Autocomplétion d'adresse ---
    if (window.Awesomplete && adresse1Input) {
        awesompleteInstance = new Awesomplete(adresse1Input, {
            minChars: 3,
            autoFirst: true,
            list: [], // Sera rempli par fetch
            data: function (item, input) { 
                return { label: item.label, value: item.properties };
            },
            item: function (suggestionData, input) {
                return Awesomplete.ITEM(suggestionData.label, input);
            },
            replace: function(suggestionData) {
                this.input.value = suggestionData.value.name || '';
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
                                properties: feature.properties
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
            const selectedProperties = event.text.value;
            
            console.log("Objet event.text d'Awesomplete:", JSON.stringify(event.text, null, 2));
            console.log("Propriétés sélectionnées (event.text.value):", JSON.stringify(selectedProperties, null, 2));

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

    // --- Validation du formulaire ---
    if (editClientForm) {
        editClientForm.addEventListener('submit', function(event) {
            document.getElementById('formJsErrors').textContent = ''; // Efface les erreurs précédentes

            // Validation JS des champs obligatoires
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
                if (telephone !== '') telInput.style.borderColor = '#ccc';
                if (portable !== '') portableInput.style.borderColor = '#ccc';
            }
            
            // Valider l'email s'il est rempli
            const emailVal = mailInput.value.trim();
            if (emailVal !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                 jsErrors.push('L\'adresse email fournie n\'est pas valide.');
                 mailInput.style.borderColor = 'red';
            } else if (emailVal !== '') {
                 mailInput.style.borderColor = '#ccc';
            }

            if (jsErrors.length > 0) {
                event.preventDefault(); // Empêche la soumission du formulaire
                document.getElementById('formJsErrors').innerHTML = jsErrors.join('<br>');
                return false;
            }
        });
    }
});
</script>