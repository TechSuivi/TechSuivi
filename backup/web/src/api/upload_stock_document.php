<?php
session_start();
header('Content-Type: application/json');

// Vérification de la session
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Inclure la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

// Définir le répertoire d'upload
$upload_dir = __DIR__ . '/../uploads/stock/';
$relative_dir = 'uploads/stock/';

// Créer le répertoire s'il n'existe pas
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Impossible de créer le répertoire de destination.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier les paramètres requis
    $fournisseur = $_POST['fournisseur'] ?? '';
    $numero_commande = $_POST['numero_commande'] ?? '';

    if (empty($fournisseur) || empty($numero_commande)) {
        echo json_encode(['success' => false, 'error' => 'Fournisseur et N° Commande requis pour lier le document.']);
        exit;
    }

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Extensions autorisées
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            // Nom de fichier unique
            $new_file_name = time() . '_' . preg_replace('/[^a-z0-9\._-]/i', '', $file_name);
            $destination = $upload_dir . $new_file_name;
            $relative_path = $relative_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                
                // Insertion dans la base de données
                try {
                    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                    $sql = "INSERT INTO stock_documents (fournisseur, numero_commande, file_path, original_name) VALUES (:fournisseur, :numero_commande, :file_path, :original_name)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':fournisseur' => $fournisseur,
                        ':numero_commande' => $numero_commande,
                        ':file_path' => $relative_path,
                        ':original_name' => $file_name
                    ]);
                    
                    $lastId = $pdo->lastInsertId();

                    echo json_encode([
                        'success' => true, 
                        'id' => $lastId,
                        'path' => $relative_path, 
                        'filename' => $new_file_name,
                        'original_name' => $file_name
                    ]);

                } catch (PDOException $e) {
                    // Si erreur DB, on pourrait supprimer le fichier, mais pour debug on le laisse
                    echo json_encode(['success' => false, 'error' => 'Erreur DB : ' . $e->getMessage()]);
                }

            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors du déplacement du fichier.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé (PDF ou Images seulement).']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu ou erreur d\'upload. Code: ' . ($_FILES['file']['error'] ?? 'N/A')]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
}
?>
