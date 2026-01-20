<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';

// Fonction pour formater la taille en octets
function formatBytes($size, $precision = 2) {
    if (!$size) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Fonction pour obtenir l'utilisation du disque
function getDiskUsage($path = '/') {
    $total = disk_total_space($path);
    $free = disk_free_space($path);
    $used = $total - $free;
    $percent = ($total > 0) ? ($used / $total) * 100 : 0;
    
    return [
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percent' => round($percent, 2)
    ];
}

// Fonction pour obtenir l'uptime du système (Linux uniquement)
function getSystemUptime() {
    if (file_exists('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $uptime = explode(' ', $uptime);
        $seconds = (int)$uptime[0];
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return sprintf('%d jours, %d heures, %d minutes', $days, $hours, $minutes);
    }
    return 'Non disponible';
}

// Fonction pour obtenir la charge système (Linux uniquement)
function getSystemLoad() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
    }
    return 'Non disponible';
}

$diskUsage = getDiskUsage();
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');
$maxFileUploads = ini_get('max_file_uploads');
$postMaxSize = ini_get('post_max_size');
$uploadMaxFilesize = ini_get('upload_max_filesize');

// Informations sur les auteurs et le script
$scriptInfo = [
    'name' => 'TechSuivi',
    'version' => '4.0',
    'description' => 'Système de gestion d\'interventions techniques',
    'authors' => [
        'Développeur Principal' => 'Votre Nom', // À personnaliser
        'Contributeurs' => 'Équipe TechSuivi'
    ],
    'technologies' => [
        'Backend' => 'PHP ' . PHP_VERSION,
        'Base de données' => 'MySQL/MariaDB',
        'Frontend' => 'HTML5, CSS3, JavaScript',
        'Serveur Web' => $_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté'
    ],
    'license' => 'Propriétaire',
    'created' => '2024',
    'last_update' => date('Y-m-d')
];
?>

<div class="container container-center max-w-1200">
    <div class="page-header">
        <h1 class="text-dark">🖥️ Informations Serveur</h1>
    </div>
    
    <!-- Informations sur le script -->
    <div class="card mb-30 p-20 bg-white border border-border shadow-sm">
        <h3 class="card-title text-primary mt-0 mb-20 text-xl border-b border-border pb-10">📋 Informations sur TechSuivi</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-20">
            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🏷️ Détails du Script</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 pr-10 text-dark">Nom :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['name']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Version :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['version']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Description :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['description']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Licence :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['license']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Créé en :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['created']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Mise à jour :</td><td class="text-muted"><?= htmlspecialchars($scriptInfo['last_update']) ?></td></tr>
                </table>
            </div>
            
            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">👥 Équipe de Dév</h4>
                <table class="w-full text-sm">
                    <?php foreach ($scriptInfo['authors'] as $role => $name): ?>
                        <tr><td class="font-bold py-5 pr-10 text-dark"><?= htmlspecialchars($role) ?> :</td><td class="text-muted"><?= htmlspecialchars($name) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🛠️ Technologies</h4>
                <table class="w-full text-sm">
                    <?php foreach ($scriptInfo['technologies'] as $tech => $version): ?>
                        <tr><td class="font-bold py-5 pr-10 text-dark"><?= htmlspecialchars($tech) ?> :</td><td class="text-muted"><?= htmlspecialchars($version) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Informations système -->
    <div class="card mb-30 p-20 bg-white border border-border shadow-sm">
        <h3 class="card-title text-primary mt-0 mb-20 text-xl border-b border-border pb-10">⚙️ Informations Système</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-20">
            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🖥️ Serveur</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-100 pr-10 text-dark">OS :</td><td class="text-muted"><?= php_uname('s') . ' ' . php_uname('r') ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Archi :</td><td class="text-muted"><?= php_uname('m') ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Hôte :</td><td class="text-muted"><?= php_uname('n') ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Serveur Web :</td><td class="text-muted"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">IP Serveur :</td><td class="text-muted"><?= $_SERVER['SERVER_ADDR'] ?? 'Non disponible' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Port :</td><td class="text-muted"><?= $_SERVER['SERVER_PORT'] ?? 'Non disponible' ?></td></tr>
                </table>
            </div>

            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🐘 PHP</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-100 pr-10 text-dark">Version PHP :</td><td class="text-muted"><?= PHP_VERSION ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">SAPI :</td><td class="text-muted"><?= php_sapi_name() ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Limite RAM :</td><td class="text-muted"><?= $memoryLimit ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Execution Max :</td><td class="text-muted"><?= $maxExecutionTime === '0' ? 'Illimité' : $maxExecutionTime . 's' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Uploads Max :</td><td class="text-muted"><?= $maxFileUploads ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">POST Max :</td><td class="text-muted"><?= $postMaxSize ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Upload Max :</td><td class="text-muted"><?= $uploadMaxFilesize ?></td></tr>
                </table>
            </div>

            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">💾 Stockage</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-100 pr-10 text-dark">Total :</td><td class="text-muted"><?= formatBytes($diskUsage['total']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Utilisé :</td><td class="text-muted"><?= formatBytes($diskUsage['used']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Libre :</td><td class="text-muted"><?= formatBytes($diskUsage['free']) ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Utilisation :</td><td class="text-muted">
                        <div class="w-full h-10 bg-white rounded overflow-hidden my-5 border border-border">
                            <div class="h-full bg-success transition-all" style="width: <?= $diskUsage['percent'] ?>%"></div>
                        </div>
                        <span class="text-xs"><?= $diskUsage['percent'] ?>%</span>
                    </td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Informations de performance -->
    <div class="card p-20 bg-white border border-border shadow-sm">
        <h3 class="card-title text-primary mt-0 mb-20 text-xl border-b border-border pb-10">📊 Performance Système</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-20">
            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">⏱️ Temps de fonctionnement</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-100 pr-10 text-dark">Uptime :</td><td class="text-muted"><?= getSystemUptime() ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Charge :</td><td class="text-muted"><?= getSystemLoad() ?></td></tr>
                </table>
            </div>

            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🔧 Extensions PHP</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-80 pr-10 text-dark">MySQL :</td><td class="text-muted"><?= extension_loaded('mysqli') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">PDO :</td><td class="text-muted"><?= extension_loaded('pdo') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">GD :</td><td class="text-muted"><?= extension_loaded('gd') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">cURL :</td><td class="text-muted"><?= extension_loaded('curl') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">JSON :</td><td class="text-muted"><?= extension_loaded('json') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">ZIP :</td><td class="text-muted"><?= extension_loaded('zip') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                </table>
            </div>

            <div class="card bg-secondary border border-border p-15 shadow-none">
                <h4 class="text-accent mt-0 mb-15 font-bold">🌐 Informations Réseau</h4>
                <table class="w-full text-sm">
                    <tr><td class="font-bold py-5 w-80 pr-10 text-dark">User Agent :</td><td class="text-muted text-xs break-all"><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Non disponible') ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">IP Client :</td><td class="text-muted"><?= $_SERVER['REMOTE_ADDR'] ?? 'Non disponible' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Méthode :</td><td class="text-muted"><?= $_SERVER['REQUEST_METHOD'] ?? 'Non disponible' ?></td></tr>
                    <tr><td class="font-bold py-5 pr-10 text-dark">Protocole :</td><td class="text-muted"><?= $_SERVER['SERVER_PROTOCOL'] ?? 'Non disponible' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>