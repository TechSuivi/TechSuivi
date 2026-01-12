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
    
    $updates = [
        'FC_cyber' => 'id_client',
        'FC_cyber_credits' => 'id_client'
    ];
    
    foreach ($updates as $table => $column) {
        // Vérifier si la colonne existe via une requête propre
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "<p style='color:green'>✅ La table <strong>$table</strong> contient déjà la colonne <strong>$column</strong>.</p>";
        } else {
            // Tentative d'ajout
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` INT DEFAULT NULL");
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
    }
    
    echo "<hr><p>Mise à jour terminée. Vous pouvez supprimer ce fichier ou le laisser pour les futurs déploiements.</p>";
    echo "<p><a href='../index.php'>Retour à l'accueil</a></p>";

} catch (Exception $e) {
    die("Erreur critique : " . $e->getMessage());
}
?>
