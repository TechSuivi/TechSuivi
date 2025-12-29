<?php
// Configuration de la session et vérification de connexion
session_start();

// Vérifier si l'utilisateur est connecté (même vérification que dans index.php)
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

$query = $_GET['q'] ?? $_GET['term'] ?? '';
$limit = min((int)($_GET['limit'] ?? 20), 50); // Limiter à 50 résultats max

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT ID, nom, prenom, adresse1, cp, ville, telephone, portable, mail
            FROM clients
            WHERE (nom LIKE :query1 OR prenom LIKE :query2 OR telephone LIKE :query3 OR portable LIKE :query4)
            ORDER BY nom ASC, prenom ASC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $searchQuery = '%' . $query . '%';
    $stmt->bindParam(':query1', $searchQuery);
    $stmt->bindParam(':query2', $searchQuery);
    $stmt->bindParam(':query3', $searchQuery);
    $stmt->bindParam(':query4', $searchQuery);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $clients = $stmt->fetchAll();
    
    // Formater les résultats pour l'autocomplétion
    $results = [];
    foreach ($clients as $client) {
        $label = trim($client['nom'] . ' ' . $client['prenom']);
        
        // Ajout Adresse
        $adresse = [];
        if (!empty($client['adresse1'])) $adresse[] = $client['adresse1'];
        if (!empty($client['cp']) || !empty($client['ville'])) {
            $adresse[] = trim(($client['cp'] ?? '') . ' ' . ($client['ville'] ?? ''));
        }
        if (!empty($adresse)) {
            $label .= ' - ' . implode(', ', $adresse);
        }
        
        // Ajout Téléphone
        $tels = [];
        if (!empty($client['telephone'])) $tels[] = $client['telephone'];
        if (!empty($client['portable'])) $tels[] = $client['portable'];
        
        if (!empty($tels)) {
            $label .= ' - ' . implode(' / ', $tels);
        }
        
        $results[] = [
            'id' => $client['ID'],
            'label' => $label,
            'value' => trim($client['nom'] . ' ' . $client['prenom']),
            'email' => $client['mail'],
            'adresse1' => $client['adresse1'],
            'cp' => $client['cp'],
            'ville' => $client['ville'],
            'telephone' => $client['telephone'],
            'portable' => $client['portable']
        ];
    }
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recherche: ' . $e->getMessage()]);
}
?>