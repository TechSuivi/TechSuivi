<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Database connection details
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    // Gérer l'erreur de connexion
    throw $e;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $categorie = $_POST['categorie'] ?? '';
    $titre = trim($_POST['titre'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $response = ['success' => false, 'message' => ''];

    if (empty($categorie) || !is_numeric($categorie)) {
        $response['message'] = 'Catégorie invalide.';
    } elseif (empty($titre)) {
        $response['message'] = 'Le titre est obligatoire.';
    } elseif (empty($message)) {
        $response['message'] = 'Le message est obligatoire.';
    } else {
        try {
            $sql = "INSERT INTO helpdesk_msg (CATEGORIE, TITRE, MESSAGE, DATE, FAIT) VALUES (:categorie, :titre, :message, NOW(), 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':categorie', $categorie, PDO::PARAM_INT);
            $stmt->bindParam(':titre', $titre);
            $stmt->bindParam(':message', $message);

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
    
} elseif ($action === 'toggle_status') {
    $id = $_POST['id'] ?? '';
    $response = ['success' => false, 'message' => ''];

    if (empty($id) || !is_numeric($id)) {
        $response['message'] = 'ID de message invalide.';
    } else {
        try {
            // Récupérer le statut actuel
            $stmt = $pdo->prepare("SELECT FAIT FROM helpdesk_msg WHERE ID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $currentStatus = $stmt->fetchColumn();
            
            if ($currentStatus !== false) {
                $newStatus = $currentStatus ? 0 : 1;
                $dateField = $newStatus ? 'NOW()' : 'NULL';
                
                $sql = "UPDATE helpdesk_msg SET FAIT = :status, DATE_FAIT = $dateField WHERE ID = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':status', $newStatus, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = $newStatus ? 'Message marqué comme fait.' : 'Message marqué comme non fait.';
                    $response['new_status'] = $newStatus;
                } else {
                    $response['message'] = 'Erreur lors de la mise à jour du statut.';
                }
            } else {
                $response['message'] = 'Message non trouvé.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
        }
    }
    
    echo json_encode($response);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}
?>