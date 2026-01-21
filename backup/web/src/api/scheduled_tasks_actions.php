<?php
/**
 * API pour la gestion des tâches programmées
 * Compatible avec la structure de table scheduled_tasks existante
 */

// Configuration stricte pour API JSON
ini_set('display_errors', 0);
error_reporting(0);

// Headers JSON obligatoires
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Fonction pour envoyer une réponse JSON et arrêter l'exécution
function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Fonction pour valider un ID
function validateId($id) {
    return is_numeric($id) && intval($id) > 0;
}

// Fonction pour nettoyer les données d'entrée
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Vérifier la méthode HTTP
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    sendJsonResponse(false, 'Méthode non autorisée');
}

// Récupérer l'action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    sendJsonResponse(false, 'Action non spécifiée');
}

// Connexion à la base de données selon le modèle TechSuivi
try {
    $basePath = dirname(__DIR__);
    require_once $basePath . '/config/database.php';
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, 'Erreur de connexion à la base de données');
    }
    
    // Router vers la bonne fonction selon l'action
    switch ($action) {
        case 'create':
            handleCreate($pdo);
            break;
            
        case 'update':
            handleUpdate($pdo);
            break;
            
        case 'delete':
            handleDelete($pdo);
            break;
            
        case 'get':
            handleGet($pdo);
            break;
            
        case 'toggle':
            handleToggle($pdo);
            break;
            
        case 'list':
            handleList($pdo);
            break;
            
        case 'test_mail':
            handleTestMail($pdo);
            break;
            
        case 'get_logs':
            handleGetLogs($pdo);
            break;
            
        default:
            sendJsonResponse(false, 'Action non reconnue: ' . $action);
    }
    
} catch (Exception $e) {
    error_log('Erreur API scheduled_tasks: ' . $e->getMessage());
    sendJsonResponse(false, 'Erreur interne du serveur: ' . $e->getMessage());
}

/**
 * Créer une nouvelle tâche programmée
 */
function handleCreate($pdo) {
    try {
        // Validation des données requises
        $requiredFields = ['name', 'task_type', 'frequency_type', 'recipients'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                sendJsonResponse(false, "Le champ '$field' est requis");
            }
        }
        
        // Nettoyer les données
        $name = sanitizeInput($_POST['name']);
        $task_type = sanitizeInput($_POST['task_type']);
        $frequency_type = sanitizeInput($_POST['frequency_type']);
        
        // Traiter les destinataires - vérifier si c'est déjà du JSON ou une chaîne simple
        $recipients_raw = $_POST['recipients'];
        if (is_string($recipients_raw)) {
            // Vérifier si c'est déjà du JSON valide
            $test_json = json_decode($recipients_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($test_json)) {
                // C'est déjà du JSON valide
                $recipients = $recipients_raw;
            } else {
                // C'est une chaîne simple, la convertir en JSON
                $recipients = json_encode([$recipients_raw]);
            }
        } else {
            // Si c'est un tableau, l'encoder en JSON
            $recipients = json_encode($recipients_raw);
        }
        
        // Préparer les données selon la vraie structure de table
        $frequency_value = $_POST['frequency_value'] ?? null;
        $content_template = sanitizeInput($_POST['content_template'] ?? $_POST['subject_template'] ?? '');
        $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
        $report_template_id = !empty($_POST['report_template_id']) ? intval($_POST['report_template_id']) : null;
        
        // Calculer la prochaine exécution
        $next_execution = calculateNextExecution($frequency_type, $frequency_value);
        
        // Préparer la requête d'insertion avec les VRAIS noms de colonnes de la table
        $sql = "INSERT INTO scheduled_tasks (
            name, task_type, frequency_type, frequency_value,
            recipients, content_template, is_active,
            next_execution, created_at, report_template_id
        ) VALUES (
            :name, :task_type, :frequency_type, :frequency_value,
            :recipients, :content_template, :is_active,
            :next_execution, NOW(), :report_template_id
        )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':name' => $name,
            ':task_type' => $task_type,
            ':frequency_type' => $frequency_type,
            ':frequency_value' => $frequency_value,
            ':recipients' => $recipients,
            ':content_template' => $content_template,
            ':is_active' => $is_active ? 1 : 0,
            ':next_execution' => $next_execution,
            ':report_template_id' => $report_template_id
        ]);
        
        if ($result) {
            $taskId = $pdo->lastInsertId();
            sendJsonResponse(true, 'Tâche créée avec succès', ['id' => $taskId]);
        } else {
            sendJsonResponse(false, 'Erreur lors de la création de la tâche');
        }
        
    } catch (Exception $e) {
        error_log('Erreur création tâche: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la création de la tâche: ' . $e->getMessage());
    }
}

