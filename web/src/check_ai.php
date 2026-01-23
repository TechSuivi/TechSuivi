<?php
// web/check_ai.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Tenter de définir le chemin correct vers database.php
// Suppose que check_ai.php est à la racine de web/ et database.php dans src/config/
$dbPath = __DIR__ . '/src/config/database.php';

if (!file_exists($dbPath)) {
    // Essai alternatif si on est dans src/
    $dbPath = __DIR__ . '/config/database.php';
}

if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    die("❌ Impossible de trouver database.php. Chemin testé: $dbPath. Dossier actuel: " . __DIR__);
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'gemini_api_key'");
    $stmt->execute();
    $apiKey = $stmt->fetchColumn();

    if (!$apiKey) {
        die("❌ Clé API non trouvée dans la base de données.");
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . trim($apiKey);
    
    echo "<h1>Diagnostic Gemini API</h1>";
    echo "<p>Test de la clé API (lecture des modèles disponibles)...</p>";
    
    $json = @file_get_contents($url);
    
    if ($json === FALSE) {
        $error = error_get_last();
        echo "<h2 style='color:red'>Erreur Connexion:</h2>";
        echo "<pre>" . print_r($error, true) . "</pre>";
    } else {
        $data = json_decode($json, true);
        if (isset($data['models'])) {
            echo "<h2 style='color:green'>Succès ! Modèles disponibles :</h2>";
            echo "<ul>";
            foreach ($data['models'] as $model) {
                echo "<li><strong>" . htmlspecialchars($model['name']) . "</strong><br>";
                echo "Support: " . implode(", ", $model['supportedGenerationMethods']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<h2 style='color:orange'>Réponse inattendue :</h2>";
            echo "<pre>" . htmlspecialchars($json) . "</pre>";
        }
    }

} catch (Exception $e) {
    die("Exception: " . $e->getMessage());
}
