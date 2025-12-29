<?php
/**
 * Script d'installation de la table mail_config avec utilisateur root
 */

try {
    // Connexion avec l'utilisateur root
    $pdo = new PDO("mysql:host=db;dbname=techsuivi_db;charset=utf8mb4", 'root', 'techsuivi_root_2024');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Connexion à la base de données réussie (root)\n";
    
    // SQL de création de la table
    $sql = "
    CREATE TABLE IF NOT EXISTS mail_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        smtp_host VARCHAR(255) NOT NULL,
        smtp_port INT NOT NULL DEFAULT 587,
        smtp_username VARCHAR(255) NOT NULL,
        smtp_password VARCHAR(255) NOT NULL,
        smtp_encryption ENUM('none', 'tls', 'ssl') NOT NULL DEFAULT 'tls',
        from_name VARCHAR(255) NOT NULL,
        from_email VARCHAR(255) NOT NULL,
        reports_enabled BOOLEAN NOT NULL DEFAULT FALSE,
        report_frequency ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
        report_recipients TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    // Exécuter la requête
    $pdo->exec($sql);
    
    echo "✅ Table 'mail_config' créée avec succès !\n";
    
    // Vérifier que la table existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'mail_config'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Vérification : La table 'mail_config' existe bien\n";
        
        // Afficher la structure de la table
        $stmt = $pdo->query("DESCRIBE mail_config");
        echo "\n📋 Structure de la table :\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "❌ Erreur : La table 'mail_config' n'a pas été créée\n";
    }
    
    echo "\n🎉 Installation terminée !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur de base de données : " . $e->getMessage() . "\n";
    exit(1);
}
?>