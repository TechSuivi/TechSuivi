<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    define('TECHSUIVI_INCLUDED', true);
}

header('Content-Type: application/json');

// Configuration de la base de données
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset = 'utf8mb4';

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset={$charset}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer la date la plus récente dans updated_at de la table catalog
    $sql = "SELECT MAX(updated_at) as last_sync FROM catalog";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastSync = $result['last_sync'];
    
    if ($lastSync) {
        // Formater la date en français
        $date = new DateTime($lastSync);
        $formattedDate = $date->format('d/m/Y à H:i:s');
        
        echo json_encode([
            'success' => true,
            'last_sync' => $lastSync,
            'formatted_date' => $formattedDate,
            'relative_time' => getRelativeTime($lastSync)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune synchronisation trouvée'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getRelativeTime($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Il y a quelques secondes';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Il y a {$minutes} minute" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Il y a {$hours} heure" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Il y a {$days} jour" . ($days > 1 ? 's' : '');
    } else {
        $months = floor($time / 2592000);
        return "Il y a {$months} mois";
    }
}
?>