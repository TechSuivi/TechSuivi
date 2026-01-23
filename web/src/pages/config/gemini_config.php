<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Fonction pour lister les modèles disponibles
function listGeminiModels($apiKey) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
    $options = [
        'http' => [
            'method'  => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        return [];
    }
    
    $decoded = json_decode($result, true);
    $models = [];
    if (isset($decoded['models'])) {
        foreach ($decoded['models'] as $model) {
            if (isset($model['supportedGenerationMethods']) && in_array('generateContent', $model['supportedGenerationMethods'])) {
                // On garde juste le nom court (ex: gemini-pro) en enlevant "models/"
                $models[] = str_replace('models/', '', $model['name']);
            }
        }
    }
    return $models;
}

// Fonction pour tester l'API Gemini
function testGeminiApi($apiKey, $modelName) {
    // Nettoyer la clé API
    $apiKey = trim($apiKey);
    $modelName = trim($modelName);
    
    if (empty($modelName)) {
        $modelName = "gemini-1.5-flash"; // Fallback
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$modelName:generateContent?key=" . $apiKey;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => "Réponds simplement 'OK' si tu me reçois."]
                ]
            ]
        ]
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 10,
            'ignore_errors' => true 
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        $error = error_get_last();
        return ['success' => false, 'message' => "Erreur de connexion : " . $error['message']];
    }
    
    // Analyser la réponse
    $decoded = json_decode($result, true);
    
    // Vérifier les en-têtes HTTP pour le code statut
    if (isset($http_response_header)) {
        $status_line = $http_response_header[0];
        preg_match('/HTTP\/\d\.\d\s+(\d+)\s+(.*)/', $status_line, $matches);
        $status_code = isset($matches[1]) ? intval($matches[1]) : 0;
        
        // Si ce n'est pas un code 200, c'est une erreur
        if ($status_code !== 200) {
            $errorMessage = "Erreur HTTP $status_code ($modelName)";
            
            // Essayer de trouver le message d'erreur détaillé de Google
            if (isset($decoded['error']['message'])) {
                $errorMessage .= " : " . $decoded['error']['message'];
            }
            
            return ['success' => false, 'message' => $errorMessage];
        }
    }
    
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => true, 'message' => "Connexion réussie ! Réponse : " . $decoded['candidates'][0]['content']['parts'][0]['text']];
    } else {
        return ['success' => false, 'message' => "Réponse inattendue : " . substr(strip_tags($result), 0, 100) . "..."];
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gemini_api_key = $_POST['gemini_api_key'] ?? '';
    $gemini_model = $_POST['gemini_model'] ?? '';
    $stirling_pdf_url = $_POST['stirling_pdf_url'] ?? '';

    if (isset($_POST['save_config'])) {
        try {
            // Sauvegarde API KEY
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = 'gemini_api_key'");
            $stmt->execute([$gemini_api_key]);
            if ($stmt->rowCount() === 0) {
                 $check = $pdo->prepare("SELECT id FROM configuration WHERE config_key = 'gemini_api_key'");
                 $check->execute();
                 if (!$check->fetch()) {
                     $insert = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('gemini_api_key', ?, 'text', 'Clé API pour Google Gemini AI', 'ai')");
                     $insert->execute([$gemini_api_key]);
                 }
            }

            // Sauvegarde MODEL
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = 'gemini_model'");
            $stmt->execute([$gemini_model]);
            if ($stmt->rowCount() === 0) {
                 $check = $pdo->prepare("SELECT id FROM configuration WHERE config_key = 'gemini_model'");
                 $check->execute();
                 if (!$check->fetch()) {
                     $insert = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('gemini_model', ?, 'text', 'Modèle Google Gemini AI utilisé', 'ai')");
                     $insert->execute([$gemini_model]);
                 }
            }
            
            // Sauvegarde STIRLING PDF URL
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = 'stirling_pdf_url'");
            $stmt->execute([$stirling_pdf_url]);
            if ($stmt->rowCount() === 0) {
                 $check = $pdo->prepare("SELECT id FROM configuration WHERE config_key = 'stirling_pdf_url'");
                 $check->execute();
                 if (!$check->fetch()) {
                     $insert = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('stirling_pdf_url', ?, 'text', 'URL du serveur Stirling PDF pour OCR', 'ai')");
                     $insert->execute([$stirling_pdf_url]);
                 }
            }
            
            $success_message = "Configuration sauvegardée avec succès !";
        } catch (Exception $e) {
            $error_message = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }
    } elseif (isset($_POST['test_config'])) {
        if (empty($gemini_api_key)) {
            $test_message = ['success' => false, 'message' => "Veuillez d'abord entrer une clé API."];
        } else {
            $test_message = testGeminiApi($gemini_api_key, $gemini_model);
        }
    }
}