/**
 * Mettre à jour une tâche existante
 */
function handleUpdate($pdo) {
    try {
        // Validation de l'ID
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            sendJsonResponse(false, 'ID de tâche invalide');
        }
        
        // Vérifier que la tâche existe
        $stmt = $pdo->prepare("SELECT id FROM scheduled_tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) {
            sendJsonResponse(false, 'Tâche non trouvée');
        }
        
        // Validation des données requises
        $requiredFields = ['name', 'task_type', 'frequency_type', 'recipients'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                sendJsonResponse(false, "Le champ '$field' est requis");
            }
        }
        
        // Nettoyer les données
        $name = sanitizeInput($_POST['name']);
        $task_type = sanitizeInput($_POST['task_type']);
        $frequency_type = sanitizeInput($_POST['frequency_type']);
        
        // Traiter les destinataires - vérifier si c'est déjà du JSON ou une chaîne simple
        $recipients_raw = $_POST['recipients'];
        if (is_string($recipients_raw)) {
            // Vérifier si c'est déjà du JSON valide
            $test_json = json_decode($recipients_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($test_json)) {
                // C'est déjà du JSON valide
                $recipients = $recipients_raw;
            } else {
                // C'est une chaîne simple, la convertir en JSON
                $recipients = json_encode([$recipients_raw]);
            }
        } else {
            // Si c'est un tableau, l'encoder en JSON
            $recipients = json_encode($recipients_raw);
        }
        
        // Préparer les données selon la vraie structure de table
        $frequency_value = $_POST['frequency_value'] ?? null;
        $content_template = sanitizeInput($_POST['content_template'] ?? $_POST['subject_template'] ?? '');
        $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
        $report_template_id = !empty($_POST['report_template_id']) ? intval($_POST['report_template_id']) : null;
        
        // Calculer la prochaine exécution
        $next_execution = calculateNextExecution($frequency_type, $frequency_value);
        
        // Préparer la requête de mise à jour avec les VRAIS noms de colonnes
        $sql = "UPDATE scheduled_tasks SET
            name = :name,
            task_type = :task_type,
            frequency_type = :frequency_type,
            frequency_value = :frequency_value,
            recipients = :recipients,
            content_template = :content_template,
            next_execution = :next_execution,
            is_active = :is_active,
            updated_at = NOW(),
            report_template_id = :report_template_id
        WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':task_type' => $task_type,
            ':frequency_type' => $frequency_type,
            ':frequency_value' => $frequency_value,
            ':recipients' => $recipients,
            ':content_template' => $content_template,
            ':next_execution' => $next_execution,
            ':is_active' => $is_active ? 1 : 0,
            ':report_template_id' => $report_template_id
        ]);
        
        if ($result) {
            sendJsonResponse(true, 'Tâche mise à jour avec succès');
        } else {
            sendJsonResponse(false, 'Erreur lors de la mise à jour de la tâche');
        }
        
    } catch (Exception $e) {
        error_log('Erreur mise à jour tâche: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la mise à jour de la tâche: ' . $e->getMessage());
    }
}

/**
 * Supprimer une tâche
 */
function handleDelete($pdo) {
    try {
        // Validation de l'ID
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            sendJsonResponse(false, 'ID de tâche invalide');
        }
        
        // Vérifier que la tâche existe
        $stmt = $pdo->prepare("SELECT name FROM scheduled_tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendJsonResponse(false, 'Tâche non trouvée');
        }
        
        // Supprimer la tâche
        $stmt = $pdo->prepare("DELETE FROM scheduled_tasks WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);
        
        if ($result && $stmt->rowCount() > 0) {
            sendJsonResponse(true, 'Tâche "' . $task['name'] . '" supprimée avec succès');
        } else {
            sendJsonResponse(false, 'Erreur lors de la suppression de la tâche');
        }
        
    } catch (Exception $e) {
        error_log('Erreur suppression tâche: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la suppression de la tâche: ' . $e->getMessage());
    }
}

