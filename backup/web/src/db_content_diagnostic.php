<?php
// db_content_diagnostic.php
// Outil pour inspecter le contenu brut de la base de données (Hex Dump)
// Pour vérifier la présence de '0x0A' (Saut de ligne)

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<h1>Diagnostic Contenu BDD (Sauts de ligne)</h1>";
echo "<style>
    .hex { font-family: monospace; background: #f0f0f0; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; }
    .good { color: green; font-weight: bold; }
    .bad { color: red; font-weight: bold; }
</style>";

$table = $_GET['table'] ?? 'interventions';
$col = $_GET['col'] ?? 'description'; // ou 'log_information' ?
$id_col = $_GET['id_col'] ?? 'id';
$limit = 5;

echo "<form method='GET'>
    Table: <input type='text' name='table' value='" . htmlspecialchars($table) . "'>
    Colonne Texte: <input type='text' name='col' value='" . htmlspecialchars($col) . "'>
    Colonne ID: <input type='text' name='id_col' value='" . htmlspecialchars($id_col) . "'>
    <button type='submit'>Inspecter</button>
</form>";

try {
    $pdo = getDatabaseConnection();
    
    // Lister les tables existantes
    $tablesList = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables disponibles :</h3><p style='font-size:12px;color:#666'>" . implode(', ', $tablesList) . "</p>";

    // Récupérer les 5 dernières entrées qui ne sont pas vides
    $sql = "SELECT $id_col, $col FROM $table WHERE $col IS NOT NULL AND $col != '' ORDER BY $id_col DESC LIMIT $limit";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Analyse de la table `$table` (colonne `$col`)</h2>";
    
    if (empty($rows)) {
        echo "<p>Aucune donnée trouvée.</p>";
    } else {
        foreach ($rows as $row) {
            $id = $row[$id_col];
            $content = $row[$col];
            
            echo "<hr>";
            echo "<h3>ID: $id</h3>";
            echo "<h4>Contenu brut (visuel) :</h4>";
            echo "<div class='hex'>" . nl2br(htmlspecialchars($content)) . "</div>";
            
            echo "<h4>Analyse des sauts de ligne :</h4>";
            $hasNewline = strpos($content, "\n") !== false;
            $hasReturn = strpos($content, "\r") !== false;
            $hasEscapedN = strpos($content, "\\n") !== false;
            
            if ($hasNewline) echo "<div class='good'>✅ Contient de vrais sauts de ligne (\\n - ASCII 0x0A)</div>";
            else echo "<div class='bad'>❌ Aucun vrai saut de ligne trouvé</div>";
            
            if ($hasReturn) echo "<div>⚠️ Contient des retours chariot (\\r - ASCII 0x0D)</div>";
            if ($hasEscapedN) echo "<div>ℹ️ Contient des sauts de ligne littéraux '\\n' (Backslash + n)</div>";

            echo "<h4>Hex Dump (Partiel, max 500 chars) :</h4>";
            $hex = bin2hex(substr($content, 0, 500));
            // Surligner 0a (newline) en vert
            $hexFormatted = str_replace('0a', '<strong style="background:lightgreen">0a</strong>', $hex);
            echo "<div class='hex'>$hexFormatted</div>";
        }
    }

} catch (Exception $e) {
    echo "<p class='bad'>Erreur : " . $e->getMessage() . "</p>";
}
?>
