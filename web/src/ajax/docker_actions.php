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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-20 mb-20">
                <div class="card p-20 border border-border rounded shadow-sm bg-card">
                    <h4 class="mt-0 mb-15 text-lg font-bold text-dark border-b border-border pb-10">📊 Vue d'ensemble</h4>
                    <table class="w-full">
                        <tr class="border-b border-border"><td class="p-10 font-bold text-muted">Version :</td><td class="p-10 text-dark"><?= htmlspecialchars($dockerInfo['stats']['version']) ?></td></tr>
                        <tr class="border-b border-border"><td class="p-10 font-bold text-muted">Conteneurs :</td><td class="p-10 text-dark"><?= htmlspecialchars($dockerInfo['stats']['total']) ?></td></tr>
                        <tr class="border-b border-border"><td class="p-10 font-bold text-muted">En cours :</td><td class="p-10 text-success font-bold">● <?= htmlspecialchars($dockerInfo['stats']['running']) ?></td></tr>
                        <tr><td class="p-10 font-bold text-muted">Arrêtés :</td><td class="p-10 text-danger font-bold">● <?= htmlspecialchars($dockerInfo['stats']['stopped']) ?></td></tr>
                    </table>
                </div>

                <!-- CARTE GESTION RUSTDESK -->
                <div class="card p-20 border border-border rounded shadow-sm bg-card">
                    <h4 class="mt-0 mb-15 text-lg font-bold text-dark border-b border-border pb-10">🔐 Gestion Rustdesk</h4>
                    <p class="text-muted text-sm mb-15">
                        Sauvegardez ou restaurez l'identité (ID/Key) de votre serveur Rustdesk.
                    </p>
                    
                    <div class="flex flex-wrap gap-10">
                        <a href="ajax/rustdesk_keys.php?action=download_keys" target="_blank" class="btn btn-primary text-white no-underline flex items-center gap-5">
                            📥 Sauvegarder les clés (.zip)
                        </a>

                        <button class="btn btn-secondary flex items-center gap-5" onclick="document.getElementById('rustdeskKeyInput').click()">
                            📤 Restaurer les clés
                        </button>
                        
                        <input type="file" id="rustdeskKeyInput" multiple class="hidden" onchange="uploadRustdeskKeys(this)">
                    </div>
                </div>
            </div>

            <?php if (!empty($dockerInfo['containers'])): ?>
                <div class="card p-0 overflow-hidden border border-border rounded shadow-sm bg-card w-full overflow-x-auto">
                    <div class="p-15 border-b border-border bg-light">
                        <h4 class="m-0 text-lg font-bold text-dark">🚀 Conteneurs Actifs</h4>
                    </div>
                    <table class="w-full min-w-600 border-collapse">
                        <thead>
                            <tr class="bg-light border-b border-border">
                                <th class="text-left p-10 font-bold text-muted text-xs uppercase">Nom</th>
                                <th class="text-left p-10 font-bold text-muted text-xs uppercase">Image</th>
                                <th class="text-left p-10 font-bold text-muted text-xs uppercase">Status</th>
                                <th class="text-left p-10 font-bold text-muted text-xs uppercase">Ports</th>
                                <th class="text-left p-10 font-bold text-muted text-xs uppercase">CPU / RAM</th>
                                <th class="text-right p-10 font-bold text-muted text-xs uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dockerInfo['containers'] as $c): ?>
                                <?php 
                                    $isSelf = ($dockerInfo['in_container'] && strpos($c['name'], 'web') !== false);
                                    $isRunning = stringsStartsWith($c['status'], 'Up');
                                ?>
                                <tr class="border-b border-border hover:bg-hover transition-colors <?= $isSelf ? 'bg-soft-blue' : '' ?>">
                                    <td class="p-10 text-dark">
                                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                                        <?php if ($isSelf): ?> <span class="badge bg-info text-white text-xs ml-5">(Ce serveur)</span><?php endif; ?>
                                    </td>
                                    <td class="p-10 text-muted text-sm"><?= htmlspecialchars($c['image']) ?></td>
                                    <td class="p-10">
                                        <?php if ($isRunning): ?>
                                            <span class="text-success font-bold">● En cours</span>
                                            <br><small class="text-muted"><?= htmlspecialchars($c['status']) ?></small>
                                        <?php else: ?>
                                            <span class="text-danger font-bold">● Arrêté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-10 text-muted text-xs"><?= htmlspecialchars($c['ports']) ?></td>
                                    <td class="p-10 font-mono text-sm text-dark">
                                        <?php if ($isRunning): ?>
                                            CPU: <?= htmlspecialchars($c['cpu']) ?><br>
                                            RAM: <?= htmlspecialchars($c['mem']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-10 text-right whitespace-nowrap flex justify-end gap-5">
                                        <?php if ($isRunning): ?>
                                            <?php if (!$isSelf): ?>
                                            <button class="btn-sm-action text-danger border-danger hover:bg-danger hover:text-white" onclick="dockerAction('stop', '<?= htmlspecialchars($c['name']) ?>')" title="Arrêter">⏹️</button>
                                            <?php endif; ?>
                                            <button class="btn-sm-action text-orange border-orange hover:bg-orange hover:text-white" onclick="dockerAction('restart', '<?= htmlspecialchars($c['name']) ?>', <?= $isSelf ? 1 : 0 ?>)" title="Redémarrer">🔄</button>
                                            <button class="btn-sm-action text-info border-info hover:bg-info hover:text-white" onclick="showDockerLogs('<?= htmlspecialchars($c['name']) ?>')" title="Logs">📄</button>
                                        <?php else: ?>
                                            <?php if (!$isSelf): ?>
                                            <button class="btn-sm-action text-success border-success hover:bg-success hover:text-white" onclick="dockerAction('start', '<?= htmlspecialchars($c['name']) ?>')" title="Démarrer">▶️</button>
                                            <?php endif; ?>
                                            <button class="btn-sm-action text-info border-info hover:bg-info hover:text-white" onclick="showDockerLogs('<?= htmlspecialchars($c['name']) ?>')" title="Logs">📄</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info flex items-center gap-10">
                    <span class="text-lg">ℹ️</span> Aucun conteneur trouvé.
                </div>
            <?php endif; ?>

        <?php elseif ($dockerInfo['status'] === 'permission_denied'): ?>
            <div class="alert alert-warning flex items-center gap-10">
                <span class="text-lg">⚠️</span>
                <div>
                    <strong>Docker détecté mais inaccessible</strong><br>
                    L'utilisateur <code><?= htmlspecialchars($dockerInfo['user']) ?></code> n'a pas les droits sur <code>/var/run/docker.sock</code>.
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger flex items-center gap-10">
                <span class="text-lg">❌</span>
                <div>
                    <strong>Docker non disponible</strong><br>
                    Statut : <?= htmlspecialchars($dockerInfo['status']) ?>
                </div>
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
