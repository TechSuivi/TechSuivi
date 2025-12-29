<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

$message = '';
$messageType = '';

// Créer la table oauth2_config si elle n'existe pas
try {
    $createTable = "CREATE TABLE IF NOT EXISTS oauth2_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL,
        client_id TEXT NOT NULL,
        client_secret TEXT NOT NULL,
        tenant_id VARCHAR(255) DEFAULT NULL,
        redirect_uri TEXT NOT NULL,
        scopes TEXT NOT NULL,
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_provider (provider)
    )";
    
    $pdo->exec($createTable);
} catch (PDOException $e) {
    error_log("Erreur création table oauth2_config: " . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $provider = $_POST['provider'] ?? '';
        $client_id = $_POST['client_id'] ?? '';
        $client_secret = $_POST['client_secret'] ?? '';
        $tenant_id = $_POST['tenant_id'] ?? '';
        $redirect_uri = $_POST['redirect_uri'] ?? '';
        $scopes = $_POST['scopes'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($provider) || empty($client_id) || empty($client_secret) || empty($redirect_uri)) {
            throw new Exception('Tous les champs obligatoires doivent être remplis');
        }
        
        // Vérifier si la configuration existe déjà
        $stmt = $pdo->prepare("SELECT id FROM oauth2_config WHERE provider = ?");
        $stmt->execute([$provider]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE oauth2_config SET 
                client_id = ?, client_secret = ?, tenant_id = ?, 
                redirect_uri = ?, scopes = ?, is_active = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE provider = ?");
            $stmt->execute([$client_id, $client_secret, $tenant_id, $redirect_uri, $scopes, $is_active, $provider]);
            $message = "Configuration OAuth2 mise à jour avec succès pour $provider";
        } else {
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO oauth2_config 
                (provider, client_id, client_secret, tenant_id, redirect_uri, scopes, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$provider, $client_id, $client_secret, $tenant_id, $redirect_uri, $scopes, $is_active]);
            $message = "Configuration OAuth2 créée avec succès pour $provider";
        }
        
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer les configurations existantes
$configs = [];
$configsByProvider = [];
try {
    $stmt = $pdo->query("SELECT * FROM oauth2_config ORDER BY provider");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($configs as $config) {
        $configsByProvider[$config['provider']] = $config;
    }
} catch (PDOException $e) {
    error_log("Erreur récupération configs OAuth2: " . $e->getMessage());
}

// Générer l'URL de redirection automatiquement
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host;
$redirect_uri_google = $base_url . '/api/oauth2_callback.php?provider=google';
$redirect_uri_outlook = $base_url . '/api/oauth2_callback.php?provider=outlook';
?>

<style>
.oauth2-page {
    background: var(--bg-color);
    color: var(--text-color);
}

.page-header {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.6em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-card {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.info-card h5 {
    margin-top: 0;
    color: #1976d2;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card ul {
    margin-bottom: 0;
}

.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    border-bottom: 2px solid var(--border-color);
}

.tab-button {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-color);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}

.tab-button:hover {
    background: var(--hover-bg);
}

.tab-button.active {
    border-bottom-color: #e74c3c;
    color: #e74c3c;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.config-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.warning-box h6 {
    margin-top: 0;
    color: #856404;
}

.warning-box ol {
    margin-bottom: 0;
}

.warning-box code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    color: #c7254e;
    font-size: 0.9em;
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
    border-color: #e74c3c;
    box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
}

.form-control[readonly] {
    background: var(--hover-bg);
    cursor: not-allowed;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 0.85em;
    color: var(--text-muted);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}

.form-check-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #e74c3c;
}

.form-check-label {
    cursor: pointer;
    user-select: none;
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
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.9em;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
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

.status-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    background: var(--card-bg);
}

.status-table thead {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.status-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9em;
}

.status-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}

.status-table tr:last-child td {
    border-bottom: none;
}

