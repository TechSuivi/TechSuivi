<?php
/**
 * Définition centralisée des mises à jour de structure BDD
 * Ce fichier est utilisé par :
 * 1. install/update_db_structure.php (Mise à jour manuelle)
 * 2. install/init_installer.php (Mise à jour auto au démarrage Docker)
 */

return [
    // Liste des colonnes à vérifier/ajouter
    // Format: 'NomTable' => 'NomColonne'
    'columns' => [
        'FC_cyber' => 'id_client',
        'FC_cyber_credits' => 'id_client'
    ],
    
    // Futures évolutions possibles (ex: nouvelles tables)
    'tables' => [
        'mail_config' => "CREATE TABLE IF NOT EXISTS mail_config (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ]
];
?>
