<?php
/**
 * Configuration et installation de PHPMailer pour TechSuivi
 *
 * PHPMailer est une bibliothèque PHP complète pour l'envoi d'emails
 * avec support SMTP, authentification, chiffrement, pièces jointes, etc.
 */

// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Charger l'autoloader Composer si disponible
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../autoload.php')) {
    require_once __DIR__ . '/../autoload.php';
}

// Déclarations use pour PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Vérifier si PHPMailer est installé
 */
function isPHPMailerInstalled() {
    return class_exists('PHPMailer\PHPMailer\PHPMailer');
}

/**
 * Instructions d'installation de PHPMailer
 */
function getPHPMailerInstallInstructions() {
    return [
        'composer' => [
            'title' => 'Installation via Composer (recommandée)',
            'commands' => [
                'cd /path/to/techsuivi/web/src',
                'composer require phpmailer/phpmailer'
            ],
            'description' => 'Composer installera automatiquement PHPMailer et ses dépendances'
        ],
        'manual' => [
            'title' => 'Installation manuelle',
            'steps' => [
                '1. Télécharger PHPMailer depuis https://github.com/PHPMailer/PHPMailer/releases',
                '2. Extraire dans web/src/vendor/phpmailer/',
                '3. Inclure les fichiers nécessaires'
            ],
            'files' => [
                'web/src/vendor/phpmailer/src/PHPMailer.php',
                'web/src/vendor/phpmailer/src/SMTP.php',
                'web/src/vendor/phpmailer/src/Exception.php'
            ]
        ],
        'benefits' => [
            'title' => 'Avantages de PHPMailer',
            'description' => 'PHPMailer est la solution professionnelle pour l\'envoi d\'emails en PHP',
            'advantages' => [
                'Support SMTP authentifié complet',
                'Chiffrement TLS/SSL sécurisé',
                'Gestion avancée des erreurs',
                'Support des pièces jointes',
                'Meilleure délivrabilité',
                'Compatible avec tous les fournisseurs email',
                'Gestion des bounces et retours',
                'Support Unicode complet'
            ]
        ]
    ];
}

/**
 * Créer une instance PHPMailer configurée
 */
function createPHPMailerInstance($config) {
    if (!isPHPMailerInstalled()) {
        throw new Exception('PHPMailer n\'est pas installé. Utilisez la fonction mail() native ou installez PHPMailer.');
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->Port = $config['smtp_port'];
        
        // Configuration du chiffrement
        switch ($config['smtp_encryption']) {
            case 'tls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'ssl':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            default:
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
        }
        
        // Configuration de l'expéditeur
        $mail->setFrom($config['from_email'], $config['from_name']);
        
        // Configuration pour le débogage (désactivé en production)
        $mail->SMTPDebug = 0; // 0 = off, 1 = client, 2 = client and server
        
        // Encodage
        $mail->CharSet = 'UTF-8';
        
        return $mail;
        
    } catch (Exception $e) {
        throw new Exception('Erreur de configuration PHPMailer : ' . $e->getMessage());
    }
}

/**
 * Envoyer un email avec PHPMailer
 */
function sendEmailWithPHPMailer($config, $to, $subject, $body, $isHtml = true, $attachments = []) {
    $mail = createPHPMailerInstance($config);
    
    // Add attachments to config for processing
    $config['attachments'] = $attachments;
    
    try {
        // Destinataire
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Contenu
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Attachments
        if (!empty($config['attachments']) && is_array($config['attachments'])) {
            foreach ($config['attachments'] as $attachment) {
                if (isset($attachment['content']) && isset($attachment['name'])) {
                    $mail->addStringAttachment($attachment['content'], $attachment['name'], 'base64', $attachment['type'] ?? '');
                } elseif (is_string($attachment) && file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Si HTML, créer une version texte automatiquement
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        // Envoyer
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Erreur d\'envoi : ' . $mail->ErrorInfo);
    }
}

/**
 * Vérifier la configuration PHPMailer
 */
function validatePHPMailerConfig($config) {
    $errors = [];
    
    if (empty($config['smtp_host'])) {
        $errors[] = "Serveur SMTP manquant";
    }
    
    if (empty($config['smtp_username'])) {
        $errors[] = "Nom d'utilisateur SMTP manquant";
    }
    
    if (empty($config['smtp_password'])) {
        $errors[] = "Mot de passe SMTP manquant";
    }
    
    if (empty($config['from_email']) || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email expéditeur invalide";
    }
    
    if (!in_array($config['smtp_encryption'], ['none', 'tls', 'ssl'])) {
        $errors[] = "Type de chiffrement invalide";
    }
    
    if ($config['smtp_port'] < 1 || $config['smtp_port'] > 65535) {
        $errors[] = "Port SMTP invalide";
    }
    
    return $errors;
}
?>