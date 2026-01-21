<?php
/**
 * Action AJAX pour ajouter une nouvelle intervention
 * Retourne un JSON avec le succès ou les erreurs
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé - Connexion requise.']);
    exit();
}

// Inclure la configuration centralisée de la base de données
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit();
}

// Fonction pour générer un ID hexadécimal basé sur la date et l'heure
function generateInterventionId() {
    $timestamp = time();
    return strtoupper(dechex($timestamp));
}

// Récupération et nettoyage des données du formulaire
$id_client = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
$date = trim($_POST['date'] ?? '');
$en_cours = isset($_POST['en_cours']) ? 1 : 0;
$info = trim($_POST['info'] ?? '');

// Validation des champs obligatoires
$errors = [];

if ($id_client <= 0) {
    $errors[] = 'Veuillez sélectionner un client valide.';
}
if (empty($date)) {
    $errors[] = 'La date est obligatoire.';
}
if (empty($info)) {
    $errors[] = 'Les informations sont obligatoires.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    // Vérifier que le client existe
    $checkSql = "SELECT ID FROM clients WHERE ID = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $id_client]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Client non trouvé.']);
        exit();
    }

    // Générer l'ID hexadécimal
    $intervention_id = generateInterventionId();
    
    // Insertion de l'intervention
    $sql = "INSERT INTO inter (id, id_client, date, en_cours, statut_id, info, nettoyage, info_log, note_user) 
            VALUES (:id, :id_client, :date, :en_cours, :statut_id, :info, '', '', '')";
    $stmt = $pdo->prepare($sql);
    
    $statut_id = 1; // Statut par défaut: 1 (en cours)
    
    $stmt->bindParam(':id', $intervention_id);
    $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':en_cours', $en_cours, PDO::PARAM_INT);
    $stmt->bindParam(':statut_id', $statut_id, PDO::PARAM_INT);
    $stmt->bindParam(':info', $info);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Intervention ajoutée avec succès !',
            'intervention_id' => $intervention_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout de l\'intervention.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
