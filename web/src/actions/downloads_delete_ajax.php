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
$deletePhysicalFile = isset($_POST['delete_file']) && $_POST['delete_file'] === '1';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID invalide.']);
    exit();
}

try {
    // 1. Récupérer les infos du fichier avant la suppression en base
    $stmtSelect = $pdo->prepare("SELECT URL FROM download WHERE ID = :id");
    $stmtSelect->execute([':id' => $id]);
    $download = $stmtSelect->fetch(PDO::FETCH_ASSOC);
    
    // 2. Supprimer de la base de données
    $stmt = $pdo->prepare("DELETE FROM download WHERE ID = :id");
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        if ($stmt->rowCount() > 0) {
            // 3. Supprimer le fichier physique si demandé et local
            if ($deletePhysicalFile && $download && !empty($download['URL'])) {
                $fileUrl = $download['URL'];
                // Vérifier si c'est un fichier local (commence par /uploads/downloads/)
                if (strpos($fileUrl, '/uploads/downloads/') === 0) {
                    $filePath = __DIR__ . '/..' . $fileUrl;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
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