// Récupération de la configuration actuelle
$gemini_api_key = '';
$gemini_model = ''; // Default

try {
    // API KEY
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'gemini_api_key'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) $gemini_api_key = $result['config_value'];

    // MODEL
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'gemini_model'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) $gemini_model = $result['config_value'];

    // STIRLING PDF URL
    $stirling_pdf_url = '';
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'stirling_pdf_url'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) $stirling_pdf_url = $result['config_value'];

    // Override from POST (pour ne pas perdre la sélection lors d'un test ou erreur)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['gemini_api_key'])) $gemini_api_key = $_POST['gemini_api_key'];
        if (isset($_POST['gemini_model'])) $gemini_model = $_POST['gemini_model'];
        if (isset($_POST['stirling_pdf_url'])) $stirling_pdf_url = $_POST['stirling_pdf_url'];
    }

} catch (Exception $e) {
    if (!isset($error_message)) $error_message = "Erreur chargement config : " . $e->getMessage();
}

// Charger la liste des modèles si la clé est présente
$available_models = [];
if (!empty($gemini_api_key)) {
    $api_models = listGeminiModels($gemini_api_key);
    // On ajoute toujours les modèles standards "sûrs" car l'API ne les liste pas toujours selon la région
    $standard_models = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro'];
    $available_models = array_unique(array_merge($standard_models, $api_models));
}
// Fallback si vide ou erreur
if (empty($available_models)) {
    $available_models = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro']; 
}
// S'assurer que le modèle sélectionné est dans la liste (visuellement)
if (!empty($gemini_model) && !in_array($gemini_model, $available_models)) {
    array_unshift($available_models, $gemini_model);
}
?>

