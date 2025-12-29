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
        // Récupérer les valeurs du formulaire
        $catalogUrl = trim($_POST['catalog_url'] ?? '');
        $apiToken = trim($_POST['api_token'] ?? '');
        
        // Validation basique
        if (empty($catalogUrl)) {
            throw new Exception('L\'URL du catalogue est obligatoire.');
        }
        
        if (!filter_var($catalogUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('L\'URL du catalogue n\'est pas valide.');
        }
        
        if (empty($apiToken)) {
            throw new Exception('Le token API est obligatoire.');
        }
        
        // Mettre à jour ou insérer les configurations
        $configs = [
            'acadia_catalog_url' => $catalogUrl,
            'acadia_api_token' => $apiToken
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO configuration (config_key, config_value, config_type, description, category) 
                VALUES (?, ?, 'text', ?, 'acadia')
                ON DUPLICATE KEY UPDATE 
                    config_value = VALUES(config_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $description = ($key === 'acadia_catalog_url') 
                ? 'URL du catalogue Acadia pour l\'import des produits'
                : 'Token API pour l\'accès au catalogue Acadia';
                
            $stmt->execute([$key, $value, $description]);
        }
        
        $message = 'Configuration Acadia mise à jour avec succès !';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Erreur : ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer les configurations actuelles
$currentConfigs = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'acadia'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentConfigs[$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    // Si la table n'existe pas encore, utiliser des valeurs vides
    $currentConfigs = [
        'acadia_catalog_url' => '',
        'acadia_api_token' => ''
    ];
}

// Extraire le token de l'URL actuelle si elle contient un token
$currentUrl = $currentConfigs['acadia_catalog_url'] ?? '';
$currentToken = $currentConfigs['acadia_api_token'] ?? '';

// Si le token n'est pas défini séparément, essayer de l'extraire de l'URL
if (empty($currentToken) && !empty($currentUrl)) {
    if (preg_match('/token=([^&]+)/', $currentUrl, $matches)) {
        $currentToken = $matches[1];
    }
}
// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<h1>Configuration Catalogue Acadia</h1>

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
    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; border: 1px solid #2196f3; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #1976d2;">🔧 Configuration du catalogue Acadia</h3>
        <p style="color: #666;">
            Configurez ici l'URL et le token API pour accéder au catalogue Acadia. Ces paramètres sont utilisés 
            pour l'import automatique des produits dans votre base de données.
        </p>
        <div style="background-color: #fff; padding: 15px; border-radius: 4px; margin-top: 15px; border-left: 4px solid #ff9800;">
            <h4 style="margin-top: 0; color: #f57c00;">⚠️ Important</h4>
            <ul style="color: #666; margin-bottom: 0;">
                <li>Assurez-vous que le token API est valide et actif</li>
                <li>L'URL doit être accessible depuis votre serveur</li>
                <li>Ces modifications affecteront tous les imports de catalogue</li>
            </ul>
        </div>
    </div>

    <!-- Formulaire de configuration -->
    <form method="POST" style="background-color: #f8f9fa; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6;">
        <h3 style="margin-top: 0; color: #495057;">📝 Paramètres de connexion</h3>
        
        <div style="margin-bottom: 20px;">
            <label for="catalog_url" style="display: block; margin-bottom: 8px; font-weight: bold; color: #495057;">
                🌐 URL du catalogue Acadia :
            </label>
            <input type="url" 
                   id="catalog_url" 
                   name="catalog_url" 
                   value="<?= htmlspecialchars($currentUrl) ?>"
                   required
                   style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
            <small style="color: #6c757d; font-size: 12px;">
                URL complète pour accéder au catalogue CSV d'Acadia
            </small>
        </div>

        <div style="margin-bottom: 25px;">
            <label for="api_token" style="display: block; margin-bottom: 8px; font-weight: bold; color: #495057;">
                🔑 Token API :
            </label>
            <input type="text" 
                   id="api_token" 
                   name="api_token" 
                   value="<?= htmlspecialchars($currentToken) ?>"
                   required
                   style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
            <small style="color: #6c757d; font-size: 12px;">
                Token d'authentification fourni par Acadia
            </small>
        </div>

        <div style="display: flex; gap: 15px; align-items: center;">
            <button type="submit" 
                    style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px;">
                💾 Enregistrer la configuration
            </button>
            
            <a href="index.php?page=catalog_import" 
               style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;">
                📦 Tester l'import
            </a>
        </div>
    </form>

    <!-- Section de test -->
    <div style="background-color: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffc107; margin-top: 20px;">
        <h3 style="margin-top: 0; color: #856404;">🧪 Test de connexion</h3>
        <p style="color: #856404; margin-bottom: 15px;">
            Vous pouvez tester la configuration en cliquant sur le bouton ci-dessous :
        </p>
        
        <button onclick="testConnection()" 
                style="background-color: #ffc107; color: #212529; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
            🔍 Tester la connexion
        </button>
        
        <div id="test-result" style="margin-top: 15px; padding: 10px; border-radius: 4px; display: none;"></div>
    </div>

    <!-- Informations sur l'utilisation -->
    <div style="background-color: #d1ecf1; padding: 20px; border-radius: 8px; border: 1px solid #bee5eb; margin-top: 20px;">
        <h3 style="margin-top: 0; color: #0c5460;">📋 Où ces paramètres sont utilisés</h3>
        <ul style="color: #0c5460; margin-bottom: 0;">
            <li><strong>Import manuel :</strong> Page "Import Catalogue Acadia" dans les paramètres système</li>
            <li><strong>Synchronisation API :</strong> Script <code>sync_catalog.php</code> pour les synchronisations automatiques</li>
            <li><strong>Applications externes :</strong> Toute application utilisant l'API de synchronisation</li>
        </ul>
    </div>
</div>

<style>
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

button:hover, input[type="submit"]:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

#test-result.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#test-result.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<script>
function testConnection() {
    const catalogUrl = document.getElementById('catalog_url').value;
    const apiToken = document.getElementById('api_token').value;
    const resultDiv = document.getElementById('test-result');
    
    if (!catalogUrl || !apiToken) {
        resultDiv.innerHTML = '❌ Veuillez remplir tous les champs avant de tester.';
        resultDiv.className = 'error';
        resultDiv.style.display = 'block';
        return;
    }
    
    resultDiv.innerHTML = '⏳ Test en cours...';
    resultDiv.className = '';
    resultDiv.style.display = 'block';
    
    // Construire l'URL de test
    let testUrl = catalogUrl;
    if (!testUrl.includes('token=')) {
        const separator = testUrl.includes('?') ? '&' : '?';
        testUrl += separator + 'token=' + encodeURIComponent(apiToken);
    }
    
    // Test simple avec fetch
    fetch(testUrl, {
        method: 'HEAD',
        mode: 'no-cors'
    })
    .then(() => {
        resultDiv.innerHTML = '✅ La connexion semble fonctionner. Vous pouvez maintenant tester l\'import complet.';
        resultDiv.className = 'success';
    })
    .catch(error => {
        resultDiv.innerHTML = '❌ Erreur de connexion. Vérifiez l\'URL et le token API.';
        resultDiv.className = 'error';
    });
}
</script>