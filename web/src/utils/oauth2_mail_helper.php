<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;
use League\OAuth2\Client\Token\AccessToken;

class OAuth2MailHelper {
    private $pdo;
    
    public function __construct() {
        global $host, $dbname, $username, $password;
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    public function getOAuth2Config($provider) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM oauth2_config WHERE provider = ? AND is_active = 1");
            $stmt->execute([$provider]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération config OAuth2: " . $e->getMessage());
            return false;
        }
    }
    
    public function getOAuth2Token($provider, $userEmail) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM oauth2_tokens WHERE provider = ? AND user_email = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$provider, $userEmail]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération token OAuth2: " . $e->getMessage());
            return false;
        }
    }
    
    public function refreshOAuth2Token($provider, $refreshToken) {
        $config = $this->getOAuth2Config($provider);
        if (!$config) {
            throw new Exception("Configuration OAuth2 non trouvée pour $provider");
        }
        
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
            ]);
        } else {
            throw new Exception("Provider OAuth2 non supporté: $provider");
        }
        
        try {
            $newAccessToken = $oauthProvider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);
            
            return [
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $refreshToken,
                'expires' => $newAccessToken->getExpires()
            ];
        } catch (Exception $e) {
            throw new Exception("Erreur lors du rafraîchissement du token: " . $e->getMessage());
        }
    }
    
    public function sendMailWithOAuth2($provider, $userEmail, $to, $subject, $body, $isHTML = true) {
        $token = $this->getOAuth2Token($provider, $userEmail);
        if (!$token) {
            throw new Exception("Token OAuth2 non trouvé pour $provider et $userEmail");
        }
        
        // Vérifier si le token a expiré
        if ($token['expires_at'] && strtotime($token['expires_at']) <= time()) {
            if ($token['refresh_token']) {
                $newToken = $this->refreshOAuth2Token($provider, $token['refresh_token']);
                
                // Mettre à jour le token en base
                $stmt = $this->pdo->prepare("UPDATE oauth2_tokens SET 
                    access_token = ?, refresh_token = ?, expires_at = ?, updated_at = NOW() 
                    WHERE provider = ? AND user_email = ?");
                $stmt->execute([
                    $newToken['access_token'],
                    $newToken['refresh_token'],
                    $newToken['expires'] ? date('Y-m-d H:i:s', $newToken['expires']) : null,
                    $provider,
                    $userEmail
                ]);
                
                $token['access_token'] = $newToken['access_token'];
            } else {
                throw new Exception("Token expiré et aucun refresh token disponible");
            }
        }
        
        $mail = new PHPMailer(true);
        
        try {
            if ($provider === 'google') {
                // Configuration Gmail OAuth2
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->AuthType = 'XOAUTH2';
                
                // Configuration OAuth2 pour Gmail
                $mail->setOAuth(
                    new AccessToken(['access_token' => $token['access_token']]),
                    $userEmail,
                    $userEmail,
                    $token['access_token']
                );
                
            } elseif ($provider === 'outlook') {
                // Configuration Outlook OAuth2
                $mail->isSMTP();
                $mail->Host = 'smtp-mail.outlook.com';
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->AuthType = 'XOAUTH2';
                
                // Configuration OAuth2 pour Outlook
                $mail->setOAuth(
                    new AccessToken(['access_token' => $token['access_token']]),
                    $userEmail,
                    $userEmail,
                    $token['access_token']
                );
            }
            
            // Destinataires
            $mail->setFrom($userEmail, $userEmail);
            $mail->addAddress($to);
            
            // Contenu
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->CharSet = 'UTF-8';
            
            $mail->send();
            return ['success' => true, 'message' => 'Email envoyé avec succès via OAuth2'];
            
        } catch (Exception $e) {
            throw new Exception("Erreur d'envoi OAuth2: " . $e->getMessage());
        }
    }
    
    public function testOAuth2Connection($provider, $userEmail) {
        try {
            $testSubject = "Test OAuth2 - TechSuivi";
            $testBody = "
            <html>
            <body>
                <h2>Test de connexion OAuth2</h2>
                <p>Ce message confirme que l'authentification OAuth2 avec $provider fonctionne correctement.</p>
                <p><strong>Provider :</strong> " . ucfirst($provider) . "</p>
                <p><strong>Compte :</strong> $userEmail</p>
                <p><strong>Date du test :</strong> " . date('d/m/Y H:i:s') . "</p>
                <hr>
                <p><em>Message généré automatiquement par TechSuivi</em></p>
            </body>
            </html>";
            
            $result = $this->sendMailWithOAuth2($provider, $userEmail, $userEmail, $testSubject, $testBody, true);
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors du test OAuth2: ' . $e->getMessage()];
        }
    }
    
    public function getAvailableOAuth2Providers() {
        try {
            $stmt = $this->pdo->query("SELECT provider, COUNT(*) as token_count FROM oauth2_tokens WHERE expires_at > NOW() OR expires_at IS NULL GROUP BY provider");
            $providers = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $providers[] = [
                    'provider' => $row['provider'],
                    'token_count' => $row['token_count'],
                    'available' => true
                ];
            }
            
            return $providers;
        } catch (PDOException $e) {
            error_log("Erreur récupération providers OAuth2: " . $e->getMessage());
            return [];
        }
    }
    
    public function getOAuth2Status() {
        try {
            $status = [
                'google' => ['configured' => false, 'tokens' => 0, 'active' => false],
                'outlook' => ['configured' => false, 'tokens' => 0, 'active' => false]
            ];
            
            // Vérifier les configurations
            $stmt = $this->pdo->query("SELECT provider, is_active FROM oauth2_config");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($status[$row['provider']])) {
                    $status[$row['provider']]['configured'] = true;
                    $status[$row['provider']]['active'] = (bool)$row['is_active'];
                }
            }
            
            // Compter les tokens valides
            $stmt = $this->pdo->query("SELECT provider, COUNT(*) as count FROM oauth2_tokens WHERE expires_at > NOW() OR expires_at IS NULL GROUP BY provider");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($status[$row['provider']])) {
                    $status[$row['provider']]['tokens'] = (int)$row['count'];
                }
            }
            
            return $status;
        } catch (PDOException $e) {
            error_log("Erreur récupération statut OAuth2: " . $e->getMessage());
            return [];
        }
    }
}