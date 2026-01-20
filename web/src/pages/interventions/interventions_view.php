<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure les fonctions d'historique des statuts
require_once __DIR__ . '/../../utils/statuts_historique.php';

$message = '';
$intervention = null;
$intervention_id = trim($_GET['id'] ?? '');

// Fonction pour parser les données de nettoyage
function parseNettoyageData($nettoyageString) {
    if (empty($nettoyageString)) {
        return [];
    }
    
    $logiciels = [];
    $items = explode(';', $nettoyageString);
    
    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) continue;
        
        // Format: nomdulogiciel=1passé0nonpassé=information
        if (preg_match('/^([^=]+)=([01])=(.*)$/', $item, $matches)) {
            $nom = $matches[1];
            $passe = $matches[2] == '1';
            $info = $matches[3];
            
            $logiciels[] = [
                'nom' => $nom,
                'passe' => $passe,
                'info' => $info
            ];
        }
    }
    
    return $logiciels;
}

// Fonction pour formater le log
function formatLogContent($logContent) {
    if (empty($logContent)) return '';

    $lines = explode("\n", $logContent);
    $formattedHtml = '<div class="log-container">';
    
    $rawBuffer = [];

    // Helper closure to flush buffer
    $flushBuffer = function() use (&$formattedHtml, &$rawBuffer) {
        if (empty($rawBuffer)) return;

        $count = count($rawBuffer);
        if ($count > 5) {
            $formattedHtml .= '<details class="log-details">';
            $formattedHtml .= '<summary class="log-summary">Afficher ' . $count . ' lignes masquées...</summary>';
            $formattedHtml .= '<div class="log-details-content">';
            foreach ($rawBuffer as $rawLine) {
                $formattedHtml .= '<div class="log-raw">' . htmlspecialchars($rawLine) . '</div>';
            }
            $formattedHtml .= '</div>';
            $formattedHtml .= '</details>';
        } else {
            foreach ($rawBuffer as $rawLine) {
                $formattedHtml .= '<div class="log-raw">' . htmlspecialchars($rawLine) . '</div>';
            }
        }
        $rawBuffer = [];
    };

    // Helper to slugify categories/types for CSS classes
    $slugifyClass = function($string) {
        $string = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'ç', 'É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Î', 'Ï', 'Ô', 'Û', 'Ù', 'Ç'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c', 'e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'],
            $string
        );
        return strtolower(preg_replace('/[^a-z0-9]/i', '-', $string));
    };

    foreach ($lines as $line) {
        // Nettoyage agressif des espaces invisibles :
        // 1. Décodage des entités HTML (pour les &nbsp; et autres)
        $cleanLine = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 2. Suppression des codes ANSI (couleurs terminal, curseur, etc.)
        $cleanLine = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $cleanLine);

        // 3. Suppression via Regex :
        // \p{Z} : tous les séparateurs (espaces, insécables, etc.)
        // \p{C} : tous les caractères de contrôle
        // \x{2800} : Caractère "Braille Pattern Blank" souvent utilisé dans les barres de progression CLI
        $cleanLine = preg_replace('/[\p{Z}\p{C}\x{2800}]/u', '', $cleanLine);
        
        if (empty($cleanLine)) {
            continue; // Skip empty lines
        }

        // Check if line starts with [YYYY-MM-DD HH:MM:SS]
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.*)$/', $line, $matches)) {
            // Flush any pending raw lines before adding a new structured entry
            $flushBuffer();

            $date = $matches[1];
            $rest = $matches[2];

            $metadata = [];
            $message = $rest;

            // Extract all [Brackets] at the beginning
            while (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $message, $metaMatches)) {
                $metadata[] = $metaMatches[1];
                $message = $metaMatches[2];
            }

            $classes = [];
            if (isset($metadata[0])) {
                $classes[] = 'cat-' . $slugifyClass($metadata[0]);
            }
            if (isset($metadata[1])) {
                $classes[] = 'type-' . $slugifyClass($metadata[1]);
            }
            
            // Keyword highlighting
            if (stripos($message, 'erreur') !== false || stripos($message, 'échec') !== false || stripos($message, 'failed') !== false) {
                $classes[] = 'line-error';
            } elseif (stripos($message, 'success') !== false || stripos($message, 'réussie') !== false || stripos($message, 'terminée') !== false || stripos($message, ' OK') !== false) {
                $classes[] = 'line-success';
            } elseif (stripos($message, 'recherche') !== false || stripos($message, 'rechercher') !== false) {
                $classes[] = 'line-search';
            }

            $formattedHtml .= '<div class="log-entry ' . implode(' ', $classes) . '">';
            $formattedHtml .= '<span class="log-date">[' . $date . ']</span>';

            foreach ($metadata as $index => $meta) {
                $metaClass = ($index === 0) ? 'log-category' : 'log-type';
                $formattedHtml .= ' <span class="' . $metaClass . '">[' . htmlspecialchars($meta) . ']</span>';
            }
            
            $formattedHtml .= ' <span class="log-message">' . htmlspecialchars($message) . '</span>';
            $formattedHtml .= '</div>';
        } else {
            // Add to buffer
            $rawBuffer[] = $line;
        }
    }
    
    // Flush remaining buffer at the end
    $flushBuffer();

    $formattedHtml .= '</div>';
    return $formattedHtml;
}

