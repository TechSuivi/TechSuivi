<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'ID requis']);
    exit;
}

try {
    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Récupérer le chemin du fichier
    $stmt = $pdo->prepare("SELECT file_path FROM stock_documents WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doc) {
        $filePath = __DIR__ . '/../' . $doc['file_path']; // stocké relatif comme uploads/stock/...
        
        // 2. Supprimer de la DB
        $delStmt = $pdo->prepare("DELETE FROM stock_documents WHERE id = :id");
        $delStmt->execute([':id' => $id]);

        // 3. Supprimer du disque
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Document introuvable']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
