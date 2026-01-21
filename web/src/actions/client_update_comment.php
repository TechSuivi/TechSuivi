<?php
/**
 * Action AJAX pour modifier uniquement le commentaire d'un client
 */

session_start();

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé.']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion BDD']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit();
}

$clientId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$commentaire = trim($_POST['commentaire'] ?? '');

if ($clientId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID client invalide.']);
    exit();
}

try {
    $sql = "UPDATE clients SET commentaire = :commentaire WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Commentaire mis à jour !']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur SQL : ' . $e->getMessage()]);
}
