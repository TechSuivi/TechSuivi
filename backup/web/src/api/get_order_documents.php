<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$fournisseur = $_GET['fournisseur'] ?? '';
$numero_commande = $_GET['numero_commande'] ?? '';

if (empty($fournisseur) || empty($numero_commande)) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "SELECT * FROM stock_documents WHERE fournisseur = :fournisseur AND numero_commande = :numero_commande ORDER BY date_ajout DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fournisseur' => $fournisseur,
        ':numero_commande' => $numero_commande
    ]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
