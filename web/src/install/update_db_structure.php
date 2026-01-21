<?php
// Script de mise à jour de la structure de la base de données
// À exécuter une fois pour appliquer les changements

session_start();
define('TECHSUIVI_INCLUDED', true);

// Détection du chemin de configuration
$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    die("Erreur : Fichier de configuration non trouvé à : $configPath");
}
require_once $configPath;

try {
    $pdo = getDatabaseConnection();
    echo "<h1>Mise à jour de la base de données</h1>";
    
    // Chargement de la configuration centralisée
    $schemaConfig = require __DIR__ . '/../config/db_schema.php';
    $updates = $schemaConfig['columns'] ?? [];
    
    foreach ($updates as $table => $column) {
        // Vérifier si la colonne existe via une requête propre
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "<p style='color:green'>✅ La table <strong>$table</strong> contient déjà la colonne <strong>$column</strong>.</p>";
        } else {
            // Déterminer le type de colonne
            $columnType = 'INT DEFAULT NULL';
            if ($column === 'commentaire') {
                $columnType = 'TEXT';
            } elseif ($column === 'show_on_login') {
                $columnType = 'TINYINT(1) NOT NULL DEFAULT 0';
            }
            
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $columnType");
                echo "<p style='color:blue'>🛠️ Ajout de la colonne <strong>$column</strong> à la table <strong>$table</strong>...</p>";
                
                // Vérification après ajout
                $stmtCheck = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if ($stmtCheck->fetch()) {
                    echo "<p style='color:green'>✅ Succès ! La colonne a été ajoutée.</p>";
                } else {
                    echo "<p style='color:red'>❌ Erreur : La commande a semblé réussir mais la colonne n'est pas visible.</p>";
                }
            } catch (PDOException $e) {
                echo "<p style='color:red'>❌ Erreur SQL lors de l'ajout : " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
        // Correction spécifique si la colonne commentaire est en INT au lieu de TEXT
        if ($table === 'clients' && $column === 'commentaire') {
            $stmtType = $pdo->query("SHOW COLUMNS FROM `clients` LIKE 'commentaire'");
            $colInfo = $stmtType->fetch();
            if ($colInfo && strpos(strtolower($colInfo['Type']), 'int') !== false) {
                echo "<p style='color:orange'>🔶 Correction du type pour <strong>commentaire</strong> (passage de INT à TEXT)...</p>";
                $pdo->exec("ALTER TABLE `clients` MODIFY COLUMN `commentaire` TEXT");
                echo "<p style='color:green'>✅ Type corrigé avec succès.</p>";
            }
        }
    }

    // Gestion des nouvelles tables
    $newTables = $schemaConfig['tables'] ?? [];
    foreach ($newTables as $tableName => $sql) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->fetch()) {
            echo "<p style='color:green'>✅ La table <strong>$tableName</strong> existe déjà.</p>";
        } else {
            try {
                $pdo->exec($sql);
                echo "<p style='color:blue'>🛠️ Création de la table <strong>$tableName</strong>...</p>";
                echo "<p style='color:green'>✅ Table créée avec succès.</p>";
            } catch (PDOException $e) {
                echo "<p style='color:red'>❌ Erreur SQL lors de la création de la table <strong>$tableName</strong> : " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "<hr><p>Mise à jour terminée. Vous pouvez supprimer ce fichier ou le laisser pour les futurs déploiements.</p>";
    echo "<p><a href='../index.php'>Retour à l'accueil</a></p>";

} catch (Exception $e) {
    die("Erreur critique : " . $e->getMessage());
}
?>
