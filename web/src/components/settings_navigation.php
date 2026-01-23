<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Déterminer l'onglet actif basé sur la page actuelle
function getActiveSettingsTab($currentPage) {
    $tabMapping = [
        // Onglet Gestion
        'helpdesk_categories' => 'gestion',
        'fournisseurs_list' => 'gestion',
        'moyens_paiement' => 'gestion',
        'statuts_list' => 'gestion',
        'print_generator' => 'gestion',
        
        // Onglet Système
        'catalog_import' => 'system',
        'photos_settings' => 'system',
        'timezone_settings' => 'system',
        
        // Onglet Utilisateurs
        'users_list' => 'users',
        'change_password' => 'users',
        
        // Onglet Sauvegarde
        'database_backup' => 'sauvegarde',
        'files_manager' => 'sauvegarde',
        'rustdesk_backup' => 'sauvegarde',
        
        // Onglet Configuration
        'intervention_sheet_config' => 'config',
        'cyber_pricing_config' => 'config',
        'stock_config' => 'config',
        'acadia_config' => 'config',
        'gemini_config' => 'config',
        'theme_config' => 'config',
        
        // Onglet Mail
        'mail_config' => 'mail',

        'oauth2_config' => 'mail',
        'scheduled_tasks' => 'mail',
        'reports_config' => 'mail',
        
        // Onglet Informations
        'server_info' => 'server',
        'docker_info' => 'server',
        'script_contributors' => 'server',
        
        // Onglet AutoIT
        'autoit_logiciels_list' => 'autoit',
        'autoit_commandes_list' => 'autoit',
        'autoit_nettoyage_list' => 'autoit',
        'autoit_personnalisation_list' => 'autoit',
        'autoit_installeur_list' => 'autoit',
        'api_keys_config' => 'autoit',
    ];
    
    return $tabMapping[$currentPage] ?? 'gestion';
}

// Obtenir l'onglet actif
$currentPage = $_GET['page'] ?? 'settings';
$activeTab = $_GET['tab'] ?? getActiveSettingsTab($currentPage);

