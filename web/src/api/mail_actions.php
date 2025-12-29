<?php
// Configuration stricte pour API JSON
ini_set('display_errors', 0);
error_reporting(0);

// Headers JSON obligatoires
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les paramètres
$action = $_POST['action'] ?? '';
$testEmail = $_POST['test_email'] ?? '';

// Validation de base
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action requise']);
    exit;
}

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Adresse email valide requise']);
    exit;
}

// Connexion à la base de données
try {
    $basePath = dirname(__DIR__);
    require_once $basePath . '/config/database.php';
    $pdo = getDatabaseConnection();
    
    // Vérifier si une configuration mail existe
    $stmt = $pdo->query("SELECT COUNT(*) FROM mail_config WHERE smtp_host != '' AND smtp_username != ''");
    $hasConfig = $stmt->fetchColumn() > 0;
    
    if (!$hasConfig) {
        echo json_encode([
            'success' => false,
            'message' => 'Configuration mail non trouvée. Veuillez d\'abord configurer vos paramètres SMTP.'
        ]);
        exit;
    }
    
    // Récupérer la configuration
    $stmt = $pdo->query("SELECT * FROM mail_config ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune configuration mail trouvée.'
        ]);
        exit;
    }
    
    // Actions selon le type
    switch ($action) {
        case 'test_config':
            // Test réel avec PHPMailer
            try {
                // Vérifier si PHPMailer est disponible
                if (!file_exists($basePath . '/vendor/autoload.php')) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'PHPMailer non installé. Veuillez installer les dépendances.'
                    ]);
                    exit;
                }
                
                require_once $basePath . '/vendor/autoload.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // Configuration SMTP
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_username'];
                $mail->Password = $config['smtp_password'] ?? '';
                $mail->SMTPSecure = $config['smtp_encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int)$config['smtp_port'];
                $mail->CharSet = 'UTF-8';
                
                // Configuration du message
                $mail->setFrom($config['smtp_username'], 'TechSuivi');
                $mail->addAddress($testEmail);
                $mail->Subject = 'Test de configuration mail - TechSuivi';
                $mail->Body = "Ceci est un email de test envoyé depuis TechSuivi.\n\nConfiguration SMTP utilisée :\n- Serveur : {$config['smtp_host']}\n- Port : {$config['smtp_port']}\n- Chiffrement : {$config['smtp_encryption']}\n\nSi vous recevez cet email, la configuration fonctionne correctement !";
                
                // Tentative d'envoi
                $mail->send();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Email de test envoyé avec succès à ' . $testEmail . ' !'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'send_test_report':
            // Envoi d'un rapport de test
            try {
                if (!file_exists($basePath . '/vendor/autoload.php')) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'PHPMailer non installé. Veuillez installer les dépendances.'
                    ]);
                    exit;
                }
                
                require_once $basePath . '/vendor/autoload.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // Configuration SMTP
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_username'];
                $mail->Password = $config['smtp_password'] ?? '';
                $mail->SMTPSecure = $config['smtp_encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int)$config['smtp_port'];
                $mail->CharSet = 'UTF-8';
                
                // Configuration du message
                $mail->setFrom($config['smtp_username'], 'TechSuivi - Rapport');
                $mail->addAddress($testEmail);
                $mail->Subject = 'Rapport de test - TechSuivi - ' . date('d/m/Y H:i');
                
                // Contenu du rapport
                $reportContent = "=== RAPPORT DE TEST TECHSUIVI ===\n\n";
                $reportContent .= "Date : " . date('d/m/Y H:i:s') . "\n";
                $reportContent .= "Serveur : " . $_SERVER['HTTP_HOST'] ?? 'localhost' . "\n\n";
                $reportContent .= "=== STATISTIQUES ===\n";
                $reportContent .= "- Configuration mail : Opérationnelle\n";
                $reportContent .= "- Serveur SMTP : {$config['smtp_host']}:{$config['smtp_port']}\n";
                $reportContent .= "- Chiffrement : {$config['smtp_encryption']}\n\n";
                $reportContent .= "Ce rapport de test confirme que le système d'envoi d'emails fonctionne correctement.\n\n";
                $reportContent .= "---\nTechSuivi - Système de gestion technique";
                
                $mail->Body = $reportContent;
                
                // Tentative d'envoi
                $mail->send();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rapport de test envoyé avec succès à ' . $testEmail . ' !'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi du rapport : ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue: ' . $action
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}

exit;
?>