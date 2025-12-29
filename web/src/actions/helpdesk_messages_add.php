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

$categorie = $_POST['categorie'] ?? '';
$titre = trim($_POST['titre'] ?? '');
$message = trim($_POST['message'] ?? '');
$id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;

$response = ['success' => false, 'message' => ''];

if (empty($categorie) || !is_numeric($categorie)) {
    $response['message'] = 'Catégorie invalide.';
} elseif (empty($titre)) {
    $response['message'] = 'Le titre est obligatoire.';
} elseif (empty($message)) {
    $response['message'] = 'Le message est obligatoire.';
} else {
    try {
        $sql = "INSERT INTO helpdesk_msg (CATEGORIE, TITRE, MESSAGE, DATE, FAIT, id_client) VALUES (:categorie, :titre, :message, NOW(), 0, :id_client)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':categorie', $categorie, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $titre);
        $stmt->bindParam(':message', $message);
        $stmt->bindValue(':id_client', $id_client, $id_client ? PDO::PARAM_INT : PDO::PARAM_NULL);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Message ajouté avec succès !';
        } else {
            $response['message'] = 'Erreur lors de l\'ajout du message.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
