<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$id = $_POST['id'] ?? '';
$fait = $_POST['fait'] ?? '';
$response = ['success' => false, 'message' => ''];

if (empty($id) || !is_numeric($id)) {
    $response['message'] = 'ID de message invalide.';
} elseif (!isset($_POST['fait']) || !in_array($fait, ['0', '1'])) {
    $response['message'] = 'Statut invalide.';
} else {
    try {
        $dateField = $fait == 1 ? 'NOW()' : 'NULL';
        
        $sql = "UPDATE helpdesk_msg SET FAIT = :fait, DATE_FAIT = $dateField WHERE ID = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fait', $fait, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = $fait == 1 ? 'Message marqué comme fait.' : 'Message marqué comme non fait.';
        } else {
            $response['message'] = 'Erreur lors de la mise à jour du statut.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
