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

$messageId = $_POST['message_id'] ?? '';
$message = trim($_POST['message'] ?? '');
$response = ['success' => false, 'message' => ''];

if (empty($messageId) || !is_numeric($messageId)) {
    $response['message'] = 'ID de message invalide.';
} elseif (empty($message)) {
    $response['message'] = 'Le message est obligatoire.';
} else {
    try {
        // Vérifier si le message parent existe
        $stmt = $pdo->prepare("SELECT ID FROM helpdesk_msg WHERE ID = :id");
        $stmt->bindParam(':id', $messageId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            $sql = "INSERT INTO helpdesk_reponses (MESSAGE_ID, MESSAGE, DATE_REPONSE) VALUES (:message_id, :message, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
            $stmt->bindParam(':message', $message);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Réponse ajoutée avec succès !';
            } else {
                $response['message'] = 'Erreur lors de l\'ajout de la réponse.';
            }
        } else {
            $response['message'] = 'Message parent introuvable.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
