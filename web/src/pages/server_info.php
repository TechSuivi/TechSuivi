<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';

// Fonction pour formater la taille en octets
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}



// Fonction pour obtenir l'utilisation du disque
function getDiskUsage($path = '/') {
    $total = disk_total_space($path);
    $free = disk_free_space($path);
    $used = $total - $free;
    $percent = ($used / $total) * 100;
    
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

<div class="container">
    <h2>🖥️ Informations Serveur</h2>
    
    <!-- Informations sur le script -->
    <div class="info-section">
        <h3>📋 Informations sur TechSuivi</h3>
        <div class="info-grid">
            <div class="info-card">
                <h4>🏷️ Détails du Script</h4>
                <table class="info-table">
                    <tr><td><strong>Nom :</strong></td><td><?= htmlspecialchars($scriptInfo['name']) ?></td></tr>
                    <tr><td><strong>Version :</strong></td><td><?= htmlspecialchars($scriptInfo['version']) ?></td></tr>
                    <tr><td><strong>Description :</strong></td><td><?= htmlspecialchars($scriptInfo['description']) ?></td></tr>
                    <tr><td><strong>Licence :</strong></td><td><?= htmlspecialchars($scriptInfo['license']) ?></td></tr>
                    <tr><td><strong>Créé en :</strong></td><td><?= htmlspecialchars($scriptInfo['created']) ?></td></tr>
                    <tr><td><strong>Dernière mise à jour :</strong></td><td><?= htmlspecialchars($scriptInfo['last_update']) ?></td></tr>
                </table>
            </div>
            
            <div class="info-card">
                <h4>👥 Équipe de Développement</h4>
                <table class="info-table">
                    <?php foreach ($scriptInfo['authors'] as $role => $name): ?>
                        <tr><td><strong><?= htmlspecialchars($role) ?> :</strong></td><td><?= htmlspecialchars($name) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="info-card">
                <h4>🛠️ Technologies Utilisées</h4>
                <table class="info-table">
                    <?php foreach ($scriptInfo['technologies'] as $tech => $version): ?>
                        <tr><td><strong><?= htmlspecialchars($tech) ?> :</strong></td><td><?= htmlspecialchars($version) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Informations système -->
    <div class="info-section">
        <h3>⚙️ Informations Système</h3>
        <div class="info-grid">
            <div class="info-card">
                <h4>🖥️ Serveur</h4>
                <table class="info-table">
                    <tr><td><strong>Système d'exploitation :</strong></td><td><?= php_uname('s') . ' ' . php_uname('r') ?></td></tr>
                    <tr><td><strong>Architecture :</strong></td><td><?= php_uname('m') ?></td></tr>
                    <tr><td><strong>Nom d'hôte :</strong></td><td><?= php_uname('n') ?></td></tr>
                    <tr><td><strong>Serveur Web :</strong></td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté' ?></td></tr>
                    <tr><td><strong>Adresse IP Serveur :</strong></td><td><?= $_SERVER['SERVER_ADDR'] ?? 'Non disponible' ?></td></tr>
                    <tr><td><strong>Port :</strong></td><td><?= $_SERVER['SERVER_PORT'] ?? 'Non disponible' ?></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h4>🐘 PHP</h4>
                <table class="info-table">
                    <tr><td><strong>Version PHP :</strong></td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td><strong>SAPI :</strong></td><td><?= php_sapi_name() ?></td></tr>
                    <tr><td><strong>Limite mémoire :</strong></td><td><?= $memoryLimit ?></td></tr>
                    <tr><td><strong>Temps d'exécution max :</strong></td><td><?= $maxExecutionTime === '0' ? 'Illimité' : $maxExecutionTime . 's' ?></td></tr>
                    <tr><td><strong>Uploads max :</strong></td><td><?= $maxFileUploads ?></td></tr>
                    <tr><td><strong>Taille POST max :</strong></td><td><?= $postMaxSize ?></td></tr>
                    <tr><td><strong>Taille upload max :</strong></td><td><?= $uploadMaxFilesize ?></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h4>💾 Stockage</h4>
                <table class="info-table">
                    <tr><td><strong>Espace total :</strong></td><td><?= formatBytes($diskUsage['total']) ?></td></tr>
                    <tr><td><strong>Espace utilisé :</strong></td><td><?= formatBytes($diskUsage['used']) ?></td></tr>
                    <tr><td><strong>Espace libre :</strong></td><td><?= formatBytes($diskUsage['free']) ?></td></tr>
                    <tr><td><strong>Utilisation :</strong></td><td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $diskUsage['percent'] ?>%"></div>
                        </div>
                        <?= $diskUsage['percent'] ?>%
                    </td></tr>
                </table>
            </div>
        </div>
    </div>



    <!-- Informations de performance -->
    <div class="info-section">
        <h3>📊 Performance Système</h3>
        <div class="info-grid">
            <div class="info-card">
                <h4>⏱️ Temps de fonctionnement</h4>
                <table class="info-table">
                    <tr><td><strong>Uptime système :</strong></td><td><?= getSystemUptime() ?></td></tr>
                    <tr><td><strong>Charge système :</strong></td><td><?= getSystemLoad() ?></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h4>🔧 Extensions PHP Importantes</h4>
                <table class="info-table">
                    <tr><td><strong>MySQL :</strong></td><td><?= extension_loaded('mysqli') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td><strong>PDO :</strong></td><td><?= extension_loaded('pdo') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td><strong>GD :</strong></td><td><?= extension_loaded('gd') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td><strong>cURL :</strong></td><td><?= extension_loaded('curl') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td><strong>JSON :</strong></td><td><?= extension_loaded('json') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                    <tr><td><strong>ZIP :</strong></td><td><?= extension_loaded('zip') ? '✅ Activé' : '❌ Désactivé' ?></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h4>🌐 Informations Réseau</h4>
                <table class="info-table">
                    <tr><td><strong>User Agent :</strong></td><td style="word-break: break-all; font-size: 12px;"><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Non disponible') ?></td></tr>
                    <tr><td><strong>IP Client :</strong></td><td><?= $_SERVER['REMOTE_ADDR'] ?? 'Non disponible' ?></td></tr>
                    <tr><td><strong>Méthode HTTP :</strong></td><td><?= $_SERVER['REQUEST_METHOD'] ?? 'Non disponible' ?></td></tr>
                    <tr><td><strong>Protocole :</strong></td><td><?= $_SERVER['SERVER_PROTOCOL'] ?? 'Non disponible' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.info-section {
    margin-bottom: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
}

.info-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: var(--accent-color);
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.info-card {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.info-card h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--accent-color);
    font-size: 16px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.info-table td:first-child {
    width: 40%;
    padding-right: 15px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 5px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
    transition: width 0.3s ease;
}

/* Mode sombre */
body.dark .info-section {
    background-color: #2c2c2c;
    border-color: #444;
}

body.dark .info-card {
    background-color: #333;
    border-color: #555;
    color: var(--text-color-dark);
}

body.dark .info-table td {
    border-bottom-color: #444;
}

body.dark .progress-bar {
    background-color: #444;
}

/* Responsive */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-table td:first-child {
        width: 50%;
    }
}






</style>