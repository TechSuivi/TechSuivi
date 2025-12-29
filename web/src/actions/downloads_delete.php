<?php
session_start();

// Empêcher l'accès si non logué
if (!isset($_SESSION['username'])) {
    // Rediriger vers la page de login ou afficher une erreur
    // Pour un script d'action, il est souvent préférable de ne rien afficher et de juste stopper.
    // Ou rediriger avec un message d'erreur.
    header('Location: ../login.php?error=unauthorized');
    exit();
}

$download_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$download_id) {
    // Gérer l'erreur, peut-être rediriger avec un message
    $_SESSION['delete_message'] = '<p style="color: red;">ID de téléchargement invalide ou manquant pour la suppression.</p>';
    header('Location: ../index.php?page=downloads_list');
    exit();
}

// Connexion PDO (similaire à check_duplicate_client.php)
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    $_SESSION['delete_message'] = '<p style="color: red;">Erreur de connexion DB lors de la suppression.</p>';
    header('Location: ../index.php?page=downloads_list');
    exit;
}

// Suppression
try {
    $stmt = $pdo->prepare("DELETE FROM download WHERE ID = :id");
    $stmt->bindParam(':id', $download_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $_SESSION['delete_message'] = '<p style="color: green;">Téléchargement supprimé avec succès !</p>';
        } else {
            $_SESSION['delete_message'] = '<p style="color: orange;">Aucun téléchargement trouvé avec cet ID pour la suppression.</p>';
        }
    } else {
        $_SESSION['delete_message'] = '<p style="color: red;">Erreur lors de la suppression du téléchargement.</p>';
    }
} catch (PDOException $e) {
    $_SESSION['delete_message'] = '<p style="color: red;">Erreur de base de données lors de la suppression : ' . htmlspecialchars($e->getMessage()) . '</p>';
}

header('Location: ../index.php?page=downloads_list');
exit();
?>