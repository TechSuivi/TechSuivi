<?php
/**
 * Action AJAX pour ajouter un nouveau téléchargement
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

// Récupération des données du formulaire
$nom = trim($_POST['nom'] ?? '');
$description = trim($_POST['description'] ?? '');
$source_type = $_POST['source_type'] ?? 'url';
$url = trim($_POST['url'] ?? '');
$show_on_login = isset($_POST['show_on_login']) ? 1 : 0;

// Validation
$errors = [];

if (empty($nom)) {
    $errors[] = "Le nom du fichier est obligatoire.";
}

if ($source_type === 'url') {
    if (empty($url)) {
        $errors[] = "L'URL est obligatoire.";
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = "Format d'URL invalide.";
    }
} else {
    // Gestion de l'upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'upload du fichier (Code: " . ($_FILES['file']['error'] ?? 'Inconnu') . ").";
    } else {
        $file = $_FILES['file'];
        
        // Créer le dossier s'il n'existe pas
        $uploadDir = __DIR__ . '/../uploads/downloads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0775, true);
            // S'assurer que les permissions sont OK pour www-data
            chmod($uploadDir, 0775);
        }
        
        // Sécuriser le nom du fichier
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        
        // Éviter d'écraser un fichier existant si besoin, ou utiliser un prefix unique
        $uniqueFilename = time() . '_' . $filename;
        $destination = $uploadDir . $uniqueFilename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Générer l'URL relative. On suppose que le script est dans /actions/
            // L'URL finale doit être accessible depuis la racine du site
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Correction du chemin pour l'URL (on pointe vers /uploads/downloads/)
            $url = "/uploads/downloads/" . $uniqueFilename;
        } else {
            $errors[] = "Impossible de déplacer le fichier vers le dossier de destination.";
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO download (NOM, DESCRIPTION, URL, show_on_login) 
        VALUES (:nom, :description, :url, :show_on_login)
    ");
    
    $result = $stmt->execute([
        ':nom' => $nom,
        ':description' => $description,
        ':url' => $url,
        ':show_on_login' => $show_on_login
    ]);
    
    if ($result) {
        $newId = $pdo->lastInsertId();
        $_SESSION['edit_message'] = "Téléchargement ajouté avec succès via popup !"; // Message pour la session
        echo json_encode([
            'success' => true, 
            'message' => 'Téléchargement ajouté avec succès !',
            'id' => $newId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du téléchargement.']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