<div class="container">
    <h2>🧠 Configuration Google Gemini AI</h2>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($test_message)): ?>
        <div class="alert alert-<?= $test_message['success'] ? 'success' : 'error' ?>">
            <?= $test_message['success'] ? '✅' : '❌' ?> <?= htmlspecialchars($test_message['message']) ?>
        </div>
    <?php endif; ?>
    
    <div class="config-section">
        <p class="config-description">
            Configurez l'intégration avec l'intelligence artificielle Google Gemini.
            Cette clé sera utilisée pour les fonctionnalités d'assistant IA.
        </p>
        
        <form method="POST" class="config-form">
            <!-- Clé API -->
            <div class="form-group">
                <label for="gemini_api_key">🔑 Clé API Gemini</label>
                <div class="input-group">
                    <input type="password" 
                           id="gemini_api_key" 
                           name="gemini_api_key" 
                           value="<?= htmlspecialchars($gemini_api_key) ?>" 
                           class="form-control"
                           placeholder="Ex: AIzaSy..."
                           autocomplete="off">
                    <button type="button" class="btn-toggle-visibility" onclick="togglePasswordVisibility()">
                        👁️
                    </button>
                </div>
                <small class="form-help">
                    Vous pouvez obtenir une clé API sur <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.
                </small>
            </div>

            <!-- Modèle -->
            <div class="form-group">
                <label for="gemini_model">🤖 Modèle IA</label>
                <select id="gemini_model" name="gemini_model" class="form-control">
                    <option value="">-- Sélectionner un modèle --</option>
                    <?php foreach ($available_models as $model): ?>
                        <option value="<?= htmlspecialchars($model) ?>" <?= $gemini_model === $model ? 'selected' : '' ?>>
                            <?= htmlspecialchars($model) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">
                    Le modèle utilisé pour les réponses (ex: gemini-1.5-flash est rapide et économique).
                </small>
            </div>

            <!-- Stirling PDF URL -->
            <div class="form-group">
                <label for="stirling_pdf_url">📄 URL Stirling PDF (OCR)</label>
                <div class="input-group">
                    <input type="text" 
                           id="stirling_pdf_url" 
                           name="stirling_pdf_url" 
                           value="<?= htmlspecialchars($stirling_pdf_url) ?>" 
                           class="form-control"
                           placeholder="http://192.168.x.x:8080">
                </div>
                <small class="form-help">
                    L'URL de votre instance Stirling PDF locale. Si renseigné, l'OCR passera par ce serveur avant d'être analysé par l'IA.
                </small>
                
                <!-- Zone de test OCR -->
                <div style="margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 4px;">
                    <label style="font-size: 0.9em;">🧬 Tester la connexion Stirling OCR</label>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                        <input type="file" id="ocr_test_file" class="form-control" style="width: auto;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="testStirlingStats()">
                            Tester l'extraction
                        </button>
                    </div>
                    <div id="ocr_test_result" style="margin-top: 10px; font-family: monospace; font-size: 0.85em; white-space: pre-wrap; max-height: 100px; overflow-y: auto; background: white; padding: 5px; border: 1px solid #ccc; display: none;"></div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="form-actions">
                <button type="submit" name="save_config" class="btn btn-primary">
                    💾 Sauvegarder
                </button>
                <button type="submit" name="test_config" class="btn btn-secondary">
                    🧪 Tester la connexion
                </button>
            </div>
        </form>
    </div>
    <div class="config-section">
        <h3 style="display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
            <span>📏</span> Gestion des Règles / Modèles
        </h3>
        <p class="text-muted" style="margin-bottom: 25px;">
            Créez des règles (ou "personas") pour prédéfinir le comportement de l'IA.
            <br><em>Exemples : "Ton formel", "Correcteur orthographique", "Expert technique".</em>
        </p>
        
        <div class="row">
            <!-- Formulaire d'ajout -->
            <div class="col-md-5 mb-4">
                <div class="rule-card form-card">
                    <h5 class="card-title" id="formTitle">✨ Nouvelle Règle</h5>
                    <form id="saveRuleForm" onsubmit="saveRule(event)">
                        <input type="hidden" id="ruleId" value="">
                        <div class="form-group mb-3">
                            <label for="ruleName" class="form-label-sm">Nom de la règle</label>
                            <input type="text" class="form-control" id="ruleName" placeholder="Ex: Ton Formel" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="ruleContent" class="form-label-sm">Instruction système</label>
                            <textarea class="form-control" id="ruleContent" rows="5" 
                                placeholder="Indiquez ici comment l'IA doit se comporter.&#10;Ex: 'Réponds toujours de manière très polie et vouvoie l'interlocuteur.'" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="justify-content: center;" id="btnSave">
                            ➕ Ajouter la règle
                        </button>
                        <button type="button" class="btn btn-secondary w-100 mt-2" style="justify-content: center; display: none;" id="btnCancel" onclick="resetForm()">
                            ❌ Annuler
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Liste des règles -->
            <div class="col-md-7">
                <h5 class="mb-3" style="color: var(--accent-color);">📋 Règles existantes</h5>
                <div id="rulesList" class="rules-container">
                    <!-- Chargé via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadRules);

