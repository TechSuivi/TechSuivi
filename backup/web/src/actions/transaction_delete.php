<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['transaction_message'] = "ID de transaction invalide.";
    header('Location: ../index.php?page=transactions_list');
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
    
    // Vérifier que la transaction existe
    $stmt = $pdo->prepare("SELECT nom, montant, type FROM FC_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        $_SESSION['transaction_message'] = "Transaction non trouvée.";
    } else {
        // Supprimer la transaction
        $stmt = $pdo->prepare("DELETE FROM FC_transactions WHERE id = ?");
        $stmt->execute([$id]);
        
        $description = $transaction['nom'] ? $transaction['nom'] : 'Transaction #' . $id;
        $montant = number_format($transaction['montant'], 2);
        $type = ucfirst($transaction['type']);
        
        $_SESSION['transaction_message'] = "Transaction \"" . htmlspecialchars($description) . "\" (" . $type . " - " . $montant . " €) supprimée avec succès.";
    }
    
} catch (PDOException $e) {
    $_SESSION['transaction_message'] = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
}

header('Location: ../index.php?page=transactions_list');
exit();
?>