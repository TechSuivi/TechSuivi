<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$searchAllCategories = isset($_GET['search_all']) && $_GET['search_all'] === 'true';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(10, min(100, intval($_GET['limit'] ?? 20))); // Entre 10 et 100, défaut 20

$response = ['success' => false, 'data' => [], 'pagination' => []];

// Si on recherche dans toutes les catégories, on n'a pas besoin de catégorie spécifique
if (!$searchAllCategories && (empty($category) || !is_numeric($category))) {
    echo json_encode(['success' => false, 'message' => 'Catégorie invalide']);
    exit();
}

try {
    // Construire la requête de base
    if ($searchAllCategories) {
        // Recherche dans toutes les catégories
        $baseQuery = "FROM helpdesk_msg h
                      LEFT JOIN helpdesk_cat c ON h.CATEGORIE = c.ID
                      LEFT JOIN clients cl ON h.id_client = cl.ID
                      WHERE 1=1";
        $params = [];
    } else {
        // Recherche dans une catégorie spécifique
        $baseQuery = "FROM helpdesk_msg h
                      LEFT JOIN helpdesk_cat c ON h.CATEGORIE = c.ID
                      LEFT JOIN clients cl ON h.id_client = cl.ID
                      WHERE h.CATEGORIE = ?";
        $params = [$category];
    }
    
    // Ajouter la recherche si fournie
    if (!empty($search)) {
        $baseQuery .= " AND (h.TITRE LIKE ? OR h.MESSAGE LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Compter le total des résultats
    $countQuery = "SELECT COUNT(*) " . $baseQuery;
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetchColumn();
    
    // Calculer la pagination
    $totalPages = ceil($totalResults / $limit);
    $offset = ($page - 1) * $limit;
    
    // Récupérer les messages avec pagination
    if ($searchAllCategories) {
        $dataQuery = "SELECT h.ID, h.TITRE, h.MESSAGE, h.DATE, h.FAIT, h.DATE_FAIT, h.CATEGORIE, 
                             c.CATEGORIE as CATEGORIE_NOM, c.couleur as CATEGORIE_COULEUR,
                             cl.nom as client_nom, cl.prenom as client_prenom, cl.telephone as client_telephone, cl.portable as client_portable, cl.ID as id_client
                      " . $baseQuery . " 
                      ORDER BY h.FAIT ASC, h.DATE DESC LIMIT ? OFFSET ?";
    } else {
        $dataQuery = "SELECT h.ID, h.TITRE, h.MESSAGE, h.DATE, h.FAIT, h.DATE_FAIT, h.CATEGORIE, 
                             c.CATEGORIE as CATEGORIE_NOM, c.couleur as CATEGORIE_COULEUR,
                             cl.nom as client_nom, cl.prenom as client_prenom, cl.telephone as client_telephone, cl.portable as client_portable, cl.ID as id_client
                      " . $baseQuery . " 
                      ORDER BY h.FAIT ASC, h.DATE DESC LIMIT ? OFFSET ?";
    }
    
    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($dataQuery);
    $stmt->execute($dataParams);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les réponses pour ces messages
    if (!empty($messages)) {
        $messageIds = array_column($messages, 'ID');
        $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';
        
        $repliesQuery = "SELECT ID, MESSAGE_ID, MESSAGE, DATE_REPONSE 
                         FROM helpdesk_reponses 
                         WHERE MESSAGE_ID IN ($placeholders) 
                         ORDER BY DATE_REPONSE ASC";
                         
        $stmt = $pdo->prepare($repliesQuery);
        $stmt->execute($messageIds);
        $allReplies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les réponses par message_id
        $repliesByMessage = [];
        foreach ($allReplies as $reply) {
            $repliesByMessage[$reply['MESSAGE_ID']][] = $reply;
        }
        
        // Attacher les réponses aux messages
        foreach ($messages as &$msg) {
            $msg['REPLIES'] = $repliesByMessage[$msg['ID']] ?? [];
        }
    }
    
    // Calculer les statistiques
    $statsQuery = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN h.FAIT = 1 THEN 1 ELSE 0 END) as done,
                    SUM(CASE WHEN h.FAIT = 0 THEN 1 ELSE 0 END) as todo
                   " . $baseQuery;
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => $messages,
        'stats' => $stats,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_results' => $totalResults,
            'limit' => $limit,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ],
        'search' => $search
    ];
    
} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()];
}

echo json_encode($response);
?>