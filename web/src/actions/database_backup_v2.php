<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

// Inclure le système de permissions
require_once __DIR__ . '/../utils/permissions_helper.php';

/**
 * Parse SQL content intelligemment pour éviter les problèmes avec les points-virgules dans les données
 */
function parseSQL($sqlContent) {
    $queries = [];
    $currentQuery = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    
    $lines = explode("\n", $sqlContent);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Ignorer les lignes vides et les commentaires
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        // Traiter caractère par caractère pour gérer les chaînes correctement
        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            
            if ($escaped) {
                $currentQuery .= $char;
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $currentQuery .= $char;
                $escaped = true;
                continue;
            }
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $currentQuery .= $char;
                continue;
            }
            
            if ($inString && $char === $stringChar) {
                $inString = false;
                $stringChar = '';
                $currentQuery .= $char;
                continue;
            }
            
            if (!$inString && $char === ';') {
                // Fin de requête
                $currentQuery = trim($currentQuery);
                if (!empty($currentQuery)) {
                    $queries[] = $currentQuery;
                }
                $currentQuery = '';
                continue;
            }
            
            $currentQuery .= $char;
        }
        
        // Ajouter un espace entre les lignes si on est dans une requête
        if (!empty($currentQuery)) {
            $currentQuery .= ' ';
        }
    }
    
    // Ajouter la dernière requête si elle existe
    $currentQuery = trim($currentQuery);
    if (!empty($currentQuery)) {
        $queries[] = $currentQuery;
    }
    
    return array_filter($queries);
}

// FORCER L'AFFICHAGE DE LA VERSION v2.8 - IMPOSSIBLE À IGNORER
$_SESSION['restore_message'] = "🚀 VERSION v2.8 ULTRA-FORCÉE ACTIVÉE - SYSTÈME DE RESTAURATION FINAL";
$_SESSION['restore_message_type'] = 'info';

// Debug: Forcer l'écriture immédiate de la session
session_write_close();
session_start();

// Vérifier si c'est un téléchargement direct - dans ce cas, pas de debug output
$isDownload = (isset($_POST['create_backup']) && isset($_POST['backup_destination']) && $_POST['backup_destination'] === 'download');

