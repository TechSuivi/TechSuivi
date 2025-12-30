<?php
/**
 * Script d'initialisation de l'installeur TechSuivi
 * Ce script est exécuté au démarrage du conteneur Docker.
 * Il renomme installeur.exe avec le HEX de l'URL de l'application
 * et met à jour la table download dans la base de données.
 */

require_once __DIR__ . '/../config/database.php';

echo "--- Initialisation de l'installeur TechSuivi ---\n";

// 1. Déterminer l'URL de base
$appUrl = getenv('APP_URL');

if (empty($appUrl)) {
    echo "ATTENTION : La variable d'environnement APP_URL n'est pas définie.\n";
    echo "L'installeur ne pourra pas se configurer automatiquement sans l'URL de votre NAS.\n";
    echo "Veuillez définir APP_URL (ex: http://192.168.10.100) dans votre configuration Docker.\n";
    exit(0);
}

$appUrl = rtrim($appUrl, '/');
$installUrl = $appUrl . "/Download/Install/";
$hex = strtoupper(bin2hex($installUrl));
$newFileName = "installeur_" . $hex . ".exe";

$sourceFile = __DIR__ . '/../uploads/downloads/installeur.exe';
$destFile = __DIR__ . '/../uploads/downloads/' . $newFileName;
$dbUrl = '/uploads/downloads/' . $newFileName;

echo "URL de l'application : $appUrl\n";
echo "URL d'installation cible : $installUrl\n";
echo "HEX généré : $hex\n";
echo "Nouveau nom de fichier : $newFileName\n";

// 2. Renommer le fichier physique
if (file_exists($sourceFile)) {
    // Supprimer les anciens installeurs renommés pour éviter le désordre
    $files = glob(__DIR__ . '/../uploads/downloads/installeur_*.exe');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    
    if (copy($sourceFile, $destFile)) {
        echo "Fichier renommé avec succès : $newFileName\n";
    } else {
        echo "ERREUR : Impossible de copier le fichier vers $newFileName\n";
        exit(1);
    }
} else {
    // Si le fichier source n'existe pas, on vérifie s'il y a déjà un fichier renommé
    $existingFiles = glob(__DIR__ . '/../uploads/downloads/installeur_*.exe');
    if (!empty($existingFiles)) {
        $destFile = $existingFiles[0];
        $newFileName = basename($destFile);
        $dbUrl = '/uploads/downloads/' . $newFileName;
        echo "Utilisation de l'installeur existant : $newFileName\n";
    } else {
        echo "ERREUR : Fichier installeur.exe non trouvé dans uploads/downloads/\n";
        exit(0);
    }
}

// 3. Mettre à jour la base de données
$maxRetries = 20;
$retryCount = 0;
$pdo = null;

echo "Tentative de connexion à la base de données (max 100s)...\n";

while ($retryCount < $maxRetries) {
    try {
        $pdo = getDatabaseConnection();
        echo "✓ Connecté à la base de données.\n";
        break;
    } catch (Exception $e) {
        $retryCount++;
        echo "  (Tentative $retryCount/$maxRetries) La base de données n'est pas encore prête : " . $e->getMessage() . "\n";
        sleep(5);
    }
}

if (!$pdo) {
    echo "ERREUR : Impossible de se connecter à la base de données après $maxRetries tentatives. Abandon.\n";
    exit(1);
}

try {
    // Vérifier si l'entrée existe déjà
    $stmt = $pdo->prepare("SELECT ID FROM download WHERE NOM = 'Installeur TechSuivi' OR URL LIKE '/uploads/downloads/installeur_%'");
    $stmt->execute();
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE download SET NOM = 'Installeur TechSuivi', URL = :url, show_on_login = 1, DESCRIPTION = 'Logiciel d\'installation TechSuivi' WHERE ID = :id");
        $stmt->execute([':url' => $dbUrl, ':id' => $existing['ID']]);
        echo "✓ Entrée mise à jour dans la base de données (ID: {$existing['ID']})\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO download (NOM, DESCRIPTION, URL, show_on_login) VALUES ('Installeur TechSuivi', 'Logiciel d\'installation TechSuivi', :url, 1)");
        $stmt->execute([':url' => $dbUrl]);
        echo "✓ Nouvelle entrée créée dans la base de données.\n";
    }
    
} catch (Exception $e) {
    echo "ERREUR lors de la mise à jour DB : " . $e->getMessage() . "\n";
}

echo "Initialisation terminée.\n";
?>
