<?php
/**
 * API pour récupérer les informations d'un client
 * Retourne un JSON avec les données du client
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé - Connexion requise.']);
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Récupération de l'ID du client
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($clientId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID client invalide.']);
    exit();
}

try {
    $sql = "SELECT ID, nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail FROM clients WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Client non trouvé.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'client' => $client
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
