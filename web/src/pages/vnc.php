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
                'host'      => $_SERVER['SERVER_NAME'], // IP du serveur Docker
                'port'      => 8085,             // Port public unique de noVNC
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
/* Utilitaires manquants pour la page VNC */
.p-20 { padding: 20px; }
.px-20 { padding-left: 20px; padding-right: 20px; }
.py-10 { padding-top: 10px; padding-bottom: 10px; }
.p-15 { padding: 15px; }
.mb-25 { margin-bottom: 25px; }
.mt-15 { margin-top: 15px; }
.gap-30 { gap: 30px; }
.gap-10 { gap: 10px; }
.gap-5 { gap: 5px; }
.rounded-4 { border-radius: 4px; }
.rounded-50 { border-radius: 50px; }
.text-white { color: white; }
.text-success { color: #10b981; }
.text-warning { color: #f59e0b; }
.bg-transparent { background-color: transparent; }
.bg-muted { background-color: #6c757d; }
.bg-black { background-color: #000; }
.opacity-70 { opacity: 0.7; }
.cursor-pointer { cursor: pointer; }
.no-underline { text-decoration: none; }
.flex-col { flex-direction: column; }
.justify-start { justify-content: flex-start; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.min-h-420 { min-height: 420px; }
.h-400 { height: 400px; }
.max-w-700 { max-width: 700px; }
.w-full { width: 100%; }
.max-w-full { max-width: 100%; }
.fixed { position: fixed; }
.absolute { position: absolute; }
.top-0 { top: 0; }
.left-0 { left: 0; }
.top-20 { top: 20px; }
.right-30 { right: 30px; }
.z-50 { z-index: 50; }
.bg-black-opacity { background: rgba(0,0,0,0.9); }
.w-90vw { width: 90vw; }
.h-90vh { height: 90vh; }
.select-none { user-select: none; }
.vnc-grid { display: grid; }
</style>

<div class="container w-full max-w-full px-20">
    <div class="page-header flex justify-between items-center text-white p-15 mb-25 rounded-4 shadow-sm bg-gradient-primary">
        <h1 class="m-0 text-xl font-normal flex items-center gap-10">
            <span>🖥️</span>
            VNC - Accès distant multiposte
        </h1>
        <div class="flex gap-10 items-center">
            <button type="button" class="layout-btn bg-transparent border-0 text-white cursor-pointer opacity-70 text-lg px-10 rounded-4" data-cols="2" title="2 colonnes">2</button>
            <button type="button" class="layout-btn bg-transparent border-0 text-white cursor-pointer opacity-70 text-lg px-10 rounded-4" data-cols="3" title="3 colonnes">3</button>
            <button type="button" class="layout-btn bg-transparent border-0 text-white cursor-pointer opacity-70 text-lg px-10 rounded-4" data-cols="4" title="4 colonnes">4</button>
        </div>

        <div class="flex items-center gap-10">
            <button 
                id="refreshVncBtn" 
                class="btn btn-success btn-sm flex items-center gap-5 transition-all text-sm"
            >
                🔄 Rafraîchir
            </button>
            <?php if ($tokensError): ?>
                <span class="text-warning text-xs font-bold">⚠️ <?= htmlspecialchars($tokensError) ?></span>
            <?php elseif ($tokensGenerated): ?>
                <span class="text-success text-xs font-bold">✓</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($vncMachines)): ?>
        <div class="card text-center p-30 border-dashed">
            <p class="text-lg text-muted m-0">
                ℹ️ Aucune connexion VNC configurée
            </p>
            <p class="text-sm text-muted mt-10">
                Pour ajouter une connexion VNC, renseignez les champs <code>ip_vnc</code> et <code>pass_vnc</code> dans la table <code>inter</code>.
            </p>
        </div>
    <?php else: ?>

    <div class="vnc-grid grid gap-30" style="grid-template-columns: repeat(auto-fit, minmax(650px, 1fr));">
        <?php foreach ($vncMachines as $machine): 
            $iframeId  = 'vncFrame_' . $machine['id'];
            $buttonId  = 'vncExpandBtn_' . $machine['id'];
            $label     = htmlspecialchars($machine['label'], ENT_QUOTES, 'UTF-8');
            // Calcul du port déterministe (Même algo que generate_vnc_tokens.php)
            $portHash = hexdec(substr(md5('inter_' . $machine['intervention_id']), -4));
            $vncPort = 10000 + ($portHash % 20000);
            
            $host      = htmlspecialchars($machine['host'], ENT_QUOTES, 'UTF-8');
            $password  = htmlspecialchars($machine['password'], ENT_QUOTES, 'UTF-8');

            $vncUrl = "http://{$host}:8085/vnc_lite.html?host={$host}&port={$vncPort}&password={$password}&autoconnect=true&scale=true";
        ?>
        <div class="card p-20 flex flex-col items-center justify-start min-h-420 border overflow-hidden">
            <h2 class="mt-0 mb-5 text-lg font-bold">
                <?= $label ?>
            </h2>
            <div class="mb-15">
                <a href="index.php?page=interventions_view&id=<?= $machine['intervention_id'] ?>" class="text-primary no-underline text-sm hover:underline">
                    🔗 Voir l'intervention
                </a>
            </div>

            <iframe
                id="<?= $iframeId ?>"
                class="w-full max-w-700 h-400 border-0 rounded-4 shadow-md bg-black transition-all"
                src="<?= $vncUrl ?>"
                allowfullscreen
            ></iframe>

            <button
                type="button"
                id="<?= $buttonId ?>"
                class="btn btn-primary rounded-50 mt-15 px-20 py-10"
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
            this.classList.add('bg-muted');
            
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
            overlay.className = 'fixed top-0 left-0 w-full h-full bg-black-opacity flex items-center justify-center z-50';

            // Bouton fermeture
            const closeBtn = document.createElement('div');
            closeBtn.className = 'absolute top-20 right-30 text-2xl text-white cursor-pointer select-none';
            closeBtn.textContent = '✖';

            // Iframe en grand (même URL que la miniature)
            const fullFrame = document.createElement('iframe');
            fullFrame.className = 'w-90vw h-90vh rounded-4 bg-black border-0 shadow-lg';
            fullFrame.src = miniFrame.src;
            fullFrame.setAttribute('allowfullscreen', 'true');

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
