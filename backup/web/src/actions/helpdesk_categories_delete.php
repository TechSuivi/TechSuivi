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

// Récupérer l'ID de la catégorie à supprimer
$id = $_GET['id'] ?? '';

if (empty($id) || !is_numeric($id)) {
    $_SESSION['delete_message'] = 'ID de catégorie invalide.';
    header('Location: ../index.php?page=helpdesk_categories');
    exit();
}

try {
    // Vérifier si la catégorie existe
    $stmt = $pdo->prepare("SELECT CATEGORIE FROM helpdesk_cat WHERE ID = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $category = $stmt->fetch();
    
    if (!$category) {
        $_SESSION['delete_message'] = 'Catégorie non trouvée.';
        header('Location: ../index.php?page=helpdesk_categories');
        exit();
    }
    
    // TODO: Vérifier si la catégorie est utilisée dans d'autres tables
    // Pour l'instant, on supprime directement
    
    // Supprimer la catégorie
    $stmt = $pdo->prepare("DELETE FROM helpdesk_cat WHERE ID = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $_SESSION['delete_message'] = 'Catégorie "' . htmlspecialchars($category['CATEGORIE']) . '" supprimée avec succès.';
    } else {
        $_SESSION['delete_message'] = 'Erreur lors de la suppression de la catégorie.';
    }
    
} catch (PDOException $e) {
    $_SESSION['delete_message'] = 'Erreur de base de données : ' . $e->getMessage();
}

// Rediriger vers la liste des catégories
header('Location: ../index.php?page=helpdesk_categories');
exit();
?>