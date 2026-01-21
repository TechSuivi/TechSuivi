<?php
// Empêcher l'accès direct si TECHSUIVI_INCLUDED n'est pas défini par index.php
// Cependant, ce script est appelé via fetch() et n'inclut pas index.php directement.
// Une vérification de session ou un autre token pourrait être plus approprié ici pour la sécurité.
// Pour l'instant, on se concentre sur la fonctionnalité.
// session_start(); // Décommentez si vous voulez vérifier une session existante
// if (!isset($_SESSION['username'])) {
//     header('Content-Type: application/json');
//     echo json_encode(['error' => 'Non autorisé']);
//     exit;
// }

header('Content-Type: application/json');

$cp = trim($_GET['cp'] ?? '');

if (empty($cp) || !preg_match('/^\d{5}$/', $cp)) {
    echo json_encode(['error' => 'Code postal invalide.']);
    exit;
}

$apiUrl = "https://geo.api.gouv.fr/communes?codePostal=" . urlencode($cp) . "&fields=nom,code&format=json";

// Utiliser file_get_contents avec gestion des erreurs basique
// Pour une application en production, cURL serait plus robuste (gestion des timeouts, headers, etc.)
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: TechSuivi/1.0\r\n" // Il est bon de spécifier un User-Agent
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($apiUrl, false, $context); // @ pour supprimer les warnings en cas d'échec

if ($response === FALSE) {
    echo json_encode(['error' => 'Impossible de contacter l\'API Géo.']);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Réponse invalide de l\'API Géo.']);
    exit;
}

if (empty($data)) {
    echo json_encode([]); // Retourne un tableau vide si aucune ville n'est trouvée
    exit;
}

// On ne renvoie que les informations nécessaires (nom, code INSEE)
// L'API peut renvoyer plusieurs codes postaux pour une même commune, ou plusieurs communes pour un CP.
// Ici, on renvoie toutes les communes trouvées pour le CP.
echo json_encode($data);
?>