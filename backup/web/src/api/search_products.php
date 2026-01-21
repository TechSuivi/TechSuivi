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
    
    $searchTerm = trim($_GET['q'] ?? '');
    $scope = $_GET['scope'] ?? 'auto'; // auto, stock, catalog, web
    
    if (empty($searchTerm)) {
        echo json_encode([]);
        exit;
    }
    
    $searchTermLower = strtolower($searchTerm);
    $likePattern = '%' . $searchTermLower . '%';
    
    $results = [];

    // --- 1. SEARCH CATALOG ---
    // Ordre inversé: D'abord le Catalogue
    if ($scope === 'auto' || $scope === 'catalog') {
        $sql = "SELECT id, ref_acadia, ean_code, designation, prix_ht as prix_achat_ht, prix_client as prix_vente_ttc,
                       marque as fournisseur, '' as numero_commande, updated_at as date_ajout, 'catalog' as source
                FROM catalog
                WHERE (LOWER(ean_code) LIKE :search1
                       OR LOWER(designation) LIKE :search2
                       OR LOWER(ref_acadia) LIKE :search3
                       OR LOWER(marque) LIKE :search4
                       OR LOWER(famille) LIKE :search5)
                ORDER BY updated_at DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':search1', $likePattern);
        $stmt->bindParam(':search2', $likePattern);
        $stmt->bindParam(':search3', $likePattern);
        $stmt->bindParam(':search4', $likePattern);
        $stmt->bindParam(':search5', $likePattern);
        $stmt->execute();
        
        $results = array_merge($results, $stmt->fetchAll());
    }

    // --- 2. SEARCH STOCK ---
    // Si 'auto' : on cherche seulement si Catalog vide.
    if (($scope === 'auto' && empty($results)) || $scope === 'stock') {
        $sql = "SELECT id, ref_acadia, ean_code, designation, prix_achat_ht, prix_vente_ttc, fournisseur, numero_commande, date_ajout, 'stock' as source
                FROM Stock
                WHERE (LOWER(ean_code) LIKE :search1
                       OR LOWER(designation) LIKE :search2
                       OR LOWER(ref_acadia) LIKE :search3
                       OR LOWER(fournisseur) LIKE :search4)
                ORDER BY date_ajout DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':search1', $likePattern);
        $stmt->bindParam(':search2', $likePattern);
        $stmt->bindParam(':search3', $likePattern);
        $stmt->bindParam(':search4', $likePattern);
        $stmt->execute();
        
        $results = array_merge($results, $stmt->fetchAll());
    }

    // --- 3. SEARCH WEB (UPCItemDB) ---
    // Si 'auto' : on cherche seulement si Stock ET Catalog vides. Si 'web' : on cherche toujours.
    // Uniquement si le terme de recherche ressemble à un EAN (numérique, > 8 caractères)
    if (($scope === 'auto' && empty($results)) || $scope === 'web') {
        // Simple EAN validation roughly
        if (preg_match('/^\d{8,14}$/', $searchTerm)) {
            $webResults = searchUPCItemDB($searchTerm);
            if (!empty($webResults)) {
                $results = array_merge($results, $webResults);
            }
        }
    }
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
}

function searchUPCItemDB($ean) {
    $url = "https://api.upcitemdb.com/prod/trial/lookup?upc=" . urlencode($ean);
    
    // Initialiser curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Pour éviter les soucis de cert en local/dev
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout court
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['code']) && $data['code'] === 'OK' && !empty($data['items'])) {
            $items = [];
            foreach ($data['items'] as $item) {
                $items[] = [
                    'id' => 'web_' . $item['ean'],
                    'ref_acadia' => '', // Pas de ref interne
                    'ean_code' => $item['ean'],
                    'designation' => $item['title'],
                    'prix_achat_ht' => 0, // Inconnu
                    'prix_vente_ttc' => 0, // Inconnu
                    'fournisseur' => $item['brand'] ?? 'Inconnu',
                    'numero_commande' => '',
                    'date_ajout' => null,
                    'source' => 'web',
                    'image' => !empty($item['images']) ? $item['images'][0] : null
                ];
            }
            return $items;
        }
    }
    return [];
}
?>