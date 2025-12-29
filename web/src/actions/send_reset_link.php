<?php
session_start();
define('TECHSUIVI_INCLUDED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/mail_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Vérifier si l'email existe
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Pour des raisons de sécurité, on ne dit pas si l'email existe ou non, 
        // mais on simule un succès pour éviter l'énumération des utilisateurs.
        // Cependant, dans un contexte interne, on peut vouloir être plus explicite ou loguer l'erreur.
        // Ici on retourne un succès générique.
        echo json_encode(['success' => true, 'message' => 'Si cet email existe, un lien a été envoyé.']);
        exit;
    }
    
    // Générer un token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Sauvegarder le token
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);
    
    // Préparer l'email
    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    // Ajuster le chemin si nécessaire. On suppose que reset_password.php est à la racine web.
    // Si techsuivi est dans un sous-dossier, il faudra l'ajouter. 
    // On peut essayer de déduire le chemin de base.
    $scriptDir = dirname($_SERVER['PHP_SELF']); // /TechSuivi/web/src/actions
    $baseDir = dirname(dirname($scriptDir)); // /TechSuivi/web/src -> mais via HTTP
    // Plus simple: on utilise le referer ou on construit un chemin relatif propre si on connait la structure.
    // Le user utilise http://192.168.10.248/
    // Donc fichiers dans /
    $resetLink .= "/reset_password.php?token=" . $token;
    
    $subject = "Réinitialisation de votre mot de passe TechSuivi";
    $body = "
    <html>
    <body>
        <h2>Bonjour " . htmlspecialchars($user['username']) . ",</h2>
        <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte TechSuivi.</p>
        <p>Si vous êtes à l'origine de cette demande, cliquez sur le lien ci-dessous pour définir un nouveau mot de passe :</p>
        <p><a href='" . $resetLink . "' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Réinitialiser mon mot de passe</a></p>
        <p>Ce lien est valide pendant 1 heure.</p>
        <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.</p>
    </body>
    </html>
    ";
    
    $mailHelper = new MailHelper();
    
    if (!$mailHelper->isConfigured()) {
         echo json_encode(['success' => false, 'message' => 'Le service de messagerie n\'est pas configuré. Contactez l\'administrateur.']);
         exit;
    }
    
    $mailHelper->sendMail($email, $subject, $body, true);
    
    echo json_encode(['success' => true, 'message' => 'Un email contenant le lien de réinitialisation a été envoyé.']);
    
} catch (Exception $e) {
    error_log("Erreur reset password: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors du traitement de la demande.']);
}
?>
