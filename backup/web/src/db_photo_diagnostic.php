<?php
// db_photo_diagnostic.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/permissions_helper.php';

echo "<h1>Diagnostic DB vs Fichiers</h1>";

$intervention_id = $_GET['id'] ?? '';
if (empty($intervention_id)) {
    die("Veuillez fournir un ID d'intervention dans l'URL (ex: ?id=695BD23B)");
}

echo "<h3>Intervention ID: " . htmlspecialchars($intervention_id) . "</h3>";

try {
    $pdo = getDatabaseConnection();
    
    // 1. Récupérer les photos en BDD
    $stmt = $pdo->prepare("SELECT * FROM intervention_photos WHERE intervention_id = ?");
    $stmt->execute([$intervention_id]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Enregistrements en Base de Données (" . count($photos) . ") :</h4>";
    
    if (empty($photos)) {
        echo "<p style='color:orange'>Aucune photo trouvée en base pour cet ID.</p>";
    } else {
        echo "<table border='1' cellspacing='0' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Filename (Attendu)</th><th>Original Name</th><th>Status Fichier</th></tr>";
        
        $uploadDir = __DIR__ . '/uploads/interventions/';
        
        foreach ($photos as $p) {
            $expectedPath = $uploadDir . $p['filename'];
            $exists = file_exists($expectedPath);
            
            echo "<tr>";
            echo "<td>" . $p['id'] . "</td>";
            echo "<td>" . htmlspecialchars($p['filename']) . "</td>";
            echo "<td>" . htmlspecialchars($p['original_filename']) . "</td>";
            
            if ($exists) {
                echo "<td style='color:green; font-weight:bold;'>TROUVÉ</td>";
            } else {
                echo "<td style='color:red; font-weight:bold;'>MANQUANT<br><small>$expectedPath</small></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Lister les fichiers sur le disque qui pourraient correspondre (recherche partielle)
    echo "<h4>Fichiers sur le disque (Recherche partielle '$intervention_id') :</h4>";
    $files = scandir($uploadDir);
    echo "<ul>";
    $found = false;
    foreach ($files as $f) {
        if ($f == '.' || $f == '..') continue;
        if (strpos($f, $intervention_id) !== false) {
            echo "<li>" . htmlspecialchars($f) . "</li>";
            $found = true;
        }
    }
    if (!$found) echo "<li>Aucun fichier trouvé contenant l'ID '$intervention_id'</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "Erreur BDD : " . $e->getMessage();
}
?>
