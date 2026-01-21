<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

try {
    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($action === 'create_session') {
        $name = $_POST['name'] ?? '';
        if (empty($name)) throw new Exception("Nom requis");

        $stmt = $pdo->prepare("INSERT INTO inventory_sessions (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'add_item') {
        $session_id = $_POST['session_id'] ?? 0;
        $ean = $_POST['ean'] ?? '';
        $designation = $_POST['designation'] ?? '';
        $quantity = $_POST['quantity'] ?? 0;
        $prix = $_POST['prix'] ?? 0.00;

        if (empty($designation)) throw new Exception("Désignation requise");

        $stmt = $pdo->prepare("INSERT INTO inventory_items (session_id, ean_code, designation, quantity, prix_achat_ht) VALUES (:sid, :ean, :des, :qty, :prix)");
        $stmt->execute([
            ':sid' => $session_id,
            ':ean' => $ean,
            ':des' => $designation,
            ':qty' => $quantity,
            ':prix' => $prix
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'update_item') {
        $id = $_POST['id'] ?? 0;
        $quantity = $_POST['quantity'] ?? null;
        $prix = $_POST['prix'] ?? null;

        $fields = [];
        $params = [':id' => $id];

        if ($quantity !== null) {
            $fields[] = "quantity = :qty";
            $params[':qty'] = $quantity;
        }
        if ($prix !== null) {
            $fields[] = "prix_achat_ht = :prix";
            $params[':prix'] = $prix;
        }

        if (empty($fields)) throw new Exception("Rien à mettre à jour");

        $sql = "UPDATE inventory_items SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_item') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'close_session') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE inventory_sessions SET status = 'CLOSED' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);

    } else {
        throw new Exception("Action inconnue");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