.status-table tbody tr:hover {
    background: var(--hover-bg);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-secondary {
    background: #e2e3e5;
    color: #6c757d;
}

body.dark .info-card {
    background: #1a3a52;
    border-left-color: #2196f3;
}

body.dark .info-card h5 {
    color: #64b5f6;
}

body.dark .warning-box {
    background: #3d3416;
    border-left-color: #ffc107;
}

body.dark .warning-box h6 {
    color: #ffeb3b;
}

body.dark .warning-box code {
    background: #2d2d2d;
    color: #ff79c6;
}
</style>

<div class="oauth2-page">
    <div class="page-header">
        <h1>
            <span>🔐</span>
            Configuration OAuth2
        </h1>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <span style="font-size: 1.5em;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <div class="info-card">
        <h5>ℹ️ Pourquoi OAuth2 ?</h5>
        <p>Les fournisseurs d'email modernes (Gmail, Outlook) exigent maintenant OAuth2 au lieu des mots de passe d'application pour des raisons de sécurité. Cette méthode est plus sûre et recommandée.</p>
        <ul>
            <li><strong>Gmail :</strong> Nécessite OAuth2 depuis mai 2022</li>
            <li><strong>Outlook :</strong> Désactive progressivement l'authentification basique</li>
        </ul>
    </div>

    <div class="tabs">
        <button class="tab-button active" data-tab="google">
            <span>🔴</span> Google/Gmail
        </button>
        <button class="tab-button" data-tab="outlook">
            <span>🔵</span> Outlook/Hotmail
        </button>
        <button class="tab-button" data-tab="status">
            <span>📊</span> État des Configurations
        </button>
    </div>

    <!-- Configuration Google -->
    <div class="tab-content active" id="tab-google">
        <div class="config-card">
            <h3 style="margin-top: 0;">🔴 Configuration Google OAuth2</h3>
            
            <div class="warning-box">
                <h6>⚠️ Étapes préalables :</h6>
                <ol>
                    <li>Aller sur <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Créer un projet ou sélectionner un projet existant</li>
                    <li>Activer l'API Gmail</li>
                    <li>Créer des identifiants OAuth 2.0</li>
                    <li>Ajouter l'URI de redirection : <code><?= $redirect_uri_google ?></code></li>
                </ol>
            </div>

            <form method="POST">
                <input type="hidden" name="provider" value="google">
                
                <div class="form-group">
                    <label for="google_client_id">Client ID *</label>
                    <input type="text" class="form-control" id="google_client_id" name="client_id" 
                           value="<?= htmlspecialchars($configsByProvider['google']['client_id'] ?? '') ?>"
                           placeholder="123456789-abcdefghijklmnop.apps.googleusercontent.com" required>
                </div>
                
                <div class="form-group">
                    <label for="google_client_secret">Client Secret *</label>
                    <input type="password" class="form-control" id="google_client_secret" name="client_secret" 
                           value="<?= htmlspecialchars($configsByProvider['google']['client_secret'] ?? '') ?>"
                           placeholder="GOCSPX-..." required>
                </div>
                
                <div class="form-group">
                    <label for="google_redirect_uri">URI de Redirection *</label>
                    <input type="url" class="form-control" id="google_redirect_uri" name="redirect_uri" 
                           value="<?= $redirect_uri_google ?>" readonly>
                    <small class="form-text">Cette URL doit être ajoutée dans votre configuration Google Cloud Console</small>
                </div>
                
                <div class="form-group">
                    <label for="google_scopes">Scopes</label>
                    <input type="text" class="form-control" id="google_scopes" name="scopes" 
                           value="<?= htmlspecialchars($configsByProvider['google']['scopes'] ?? 'https://www.googleapis.com/auth/gmail.send') ?>"
                           placeholder="https://www.googleapis.com/auth/gmail.send">
                </div>
                
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="google_active" name="is_active" 
                           <?= isset($configsByProvider['google']) && $configsByProvider['google']['is_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="google_active">
                        Activer cette configuration
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span>💾</span> Sauvegarder Google OAuth2
                </button>
            </form>
        </div>
    </div>

    <!-- Configuration Outlook -->
    <div class="tab-content" id="tab-outlook">
        <div class="config-card">
            <h3 style="margin-top: 0;">🔵 Configuration Outlook OAuth2</h3>
            
            <div class="warning-box">
                <h6>⚠️ Étapes préalables :</h6>
                <ol>
                    <li>Aller sur <a href="https://portal.azure.com/" target="_blank">Azure Portal</a></li>
                    <li>Aller dans "App registrations" → "New registration"</li>
                    <li>Configurer l'application avec l'URI de redirection : <code><?= $redirect_uri_outlook ?></code></li>
                    <li>Aller dans "API permissions" → Ajouter "Mail.Send" pour Microsoft Graph</li>
                    <li>Créer un "Client secret" dans "Certificates & secrets"</li>
                </ol>
            </div>

            <form method="POST">
                <input type="hidden" name="provider" value="outlook">
                
                <div class="form-group">
                    <label for="outlook_client_id">Application (client) ID *</label>
                    <input type="text" class="form-control" id="outlook_client_id" name="client_id" 
                           value="<?= htmlspecialchars($configsByProvider['outlook']['client_id'] ?? '') ?>"
                           placeholder="12345678-1234-1234-1234-123456789012" required>
                </div>
                
                <div class="form-group">
                    <label for="outlook_client_secret">Client Secret *</label>
                    <input type="password" class="form-control" id="outlook_client_secret" name="client_secret" 
                           value="<?= htmlspecialchars($configsByProvider['outlook']['client_secret'] ?? '') ?>"
                           placeholder="..." required>
                </div>
                
                <div class="form-group">
                    <label for="outlook_tenant_id">Directory (tenant) ID</label>
                    <input type="text" class="form-control" id="outlook_tenant_id" name="tenant_id" 
                           value="<?= htmlspecialchars($configsByProvider['outlook']['tenant_id'] ?? 'common') ?>"
                           placeholder="common (ou votre tenant ID spécifique)">
                    <small class="form-text">Utilisez "common" pour les comptes personnels et professionnels</small>
                </div>
                
                <div class="form-group">
                    <label for="outlook_redirect_uri">URI de Redirection *</label>
                    <input type="url" class="form-control" id="outlook_redirect_uri" name="redirect_uri" 
                           value="<?= $redirect_uri_outlook ?>" readonly>
                    <small class="form-text">Cette URL doit être ajoutée dans votre configuration Azure</small>
                </div>
                
                <div class="form-group">
                    <label for="outlook_scopes">Scopes</label>
                    <input type="text" class="form-control" id="outlook_scopes" name="scopes" 
                           value="<?= htmlspecialchars($configsByProvider['outlook']['scopes'] ?? 'https://graph.microsoft.com/Mail.Send') ?>"
                           placeholder="https://graph.microsoft.com/Mail.Send">
                </div>
                
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="outlook_active" name="is_active" 
                           <?= isset($configsByProvider['outlook']) && $configsByProvider['outlook']['is_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="outlook_active">
                        Activer cette configuration
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span>💾</span> Sauvegarder Outlook OAuth2
                </button>
            </form>
        </div>
    </div>

    <!-- État des configurations -->
    <div class="tab-content" id="tab-status">
        <div class="config-card">
            <h3 style="margin-top: 0;">📊 État des Configurations OAuth2</h3>
            
            <?php if (empty($configs)): ?>
                <div class="alert" style="background: #e3f2fd; border: 1px solid #90caf9; color: #1976d2;">
                    <span style="font-size: 1.5em;">ℹ️</span>
                    <div>Aucune configuration OAuth2 trouvée. Configurez au moins un fournisseur pour utiliser l'authentification moderne.</div>
                </div>
            <?php else: ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>Fournisseur</th>
                            <th>Client ID</th>
                            <th>État</th>
                            <th>Dernière Mise à Jour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $config): ?>
                            <tr>
                                <td>
                                    <span><?= $config['provider'] === 'google' ? '🔴' : '🔵' ?></span>
                                    <?= ucfirst($config['provider']) ?>
                                </td>
                                <td>
                                    <code><?= substr(htmlspecialchars($config['client_id']), 0, 30) ?>...</code>
                                </td>
                                <td>
                                    <?php if ($config['is_active']): ?>
                                        <span class="badge badge-success">
                                            ✓ Actif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            ⏸ Inactif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($config['updated_at'])) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="testOAuth2('<?= $config['provider'] ?>')">
                                        <span>🧪</span> Tester
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Gestion des onglets
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', () => {
        // Désactiver tous les boutons et contenus
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Activer le bouton cliqué et son contenu
        button.classList.add('active');
        const tabId = 'tab-' + button.dataset.tab;
        document.getElementById(tabId).classList.add('active');
    });
});

function testOAuth2(provider) {
    // Ouvrir une nouvelle fenêtre pour le test OAuth2
    const testUrl = '../api/oauth2_test.php?provider=' + provider;
    window.open(testUrl, 'oauth2_test', 'width=600,height=700,scrollbars=yes,resizable=yes');
}
</script>