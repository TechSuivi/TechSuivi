<?php
/**
 * API Interventions en Cours - Version pour AutoIt
 * Authentification par clé API
 */

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Vérification de l'authentification
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Clé API manquante',
        'code' => 'MISSING_API_KEY'
    ]);
    exit;
}

// Connexion à la base de données
try {
    $pdo = getDatabaseConnection();
    // S'assurer que la connexion utilise UTF-8
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion à la base de données',
        'code' => 'DATABASE_ERROR'
    ]);
    exit;
}

// Vérification de la clé API dans la table configuration
try {
    $stmt = $pdo->prepare("SELECT config_key, description FROM configuration WHERE config_value = ? AND config_key = 'api_key_autoit_client' AND category = 'api_keys' AND config_type = 'text'");
    $stmt->execute([$apiKey]);
    $validKey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$validKey) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Clé API invalide',
            'code' => 'INVALID_API_KEY'
        ]);
        exit;
    }
    
    // Log de l'utilisation de la clé
    error_log("Interventions API - Accès autorisé avec la clé: AutoIt Client Access");
    
} catch (Exception $e) {
    // Si la table configuration n'existe pas ou n'a pas de clés API, utiliser les clés par défaut en fallback
    $FALLBACK_API_KEYS = [
        'techsuivi_autoit_2025' => 'AutoIt Interventions Access (Fallback)',
    ];
    
    if (!array_key_exists($apiKey, $FALLBACK_API_KEYS)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Clé API invalide (mode fallback)',
            'code' => 'INVALID_API_KEY'
        ]);
        exit;
    }
    
    error_log("Interventions API - Mode fallback activé, table configuration non disponible: " . $e->getMessage());
}

// Paramètres de la requête
$limit = min((int)($_GET['limit'] ?? 100), 500);
$offset = max((int)($_GET['offset'] ?? 0), 0);
$format = $_GET['format'] ?? 'json'; // json ou simple

try {
    // Requête pour récupérer les interventions en cours
    $sql = "SELECT 
                i.id, 
                i.id_client, 
                i.date, 
                i.en_cours, 
                i.info, 
                i.nettoyage, 
                i.info_log, 
                i.note_user,
                CONCAT(c.nom, ' ', c.prenom) as client_nom,
                c.nom as client_nom_famille,
                c.prenom as client_prenom,
                c.ville as client_ville,
                c.telephone as client_telephone,
                c.portable as client_portable,
                c.adresse1 as client_adresse1,
                c.adresse2 as client_adresse2,
                c.cp as client_cp,
                c.mail as client_mail
            FROM inter i 
            LEFT JOIN clients c ON i.id_client = c.ID 
            WHERE i.en_cours = 1
            ORDER BY i.date DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $interventions = $stmt->fetchAll();
    
    // Compter le total
    $countSql = "SELECT COUNT(*) as total FROM inter WHERE en_cours = 1";
    $countStmt = $pdo->query($countSql);
    $total = $countStmt->fetch()['total'];
    
    // Formater selon le type demandé
    if ($format === 'simple') {
        // Format simplifié pour AutoIt
        $results = [];
        foreach ($interventions as $intervention) {
            $adresse_complete = trim($intervention['client_adresse1'] ?? '');
            if (!empty($intervention['client_adresse2'])) {
                $adresse_complete .= (!empty($adresse_complete) ? ', ' : '') . trim($intervention['client_adresse2']);
            }
            if (!empty($intervention['client_cp'])) {
                $adresse_complete .= (!empty($adresse_complete) ? ', ' : '') . trim($intervention['client_cp']);
            }
            
            $results[] = [
                'id' => $intervention['id'],
                'client_id' => $intervention['id_client'],
                'date' => $intervention['date'],
                'client_nom' => $intervention['client_nom'],
                'client_ville' => $intervention['client_ville'],
                'client_telephone' => $intervention['client_telephone'] ?: $intervention['client_portable'],
                'client_adresse' => $adresse_complete,
                'description' => $intervention['info'],
                'notes' => $intervention['note_user']
            ];
        }
        
        $response = [
            'success' => true,
            'total' => (int)$total,
            'count' => count($results),
            'interventions' => $results
        ];
    } else {
        // Format complet (comme l'API principale)
        $results = [];
        foreach ($interventions as $intervention) {
            $adresse_complete = trim($intervention['client_adresse1'] ?? '');
            if (!empty($intervention['client_adresse2'])) {
                $adresse_complete .= (!empty($adresse_complete) ? ', ' : '') . trim($intervention['client_adresse2']);
            }
            if (!empty($intervention['client_cp'])) {
                $adresse_complete .= (!empty($adresse_complete) ? ', ' : '') . trim($intervention['client_cp']);
            }
            
            $results[] = [
                'id' => (int)$intervention['id'],
                'id_client' => (int)$intervention['id_client'],
                'date' => $intervention['date'],
                'en_cours' => (bool)$intervention['en_cours'],
                'info' => $intervention['info'],
                'nettoyage' => $intervention['nettoyage'],
                'info_log' => $intervention['info_log'],
                'note_user' => $intervention['note_user'],
                'client' => [
                    'nom_complet' => $intervention['client_nom'],
                    'nom' => $intervention['client_nom_famille'],
                    'prenom' => $intervention['client_prenom'],
                    'ville' => $intervention['client_ville'],
                    'telephone' => $intervention['client_telephone'],
                    'portable' => $intervention['client_portable'],
                    'adresse1' => $intervention['client_adresse1'],
                    'adresse2' => $intervention['client_adresse2'],
                    'adresse_complete' => $adresse_complete,
                    'cp' => $intervention['client_cp'],
                    'mail' => $intervention['client_mail']
                ]
            ];
        }
        
        $response = [
            'success' => true,
            'data' => $results,
            'meta' => [
                'total' => (int)$total,
                'count' => count($results),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des interventions',
        'code' => 'QUERY_ERROR'
    ]);
}
?>