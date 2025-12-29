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
        
        // Onglet Configuration
        'intervention_sheet_config' => 'config',
        'cyber_pricing_config' => 'config',
        'acadia_config' => 'config',
        
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
            ['url' => 'index.php?page=acadia_config', 'icon' => '🔧', 'title' => 'Configuration Acadia'],
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
            <h4 style="margin: 0 0 10px 0; color: var(--accent-color);">
                <?= $tabs[$activeTab]['title'] ?> - Navigation rapide
            </h4>
            <div class="quick-nav-buttons">
                <?php foreach ($tabs[$activeTab]['items'] as $item): ?>
                    <a href="<?= $item['url'] ?>" class="quick-nav-btn">
                        <?= $item['icon'] ?> <?= $item['title'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Navigation persistante des paramètres */
.settings-persistent-nav {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Fil d'Ariane */
.settings-breadcrumb {
    background-color: transparent;
    padding: 0 0 15px 0;
    margin-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}

.settings-breadcrumb a {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 500;
}

.settings-breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb-separator {
    margin: 0 8px;
    color: #6c757d;
}

.breadcrumb-current {
    color: #6c757d;
    font-weight: 600;
}

/* Onglets de navigation */
.settings-tabs-nav {
    margin-bottom: 15px;
}

.tab-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.tab-button {
    background: #ffffff;
    border: 1px solid #dee2e6;
    padding: 8px 16px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-color);
    border-radius: 6px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.tab-button:hover {
    background-color: var(--hover-color);
    border-color: var(--accent-color);
}

.tab-button.active {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
    border-color: var(--accent-color);
    font-weight: bold;
}

/* Menu de navigation rapide */
.quick-nav-menu {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-top: 15px;
}

.quick-nav-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-nav-btn {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.quick-nav-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    text-decoration: none;
    color: white;
}

/* Styles pour le mode sombre */
body.dark .settings-persistent-nav {
    background-color: #2c2c2c;
    border-color: #444;
    box-shadow: 0 2px 4px rgba(255,255,255,0.05);
}

body.dark .settings-breadcrumb {
    border-bottom-color: #444;
}

body.dark .breadcrumb-current,
body.dark .breadcrumb-separator {
    color: #aaa;
}

body.dark .tab-button {
    background-color: #333;
    color: var(--text-color-dark);
    border-color: #555;
}

body.dark .tab-button:hover {
    background-color: #383838;
    border-color: var(--accent-color);
}

body.dark .quick-nav-menu {
    background-color: #333;
    border-color: #555;
}

body.dark .quick-nav-menu h4 {
    color: var(--accent-color) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-button {
        text-align: center;
    }
    
    .quick-nav-buttons {
        flex-direction: column;
    }
    
    .quick-nav-btn {
        justify-content: center;
    }
}
</style>

<script>
function switchSettingsTab(tabName) {
    // Rediriger vers la page settings avec l'onglet spécifié
    window.location.href = 'index.php?page=settings&tab=' + tabName;
}
</script>