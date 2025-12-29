<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = ''; // Pour les messages de succès ou d'erreur

// Fonction pour générer un ID hexadécimal basé sur la date et l'heure
function generateInterventionId() {
    // Obtenir le timestamp Unix actuel
    $timestamp = time();
    // Convertir en hexadécimal et mettre en majuscules
    return strtoupper(dechex($timestamp));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $id_client = trim($_POST['id_client'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $en_cours = isset($_POST['en_cours']) ? 1 : 0;
    $info = trim($_POST['info'] ?? '');

    $errors = [];
    if (empty($id_client) || !is_numeric($id_client)) {
        $errors[] = 'Veuillez sélectionner un client valide.';
    }
    if (empty($date)) {
        $errors[] = 'La date est obligatoire.';
    }
    if (empty($info)) {
        $errors[] = 'Les informations sont obligatoires.';
    }

    if (!empty($errors)) {
        $message = '<p style="color: red;">' . implode('<br>', $errors) . '</p>';
    } else {
        try {
            // Générer l'ID hexadécimal
            $intervention_id = generateInterventionId();
            
            $sql = "INSERT INTO inter (id, id_client, date, en_cours, statut_id, info, nettoyage, info_log, note_user) VALUES (:id, :id_client, :date, :en_cours, :statut_id, :info, '', '', '')";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $intervention_id);
            $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':en_cours', $en_cours, PDO::PARAM_INT);
            $statut_id = 1; // Statut par défaut: 1 (en cours)
            $stmt->bindParam(':statut_id', $statut_id, PDO::PARAM_INT);
            $stmt->bindParam(':info', $info);

            if ($stmt->execute()) {
                $message = '<p style="color: green;">Intervention ajoutée avec succès ! ID: ' . htmlspecialchars($intervention_id) .
                          ' <a href="index.php?page=interventions_list">Retour à la liste</a>' .
                          ' <button onclick="printIntervention(\'' . htmlspecialchars($intervention_id) . '\')" style="margin-left: 10px; padding: 5px 10px; background-color: var(--accent-color); color: white; border: none; border-radius: 4px; cursor: pointer;">Imprimer la fiche</button></p>';
                // Vider les champs après succès
                $_POST = [];
                $id_client = $client_name = $date = $info = '';
                $en_cours = 1; // Valeur par défaut
            } else {
                $message = '<p style="color: red;">Erreur lors de l\'ajout de l\'intervention.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<p style="color: red;">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</p>';
}

// Valeurs par défaut
if (!isset($id_client)) $id_client = '';
if (!isset($client_name)) $client_name = '';
if (!isset($date)) $date = date('Y-m-d H:i:s'); // Date actuelle par défaut
if (!isset($en_cours)) $en_cours = 1; // En cours par défaut
if (!isset($info)) $info = '';
?>

<style>
/* Styles modernes pour la page d'ajout d'intervention */
.intervention-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
    max-width: 900px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95em;
}

.form-control {
    width: 100%;
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
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

textarea.form-control {
    resize: vertical;
    font-family: inherit;
}

.form-hint {
    color: var(--text-muted);
    font-size: 0.85em;
    margin-top: 6px;
    display: block;
}

.client-search-container {
    position: relative;
}

#client_suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-color, white);
    border: 1px solid var(--border-color, #ccc);
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
    user-select: none;
}

.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    margin-top: 25px;
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
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
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
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

.alert-icon {
    font-size: 1.5em;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .page-header h1 {
        font-size: 1.2em;
    }
}
</style>

<div class="intervention-page">
    <div class="page-header">
        <h1>
            <span>🔧</span>
            Ajouter une nouvelle intervention
        </h1>
    </div>

    <?php if ($message): ?>
        <?php
        // Determine message type
        $isSuccess = strpos($message, 'succès') !== false || strpos($message, 'green') !== false;
        $isError = strpos($message, 'Erreur') !== false || strpos($message, 'red') !== false;
        $alertClass = $isSuccess ? 'alert-success' : ($isError ? 'alert-error' : 'alert-success');
        $alertIcon = $isSuccess ? '✅' : ($isError ? '⚠️' : 'ℹ️');
        
        // Strip inline styles from message
        $cleanMessage = strip_tags($message, '<a><br><button>');
        ?>
        <div class="alert <?= $alertClass ?>">
            <span class="alert-icon"><?= $alertIcon ?></span>
            <div><?= $cleanMessage ?></div>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form action="index.php?page=interventions_add" method="POST">
            <div class="form-group">
                <label for="client_search">Client</label>
                <div class="client-search-container">
                    <input type="text"
                           id="client_search"
                           name="client_name"
                           class="form-control"
                           value="<?= htmlspecialchars($client_name) ?>"
                           placeholder="Tapez pour rechercher un client..."
                           required
                           autocomplete="off">
                    <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($id_client) ?>">
                    <div id="client_suggestions"></div>
                </div>
                <small class="form-hint">Tapez au moins 2 caractères pour rechercher par nom, prénom ou téléphone</small>
            </div>
            
            <div class="form-group">
                <label for="date">Date et heure</label>
                <input type="datetime-local" 
                       id="date" 
                       name="date" 
                       class="form-control"
                       value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($date))) ?>" 
                       required>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" 
                       id="en_cours" 
                       name="en_cours" 
                       value="1" 
                       <?= $en_cours ? 'checked' : '' ?>>
                <label for="en_cours">Intervention en cours</label>
            </div>
            
            <div class="form-group">
                <label for="info">Informations</label>
                <textarea id="info" 
                          name="info" 
                          rows="6" 
                          class="form-control"
                          required 
                          placeholder="Décrivez l'intervention à effectuer..."><?= htmlspecialchars($info) ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span>➕</span>
                    Ajouter l'intervention
                </button>
                <a href="index.php?page=interventions_list" class="btn btn-secondary">
                    <span>❌</span>
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('client_search');
    const clientId = document.getElementById('id_client');
    const suggestions = document.getElementById('client_suggestions');
    let searchTimeout;
    let selectedIndex = -1;

    // Fonction pour effectuer la recherche
    function searchClients(query) {
        if (query.length < 2) {
            suggestions.style.display = 'none';
            return;
        }

        fetch(`api/search_clients.php?q=${encodeURIComponent(query)}&limit=10`)
            .then(response => response.json())
            .then(data => {
                suggestions.innerHTML = '';
                selectedIndex = -1;

                if (data.length === 0) {
                    suggestions.innerHTML = '<div style="padding: 8px; color: var(--text-muted, #666); font-style: italic;">Aucun client trouvé</div>';
                } else {
                    data.forEach((client, index) => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid var(--border-light, #eee); color: var(--text-color, #333); transition: background-color 0.2s ease;';
                        div.innerHTML = client.label;
                        div.dataset.id = client.id;
                        div.dataset.value = client.value;
                        div.dataset.index = index;

                        div.addEventListener('mouseenter', function() {
                            // Retirer la sélection précédente
                            suggestions.querySelectorAll('div').forEach(d => d.style.backgroundColor = '');
                            // Sélectionner l'élément survolé
                            this.style.backgroundColor = 'var(--hover-color, #f0f0f0)';
                            selectedIndex = parseInt(this.dataset.index);
                        });

                        div.addEventListener('click', function() {
                            selectClient(this.dataset.id, this.dataset.value);
                        });

                        suggestions.appendChild(div);
                    });
                }

                suggestions.style.display = 'block';
            })
            .catch(error => {
                console.error('Erreur lors de la recherche:', error);
                suggestions.innerHTML = '<div style="padding: 8px; color: var(--error-color, red);">Erreur lors de la recherche</div>';
                suggestions.style.display = 'block';
            });
    }

    // Fonction pour sélectionner un client
    function selectClient(id, name) {
        clientId.value = id;
        clientSearch.value = name;
        suggestions.style.display = 'none';
        selectedIndex = -1;
    }

    // Événement de saisie dans le champ de recherche
    clientSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Réinitialiser l'ID si le champ est vidé
        if (query === '') {
            clientId.value = '';
        }

        // Annuler la recherche précédente
        clearTimeout(searchTimeout);
        
        // Lancer une nouvelle recherche après un délai
        searchTimeout = setTimeout(() => {
            searchClients(query);
        }, 300);
    });

    // Gestion des touches du clavier
    clientSearch.addEventListener('keydown', function(e) {
        const items = suggestions.querySelectorAll('div[data-index]');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                const item = items[selectedIndex];
                selectClient(item.dataset.id, item.dataset.value);
            }
        } else if (e.key === 'Escape') {
            suggestions.style.display = 'none';
            selectedIndex = -1;
        }
    });

    // Fonction pour mettre à jour la sélection visuelle
    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.style.backgroundColor = 'var(--hover-color, #f0f0f0)';
            } else {
                item.style.backgroundColor = '';
            }
        });
    }

    // Fermer les suggestions en cliquant ailleurs
    document.addEventListener('click', function(e) {
        if (!clientSearch.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
            selectedIndex = -1;
        }
    });

    // Validation du formulaire
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!clientId.value) {
            e.preventDefault();
            alert('Veuillez sélectionner un client dans la liste de suggestions.');
            clientSearch.focus();
        }
    });
});

// Fonction pour imprimer la fiche d'intervention
function printIntervention(interventionId) {
    // Ouvrir une nouvelle fenêtre pour l'impression (page autonome sans menu)
    const printWindow = window.open(`print_intervention.php?id=${interventionId}`, '_blank', 'width=800,height=600');
    
    // La page autonome gère automatiquement l'impression
}
</script>