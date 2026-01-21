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
        // 'NouvelleTable' => "CREATE TABLE ... "
    ]
];
?>
