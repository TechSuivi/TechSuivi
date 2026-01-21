<?php
header('Content-Type: application/json; charset=utf-8');

// Inclure la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit();
}

// Récupérer le terme de recherche et les paramètres de pagination
$searchTerm = trim($_GET['search'] ?? '');
$itemsPerPage = (int)($_GET['per_page'] ?? 20); // Par défaut 20 éléments par page
$currentPage = max(1, (int)($_GET['page_num'] ?? 1)); // Page courante (minimum 1)
$hideClosed = isset($_GET['hide_closed']) && $_GET['hide_closed'] === '1'; // Masquer les interventions clôturées

// Valider le nombre d'éléments par page
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($itemsPerPage, $allowedPerPage)) {
    $itemsPerPage = 20; // Valeur par défaut si invalide
}

// Calculer l'offset pour la pagination
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    // Vérifier si la table intervention_statuts existe
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        // Table n'existe pas encore
    }
    
    // Construire la requête avec ou sans recherche et filtrage
    $whereConditions = [];
    $params = [];
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(LOWER(c.nom) LIKE :search1 OR LOWER(c.prenom) LIKE :search2 OR LOWER(CONCAT(c.nom, ' ', c.prenom)) LIKE :search3)";
        $searchPattern = '%' . strtolower($searchTerm) . '%';
        $params[':search1'] = $searchPattern;
        $params[':search2'] = $searchPattern;
        $params[':search3'] = $searchPattern;
    }
    
    if ($hideClosed) {
        $whereConditions[] = "i.en_cours = 1";
    }
    
    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";
    
    // D'abord, compter le nombre total d'interventions
    $countSql = "
        SELECT COUNT(*) as total
        FROM inter i
        LEFT JOIN clients c ON i.id_client = c.ID
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalInterventions = $countStmt->fetch()['total'];
    
    // Calculer le nombre total de pages
    $totalPages = ceil($totalInterventions / $itemsPerPage);
    
    // S'assurer que la page courante n'excède pas le nombre total de pages
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Ajouter les paramètres de pagination
    $params[':limit'] = $itemsPerPage;
    $params[':offset'] = $offset;
    
    if ($tableExists) {
        $sql = "
            SELECT
                i.id,
                i.id_client,
                i.date,
                i.en_cours,
                i.statut_id,
                i.info,
                i.nettoyage,
                i.info_log,
                i.note_user,
                CONCAT(c.nom, ' ', c.prenom) as client_nom,
                s.nom as statut_nom,
                s.couleur as statut_couleur
            FROM inter i
            LEFT JOIN clients c ON i.id_client = c.ID
            LEFT JOIN intervention_statuts s ON i.statut_id = s.id
            $whereClause
            ORDER BY i.date DESC
            LIMIT :limit OFFSET :offset
        ";
    } else {
        // Fallback sans les statuts
        $sql = "
            SELECT
                i.id,
                i.id_client,
                i.date,
                i.en_cours,
                i.info,
                i.nettoyage,
                i.info_log,
                i.note_user,
                CONCAT(c.nom, ' ', c.prenom) as client_nom
            FROM inter i
            LEFT JOIN clients c ON i.id_client = c.ID
            $whereClause
            ORDER BY i.date DESC
            LIMIT :limit OFFSET :offset
        ";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $interventions = $stmt->fetchAll();
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'data' => $interventions,
        'count' => count($interventions),
        'total' => $totalInterventions,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'search_term' => $searchTerm,
        'has_statuts' => $tableExists
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors de la recherche : ' . $e->getMessage()
    ]);
}
?>