<?php
session_start();

// Empêcher l'accès si non logué
if (!isset($_SESSION['username'])) {
    // Rediriger vers la page de login ou afficher une erreur
    header('Location: ../login.php?error=unauthorized');
    exit();
}

$intervention_id = trim($_GET['id'] ?? '');

if (empty($intervention_id)) {
    // Gérer l'erreur, peut-être rediriger avec un message
    $_SESSION['delete_message'] = '<p style="color: red;">ID d\'intervention invalide ou manquant pour la suppression.</p>';
    header('Location: ../index.php?page=interventions_list');
    exit();
}

// Connexion PDO (similaire aux autres fichiers d'actions)
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
    header('Location: ../index.php?page=interventions_list');
    exit;
}

// Suppression
try {
    // D'abord supprimer les photos associées (fichiers et BD)
    $stmtPhotos = $pdo->prepare("SELECT filename FROM intervention_photos WHERE intervention_id = :id");
    $stmtPhotos->execute([':id' => $intervention_id]);
    $photos = $stmtPhotos->fetchAll(PDO::FETCH_COLUMN);
    
    $uploadDir = __DIR__ . '/../uploads/interventions/';
    
    foreach ($photos as $filename) {
        $filepath = $uploadDir . $filename;
        $thumbpath = $uploadDir . 'thumb_' . $filename;
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        if (file_exists($thumbpath)) {
            unlink($thumbpath);
        }
    }
    
    // Supprimer les enregistrements de photos
    $stmtDeletePhotos = $pdo->prepare("DELETE FROM intervention_photos WHERE intervention_id = :id");
    $stmtDeletePhotos->execute([':id' => $intervention_id]);

    // Ensuite supprimer l'intervention elle-même
    $stmt = $pdo->prepare("DELETE FROM inter WHERE id = :id");
    $stmt->bindParam(':id', $intervention_id, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            $_SESSION['delete_message'] = '<p style="color: green;">Intervention supprimée avec succès !</p>';
        } else {
            $_SESSION['delete_message'] = '<p style="color: orange;">Aucune intervention trouvée avec cet ID pour la suppression.</p>';
        }
    } else {
        $_SESSION['delete_message'] = '<p style="color: red;">Erreur lors de la suppression de l\'intervention.</p>';
    }
} catch (PDOException $e) {
    $_SESSION['delete_message'] = '<p style="color: red;">Erreur de base de données lors de la suppression : ' . htmlspecialchars($e->getMessage()) . '</p>';
}

header('Location: ../index.php?page=interventions_list');
exit();
?>