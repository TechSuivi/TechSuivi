<?php
header('Content-Type: application/json');

// Database connection details
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    $sql = "SELECT ID, Fournisseur FROM fournisseur ORDER BY Fournisseur ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll();
    
    echo json_encode($fournisseurs);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>