<?php
/**
 * Action AJAX pour supprimer un téléchargement
 * Retourne un JSON
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
    echo json_encode(['success' => false, 'error' => 'Erreur DB']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID invalide.']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM download WHERE ID = :id");
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        if ($stmt->rowCount() > 0) {
            $_SESSION['delete_message'] = "Téléchargement supprimé avec succès !";
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Cet élément n\'existe plus.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur DB: ' . $e->getMessage()]);
}
