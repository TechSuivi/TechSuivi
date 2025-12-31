<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Fonction helper pour vérifier le début d'une chaîne
function stringsStartsWith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Helper function for Docker info (Moved from server_info.php)
function getDockerInfo() {
    $inContainer = file_exists('/.dockerenv') || file_exists('/run/.containerenv');
    $currentHostname = gethostname();
    $socketPath = '/var/run/docker.sock';
    $socketExists = file_exists($socketPath);
    $socketReadable = is_readable($socketPath);
    $socketWritable = is_writable($socketPath);

    if (!function_exists('shell_exec')) {
        return ['status' => 'exec_disabled', 'in_container' => $inContainer];
    }

    $dockerBin = '/usr/bin/docker';
    if (!file_exists($dockerBin)) $dockerBin = 'docker';
    $dockerCmd = "DOCKER_API_VERSION=1.41 $dockerBin"; // Force API version

    $version = shell_exec("$dockerCmd -v 2>&1");
    if (!$version || stripos($version, 'docker') === false || stripos($version, 'not found') !== false) {
        return [
            'status' => 'binary_missing',
            'in_container' => $inContainer,
            'socket_exists' => $socketExists
        ];
    }
    
    $cleanVersion = trim(str_ireplace('Docker version', '', $version));
    if (($comma = strpos($cleanVersion, ',')) !== false) $cleanVersion = substr($cleanVersion, 0, $comma);

    $statsCmd = "$dockerCmd info --format '{{.ServerVersion}}|{{.Containers}}|{{.ContainersRunning}}|{{.ContainersPaused}}|{{.ContainersStopped}}' 2>/dev/null";
    $statsOutput = shell_exec($statsCmd);
    
    if (!$statsOutput) {
        return [
            'status' => 'permission_denied',
            'version' => $cleanVersion,
            'in_container' => $inContainer,
            'socket_exists' => $socketExists,
            'socket_readable' => $socketReadable,
            'socket_writable' => $socketWritable,
            'user' => exec('whoami'),
            'groups' => exec('groups')
        ];
    }
    
    $parts = explode('|', trim($statsOutput));
    $stats = [
        'version' => $parts[0] ?? $cleanVersion,
        'total' => $parts[1] ?? 0,
        'running' => $parts[2] ?? 0,
        'paused' => $parts[3] ?? 0,
        'stopped' => $parts[4] ?? 0
    ];

    $psCmd = "$dockerCmd ps -a --format '{{.Names}}|{{.Image}}|{{.Status}}|{{.Ports}}' 2>/dev/null";
    $psOutput = shell_exec($psCmd);
    
    $statsData = [];
    $statsCmd = "$dockerCmd stats --no-stream --format '{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}' 2>/dev/null";
    $rawStats = shell_exec($statsCmd);
    if ($rawStats) {
        foreach (explode("\n", trim($rawStats)) as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $statsData[$parts[0]] = ['cpu' => $parts[1], 'mem' => $parts[2]];
            }
        }
    }

    $containers = [];
    if ($psOutput) {
        foreach (explode("\n", trim($psOutput)) as $line) {
             if (empty($line)) continue;
             $parts = explode('|', $line);
             if (count($parts) >= 4) {
                 $name = $parts[0];
                 $containers[] = [
                     'name' => $name,
                     'image' => $parts[1],
                     'status' => $parts[2],
                     'ports' => $parts[3],
                     'cpu' => $statsData[$name]['cpu'] ?? '--',
                     'mem' => $statsData[$name]['mem'] ?? '--'
                 ];
             }
        }
    }

    return [
        'status' => 'ok',
        'in_container' => $inContainer,
        'current_container' => $currentHostname,
        'stats' => $stats,
        'containers' => $containers
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$container = $_GET['container'] ?? $_POST['container_name'] ?? '';

// Sécurité : validation du nom du conteneur pour les actions qui en ont besoin
if (in_array($action, ['logs', 'start', 'stop', 'restart']) && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $container)) {
    echo json_encode(['error' => 'Nom de conteneur invalide']);
    exit;
}

$dockerBin = '/usr/bin/docker';
if (!file_exists($dockerBin)) $dockerBin = 'docker';
$dockerCmd = "DOCKER_API_VERSION=1.41 $dockerBin";


