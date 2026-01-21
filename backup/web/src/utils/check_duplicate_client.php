<?php
// Ce script est appelé via AJAX pour vérifier les doublons potentiels.
// Il a besoin d'accéder à la base de données.

// Sécurité de base : Assurer que ce script est appelé dans le bon contexte.
// Pourrait être amélioré avec une vérification de session/token.
define('TECHSUIVI_INCLUDED_AJAX', true); // Une constante pour ce type de script

// Inclure la configuration de la base de données ou la connexion PDO
// Supposons que index.php gère la session, mais ici nous avons besoin de $pdo.
// Pour simplifier, nous allons recréer une connexion PDO ici.
// Idéalement, vous auriez un fichier de configuration DB séparé.

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de connexion DB: ' . $e->getMessage(), 'duplicates' => []]);
    exit;
}


// Récupérer les données POST
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$nomClient = trim($input['nom'] ?? $_GET['nom'] ?? '');
$prenomClient = trim($input['prenom'] ?? $_GET['prenom'] ?? '');
$telephone = trim($input['telephone'] ?? '');
$portable = trim($input['portable'] ?? '');

// Nettoyage des numéros pour la recherche (enlever espaces, points, tirets)
$telSearch = preg_replace('/[^0-9]/', '', $telephone);
$portSearch = preg_replace('/[^0-9]/', '', $portable);

$response = ['duplicates' => []];

if (empty($nomClient) && empty($telSearch) && empty($portSearch)) {
    echo json_encode($response);
    exit;
}

try {
    $params = [];
    $conditions = [];
    
    // Recherche par Nom (et prénom si présent)
    if (!empty($nomClient)) {
        $cond = "LOWER(nom) LIKE :nom";
        $params[':nom'] = '%' . strtolower($nomClient) . '%';
        
        if (!empty($prenomClient)) {
            $cond .= " AND LOWER(prenom) LIKE :prenom";
            $params[':prenom'] = '%' . strtolower($prenomClient) . '%';
        }
        $conditions[] = "(" . $cond . ")";
    }
    
    // Recherche par Téléphone
    if (!empty($telSearch) && strlen($telSearch) > 6) {
        $conditions[] = "(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '.', ''), '-', ''), '/', '') LIKE :tel OR REPLACE(REPLACE(REPLACE(REPLACE(portable, ' ', ''), '.', ''), '-', ''), '/', '') LIKE :tel)";
        $params[':tel'] = '%' . $telSearch . '%';
    }
    
    // Recherche par Portable
    if (!empty($portSearch) && strlen($portSearch) > 6) {
        $conditions[] = "(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '.', ''), '-', ''), '/', '') LIKE :port OR REPLACE(REPLACE(REPLACE(REPLACE(portable, ' ', ''), '.', ''), '-', ''), '/', '') LIKE :port)";
        $params[':port'] = '%' . $portSearch . '%';
    }
    
    if (empty($conditions)) {
        echo json_encode($response);
        exit;
    }
    
    $sql = "SELECT ID, nom, prenom, ville, cp, telephone, portable FROM clients WHERE " . implode(' OR ', $conditions) . " LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $duplicates = $stmt->fetchAll();
    
    $response['duplicates'] = $duplicates;

} catch (PDOException $e) {
    $response['error'] = 'Erreur lors de la recherche de doublons: ' . $e->getMessage();
}

echo json_encode($response);
?>