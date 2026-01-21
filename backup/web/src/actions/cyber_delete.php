<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['cyber_message'] = "ID de session invalide.";
    header('Location: ../index.php?page=cyber_list');
    exit();
}

$id = (int)$_GET['id'];

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
    
    // Vérifier que la session existe
    $stmt = $pdo->prepare("SELECT nom FROM FC_cyber WHERE id = ?");
    $stmt->execute([$id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $_SESSION['cyber_message'] = "Session non trouvée.";
    } else {
        // Supprimer la session
        $stmt = $pdo->prepare("DELETE FROM FC_cyber WHERE id = ?");
        $stmt->execute([$id]);
        
        $nomClient = $session['nom'] ? $session['nom'] : 'Session #' . $id;
        $_SESSION['cyber_message'] = "Session de " . htmlspecialchars($nomClient) . " supprimée avec succès.";
    }
    
} catch (PDOException $e) {
    $_SESSION['cyber_message'] = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
}

header('Location: ../index.php?page=cyber_list');
exit();
?>