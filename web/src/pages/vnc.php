<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Générer le fichier tokens automatiquement
$tokensGenerated = false;
$tokensError = null;

// Appeler le script de génération des tokens
$generateUrl = 'http://localhost/actions/generate_vnc_tokens.php';
$ch = curl_init($generateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    if ($result && isset($result['success']) && $result['success']) {
        $tokensGenerated = true;
    } else {
        $tokensError = $result['error'] ?? 'Erreur inconnue lors de la génération des tokens';
    }
}

// Récupérer les machines VNC depuis la base de données
$vncMachines = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT i.id, i.ip_vnc, i.pass_vnc, c.nom, c.prenom
            FROM inter i
            LEFT JOIN clients c ON i.id_client = c.ID
            WHERE i.ip_vnc IS NOT NULL 
            AND i.ip_vnc != '' 
            ORDER BY i.id ASC
        ");
        
        $entries = $stmt->fetchAll();
        
        foreach ($entries as $entry) {
            $ipVnc = trim($entry['ip_vnc']);
            
            // Parser le format IP:PORT ou IP seul
            if (strpos($ipVnc, ':') !== false) {
                list($ip, $port) = explode(':', $ipVnc, 2);
            } else {
                $ip = $ipVnc;
                $port = 5900;
            }
            
            // Construire le label avec les infos client
            $clientInfo = '';
            if (!empty($entry['nom']) || !empty($entry['prenom'])) {
                $clientInfo = trim(($entry['nom'] ?? '') . ' ' . ($entry['prenom'] ?? ''));
                $label = "Inter #{$entry['id']} - {$clientInfo}";
            } else {
                $label = "Intervention #{$entry['id']} - {$ip}";
            }
            
            $vncMachines[] = [
                'intervention_id' => $entry['id'],
                'id'        => 'inter_' . $entry['id'],
                'label'     => $label,
                'host'      => '192.168.10.248', // IP du serveur Docker
                'port'      => 8080,             // Port public unique de noVNC
                'token'     => 'inter_' . $entry['id'],
                'password'  => $entry['pass_vnc'] ?? '',
            ];
        }
    } catch (Exception $e) {
        $tokensError = 'Erreur lors de la récupération des machines VNC : ' . $e->getMessage();
    }
}
?>

<style>
.vnc-container {
    max-width: 95%; /* Utiliser quasi toute la largeur */
    margin: 0 auto;
}