/**
 * Récupérer une tâche spécifique
 */
function handleGet($pdo) {
    try {
        // Validation de l'ID
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        if (!validateId($id)) {
            sendJsonResponse(false, 'ID de tâche invalide');
        }
        
        // Récupérer la tâche avec les vrais noms de colonnes
        $stmt = $pdo->prepare("SELECT * FROM scheduled_tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendJsonResponse(false, 'Tâche non trouvée');
        }
        
        // Traiter les destinataires (peuvent être JSON ou texte simple)
        if (!empty($task['recipients'])) {
            $recipients = json_decode($task['recipients'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($recipients)) {
                $task['recipients_array'] = $recipients;
                $task['recipients_text'] = implode('\n', $recipients);
            } else {
                $task['recipients_text'] = $task['recipients'];
                $task['recipients_array'] = explode(',', $task['recipients']);
            }
        }
        
        // Compatibilité avec l'interface (mapping des noms)
        $task['subject_template'] = $task['content_template'] ?? '';
        $task['body_template'] = $task['content_template'] ?? '';
        
        sendJsonResponse(true, 'Tâche récupérée avec succès', $task);
        
    } catch (Exception $e) {
        error_log('Erreur récupération tâche: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la récupération de la tâche: ' . $e->getMessage());
    }
}

/**
 * Activer/désactiver une tâche
 */
function handleToggle($pdo) {
    try {
        // Validation de l'ID
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            sendJsonResponse(false, 'ID de tâche invalide');
        }
        
        // Récupérer l'état actuel
        $stmt = $pdo->prepare("SELECT name, is_active FROM scheduled_tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendJsonResponse(false, 'Tâche non trouvée');
        }
        
        // Inverser l'état
        $newState = !$task['is_active'];
        
        // Mettre à jour
        $stmt = $pdo->prepare("UPDATE scheduled_tasks SET is_active = :is_active, updated_at = NOW() WHERE id = :id");
        $result = $stmt->execute([
            ':id' => $id,
            ':is_active' => $newState ? 1 : 0
        ]);
        
        if ($result) {
            $status = $newState ? 'activée' : 'désactivée';
            sendJsonResponse(true, 'Tâche "' . $task['name'] . '" ' . $status . ' avec succès');
        } else {
            sendJsonResponse(false, 'Erreur lors du changement d\'état de la tâche');
        }
        
    } catch (Exception $e) {
        error_log('Erreur toggle tâche: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors du changement d\'état de la tâche: ' . $e->getMessage());
    }
}

/**
 * Lister toutes les tâches
 */
