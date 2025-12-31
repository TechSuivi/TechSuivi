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

        // AUTO-RESTAURATION : Si aucun fichier n'existe, on le prend du master
        $masterFile = '/usr/local/share/installeur.exe';
        if (!$foundSource && file_exists($masterFile)) {
            writeLog("Restauration de l'installeur depuis l'image Docker...");
            
            // Assurer que le dossier de destination existe (cas des volumes vides)
            $destDir = __DIR__ . '/../uploads/downloads';
            if (!is_dir($destDir)) {
                writeLog("Création du dossier $destDir...");
                mkdir($destDir, 0775, true);
            }
            
            $restoredFile = $destDir . '/installeur.exe';
            if (copy($masterFile, $restoredFile)) {
                chmod($restoredFile, 0666); // Droits rw pour tous
                $foundSource = $restoredFile;
                writeLog("✓ Installeur restauré avec succès.");
            } else {
                writeLog("ERREUR : Échec de la restauration. Vérifiez les permissions du dossier uploads.");
                writeLog("Source: $masterFile");
                writeLog("Dest: $restoredFile");
            }
        }

        if ($foundSource && basename($foundSource) !== $newFileName) {
            if (rename($foundSource, $destFile)) {
                chmod($destFile, 0644);
                writeLog("✓ Mise à jour du nom (Changement d'URL) : $newFileName");
            } else {
                writeLog("ERREUR : Impossible de renommer $foundSource");
            }
        } elseif (!$foundSource) {
            writeLog("⚠️ Aucun fichier installeur*.exe trouvé dans uploads/downloads/");
            writeLog("Veuillez uploader 'installeur.exe' dans la page Téléchargements.");
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
    
    // ... (Code précédent pour la table download) ...

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE download SET NOM = 'Installeur TechSuivi', URL = :url, show_on_login = 1 WHERE ID = :id");
        $stmt->execute([':url' => $dbUrl, ':id' => $existing['ID']]);
        writeLog("✓ Liaison DB mise à jour.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO download (NOM, DESCRIPTION, URL, show_on_login) VALUES ('Installeur TechSuivi', 'Installer TechSuivi', :url, 1)");
        $stmt->execute([':url' => $dbUrl]);
        writeLog("✓ Liaison DB créée.");
    }

    // ---------------------------------------------------------
    // 4. Mettre à jour cfg.ini et la clé API
    // ---------------------------------------------------------
    $iniFile = __DIR__ . '/../Download/Install/ini/cfg.ini';
    $iniDir = dirname($iniFile);
    
    if (!is_dir($iniDir)) {
        mkdir($iniDir, 0775, true);
    }

    // Valeurs par défaut
    $apiKey = 'api_' . substr(bin2hex(random_bytes(10)), 0, 20);
    $currentIni = [];

    if (file_exists($iniFile)) {
        $currentIni = parse_ini_file($iniFile, true);
        // Si une clé existe déjà et n'est pas celle par défaut (exemple "api_ChangeMe"), on la garde
        if (!empty($currentIni['config']['key_api']) && strlen($currentIni['config']['key_api']) > 10) {
            $apiKey = $currentIni['config']['key_api'];
            writeLog("Clé API existante conservée : " . substr($apiKey, 0, 5) . "...");
        } else {
            writeLog("Génération d'une nouvelle clé API.");
        }
    } else {
        writeLog("Création du fichier cfg.ini...");
    }

    // Préparation des URLs
    $apiUrl = $appUrl . "/api/autoit_api.php";
    $ipOnly = parse_url($appUrl, PHP_URL_HOST);

    // Contenu INI propre
    $iniContent = "[config]\n";
    $iniContent .= "firstinit=0\n";
    $iniContent .= "url_base=" . $appUrl . "/\n";
    $iniContent .= "url_api=" . $apiUrl . "\n";
    $iniContent .= "key_api=" . $apiKey . "\n";
    $iniContent .= "id_inter=\n\n";
    $iniContent .= "[dl]\n";
    $iniContent .= "protocole=" . parse_url($appUrl, PHP_URL_SCHEME) . "\n";
    $iniContent .= "ip=" . $ipOnly . "\n";
    $iniContent .= "chemin=/\n";

    if (file_put_contents($iniFile, $iniContent)) {
        writeLog("✓ Fichier cfg.ini mis à jour avec l'IP $ipOnly");
    } else {
        writeLog("❌ ERREUR : Impossible d'écrire dans $iniFile");
    }

    // 5. Insérer/Mettre à jour la clé API en base de données
    // On cherche la config 'api_key_autoit_client'
    $stmt = $pdo->prepare("SELECT id FROM configuration WHERE config_key = 'api_key_autoit_client'");
    $stmt->execute();
    $configExists = $stmt->fetch();

    if ($configExists) {
        $stmt = $pdo->prepare("UPDATE configuration SET config_value = :val WHERE id = :id");
        $stmt->execute([':val' => $apiKey, ':id' => $configExists['id']]);
        writeLog("✓ Clé API mise à jour dans la base de données.");
    } else {
        // Insertion (Attention aux champs par défaut de votre table configuration)
        // Basé sur votre dump : (config_key, config_value, config_type, description, category)
        $stmt = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('api_key_autoit_client', :val, 'text', 'Clé API AutoIt', 'api_keys')");
        $stmt->execute([':val' => $apiKey]);
        writeLog("✓ Nouvelle clé API insérée dans la base de données.");
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
