<?php
/**
 * Script cron avancé pour l'envoi automatique des tâches programmées
 * 
 * Ce script remplace l'ancien système basique et utilise la table scheduled_tasks
 * pour une gestion flexible et avancée des envois automatiques.
 * 
 * Fonctionnalités :
 * - Support des fréquences : once, daily, weekly, monthly, custom_cron
 * - Gestion des heures spécifiques et jours de la semaine
 * - Conditions personnalisées pour déclencher les tâches
 * - Multiples destinataires par tâche
 * - Templates de contenu personnalisables
 * - Historique d'exécution et compteurs
 * 
 * Configuration cron recommandée (toutes les 5 minutes) :
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/techsuivi/web/src/cron/advanced_scheduled_tasks.php
 */

// Définir le chemin absolu vers le répertoire racine
$rootPath = dirname(dirname(__FILE__));

// Inclure les fichiers nécessaires
define('TECHSUIVI_INCLUDED', true);
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/utils/mail_helper.php';
require_once $rootPath . '/utils/report_generator.php';

/**
 * Classe pour la gestion avancée des tâches programmées
 */
class AdvancedScheduledTaskManager {
    private $pdo;
    private $mailHelper;
    private $reportGenerator;
    private $logFile;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
        $this->mailHelper = new MailHelper();
        $this->reportGenerator = new ReportGenerator();
        $this->logFile = dirname(__FILE__) . '/advanced_cron.log';
    }
    
    /**
     * Log des messages avec timestamp
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Afficher aussi en console si exécuté en CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Exécuter toutes les tâches programmées
     */
    public function executeTasks() {
        $this->log("🚀 Début de l'exécution des tâches programmées avancées");
        
        try {
            // Récupérer toutes les tâches actives
            $stmt = $this->pdo->query("
                SELECT * FROM scheduled_tasks
                WHERE is_active = TRUE
                ORDER BY frequency_type, frequency_value ASC
            ");
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($tasks)) {
                $this->log("ℹ️ Aucune tâche active trouvée");
                return;
            }
            
            $this->log("📋 " . count($tasks) . " tâche(s) active(s) trouvée(s)");
            
            $executedCount = 0;
            $skippedCount = 0;
            
            foreach ($tasks as $task) {
                if ($this->shouldExecuteTask($task)) {
                    $this->log("⚡ Exécution de la tâche : " . $task['name']);
                    
                    // Vérifier le nombre de logs avant exécution
                    $logsBefore = $this->countTaskLogs($task['id']);
                    
                    // Toujours incrémenter le compteur d'exécution quand la tâche est déclenchée
                    $this->updateTaskExecution($task['id']);
                    
                    $taskSuccess = $this->executeTask($task);
                    
                    // Vérifier le nombre de logs après exécution
                    $logsAfter = $this->countTaskLogs($task['id']);
                    
                    if ($taskSuccess) {
                        $executedCount++;
                        $this->log("✅ Tâche '{$task['name']}' exécutée avec succès");
                    } else {
                        $executedCount++; // Compter aussi les échecs comme des exécutions
                        $this->log("❌ Échec de l'exécution de la tâche '{$task['name']}'", 'ERROR');
                    }
                    
                    // Si aucun log n'a été créé pendant l'exécution, créer un log d'échec général
                    if ($logsAfter === $logsBefore) {
                        $this->log("📝 Aucun log créé pendant l'exécution, création d'un log d'échec général");
                        $this->logTaskFailure($task['id'], $taskSuccess ? "Exécution réussie mais aucun mail envoyé" : "Échec général de l'exécution de la tâche");
                    }
                } else {
                    $skippedCount++;
                }
            }
            
            $this->log("📊 Résumé : $executedCount tâche(s) exécutée(s), $skippedCount ignorée(s)");
            
        } catch (Exception $e) {
            $this->log("💥 ERREUR CRITIQUE : " . $e->getMessage(), 'ERROR');
            $this->sendErrorAlert($e);
        }
        
        $this->log("🏁 Fin de l'exécution des tâches programmées");
    }
    
    /**
     * Vérifier si une tâche doit être exécutée maintenant
     */
    private function shouldExecuteTask($task) {
        $now = new DateTime();
        $currentTime = $now->format('H:i');
        $currentDate = $now->format('Y-m-d');
        $currentDayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
        
        $this->log("🔍 Vérification tâche '{$task['name']}' - Type: {$task['frequency_type']}, Valeur: {$task['frequency_value']}");
        
        // Vérifier les conditions selon le type de fréquence
        switch ($task['frequency_type']) {
            case 'once':
                // Exécuter une seule fois si jamais exécuté
                $shouldExecute = $task['last_executed'] === null;
                $this->log("📅 Once - Jamais exécuté: " . ($shouldExecute ? 'OUI' : 'NON'));
                return $shouldExecute;
                
            case 'daily':
                // Format attendu: "08:00"
                if (!$task['frequency_value']) {
                    $this->log("⚠️ Daily - Pas d'heure définie");
                    return false;
                }
                
                $taskTime = $task['frequency_value'];
                $timeMatch = ($currentTime === $taskTime);
                
                // Vérifier si déjà exécuté aujourd'hui
                $alreadyExecutedToday = false;
                if ($task['last_executed']) {
                    $lastExecuted = new DateTime($task['last_executed']);
                    $alreadyExecutedToday = ($lastExecuted->format('Y-m-d') === $currentDate);
                }
                
                $shouldExecute = $timeMatch && !$alreadyExecutedToday;
                $this->log("📅 Daily - Heure: $currentTime vs $taskTime, Déjà exécuté: " . ($alreadyExecutedToday ? 'OUI' : 'NON') . " -> " . ($shouldExecute ? 'EXÉCUTER' : 'IGNORER'));
                return $shouldExecute;
                
            case 'weekly':
                // Format attendu: "monday:04:00"
                if (!$task['frequency_value']) {
                    $this->log("⚠️ Weekly - Pas de valeur définie");
                    return false;
                }
                
                $parts = explode(':', $task['frequency_value']);
                if (count($parts) < 3) {
                    $this->log("⚠️ Weekly - Format invalide: {$task['frequency_value']}");
                    return false;
                }
                
                $taskDay = $parts[0]; // monday, tuesday, etc.
                $taskTime = $parts[1] . ':' . $parts[2]; // 04:00
                
                $dayMatch = ($currentDayOfWeek === $taskDay);
                $timeMatch = ($currentTime === $taskTime);
                
                // Vérifier si déjà exécuté cette semaine
                $alreadyExecutedThisWeek = false;
                if ($task['last_executed']) {
                    $lastExecuted = new DateTime($task['last_executed']);
                    $weekStart = clone $now;
                    $weekStart->modify('monday this week')->setTime(0, 0, 0);
                    $alreadyExecutedThisWeek = ($lastExecuted >= $weekStart);
                }
                
                $shouldExecute = $dayMatch && $timeMatch && !$alreadyExecutedThisWeek;
                $this->log("📅 Weekly - Jour: $currentDayOfWeek vs $taskDay, Heure: $currentTime vs $taskTime, Déjà exécuté: " . ($alreadyExecutedThisWeek ? 'OUI' : 'NON') . " -> " . ($shouldExecute ? 'EXÉCUTER' : 'IGNORER'));
                return $shouldExecute;
                
            case 'monthly':
                // Format attendu: "1:04:00" (1er du mois à 04:00)
                if (!$task['frequency_value']) {
                    $this->log("⚠️ Monthly - Pas de valeur définie");
                    return false;
                }
                
                $parts = explode(':', $task['frequency_value']);
                if (count($parts) < 3) {
                    $this->log("⚠️ Monthly - Format invalide: {$task['frequency_value']}");
                    return false;
                }
                
                $taskDay = (int)$parts[0]; // 1, 2, 3, etc.
                $taskTime = $parts[1] . ':' . $parts[2]; // 04:00
                $currentDay = (int)$now->format('d');
                
                $dayMatch = ($currentDay === $taskDay);
                $timeMatch = ($currentTime === $taskTime);
                
                // Vérifier si déjà exécuté ce mois
                $alreadyExecutedThisMonth = false;
                if ($task['last_executed']) {
                    $lastExecuted = new DateTime($task['last_executed']);
                    $alreadyExecutedThisMonth = ($lastExecuted->format('Y-m') === $now->format('Y-m'));
                }
                
                $shouldExecute = $dayMatch && $timeMatch && !$alreadyExecutedThisMonth;
                $this->log("📅 Monthly - Jour: $currentDay vs $taskDay, Heure: $currentTime vs $taskTime, Déjà exécuté: " . ($alreadyExecutedThisMonth ? 'OUI' : 'NON') . " -> " . ($shouldExecute ? 'EXÉCUTER' : 'IGNORER'));
                return $shouldExecute;
                
            case 'custom_cron':
                $shouldExecute = $this->evaluateCronExpression($task['frequency_value'], $now);
                $this->log("📅 Cron - Expression: {$task['frequency_value']} -> " . ($shouldExecute ? 'EXÉCUTER' : 'IGNORER'));
                return $shouldExecute;
                
            default:
                $this->log("⚠️ Type de fréquence inconnu: {$task['frequency_type']}");
                return false;
        }
    }
    
    /**
     * Évaluer une expression cron personnalisée (améliorée)
     */
    private function evaluateCronExpression($cronExpression, $now) {
        if (!$cronExpression) {
            $this->log("⚠️ Expression cron vide");
            return false;
        }
        
        // Format : minute heure jour mois jour_semaine
        $parts = explode(' ', trim($cronExpression));
        if (count($parts) !== 5) {
            $this->log("⚠️ Expression cron invalide (doit avoir 5 parties) : $cronExpression", 'WARNING');
            return false;
        }
        
        list($minute, $hour, $day, $month, $dayOfWeek) = $parts;
        
        $currentMinute = (int)$now->format('i');
        $currentHour = (int)$now->format('H');
        $currentDay = (int)$now->format('d');
        $currentMonth = (int)$now->format('m');
        $currentDayOfWeek = (int)$now->format('w'); // 0 = dimanche
        
        // Vérifier les minutes
        if (!$this->matchesCronField($minute, $currentMinute, 0, 59)) {
            $this->log("🔍 Cron - Minutes ne correspondent pas: $minute vs $currentMinute");
            return false;
        }
        
        // Vérifier les heures
        if (!$this->matchesCronField($hour, $currentHour, 0, 23)) {
            $this->log("🔍 Cron - Heures ne correspondent pas: $hour vs $currentHour");
            return false;
        }
        
        // Vérifier les jours du mois
        if (!$this->matchesCronField($day, $currentDay, 1, 31)) {
            $this->log("🔍 Cron - Jours ne correspondent pas: $day vs $currentDay");
            return false;
        }
        
        // Vérifier les mois
        if (!$this->matchesCronField($month, $currentMonth, 1, 12)) {
            $this->log("🔍 Cron - Mois ne correspondent pas: $month vs $currentMonth");
            return false;
        }
        
        // Vérifier les jours de la semaine
        if (!$this->matchesCronField($dayOfWeek, $currentDayOfWeek, 0, 6)) {
            $this->log("🔍 Cron - Jours semaine ne correspondent pas: $dayOfWeek vs $currentDayOfWeek");
            return false;
        }
        
        $this->log("✅ Expression cron correspond: $cronExpression");
        return true;
    }
    
    /**
     * Vérifier si une valeur correspond à un champ cron
     */
    private function matchesCronField($cronField, $currentValue, $min, $max) {
        // Astérisque = toujours vrai
        if ($cronField === '*') {
            return true;
        }
        
        // Valeur simple
        if (is_numeric($cronField)) {
            return (int)$cronField === $currentValue;
        }
        
        // Gestion des intervalles (*/5 = toutes les 5 unités)
        if (preg_match('/^\*\/(\d+)$/', $cronField, $matches)) {
            $interval = (int)$matches[1];
            return ($currentValue % $interval) === 0;
        }
        
        // Gestion des listes (1,3,5)
        if (strpos($cronField, ',') !== false) {
            $values = explode(',', $cronField);
            foreach ($values as $value) {
                if ((int)trim($value) === $currentValue) {
                    return true;
                }
            }
            return false;
        }
        
        // Gestion des plages (1-5)
        if (preg_match('/^(\d+)-(\d+)$/', $cronField, $matches)) {
            $start = (int)$matches[1];
            $end = (int)$matches[2];
            return ($currentValue >= $start && $currentValue <= $end);
        }
        
        return false;
    }
    
    /**
     * Exécuter une tâche spécifique
     */
    private function executeTask($task) {
        try {
            $this->log("🔹 Début executeTask pour '{$task['name']}' (ID: {$task['id']},  Type: {$task['task_type']})");
            
            // Vérifier les conditions personnalisées si définies
            if ($task['conditions_json'] && !$this->evaluateConditions($task['conditions_json'])) {
                $this->log("⏭️ Conditions non remplies pour la tâche '{$task['name']}'");
                return false;
            }
            
            // Décoder les destinataires
            $this->log("🔹 Décodage destinataires: {$task['recipients']}");
            $recipients = json_decode($task['recipients'], true);
            if (empty($recipients)) {
                $this->log("⚠️ Aucun destinataire défini pour la tâche '{$task['name']}'", 'WARNING');
                $this->logTaskFailure($task['id'], "Aucun destinataire défini");
                return false;
            }
            $this->log("🔹 " . count($recipients) . " destinataire(s) trouvé(s)");
            
            // Générer le contenu selon le template
            $this->log("🔹 Génération contenu (type: {$task['task_type']}, template_id: {$task['report_template_id']})");
            try {
                $content = $this->generateContent($task);
            } catch (Exception $contentEx) {
                $this->log("💥 Exception generateContent: " . $contentEx->getMessage(), 'ERROR');
                $this->log("💥 Stack trace: " . $contentEx->getTraceAsString(), 'ERROR');
                $this->logTaskFailure($task['id'], "Exception génération: " . $contentEx->getMessage());
                return false;
            }
            
            if (!$content) {
                $this->log("⚠️ Impossible de générer le contenu pour la tâche '{$task['name']}'", 'WARNING');
                $this->logTaskFailure($task['id'], "Génération contenu retourné null");
                return false;
            }
            $this->log("🔹 Contenu généré - Sujet: {$content['subject']}");
            
            // Envoyer l'email à tous les destinataires avec logging
            $successCount = 0;
            foreach ($recipients as $recipient) {
                $startTime = microtime(true);
                $mailContent = $content['body'];
                $mailAttachments = $content['attachments'] ?? [];
                $mailSize = strlen($mailContent) + array_sum(array_map(function($a) { 
                    return isset($a['content']) ? strlen($a['content']) : (file_exists($a) ? filesize($a) : 0); 
                }, $mailAttachments));
                
                try {
                    if ($this->mailHelper->sendMail($recipient, $content['subject'], $mailContent, true, $mailAttachments)) {
                        $executionTime = round((microtime(true) - $startTime) * 1000); // en millisecondes
                        
                        // Enregistrer le succès dans les logs
                        $this->logMailSending($task['id'], $recipient, $content['subject'], 'success', null, $executionTime, $mailSize);
                        
                        $successCount++;
                        $this->log("✅ Email envoyé avec succès à $recipient pour la tâche '{$task['name']}'");
                    } else {
                        $executionTime = round((microtime(true) - $startTime) * 1000);
                        $errorMessage = "Échec d'envoi via MailHelper";
                        
                        // Enregistrer l'échec dans les logs
                        $this->logMailSending($task['id'], $recipient, $content['subject'], 'failed', $errorMessage, $executionTime, $mailSize);
                        
                        $this->log("⚠️ Échec d'envoi à $recipient pour la tâche '{$task['name']}'", 'WARNING');
                    }
                } catch (Exception $mailException) {
                    $executionTime = round((microtime(true) - $startTime) * 1000);
                    $errorMessage = $mailException->getMessage();
                    
                    // Enregistrer l'exception dans les logs
                    $this->logMailSending($task['id'], $recipient, $content['subject'], 'failed', $errorMessage, $executionTime, $mailSize);
                    
                    $this->log("💥 Exception lors de l'envoi à $recipient : " . $errorMessage, 'ERROR');
                }
            }
            
            $this->log("🔹 Fin executeTask - Succès: $successCount/" . count($recipients));
            return $successCount > 0;
            
        } catch (Exception $e) {
            $this->log("💥 Erreur lors de l'exécution de la tâche '{$task['name']}' : " . $e->getMessage(), 'ERROR');
            $this->log("💥 Stack trace: " . $e->getTraceAsString(), 'ERROR');
            $this->logTaskFailure($task['id'], "Exception générale: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enregistrer un log d'envoi de mail dans la base de données
     */
    private function logMailSending($taskId, $recipientEmail, $subject, $status, $errorMessage = null, $executionTimeMs = 0, $mailSizeBytes = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_tasks_mail_logs
                (task_id, recipient_email, subject, status, error_message, execution_time_ms, mail_size_bytes, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $taskId,
                $recipientEmail,
                $subject,
                $status,
                $errorMessage,
                $executionTimeMs,
                $mailSizeBytes
            ]);
            
            $this->log("📝 Log d'envoi enregistré : $status pour $recipientEmail");
            
        } catch (Exception $e) {
            $this->log("⚠️ Erreur lors de l'enregistrement du log d'envoi : " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Enregistrer un échec général de tâche
     */
    private function logTaskFailure($taskId, $errorMessage) {
        try {
            // Récupérer les destinataires de la tâche pour le log
            $stmt = $this->pdo->prepare("SELECT recipients, name FROM scheduled_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                $recipients = json_decode($task['recipients'], true);
                $firstRecipient = !empty($recipients) ? $recipients[0] : 'unknown@example.com';
                
                // Enregistrer un log d'échec général
                $this->logMailSending(
                    $taskId,
                    $firstRecipient,
                    "Échec d'exécution: " . $task['name'],
                    'failed',
                    $errorMessage,
                    0,
                    0
                );
                
                $this->log("📝 Log d'échec général créé pour la tâche '{$task['name']}'");
            }
            
        } catch (Exception $e) {
            $this->log("⚠️ Erreur lors de la création du log d'échec : " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Compter le nombre de logs pour une tâche
     */
    private function countTaskLogs($taskId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM scheduled_tasks_mail_logs WHERE task_id = ?");
            $stmt->execute([$taskId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $this->log("⚠️ Erreur lors du comptage des logs : " . $e->getMessage(), 'WARNING');
            return 0;
        }
    }
    
    /**
     * Évaluer les conditions personnalisées
     */
    private function evaluateConditions($conditionsJson) {
        try {
            $conditions = json_decode($conditionsJson, true);
            if (!$conditions) {
                return true; // Pas de conditions = toujours vrai
            }
            
            // Exemple de conditions possibles
            foreach ($conditions as $condition) {
                switch ($condition['type']) {
                    case 'urgent_interventions':
                        // Vérifier s'il y a des interventions urgentes non traitées
                        $stmt = $this->pdo->query("
                            SELECT COUNT(*) as count 
                            FROM inter 
                            WHERE status_inter = 'En cours' 
                            AND DATEDIFF(NOW(), date_creation) >= 1
                        ");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result['count'] == 0) {
                            return false; // Pas d'interventions urgentes
                        }
                        break;
                        
                    case 'backup_needed':
                        // Vérifier si une sauvegarde est nécessaire
                        // (logique personnalisée selon vos besoins)
                        break;
                        
                    default:
                        $this->log("⚠️ Type de condition inconnu : {$condition['type']}", 'WARNING');
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log("⚠️ Erreur lors de l'évaluation des conditions : " . $e->getMessage(), 'WARNING');
            return true; // En cas d'erreur, on exécute quand même
        }
    }
    
    /**
     * Générer le contenu selon le template
     */
    private function generateContent($task) {
        try {
            // Utiliser le template de contenu de la tâche ou générer selon le type
            $contentTemplate = $task['content_template'] ?: '';
            
            // Générer le sujet (utiliser le nom de la tâche par défaut)
            $subject = $task['name'];
            
            $attachments = [];

            // Si c'est un template prédéfini, générer le contenu approprié
            switch ($task['task_type']) {
                case 'report':
                    $subject = 'Rapport TechSuivi - ' . date('d/m/Y');
                    // Utiliser l'ID du template de rapport s'il existe, sinon le contenu texte
                    $reportSource = !empty($task['report_template_id']) ? $task['report_template_id'] : $contentTemplate;
                    $reportData = $this->generateReport($reportSource);
                    $body = $reportData['body'];
                    $attachments = $reportData['attachments'] ?? [];
                    break;
                    
                case 'notification':
                    $subject = '🔔 ' . $task['name'];
                    $body = $this->generateNotification($contentTemplate);
                    break;
                    
                case 'backup_reminder':
                    $subject = '💾 Rappel : Sauvegarde TechSuivi';
                    $body = $this->generateBackupReminder();
                    break;
                    
                default:
                    // Utiliser le template personnalisé ou contenu par défaut
                    $body = $contentTemplate ?: 'Tâche programmée exécutée automatiquement le ' . date('d/m/Y à H:i');
            }
            
            // Remplacer les variables dans le contenu
            $body = $this->replaceTemplateVariables($body);
            
            return [
                'subject' => $subject,
                'body' => $body,
                'attachments' => $attachments
            ];
            
        } catch (Exception $e) {
            $this->log("💥 Erreur lors de la génération du contenu : " . $e->getMessage(), 'ERROR');
            return [
                'subject' => $task['name'],
                'body' => 'Erreur lors de la génération du contenu : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Remplacer les variables dans les templates
     */
    private function replaceTemplateVariables($content) {
        $variables = [
            '{{date}}' => date('d/m/Y'),
            '{{time}}' => date('H:i'),
            '{{datetime}}' => date('d/m/Y H:i'),
            '{{day}}' => date('l'),
            '{{month}}' => date('F'),
            '{{year}}' => date('Y')
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
    
    /**
     * Générer un rapport
     */
    private function generateReport($template) {
        $this->log("🔹 generateReport appelé avec template: " . ($template ?: 'NULL'));
        
        // Si c'est un template personnalisé simple, l'utiliser directement
        if ($template && !is_numeric($template)) {
            $this->log("🔹 Template textuel simple");
            return [
                'body' => $this->replaceTemplateVariables($template),
                'attachments' => []
            ];
        }
        
        // Si c'est un ID de template de rapport, utiliser le générateur
        if (is_numeric($template)) {
            $this->log("🔹 Appel ReportGenerator avec template_id: $template");
            try {
                $reportResult = $this->reportGenerator->generateReport($template);
                $this->log("🔹 ReportGenerator retourné, succès: " . ($reportResult['success'] ? 'OUI' : 'NON'));
                
                if ($reportResult['success']) {
                    $hasPdf = ($reportResult['mime_type'] ?? '') === 'application/pdf';
                    if ($hasPdf) {
                         return [
                              'body' => '<html><body><h3>' . htmlspecialchars($reportResult['template_name']) . '</h3><p>Veuillez trouver ci-joint le rapport généré le ' . date('d/m/Y à H:i') . '.</p><p>Cordialement,<br>TechSuivi</p></body></html>',
                              'attachments' => [[
                                  'content' => $reportResult['content'],
                                  'name' => $reportResult['filename'] ?? 'rapport.pdf',
                                  'type' => 'application/pdf'
                              ]]
                         ];
                    } else {
                        return [
                            'body' => $reportResult['content'],
                            'attachments' => []
                        ];
                    }
                } else {
                    $this->log("⚠️ Erreur génération rapport template $template: " . $reportResult['error'], 'WARNING');
                    return [
                        'body' => "<p>Erreur lors de la génération du rapport : " . htmlspecialchars($reportResult['error']) . "</p>",
                        'attachments' => []
                    ];
                }
            } catch (Exception $e) {
                $this->log("💥 Exception ReportGenerator: " . $e->getMessage(), 'ERROR');
                return [
                    'body' => "<p>Exception lors de la génération du rapport : " . htmlspecialchars($e->getMessage()) . "</p>",
                    'attachments' => []
                ];
            }
        }
        
        $this->log("🔹 Génération rapport quotidien par défaut");
        return [
            'body' => $this->generateDailyReport(),
            'attachments' => []
        ];
    }
    
    /**
     * Générer une notification
     */
    private function generateNotification($template) {
        if ($template) {
            return $this->replaceTemplateVariables($template);
        }
        
        return "Notification automatique générée le " . date('d/m/Y à H:i') . ".";
    }
    
    /**
     * Générer le rapport quotidien
     */
    private function generateDailyReport() {
        try {
            // Statistiques du jour
            $today = date('Y-m-d');
            $newInterventions = 0;
            $completedInterventions = 0;
            
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM inter WHERE DATE(date_inter) = ?");
                $stmt->execute([$today]);
                $newInterventions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM inter WHERE DATE(date_fin) = ? AND status_inter = 'Terminé'");
                $stmt->execute([$today]);
                $completedInterventions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch (Exception $e) {
                // Table interventions n'existe peut-être pas, on ignore
            }
            
            return "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>📊 Rapport quotidien - " . date('d/m/Y') . "</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>📈 Activité du jour</h3>
                    <ul>
                        <li><strong>Nouvelles interventions :</strong> $newInterventions</li>
                        <li><strong>Interventions terminées :</strong> $completedInterventions</li>
                    </ul>
                </div>
                
                <p><em>Rapport généré automatiquement par TechSuivi</em></p>
            </body>
            </html>";
            
        } catch (Exception $e) {
            return "<p>Erreur lors de la génération du rapport quotidien : " . $e->getMessage() . "</p>";
        }
    }
    
    /**
     * Générer le rapport hebdomadaire
     */
    private function generateWeeklyReport() {
        // Logique similaire mais pour la semaine
        return "<h2>📊 Rapport hebdomadaire</h2><p>Rapport hebdomadaire détaillé...</p>";
    }
    
    /**
     * Générer le rapport mensuel
     */
    private function generateMonthlyReport() {
        // Logique similaire mais pour le mois
        return "<h2>📊 Rapport mensuel</h2><p>Rapport mensuel complet...</p>";
    }
    
    /**
     * Générer le rappel de sauvegarde
     */
    private function generateBackupReminder() {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>💾 Rappel de sauvegarde</h2>
            
            <div style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>
                <p><strong>Il est temps de faire une sauvegarde de votre système TechSuivi !</strong></p>
                
                <p>Pour effectuer une sauvegarde :</p>
                <ol>
                    <li>Connectez-vous à votre interface TechSuivi</li>
                    <li>Allez dans Paramètres > Sauvegarde</li>
                    <li>Cliquez sur 'Créer une sauvegarde'</li>
                    <li>Téléchargez le fichier généré</li>
                </ol>
                
                <p><em>Une sauvegarde régulière protège vos données importantes.</em></p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Générer l'alerte d'interventions urgentes
     */
    private function generateUrgentInterventionsAlert() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, client_nom, descrip, date_inter 
                FROM inter 
                WHERE status_inter = 'En cours' 
                AND DATEDIFF(NOW(), date_inter) >= 1
                ORDER BY date_inter ASC
                LIMIT 10
            ");
            $urgentInterventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $html = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>🚨 Interventions urgentes en attente</h2>
                
                <div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;'>
                    <p><strong>" . count($urgentInterventions) . " intervention(s) non traitée(s) depuis plus de 24h</strong></p>
                </div>
                
                <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                    <thead>
                        <tr style='background: #e9ecef;'>
                            <th style='padding: 10px; border: 1px solid #ddd;'>ID</th>
                            <th style='padding: 10px; border: 1px solid #ddd;'>Client</th>
                            <th style='padding: 10px; border: 1px solid #ddd;'>Description</th>
                            <th style='padding: 10px; border: 1px solid #ddd;'>Date création</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            foreach ($urgentInterventions as $intervention) {
                $html .= "
                        <tr>
                            <td style='padding: 10px; border: 1px solid #ddd;'>{$intervention['id']}</td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>{$intervention['client_nom']}</td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>" . substr($intervention['descrip'], 0, 50) . "...</td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>" . date('d/m/Y H:i', strtotime($intervention['date_inter'])) . "</td>
                        </tr>";
            }
            
            $html .= "
                    </tbody>
                </table>
                
                <p><em>Veuillez traiter ces interventions prioritaires.</em></p>
            </body>
            </html>";
            
            return $html;
            
        } catch (Exception $e) {
            return "<p>Erreur lors de la génération de l'alerte : " . $e->getMessage() . "</p>";
        }
    }
    
    /**
     * Mettre à jour l'historique d'exécution d'une tâche
     */
    private function updateTaskExecution($taskId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE scheduled_tasks 
                SET last_executed = NOW(), 
                    execution_count = execution_count + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$taskId]);
            
        } catch (Exception $e) {
            $this->log("⚠️ Erreur lors de la mise à jour de l'historique : " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Envoyer une alerte en cas d'erreur critique
     */
    private function sendErrorAlert($exception) {
        try {
            if (!$this->mailHelper->isConfigured()) {
                return;
            }
            
            $subject = "🚨 Erreur critique - Tâches programmées TechSuivi";
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>🚨 Erreur critique dans les tâches programmées</h2>
                
                <div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;'>
                    <p><strong>Date :</strong> " . date('d/m/Y H:i:s') . "</p>
                    <p><strong>Erreur :</strong> " . htmlspecialchars($exception->getMessage()) . "</p>
                    <p><strong>Fichier :</strong> " . $exception->getFile() . "</p>
                    <p><strong>Ligne :</strong> " . $exception->getLine() . "</p>
                </div>
                
                <p>Veuillez vérifier la configuration et les logs du serveur.</p>
                <p><em>Consultez le fichier advanced_cron.log pour plus de détails.</em></p>
            </body>
            </html>";
            
            // Récupérer l'email admin pour l'alerte
            $stmt = $this->pdo->query("SELECT from_email FROM mail_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config && $config['from_email']) {
                $this->mailHelper->sendMail($config['from_email'], $subject, $body, true);
                $this->log("📧 Email d'alerte envoyé");
            }
            
        } catch (Exception $e) {
            $this->log("💥 Impossible d'envoyer l'email d'alerte : " . $e->getMessage(), 'ERROR');
        }
    }
}

// Exécution du script
try {
    $taskManager = new AdvancedScheduledTaskManager();
    $taskManager->executeTasks();
    
} catch (Exception $e) {
    error_log("Erreur critique dans advanced_scheduled_tasks.php : " . $e->getMessage());
    
    // Si le script est appelé depuis le navigateur (pour test)
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "<h2>🚨 Erreur critique</h2>";
        echo "<p>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Interface web pour les tests
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<h2>🤖 Gestionnaire avancé de tâches programmées TechSuivi</h2>";
    echo "<p>Ce script est conçu pour être exécuté via cron toutes les 5 minutes.</p>";
    echo "<p>Configuration cron recommandée : <code>*/5 * * * * /usr/bin/php " . __FILE__ . "</code></p>";
    
    // Afficher les dernières lignes du log
    $logFile = dirname(__FILE__) . '/advanced_cron.log';
    if (file_exists($logFile)) {
        echo "<h3>📋 Dernières entrées du log :</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
        $lines = file($logFile);
        $lastLines = array_slice($lines, -20); // 20 dernières lignes
        echo htmlspecialchars(implode('', $lastLines));
        echo "</pre>";
    }
    
    // Afficher les tâches actives
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->query("SELECT * FROM scheduled_tasks WHERE is_active = TRUE ORDER BY frequency_type, frequency_value ASC");
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 Tâches actives (" . count($tasks) . ") :</h3>";
        if (!empty($tasks)) {
            echo "<table style='width: 100%; border-collapse: collapse;'>";
            echo "<tr style='background: #e9ecef;'>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Nom</th>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Type</th>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Fréquence</th>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Heure</th>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Dernière exécution</th>";
            echo "<th style='padding: 8px; border: 1px solid #ddd;'>Exécutions</th>";
            echo "</tr>";
            
            foreach ($tasks as $task) {
                echo "<tr>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$task['name']}</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$task['task_type']}</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$task['frequency_type']}</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$task['frequency_value']}</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($task['last_executed'] ? date('d/m/Y H:i', strtotime($task['last_executed'])) : 'Jamais') . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$task['execution_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Aucune tâche active configurée.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>Erreur lors de la récupération des tâches : " . $e->getMessage() . "</p>";
    }
}
?>