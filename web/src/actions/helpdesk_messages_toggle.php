<?php
// actions/helpdesk_messages_toggle.php

// Désactiver l'affichage des erreurs pour ne pas casser le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Configuration de la base de données
require_once __DIR__ . '/../config/database.php';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Vérifier les paramètres
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        throw new Exception("Paramètres manquants (id ou status)");
    }

    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];

    // Connexion DB
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    // Mise à jour du statut
    $stmt = $pdo->prepare("UPDATE helpdesk_msg SET FAIT = ? WHERE ID = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
