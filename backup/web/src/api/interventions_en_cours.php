<?php
// Configuration de la session et vérification de connexion
session_start();

// Vérifier si l'utilisateur est connecté (même vérification que dans index.php)
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Paramètres optionnels
$limit = min((int)($_GET['limit'] ?? 100), 500); // Limiter à 500 résultats max
$offset = max((int)($_GET['offset'] ?? 0), 0);

try {
    // Requête pour récupérer les interventions en cours (non clôturées)
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
    
    // Compter le nombre total d'interventions en cours
    $countSql = "SELECT COUNT(*) as total FROM inter WHERE en_cours = 1";
    $countStmt = $pdo->query($countSql);
    $total = $countStmt->fetch()['total'];
    
    // Formater les résultats
    $results = [];
    foreach ($interventions as $intervention) {
        // Construire l'adresse complète
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
    
    // Réponse avec métadonnées
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
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des interventions: ' . $e->getMessage()
    ]);
}
?>