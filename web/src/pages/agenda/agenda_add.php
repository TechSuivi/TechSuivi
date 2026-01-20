<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = '';
$messageType = '';
$isEdit = false;
$agendaItem = null;

// Vérifier si c'est une modification
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $isEdit = true;
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM agenda a
            LEFT JOIN clients c ON a.id_client = c.ID
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $agendaItem = $stmt->fetch();
        
        if (!$agendaItem) {
            $message = "Tâche non trouvée.";
            $messageType = 'error';
            $isEdit = false;
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la récupération de la tâche : " . htmlspecialchars($e->getMessage());
        $messageType = 'error';
        $isEdit = false;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_planifiee = $_POST['date_planifiee'] ?? '';
    $priorite = $_POST['priorite'] ?? 'normale';
    $statut = $_POST['statut'] ?? 'planifie';
    $couleur = $_POST['couleur'] ?? '#3498db';
    $rappel_minutes = (int)($_POST['rappel_minutes'] ?? 0);
    $id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
    $utilisateur = $username; // Utilisateur connecté
    
    // Validation
    $errors = [];
    
    if (empty($titre)) {
        $errors[] = "Le titre est obligatoire.";
    }
    
    if (empty($date_planifiee)) {
        $errors[] = "La date planifiée est obligatoire.";
    } elseif (strtotime($date_planifiee) === false) {
        $errors[] = "Format de date invalide.";
    }
    
    if (!in_array($priorite, ['basse', 'normale', 'haute', 'urgente'])) {
        $errors[] = "Priorité invalide.";
    }
    
    if (!in_array($statut, ['planifie', 'en_cours', 'termine', 'reporte', 'annule'])) {
        $errors[] = "Statut invalide.";
    }
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
        $errors[] = "Couleur invalide.";
    }
    
    if (empty($errors)) {
        try {
            if ($isEdit && $agendaItem) {
                // Modification
                $stmt = $pdo->prepare("
                    UPDATE agenda SET 
                        titre = ?, 
                        description = ?, 
                        date_planifiee = ?, 
                        priorite = ?, 
                        statut = ?, 
                        couleur = ?, 
                        rappel_minutes = ?,
                        id_client = ?,
                        date_modification = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $titre, $description, $date_planifiee, $priorite, 
                    $statut, $couleur, $rappel_minutes, $id_client, $agendaItem['id']
                ]);
                
                $message = "Tâche modifiée avec succès !";
                $messageType = 'success';
                
                // Recharger les données
                $stmt = $pdo->prepare("SELECT * FROM agenda WHERE id = ?");
                $stmt->execute([$agendaItem['id']]);
                $agendaItem = $stmt->fetch();
                
            } else {
                // Création
                $stmt = $pdo->prepare("
                    INSERT INTO agenda (titre, description, date_planifiee, priorite, statut, utilisateur, couleur, rappel_minutes, id_client) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $titre, $description, $date_planifiee, $priorite, 
                    $statut, $utilisateur, $couleur, $rappel_minutes, $id_client
                ]);
                
                $message = "Tâche créée avec succès !";
                $messageType = 'success';
                
                // Rediriger vers la liste après création
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'index.php?page=agenda_list';
                    }, 2000);
                </script>";
            }
            
        } catch (PDOException $e) {
            $message = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

// Valeurs par défaut pour le formulaire
$formData = [
    'titre' => $agendaItem['titre'] ?? ($_POST['titre'] ?? ''),
    'description' => $agendaItem['description'] ?? ($_POST['description'] ?? ''),
    'date_planifiee' => $agendaItem['date_planifiee'] ?? ($_POST['date_planifiee'] ?? date('Y-m-d\TH:i')),
    'priorite' => $agendaItem['priorite'] ?? ($_POST['priorite'] ?? 'normale'),
    'statut' => $agendaItem['statut'] ?? ($_POST['statut'] ?? 'planifie'),
    'couleur' => $agendaItem['couleur'] ?? ($_POST['couleur'] ?? '#3498db'),
    'couleur' => $agendaItem['couleur'] ?? ($_POST['couleur'] ?? '#3498db'),
    'rappel_minutes' => $agendaItem['rappel_minutes'] ?? ($_POST['rappel_minutes'] ?? 0),
    'id_client' => $agendaItem['id_client'] ?? ($_POST['id_client'] ?? ''),
    'client_nom' => isset($agendaItem['client_nom']) ? trim($agendaItem['client_nom'] . ' ' . ($agendaItem['client_prenom'] ?? '')) : ''
];
?>

<link rel="stylesheet" href="css/awesomplete.css">
<script src="js/awesomplete.min.js"></script>





<div class="agenda-page">
<div class="agenda-page">
    <div class="page-header">
        <div class="header-content">
            <h1>
                <span><?= $isEdit ? '✏️' : '➕' ?></span>
                <?= $isEdit ? 'Modifier la tâche' : 'Nouvelle tâche' ?>
            </h1>
            <p class="subtitle"><?= $isEdit ? 'Modifiez les détails de l\'événement ci-dessous' : 'Planifiez un nouvel événement dans l\'agenda' ?></p>
        </div>
        <div class="header-actions">
            <a href="index.php?page=agenda_list" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste
            </a>
        </div>
    </div>
    
    <div class="agenda-form-container">
        <!-- Form Header removed (button moved up) -->
        <div class="form-header">
            <div></div> <!-- Empty div kept if needed for flex spacing, or remove entirely if unused -->
        </div>

        <?php if ($message): ?>
            <?php
            $alertIcon = $messageType === 'success' ? '✅' : '⚠️';
            ?>
            <div class="alert alert-<?= $messageType ?>">
                <span style="font-size: 1.2em; margin-right: 8px;"><?= $alertIcon ?></span>
                <?= $message ?>
            </div>
        <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div class="form-group">
                <label for="titre">Titre de la tâche *</label>
                <input type="text" id="titre" name="titre" class="form-control" 
                       value="<?= htmlspecialchars($formData['titre']) ?>" 
                       placeholder="Ex: Réunion équipe, Maintenance serveur..." required>
            </div>

            <!-- Client Section -->
            <div class="form-group client-search-container">
                <label for="client_search">Client associé</label>
                <div style="display: flex; gap: 10px;">
                    <div style="flex-grow: 1; position: relative;">
                        <input type="text" id="client_search" class="form-control" 
                               placeholder="Rechercher un client..." 
                               value="<?= htmlspecialchars($formData['client_nom']) ?>" autocomplete="off">
                        <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($formData['id_client']) ?>">
                        <div id="client_suggestions" class="client-suggestions"></div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="openNestedClientModal()" style="white-space: nowrap;">
                        <span>➕</span> Nouveau
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control textarea" 
                          placeholder="Détails de la tâche, notes importantes..."><?= htmlspecialchars($formData['description']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="date_planifiee">Date et heure planifiées *</label>
                        <div class="datetime-local-wrapper">
                            <input type="datetime-local" id="date_planifiee" name="date_planifiee" 
                                   class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($formData['date_planifiee'])) ?>" required>
                        </div>
                        <div class="form-help">Sélectionnez la date et l'heure de la tâche</div>
                    </div>
                </div>

                <div class="form-col-small">
                    <div class="form-group">
                        <label for="rappel_minutes">Rappel (minutes)</label>
                        <input type="number" id="rappel_minutes" name="rappel_minutes" 
                               class="form-control" value="<?= htmlspecialchars($formData['rappel_minutes']) ?>" 
                               min="0" max="10080" placeholder="0">
                        <div class="form-help">0 = pas de rappel</div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="priorite">Priorité</label>
                        <select id="priorite" name="priorite" class="form-control">
                            <option value="basse" <?= $formData['priorite'] === 'basse' ? 'selected' : '' ?>>🟢 Basse</option>
                            <option value="normale" <?= $formData['priorite'] === 'normale' ? 'selected' : '' ?>>🔵 Normale</option>
                            <option value="haute" <?= $formData['priorite'] === 'haute' ? 'selected' : '' ?>>🟠 Haute</option>
                            <option value="urgente" <?= $formData['priorite'] === 'urgente' ? 'selected' : '' ?>>🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="form-col">
                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut" class="form-control" onchange="updateStatusPreview()">
                            <option value="planifie" <?= $formData['statut'] === 'planifie' ? 'selected' : '' ?>>📅 Planifié</option>
                            <option value="en_cours" <?= $formData['statut'] === 'en_cours' ? 'selected' : '' ?>>⏳ En cours</option>
                            <option value="termine" <?= $formData['statut'] === 'termine' ? 'selected' : '' ?>>✅ Terminé</option>
                            <option value="reporte" <?= $formData['statut'] === 'reporte' ? 'selected' : '' ?>>⏰ Reporté</option>
                            <option value="annule" <?= $formData['statut'] === 'annule' ? 'selected' : '' ?>>❌ Annulé</option>
                        </select>
                        <span id="status-preview" class="status-badge-preview"></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="couleur">Couleur de la tâche</label>
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <input type="color" id="couleur" name="couleur" 
                           value="<?= htmlspecialchars($formData['couleur']) ?>" 
                           onchange="updateColorPreview()">
                    <span class="color-preview" id="color-preview" 
                          style="background-color: <?= htmlspecialchars($formData['couleur']) ?>"></span>
                    <div class="priority-colors">
                        <div class="priority-color" style="background-color: #3498db" 
                             onclick="selectColor('#3498db')" title="Bleu (Normal)"></div>
                        <div class="priority-color" style="background-color: #2ecc71" 
                             onclick="selectColor('#2ecc71')" title="Vert (Succès)"></div>
                        <div class="priority-color" style="background-color: #f39c12" 
                             onclick="selectColor('#f39c12')" title="Orange (Attention)"></div>
                        <div class="priority-color" style="background-color: #e74c3c" 
                             onclick="selectColor('#e74c3c')" title="Rouge (Urgent)"></div>
                        <div class="priority-color" style="background-color: #9b59b6" 
                             onclick="selectColor('#9b59b6')" title="Violet (Important)"></div>
                        <div class="priority-color" style="background-color: #1abc9c" 
                             onclick="selectColor('#1abc9c')" title="Turquoise (Info)"></div>
                    </div>
                </div>
                <div class="form-help">Choisissez une couleur pour identifier facilement cette tâche</div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <span><?= $isEdit ? '💾' : '➕' ?></span>
                    <?= $isEdit ? 'Enregistrer les modifications' : 'Créer la tâche' ?>
                </button>
                <a href="index.php?page=agenda_list" class="btn btn-secondary">
                    <span>✕</span>
                    Annuler
                </a>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Nested Client Modal -->
<div id="nestedClientModal" class="modal-overlay hidden fixed inset-0 z-50 bg-black-opacity items-center justify-center backdrop-blur-sm" style="display: none;">
    <div class="modal-content max-w-600">
        <div class="modal-header">
            <h3 class="m-0">Nouveau Client</h3>
            <span class="modal-close" onclick="closeNestedClientModal()">×</span>
        </div>
        <div class="modal-body">
            <form id="nestedClientForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_nom">Nom *</label>
                        <input type="text" id="nested_nom" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nested_prenom">Prénom</label>
                        <input type="text" id="nested_prenom" name="prenom" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nested_adresse1">Adresse</label>
                    <input type="text" id="nested_adresse1" name="adresse1" class="form-control" placeholder="Rechercher une adresse..." autocomplete="off">
                    <div id="nested_address_suggestions" class="client-suggestions"></div>
                </div>
                
                <div class="form-group">
                    <label for="nested_adresse2">Adresse 2 (complément)</label>
                    <input type="text" id="nested_adresse2" name="adresse2" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_cp">Code Postal</label>
                        <input type="text" id="nested_cp" name="cp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="nested_ville">Ville</label>
                        <input type="text" id="nested_ville" name="ville" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nested_telephone">Téléphone</label>
                        <input type="tel" id="nested_telephone" name="telephone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="nested_portable">Portable</label>
                        <input type="tel" id="nested_portable" name="portable" class="form-control">
                    </div>
                </div>
            </form>
            
            <div id="nestedDuplicateCheckSection" class="hidden mt-15 p-15 bg-hover rounded">
                <h4 class="m-0 mb-10 text-warning">⚠️ Doublons potentiels :</h4>
                <div id="nestedDuplicatesContainer" class="max-h-150 overflow-y-auto"></div>
            </div>
        </div>
        <div class="modal-footer flex justify-end gap-10 p-20 border-t border-border">
            <button type="button" class="btn btn-secondary" onclick="closeNestedClientModal()">
                <span>✕</span> Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitNestedClientForm()">
                <span>✓</span> Créer le client
            </button>
        </div>
    </div>
</div>



<script>
function selectColor(color) {
    document.getElementById('couleur').value = color;
    updateColorPreview();
    
    // Mettre à jour la sélection visuelle
    document.querySelectorAll('.priority-color').forEach(el => {
        el.classList.remove('selected');
    });
    
    document.querySelector(`.priority-color[style*="${color}"]`)?.classList.add('selected');
}

function updateColorPreview() {
    const color = document.getElementById('couleur').value;
    document.getElementById('color-preview').style.backgroundColor = color;
}

function updateStatusPreview() {
    const statut = document.getElementById('statut').value;
    const preview = document.getElementById('status-preview');
    
    const statusClasses = {
        'planifie': 'status-planned',
        'en_cours': 'status-progress', 
        'termine': 'status-completed',
        'reporte': 'status-postponed',
        'annule': 'status-cancelled'
    };
    
    const statusLabels = {
        'planifie': 'Planifié',
        'en_cours': 'En cours',
        'termine': 'Terminé', 
        'reporte': 'Reporté',
        'annule': 'Annulé'
    };
    
    preview.className = 'status-badge-preview ' + (statusClasses[statut] || '');
    preview.textContent = statusLabels[statut] || statut;
}

// Initialiser les aperçus
document.addEventListener('DOMContentLoaded', function() {
    updateColorPreview();
    updateStatusPreview();
    
    // Marquer la couleur sélectionnée
    const currentColor = document.getElementById('couleur').value;
    document.querySelector(`.priority-color[style*="${currentColor}"]`)?.classList.add('selected');

    // Init Client Search
    initClientSearch();
});

// Client Search Logic
function initClientSearch() {
    const input = document.getElementById('client_search');
    const suggestionsDiv = document.getElementById('client_suggestions');
    const hiddenInput = document.getElementById('id_client');
    
    // Awesomplete for main search
    new Awesomplete(input, {
        minChars: 2,
        maxItems: 20,
        autoFirst: true,
        list: [],
        filter: function(text, input) { return true; }, // Server-side filtering
        item: function(text, input) {
            const item = text.original; // Custom object from API
            const li = document.createElement("li");
            li.setAttribute("aria-selected", "false");
            li.className = "client-suggestion-item";
            li.innerHTML = `
                <div style="font-weight: 600;">${item.label.split(' - ')[0]}</div>
                <div style="font-size: 0.85em; color: var(--text-muted);">${item.label.split(' - ').slice(1).join(' - ')}</div>
            `;
            return li;
        }
    });

    // Handle typing for AJAX fetch
    let debounceTimer;
    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value;
        
        if (query.length < 2) return;
        
        debounceTimer = setTimeout(() => {
            fetch(`api/search_clients.php?term=${encodeURIComponent(query)}&limit=10`)
                .then(r => r.json())
                .then(data => {
                    const list = data.map(item => ({
                        label: item.label,
                        value: item.value,
                        id: item.id,
                        original: item
                    }));
                    input.awesomplete.list = list;
                    input.awesomplete.evaluate();
                });
        }, 300);
    });

    // Handle Selection
    input.addEventListener('awesomplete-selectcomplete', function(e) {
        const item = e.text;
        hiddenInput.value = item.id;
        input.value = item.value; // Just name
    });
    
    // Clear hidden ID if cleared
    input.addEventListener('change', function() {
        if (!this.value) hiddenInput.value = '';
    });
}

