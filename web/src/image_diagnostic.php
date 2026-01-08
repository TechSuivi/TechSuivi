<?php
// Script de diagnostic pour les images (image_diagnostic.php)

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnostic Images</h1>";
echo "<p>Serveur: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Utilisateur PHP: " . get_current_user() . " (UID: " . getmyuid() . ")</p>";
echo "<p>Utilisateur Processus: " . posix_getpwuid(posix_geteuid())['name'] . " (UID: " . posix_geteuid() . ")</p>";

$uploadDir = __DIR__ . '/uploads/interventions/';
echo "<h3>Dossier cible</h3>";
echo "Chemin: " . htmlspecialchars($uploadDir) . "<br>";

if (!is_dir($uploadDir)) {
    echo "<strong style='color:red'>ERREUR: Le dossier n'existe pas !</strong>";
    echo "<br>Contenu de " . dirname($uploadDir) . ":<br>";
    print_r(scandir(dirname($uploadDir)));
    exit;
}

echo "Permissions dossier: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";
echo "Propriétaire dossier: " . posix_getpwuid(fileowner($uploadDir))['name'] . "<br>";
echo "Groupe dossier: " . posix_getgrgid(filegroup($uploadDir))['name'] . "<br>";

$files = scandir($uploadDir);
$count = 0;

echo "<h2>Fichiers trouvés (20 premiers) :</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; text-align: left;'>";
echo "<tr style='background:#eee;'><th>Fichier</th><th>Taille</th><th>Perms</th><th>Owner</th><th>Test Visuel</th></tr>";

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    // Ignorer les fichiers non images pour le test visuel, sauf si on veut tout voir
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    // Limiter à 20 fichiers
    if ($count >= 20) break; 
    
    $path = $uploadDir . $file;
    $perms = substr(sprintf('%o', fileperms($path)), -4);
    $owner = posix_getpwuid(fileowner($path))['name'];
    
    // URL relative depuis la racine web (qui semble être src/)
    $url = 'uploads/interventions/' . $file;
    
    echo "<tr>";
    echo "<td style='padding:5px;'>" . htmlspecialchars($file) . "</td>";
    echo "<td style='padding:5px;'>" . filesize($path) . "</td>";
    echo "<td style='padding:5px;'>" . $perms . "</td>";
    echo "<td style='padding:5px;'>" . $owner . "</td>";
    
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        echo "<td style='padding:5px;'>
                <a href='$url' target='_blank'>Lien Direct</a><br>
                <img src='$url' style='height: 60px; border:1px solid #ccc; margin-top:5px;' alt='Erreur chargement'>
              </td>";
    } else {
        echo "<td style='padding:5px;'>$ext (Pas une image)</td>";
    }
    echo "</tr>";
    $count++;
}
echo "</table>";

if ($count === 0) {
    echo "<p>Aucun fichier trouvé dans le dossier.</p>";
}

echo "<h3>Information PHP</h3>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
?>