try {
    // Debug: Capturer toutes les erreurs (mais pas d'affichage si téléchargement)
    error_reporting(E_ALL);
    if (!$isDownload) {
        ini_set('display_errors', 1);
    } else {
        ini_set('display_errors', 0);
    }
    
    // Debug complet - Log toutes les informations
    $debugInfo = [
        'POST_data' => $_POST,
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'Non défini',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'Non défini',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'v2.8-ULTRA-FORCED'
    ];
    
    // Sauvegarder le debug dans la session
    $_SESSION['backup_debug'] = $debugInfo;
    
    // Vérifier si c'est une demande de sauvegarde
    if (isset($_POST['create_backup'])) {
        $_SESSION['backup_message'] = "🚀 DEBUG v2.8 ULTRA-FORCÉ: Demande de sauvegarde reçue à " . date('H:i:s');
        $_SESSION['backup_message_type'] = 'info';
        
        // Récupérer les options de sauvegarde
        $backupType = $_POST['backup_type'] ?? 'full';
        $backupFormat = $_POST['backup_format'] ?? 'sql';
        $backupDestination = $_POST['backup_destination'] ?? 'download';
        $selectedTables = $_POST['selected_tables'] ?? [];
        $backupPassword = $_POST['backup_password'] ?? '';
        
        // Si un mot de passe est défini, on force le format ZIP
        if (!empty($backupPassword)) {
            $backupFormat = 'zip';
        }
        
        // Validation pour sauvegarde partielle
        if ($backupType === 'partial' && empty($selectedTables)) {
            $_SESSION['backup_message'] = "❌ Erreur v2.8 ULTRA-FORCÉ: Aucune table sélectionnée pour la sauvegarde partielle.";
            $_SESSION['backup_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // Créer le nom du fichier de sauvegarde avec timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $typePrefix = $backupType === 'partial' ? 'partial_' : '';
        $backupFileName = "techsuivi_{$typePrefix}backup_{$timestamp}";
        
        // Déterminer les chemins selon la destination
        if ($backupDestination === 'server') {
            $backupDir = __DIR__ . '/../uploads/backups/';
            
            // Utiliser le système de permissions pour créer le dossier
            if (!createDirectoryWithPermissions($backupDir)) {
                $_SESSION['backup_message'] = "❌ Erreur v2.8 ULTRA-FORCÉ de permissions : " . getPermissionErrorMessage($backupDir);
                $_SESSION['backup_message_type'] = 'error';
                header('Location: ../index.php?page=database_backup');
                exit();
            }
            
            $backupPath = $backupDir . $backupFileName;
        } else {
            $backupPath = "/tmp/{$backupFileName}";
        }
        
        // Extension selon le format
        $sqlPath = $backupPath . '.sql';
        $finalPath = $backupFormat === 'zip' ? $backupPath . '.zip' : $sqlPath;
        $finalFileName = basename($finalPath);
        
        // Les permissions sont maintenant gérées par le système de permissions
        // Pas besoin de vérification supplémentaire ici
        
        // Augmenter les limites pour les gros fichiers
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '1G');
        
        // Créer la connexion PDO
        $pdo = getDatabaseConnection();
        
        // Générer la sauvegarde SQL
        $backup = "-- TechSuivi Database Backup v2.8 ULTRA-FORCED\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Backup type: " . ($backupType === 'partial' ? 'Partial (' . count($selectedTables) . ' tables)' : 'Full') . "\n";
        $backup .= "-- Format: " . strtoupper($backupFormat) . "\n";
        $backup .= "-- Version: v2.8 ULTRA-FORCED FINAL UPDATE\n\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $backup .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup .= "SET AUTOCOMMIT = 0;\n";
        $backup .= "START TRANSACTION;\n\n";
        
        // Obtenir la liste des tables à sauvegarder
        if ($backupType === 'partial') {
            $tables = $selectedTables;
        } else {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $tableCount = 0;
        $totalRows = 0;
        
        foreach ($tables as $table) {
            try {
                // Vérifier que la table existe
                $checkTable = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
                if (!$checkTable) {
                    continue; // Ignorer les tables qui n'existent pas
                }
                
                // Structure de la table
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
                $backup .= "-- --------------------------------------------------------\n";
                $backup .= "-- Structure for table `{$table}` (v2.8 ULTRA-FORCED)\n";
                $backup .= "-- --------------------------------------------------------\n\n";
                
                // Remplacer CREATE TABLE par CREATE TABLE IF NOT EXISTS pour éviter les conflits
                $createTableSQL = $createTable['Create Table'];
                $createTableSQL = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $createTableSQL);
                $backup .= $createTableSQL . ";\n\n";
                
                // Données de la table
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
                $rowCount = $stmt->fetch()['count'];
                
                if ($rowCount > 0) {
                    $backup .= "-- --------------------------------------------------------\n";
                    $backup .= "-- Data for table `{$table}` ({$rowCount} rows) v2.8 ULTRA-FORCED\n";
                    $backup .= "-- --------------------------------------------------------\n\n";
                    
                    // Traiter les données par chunks pour éviter les problèmes de mémoire
                    $chunkSize = 1000;
                    $offset = 0;
                    
                    while ($offset < $rowCount) {
                        $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$chunkSize} OFFSET {$offset}")->fetchAll();
                        
                        if (!empty($rows)) {
                            $columns = array_keys($rows[0]);
                            $backup .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                            
                            $values = [];
                            foreach ($rows as $row) {
                                $rowValues = [];
                                foreach ($row as $value) {
                                    if ($value === null) {
                                        $rowValues[] = 'NULL';
                                    } elseif (is_numeric($value)) {
                                        $rowValues[] = $value;
                                    } else {
                                        $rowValues[] = "'" . addslashes($value) . "'";
                                    }
                                }
                                $values[] = "(" . implode(', ', $rowValues) . ")";
                            }
                            $backup .= implode(",\n", $values) . ";\n\n";
                        }
                        
                        $offset += $chunkSize;
                    }
                    
                    $totalRows += $rowCount;
                }
                
                $tableCount++;
                
            } catch (PDOException $e) {
                // Continuer avec les autres tables en cas d'erreur
                $backup .= "-- ERROR v2.6 with table `{$table}`: " . $e->getMessage() . "\n\n";
            }
        }
        
        $backup .= "COMMIT;\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup .= "\n-- Backup completed v2.6: {$tableCount} tables, {$totalRows} total rows\n";
        
        // Écrire le fichier SQL
        file_put_contents($sqlPath, $backup);
        
        // Traitement selon le format
        if ($backupFormat === 'zip') {
            // Créer un fichier ZIP
                if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                // Si mot de passe, on utilise la commande système "zip" si dispo (meilleur cryptage)
                $zipBinPath = shell_exec('which zip');
                $zipBin = $zipBinPath ? trim($zipBinPath) : '';
                $useSystemZip = !empty($zipBin);
                
                if (!empty($backupPassword) && $useSystemZip) {
                    error_log("DBBackup: Password set, Zip bin: $zipBin");
                    $currentDir = getcwd();
                    chdir(dirname($sqlPath));
                    $sqlFile = basename($sqlPath);
                    $cmd = sprintf("'%s' -j -P %s %s %s", $zipBin, escapeshellarg($backupPassword), escapeshellarg($finalPath), escapeshellarg($sqlFile));
                    
                    error_log("DB Backup trying: $cmd");
                    exec($cmd, $output, $returnVar);
                    chdir($currentDir);
                    
                    if ($returnVar !== 0 || !file_exists($finalPath) || filesize($finalPath) === 0) {
                        error_log("DB Backup Zip failed: " . implode(', ', $output));
                        if(file_exists($finalPath)) @unlink($finalPath);
                        $useSystemZip = false; 
                    }
                }

                if (empty($backupPassword) || !$useSystemZip) {
                    // Méthode PHP classique (fallback ou sans mot de passe)
                    $flags = ZipArchive::CREATE;
                    if (file_exists($finalPath)) $flags |= ZipArchive::OVERWRITE;
                    
                    if ($zip->open($finalPath, $flags) === TRUE) {
                        // Si un mot de passe est défini (mais zip commande échouée)
                        if (!empty($backupPassword)) {
                             // Fallback compatible
                             $zip->setPassword($backupPassword);
                        }
                        
                        $zip->addFile($sqlPath, basename($sqlPath));
                        
                        // Tentative chiffrement spécifique si dispo
                        if (!empty($backupPassword) && defined('ZipArchive::EM_TRADITIONAL')) {
                             $zip->setEncryptionName(basename($sqlPath), ZipArchive::EM_TRADITIONAL);
                        }
                        
                        if (!$zip->close()) {
                            throw new Exception("Erreur fermeture ZIP");
                        }
                    } else {
                        throw new Exception("Impossible de créer le fichier ZIP v2.6");
                    }
                }
    
                // Supprimer le fichier SQL temporaire
                unlink($sqlPath);
            } else {
                // Fallback vers SQL si ZIP non disponible
                $_SESSION['backup_message'] = "⚠️ Extension ZIP non disponible v2.6, sauvegarde créée au format SQL";
                $_SESSION['backup_message_type'] = 'warning';
                $finalPath = $sqlPath;
                $finalFileName = basename($sqlPath);
            }
        } else {
            $finalPath = $sqlPath;
        }
        
        // Vérifier que le fichier final existe et n'est pas vide
        if (!file_exists($finalPath) || filesize($finalPath) === 0) {
            throw new Exception("Le fichier de sauvegarde v2.6 est vide ou n'a pas pu être créé");
        }
        
        $fileSize = round(filesize($finalPath) / 1024 / 1024, 2);
        
        // Traitement selon la destination
        if ($backupDestination === 'server') {
            $_SESSION['backup_message'] = "✅ Sauvegarde v2.6 créée avec succès sur le serveur : {$finalFileName} ({$fileSize} MB). {$tableCount} tables sauvegardées.";
            $_SESSION['backup_message_type'] = 'success';
            
            header('Location: ../index.php?page=database_backup');
            exit();
            
        } else {
            // Téléchargement direct
            
            // Nettoyer complètement tous les buffers de sortie
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Vérifier que le fichier existe et est lisible
            if (!file_exists($finalPath) || !is_readable($finalPath)) {
                $_SESSION['backup_message'] = "❌ Erreur v2.6 : Fichier de sauvegarde non accessible pour téléchargement.";
                $_SESSION['backup_message_type'] = 'error';
                header('Location: ../index.php?page=database_backup');
                exit();
            }
            
            // Désactiver la compression automatique
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            ini_set('zlib.output_compression', 'Off');
            
            // Headers pour forcer le téléchargement
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $finalFileName . '"');
            header('Content-Length: ' . filesize($finalPath));
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Expires: 0');
            
            // Vider les buffers avant d'envoyer le fichier
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            
            // Lire et envoyer le fichier par chunks pour les gros fichiers
            $handle = fopen($finalPath, 'rb');
            if ($handle) {
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192); // Lire par chunks de 8KB
                    echo $chunk;
                    flush();
                    
                    // Vérifier si la connexion est toujours active
                    if (connection_aborted()) {
                        break;
                    }
                }
                fclose($handle);
            } else {
                // Fallback avec readfile si fopen échoue
                readfile($finalPath);
            }
            
            // Supprimer le fichier temporaire
            if (file_exists($finalPath)) {
                unlink($finalPath);
            }
            
            exit();
        }
        
    } elseif (isset($_POST['restore_from_server'])) {
        // RESTAURATION DEPUIS SERVEUR - VERSION v2.6 FORCÉE
        $serverFile = $_POST['server_backup_file'] ?? '';
        $dropTables = isset($_POST['drop_tables']) && $_POST['drop_tables'] === '1';
        
        if (empty($serverFile)) {
            $_SESSION['restore_message'] = "❌ Erreur v2.6 : Aucun fichier sélectionné.";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        $restorePassword = $_POST['restore_password'] ?? '';
        
        $serverPath = __DIR__ . '/../uploads/backups/' . basename($serverFile);
        
        if (!file_exists($serverPath)) {
            $_SESSION['restore_message'] = "❌ Erreur v2.6 : Fichier non trouvé sur le serveur : {$serverFile}";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // TRAITEMENT DE LA RESTAURATION v2.6 AVEC LOGGING DÉTAILLÉ FORCÉ
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1G');
            
            // Debug: Créer un fichier de log temporaire pour diagnostiquer
            $debugLogFile = __DIR__ . '/../uploads/debug_restore_v2.6.log';
            file_put_contents($debugLogFile, "=== DÉBUT RESTAURATION v2.6 FORCÉE ===\n", FILE_APPEND);
            file_put_contents($debugLogFile, "Timestamp: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            file_put_contents($debugLogFile, "Fichier: $serverFile\n", FILE_APPEND);
            
            $pdo = getDatabaseConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $restoreResults = [];
            $successCount = 0;
            $errorCount = 0;
            
            // FORCER L'AFFICHAGE DE LA VERSION v2.8 ULTRA-FORCÉE
            $restoreResults[] = "🚀 SYSTÈME DE RESTAURATION v2.8 ULTRA-FORCÉ ACTIVÉ - VERSION FINALE";
            $restoreResults[] = "📅 Timestamp : " . date('Y-m-d H:i:s');
            
            file_put_contents($debugLogFile, "Initialisation terminée\n", FILE_APPEND);
            
            // Désactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $restoreResults[] = "✅ Vérifications de clés étrangères désactivées (v2.6)";
            
            // Vider les tables si demandé
            if ($dropTables) {
                try {
                    // Obtenir la liste des tables
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $restoreResults[] = "🗑️ Table `$table` supprimée (v2.6)";
                    }
                    $restoreResults[] = "✅ Toutes les tables ont été vidées (v2.6)";
                } catch (PDOException $e) {
                    $restoreResults[] = "⚠️ Erreur v2.6 lors du vidage des tables : " . $e->getMessage();
                    $errorCount++;
                }
            }
            
            // Traitement du fichier (ZIP ou SQL)
            $sqlContent = '';
            if (pathinfo($serverPath, PATHINFO_EXTENSION) === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($serverPath) === TRUE) {
                        
                        // Si un mot de passe est fourni, l'appliquer
                        if (!empty($restorePassword)) {
                            $zip->setPassword($restorePassword);
                        }
                        $restoreResults[] = "📦 Fichier ZIP ouvert avec succès (v2.6)";
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                                $sqlContent = $zip->getFromIndex($i);
                                $restoreResults[] = "📄 Fichier SQL extrait : $filename (v2.6)";
                                break;
                            }
                        }
                        $zip->close();
                    } else {
                        throw new Exception("Impossible d'ouvrir le fichier ZIP v2.6");
                    }
                } else {
                    throw new Exception("Extension ZIP non disponible v2.6");
                }
            } else {
                $sqlContent = file_get_contents($serverPath);
                $restoreResults[] = "📄 Fichier SQL lu directement (v2.6)";
            }
            
            if (empty($sqlContent)) {
                throw new Exception("Impossible de lire le contenu SQL du fichier v2.6");
            }
            
            // Diviser le contenu en requêtes individuelles avec un parsing plus intelligent
            $queries = parseSQL($sqlContent);
            $validQueries = 0;
            
            // COMPTAGE PRÉCIS DES REQUÊTES VALIDES - v2.6 FORCÉ
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) &&
                    strpos($query, '--') !== 0 &&
                    strpos($query, '/*') !== 0 &&
                    stripos($query, 'SET ') !== 0 &&
                    stripos($query, 'START TRANSACTION') !== 0 &&
                    stripos($query, 'COMMIT') !== 0 &&
                    stripos($query, 'AUTOCOMMIT') !== 0) {
                    $validQueries++;
                }
            }
            
            $restoreResults[] = "📋 " . count($queries) . " requêtes brutes analysées (v2.6)";
            $restoreResults[] = "📊 $validQueries requêtes valides détectées (v2.8 ULTRA-FORCÉ)";
            
            // Collecter les tables qui vont être restaurées pour vider leurs données
            $tablesToClear = [];
            foreach ($queries as $query) {
                if (stripos($query, 'CREATE TABLE') === 0) {
                    preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                    $tableName = $matches[1] ?? null;
                    if ($tableName) {
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $tablesToClear[] = $tableName;
                        }
                    }
                }
            }
            
            // Vider les données des tables existantes qui vont être restaurées
            if (!empty($tablesToClear)) {
                $restoreResults[] = "🧹 Vidage des données des tables existantes... (v2.6)";
                foreach ($tablesToClear as $tableName) {
                    try {
                        $pdo->exec("DELETE FROM `$tableName`");
                        $restoreResults[] = "🗑️ Données de la table `$tableName` vidées (v2.6)";
                    } catch (PDOException $e) {
                        $restoreResults[] = "⚠️ Impossible de vider `$tableName` (v2.6) : " . $e->getMessage();
                    }
                }
            }
            
            foreach ($queries as $index => $query) {
                // Nettoyer la requête
                $query = trim($query);
                
                // Ignorer les lignes vides et les commentaires purs
                if (empty($query) || strpos($query, '--') === 0 || strpos($query, '/*') === 0) {
                    continue;
                }
                
                // Ignorer les requêtes de configuration MySQL
                if (stripos($query, 'SET ') === 0 || stripos($query, 'START TRANSACTION') === 0 ||
                    stripos($query, 'COMMIT') === 0 || stripos($query, 'AUTOCOMMIT') === 0) {
                    continue;
                }
                
                // DEBUG AVANT EXÉCUTION - v2.6 FORCÉ
                $queryType = 'UNKNOWN';
                if (stripos($query, 'CREATE TABLE') === 0) $queryType = 'CREATE TABLE';
                elseif (stripos($query, 'INSERT INTO') === 0) $queryType = 'INSERT INTO';
                elseif (stripos($query, 'DROP TABLE') === 0) $queryType = 'DROP TABLE';
                elseif (stripos($query, 'ALTER TABLE') === 0) $queryType = 'ALTER TABLE';
                
                $restoreResults[] = "🔍 Exécution requête $queryType (#" . ($index + 1) . ") - v2.8 ULTRA-FORCÉ";
                
                // Debug dans le fichier de log
                file_put_contents($debugLogFile, "Requête #" . ($index + 1) . " - Type: $queryType\n", FILE_APPEND);
                
                try {
                    // Gestion spéciale pour les CREATE TABLE
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $restoreResults[] = "⚠️ Table `$tableName` existe déjà - structure ignorée (v2.6)";
                            $successCount++; // Compter comme succès car c'est intentionnel
                            continue; // Passer à la requête suivante
                        }
                        
                        // Modifier dynamiquement les anciennes sauvegardes pour ajouter IF NOT EXISTS
                        if (stripos($query, 'IF NOT EXISTS') === false) {
                            $query = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $query);
                            $restoreResults[] = "🔧 Requête CREATE TABLE modifiée pour `$tableName` (compatibilité v2.6)";
                        }
                    }
                    
                    // Exécuter la requête
                    $result = $pdo->exec($query);
                    $successCount++;
                    
                    // Log détaillé pour les requêtes importantes - v2.6 FORCÉ
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "✅ Table `$tableName` créée avec succès (v2.6)";
                    } elseif (stripos($query, 'INSERT INTO') === 0) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        // Compter les lignes insérées
                        $insertCount = substr_count($query, '),(') + 1;
                        $affectedRows = $result !== false ? $result : 'N/A';
                        $restoreResults[] = "📝 $insertCount ligne(s) insérée(s) dans `$tableName` (Lignes affectées: $affectedRows) - v2.6 FORCÉ";
                    } elseif (stripos($query, 'DROP TABLE') === 0) {
                        preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🗑️ Table `$tableName` supprimée (v2.6)";
                    } elseif (stripos($query, 'ALTER TABLE') === 0) {
                        preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🔧 Table `$tableName` modifiée (v2.6)";
                    }
                    
                } catch (PDOException $e) {
                    // Gestion spéciale des erreurs courantes
                    $errorMessage = $e->getMessage();
                    
                    // Si c'est une erreur de table existante, la traiter comme un avertissement
                    if (strpos($errorMessage, 'already exists') !== false) {
                        preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Table `$tableName` existe déjà - ignorée (v2.6)";
                        $successCount++; // Compter comme succès car c'est intentionnel
                        continue; // Ne pas compter comme erreur
                    }
                    
                    // Si c'est une erreur de clé dupliquée, la traiter comme un avertissement
                    if (strpos($errorMessage, 'Duplicate entry') !== false) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Données dupliquées ignorées dans `$tableName` (v2.6)";
                        continue; // Ne pas compter comme erreur
                    }
                    
                    $errorCount++;
                    $queryPreview = substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');
                    $restoreResults[] = "❌ Erreur requête " . ($index + 1) . " (v2.6) : " . $errorMessage;
                    $restoreResults[] = "   Requête : " . htmlspecialchars($queryPreview);
                }
            }
            
            // Réactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $restoreResults[] = "✅ Vérifications de clés étrangères réactivées (v2.6)";
            
            $fileSize = round(filesize($serverPath) / 1024 / 1024, 2);
            
            // Message de résumé - v2.6 FORCÉ
            if ($errorCount === 0) {
                $message = "✅ Restauration v2.8 ULTRA-FORCÉE réussie depuis : {$serverFile} ({$fileSize} MB). $successCount requêtes exécutées avec succès.";
                $messageType = 'success';
            } else {
                $message = "⚠️ Restauration v2.8 ULTRA-FORCÉE partiellement réussie depuis : {$serverFile} ({$fileSize} MB). $successCount requêtes OK, $errorCount erreurs.";
                $messageType = 'warning';
            }
            
            // Ajouter les détails
            $message .= "<br><br><strong>📋 Détails de la restauration v2.8 ULTRA-FORCÉE :</strong><br>";
            $message .= implode('<br>', $restoreResults);
            
            $_SESSION['restore_message'] = $message;
            $_SESSION['restore_message_type'] = $messageType;
            
            // Debug: Forcer l'écriture de la session
            session_write_close();
            session_start();
            
            // Debug final dans le fichier de log
            file_put_contents($debugLogFile, "=== FIN RESTAURATION v2.6 FORCÉE ===\n", FILE_APPEND);
            file_put_contents($debugLogFile, "Succès: $successCount, Erreurs: $errorCount\n", FILE_APPEND);
            file_put_contents($debugLogFile, "Message final: " . substr($message, 0, 200) . "...\n", FILE_APPEND);
            
        } catch (Exception $e) {
            $errorMsg = "❌ Erreur critique v2.6 lors de la restauration : " . $e->getMessage();
            $_SESSION['restore_message'] = $errorMsg;
            $_SESSION['restore_message_type'] = 'error';
            
            // Debug erreur dans le fichier de log
            if (isset($debugLogFile)) {
                file_put_contents($debugLogFile, "ERREUR CRITIQUE: " . $e->getMessage() . "\n", FILE_APPEND);
                file_put_contents($debugLogFile, "Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            }
        }
        
        header('Location: ../index.php?page=database_backup');
        exit();
        
    } elseif (isset($_POST['restore_upload']) && isset($_FILES['backup_file'])) {
        // RESTAURATION DEPUIS UPLOAD - VERSION v2.6 FORCÉE
        $uploadedFile = $_FILES['backup_file'];
        $dropTables = isset($_POST['drop_tables']) && $_POST['drop_tables'] === '1';
        $restorePassword = $_POST['restore_password'] ?? '';
        
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['restore_message'] = "❌ Erreur v2.6 lors de l'upload : " . $uploadedFile['error'];
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // Vérifier le type de fichier
        $allowedExtensions = ['sql', 'zip'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $_SESSION['restore_message'] = "❌ Erreur v2.6 : Type de fichier non supporté. Utilisez .sql ou .zip";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // TRAITEMENT DE LA RESTAURATION UPLOAD v2.6 AVEC LOGGING DÉTAILLÉ FORCÉ
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1G');
            
            $pdo = getDatabaseConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $restoreResults = [];
            $successCount = 0;
            $errorCount = 0;
            
            // FORCER L'AFFICHAGE DE LA VERSION v2.6
            $restoreResults[] = "🚀 SYSTÈME DE RESTAURATION UPLOAD v2.8 ULTRA-FORCÉ ACTIVÉ - VERSION FINALE";
            $restoreResults[] = "📅 Timestamp : " . date('Y-m-d H:i:s');
            $restoreResults[] = "📁 Fichier : " . $uploadedFile['name'];
            
            // Désactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $restoreResults[] = "✅ Vérifications de clés étrangères désactivées (v2.6)";
            
            // Vider les tables si demandé
            if ($dropTables) {
                try {
                    // Obtenir la liste des tables
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $restoreResults[] = "🗑️ Table `$table` supprimée (v2.6)";
                    }
                    $restoreResults[] = "✅ Toutes les tables ont été vidées (v2.6)";
                } catch (PDOException $e) {
                    $restoreResults[] = "⚠️ Erreur v2.6 lors du vidage des tables : " . $e->getMessage();
                    $errorCount++;
                }
            }
            
            // Traitement du fichier (ZIP ou SQL)
            $sqlContent = '';
            if ($fileExtension === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($uploadedFile['tmp_name']) === TRUE) {
                        
                        // Si un mot de passe est fourni, l'appliquer
                        if (!empty($restorePassword)) {
                            $zip->setPassword($restorePassword);
                        }
                        $restoreResults[] = "📦 Fichier ZIP ouvert avec succès (v2.6)";
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                                $sqlContent = $zip->getFromIndex($i);
                                $restoreResults[] = "📄 Fichier SQL extrait : $filename (v2.6)";
                                break;
                            }
                        }
                        $zip->close();
                    } else {
                        throw new Exception("Impossible d'ouvrir le fichier ZIP v2.6");
                    }
                } else {
                    throw new Exception("Extension ZIP non disponible v2.6");
                }
            } else {
                $sqlContent = file_get_contents($uploadedFile['tmp_name']);
                $restoreResults[] = "📄 Fichier SQL lu directement (v2.6)";
            }
            
            if (empty($sqlContent)) {
                throw new Exception("Impossible de lire le contenu SQL du fichier v2.6");
            }
            
            // Diviser le contenu en requêtes individuelles avec un parsing plus intelligent
            $queries = parseSQL($sqlContent);
            $validQueries = 0;
            
            // COMPTAGE PRÉCIS DES REQUÊTES VALIDES - v2.6 FORCÉ
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) &&
                    strpos($query, '--') !== 0 &&
                    strpos($query, '/*') !== 0 &&
                    stripos($query, 'SET ') !== 0 &&
                    stripos($query, 'START TRANSACTION') !== 0 &&
                    stripos($query, 'COMMIT') !== 0 &&
                    stripos($query, 'AUTOCOMMIT') !== 0) {
                    $validQueries++;
                }
            }
            
            $restoreResults[] = "📋 " . count($queries) . " requêtes brutes analysées (v2.6)";
            $restoreResults[] = "📊 $validQueries requêtes valides détectées (v2.8 ULTRA-FORCÉ UPLOAD)";
            
            // Collecter les tables qui vont être restaurées pour vider leurs données
            $tablesToClear = [];
            foreach ($queries as $query) {
                if (stripos($query, 'CREATE TABLE') === 0) {
                    preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                    $tableName = $matches[1] ?? null;
                    if ($tableName) {
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $tablesToClear[] = $tableName;
                        }
                    }
                }
            }
            
            // Vider les données des tables existantes qui vont être restaurées
            if (!empty($tablesToClear)) {
                $restoreResults[] = "🧹 Vidage des données des tables existantes... (v2.6)";
                foreach ($tablesToClear as $tableName) {
                    try {
                        $pdo->exec("DELETE FROM `$tableName`");
                        $restoreResults[] = "🗑️ Données de la table `$tableName` vidées (v2.6)";
                    } catch (PDOException $e) {
                        $restoreResults[] = "⚠️ Impossible de vider `$tableName` (v2.6) : " . $e->getMessage();
                    }
                }
            }
            
            foreach ($queries as $index => $query) {
                // Nettoyer la requête
                $query = trim($query);
                
                // Ignorer les lignes vides et les commentaires purs
                if (empty($query) || strpos($query, '--') === 0 || strpos($query, '/*') === 0) {
                    continue;
                }
                
                // Ignorer les requêtes de configuration MySQL
                if (stripos($query, 'SET ') === 0 || stripos($query, 'START TRANSACTION') === 0 ||
                    stripos($query, 'COMMIT') === 0 || stripos($query, 'AUTOCOMMIT') === 0) {
                    continue;
                }
                
                // DEBUG AVANT EXÉCUTION - v2.6 FORCÉ UPLOAD
                $queryType = 'UNKNOWN';
                if (stripos($query, 'CREATE TABLE') === 0) $queryType = 'CREATE TABLE';
                elseif (stripos($query, 'INSERT INTO') === 0) $queryType = 'INSERT INTO';
                elseif (stripos($query, 'DROP TABLE') === 0) $queryType = 'DROP TABLE';
                elseif (stripos($query, 'ALTER TABLE') === 0) $queryType = 'ALTER TABLE';
                
                $restoreResults[] = "🔍 Exécution requête $queryType (#" . ($index + 1) . ") - v2.8 ULTRA-FORCÉ UPLOAD";
                
                try {
                    // Gestion spéciale pour les CREATE TABLE
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $restoreResults[] = "⚠️ Table `$tableName` existe déjà - structure ignorée (v2.6)";
                            $successCount++; // Compter comme succès car c'est intentionnel
                            continue; // Passer à la requête suivante
                        }
                        
                        // Modifier dynamiquement les anciennes sauvegardes pour ajouter IF NOT EXISTS
                        if (stripos($query, 'IF NOT EXISTS') === false) {
                            $query = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $query);
                            $restoreResults[] = "🔧 Requête CREATE TABLE modifiée pour `$tableName` (compatibilité v2.6)";
                        }
                    }
                    
                    // Exécuter la requête
                    $result = $pdo->exec($query);
                    $successCount++;
                    
                    // Log détaillé pour les requêtes importantes - v2.6 FORCÉ UPLOAD
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "✅ Table `$tableName` créée avec succès (v2.6)";
                    } elseif (stripos($query, 'INSERT INTO') === 0) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        // Compter les lignes insérées
                        $insertCount = substr_count($query, '),(') + 1;
                        $affectedRows = $result !== false ? $result : 'N/A';
                        $restoreResults[] = "📝 $insertCount ligne(s) insérée(s) dans `$tableName` (Lignes affectées: $affectedRows) - v2.6 FORCÉ UPLOAD";
                    } elseif (stripos($query, 'DROP TABLE') === 0) {
                        preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🗑️ Table `$tableName` supprimée (v2.6)";
                    } elseif (stripos($query, 'ALTER TABLE') === 0) {
                        preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🔧 Table `$tableName` modifiée (v2.6)";
                    }
                    
                } catch (PDOException $e) {
                    // Gestion spéciale des erreurs courantes
                    $errorMessage = $e->getMessage();
                    
                    // Si c'est une erreur de table existante, la traiter comme un avertissement
                    if (strpos($errorMessage, 'already exists') !== false) {
                        preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Table `$tableName` existe déjà - ignorée (v2.6)";
                        $successCount++; // Compter comme succès car c'est intentionnel
                        continue; // Ne pas compter comme erreur
                    }
                    
                    // Si c'est une erreur de clé dupliquée, la traiter comme un avertissement
                    if (strpos($errorMessage, 'Duplicate entry') !== false) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Données dupliquées ignorées dans `$tableName` (v2.6)";
                        continue; // Ne pas compter comme erreur
                    }
                    
                    $errorCount++;
                    $queryPreview = substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');
                    $restoreResults[] = "❌ Erreur requête " . ($index + 1) . " (v2.6) : " . $errorMessage;
                    $restoreResults[] = "   Requête : " . htmlspecialchars($queryPreview);
                }
            }
            
            // Réactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $restoreResults[] = "✅ Vérifications de clés étrangères réactivées (v2.6)";
            
            $fileSize = round($uploadedFile['size'] / 1024 / 1024, 2);
            
            // Message de résumé - v2.6 FORCÉ UPLOAD
            if ($errorCount === 0) {
                $message = "✅ Restauration UPLOAD v2.8 ULTRA-FORCÉE réussie depuis : {$uploadedFile['name']} ({$fileSize} MB). $successCount requêtes exécutées avec succès.";
                $messageType = 'success';
            } else {
                $message = "⚠️ Restauration UPLOAD v2.8 ULTRA-FORCÉE partiellement réussie depuis : {$uploadedFile['name']} ({$fileSize} MB). $successCount requêtes OK, $errorCount erreurs.";
                $messageType = 'warning';
            }
            
            // Ajouter les détails
            $message .= "<br><br><strong>📋 Détails de la restauration UPLOAD v2.8 ULTRA-FORCÉE :</strong><br>";
            $message .= implode('<br>', $restoreResults);
            
            $_SESSION['restore_message'] = $message;
            $_SESSION['restore_message_type'] = $messageType;
            
            // Debug: Forcer l'écriture de la session
            session_write_close();
            session_start();
            
        } catch (Exception $e) {
            $_SESSION['restore_message'] = "❌ Erreur critique UPLOAD v2.6 lors de la restauration : " . $e->getMessage();
            $_SESSION['restore_message_type'] = 'error';
        }
        
        header('Location: ../index.php?page=database_backup');
        exit();
    }
    
} catch (Exception $e) {
    // Nettoyer les buffers de sortie en cas d'erreur
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $_SESSION['backup_message'] = "❌ Erreur v2.6 lors de l'opération : " . $e->getMessage();
    $_SESSION['backup_message_type'] = 'error';
    
    // Nettoyer les fichiers temporaires
    if (isset($finalPath) && file_exists($finalPath)) {
        unlink($finalPath);
    }
    if (isset($sqlPath) && file_exists($sqlPath) && $sqlPath !== $finalPath) {
        unlink($sqlPath);
    }
    
    header('Location: ../index.php?page=database_backup');
    exit();
}

// Redirection finale seulement si on n'est pas en mode téléchargement
if (!isset($_POST['create_backup']) || (isset($_POST['backup_destination']) && $_POST['backup_destination'] === 'server')) {
    header('Location: ../index.php?page=database_backup');
    exit();
}
?>