function loadRules() {
    const list = document.getElementById('rulesList');
    list.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
    
    const formData = new FormData();
    formData.append('action', 'list_rules');
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        list.innerHTML = '';
        if (data.success && data.rules.length > 0) {
            data.rules.forEach(rule => {
                const item = document.createElement('div');
                item.className = 'rule-item trigger-animation';
                // Echapper les guillemets pour le JS
                const safeName = rule.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeContent = rule.content.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, '\\n');
                
                item.innerHTML = `
                    <div class="rule-content">
                        <div class="rule-header">
                            <span class="rule-icon">📜</span>
                            <strong>${rule.name}</strong>
                        </div>
                        <div class="rule-text">${rule.content}</div>
                    </div>
                    <div class="rule-actions" style="display:flex; flex-direction:column; gap:5px;">
                        <button class="btn-icon-edit" onclick="editRule(${rule.id}, '${safeName}', '${safeContent}')" title="Modifier">
                            ✏️
                        </button>
                        <button class="btn-icon-delete" onclick="deleteRule(${rule.id})" title="Supprimer">
                            🗑️
                        </button>
                    </div>
                `;
                list.appendChild(item);
            });
        } else {
            list.innerHTML = '<div class="text-center p-3 text-muted">Aucune règle définie.</div>';
        }
    })
    .catch(e => {
        list.innerHTML = '<div class="alert alert-danger">Erreur chargement</div>';
    });
}

function saveRule(e) {
    e.preventDefault();
    const id = document.getElementById('ruleId').value;
    const name = document.getElementById('ruleName').value;
    const content = document.getElementById('ruleContent').value;
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('content', content);
    
    if (id) {
        // Mode ÉDITION
        formData.append('action', 'edit_rule');
        formData.append('id', id);
    } else {
        // Mode AJOUT
        formData.append('action', 'add_rule');
    }
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            resetForm();
            loadRules();
        } else {
            alert('Erreur: ' + data.message);
        }
    });
}

function editRule(id, name, content) {
    document.getElementById('ruleId').value = id;
    document.getElementById('ruleName').value = name;
    document.getElementById('ruleContent').value = content; // textarea decode auto
    
    document.getElementById('formTitle').innerText = '✏️ Modifier la Règle';
    document.getElementById('btnSave').innerHTML = '💾 Enregistrer les modifs';
    document.getElementById('btnCancel').style.display = 'flex';
    
    // Scroll vers le formulaire
    document.querySelector('.form-card').scrollIntoView({behavior: 'smooth'});
}

function resetForm() {
    document.getElementById('saveRuleForm').reset();
    document.getElementById('ruleId').value = '';
    
    document.getElementById('formTitle').innerText = '✨ Nouvelle Règle';
    document.getElementById('btnSave').innerHTML = '➕ Ajouter la règle';
    document.getElementById('btnCancel').style.display = 'none';
}

function deleteRule(id) {
    if(!confirm('Supprimer cette règle ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_rule');
    formData.append('id', id);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            // Si on supprimait la règle en cours d'édition
            if(document.getElementById('ruleId').value == id) {
                resetForm();
            }
            loadRules();
        }
        else alert('Erreur suppression');
    });
}
</script>

<style>
.config-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
    margin-top: 20px;
    border: 1px solid #dee2e6;
}

.config-description {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 4px;
    color: #1565c0;
    font-size: 14px;
}

.config-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: var(--accent-color);
    font-size: 16px;
}

.input-group {
    display: flex;
    gap: 10px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    font-family: monospace;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(52, 144, 220, 0.1);
}

.btn-toggle-visibility {
    background: #e9ecef;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 0 15px;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.2s;
}

.btn-toggle-visibility:hover {
    background: #dee2e6;
}

.form-help {
    display: block;
    margin-top: 8px;
    color: #6c757d;
    font-size: 13px;
}

