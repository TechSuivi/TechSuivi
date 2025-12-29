<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;

header('Content-Type: text/html; charset=utf-8');

$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    echo "<h3>Erreur OAuth2</h3>";
    echo "<p>Erreur: " . htmlspecialchars($error) . "</p>";
    echo "<p>Description: " . htmlspecialchars($_GET['error_description'] ?? '') . "</p>";
    echo "<script>setTimeout(() => window.close(), 5000);</script>";
    exit;
}

if (!$provider || !$code) {
    echo "<h3>Paramètres manquants</h3>";
    echo "<p>Provider ou code d'autorisation manquant</p>";
    echo "<script>setTimeout(() => window.close(), 3000);</script>";
    exit;
}

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer la configuration OAuth2
    $stmt = $pdo->prepare("SELECT * FROM oauth2_config WHERE provider = ? AND is_active = 1");
    $stmt->execute([$provider]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        throw new Exception("Configuration OAuth2 non trouvée pour $provider");
    }
    
    // Créer le provider OAuth2
    $oauthProvider = null;
    
    if ($provider === 'google') {
        $oauthProvider = new Google([
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
            'redirectUri' => $config['redirect_uri'],
        ]);
    } elseif ($provider === 'outlook') {
        $oauthProvider = new Azure([
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
            'redirectUri' => $config['redirect_uri'],
            'urlAuthorize' => 'https://login.microsoftonline.com/' . ($config['tenant_id'] ?: 'common') . '/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/' . ($config['tenant_id'] ?: 'common') . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
            'scopes' => explode(' ', $config['scopes']),
        ]);
    } else {
        throw new Exception("Provider non supporté: $provider");
    }
    
    // Échanger le code contre un token d'accès
    $accessToken = $oauthProvider->getAccessToken('authorization_code', [
        'code' => $code
    ]);
    
    // Récupérer les informations de l'utilisateur
    $resourceOwner = $oauthProvider->getResourceOwner($accessToken);
    $userInfo = $resourceOwner->toArray();
    
    // Sauvegarder le token dans la session pour les tests
    $_SESSION['oauth2_token_' . $provider] = [
        'access_token' => $accessToken->getToken(),
        'refresh_token' => $accessToken->getRefreshToken(),
        'expires' => $accessToken->getExpires(),
        'user_info' => $userInfo,
        'provider' => $provider
    ];
    
    // Optionnel: Sauvegarder le token en base de données pour utilisation future
    $stmt = $pdo->prepare("INSERT INTO oauth2_tokens (provider, user_email, access_token, refresh_token, expires_at, user_info, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                          ON DUPLICATE KEY UPDATE 
                          access_token = VALUES(access_token), 
                          refresh_token = VALUES(refresh_token), 
                          expires_at = VALUES(expires_at), 
                          user_info = VALUES(user_info), 
                          updated_at = NOW()");
    
    $userEmail = $userInfo['email'] ?? $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? 'unknown';
    $expiresAt = $accessToken->getExpires() ? date('Y-m-d H:i:s', $accessToken->getExpires()) : null;
    
    // Créer la table des tokens si elle n'existe pas
    $createTokensTable = "CREATE TABLE IF NOT EXISTS oauth2_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT,
        expires_at DATETIME,
        user_info JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_provider_user (provider, user_email)
    )";
    $pdo->exec($createTokensTable);
    
    $stmt->execute([
        $provider,
        $userEmail,
        $accessToken->getToken(),
        $accessToken->getRefreshToken(),
        $expiresAt,
        json_encode($userInfo)
    ]);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>OAuth2 - Succès</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
            .btn:hover { background: #0056b3; }
            pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <h2>🎉 Authentification OAuth2 Réussie !</h2>
        
        <div class='success'>
            <strong>Succès !</strong> L'authentification OAuth2 avec " . ucfirst($provider) . " a été configurée avec succès.
        </div>
        
        <div class='info'>
            <h4>Informations du compte connecté :</h4>
            <ul>
                <li><strong>Email :</strong> " . htmlspecialchars($userEmail) . "</li>
                <li><strong>Provider :</strong> " . ucfirst($provider) . "</li>
                <li><strong>Token expire :</strong> " . ($expiresAt ? date('d/m/Y H:i:s', $accessToken->getExpires()) : 'Jamais') . "</li>
            </ul>
        </div>
        
        <div class='info'>
            <h4>Prochaines étapes :</h4>
            <ol>
                <li>Le token d'accès a été sauvegardé automatiquement</li>
                <li>Vous pouvez maintenant envoyer des emails via OAuth2</li>
                <li>Testez l'envoi d'email depuis la configuration mail</li>
            </ol>
        </div>
        
        <button class='btn' onclick='testEmailSend()'>Tester l'envoi d'email</button>
        <button class='btn' onclick='window.close()'>Fermer cette fenêtre</button>
        
        <script>
        function testEmailSend() {
            // Rediriger vers la page de test d'email
            window.opener.location.href = '/index.php?page=settings&tab=mail';
            window.close();
        }
        
        // Fermer automatiquement après 30 secondes
        setTimeout(() => {
            if (confirm('Fermer cette fenêtre automatiquement ?')) {
                window.close();
            }
        }, 30000);
        </script>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>OAuth2 - Erreur</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .btn { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
            .btn:hover { background: #c82333; }
        </style>
    </head>
    <body>
        <h2>❌ Erreur OAuth2</h2>
        
        <div class='error'>
            <strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>
        
        <div class='error'>
            <h4>Solutions possibles :</h4>
            <ul>
                <li>Vérifiez que la configuration OAuth2 est correcte</li>
                <li>Vérifiez que l'URI de redirection est bien configurée</li>
                <li>Vérifiez que les permissions sont accordées dans la console du provider</li>
                <li>Consultez les logs pour plus de détails</li>
            </ul>
        </div>
        
        <button class='btn' onclick='window.close()'>Fermer cette fenêtre</button>
        
        <script>
        // Fermer automatiquement après 10 secondes
        setTimeout(() => window.close(), 10000);
        </script>
    </body>
    </html>";
    
    error_log("Erreur OAuth2 callback: " . $e->getMessage());
}
?>