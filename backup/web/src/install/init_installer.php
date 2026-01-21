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

        // 54b. AUTO-CRÉATION DES DOSSIERS UPLOADS (Indispensable si volume monté)
        $requiredDirs = [
            '/../uploads/downloads',
            '/../uploads/temp',
            '/../uploads/documents',
            '/../uploads/interventions',
            '/../uploads/stock',
            '/../uploads/backups',
            '/../uploads/autoit/logiciels',
            '/../uploads/autoit/nettoyage',
            '/../vnc_tokens'
        ];

        foreach ($requiredDirs as $dir) {
            $fullPath = __DIR__ . $dir;
            if (!is_dir($fullPath)) {
                writeLog("Création du dossier manquant : $dir");
                mkdir($fullPath, 0777, true);
                chmod($fullPath, 0777); // Force permissions larges
                // Assurer la propriété à www-data (car init tourne souvent en root au démarrage)
                chown($fullPath, 'www-data'); 
                chgrp($fullPath, 'www-data');
            }
        }

        // 54c. RESTAURATION CLÉS RUSTDESK
        $rustdeskDataDir = '/var/www/rustdesk_data';
        $keysDir = __DIR__ . '/../uploads/keys';
        
        // Créer le dossier keys s'il n'existe pas
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0777, true);
            chmod($keysDir, 0777);
            chown($keysDir, 'www-data');
        }

        if (is_dir($rustdeskDataDir)) {
            $pubKeySrc = $keysDir . '/id_ed25519.pub';
            $privKeySrc = $keysDir . '/id_ed25519';
            
            // On ne restaure que si la destination est VIDE ou n'a pas les clés
            if (file_exists($pubKeySrc) && file_exists($privKeySrc)) {
                $pubKeyDest = $rustdeskDataDir . '/id_ed25519.pub';
                $privKeyDest = $rustdeskDataDir . '/id_ed25519';

                // On restaure (écrase) les clés si elles sont présentes dans uploads
                writeLog("🔑 Restauration des clés Rustdesk (Overwrite)...");
                if (copy($privKeySrc, $privKeyDest) && copy($pubKeySrc, $pubKeyDest)) {
                    // Permissions: Root doit pouvoir lire (Rustdesk tourne souvent en root)
                    chmod($privKeyDest, 0600);
                    chmod($pubKeyDest, 0644);
                    writeLog("✓ Clés Rustdesk restaurées avec succès.");
                } else {
                    writeLog("❌ Erreur lors de la copie des clés Rustdesk.");
                }
            }
        }

        // AUTO-RESTAURATION : Si aucun fichier n'existe, on le prend du master
        $masterFile = '/usr/local/share/installeur.exe';
        if (!$foundSource && file_exists($masterFile)) {
            writeLog("Restauration de l'installeur depuis l'image Docker...");
            
            // Le dossier est déjà créé par la boucle ci-dessus
            $restoredFile = __DIR__ . '/../uploads/downloads/installeur.exe';
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

    // 3b. Mises à jour structurelles critiques (Synchro avec config/db_schema.php)
    writeLog("Vérification de la structure de la base de données...");
    
    $schemaFile = __DIR__ . '/../config/db_schema.php';
    if (file_exists($schemaFile)) {
        $schemaConfig = require $schemaFile;
        $criticalUpdates = $schemaConfig['columns'] ?? [];
        
        foreach ($criticalUpdates as $table => $column) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if (!$stmt->fetch()) {
                    writeLog("  + Ajout automatique de la colonne $column dans $table...");
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` INT DEFAULT NULL");
                    writeLog("    ✓ Succès.");
                }
            } catch (Exception $e) {
                 writeLog("  ⚠️ Erreur lors de la mise à jour structure $table : " . $e->getMessage());
            }
        }
    } else {
        writeLog("⚠️ Fichier de schéma non trouvé : $schemaFile");
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
    // On vérifie d'abord si une clé valide existe déjà en base (source de vérité)
    $stmt = $pdo->prepare("SELECT id, config_value FROM configuration WHERE config_key = 'api_key_autoit_client'");
    $stmt->execute();
    $configExists = $stmt->fetch();

    $bannedKeys = ['api_yxftwm9yzai8e5unki0to']; // Clés par défaut à bannir
    $finalApiKey = null;
    $shouldUpdateDb = false;

    // A. Clé en base de données ?
    if ($configExists && !empty($configExists['config_value']) && !in_array(trim($configExists['config_value']), $bannedKeys)) {
        $finalApiKey = trim($configExists['config_value']);
        writeLog("✓ Clé API récupérée depuis la base de données.");
    } else {
        // B. Clé dans cfg.ini (si pas en base ou invalide en base) ?
        $iniFile = __DIR__ . '/../Download/Install/ini/cfg.ini';
        if (file_exists($iniFile)) {
            $currentIni = parse_ini_file($iniFile, true);
            $iniKey = $currentIni['config']['key_api'] ?? '';
            
            if (!empty($iniKey) && strlen($iniKey) > 10 && !in_array(trim($iniKey), $bannedKeys)) {
                $finalApiKey = trim($iniKey);
                writeLog("✓ Clé API personnalisée détectée dans cfg.ini (et adoptée).");
                $shouldUpdateDb = true;
            }
        }
    }

    // C. Toujours rien ? Génération !
    if (!$finalApiKey) {
        $finalApiKey = 'api_' . substr(bin2hex(random_bytes(10)), 0, 20);
        $shouldUpdateDb = true;
        writeLog("✓ Génération d'une NOUVELLE clé API unique.");
    }

    // Mise à jour Base de données si nécessaire
    if ($shouldUpdateDb) {
        if ($configExists) {
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = :val WHERE id = :id");
            $stmt->execute([':val' => $finalApiKey, ':id' => $configExists['id']]);
            writeLog("✓ Base de données mise à jour avec la nouvelle clé.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('api_key_autoit_client', :val, 'text', 'Clé API AutoIt', 'api_keys')");
            $stmt->execute([':val' => $finalApiKey]);
            writeLog("✓ Base de données initialisée avec la nouvelle clé.");
        }
    }

    // Mise à jour de cfg.ini (Toujours, pour s'assurer que l'IP/URL et la clé sont synchro)
    $iniFile = __DIR__ . '/../Download/Install/ini/cfg.ini';
    $iniDir = dirname($iniFile);
    if (!is_dir($iniDir)) {
        mkdir($iniDir, 0775, true);
    }
    
    // Préparation des URLs pour le INI
    $apiUrl = $appUrl . "/api/autoit_api.php";
    $ipOnly = parse_url($appUrl, PHP_URL_HOST);

    $iniContent = "[config]\n";
    $iniContent .= "firstinit=0\n";
    $iniContent .= "url_base=" . $appUrl . "/\n";
    $iniContent .= "url_api=" . $apiUrl . "\n";
    $iniContent .= "key_api=" . $finalApiKey . "\n";
    $iniContent .= "id_inter=\n\n";
    $iniContent .= "[dl]\n";
    $iniContent .= "protocole=" . parse_url($appUrl, PHP_URL_SCHEME) . "\n";
    $iniContent .= "ip=" . $ipOnly . "\n";
    $iniContent .= "chemin=/\n";

    if (file_put_contents($iniFile, $iniContent)) {
        writeLog("✓ Fichier cfg.ini synchronisé (Clé: " . substr($finalApiKey, 0, 5) . "...)");
    } else {
        writeLog("❌ ERREUR : Impossible d'écrire dans $iniFile");
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
