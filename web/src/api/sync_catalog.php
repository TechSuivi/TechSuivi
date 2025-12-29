<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    define('TECHSUIVI_INCLUDED', true);
}

// Configuration de la base de données
// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
$charset = 'utf8mb4';

header('Content-Type: application/json');

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset={$charset}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer l'URL du catalogue depuis la configuration
    function getCatalogUrl($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'acadia_catalog_url'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['config_value'];
            }
        } catch (Exception $e) {
            // Si erreur, retourner null
        }
        
        return null;
    }
    
    $catalogUrl = getCatalogUrl($pdo);
    
    if (empty($catalogUrl)) {
        echo json_encode([
            'success' => false,
            'error' => 'URL du catalogue Acadia non configurée. Veuillez configurer l\'URL dans les paramètres.'
        ]);
        exit;
    }
    
    // Télécharger le fichier CSV
    $csvContent = file_get_contents($catalogUrl);
    
    if ($csvContent === false) {
        echo json_encode(['success' => false, 'error' => 'Impossible de télécharger le catalogue']);
        exit;
    }
    
    // Convertir l'encodage si nécessaire
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    // Sauvegarder le fichier temporairement
    $tempFile = sys_get_temp_dir() . '/acadia_catalog_sync.csv';
    file_put_contents($tempFile, $csvContent);
    
    $handle = fopen($tempFile, 'r');
    if ($handle === false) {
        echo json_encode(['success' => false, 'error' => 'Impossible d\'ouvrir le fichier CSV']);
        exit;
    }
    
    $headers = fgetcsv($handle, 0, ';');
    
    // Préparer la requête d'insertion avec mise à jour automatique
    $sql = "INSERT INTO catalog (
        marque, famille, part_number, ref_acadia, ean_code, ref_constructeur,
        designation, stock_reel, prix_ht, prix_client, ecotaxe, copie_privee,
        poids, image, categorie_principale, categorie_secondaire, categorie_tertiaire
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        marque = VALUES(marque),
        famille = VALUES(famille),
        part_number = VALUES(part_number),
        ref_constructeur = VALUES(ref_constructeur),
        designation = VALUES(designation),
        stock_reel = VALUES(stock_reel),
        prix_ht = VALUES(prix_ht),
        prix_client = VALUES(prix_client),
        ecotaxe = VALUES(ecotaxe),
        copie_privee = VALUES(copie_privee),
        poids = VALUES(poids),
        image = VALUES(image),
        categorie_principale = VALUES(categorie_principale),
        categorie_secondaire = VALUES(categorie_secondaire),
        categorie_tertiaire = VALUES(categorie_tertiaire)";
    
    $stmt = $pdo->prepare($sql);
    
    $imported = 0;
    $updated = 0;
    $errors = 0;
    $lineNumber = 0;
    
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $lineNumber++;
        
        // Vérifier que le nombre de colonnes correspond
        if (count($row) !== count($headers)) {
            $errors++;
            continue;
        }
        
        $data = array_combine($headers, $row);
        
        // Nettoyer et valider les données selon les vrais en-têtes CSV
        $cleanData = [
            trim($data['MARQUE'] ?? ''),
            trim($data['FAMILLE'] ?? ''),
            trim($data['Part Number'] ?? ''),
            trim($data['REF ACADIA'] ?? ''),
            trim($data['EAN CODE'] ?? ''),
            trim($data['REF CONSTRUCTEUR'] ?? ''),
            trim($data['DESIGNATION 1'] ?? ''),
            !empty(trim($data['STOCK REEL'] ?? '')) ? (int)$data['STOCK REEL'] : null,
            !empty(trim($data['PRIX HT'] ?? '')) ? (float)str_replace(',', '.', $data['PRIX HT']) : null,
            !empty(trim($data['PRIX CLIENT'] ?? '')) ? (float)str_replace(',', '.', $data['PRIX CLIENT']) : null,
            !empty(trim($data['ECOTAXE'] ?? '')) ? (float)str_replace(',', '.', $data['ECOTAXE']) : null,
            !empty(trim($data['COPIE PRIVEE'] ?? '')) ? (float)str_replace(',', '.', $data['COPIE PRIVEE']) : null,
            !empty(trim($data['POIDS'] ?? '')) ? (float)str_replace(',', '.', $data['POIDS']) : null,
            trim($data['IMAGE'] ?? ''),
            trim($data['CATEGORIE PRINCIPALE'] ?? ''),
            trim($data['CATEGORIE SECONDAIRE'] ?? ''),
            trim($data['CATEGORIE TERTIAIRE'] ?? '')
        ];
        
        try {
            $stmt->execute($cleanData);
            
            // Vérifier si c'est une insertion ou une mise à jour
            if ($stmt->rowCount() == 1) {
                $imported++; // Nouveau produit
            } else if ($stmt->rowCount() == 2) {
                $updated++; // Produit mis à jour
            }
            
        } catch (PDOException $e) {
            $errors++;
        }
    }
    
    fclose($handle);
    unlink($tempFile);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'updated' => $updated,
        'errors' => $errors,
        'total' => $imported + $updated + $errors,
        'message' => "Synchronisation terminée"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>