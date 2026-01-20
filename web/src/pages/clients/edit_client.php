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
    $message = '<div class="alert alert-error">ID client invalide.</div>';
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
                $message = '<div class="alert alert-error">Client non trouvé.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur lors de la récupération du client : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</div>';
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
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
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
                $message = '<div class="alert alert-success">Client modifié avec succès !</div>';
                // Recharger les données du client après modification
                $sql = "SELECT ID, nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail FROM clients WHERE ID = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
                $stmt->execute();
                $client = $stmt->fetch();
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification du client.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<!-- Lien vers Awesomplete CSS -->
<link rel="stylesheet" href="../css/awesomplete.css" />

<!-- Style interne supprimé - Utilisation de style.css global -->

<div>
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
            <span class="alert-icon"><?= $alertIcon ?></span>
            <div><?= $cleanMessage ?></div>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="header-controls" style="margin-bottom: 20px;">
            <a href="index.php?page=clients" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste des clients
            </a>
            <a href="index.php?page=clients_view&id=<?= htmlspecialchars($client['ID']) ?>" class="btn btn-secondary">
                <span>👁️</span>
                Voir le client
            </a>
        </div>

        <div class="card" style="max-width: 700px;">
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
        <div id="formJsErrors" class="alert alert-error" style="display: none; margin-top: 10px;"></div>

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
            errorSpan.style.color = 'var(--danger-color, red)';
            errorSpan.style.fontSize = '0.8em';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '2px';
            inputElement.parentNode.appendChild(errorSpan);
        }

        if (value.length > 0 && value.length !== 10) {
            inputElement.classList.add('error');
            inputElement.style.borderColor = 'var(--danger-color, red)';
            errorSpan.textContent = 'Le numéro doit contenir 10 chiffres.';
        } else {
            inputElement.classList.remove('error');
            inputElement.style.borderColor = ''; // Default handled by CSS
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
                nomInput.classList.add('error');
                nomInput.style.borderColor = 'var(--danger-color, red)';
            } else {
                nomInput.classList.remove('error');
                nomInput.style.borderColor = '';
            }

            if (telephone === '' && portable === '') {
                jsErrors.push('Au moins un numéro de téléphone (fixe ou portable) est obligatoire.');
                telInput.style.borderColor = 'var(--danger-color, red)';
                portableInput.style.borderColor = 'var(--danger-color, red)';
            } else {
                if (telephone !== '') telInput.style.borderColor = '';
                if (portable !== '') portableInput.style.borderColor = '';
            }
            
            // Valider l'email s'il est rempli
            const emailVal = mailInput.value.trim();
            if (emailVal !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                 jsErrors.push('L\'adresse email fournie n\'est pas valide.');
                 mailInput.style.borderColor = 'var(--danger-color, red)';
            } else if (emailVal !== '') {
                 mailInput.style.borderColor = '';
            }

            if (jsErrors.length > 0) {
                event.preventDefault(); // Empêche la soumission du formulaire
                const errorDiv = document.getElementById('formJsErrors');
                errorDiv.innerHTML = jsErrors.join('<br>');
                errorDiv.style.display = 'block';
                return false;
            }
        });
    }
});
</script>