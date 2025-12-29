<?php
/**
 * Configuration centralisée de la base de données
 * Lit les variables d'environnement depuis le fichier .env
 */

// Définir le fuseau horaire par défaut (Europe/Paris pour la France)
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Paris');
}

// Fonction pour lire le fichier .env
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Le fichier .env n'existe pas : $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Séparer la clé et la valeur
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    return $env;
}

// Charger les variables d'environnement
// Essayer plusieurs chemins possibles pour le fichier .env
$possiblePaths = [
   __DIR__ . '/../.env',           // Depuis config/
  //  __DIR__ . '/../../.env',           // Depuis config/
 //   __DIR__ . '/../../../.env',        // Depuis web/src/config/
 //   '/var/www/.env',                   // Chemin Docker
 //   '/TechSuivi/.env'                  // Chemin absolu
];

$env = [];
$envLoaded = false;

foreach ($possiblePaths as $envPath) {
    if (file_exists($envPath)) {
        try {
            $env = loadEnv($envPath);
            $envLoaded = true;
            break;
        } catch (Exception $e) {
            continue;
        }
    }
}



// Si aucun fichier .env n'est trouvé, arrêter avec une erreur claire
if (!$envLoaded) {
    throw new Exception("❌ Fichier .env introuvable !

Chemins vérifiés :
- " . implode("\n- ", $possiblePaths) . "

Le fichier .env est OBLIGATOIRE et doit contenir :
DB_HOST=db
DB_NAME=TechSuivi
DB_USER=monuser
DB_PASS=motdepasseuser");
}

// Vérifier que toutes les variables requises sont présentes
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($requiredVars as $var) {
    if (!isset($env[$var]) || empty($env[$var])) {
        throw new Exception("❌ Variable $var manquante dans le fichier .env !");
    }
}

// Configuration de la base de données EXCLUSIVEMENT depuis le fichier .env
$host = $env['DB_HOST'];   // OBLIGATOIRE depuis .env
$dbName = $env['DB_NAME']; // OBLIGATOIRE depuis .env
$dbUser = $env['DB_USER']; // OBLIGATOIRE depuis .env
$dbPass = $env['DB_PASS']; // OBLIGATOIRE depuis .env

// Fonction pour créer une connexion PDO
function getDatabaseConnection() {
    global $host, $dbName, $dbUser, $dbPass;
    
    try {
        $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Configurer le fuseau horaire MySQL pour correspondre au fuseau horaire PHP
        $timezone = date_default_timezone_get();
        $offset = date('P'); // Format +02:00
        $pdo->exec("SET time_zone = '$offset'");
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("
        ❌ Erreur de connexion à la base de données !
        
        Détails : " . $e->getMessage() . "
        
        Vérifiez :
        1. Que Docker est démarré : docker compose ps
        2. Que la base de données est accessible
        3. Que les paramètres dans .env sont corrects
        4. Que le conteneur de base de données est actif
        ");
    }
}

// Variables globales pour la compatibilité avec le code existant
// Ces variables seront disponibles dans tous les fichiers qui incluent ce fichier
?>