.page-header {
    background: linear-gradient(135deg, #5e35b1 0%, #4527a0 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.vnc-grid {
    display: grid;
    /* Force 2 colonnes max en augmentant la taille mini de 450px à 600px+ */
    grid-template-columns: repeat(auto-fit, minmax(650px, 1fr));
    gap: 30px;
}

.vnc-content-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
    min-height: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

/* Miniature VNC */
.vnc-mini {
    width: 100%;
    max-width: 700px;
    height: 400px;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 0 10px rgba(0,0,0,0.4);
    background: #000;
}

/* Iframe plein écran dans l’overlay */
.vnc-full {
    width: 90vw;
    height: 90vh;
    border-radius: 8px;
    background: #000;
}

/* Overlay plein écran */
.vnc-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

/* Bouton fermeture */
.vnc-close {
    position: absolute;
    top: 20px;
    right: 30px;
    font-size: 2rem;
    color: #fff;
    cursor: pointer;
    user-select: none;
}
</style>

<div class="vnc-container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%; box-sizing: border-box;">
        <h1 style="margin: 0; font-size: 1.4em; font-weight: 400; display: flex; align-items: center; gap: 10px;">
            <span>🖥️</span>
            VNC - Accès distant multiposte
        </h1>
        <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="layout-btn" data-cols="2" title="2 colonnes" style="border:none; background:transparent; color:white; cursor:pointer; opacity:0.7; font-size:1.1em; padding: 2px 8px; border-radius: 4px;">2</button>
                <button type="button" class="layout-btn" data-cols="3" title="3 colonnes" style="border:none; background:transparent; color:white; cursor:pointer; opacity:0.7; font-size:1.1em; padding: 2px 8px; border-radius: 4px;">3</button>
                <button type="button" class="layout-btn" data-cols="4" title="4 colonnes" style="border:none; background:transparent; color:white; cursor:pointer; opacity:0.7; font-size:1.1em; padding: 2px 8px; border-radius: 4px;">4</button>
            </div>

            <button 
                id="refreshVncBtn" 
                style="padding: 8px 16px; border-radius: 8px; border: none; background: #4caf50; color: white; cursor: pointer; font-size: 0.9em; transition: all 0.2s;"
                onmouseover="this.style.background='#45a049'"
                onmouseout="this.style.background='#4caf50'"
            >
                🔄 Rafraîchir
            </button>
            <?php if ($tokensError): ?>
                <span style="color: #ff9800; font-size: 0.85em;">⚠️ <?= htmlspecialchars($tokensError) ?></span>
            <?php elseif ($tokensGenerated): ?>
                <span style="color: #4caf50; font-size: 0.85em;">✓</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($vncMachines)): ?>
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; text-align: center; border: 1px solid var(--border-color);">
            <p style="font-size: 1.1em; color: var(--text-muted); margin: 0;">
                ℹ️ Aucune connexion VNC configurée
            </p>
            <p style="font-size: 0.9em; color: var(--text-muted); margin-top: 10px;">
                Pour ajouter une connexion VNC, renseignez les champs <code>ip_vnc</code> et <code>pass_vnc</code> dans la table <code>inter</code>.
            </p>
        </div>
    <?php else: ?>

    <div class="vnc-grid">
        <?php foreach ($vncMachines as $machine): 
            $iframeId  = 'vncFrame_' . $machine['id'];
            $buttonId  = 'vncExpandBtn_' . $machine['id'];
            $label     = htmlspecialchars($machine['label'], ENT_QUOTES, 'UTF-8');
            // Calcul du port déterministe (Même algo que generate_vnc_tokens.php)
            $portHash = hexdec(substr(md5('inter_' . $machine['intervention_id']), -4));
            $vncPort = 10000 + ($portHash % 20000);
            
            $host      = htmlspecialchars($machine['host'], ENT_QUOTES, 'UTF-8');
            $password  = htmlspecialchars($machine['password'], ENT_QUOTES, 'UTF-8');

            // URL noVNC : On utilise le port dédié (qui pointe directement vers le bon VNC)
            // Plus besoin de token dans l'URL car le port EST le sélecteur
            // On charge le viewer JS depuis le serveur 8080 (fichiers statiques) mais on connecte le Websocket au port dédié
            // Note: vnc_lite.html permet "path" ou "port". Ici on hack l'URL pour pointer le websocket vers le bon port
            
            // vnc_lite.html?host=192.168.10.248&port=60XX
            // Utilisation de scale=true au lieu de resize=scale pour adapter l'image à l'iframe
            $vncUrl = "http://{$host}:8080/vnc_lite.html?host={$host}&port={$vncPort}&password={$password}&autoconnect=true&scale=true";
        ?>
        <div class="vnc-content-card" style="overflow: hidden;">
            <h2 style="margin-top: 0; margin-bottom: 5px; font-size: 1.1em;">
                <?= $label ?>
            </h2>
            <div style="margin-bottom: 15px;">
                <a href="index.php?page=interventions_view&id=<?= $machine['intervention_id'] ?>" style="color: #3498db; text-decoration: none; font-size: 0.9em;">
                    🔗 Voir l'intervention
                </a>
            </div>

            <iframe
                id="<?= $iframeId ?>"
                class="vnc-mini"
                src="<?= $vncUrl ?>"
                style="border: none;"
                allowfullscreen
            ></iframe>

            <button
                type="button"
                id="<?= $buttonId ?>"
                style="margin-top: 15px; padding: 8px 16px; border-radius: 999px; border: none; background: #5e35b1; color: #fff; cursor: pointer;"
            >
                🔍 Agrandir <?= $label ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Bouton de rafraîchissement
    const refreshBtn = document.getElementById('refreshVncBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = '⏳ Rafraîchissement...';
            this.style.background = '#9e9e9e';
            
            // Recharger la page pour regénérer les tokens
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }

    // Gestion du layout (nombre de colonnes)
    const grid = document.querySelector('.vnc-grid');
    const layoutBtns = document.querySelectorAll('.layout-btn');

    function setLayout(cols) {
        if (!grid) return;
        
        // Largeurs minimales adaptées pour permettre aux colonnes de s'afficher
        let minWidth = '450px';
        if (cols == 2) minWidth = '650px';
        if (cols == 3) minWidth = '400px'; 
        if (cols == 4) minWidth = '280px'; // Assez petit pour tenir x4 sur ~1200px

        grid.style.gridTemplateColumns = `repeat(auto-fit, minmax(${minWidth}, 1fr))`;

        // Update buttons style
        layoutBtns.forEach(btn => {
            if(btn.dataset.cols == cols) {
                btn.style.opacity = '1';
                btn.style.fontWeight = 'bold';
                btn.style.textDecoration = 'underline';
                btn.style.border = '1px solid rgba(255,255,255,0.5)';
            } else {
                btn.style.opacity = '0.7';
                btn.style.fontWeight = 'normal';
                btn.style.textDecoration = 'none';
                btn.style.border = 'none';
            }
        });
        
        localStorage.setItem('vncLayoutCols', cols);
    }

    layoutBtns.forEach(btn => {
        btn.addEventListener('click', () => setLayout(btn.dataset.cols));
    });

    // Charger la préférence ou défaut à 2
    const savedCols = localStorage.getItem('vncLayoutCols') || 2;
    setLayout(savedCols);


    // Pour chaque machine, on branche le bouton "Agrandir"
    <?php foreach ($vncMachines as $machine): 
        $iframeId  = 'vncFrame_' . $machine['id'];
        $buttonId  = 'vncExpandBtn_' . $machine['id'];
    ?>
    (function () {
        const miniFrame = document.getElementById('<?= $iframeId ?>');
        const expandBtn = document.getElementById('<?= $buttonId ?>');

        if (!miniFrame || !expandBtn) return;

        expandBtn.addEventListener('click', function () {
            // Crée l’overlay plein écran
            const overlay = document.createElement('div');
            overlay.className = 'vnc-overlay';

            // Bouton fermeture
            const closeBtn = document.createElement('div');
            closeBtn.className = 'vnc-close';
            closeBtn.textContent = '✖';

            // Iframe en grand (même URL que la miniature)
            const fullFrame = document.createElement('iframe');
            fullFrame.className = 'vnc-full';
            fullFrame.src = miniFrame.src;
            fullFrame.setAttribute('allowfullscreen', 'true');
            fullFrame.style.border = 'none';

            overlay.appendChild(closeBtn);
            overlay.appendChild(fullFrame);
            document.body.appendChild(overlay);

            // Fermer sur clic sur la croix
            closeBtn.addEventListener('click', function () {
                document.body.removeChild(overlay);
            });

            // Optionnel : fermer en cliquant dans le fond noir
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });
        });
    })();
    <?php endforeach; ?>
});
</script>
