<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Inclure la configuration centralisée de la base de données
// Ajuster le chemin si nécessaire selon l'emplacement du fichier
require_once __DIR__ . '/../config/database.php';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Récupérer la date de référence (optionnelle, sinon aujourd'hui)
    $currentDate = $_GET['date'] ?? date('Y-m-d');
    
    // Récupérer la dernière feuille de caisse précédant la date donnée
    // On veut récupérer les chèques de cette feuille
    $stmt = $pdo->prepare("
        SELECT cheques_details
        FROM FC_feuille_caisse
        WHERE date_comptage < ?
        ORDER BY date_comptage DESC
        LIMIT 1
    ");
    
    $stmt->execute([$currentDate]);
    $result = $stmt->fetch();
    
    $cheques = [];
    if ($result && !empty($result['cheques_details'])) {
        $cheques = json_decode($result['cheques_details'], true);
    }
    
    header('Content-Type: application/json');
    echo json_encode($cheques);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erreur API get_last_cheques: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>