// Nested Modal Logic
function openNestedClientModal() {
    document.getElementById('nestedClientModal').style.display = 'flex';
    document.getElementById('nestedClientForm').reset();
    document.getElementById('nestedDuplicateCheckSection').style.display = 'none';
    
    // Init address autocomplete for nested modal
    const addrInput = document.getElementById('nested_adresse1');
    if (!addrInput.awesomplete) {
        new Awesomplete(addrInput, {
            minChars: 3,
            autoFirst: true,
            filter: function() { return true; }, // API filtering
        });
        
        addrInput.addEventListener('input', function() {
            if (this.value.length >= 3) {
               fetch(`api/get_addresses.php?term=${encodeURIComponent(this.value)}`)
                   .then(r => r.json())
                   .then(data => {
                       addrInput.awesomplete.list = data.map(a => a.label);
                   });
            }
        });
        
        addrInput.addEventListener('awesomplete-selectcomplete', function(e) {
             // Basic address fill if needed, though API currently returns simple labels
             // You might want to parse it if the API returns structured data
        });
    }
}

function closeNestedClientModal() {
    document.getElementById('nestedClientModal').style.display = 'none';
}

function submitNestedClientForm() {
    const form = document.getElementById('nestedClientForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    
    fetch('actions/client_add.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update main form
            document.getElementById('id_client').value = data.client_id;
            const fullName = `${formData.get('nom')} ${formData.get('prenom') || ''}`.trim();
            document.getElementById('client_search').value = fullName;
            
            closeNestedClientModal();
            // Optional: Show success toast
        } else if (data.duplicate_check) {
             // Handle duplicates similar to existing logic
             const duplicates = data.duplicates;
             const container = document.getElementById('nestedDuplicatesContainer');
             container.innerHTML = '';
             duplicates.forEach(dup => {
                 const div = document.createElement('div');
                 div.style.padding = '8px';
                 div.style.borderBottom = '1px solid rgba(0,0,0,0.1)';
                 div.innerHTML = `<strong>${dup.nom} ${dup.prenom}</strong> - ${dup.email || 'Pas de mail'}`;
                 container.appendChild(div);
             });
             document.getElementById('nestedDuplicateCheckSection').style.display = 'block';
        } else {
            alert('Erreur: ' + (data.error || 'Erreur inconnue'));
        }
    })
    .catch(e => {
        console.error(e);
        alert('Erreur technique lors de la création du client.');
    });
}

// Ajouter les styles CSS pour les status badges
const style = document.createElement('style');
style.textContent = `
.status-planned { background: #3498db; color: white; }
.status-progress { background: #f39c12; color: white; }
.status-completed { background: #27ae60; color: white; }
.status-postponed { background: #e67e22; color: white; }
.status-cancelled { background: #e74c3c; color: white; }
`;
document.head.appendChild(style);
</script>