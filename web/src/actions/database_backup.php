<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

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
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Sauvegarder le debug dans la session
    $_SESSION['backup_debug'] = $debugInfo;
    
    // Vérifier si c'est une demande de sauvegarde
    if (isset($_POST['create_backup'])) {
        $_SESSION['backup_message'] = "DEBUG: Demande de sauvegarde reçue à " . date('H:i:s');
        $_SESSION['backup_message_type'] = 'info';
        
        // Récupérer les options de sauvegarde
        $backupType = $_POST['backup_type'] ?? 'full';
        $backupFormat = $_POST['backup_format'] ?? 'sql';
        $backupDestination = $_POST['backup_destination'] ?? 'download';
        $selectedTables = $_POST['selected_tables'] ?? [];
        
        // Validation pour sauvegarde partielle
        if ($backupType === 'partial' && empty($selectedTables)) {
            $_SESSION['backup_message'] = "Erreur : Aucune table sélectionnée pour la sauvegarde partielle.";
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
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            $backupPath = $backupDir . $backupFileName;
        } else {
            $backupPath = "/tmp/{$backupFileName}";
        }
        
        // Extension selon le format
        $sqlPath = $backupPath . '.sql';
        $finalPath = $backupFormat === 'zip' ? $backupPath . '.zip' : $sqlPath;
        $finalFileName = basename($finalPath);
        
        // Vérifier les permissions du dossier
        $targetDir = dirname($backupPath);
        if (!is_writable($targetDir)) {
            // Essayer de corriger les permissions
            @chmod($targetDir, 0755);
            if (!is_writable($targetDir)) {
                $_SESSION['backup_message'] = "❌ Erreur de permissions : Le dossier {$targetDir} n'est pas accessible en écriture. Exécutez : chmod 755 {$targetDir}";
                $_SESSION['backup_message_type'] = 'error';
                header('Location: ../index.php?page=database_backup');
                exit();
            }
        }
        
        // Augmenter les limites pour les gros fichiers
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '1G');
        
        // Créer la connexion PDO
        $pdo = getDatabaseConnection();
        
        // Générer la sauvegarde SQL
        $backup = "-- TechSuivi Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Backup type: " . ($backupType === 'partial' ? 'Partial (' . count($selectedTables) . ' tables)' : 'Full') . "\n";
        $backup .= "-- Format: " . strtoupper($backupFormat) . "\n\n";
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
                $backup .= "-- Structure for table `{$table}`\n";
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
                    $backup .= "-- Data for table `{$table}` ({$rowCount} rows)\n";
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
                $backup .= "-- ERROR with table `{$table}`: " . $e->getMessage() . "\n\n";
            }
        }
        
        $backup .= "COMMIT;\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup .= "\n-- Backup completed: {$tableCount} tables, {$totalRows} total rows\n";
        
        // Écrire le fichier SQL
        file_put_contents($sqlPath, $backup);
        
        // Traitement selon le format
        if ($backupFormat === 'zip') {
            // Créer un fichier ZIP
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($finalPath, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($sqlPath, basename($sqlPath));
                    $zip->close();
                    
                    // Supprimer le fichier SQL temporaire
                    unlink($sqlPath);
                } else {
                    throw new Exception("Impossible de créer le fichier ZIP");
                }
            } else {
                // Fallback vers SQL si ZIP non disponible
                $_SESSION['backup_message'] = "⚠️ Extension ZIP non disponible, sauvegarde créée au format SQL";
                $_SESSION['backup_message_type'] = 'warning';
                $finalPath = $sqlPath;
                $finalFileName = basename($sqlPath);
            }
        } else {
            $finalPath = $sqlPath;
        }
        
        // Vérifier que le fichier final existe et n'est pas vide
        if (!file_exists($finalPath) || filesize($finalPath) === 0) {
            throw new Exception("Le fichier de sauvegarde est vide ou n'a pas pu être créé");
        }
        
        $fileSize = round(filesize($finalPath) / 1024 / 1024, 2);
        
        // Traitement selon la destination
        if ($backupDestination === 'server') {
            $_SESSION['backup_message'] = "Sauvegarde créée avec succès sur le serveur : {$finalFileName} ({$fileSize} MB). {$tableCount} tables sauvegardées.";
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
                $_SESSION['backup_message'] = "Erreur : Fichier de sauvegarde non accessible pour téléchargement.";
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
        // Restauration depuis fichier serveur avec logging détaillé
        $serverFile = $_POST['server_backup_file'] ?? '';
        $dropTables = isset($_POST['drop_tables']) && $_POST['drop_tables'] === '1';
        
        if (empty($serverFile)) {
            $_SESSION['restore_message'] = "❌ Erreur : Aucun fichier sélectionné.";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        $serverPath = __DIR__ . '/../uploads/backups/' . basename($serverFile);
        
        if (!file_exists($serverPath)) {
            $_SESSION['restore_message'] = "❌ Erreur : Fichier non trouvé sur le serveur : {$serverFile}";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // Traitement de la restauration avec logging détaillé
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1G');
            
            $pdo = getDatabaseConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $restoreResults = [];
            $successCount = 0;
            $errorCount = 0;
            
            // Désactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $restoreResults[] = "✅ Vérifications de clés étrangères désactivées";
            
            // Vider les tables si demandé
            if ($dropTables) {
                try {
                    // Obtenir la liste des tables
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $restoreResults[] = "🗑️ Table `$table` supprimée";
                    }
                    $restoreResults[] = "✅ Toutes les tables ont été vidées";
                } catch (PDOException $e) {
                    $restoreResults[] = "⚠️ Erreur lors du vidage des tables : " . $e->getMessage();
                    $errorCount++;
                }
            }
            
            // Traitement du fichier (ZIP ou SQL)
            $sqlContent = '';
            if (pathinfo($serverPath, PATHINFO_EXTENSION) === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($serverPath) === TRUE) {
                        $restoreResults[] = "📦 Fichier ZIP ouvert avec succès";
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                                $sqlContent = $zip->getFromIndex($i);
                                $restoreResults[] = "📄 Fichier SQL extrait : $filename";
                                break;
                            }
                        }
                        $zip->close();
                    } else {
                        throw new Exception("Impossible d'ouvrir le fichier ZIP");
                    }
                } else {
                    throw new Exception("Extension ZIP non disponible");
                }
            } else {
                $sqlContent = file_get_contents($serverPath);
                $restoreResults[] = "📄 Fichier SQL lu directement";
            }
            
            if (empty($sqlContent)) {
                throw new Exception("Impossible de lire le contenu SQL du fichier");
            }
            
            // Diviser le contenu en requêtes individuelles
            $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
            
            $restoreResults[] = "📋 " . count($queries) . " requêtes à exécuter";
            
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
                $restoreResults[] = "🧹 Vidage des données des tables existantes...";
                foreach ($tablesToClear as $tableName) {
                    try {
                        $pdo->exec("DELETE FROM `$tableName`");
                        $restoreResults[] = "🗑️ Données de la table `$tableName` vidées";
                    } catch (PDOException $e) {
                        $restoreResults[] = "⚠️ Impossible de vider `$tableName` : " . $e->getMessage();
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
                
                try {
                    // Gestion spéciale pour les CREATE TABLE
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $restoreResults[] = "⚠️ Table `$tableName` existe déjà - structure ignorée";
                            $successCount++; // Compter comme succès car c'est intentionnel
                            continue; // Passer à la requête suivante
                        }
                        
                        // Modifier dynamiquement les anciennes sauvegardes pour ajouter IF NOT EXISTS
                        if (stripos($query, 'IF NOT EXISTS') === false) {
                            $query = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $query);
                            $restoreResults[] = "🔧 Requête CREATE TABLE modifiée pour `$tableName` (compatibilité)";
                        }
                    }
                    
                    // Exécuter la requête
                    $pdo->exec($query);
                    $successCount++;
                    
                    // Log détaillé pour les requêtes importantes
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "✅ Table `$tableName` créée";
                    } elseif (stripos($query, 'INSERT INTO') === 0) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        // Compter les lignes insérées
                        $insertCount = substr_count($query, '),(') + 1;
                        $restoreResults[] = "📝 $insertCount ligne(s) insérée(s) dans `$tableName`";
                    } elseif (stripos($query, 'DROP TABLE') === 0) {
                        preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🗑️ Table `$tableName` supprimée";
                    } elseif (stripos($query, 'ALTER TABLE') === 0) {
                        preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🔧 Table `$tableName` modifiée";
                    }
                    
                } catch (PDOException $e) {
                    // Gestion spéciale des erreurs courantes
                    $errorMessage = $e->getMessage();
                    
                    // Si c'est une erreur de table existante, la traiter comme un avertissement
                    if (strpos($errorMessage, 'already exists') !== false) {
                        preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Table `$tableName` existe déjà - ignorée";
                        $successCount++; // Compter comme succès car c'est intentionnel
                        continue; // Ne pas compter comme erreur
                    }
                    
                    // Si c'est une erreur de clé dupliquée, la traiter comme un avertissement
                    if (strpos($errorMessage, 'Duplicate entry') !== false) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Données dupliquées ignorées dans `$tableName`";
                        continue; // Ne pas compter comme erreur
                    }
                    
                    $errorCount++;
                    $queryPreview = substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');
                    $restoreResults[] = "❌ Erreur requête " . ($index + 1) . " : " . $errorMessage;
                    $restoreResults[] = "   Requête : " . htmlspecialchars($queryPreview);
                }
            }
            
            // Réactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $restoreResults[] = "✅ Vérifications de clés étrangères réactivées";
            
            $fileSize = round(filesize($serverPath) / 1024 / 1024, 2);
            
            // Message de résumé
            if ($errorCount === 0) {
                $message = "✅ Restauration réussie depuis : {$serverFile} ({$fileSize} MB). $successCount requêtes exécutées avec succès.";
                $messageType = 'success';
            } else {
                $message = "⚠️ Restauration partiellement réussie depuis : {$serverFile} ({$fileSize} MB). $successCount requêtes OK, $errorCount erreurs.";
                $messageType = 'warning';
            }
            
            // Ajouter les détails
            $message .= "<br><br><strong>📋 Détails de la restauration :</strong><br>";
            $message .= implode('<br>', $restoreResults);
            
            $_SESSION['restore_message'] = $message;
            $_SESSION['restore_message_type'] = $messageType;
            
        } catch (Exception $e) {
            $_SESSION['restore_message'] = "❌ Erreur critique lors de la restauration : " . $e->getMessage();
            $_SESSION['restore_message_type'] = 'error';
        }
        
        header('Location: ../index.php?page=database_backup');
        exit();
        
    } elseif (isset($_POST['restore_upload']) && isset($_FILES['backup_file'])) {
        // Restauration depuis fichier uploadé
        $uploadedFile = $_FILES['backup_file'];
        $dropTables = isset($_POST['drop_tables']) && $_POST['drop_tables'] === '1';
        
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['restore_message'] = "❌ Erreur lors de l'upload : " . $uploadedFile['error'];
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // Vérifier le type de fichier
        $allowedExtensions = ['sql', 'zip'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $_SESSION['restore_message'] = "❌ Erreur : Type de fichier non supporté. Utilisez .sql ou .zip";
            $_SESSION['restore_message_type'] = 'error';
            header('Location: ../index.php?page=database_backup');
            exit();
        }
        
        // Traitement de la restauration avec logging détaillé
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1G');
            
            $pdo = getDatabaseConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $restoreResults = [];
            $successCount = 0;
            $errorCount = 0;
            
            // Désactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $restoreResults[] = "✅ Vérifications de clés étrangères désactivées";
            
            // Vider les tables si demandé
            if ($dropTables) {
                try {
                    // Obtenir la liste des tables
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $restoreResults[] = "🗑️ Table `$table` supprimée";
                    }
                    $restoreResults[] = "✅ Toutes les tables ont été vidées";
                } catch (PDOException $e) {
                    $restoreResults[] = "⚠️ Erreur lors du vidage des tables : " . $e->getMessage();
                    $errorCount++;
                }
            }
            
            // Traitement du fichier (ZIP ou SQL)
            $sqlContent = '';
            if ($fileExtension === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($uploadedFile['tmp_name']) === TRUE) {
                        $restoreResults[] = "📦 Fichier ZIP ouvert avec succès";
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                                $sqlContent = $zip->getFromIndex($i);
                                $restoreResults[] = "📄 Fichier SQL extrait : $filename";
                                break;
                            }
                        }
                        $zip->close();
                    } else {
                        throw new Exception("Impossible d'ouvrir le fichier ZIP");
                    }
                } else {
                    throw new Exception("Extension ZIP non disponible");
                }
            } else {
                $sqlContent = file_get_contents($uploadedFile['tmp_name']);
                $restoreResults[] = "📄 Fichier SQL lu directement";
            }
            
            if (empty($sqlContent)) {
                throw new Exception("Impossible de lire le contenu SQL du fichier");
            }
            
            // Diviser le contenu en requêtes individuelles
            $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
            $validQueries = 0;
            
            // Première passe : compter les requêtes valides
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
            
            $restoreResults[] = "📋 " . count($queries) . " requêtes brutes analysées";
            $restoreResults[] = "📊 $validQueries requêtes valides détectées";
            
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
                $restoreResults[] = "🧹 Vidage des données des tables existantes...";
                foreach ($tablesToClear as $tableName) {
                    try {
                        $pdo->exec("DELETE FROM `$tableName`");
                        $restoreResults[] = "🗑️ Données de la table `$tableName` vidées";
                    } catch (PDOException $e) {
                        $restoreResults[] = "⚠️ Impossible de vider `$tableName` : " . $e->getMessage();
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
                
                // Debug : afficher le type de requête
                $queryType = 'UNKNOWN';
                if (stripos($query, 'CREATE TABLE') === 0) $queryType = 'CREATE TABLE';
                elseif (stripos($query, 'INSERT INTO') === 0) $queryType = 'INSERT INTO';
                elseif (stripos($query, 'DROP TABLE') === 0) $queryType = 'DROP TABLE';
                elseif (stripos($query, 'ALTER TABLE') === 0) $queryType = 'ALTER TABLE';
                
                try {
                    // Gestion spéciale pour les CREATE TABLE
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        
                        // Vérifier si la table existe déjà
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
                        if ($checkTable) {
                            $restoreResults[] = "⚠️ Table `$tableName` existe déjà - structure ignorée";
                            $successCount++; // Compter comme succès car c'est intentionnel
                            continue; // Passer à la requête suivante
                        }
                        
                        // Modifier dynamiquement les anciennes sauvegardes pour ajouter IF NOT EXISTS
                        if (stripos($query, 'IF NOT EXISTS') === false) {
                            $query = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $query);
                            $restoreResults[] = "🔧 Requête CREATE TABLE modifiée pour `$tableName` (compatibilité)";
                        }
                    }
                    
                    // Debug avant exécution
                    $restoreResults[] = "🔍 Exécution requête $queryType (#" . ($index + 1) . ")";
                    
                    // Exécuter la requête
                    $result = $pdo->exec($query);
                    $successCount++;
                    
                    // Log détaillé pour les requêtes importantes
                    if (stripos($query, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "✅ Table `$tableName` créée avec succès";
                    } elseif (stripos($query, 'INSERT INTO') === 0) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        // Compter les lignes insérées
                        $insertCount = substr_count($query, '),(') + 1;
                        $affectedRows = $result !== false ? $result : 'N/A';
                        $restoreResults[] = "📝 $insertCount ligne(s) insérée(s) dans `$tableName` (Lignes affectées: $affectedRows)";
                    } elseif (stripos($query, 'DROP TABLE') === 0) {
                        preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🗑️ Table `$tableName` supprimée";
                    } elseif (stripos($query, 'ALTER TABLE') === 0) {
                        preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "🔧 Table `$tableName` modifiée";
                    }
                    
                } catch (PDOException $e) {
                    // Gestion spéciale des erreurs courantes
                    $errorMessage = $e->getMessage();
                    
                    // Si c'est une erreur de table existante, la traiter comme un avertissement
                    if (strpos($errorMessage, 'already exists') !== false) {
                        preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Table `$tableName` existe déjà - ignorée";
                        $successCount++; // Compter comme succès car c'est intentionnel
                        continue; // Ne pas compter comme erreur
                    }
                    
                    // Si c'est une erreur de clé dupliquée, la traiter comme un avertissement
                    if (strpos($errorMessage, 'Duplicate entry') !== false) {
                        preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $matches);
                        $tableName = $matches[1] ?? 'inconnue';
                        $restoreResults[] = "⚠️ Données dupliquées ignorées dans `$tableName`";
                        continue; // Ne pas compter comme erreur
                    }
                    
                    $errorCount++;
                    $queryPreview = substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');
                    $restoreResults[] = "❌ Erreur requête " . ($index + 1) . " : " . $errorMessage;
                    $restoreResults[] = "   Requête : " . htmlspecialchars($queryPreview);
                }
            }
            
            // Réactiver les vérifications de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $restoreResults[] = "✅ Vérifications de clés étrangères réactivées";
            
            $fileSize = round($uploadedFile['size'] / 1024 / 1024, 2);
            
            // Message de résumé
            if ($errorCount === 0) {
                $message = "✅ Restauration réussie depuis : {$uploadedFile['name']} ({$fileSize} MB). $successCount requêtes exécutées avec succès.";
                $messageType = 'success';
            } else {
                $message = "⚠️ Restauration partiellement réussie depuis : {$uploadedFile['name']} ({$fileSize} MB). $successCount requêtes OK, $errorCount erreurs.";
                $messageType = 'warning';
            }
            
            // Ajouter les détails
            $message .= "<br><br><strong>📋 Détails de la restauration :</strong><br>";
            $message .= implode('<br>', $restoreResults);
            
            $_SESSION['restore_message'] = $message;
            $_SESSION['restore_message_type'] = $messageType;
            
        } catch (Exception $e) {
            $_SESSION['restore_message'] = "❌ Erreur critique lors de la restauration : " . $e->getMessage();
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
    
    $_SESSION['backup_message'] = "Erreur lors de l'opération : " . $e->getMessage();
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