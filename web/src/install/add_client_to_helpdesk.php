<?php
// Script de migration : Ajout de la colonne id_client à la table helpdesk_msg

define('TECHSUIVI_INCLUDED', true);
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "Connexion à la base de données réussie.\n";

    // Vérifier si la colonne existe déjà
    $checkSql = "SHOW COLUMNS FROM helpdesk_msg LIKE 'id_client'";
    $stmt = $pdo->query($checkSql);
    
    if ($stmt->rowCount() == 0) {
        // La colonne n'existe pas, on l'ajoute
        echo "Ajout de la colonne id_client...\n";
        $sql = "ALTER TABLE helpdesk_msg ADD COLUMN id_client INT NULL DEFAULT NULL AFTER MESSAGE";
        $pdo->exec($sql);
        echo "Colonne id_client ajoutée avec succès.\n";
    } else {
        echo "La colonne id_client existe déjà.\n";
    }

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>
