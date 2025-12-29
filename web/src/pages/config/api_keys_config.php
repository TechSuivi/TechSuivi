<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = '';
$messageType = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_key') {
            // Ajouter une nouvelle clé API AutoIt
            $keyValue = trim($_POST['key_value'] ?? '');
            
            // Validation
            if (empty($keyValue)) {
                throw new Exception('La valeur de la clé est obligatoire.');
            }
            
            if (strlen($keyValue) < 8) {
                throw new Exception('La clé API doit contenir au moins 8 caractères.');
            }
            
            // Vérifier que la clé n'existe pas déjà
            $configKey = 'api_key_autoit_client';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuration WHERE config_key = ?");
            $stmt->execute([$configKey]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('La clé AutoIt Client Access existe déjà. Supprimez-la d\'abord si vous voulez la remplacer.');
            }
            
            // Insérer la nouvelle clé
            $stmt = $pdo->prepare("
                INSERT INTO configuration (config_key, config_value, config_type, description, category) 
                VALUES (?, ?, 'text', ?, 'api_keys')
            ");
            $stmt->execute([$configKey, $keyValue, 'AutoIt Client Access']);
            
            $message = 'Clé API AutoIt ajoutée avec succès !';
            $messageType = 'success';
            
        } elseif ($action === 'update_key') {
            // Mettre à jour la clé AutoIt existante
            $keyValue = trim($_POST['key_value'] ?? '');
            
            if (empty($keyValue)) {
                throw new Exception('La valeur de la clé est obligatoire.');
            }
            
            if (strlen($keyValue) < 8) {
                throw new Exception('La clé API doit contenir au moins 8 caractères.');
            }
            
            // Mettre à jour la clé AutoIt
            $configKey = 'api_key_autoit_client';
            $stmt = $pdo->prepare("
                UPDATE configuration 
                SET config_value = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE config_key = ?
            ");
            $stmt->execute([$keyValue, $configKey]);
            
            $message = 'Clé API AutoIt mise à jour avec succès !';
            $messageType = 'success';
            
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer la clé API existante
try {
    $stmt = $pdo->prepare("
        SELECT config_key, config_value, description, created_at, updated_at
        FROM configuration 
        WHERE config_key = 'api_key_autoit_client' AND category = 'api_keys' AND config_type = 'text'
    ");
    $stmt->execute();
    $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $apiKey = null;
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<h1>🔑 Clé API AutoIt</h1>

<?php if (!empty($message)): ?>
    <div class="message <?= $messageType ?>">
        <?php if ($messageType === 'success'): ?>
            <span style="color: green;">✅ <?= htmlspecialchars($message) ?></span>
        <?php else: ?>
            <span style="color: red;">❌ <?= htmlspecialchars($message) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="max-width: 800px;">
    <!-- Section d'information -->
    <div class="info-section">
        <h3 style="margin-top: 0;">🔐 Configuration de la clé API AutoIt</h3>
        <p>
            Configurez ici la clé API utilisée pour l'authentification des requêtes AutoIt.
            Si aucune clé n'est configurée, la clé par défaut <code>autoit_key_2025</code> sera utilisée.
        </p>
    </div>

    <?php if ($apiKey): ?>
        <!-- Clé existante -->
        <div class="keys-list-section">
            <h3 style="margin-top: 0; color: #495057;">🔑 Clé API actuelle</h3>
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <strong style="color: #495057;">AutoIt Client Access</strong>
                        <br>
                        <small style="color: #6c757d;">
                            Créée le <?= date('d/m/Y à H:i', strtotime($apiKey['created_at'])) ?>
                            <?php if ($apiKey['updated_at'] !== $apiKey['created_at']): ?>
                                • Modifiée le <?= date('d/m/Y à H:i', strtotime($apiKey['updated_at'])) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div>
                        <button onclick="showEditForm()"
                                style="background-color: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            ✏️ Modifier
                        </button>
                    </div>
                </div>
                
                <div style="font-family: monospace; background-color: #ffffff; padding: 12px; border-radius: 4px; border: 1px solid #ced4da;">
                    <span id="keyDisplay">
                        <?= str_repeat('*', max(0, strlen($apiKey['config_value']) - 8)) . substr($apiKey['config_value'], -8) ?>
                    </span>
                    <button onclick="toggleKeyVisibility()" 
                            style="margin-left: 10px; background: none; border: none; cursor: pointer; font-size: 16px;">
                        👁️
                    </button>
                </div>
            </div>
        </div>

        <!-- Formulaire de modification (masqué par défaut) -->
        <div id="editForm" style="display: none;">
            <div class="form-section">
                <h3 style="margin-top: 0; color: #495057;">✏️ Modifier la clé API</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_key">
                    
                    <div style="margin-bottom: 20px;">
                        <label for="key_value_edit" style="display: block; margin-bottom: 8px; font-weight: bold; color: #495057;">
                            🔑 Nouvelle clé API :
                        </label>
                        <input type="text" 
                               id="key_value_edit" 
                               name="key_value" 
                               required
                               value="<?= htmlspecialchars($apiKey['config_value']) ?>"
                               placeholder="Saisissez votre nouvelle clé API sécurisée (min. 8 caractères)"
                               style="width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        <small style="color: #6c757d; font-size: 12px;">
                            Clé API sécurisée pour l'accès AutoIt (minimum 8 caractères)
                        </small>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" 
                                style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-right: 10px;">
                            ✅ Mettre à jour
                        </button>
                        <button type="button" onclick="hideEditForm()" 
                                style="background-color: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer;">
                            ❌ Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Formulaire d'ajout -->
        <div class="form-section">
            <h3 style="margin-top: 0; color: #495057;">🔑 Configurer la clé API</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_key">
                
                <div style="margin-bottom: 20px;">
                    <label for="key_value" style="display: block; margin-bottom: 8px; font-weight: bold; color: #495057;">
                        🔑 Clé API AutoIt :
                    </label>
                    <input type="text" 
                           id="key_value" 
                           name="key_value" 
                           required
                           placeholder="Saisissez votre clé API sécurisée (min. 8 caractères)"
                           style="width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                    <small style="color: #6c757d; font-size: 12px;">
                        Clé API sécurisée pour l'accès AutoIt (minimum 8 caractères)
                    </small>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" 
                            style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        ✅ Configurer la clé API
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
/* Sections principales */
.info-section {
    background-color: #e3f2fd;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #2196f3;
    margin-bottom: 20px;
    color: #666;
}

.info-section h3 {
    color: #1976d2;
}

.info-section code {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #d63384;
}

.form-section {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-bottom: 20px;
}

.form-section h3 {
    color: #495057;
}

.keys-list-section {
    background-color: #ffffff;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.keys-list-section h3 {
    color: #495057;
}

/* Messages */
.message {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-weight: bold;
}

.message.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.message.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

/* Interactions */
button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

input:focus, textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* Mode sombre */
body.dark .info-section {
    background-color: #2c3e50;
    border-color: #34495e;
    color: #ecf0f1;
}

body.dark .info-section h3 {
    color: #3498db;
}

body.dark .info-section code {
    background-color: #34495e;
    color: #e74c3c;
}

body.dark .form-section {
    background-color: #34495e;
    border-color: #2c3e50;
}

body.dark .form-section h3 {
    color: #ecf0f1;
}

body.dark .keys-list-section {
    background-color: #34495e;
    border-color: #2c3e50;
}

body.dark .keys-list-section h3 {
    color: #ecf0f1;
}

body.dark input, body.dark textarea {
    background-color: #2c3e50;
    color: #ecf0f1;
    border-color: #34495e;
}

body.dark input:focus, body.dark textarea:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
}

body.dark label, body.dark small {
    color: #ecf0f1 !important;
}

body.dark .keys-list-section > div {
    background-color: #2c3e50 !important;
    border-color: #34495e !important;
}

body.dark .keys-list-section > div > div:last-child {
    background-color: #34495e !important;
    border-color: #2c3e50 !important;
}
</style>

<script>
let keyVisible = false;
const actualKey = <?= json_encode($apiKey['config_value'] ?? '') ?>;

function toggleKeyVisibility() {
    const keyDisplay = document.getElementById('keyDisplay');
    if (keyVisible) {
        keyDisplay.textContent = actualKey.substring(0, actualKey.length - 8).replace(/./g, '*') + actualKey.substring(actualKey.length - 8);
        keyVisible = false;
    } else {
        keyDisplay.textContent = actualKey;
        keyVisible = true;
    }
}

function showEditForm() {
    document.getElementById('editForm').style.display = 'block';
}

function hideEditForm() {
    document.getElementById('editForm').style.display = 'none';
}

function deleteKey() {
    if (confirm('Êtes-vous sûr de vouloir supprimer la clé API AutoIt ?\n\nCette action est irréversible et l\'API utilisera la clé par défaut.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_key">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>