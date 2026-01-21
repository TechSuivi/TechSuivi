<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$activeTab = $_GET['tab'] ?? 'gestion';

// Si l'onglet est 'server', afficher directement les informations système (avec Docker)
if ($activeTab === 'server') {
    include 'server_info.php';
    return;
}

// Inclure le composant de navigation persistant
include 'components/settings_navigation.php';
?>
