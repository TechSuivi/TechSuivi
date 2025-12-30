<?php
/**
 * Script d'initialisation de l'installeur TechSuivi
 * Ce script est exécuté au démarrage du conteneur Docker.
 * Il renomme installeur.exe avec le HEX de l'URL de l'application
 * et met à jour la table download dans la base de données.
 */

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../install.log';
if (file_exists($logFile)) unlink($logFile); // On repart sur un log neuf

function writeLog($msg) {
    global $logFile;
    echo $msg . "\n";
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

writeLog("--- Initialisation de l'installeur TechSuivi ---");

try {
    // 1. Déterminer l'URL de base
    $appUrl = getenv('APP_URL');

    if (empty($appUrl)) {
        writeLog("ATTENTION : La variable d'environnement APP_URL n'est pas définie.");
        writeLog("Veuillez définir APP_URL dans votre configuration Docker.");
    } else {
        $appUrl = rtrim($appUrl, '/');
        $installUrl = $appUrl . "/Download/Install/";
        $hex = strtoupper(bin2hex($installUrl));
        $newFileName = "installeur_" . $hex . ".exe";
        $destFile = __DIR__ . '/../uploads/downloads/' . $newFileName;
        $dbUrl = '/uploads/downloads/' . $newFileName;

        writeLog("URL App : $appUrl");
        writeLog("HEX : $hex");

        // 2. Rechercher et renommer le fichier physique si nécessaire
        $allInstallers = glob(__DIR__ . '/../uploads/downloads/installeur*.exe');
        $foundSource = null;

        foreach ($allInstallers as $file) {
            if (basename($file) === $newFileName) {
                $foundSource = $file;
                writeLog("✓ Installeur déjà au bon nom : $newFileName");
                break;
            }
            // Si on trouve un installeur avec un autre HEX ou le nom de base
            $foundSource = $file; 
        }

        if ($foundSource && basename($foundSource) !== $newFileName) {
            if (rename($foundSource, $destFile)) {
                writeLog("✓ Mise à jour du nom (Changement d'URL) : $newFileName");
            } else {
                writeLog("ERREUR : Impossible de renommer $foundSource");
            }
        } elseif (!$foundSource) {
            writeLog("⚠️ Aucun fichier installeur*.exe trouvé dans uploads/downloads/");
        }
    }

    // 3. Mettre à jour la base de données
    $maxRetries = 30;
    $retryCount = 0;
    $pdo = null;

    writeLog("Attente de la base de données (max 150s)...");

    while ($retryCount < $maxRetries) {
        try {
            $pdo = getDatabaseConnection();
            writeLog("✓ Base de données connectée.");
            break;
        } catch (Exception $e) {
            $retryCount++;
            writeLog("  ($retryCount/$maxRetries) En attente...");
            sleep(5);
        }
    }

    if (!$pdo) {
        throw new Exception("Base de données inaccessible après $maxRetries tentatives.");
    }

    $stmt = $pdo->prepare("SELECT ID FROM download WHERE NOM = 'Installeur TechSuivi' OR URL LIKE '/uploads/downloads/installeur_%'");
    $stmt->execute();
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE download SET NOM = 'Installeur TechSuivi', URL = :url, show_on_login = 1 WHERE ID = :id");
        $stmt->execute([':url' => $dbUrl, ':id' => $existing['ID']]);
        writeLog("✓ Liaison DB mise à jour.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO download (NOM, DESCRIPTION, URL, show_on_login) VALUES ('Installeur TechSuivi', 'Installer TechSuivi', :url, 1)");
        $stmt->execute([':url' => $dbUrl]);
        writeLog("✓ Liaison DB créée.");
    }
    
} catch (Exception $e) {
    writeLog("❌ ERREUR : " . $e->getMessage());
} finally {
    writeLog("--- Initialisation terminée ---");

    // 4. Libérer le verrou d'installation
    $lockFile = __DIR__ . '/../install_in_progress.lock';
    if (file_exists($lockFile)) {
        if (unlink($lockFile)) {
            writeLog("✓ Système prêt.");
        } else {
            writeLog("❌ Erreur suppression verrou.");
        }
    }
}
?>