function handleList($pdo) {
    try {
        // Récupérer toutes les tâches avec les vrais noms de colonnes
        $stmt = $pdo->query("
            SELECT st.id, st.name, st.task_type, st.frequency_type, st.frequency_value,
                   st.recipients, st.content_template, st.is_active, st.execution_count, st.last_executed,
                   st.next_execution, st.created_at, st.updated_at, st.report_template_id,
                   rt.name as report_name
            FROM scheduled_tasks st
            LEFT JOIN report_templates rt ON st.report_template_id = rt.id
            ORDER BY st.created_at DESC
        ");
        
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Traiter les données pour l'affichage
        foreach ($tasks as &$task) {
            // Traiter les destinataires
            if (!empty($task['recipients'])) {
                $recipients = json_decode($task['recipients'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($recipients)) {
                    $task['recipients_count'] = count($recipients);
                    $task['recipients_preview'] = implode(', ', array_slice($recipients, 0, 2));
                    if (count($recipients) > 2) {
                        $task['recipients_preview'] .= '...';
                    }
                } else {
                    $task['recipients_count'] = 1;
                    $task['recipients_preview'] = $task['recipients'];
                }
            }
            
            // Formater les dates
            if ($task['last_executed']) {
                $task['last_execution_formatted'] = date('d/m/Y H:i', strtotime($task['last_executed']));
            }
            if ($task['next_execution']) {
                $task['next_execution_formatted'] = date('d/m/Y H:i', strtotime($task['next_execution']));
            }
            
            // Compatibilité avec l'interface
            $task['last_execution'] = $task['last_executed'];
        }
        
        sendJsonResponse(true, 'Liste des tâches récupérée avec succès', $tasks);
        
    } catch (Exception $e) {
        error_log('Erreur liste tâches: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la récupération de la liste des tâches: ' . $e->getMessage());
    }
}

/**
 * Tester l'envoi de mail
 */
function handleTestMail($pdo) {
    try {
        // Récupérer les paramètres du test
        $recipient = sanitizeInput($_POST['recipient'] ?? 'test@techsuivi.com');
        $subject = sanitizeInput($_POST['subject'] ?? 'Test d\'envoi TechSuivi');
        $content = sanitizeInput($_POST['content'] ?? 'Ceci est un test d\'envoi de mail.');
        
        // Vérifier que le destinataire est valide
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Adresse email invalide: ' . $recipient);
        }
        
        $startTime = microtime(true);
        
        try {
            // Inclure le helper de mail
            $basePath = dirname(__DIR__);
            $mailHelperPath = $basePath . '/utils/mail_helper.php';
            
            if (file_exists($mailHelperPath)) {
                require_once $mailHelperPath;
                
                // Tenter d'envoyer le mail
                $result = sendMail($recipient, $subject, $content);
                
                $executionTime = round((microtime(true) - $startTime) * 1000); // en millisecondes
                $mailSize = strlen($subject . $content);
                
                if ($result) {
                    // Enregistrer le log de succès
                    $logStmt = $pdo->prepare("
                        INSERT INTO scheduled_tasks_mail_logs
                        (task_id, recipient_email, subject, status, error_message, execution_time_ms, mail_size_bytes, sent_at)
                        VALUES (NULL, ?, ?, 'success', NULL, ?, ?, NOW())
                    ");
                    $logStmt->execute([$recipient, $subject, $executionTime, $mailSize]);
                    
                    sendJsonResponse(true, 'Mail de test envoyé avec succès', [
                        'recipient' => $recipient,
                        'execution_time' => $executionTime . ' ms',
                        'mail_size' => $mailSize . ' bytes'
                    ]);
                } else {
                    // Enregistrer le log d'échec
                    $logStmt = $pdo->prepare("
                        INSERT INTO scheduled_tasks_mail_logs
                        (task_id, recipient_email, subject, status, error_message, execution_time_ms, mail_size_bytes, sent_at)
                        VALUES (NULL, ?, ?, 'failed', 'Échec de l\'envoi (fonction sendMail a retourné false)', ?, ?, NOW())
                    ");
                    $logStmt->execute([$recipient, $subject, $executionTime, $mailSize]);
                    
                    sendJsonResponse(false, 'Échec de l\'envoi du mail de test', [
                        'recipient' => $recipient
                    ]);
                }
            } else {
                // Pas de helper de mail disponible, simuler un envoi
                $executionTime = round((microtime(true) - $startTime) * 1000);
                $mailSize = strlen($subject . $content);
                
                // Enregistrer un log de test simulé
                $logStmt = $pdo->prepare("
                    INSERT INTO scheduled_tasks_mail_logs
                    (task_id, recipient_email, subject, status, error_message, execution_time_ms, mail_size_bytes, sent_at)
                    VALUES (NULL, ?, ?, 'success', 'Test simulé - Helper mail non disponible', ?, ?, NOW())
                ");
                $logStmt->execute([$recipient, $subject, $executionTime, $mailSize]);
                
                sendJsonResponse(true, 'Test simulé réussi (helper mail non disponible)', [
                    'recipient' => $recipient,
                    'execution_time' => $executionTime . ' ms',
                    'mail_size' => $mailSize . ' bytes',
                    'note' => 'Test simulé car le système de mail n\'est pas configuré'
                ]);
            }
            
        } catch (Exception $mailError) {
            $executionTime = round((microtime(true) - $startTime) * 1000);
            $mailSize = strlen($subject . $content);
            
            // Enregistrer le log d'erreur
            $logStmt = $pdo->prepare("
                INSERT INTO scheduled_tasks_mail_logs
                (task_id, recipient_email, subject, status, error_message, execution_time_ms, mail_size_bytes, sent_at)
                VALUES (NULL, ?, ?, 'failed', ?, ?, ?, NOW())
            ");
            $logStmt->execute([$recipient, $subject, $mailError->getMessage(), $executionTime, $mailSize]);
            
            sendJsonResponse(false, 'Erreur lors de l\'envoi : ' . $mailError->getMessage(), [
                'recipient' => $recipient
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Erreur test mail: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors du test d\'envoi: ' . $e->getMessage());
    }
}

/**
 * Calculer la prochaine exécution selon la fréquence
 */
function calculateNextExecution($frequency_type, $frequency_value = null) {
    $now = new DateTime();
    
    switch ($frequency_type) {
        case 'once':
            // Pour une exécution unique, utiliser la valeur fournie ou programmer dans 1 minute
            if ($frequency_value) {
                try {
                    return date('Y-m-d H:i:s', strtotime($frequency_value));
                } catch (Exception $e) {
                    $now->add(new DateInterval('PT1M'));
                }
            } else {
                $now->add(new DateInterval('PT1M'));
            }
            break;
            
        case 'daily':
            // Quotidien à l'heure spécifiée
            if ($frequency_value && preg_match('/^\d{1,2}:\d{2}$/', $frequency_value)) {
                $time = explode(':', $frequency_value);
                $now->setTime(intval($time[0]), intval($time[1]));
                if ($now <= new DateTime()) {
                    $now->add(new DateInterval('P1D'));
                }
            } else {
                $now->add(new DateInterval('P1D'));
            }
            break;
            
        case 'weekly':
            // Hebdomadaire - format: day:HH:MM
            if ($frequency_value && preg_match('/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday):\d{1,2}:\d{2}$/', $frequency_value)) {
                $now->add(new DateInterval('P7D'));
            } else {
                $now->add(new DateInterval('P7D'));
            }
            break;
            
        case 'monthly':
            // Mensuel - format: D:HH:MM
            if ($frequency_value && preg_match('/^\d{1,2}:\d{1,2}:\d{2}$/', $frequency_value)) {
                $now->add(new DateInterval('P1M'));
            } else {
                $now->add(new DateInterval('P1M'));
            }
            break;
            
        case 'custom_cron':
            // Expression cron personnalisée (simplifiée)
            if ($frequency_value) {
                // Pour l'instant, programmer dans 1 heure pour les expressions cron
                $now->add(new DateInterval('PT1H'));
            } else {
                $now->add(new DateInterval('P1D'));
            }
            break;
            
        default:
            // Par défaut, dans 1 jour
            $now->add(new DateInterval('P1D'));
    }
    
    return $now->format('Y-m-d H:i:s');
}

/**
 * Récupérer les logs d'envoi des mails
 */
function handleGetLogs($pdo) {
    try {
        // Récupérer les logs récents (derniers 50)
        $recentLogsQuery = "
            SELECT
                ml.*,
                st.name as task_name
            FROM scheduled_tasks_mail_logs ml
            LEFT JOIN scheduled_tasks st ON ml.task_id = st.id
            ORDER BY ml.sent_at DESC
            LIMIT 50
        ";
        $stmt = $pdo->query($recentLogsQuery);
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les statistiques
        $statsQuery = "
            SELECT
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
                AVG(execution_time_ms) as avg_execution_time,
                SUM(mail_size_bytes) as total_mail_size,
                MAX(sent_at) as last_sent
            FROM scheduled_tasks_mail_logs
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        $stmt = $pdo->query($statsQuery);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Formater les données pour l'affichage
        foreach ($recentLogs as &$log) {
            $log['sent_at_formatted'] = date('d/m/Y H:i:s', strtotime($log['sent_at']));
            $log['status_icon'] = $log['status'] === 'success' ? '✅' : '❌';
            $log['subject_short'] = strlen($log['subject']) > 50 ? substr($log['subject'], 0, 50) . '...' : $log['subject'];
        }
        
        sendJsonResponse(true, 'Logs récupérés avec succès', [
            'logs' => $recentLogs,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log('Erreur récupération logs: ' . $e->getMessage());
        sendJsonResponse(false, 'Erreur lors de la récupération des logs: ' . $e->getMessage());
    }
}

exit;
?>