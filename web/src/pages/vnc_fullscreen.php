<?php
/**
 * Page VNC plein écran avec le nom du client dans le titre
 */

// Récupérer les paramètres
$interventionId = $_GET['id'] ?? null;

if (!$interventionId) {
    die('ID intervention requis');
}

// $pdo est déjà disponible depuis index.php

// Récupérer les informations de l'intervention et du client
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.id, i.ip_vnc, i.pass_vnc,
            c.nom AS client_nom, c.prenom AS client_prenom
        FROM inter i
        LEFT JOIN clients c ON i.id_client = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$interventionId]);
    $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$intervention) {
        die('Intervention non trouvée');
    }
    
    if (empty($intervention['ip_vnc'])) {
        die('VNC non configuré pour cette intervention');
    }
    
    // Construire le nom complet du client
    $clientName = trim(($intervention['client_nom'] ?? '') . ' ' . ($intervention['client_prenom'] ?? ''));
    if (empty($clientName)) {
        $clientName = 'Client #' . $intervention['id'];
    }
    
    // Construire l'URL VNC
    $vncHost = '192.168.10.248';
    // Calcul du port déterministe (Même algo que generate_vnc_tokens.php)
    $portHash = hexdec(substr(md5('inter_' . $intervention['id']), -4));
    $vncPort = 10000 + ($portHash % 20000);
    
    $vncPassword = $intervention['pass_vnc'] ?? '';
    // URL noVNC : On utilise vnc.html (version complète) pour avoir les menus
    $vncUrl = "http://{$vncHost}:8080/vnc.html?host={$vncHost}&port={$vncPort}&password={$vncPassword}&autoconnect=true&resize=scale";
    
    
} catch (PDOException $e) {
    die('Erreur de base de données');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($clientName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #1a1a2e;
        }
        
        .vnc-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(135deg, #5e35b1 0%, #4527a0 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .vnc-title {
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vnc-title .icon {
            font-size: 16px;
        }
        
        .vnc-actions {
            display: flex;
            gap: 10px;
        }
        
        .vnc-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .vnc-btn:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .vnc-frame {
            position: fixed;
            top: 40px;
            left: 0;
            right: 0;
            bottom: 0;
            border: none;
            width: 100%;
            height: calc(100% - 40px);
        }
        
        .fullscreen-mode .vnc-header {
            display: none;
        }
        
        .fullscreen-mode .vnc-frame {
            top: 0;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="vnc-header">
        <div class="vnc-title">
            <span class="icon">🖥️</span>
            <span>VNC - <strong><?= htmlspecialchars($clientName) ?></strong></span>
            <span style="opacity: 0.7; font-size: 12px;">(Intervention #<?= htmlspecialchars($intervention['id']) ?>)</span>
        </div>
        <div class="vnc-actions">
            <button class="vnc-btn" onclick="toggleFullscreen()" title="Basculer plein écran navigateur">
                <span>⛶</span> Plein écran
            </button>
            <a href="index.php?page=interventions_view&id=<?= htmlspecialchars($intervention['id']) ?>" class="vnc-btn" title="Retour à l'intervention">
                <span>←</span> Retour
            </a>
        </div>
    </div>
    
    <iframe 
        id="vncFrame"
        class="vnc-frame" 
        src="<?= htmlspecialchars($vncUrl) ?>"
        allowfullscreen
    ></iframe>
    
    <script>
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    document.body.classList.add('fullscreen-mode');
                }).catch(err => {
                    console.log('Erreur plein écran:', err);
                });
            } else {
                document.exitFullscreen().then(() => {
                    document.body.classList.remove('fullscreen-mode');
                });
            }
        }
        
        // Détecter sortie du plein écran
        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement) {
                document.body.classList.remove('fullscreen-mode');
            }
        });
        
        // Raccourci clavier F11 pour plein écran
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F11') {
                e.preventDefault();
                toggleFullscreen();
            }
        });
    </script>
</body>
</html>
