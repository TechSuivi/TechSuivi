<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Session expirée']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$titre = trim($_POST['titre'] ?? '');
$contenu = trim($_POST['contenu'] ?? '');
$date_note = $_POST['date_note'] ?? date('Y-m-d H:i:s');
$id_client = (!empty($_POST['id_client']) && (int)$_POST['id_client'] > 0) ? (int)$_POST['id_client'] : null;
$show_on_login = isset($_POST['show_on_login']) ? 1 : 0;

if (empty($titre) || empty($contenu)) {
    echo json_encode(['success' => false, 'error' => 'Titre et contenu obligatoires']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Gestion du fichier
    $fichier_path = null;
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/notes/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception("Impossible de créer le dossier d'upload : " . $uploadDir);
            }
        }
        
        $extension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('note_') . '.' . $extension;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $targetPath)) {
            $fichier_path = 'uploads/notes/' . $fileName;
            
            // Si on modifie et qu'on a un nouveau fichier, supprimer l'ancien
            if ($id > 0) {
                $stmtOld = $pdo->prepare("SELECT fichier_path FROM notes_globales WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldPath = $stmtOld->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                    unlink(__DIR__ . '/../' . $oldPath);
                }
            }
        } else {
            throw new Exception("Erreur lors du déplacement du fichier téléchargé.");
        }
    }

    if ($id > 0) {
        // Update
        $sql = "UPDATE notes_globales SET titre = :titre, contenu = :contenu, date_note = :date_note, id_client = :id_client, show_on_login = :show_on_login";
        if ($fichier_path) {
            $sql .= ", fichier_path = :fichier_path";
        }
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    } else {
        // Insert
        $sql = "INSERT INTO notes_globales (titre, contenu, date_note, id_client, fichier_path, show_on_login) 
                VALUES (:titre, :contenu, :date_note, :id_client, :fichier_path, :show_on_login)";
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':titre', $titre);
    $stmt->bindParam(':contenu', $contenu);
    $stmt->bindParam(':date_note', $date_note);
    $stmt->bindParam(':id_client', $id_client, $id_client === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':show_on_login', $show_on_login, PDO::PARAM_INT);
    
    if (!($id > 0 && !$fichier_path)) {
        $stmt->bindParam(':fichier_path', $fichier_path);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note sauvegardée !']);
    } else {
        throw new Exception('Erreur lors de l\'exécution SQL');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
