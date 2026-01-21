<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$ean = $_GET['ean'] ?? '';

if (empty($ean)) {
    echo json_encode(['found' => false]);
    exit;
}

try {
    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Get the most recent product details (designation, latest price)
    $sqlRecent = "SELECT designation, prix_achat_ht 
                  FROM Stock 
                  WHERE ean_code = :ean 
                  ORDER BY date_ajout DESC 
                  LIMIT 1";
            
    $stmt = $pdo->prepare($sqlRecent);
    $stmt->execute([':ean' => $ean]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // 2. Init response with found product
        $response = [
            'found' => true,
            'designation' => $product['designation'],
            'prix_achat_ht' => $product['prix_achat_ht'],
            'history' => []
        ];

        // 3. Fetch distinct historical prices
        // We group by price and get the max date to know when it was last used
        $sqlHistory = "SELECT prix_achat_ht, MAX(date_ajout) as last_date 
                       FROM Stock 
                       WHERE ean_code = :ean 
                       GROUP BY prix_achat_ht 
                       ORDER BY last_date DESC";
        
        $stmtHist = $pdo->prepare($sqlHistory);
        $stmtHist->execute([':ean' => $ean]);
        $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        // Filter keys to be cleaner
        foreach ($history as $row) {
            $response['history'][] = [
                'price' => $row['prix_achat_ht'],
                'date' => $row['last_date']
            ];
        }

        echo json_encode($response);
    } else {
        echo json_encode(['found' => false]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