switch ($action) {
    case 'logs':
        $command = "$dockerCmd logs --tail 100 " . escapeshellarg($container) . " 2>&1";
        $logs = shell_exec($command);
        echo json_encode(['logs' => $logs ?: 'Aucun log disponible.']);
        break;

    case 'start':
    case 'stop':
    case 'restart':
        $cmd = "$dockerCmd $action " . escapeshellarg($container) . " 2>&1";
        $output = shell_exec($cmd);
        if ($output && stripos($output, $container) !== false) {
             echo json_encode(['success' => true, 'message' => "Action '$action' effectuée sur $container"]);
        } else {
             echo json_encode(['success' => false, 'message' => "Erreur : " . htmlspecialchars($output ?: 'Échec inconnu')]);
        }
        break;

    case 'render_info':
        // Generate the HTML for the main view
        $dockerInfo = getDockerInfo();
        ob_start(); // Capture HTML output
        
        if ($dockerInfo['status'] === 'ok'): ?>
            <div class="info-grid" style="margin-bottom: 20px;">
                <div class="info-card">
                    <h4>📊 Vue d'ensemble</h4>
                    <table class="info-table">
                        <tr><td><strong>Version :</strong></td><td><?= htmlspecialchars($dockerInfo['stats']['version']) ?></td></tr>
                        <tr><td><strong>Conteneurs :</strong></td><td><?= htmlspecialchars($dockerInfo['stats']['total']) ?></td></tr>
                        <tr><td><strong>En cours :</strong></td><td style="color: #28a745;">● <?= htmlspecialchars($dockerInfo['stats']['running']) ?></td></tr>
                        <tr><td><strong>Arrêtés :</strong></td><td style="color: #dc3545;">● <?= htmlspecialchars($dockerInfo['stats']['stopped']) ?></td></tr>
                    </table>
                </div>
                </div>

                <!-- CARTE GESTION RUSTDESK -->
                <div class="info-card">
                    <h4>🔐 Gestion Rustdesk</h4>
                    <p class="text-muted" style="font-size: 0.9em; margin-bottom: 15px;">
                        Sauvegardez ou restaurez l'identité (ID/Key) de votre serveur Rustdesk.
                    </p>
                    
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="ajax/rustdesk_keys.php?action=download_keys" target="_blank" class="btn-action" style="text-decoration: none; display: inline-flex; align-items: center; color: inherit;">
                            📥 Sauvegarder les clés (.zip)
                        </a>

                        <button class="btn-action" onclick="document.getElementById('rustdeskKeyInput').click()" style="display: inline-flex; align-items: center;">
                            📤 Restaurer les clés
                        </button>
                        
                        <input type="file" id="rustdeskKeyInput" multiple style="display: none;" onchange="uploadRustdeskKeys(this)">
                    </div>
                </div>
            </div>

            <?php if (!empty($dockerInfo['containers'])): ?>
                <div class="info-card" style="width: 100%; overflow-x: auto;">
                    <h4>🚀 Conteneurs Actifs</h4>
                    <table class="info-table" style="min-width: 600px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #dee2e6;">
                                <th style="text-align: left; padding: 10px;">Nom</th>
                                <th style="text-align: left; padding: 10px;">Image</th>
                                <th style="text-align: left; padding: 10px;">Status</th>
                                <th style="text-align: left; padding: 10px;">Ports</th>
                                <th style="text-align: left; padding: 10px;">CPU / RAM</th>
                                <th style="text-align: right; padding: 10px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dockerInfo['containers'] as $c): ?>
                                <?php 
                                    $isSelf = ($dockerInfo['in_container'] && strpos($c['name'], 'web') !== false);
                                    $isRunning = stringsStartsWith($c['status'], 'Up');
                                ?>
                                <tr class="<?= $isSelf ? 'is-self' : '' ?>">
                                    <td style="padding: 10px;">
                                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                                        <?php if ($isSelf): ?> <span class="badge-self">(Ce serveur)</span><?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;"><small><?= htmlspecialchars($c['image']) ?></small></td>
                                    <td style="padding: 10px;">
                                        <?php if ($isRunning): ?>
                                            <span style="color: #28a745; font-weight: bold;">● En cours</span>
                                            <br><small class="text-muted"><?= htmlspecialchars($c['status']) ?></small>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-weight: bold;">● Arrêté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;"><small><?= htmlspecialchars($c['ports']) ?></small></td>
                                    <td style="padding: 10px; font-family: monospace; font-size: 0.9em;">
                                        <?php if ($isRunning): ?>
                                            CPU: <?= htmlspecialchars($c['cpu']) ?><br>
                                            RAM: <?= htmlspecialchars($c['mem']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; text-align: right; white-space: nowrap;">
                                        <?php if ($isRunning): ?>
                                            <?php if (!$isSelf): ?>
                                            <button class="btn-action btn-stop" onclick="dockerAction('stop', '<?= htmlspecialchars($c['name']) ?>')" title="Arrêter">⏹️</button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-restart" onclick="dockerAction('restart', '<?= htmlspecialchars($c['name']) ?>', <?= $isSelf ? 1 : 0 ?>)" title="Redémarrer">🔄</button>
                                            <button class="btn-action btn-logs" onclick="showDockerLogs('<?= htmlspecialchars($c['name']) ?>')" title="Logs">📄</button>
                                        <?php else: ?>
                                            <?php if (!$isSelf): ?>
                                            <button class="btn-action btn-start" onclick="dockerAction('start', '<?= htmlspecialchars($c['name']) ?>')" title="Démarrer">▶️</button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-logs" onclick="showDockerLogs('<?= htmlspecialchars($c['name']) ?>')" title="Logs">📄</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Aucun conteneur trouvé.</div>
            <?php endif; ?>

        <?php elseif ($dockerInfo['status'] === 'permission_denied'): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Docker détecté mais inaccessible</strong><br>
                L'utilisateur <code><?= htmlspecialchars($dockerInfo['user']) ?></code> n'a pas les droits sur <code>/var/run/docker.sock</code>.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>❌ Docker non disponible</strong><br>
                Statut : <?= htmlspecialchars($dockerInfo['status']) ?>
            </div>
        <?php endif;

        $html = ob_get_clean();
        echo json_encode(['html' => $html]);
        break;

    default:
        echo json_encode(['error' => 'Action inconnue']);
        break;
}
?>
