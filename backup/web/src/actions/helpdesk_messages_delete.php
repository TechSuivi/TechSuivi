<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
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

// Récupérer l'ID du message à supprimer et la catégorie pour la redirection
$id = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';

if (empty($id) || !is_numeric($id)) {
    $_SESSION['delete_message'] = 'ID de message invalide.';
    $redirectUrl = !empty($category) ? '../index.php?page=messages&category=' . $category : '../index.php?page=messages';
    header('Location: ' . $redirectUrl);
    exit();
}

try {
    // Vérifier si le message existe et récupérer son titre
    $stmt = $pdo->prepare("SELECT TITRE FROM helpdesk_msg WHERE ID = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $message = $stmt->fetch();
    
    if (!$message) {
        $_SESSION['delete_message'] = 'Message non trouvé.';
        $redirectUrl = !empty($category) ? '../index.php?page=messages&category=' . $category : '../index.php?page=messages';
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    // Supprimer le message
    $stmt = $pdo->prepare("DELETE FROM helpdesk_msg WHERE ID = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $_SESSION['delete_message'] = 'Message "' . htmlspecialchars($message['TITRE']) . '" supprimé avec succès.';
    } else {
        $_SESSION['delete_message'] = 'Erreur lors de la suppression du message.';
    }
    
} catch (PDOException $e) {
    $_SESSION['delete_message'] = 'Erreur de base de données : ' . $e->getMessage();
}

// Rediriger vers la liste des messages de la catégorie
$redirect = $_GET['redirect'] ?? '';
if ($redirect === 'dashboard') {
    $redirectUrl = '../index.php?page=dashboard';
} else {
    $redirectUrl = !empty($category) ? '../index.php?page=messages&category=' . $category : '../index.php?page=messages';
}
header('Location: ' . $redirectUrl);
exit();
?>