// Configuration des onglets
$tabs = [
    'gestion' => [
        'title' => '📋 Gestion',
        'items' => [
            ['url' => 'index.php?page=helpdesk_categories', 'icon' => '📂', 'title' => 'Catégories Helpdesk'],
            ['url' => 'index.php?page=fournisseurs_list', 'icon' => '🏢', 'title' => 'Fournisseurs'],
            ['url' => 'index.php?page=moyens_paiement', 'icon' => '💳', 'title' => 'Moyens de Paiement'],
            ['url' => 'index.php?page=statuts_list', 'icon' => '🏷️', 'title' => 'Statuts d\'Intervention'],
            ['url' => 'index.php?page=print_generator', 'icon' => '📄', 'title' => 'Générateur de Feuilles'],
            ['url' => 'index.php?page=client_import', 'icon' => '👥', 'title' => 'Import Clients'],
        ]
    ],
    'config' => [
        'title' => '⚙️ Configuration',
        'items' => [
            ['url' => 'index.php?page=intervention_sheet_config', 'icon' => '📄', 'title' => 'Feuille d\'Intervention'],
            ['url' => 'index.php?page=cyber_pricing_config', 'icon' => '🖥️', 'title' => 'Tarifs Cyber'],
            ['url' => 'index.php?page=stock_config', 'icon' => '📦', 'title' => 'Configuration Stock'],
            ['url' => 'index.php?page=acadia_config', 'icon' => '🔧', 'title' => 'Configuration Acadia'],
            ['url' => 'index.php?page=gemini_config', 'icon' => '🧠', 'title' => 'Configuration Gemini'],
            ['url' => 'index.php?page=theme_config', 'icon' => '🎨', 'title' => 'Thèmes & Apparence'],
        ]
    ],
    'mail' => [
        'title' => '📧 Mail',
        'items' => [
            ['url' => 'index.php?page=mail_config', 'icon' => '📧', 'title' => 'Configuration SMTP'],
            ['url' => 'index.php?page=oauth2_config', 'icon' => '🔐', 'title' => 'OAuth2 / Auth Moderne'],
            ['url' => 'index.php?page=scheduled_tasks', 'icon' => '⏰', 'title' => 'Tâches Programmées'],
            ['url' => 'index.php?page=reports_config', 'icon' => '📊', 'title' => 'Gestion des Rapports'],
        ]
    ],
    'system' => [
        'title' => '🖥️ Système',
        'items' => [
            ['url' => 'index.php?page=catalog_import', 'icon' => '📦', 'title' => 'Import Catalogue Acadia'],
            ['url' => 'index.php?page=photos_settings', 'icon' => '📷', 'title' => 'Paramètres Photos'],
            ['url' => 'index.php?page=timezone_settings', 'icon' => '⏰', 'title' => 'Fuseau Horaire'],
        ]
    ],
    'users' => [
        'title' => '👥 Utilisateurs',
        'items' => [
            ['url' => 'index.php?page=users_list', 'icon' => '👤', 'title' => 'Gérer Utilisateurs'],
        ]
    ],
    'sauvegarde' => [
        'title' => '💾 Sauvegarde',
        'items' => [
            ['url' => 'index.php?page=database_backup', 'icon' => '💾', 'title' => 'Sauvegarde Base de Données'],
            ['url' => 'index.php?page=files_manager', 'icon' => '📁', 'title' => 'Gestion des Fichiers'],
            ['url' => 'index.php?page=rustdesk_backup', 'icon' => '🔐', 'title' => 'Sauvegarde Rustdesk'],
        ]
    ],
    'server' => [
        'title' => '🖥️ Informations',
        'active' => in_array($currentPage, ['server_info', 'docker_info', 'script_contributors']),
        'items' => [
            ['url' => 'index.php?page=server_info', 'icon' => '📊', 'title' => 'Informations Système'],
            ['url' => 'index.php?page=docker_info', 'icon' => '🐳', 'title' => 'Conteneurs Docker'],
            ['url' => 'index.php?page=script_contributors', 'icon' => '📈', 'title' => 'Versions'],
        ]
    ],
    'autoit' => [
        'title' => '🤖 AutoIT',
        'items' => [
            ['url' => 'index.php?page=autoit_logiciels_list', 'icon' => '💻', 'title' => 'Logiciels'],
            ['url' => 'index.php?page=autoit_commandes_list', 'icon' => '⚡', 'title' => 'Commandes'],
            ['url' => 'index.php?page=autoit_nettoyage_list', 'icon' => '🧹', 'title' => 'Nettoyage'],
            ['url' => 'index.php?page=autoit_personnalisation_list', 'icon' => '🎨', 'title' => 'Personnalisation OS'],
            ['url' => 'index.php?page=autoit_installeur_list', 'icon' => '📦', 'title' => 'Installeur'],
            ['url' => 'index.php?page=api_keys_config', 'icon' => '🔑', 'title' => 'Clé API'],
        ]
    ]
];
?>

<!-- Navigation persistante des paramètres -->
<div class="settings-persistent-nav">
    <!-- Fil d'Ariane -->
    <div class="settings-breadcrumb">
        <a href="index.php">🏠 Accueil</a>
        <span class="breadcrumb-separator">›</span>
        <a href="index.php?page=settings">⚙️ Paramètres</a>
        <?php if ($currentPage !== 'settings'): ?>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current"><?= $tabs[$activeTab]['title'] ?></span>
        <?php endif; ?>
    </div>

    <!-- Menu des onglets -->
    <div class="settings-tabs-nav">
        <div class="tab-buttons">
            <?php foreach ($tabs as $tabKey => $tabData): ?>
                <button class="tab-button <?= $activeTab === $tabKey ? 'active' : '' ?>" 
                        onclick="switchSettingsTab('<?= $tabKey ?>')">
                    <?= $tabData['title'] ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Menu de navigation rapide pour l'onglet actif -->
    <?php if (isset($tabs[$activeTab])): ?>
        <div class="quick-nav-menu">
            <h4>
                <?= $tabs[$activeTab]['title'] ?> - Navigation rapide
            </h4>
            <div class="quick-nav-buttons">
                <?php foreach ($tabs[$activeTab]['items'] as $item): ?>
                    <a href="<?= $item['url'] ?>" class="quick-nav-btn <?= strpos($item['url'], 'page=' . $currentPage) !== false ? 'active' : '' ?>">
                        <?= $item['icon'] ?> <?= $item['title'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>



<script>
function switchSettingsTab(tabName) {
    // Rediriger vers la page settings avec l'onglet spécifié
    window.location.href = 'index.php?page=settings&tab=' + tabName;
}
</script>