<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['user_message'] = "ID utilisateur manquant.";
    header('Location: ../index.php?page=users_list');
    exit();
}

$userId = (int)$_GET['id'];

// Configuration de la base de données
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Vérifier que l'utilisateur existe et récupérer ses informations
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['user_message'] = "Utilisateur non trouvé.";
        header('Location: ../index.php?page=users_list');
        exit();
    }
    
    // Empêcher la suppression de son propre compte
    if ($user['username'] === $_SESSION['username']) {
        $_SESSION['user_message'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header('Location: ../index.php?page=users_list');
        exit();
    }
    
    // Supprimer l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    
    $_SESSION['user_message'] = "Utilisateur '" . htmlspecialchars($user['username']) . "' supprimé avec succès.";
    
} catch (PDOException $e) {
    $_SESSION['user_message'] = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
}

header('Location: ../index.php?page=users_list');
exit();
?>