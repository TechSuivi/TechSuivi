<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/phpmailer_setup.php';

class MailHelper {
    private $pdo;
    private $config;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
        $this->loadConfig();
    }
    
    /**
     * Charger la configuration mail depuis la base de données
     */
    private function loadConfig() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM mail_config ORDER BY id DESC LIMIT 1");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($this->config && isset($this->config['report_recipients']) && $this->config['report_recipients']) {
                $this->config['report_recipients_array'] = json_decode($this->config['report_recipients'], true) ?: [];
            } else {
                if ($this->config) {
                    $this->config['report_recipients_array'] = [];
                }
            }
        } catch (Exception $e) {
            error_log("Erreur lors du chargement de la configuration mail : " . $e->getMessage());
            $this->config = null;
        }
    }
    
    /**
     * Vérifier si la configuration mail est valide
     */
    public function isConfigured() {
        return $this->config && 
               !empty($this->config['smtp_host']) && 
               !empty($this->config['smtp_username']) && 
               !empty($this->config['smtp_password']) && 
               !empty($this->config['from_email']);
    }
    
    /**
     * Envoyer un email avec PHPMailer
     */
    public function sendMail($to, $subject, $body, $isHtml = true, $attachments = []) {
        if (!$this->isConfigured()) {
            throw new Exception("Configuration mail non valide");
        }
        
        if (!isPHPMailerInstalled()) {
            throw new Exception("PHPMailer n'est pas installé. Veuillez l'installer avec: composer require phpmailer/phpmailer");
        }
        
        try {
            return sendEmailWithPHPMailer($this->config, $to, $subject, $body, $isHtml, $attachments);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'envoi de l'email : " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir des informations sur la méthode d'envoi utilisée
     */
    public function getMailMethod() {
        if (isPHPMailerInstalled()) {
            return [
                'method' => 'PHPMailer',
                'description' => 'Bibliothèque PHPMailer avec support SMTP complet',
                'features' => [
                    'Support SMTP authentifié',
                    'Chiffrement TLS/SSL',
                    'Gestion des erreurs avancée',
                    'Support des pièces jointes',
                    'Meilleure délivrabilité',
                    'Gestion des bounces',
                    'Support Unicode complet'
                ]
            ];
        } else {
            return [
                'method' => 'Non installé',
                'description' => 'PHPMailer requis pour l\'envoi d\'emails',
                'features' => [
                    'Installation requise pour utiliser cette fonctionnalité'
                ],
                'install_instructions' => getPHPMailerInstallInstructions()
            ];
        }
    }
    
    /**
     * Tester la configuration mail
     */
    public function testConfiguration($testEmail = null) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Configuration mail incomplète'];
        }
        
        $testEmail = $testEmail ?: $this->config['from_email'];
        
        try {
            $subject = "Test de configuration - TechSuivi";
            $body = "
            <html>
            <body>
                <h2>Test de configuration mail</h2>
                <p>Ce message confirme que la configuration mail de TechSuivi fonctionne correctement.</p>
                <p><strong>Serveur SMTP :</strong> " . htmlspecialchars($this->config['smtp_host']) . ":" . $this->config['smtp_port'] . "</p>
                <p><strong>Chiffrement :</strong> " . strtoupper($this->config['smtp_encryption']) . "</p>
                <p><strong>Date du test :</strong> " . date('d/m/Y H:i:s') . "</p>
            </body>
            </html>";
            
            $this->sendMail($testEmail, $subject, $body, true);
            
            return ['success' => true, 'message' => 'Email de test envoyé avec succès'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors du test : ' . $e->getMessage()];
        }
    }
    
    /**
     * Générer un rapport d'activité
     */
    public function generateActivityReport($period = 'week') {
        try {
            $dateCondition = '';
            $periodLabel = '';
            
            switch ($period) {
                case 'day':
                    $dateCondition = "DATE(date_creation) = CURDATE()";
                    $periodLabel = "aujourd'hui";
                    break;
                case 'week':
                    $dateCondition = "YEARWEEK(date_creation, 1) = YEARWEEK(CURDATE(), 1)";
                    $periodLabel = "cette semaine";
                    break;
                case 'month':
                    $dateCondition = "YEAR(date_creation) = YEAR(CURDATE()) AND MONTH(date_creation) = MONTH(CURDATE())";
                    $periodLabel = "ce mois";
                    break;
                default:
                    $dateCondition = "YEARWEEK(date_creation, 1) = YEARWEEK(CURDATE(), 1)";
                    $periodLabel = "cette semaine";
            }
            
            // Statistiques des interventions
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_interventions,
                    SUM(CASE WHEN en_cours = 1 THEN 1 ELSE 0 END) as interventions_en_cours,
                    SUM(CASE WHEN en_cours = 0 THEN 1 ELSE 0 END) as interventions_terminees
                FROM inter 
                WHERE $dateCondition
            ");
            $stmt->execute();
            $interventions = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Statistiques des messages helpdesk
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN FAIT = 0 THEN 1 ELSE 0 END) as messages_non_traites,
                    SUM(CASE WHEN FAIT = 1 THEN 1 ELSE 0 END) as messages_traites
                FROM helpdesk_msg 
                WHERE $dateCondition
            ");
            $stmt->execute();
            $messages = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Statistiques des sessions cyber
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_sessions,
                    SUM(tarif) as chiffre_affaires_cyber
                FROM FC_cyber 
                WHERE $dateCondition
            ");
            $stmt->execute();
            $cyber = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Générer le HTML du rapport
            $html = $this->generateReportHTML($interventions, $messages, $cyber, $periodLabel);
            
            return $html;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la génération du rapport : " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Générer le HTML du rapport
     */
    private function generateReportHTML($interventions, $messages, $cyber, $periodLabel) {
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
                .stat-card { background-color: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; }
                .stat-title { font-weight: bold; color: #495057; margin-bottom: 10px; }
                .stat-value { font-size: 24px; font-weight: bold; color: #007bff; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>📊 Rapport d'activité TechSuivi</h1>
                <p><strong>Période :</strong> " . ucfirst($periodLabel) . "</p>
                <p><strong>Généré le :</strong> " . date('d/m/Y à H:i:s') . "</p>
            </div>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-title'>🔧 Interventions</div>
                    <div class='stat-value'>" . ($interventions['total_interventions'] ?: 0) . "</div>
                    <div>En cours: " . ($interventions['interventions_en_cours'] ?: 0) . "</div>
                    <div>Terminées: " . ($interventions['interventions_terminees'] ?: 0) . "</div>
                </div>
                
                <div class='stat-card'>
                    <div class='stat-title'>💬 Messages Helpdesk</div>
                    <div class='stat-value'>" . ($messages['total_messages'] ?: 0) . "</div>
                    <div>Non traités: " . ($messages['messages_non_traites'] ?: 0) . "</div>
                    <div>Traités: " . ($messages['messages_traites'] ?: 0) . "</div>
                </div>
                
                <div class='stat-card'>
                    <div class='stat-title'>🖥️ Sessions Cyber</div>
                    <div class='stat-value'>" . ($cyber['total_sessions'] ?: 0) . "</div>
                    <div>CA: " . number_format($cyber['chiffre_affaires_cyber'] ?: 0, 2) . " €</div>
                </div>
            </div>
            
            <div class='footer'>
                <p>Ce rapport a été généré automatiquement par TechSuivi.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Envoyer le rapport automatique
     */
    public function sendScheduledReport() {
        if (!$this->config || !$this->config['reports_enabled'] || empty($this->config['report_recipients_array'])) {
            return false;
        }
        
        try {
            // Déterminer la période selon l'intervalle configuré
            $period = 'week';
            switch ($this->config['report_frequency']) {
                case 'daily':
                    $period = 'day';
                    break;
                case 'weekly':
                    $period = 'week';
                    break;
                case 'monthly':
                    $period = 'month';
                    break;
            }
            
            $reportHtml = $this->generateActivityReport($period);
            if (!$reportHtml) {
                throw new Exception("Impossible de générer le rapport");
            }
            
            $subject = "Rapport d'activité TechSuivi - " . date('d/m/Y');
            
            // Envoyer à tous les destinataires
            foreach ($this->config['report_recipients_array'] as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->sendMail($recipient, $subject, $reportHtml, true);
                }
            }
            
            // Mettre à jour la date du dernier envoi
            $stmt = $this->pdo->prepare("UPDATE mail_config SET last_report_sent = NOW() WHERE id = ?");
            $stmt->execute([$this->config['id']]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du rapport automatique : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifier si un rapport doit être envoyé
     */
    public function shouldSendReport() {
        if (!$this->config || !$this->config['reports_enabled']) {
            return false;
        }
        
        $lastSent = isset($this->config['last_report_sent']) ? $this->config['last_report_sent'] : null;
        if (!$lastSent) {
            return true; // Jamais envoyé
        }
        
        $lastSentTime = strtotime($lastSent);
        $now = time();
        
        switch ($this->config['report_frequency']) {
            case 'daily':
                return ($now - $lastSentTime) >= (24 * 3600); // 24 heures
            case 'weekly':
                return ($now - $lastSentTime) >= (7 * 24 * 3600); // 7 jours
            case 'monthly':
                return ($now - $lastSentTime) >= (30 * 24 * 3600); // 30 jours
            default:
                return false;
        }
    }
    
    /**
     * Envoyer un email de test
     */
    public function sendTestEmail($testEmail) {
        try {
            $subject = "Test de configuration mail - TechSuivi";
            $body = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Test Email TechSuivi</title>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #007bff; text-align: center;'>🧪 Test de Configuration Mail</h2>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3>✅ Configuration mail fonctionnelle !</h3>
                        <p>Ce message confirme que votre configuration SMTP pour TechSuivi fonctionne correctement.</p>
                    </div>
                    
                    <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <h4>📊 Informations du test :</h4>
                        <ul>
                            <li><strong>Date :</strong> " . date('d/m/Y H:i:s') . "</li>
                            <li><strong>Destinataire :</strong> " . htmlspecialchars($testEmail) . "</li>
                            <li><strong>Méthode :</strong> " . $this->getMailMethod()['method'] . "</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                        <p style='color: #666; font-size: 14px;'>
                            Email généré automatiquement par TechSuivi<br>
                            <em>Ne pas répondre à ce message</em>
                        </p>
                    </div>
                </div>
            </body>
            </html>";
            
            $this->sendMail($testEmail, $subject, $body, true);
            
            return [
                'success' => true,
                'message' => 'Email de test envoyé avec succès'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'envoi du test : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envoyer un rapport de test
     */
    public function sendTestReport($testEmail) {
        try {
            $reportHtml = $this->generateActivityReport('week');
            if (!$reportHtml) {
                throw new Exception("Impossible de générer le rapport de test");
            }
            
            $subject = "Rapport de test - TechSuivi";
            
            $this->sendMail($testEmail, $subject, $reportHtml, true);
            
            return [
                'success' => true,
                'message' => 'Rapport de test envoyé avec succès'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'envoi du rapport de test : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envoyer un email générique
     */
    public function sendEmail($recipients, $subject, $body) {
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        
        $results = [];
        $errors = [];
        
        foreach ($recipients as $recipient) {
            try {
                $this->sendMail($recipient, $subject, $body, true);
                $results[] = $recipient;
            } catch (Exception $e) {
                $errors[] = $recipient . ': ' . $e->getMessage();
            }
        }
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Email envoyé avec succès à ' . count($results) . ' destinataire(s)'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Erreurs lors de l\'envoi : ' . implode(', ', $errors)
            ];
        }
    }
}
?>