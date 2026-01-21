<?php
/**
 * Action AJAX pour modifier un lien existant
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
$nom = trim($_POST['nom'] ?? '');
$description = trim($_POST['description'] ?? '');
$url = trim($_POST['url'] ?? '');
$show_on_login = isset($_POST['show_on_login']) ? 1 : 0;

$errors = [];

if (!$id) {
    $errors[] = "ID invalide.";
}
if (empty($nom)) {
    $errors[] = "Le nom est obligatoire.";
}
if (empty($url)) {
    $errors[] = "L'URL est obligatoire.";
} elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
    $errors[] = "URL invalide.";
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE liens 
        SET NOM = :nom, DESCRIPTION = :description, URL = :url, show_on_login = :show_on_login
        WHERE ID = :id
    ");
    
    $result = $stmt->execute([
        ':nom' => $nom,
        ':description' => $description,
        ':url' => $url,
        ':show_on_login' => $show_on_login,
        ':id' => $id
    ]);
    
    if ($result) {
        $_SESSION['edit_message'] = "Lien modifié avec succès !";
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur DB: ' . $e->getMessage()]);
}