if (empty($intervention_id)) {
    $message = '<p style="color: red;">ID d\'intervention manquant.</p>';
} else {
    if (isset($pdo)) {
        // Récupérer les données de l'intervention avec les informations du client
        try {
            // Vérifier si la table intervention_statuts existe
            $tableExists = false;
            try {
                $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
                $tableExists = true;
            } catch (PDOException $e) {
                // Table n'existe pas encore
            }
            
            // Vérifier si la colonne statuts_historique existe
            $hasHistoriqueColumn = false;
            try {
                $pdo->query("SELECT statuts_historique FROM inter LIMIT 1");
                $hasHistoriqueColumn = true;
            } catch (PDOException $e) {
                // Colonne n'existe pas encore
            }
            
            if ($tableExists && $hasHistoriqueColumn) {
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.statut_id,
                        i.statuts_historique,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail,
                        s.nom as statut_nom,
                        s.couleur as statut_couleur,
                        s.description as statut_description
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                    WHERE i.id = :id
                ");
            } else if ($tableExists) {
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.statut_id,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail,
                        s.nom as statut_nom,
                        s.couleur as statut_couleur,
                        s.description as statut_description
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                    WHERE i.id = :id
                ");
            } else {
                // Fallback sans les statuts
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    WHERE i.id = :id
                ");
            }
            $stmt->execute([':id' => $intervention_id]);
            $intervention = $stmt->fetch();
            
            if (!$intervention) {
                $message = '<p style="color: red;">Intervention non trouvée.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur lors de la récupération de l\'intervention : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $message = "<p style='color: red;'>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
    }
}
?>

<!-- Inline CSS Removed for Audit -->

<div class="page-header">
    <div class="header-title">
        <h1 class="m-0">🔍 Détail de l'intervention</h1>
        <?php if ($intervention): ?>
        <div class="header-subtitle">
            ID: <strong><?= htmlspecialchars($intervention['id']) ?></strong> • Créée le <?= date('d/m/Y à H:i', strtotime($intervention['date'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($intervention): ?>
    <div class="header-actions">
        <div class="flex gap-10">
            <?php if (!empty($intervention['statut_nom'])): ?>
                <span class="status-pill" style="background-color: <?= htmlspecialchars($intervention['statut_couleur']) ?>; color: white; padding: 6px 16px; font-weight: 600;">
                    <?= htmlspecialchars($intervention['statut_nom']) ?>
                </span>
            <?php else: ?>
                <span class="status-pill <?= $intervention['en_cours'] ? 'bg-success-light text-success' : 'bg-hover text-muted' ?>">
                    <?= $intervention['en_cours'] ? 'En cours' : 'Clôturée' ?>
                </span>
            <?php endif; ?>

            <?php if ($intervention['en_cours'] == 1): ?>
                <a href="index.php?page=interventions_edit&id=<?= htmlspecialchars($intervention['id']) ?>" class="btn btn-primary btn-sm px-15">
                    <span>✏️</span> Modifier
                </a>
                <a href="pwa/?intervention_id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" class="btn btn-success btn-sm">
                    <span>📷</span> Photos (Mobile)
                </a>
            <?php endif; ?>
        </div>

        <div class="flex gap-10">
            <button onclick="printIntervention('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-secondary btn-sm">
                <span>🖨️</span> Imprimer
            </button>
            <a href="actions/interventions_delete.php?id=<?= htmlspecialchars($intervention['id']) ?>" 
                onclick="return confirm('❗ Êtes-vous sûr de vouloir supprimer cette intervention ?\n\nCette action est irréversible.');" 
                class="btn btn-sm-action btn-danger-text btn-sm">
                <span>🗑️</span> Supprimer
            </a>
            <a href="index.php?page=interventions_list" class="btn btn-sm-action btn-sm">
                Fermer
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="intervention-detail">
    <?php echo $message; ?>

    <?php if ($intervention): ?>
        <div class="grid grid-2-col gap-20 mb-20">
            <!-- Coordonnées Client -->
            <div class="card h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>👤</span> Coordonnées Client
                    </h3>
                    <a href="index.php?page=clients_view&id=<?= $intervention['id_client'] ?>" class="btn btn-sm btn-ghost" title="Voir fiche client">👁️</a>
                </div>
                <div class="card-body">
                    <div class="flex flex-col gap-15">
                        <div class="info-group">
                            <label class="text-xs text-muted uppercase font-bold spacing-1">Nom complet</label>
                            <div class="text-lg font-semibold"><?= htmlspecialchars(($intervention['client_nom'] ?? '') . ' ' . ($intervention['client_prenom'] ?? '')) ?></div>
                        </div>

                        <div class="grid grid-2-col gap-15">
                            <div class="info-group">
                                <label class="text-xs text-muted uppercase font-bold spacing-1">Email</label>
                                <div>
                                    <?php if (!empty($intervention['mail'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($intervention['mail']) ?>" class="text-primary hover-underline"><?= htmlspecialchars($intervention['mail']) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted italic">Non renseigné</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-group">
                                <label class="text-xs text-muted uppercase font-bold spacing-1">Téléphone</label>
                                <div class="font-medium">
                                    <?php 
                                    $phones = array_filter([$intervention['telephone'] ?? '', $intervention['portable'] ?? '']);
                                    echo !empty($phones) ? htmlspecialchars(implode(' / ', $phones)) : '<span class="text-muted italic">Non renseigné</span>';
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="info-group">
                            <label class="text-xs text-muted uppercase font-bold spacing-1">Adresse</label>
                            <div class="text-sm">
                                <?= !empty($intervention['adresse1']) ? htmlspecialchars($intervention['adresse1']) . '<br>' : '' ?>
                                <?= !empty($intervention['adresse2']) ? htmlspecialchars($intervention['adresse2']) . '<br>' : '' ?>
                                <?= htmlspecialchars(($intervention['cp'] ?? '') . ' ' . ($intervention['ville'] ?? '')) ?>
                                <?php if (empty($intervention['adresse1']) && empty($intervention['cp'])): ?>
                                    <span class="text-muted italic">Adresse non renseignée</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des statuts -->
            <div class="card h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>📊</span> Historique des statuts
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (isset($intervention['statuts_historique']) && !empty($intervention['statuts_historique'])): ?>
                        <?php
                        $historique = getHistoriqueComplet($pdo, $intervention['statuts_historique']);
                        if (!empty($historique)):
                        ?>
                            <div class="max-h-300 overflow-y-auto">
                                <?php foreach ($historique as $index => $entry): ?>
                                    <div class="p-15 border-b border-hover flex items-center justify-between hover-bg-light transition-all" style="border-left: 4px solid <?= htmlspecialchars($entry['statut']['couleur']) ?>;">
                                        <div>
                                            <div class="font-semibold text-sm"><?= htmlspecialchars($entry['statut']['nom']) ?></div>
                                            <div class="text-xs text-muted mt-2">
                                                Durée : 
                                                <?php if ($index < count($historique) - 1): ?>
                                                    <?= calculerDureeStatut($entry['date_heure'], $historique[$index + 1]['date_heure']) ?>
                                                <?php else: ?>
                                                    <?= calculerDureeStatut($entry['date_heure']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs font-medium"><?= formatDateHistorique($entry['date_heure']) ?></div>
                                            <?php if ($index === 0): ?>
                                                <span class="badge bg-success text-white text-3xs mt-5">ACTUEL</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-40 text-center text-muted italic">Aucun historique disponible</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-40 text-center text-muted italic">Aucun historique de statut disponible</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($intervention['ip_vnc'])): 
            // Régénérer les tokens VNC pour s'assurer qu'ils sont à jour
            try {
                require_once __DIR__ . '/../../actions/generate_vnc_tokens.php';
            } catch (Exception $e) {
                // Silencieux sur l'erreur de génération pour ne pas casser l'affichage
                error_log("Erreur génération tokens VNC: " . $e->getMessage());
            }

            $vncHost = $_SERVER['SERVER_NAME']; // IP du serveur Docker
            
            // Calcul du port déterministe (Même algo que generate_vnc_tokens.php)
            $portHash = hexdec(substr(md5('inter_' . $intervention['id']), -4));
            $vncPort = 10000 + ($portHash % 20000);
            
            $vncPassword = $intervention['pass_vnc'] ?? '';
            
            // URL : Charge le viewer sur 8085, connecte le socket sur le port dédié
            $vncUrl = "http://{$vncHost}:8085/vnc_lite.html?host={$vncHost}&port={$vncPort}&password={$vncPassword}&autoconnect=true&scale=true";
        ?>
        <!-- Informations de l'intervention -->
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="m-0 text-lg flex items-center gap-10">
                    <span>📝</span> Informations de l'intervention
                </h3>
            </div>
            <div class="card-body">
                <div class="text-content text-base leading-relaxed bg-hover/30 p-15 rounded-8 min-h-100">
                    <?= nl2br(htmlspecialchars($intervention['info'] ?? '')) ?>
                </div>
            </div>
        </div>

        <div class="grid grid-2-col gap-20 mb-20">
            <!-- Accès VNC -->
            <div class="card flex flex-col h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>🖥️</span> Accès VNC
                        <span id="ping-indicator" class="ping-indicator" title="Statut de connexion PC (Ping)"></span>
                    </h3>
                    <div class="flex gap-5">
                        <a href="index.php?page=vnc_fullscreen&id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" class="btn btn-sm btn-ghost" title="Plein écran">↗️</a>
                    </div>
                </div>
                <div class="card-body flex flex-col gap-15">
                    <div class="vnc-frame-container bg-black rounded-8 overflow-hidden aspect-video border border-border">
                        <iframe 
                            src="<?= $vncUrl ?>" 
                            class="w-full h-full border-none"
                            allowfullscreen
                        ></iframe>
                    </div>

                    <div class="flex items-center justify-between">
                        <button onclick="disableVNC('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-sm btn-danger-outline">
                            🚫 Désactiver VNC
                        </button>
                        <div class="text-xs text-muted flex items-center gap-5">
                            🔒 Sécurisé via SSH Tunnel
                        </div>
                    </div>

                    <script>
                    (function() {
                        const pingIndicator = document.getElementById('ping-indicator');
                        const ipVnc = '<?= htmlspecialchars($intervention['ip_vnc'] ?? '') ?>';
                        
                        if (ipVnc) {
                            function checkPing() {
                                fetch(`api/ping.php?ip=${encodeURIComponent(ipVnc)}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            pingIndicator.classList.remove('ping-offline');
                                            pingIndicator.classList.add('ping-online');
                                            pingIndicator.title = "PC en ligne (Ping OK)";
                                        } else {
                                            pingIndicator.classList.remove('ping-online');
                                            pingIndicator.classList.add('ping-offline');
                                            pingIndicator.title = "PC injoignable (Ping KO)";
                                        }
                                    })
                                    .catch(err => {
                                        console.error('Ping check failed', err);
                                        pingIndicator.classList.remove('ping-online');
                                        pingIndicator.classList.add('ping-offline');
                                    });
                            }
                            checkPing();
                            setInterval(checkPing, 15000);
                        }
                    })();
                    </script>
                </div>
            </div>

            <!-- App Mobile / QR Code -->
            <div class="card flex flex-col h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>📱</span> App Mobile PWA
                    </h3>
                </div>
                <div class="card-body flex flex-col items-center justify-center text-center py-20">
                    <div class="qr-box bg-white p-15 rounded-12 shadow-sm mb-15">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode("http://" . $_SERVER['HTTP_HOST'] . "/pwa/?intervention_id=" . $intervention['id']) ?>" 
                             alt="QR Code PWA" 
                             class="block w-120 h-120" 
                             title="Scanner pour ouvrir dans l'application mobile">
                    </div>
                    <p class="text-sm font-semibold mb-5">QR Code PWA</p>
                    <p class="text-xs text-muted px-20">Scannez ce code pour accéder à l'intervention sur mobile et ajouter des photos.</p>
                </div>
            </div>
        </div>
        <div style="clear: both;"></div>

        <?php else: ?>
        <!-- Informations de l'intervention -->
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="m-0 text-lg flex items-center gap-10">
                    <span>📝</span> Informations de l'intervention
                </h3>
            </div>
            <div class="card-body">
                <div class="text-content text-base leading-relaxed bg-hover/30 p-15 rounded-8 min-h-100">
                    <?= nl2br(htmlspecialchars($intervention['info'] ?? '')) ?>
                </div>
            </div>
        </div>

        <div class="grid grid-2-col gap-20 mb-20">
            <!-- Configuration VNC -->
            <div class="card flex flex-col h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>🖥️</span> Configuration VNC
                    </h3>
                </div>
                <div class="card-body flex flex-col items-center justify-center text-center py-40">
                    <p class="text-muted mb-20">Le VNC n'est pas configuré pour cette intervention.</p>
                    <button onclick="showEnableVNCModal('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-success px-20">
                        ✅ Activer le VNC
                    </button>
                </div>
            </div>

            <!-- App Mobile / QR Code -->
            <div class="card flex flex-col h-full">
                <div class="card-header">
                    <h3 class="m-0 text-lg flex items-center gap-10">
                        <span>📱</span> App Mobile PWA
                    </h3>
                </div>
                <div class="card-body flex flex-col items-center justify-center text-center py-20">
                    <div class="qr-box bg-white p-15 rounded-12 shadow-sm mb-15">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode("http://" . $_SERVER['HTTP_HOST'] . "/pwa/?intervention_id=" . $intervention['id']) ?>" 
                             alt="QR Code PWA" 
                             class="block w-120 h-120" 
                             title="Scanner pour ouvrir dans l'application mobile">
                    </div>
                    <p class="text-sm font-semibold mb-5">QR Code PWA</p>
                    <p class="text-xs text-muted px-20">Scannez ce code pour accéder à l'intervention sur mobile et ajouter des photos.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($intervention['nettoyage'])): ?>
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="m-0 text-lg">🧼 Nettoyage</h3>
            </div>
            <div class="card-body">
                <?php
                $logiciels = parseNettoyageData($intervention['nettoyage']);
                if (!empty($logiciels)): ?>
                    <div class="grid grid-2-col gap-10">
                        <?php foreach ($logiciels as $logiciel): ?>
                            <div class="flex items-center gap-10 p-10 rounded-8 <?= $logiciel['passe'] ? 'bg-success-light/20 border-success/20' : 'bg-hover/20 border-border' ?> border">
                                <span class="badge <?= $logiciel['passe'] ? 'bg-success text-white' : 'bg-secondary text-muted' ?> text-3xs">
                                    <?= $logiciel['passe'] ? 'Passé' : 'Non passé' ?>
                                </span>
                                <span class="font-semibold text-sm flex-1"><?= htmlspecialchars($logiciel['nom']) ?></span>
                                <?php if (!empty($logiciel['info'])): ?>
                                    <span class="text-xs text-muted italic"><?= htmlspecialchars($logiciel['info']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-content whitespace-pre-wrap text-sm italic text-muted">
                        <?= htmlspecialchars($intervention['nettoyage']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($intervention['info_log'])): ?>
        <div class="card mb-20">
            <div class="card-header bg-dark text-white">
                <h3 class="m-0 text-lg">📝 Log d'informations</h3>
            </div>
            <div class="card-body bg-dark text-gray-300 p-0 overflow-hidden">
                <div class="max-h-500 overflow-y-auto font-mono text-xs">
                    <?= formatLogContent($intervention['info_log']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($intervention['note_user'])): ?>
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="m-0 text-lg">📌 Notes utilisateur</h3>
            </div>
            <div class="card-body">
                <div class="text-content whitespace-pre-wrap bg-warning-light/20 p-15 rounded-8 border border-warning/10 text-sm">
                    <?= htmlspecialchars($intervention['note_user']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section Photos -->
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="m-0 text-lg flex items-center gap-10">
                    <span>📷</span> Photos & Captures
                </h3>
            </div>
            <div class="card-body">
                <div id="photos-gallery" class="grid grid-4-col gap-15">
                    <!-- Les photos seront chargées ici via JavaScript -->
                </div>
                <div id="no-photos" class="py-40 text-center text-muted italic text-sm">
                    Aucune photo disponible pour cette intervention
                </div>
            </div>
        </div>

        <script>
        function printIntervention(id) {
            window.open('print_intervention.php?id=' + id, '_blank', 'width=1000,height=800');
        }
        </script>

    <?php else: ?>
        <div class="flex items-center justify-center mt-30 p-20 bg-hover/10 rounded-12 border border-border">
            <a href="index.php?page=interventions_list" class="btn btn-secondary px-20">
                <span>←</span> Retour à la liste des interventions
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal pour activer le VNC -->
<div id="enableVNCModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #3498db; margin-bottom: 20px;">✅ Activer le VNC</h2>
        <form id="enableVNCForm" onsubmit="return submitEnableVNC(event);">
            <input type="hidden" id="vnc_intervention_id" name="intervention_id">
            
            <div style="margin-bottom: 20px;">
                <label for="vnc_ip" style="display: block; margin-bottom: 5px; font-weight: 600;">Adresse IP VNC *</label>
                <input type="text" id="vnc_ip" name="ip_vnc" required 
                    placeholder="Exemple: 192.168.1.100"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--input-bg); color: var(--text-color); font-size: 1em;">
            </div>
            
            <div style="margin-bottom: 25px;">
                <label for="vnc_pass" style="display: block; margin-bottom: 5px; font-weight: 600;">Mot de passe VNC (optionnel)</label>
                <input type="text" id="vnc_pass" name="pass_vnc" 
                    placeholder="Laisser vide si pas de mot de passe"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--input-bg); color: var(--text-color); font-size: 1em;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEnableVNCModal()" class="btn btn-secondary" style="padding: 10px 20px;">
                    Annuler
                </button>
                <button type="submit" class="btn btn-success" style="padding: 10px 20px;">
                    ✅ Activer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Gestion des photos pour la page de visualisation
let currentInterventionId = '<?= htmlspecialchars($intervention_id) ?>';

// Charger les photos existantes
function loadPhotos() {
    if (!currentInterventionId) return;
    
    fetch(`api/photos.php?intervention_id=${encodeURIComponent(currentInterventionId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPhotos(data.data);
            } else {
                console.error('Erreur lors du chargement des photos:', data.message);
                showNoPhotos();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNoPhotos();
        });
}

// Afficher les photos dans la galerie
function displayPhotos(photos) {
    const gallery = document.getElementById('photos-gallery');
    const noPhotos = document.getElementById('no-photos');
    
    if (!photos || photos.length === 0) {
        showNoPhotos();
        return;
    }
    
    gallery.innerHTML = '';
    noPhotos.style.display = 'none';
    
    photos.forEach(photo => {
        const photoDiv = document.createElement('div');
        photoDiv.style.cssText = `
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background-color: var(--bg-color, white);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        `;
        
        // Effet hover
        photoDiv.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.2)';
        });
        
        photoDiv.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });
        
        photoDiv.innerHTML = `
            <div style="position: relative;">
                <img src="${photo.thumbnail_url}" alt="${photo.original_filename}"
                     style="width: 100%; height: 150px; object-fit: cover;"
                     onclick="openPhotoModal('${photo.url}', '${photo.original_filename}', '${photo.description || ''}', '${photo.uploaded_at}')">
                <button onclick="deletePhoto('${photo.id}', event)" 
                        style="position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s;"
                        onmouseover="this.style.transform='scale(1.1)'"
                        onmouseout="this.style.transform='scale(1)'"
                        title="Supprimer cette photo">
                    🗑️
                </button>
            </div>
            <div style="padding: 12px;">
                <p style="margin: 0; font-size: 13px; font-weight: 500; color: var(--text-color, #333); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${photo.original_filename}">
                    ${photo.original_filename}
                </p>
                ${photo.description ? `<p style="margin: 6px 0 0 0; font-size: 12px; color: var(--text-muted, #666); line-height: 1.3;">${photo.description}</p>` : ''}
                <p style="margin: 6px 0 0 0; font-size: 11px; color: var(--text-muted, #888);">
                    ${formatDate(photo.uploaded_at)}
                </p>
            </div>
        `;
        
        gallery.appendChild(photoDiv);
    });
}

// Supprimer une photo
function deletePhoto(photoId, event) {
    if (event) event.stopPropagation();
    
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette photo ?')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳';
    
    fetch(`api/photos.php?photo_id=${photoId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger les photos
            loadPhotos();
        } else {
            alert('Erreur: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression');
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}

// Afficher le message "aucune photo"
function showNoPhotos() {
    const gallery = document.getElementById('photos-gallery');
    const noPhotos = document.getElementById('no-photos');
    
    gallery.innerHTML = '';
    noPhotos.style.display = 'block';
}

// Formater la date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Modal pour afficher les photos en grand
function openPhotoModal(imageUrl, filename, description, uploadedAt) {
    // Créer le modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.9); z-index: 10000;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; animation: fadeIn 0.3s ease;
    `;
    
    // Ajouter l'animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: scale(0.8) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    modal.innerHTML = `
        <div style="max-width: 90%; max-height: 90%; position: relative; animation: slideIn 0.3s ease;" onclick="event.stopPropagation()">
            <img src="${imageUrl}" alt="${filename}"
                 style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
            <div style="position: absolute; bottom: -80px; left: 0; right: 0; text-align: center; color: white; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 20px 0 10px 0; border-radius: 0 0 8px 8px;">
                <p style="margin: 0; font-size: 16px; font-weight: 500;">${filename}</p>
                ${description ? `<p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; line-height: 1.4;">${description}</p>` : ''}
                <p style="margin: 8px 0 0 0; font-size: 12px; opacity: 0.7;">
                    Ajoutée le ${formatDate(uploadedAt)}
                </p>
            </div>
            <button onclick="this.parentElement.parentElement.remove(); document.head.removeChild(document.head.lastElementChild);"
                    style="position: absolute; top: -15px; right: -15px; background-color: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 18px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.3); transition: background-color 0.2s ease;"
                    onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 1)'"
                    onmouseout="this.style.backgroundColor='rgba(220, 53, 69, 0.9)'">×</button>
        </div>
    `;
    
    // Fermer en cliquant sur le fond
    modal.addEventListener('click', function() {
        modal.remove();
        document.head.removeChild(style);
    });
    
    // Fermer avec la touche Escape
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            modal.remove();
            document.head.removeChild(style);
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
    
    document.body.appendChild(modal);
}

// Fonction pour désactiver le VNC
function disableVNC(interventionId) {
    if (!confirm('Êtes-vous sûr de vouloir désactiver le VNC pour cette intervention ?\n\nCette action supprimera les informations de connexion VNC (ip_vnc et pass_vnc).')) {
        return;
    }
    
    // Afficher un loader ou désactiver le bouton
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Désactivation...';
    
    // Envoyer la requête à l'API
    fetch('api/vnc_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=disable_vnc&intervention_id=${encodeURIComponent(interventionId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ VNC désactivé avec succès');
            // Recharger la page pour mettre à jour l'affichage
            window.location.reload();
        } else {
            alert('❌ Erreur: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur lors de la désactivation du VNC');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

// === Fonctions pour activer le VNC ===
function showEnableVNCModal(interventionId) {
    document.getElementById('vnc_intervention_id').value = interventionId;
    document.getElementById('enableVNCModal').style.display = 'flex';
    // Focus sur le champ IP
    setTimeout(() => document.getElementById('vnc_ip').focus(), 100);
}

function closeEnableVNCModal() {
    document.getElementById('enableVNCModal').style.display = 'none';
    document.getElementById('enableVNCForm').reset();
}

function submitEnableVNC(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'enable_vnc');
    
    // Désactiver le bouton de soumission
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Activation...';
    
    fetch('api/vnc_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ VNC activé avec succès');
            closeEnableVNCModal();
            // Recharger la page pour afficher le VNC
            window.location.reload();
        } else {
            alert('❌ Erreur: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur lors de l\'activation du VNC');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
    
    return false;
}

// Fermer le modal en cliquant en dehors
document.getElementById('enableVNCModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEnableVNCModal();
    }
});

// Charger les photos au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    if (currentInterventionId) {
        loadPhotos();
    }
    
    // Auto-print support
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_print') === '1') {
        // Petit délai pour laisser charger la page
        setTimeout(() => {
            printIntervention(currentInterventionId);
        }, 500);
    }
});
</script>