.form-help a {
    color: var(--accent-color);
    text-decoration: none;
}

.form-help a:hover {
    text-decoration: underline;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(52, 144, 220, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: bold;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Mode sombre */
body.dark .config-section {
    background-color: #2c2c2c;
    border-color: #444;
}

body.dark .config-description {
    background-color: #1a365d;
    color: #90cdf4;
    border-left-color: #3182ce;
}

body.dark .form-control {
    background-color: #333;
    border-color: #555;
    color: var(--text-color-dark);
}

body.dark .form-control:focus {
    border-color: var(--accent-color);
}

body.dark .btn-toggle-visibility {
    background-color: #333;
    border-color: #555;
    color: #fff;
}

body.dark .btn-toggle-visibility:hover {
    background-color: #444;
}

body.dark .form-help {
    color: #aaa;
}

body.dark .alert-success {
    background-color: #1e4d2b;
    color: #a3d9a5;
    border-color: #2d5a3d;
}

body.dark .alert-error {
    background-color: #4d1e1e;
    color: #f5a3a3;
    border-color: #5a2d2d;
}

/* Responsive pour les boutons */
    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Styles Règles IA */
.rule-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.rule-card .card-title {
    color: var(--accent-color);
    font-weight: 600;
    margin-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 10px;
}

.form-label-sm {
    font-size: 0.9em;
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
    display: block;
}

.rules-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 600px;
    overflow-y: auto;
    padding-right: 5px;
}

.rule-item {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: all 0.2s;
    border-left: 4px solid var(--accent-color);
}

.rule-item:hover {
    transform: translateX(2px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.rule-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: #2c3e50;
    font-size: 1.05em;
}

.rule-text {
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #666;
    font-size: 0.9em;
    line-height: 1.4;
    white-space: pre-line;
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
}

.btn-icon-edit {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1.2em;
    opacity: 0.6;
    transition: opacity 0.2s, transform 0.2s;
    padding: 5px;
    color: var(--accent-color);
}

.btn-icon-edit:hover {
    opacity: 1;
    transform: scale(1.1);
}

.btn-icon-delete {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1.2em;
    opacity: 0.6;
    transition: opacity 0.2s, transform 0.2s;
    padding: 5px;
}

.btn-icon-delete:hover {
    opacity: 1;
    transform: scale(1.1);
}

/* Dark Mode Adjustments */
body.dark .rule-card, body.dark .rule-item {
    background-color: #333;
    border-color: #555;
}

body.dark .rule-text {
    background-color: #2a2a2a;
    color: #ccc;
}

body.dark .form-label-sm {
    color: #bbb;
}

body.dark .rule-header {
    color: #e0e0e0;
}</style>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('gemini_api_key');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

function testStirlingStats() {
    const url = document.getElementById('stirling_pdf_url').value;
    const fileInput = document.getElementById('ocr_test_file');
    const resultDiv = document.getElementById('ocr_test_result');
    
    if (!url) {
        alert("Veuillez entrer une URL Stirling PDF d'abord.");
        return;
    }
    if (fileInput.files.length === 0) {
        alert("Veuillez sélectionner un fichier pour le test.");
        return;
    }
    
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '⏳ Envoi au serveur OCR...';
    
    const formData = new FormData();
    formData.append('action', 'test_ocr');
    formData.append('stirling_url', url);
    formData.append('test_file', fileInput.files[0]);
    
    fetch('api/ai_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            resultDiv.style.color = 'green';
            resultDiv.innerText = "✅ SUCCÈS - Texte extrait :\n\n" + data.text.substring(0, 500) + (data.text.length > 500 ? '...' : '');
        } else {
            resultDiv.style.color = 'red';
            resultDiv.innerText = "❌ ERREUR : " + data.message;
        }
    })
    .catch(e => {
        resultDiv.style.color = 'red';
        resultDiv.innerText = "❌ ERREUR JS : " + e.message;
    });
}
</script>
