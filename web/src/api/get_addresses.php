<?php
// Sécurité de base : vérifier si le paramètre q est présent
// Une vérification de session/token serait mieux en production.
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode(['error' => 'Requête vide.', 'features' => []]);
    exit;
}

// Limiter le nombre de résultats pour ne pas surcharger et pour la pertinence
$limit = 7; 
$apiUrl = "https://api-adresse.data.gouv.fr/search/?q=" . urlencode($query) . "&limit=" . $limit;

// Utiliser file_get_contents avec gestion des erreurs basique
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: TechSuivi/1.0\r\n" // User-Agent
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($apiUrl, false, $context);

if ($response === FALSE) {
    $error = error_get_last();
    $errorMsg = $error['message'] ?? 'Erreur inconnue';
    // Nettoyer le message d'erreur pour ne pas exposer trop de détails techniques si nécessaire, 
    // ou tout afficher pour le debug :
    echo json_encode(['error' => 'Impossible de contacter l\'API Adresse: ' . $errorMsg, 'features' => []]);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Réponse invalide de l\'API Adresse.', 'features' => []]);
    exit;
}

// L'API Adresse retourne un objet GeoJSON. Les adresses sont dans $data['features']
// Chaque feature a un objet 'properties' avec 'label', 'name', 'postcode', 'city', etc.
// On s'assure de renvoyer un tableau de features même si vide, pour Awesomplete.
if (!isset($data['features'])) {
    $data['features'] = [];
}

echo json_encode($data); // Renvoie l'objet GeoJSON complet (ou du moins la partie 'features')
?>