<?php
// api/send_daily_pdf.php

// Define included constant
define('TECHSUIVI_INCLUDED', true);

// Enable error reporting for debugging (returns JSON error)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/phpmailer_setup.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    if (empty($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Aucun fichier reçu ou erreur d\'upload');
    }

    $date = $_POST['date'] ?? date('Y-m-d');
    $formattedDate = date('d/m/Y', strtotime($date));

    // 1. Get Mail Config
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT * FROM mail_config ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        throw new Exception('Configuration email introuvable base de données.');
    }

    // Determine recipients
    $recipients = [];
    if (!empty($config['report_recipients'])) {
        $recipients = json_decode($config['report_recipients'], true);
    }
    
    // Fallback to admin email/from email if no recipients
    if (empty($recipients) && !empty($config['from_email'])) {
        $recipients = [$config['from_email']];
    }

    if (empty($recipients)) {
        throw new Exception('Aucun destinataire configuré dans les paramètres mails.');
    }

    // 2. Prepare Email
    $mail = createPHPMailerInstance($config);
    
    // Add recipients
    foreach ($recipients as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($email);
        }
    }

    $mail->Subject = "Rapport Caisse - Résumé Journalier du $formattedDate";
    $mail->Body = "Bonjour,<br><br>Veuillez trouver ci-joint le résumé journalier de la caisse pour le <b>$formattedDate</b>.<br><br>Cordialement,<br>TechSuivi";
    $mail->isHTML(true);

    // 3. Attach PDF
    $tmpPath = $_FILES['pdf_file']['tmp_name'];
    $filename = "Resume_Caisse_$date.pdf";
    $mail->addAttachment($tmpPath, $filename);

    // 4. Send
    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès à ' . count($recipients) . ' destinataire(s).']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
