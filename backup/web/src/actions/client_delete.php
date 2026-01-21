<?php
/**
 * Action AJAX pour supprimer un client
 * Retourne un JSON avec le succès ou les erreurs
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

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit();
}

// Récupération de l'ID du client
$clientId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($clientId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID client invalide.']);
    exit();
}

try {
    // Vérifier que le client existe
    $checkSql = "SELECT ID, nom, prenom FROM clients WHERE ID = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $clientId]);
    $client = $checkStmt->fetch();
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Client non trouvé.']);
        exit();
    }

    // Vérifier s'il y a des interventions liées à ce client
    $interSql = "SELECT COUNT(*) as count FROM inter WHERE id_client = :id";
    $interStmt = $pdo->prepare($interSql);
    $interStmt->execute([':id' => $clientId]);
    $interCount = $interStmt->fetch()['count'];
    
    if ($interCount > 0) {
        echo json_encode([
            'success' => false, 
            'error' => "Impossible de supprimer ce client car il a $interCount intervention(s) associée(s). Supprimez d'abord les interventions."
        ]);
        exit();
    }

    // Supprimer le client
    $deleteSql = "DELETE FROM clients WHERE ID = :id";
    $deleteStmt = $pdo->prepare($deleteSql);
    
    if ($deleteStmt->execute([':id' => $clientId])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Client "' . $client['nom'] . ' ' . $client['prenom'] . '" supprimé avec succès.'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression du client.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
