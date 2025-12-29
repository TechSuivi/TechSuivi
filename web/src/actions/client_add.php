<?php
/**
 * Action AJAX pour ajouter un nouveau client
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
$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$adresse1 = trim($_POST['adresse1'] ?? '');
$adresse2 = trim($_POST['adresse2'] ?? '');
$cp = trim($_POST['cp'] ?? '');
$ville = trim($_POST['ville'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$portable = trim($_POST['portable'] ?? '');
$mail = trim($_POST['mail'] ?? '');

// Validation des champs obligatoires
$errors = [];
if (empty($nom)) {
    $errors[] = 'Le nom est obligatoire.';
}
if (empty($telephone) && empty($portable)) {
    $errors[] = 'Au moins un numéro de téléphone (fixe ou portable) est obligatoire.';
}
if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L\'adresse email n\'est pas valide.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $sql = "INSERT INTO clients (nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail) 
            VALUES (:nom, :prenom, :adresse1, :adresse2, :cp, :ville, :telephone, :portable, :mail)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':prenom', $prenom);
    $stmt->bindParam(':adresse1', $adresse1);
    $stmt->bindParam(':adresse2', $adresse2);
    $stmt->bindParam(':cp', $cp);
    $stmt->bindParam(':ville', $ville);
    $stmt->bindParam(':telephone', $telephone);
    $stmt->bindParam(':portable', $portable);
    $stmt->bindParam(':mail', $mail);

    if ($stmt->execute()) {
        $newClientId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Client ajouté avec succès !',
            'client_id' => $newClientId,
            'client' => [
                'ID' => $newClientId,
                'nom' => $nom,
                'prenom' => $prenom,
                'adresse1' => $adresse1,
                'adresse2' => $adresse2,
                'cp' => $cp,
                'ville' => $ville,
                'telephone' => $telephone,
                'portable' => $portable,
                'mail' => $mail
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du client.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
