<?php
/**
 * Génération automatique du fichier tokens pour websockify
 * à partir des entrées VNC de la table inter
 */

// Inclure la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('Content-Type: application/json');
}

try {
    $pdo = getDatabaseConnection();
    
    // Récupérer les entrées avec des infos VNC
    $stmt = $pdo->query("
        SELECT id, ip_vnc, pass_vnc 
        FROM inter 
        WHERE ip_vnc IS NOT NULL 
        AND ip_vnc != '' 
        ORDER BY id ASC
    ");
    
    $entries = $stmt->fetchAll();
    
    // Dossier des tokens
    $tokensDir = __DIR__ . '/../vnc_tokens';
    
    // S'assurer que le dossier existe
    if (!is_dir($tokensDir)) {
        mkdir($tokensDir, 0777, true);
    }
    
    // Nettoyer les anciens tokens (optionnel, mais propre)
    $files = glob($tokensDir . '/inter_*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    
    $tokensContent = [];
    $tokensCount = 0;
    
    foreach ($entries as $entry) {
        $ipVnc = trim($entry['ip_vnc']);
        
        // Parser le format IP:PORT ou IP seul
        if (strpos($ipVnc, ':') !== false) {
            $vncTarget = $ipVnc;
        } else {
            $vncTarget = $ipVnc . ':5900';
        }
        
        // Calcul d'un port unique et stable basé sur l'ID
        // On prend les 4 derniers caractères hex du MD5 de l'ID, convertis en décimal
        // Modulo 20000 + 10000 => Port entre 10000 et 30000
        $portHash = hexdec(substr(md5('inter_' . $entry['id']), -4));
        $listenPort = 10000 + ($portHash % 20000);
        
        // Format pour le script bash : "PORT_ECOUTE: CIBLE_IP:CIBLE_PORT"
        // Exemple : "12345: 192.168.1.10:5900"
        $tokensContent[] = "{$listenPort}: {$vncTarget}";
        $tokensCount++;
    }
    
    // Générer uniquement le fichier tokens.txt
    // Format: inter_ID: IP:PORT
    // Ce format est lu par entrypoint.sh pour ouvrir les ports (6000 + ID)
    $tokensPath = $tokensDir . '/tokens.txt';
    $finalContent = implode("\n", $tokensContent);
    
    if (file_put_contents($tokensPath, $finalContent . "\n") === false) {
        throw new Exception("Erreur d'écriture dans $tokensPath");
    }
    chmod($tokensPath, 0666);
    
    // Redémarrage du conteneur (optionnel si script dynamique, mais ici nécessaire pour ouvrir nouveaux ports)
    // exec('docker restart novnc > /dev/null 2>&1 &');
    
    if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
        echo json_encode([
            'success' => true,
            'message' => "Fichier tokens généré ({$tokensCount} entrées). Ports: 6000+ID",
            'count' => $tokensCount
        ]);
    }
    
} catch (Exception $e) {

    if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    } else {
        // Si inclus, on log juste l'erreur
        error_log("Erreur génération tokens VNC: " . $e->getMessage());
    }
}
