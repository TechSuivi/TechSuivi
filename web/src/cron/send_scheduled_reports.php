<?php
/**
 * Script cron pour l'envoi automatique des rapports
 * 
 * Ce script doit être exécuté périodiquement via cron pour envoyer
 * les rapports automatiques selon la configuration.
 * 
 * Exemple de configuration cron (tous les jours à 8h00) :
 * 0 8 * * * /usr/bin/php /path/to/techsuivi/web/src/cron/send_scheduled_reports.php
 */

// Définir le chemin absolu vers le répertoire racine
$rootPath = dirname(dirname(__FILE__));

// Inclure les fichiers nécessaires
define('TECHSUIVI_INCLUDED', true);
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/utils/mail_helper.php';

// Fonction de log pour le cron
function cronLog($message) {
    $logFile = dirname(__FILE__) . '/cron.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    cronLog("Début de l'exécution du script de rapports automatiques");
    
    // Créer une instance du helper mail
    $mailHelper = new MailHelper();
    
    // Vérifier si un rapport doit être envoyé
    if ($mailHelper->shouldSendReport()) {
        cronLog("Un rapport doit être envoyé");
        
        // Envoyer le rapport
        $success = $mailHelper->sendScheduledReport();
        
        if ($success) {
            cronLog("Rapport envoyé avec succès");
        } else {
            cronLog("ERREUR: Échec de l'envoi du rapport");
        }
    } else {
        cronLog("Aucun rapport à envoyer pour le moment");
    }
    
    cronLog("Fin de l'exécution du script");
    
} catch (Exception $e) {
    cronLog("ERREUR CRITIQUE: " . $e->getMessage());
    
    // En cas d'erreur critique, on peut envoyer un email d'alerte
    // (si la configuration mail fonctionne)
    try {
        if (isset($mailHelper) && $mailHelper->isConfigured()) {
            $subject = "Erreur dans le script de rapports TechSuivi";
            $body = "
            <html>
            <body>
                <h2>Erreur dans le script de rapports automatiques</h2>
                <p><strong>Date :</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p><strong>Fichier :</strong> " . $e->getFile() . "</p>
                <p><strong>Ligne :</strong> " . $e->getLine() . "</p>
                <p>Veuillez vérifier la configuration et les logs du serveur.</p>
            </body>
            </html>";
            
            // Récupérer l'email de l'expéditeur pour l'alerte
            $pdo = getDatabaseConnection();
            $stmt = $pdo->query("SELECT from_email FROM mail_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config && $config['from_email']) {
                $mailHelper->sendMail($config['from_email'], $subject, $body, true);
                cronLog("Email d'alerte envoyé");
            }
        }
    } catch (Exception $mailError) {
        cronLog("ERREUR: Impossible d'envoyer l'email d'alerte - " . $mailError->getMessage());
    }
}

// Si le script est appelé depuis le navigateur (pour test)
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<h2>Script de rapports automatiques TechSuivi</h2>";
    echo "<p>Ce script est conçu pour être exécuté via cron.</p>";
    echo "<p>Consultez le fichier <code>cron.log</code> pour voir les résultats d'exécution.</p>";
    
    // Afficher les dernières lignes du log si le fichier existe
    $logFile = dirname(__FILE__) . '/cron.log';
    if (file_exists($logFile)) {
        echo "<h3>Dernières entrées du log :</h3>";
        echo "<pre>";
        $lines = file($logFile);
        $lastLines = array_slice($lines, -10); // 10 dernières lignes
        echo htmlspecialchars(implode('', $lastLines));
        echo "</pre>";
    }
}
?>