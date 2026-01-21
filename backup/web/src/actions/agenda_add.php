<?php
/**
 * Action AJAX pour ajouter une nouvelle tâche à l'agenda
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

// Récupération et nettoyage des données du formulaire
$titre = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');
$date_planifiee = $_POST['date_planifiee'] ?? '';
$priorite = $_POST['priorite'] ?? 'normale';
$statut = $_POST['statut'] ?? 'planifie';
$couleur = $_POST['couleur'] ?? '#3498db';
$rappel_minutes = (int)($_POST['rappel_minutes'] ?? 0);
$id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
$utilisateur = $_SESSION['username']; // Utilisateur connecté

// Validation
$errors = [];

if (empty($titre)) {
    $errors[] = "Le titre est obligatoire.";
}

if (empty($date_planifiee)) {
    $errors[] = "La date planifiée est obligatoire.";
} elseif (strtotime($date_planifiee) === false) {
    $errors[] = "Format de date invalide.";
}

if (!in_array($priorite, ['basse', 'normale', 'haute', 'urgente'])) {
    $errors[] = "Priorité invalide.";
}

if (!in_array($statut, ['planifie', 'en_cours', 'termine', 'reporte', 'annule'])) {
    $errors[] = "Statut invalide.";
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
    $errors[] = "Couleur invalide.";
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO agenda (titre, description, date_planifiee, priorite, statut, utilisateur, couleur, rappel_minutes, id_client) 
        VALUES (:titre, :description, :date_planifiee, :priorite, :statut, :utilisateur, :couleur, :rappel_minutes, :id_client)
    ");
    
    $result = $stmt->execute([
        ':titre' => $titre,
        ':description' => $description,
        ':date_planifiee' => $date_planifiee,
        ':priorite' => $priorite,
        ':statut' => $statut,
        ':utilisateur' => $utilisateur,
        ':couleur' => $couleur,
        ':rappel_minutes' => $rappel_minutes,
        ':id_client' => $id_client
    ]);
    
    if ($result) {
        $newId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Tâche créée avec succès !',
            'id' => $newId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la tâche.']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
