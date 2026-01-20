<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé', 'error' => 'Non autorisé']);
    exit();
}

// Database connection details
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur DB', 'error' => $e->getMessage()]);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? ''; // From GET or POST

// --- POST Handlers (Add, Toggle, Delete) ---
if ($method === 'POST') {
    if ($action === 'add') {
        $categorie = $_POST['categorie'] ?? '';
        $titre = trim($_POST['titre'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
        
        $response = ['success' => false, 'message' => ''];

        if (empty($categorie) || !is_numeric($categorie)) {
            $response['message'] = 'Catégorie invalide.';
        } elseif (empty($titre)) {
            $response['message'] = 'Le titre est obligatoire.';
        } elseif (empty($message)) {
            $response['message'] = 'Le message est obligatoire.';
        } else {
            try {
                $sql = "INSERT INTO helpdesk_msg (CATEGORIE, TITRE, MESSAGE, DATE, FAIT, id_client) VALUES (:categorie, :titre, :message, NOW(), 0, :id_client)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':categorie', $categorie, PDO::PARAM_INT);
                $stmt->bindParam(':titre', $titre);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':id_client', $id_client); // Peut être null

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Message ajouté avec succès !';
                } else {
                    $response['message'] = 'Erreur lors de l\'ajout du message.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit();

    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'] ?? '';
        $response = ['success' => false, 'message' => ''];

        if (empty($id) || !is_numeric($id)) {
            $response['message'] = 'ID de message invalide.';
        } else {
            try {
                // Récupérer le statut actuel
                $stmt = $pdo->prepare("SELECT FAIT FROM helpdesk_msg WHERE ID = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $currentStatus = $stmt->fetchColumn();
                
                if ($currentStatus !== false) {
                    $newStatus = $currentStatus ? 0 : 1;
                    $dateField = $newStatus ? 'NOW()' : 'NULL';
                    
                    // Direct SQL injection for NOW()/NULL is safe here as it's hardcoded logic
                    $sql = "UPDATE helpdesk_msg SET FAIT = :status, DATE_FAIT = $dateField WHERE ID = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':status', $newStatus, PDO::PARAM_INT);
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = $newStatus ? 'Message marqué comme fait.' : 'Message marqué comme non fait.';
                        $response['new_status'] = $newStatus;
                    } else {
                        $response['message'] = 'Erreur lors de la mise à jour du statut.';
                    }
                } else {
                    $response['message'] = 'Message non trouvé.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit();
    }
}

// --- GET Handler (Listing) ---
// Default action if not specified
$category = $_GET['category'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, (int)($_GET['limit'] ?? 20));
$search = trim($_GET['search'] ?? '');
$allCats = isset($_GET['all_categories']) && $_GET['all_categories'] == '1';

// Building the Query
$where = [];
$params = [];

// Category Filter: Only apply if NOT "search all categories" AND category is valid
if (!$allCats) {
    if ($category !== 'all' && is_numeric($category)) {
        $where[] = "m.CATEGORIE = :cat";
        $params[':cat'] = $category;
    }
}

// Search Filter
if (!empty($search)) {
    // Search in Title, Message, Client Name, Client Firstname
    $where[] = "(m.TITRE LIKE :search OR m.MESSAGE LIKE :search OR c.nom LIKE :search OR c.prenom LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereSQL = "";
if (!empty($where)) {
    $whereSQL = " WHERE " . implode(" AND ", $where);
}

try {
    // 1. Get Stats (Total, Todo, Done) based on current filters
    $sqlStats = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN m.FAIT = 0 THEN 1 ELSE 0 END) as todo,
            SUM(CASE WHEN m.FAIT = 1 THEN 1 ELSE 0 END) as done
        FROM helpdesk_msg m
        LEFT JOIN clients c ON m.id_client = c.ID
        $whereSQL
    ";
    
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
    // Safely handle nulls if no records
    $totalRecords = (int)($stats['total'] ?? 0);
    
    // 2. Get Messages (Paginated)
    $offset = ($page - 1) * $limit;
    
    $sqlMsgs = "
        SELECT 
            m.ID, m.TITRE, m.MESSAGE, m.DATE, m.FAIT, m.DATE_FAIT, m.CATEGORIE,
            c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_telephone, c.portable as client_portable, c.ID as id_client
        FROM helpdesk_msg m
        LEFT JOIN clients c ON m.id_client = c.ID
        $whereSQL
        ORDER BY m.FAIT ASC, m.DATE DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sqlMsgs);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    // Bind limit/offset explicitly as integers
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process messages for display
    foreach ($messages as &$msg) {
        $msg['date_formatted'] = date('d/m/Y H:i', strtotime($msg['DATE']));
        // Initialize replies array to avoid JS errors if frontend expects it
        // If there's a replies table, fetch them here. For now, empty.
        $msg['replies'] = []; 
    }
    
    // 3. Return Review
    echo json_encode([
        'stats' => [
            'total' => $totalRecords,
            'todo'  => (int)($stats['todo'] ?? 0),
            'done'  => (int)($stats['done'] ?? 0)
        ],
        'messages' => $messages,
        'pagination' => [
            'current_page' => $page,
            'total_pages'  => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération : ' . $e->getMessage()]);
}
?>