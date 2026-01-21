<?php
session_start();

// Vérifier si une installation est en cours
if (file_exists(__DIR__ . '/install_in_progress.lock')) {
    header('Location: installing.php');
    exit();
}

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);

// Inclure la configuration centralisée de la base de données
require_once 'config/database.php';

$pdo = null; // Initialize $pdo to null
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    // For the main page, it's critical to handle this gracefully.
    // We'll store the error message and display it in the content area.
    $dbConnectionError = "Échec de la connexion à la base de données : " . $e->getMessage();
}

$page = $_GET['page'] ?? 'dashboard'; // Default page is dashboard

// --- AJAX Bypass ---
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] === '1') {
    // Liste blanche des pages supportant l'AJAX search
    $ajaxPages = ['clients']; 
    
    if (in_array($page, $ajaxPages)) {
        if ($page === 'clients') {
            if ($pdo) {
                // Define constant to pass security check in clients.php
                if (!defined('TECHSUIVI_INCLUDED')) {
                    define('TECHSUIVI_INCLUDED', true);
                }
                
                // Inclure seulement le fichier (qui contient déjà le exit après le JSON)
                require_once 'pages/clients/clients.php';
                exit; // Sécurité supplémentaire
            } else {
                 header('Content-Type: application/json');
                 echo json_encode(['error' => 'Erreur de connexion DB']);
                 exit;
            }
        }
    }
}
// -------------------

// Traitement spécial pour les actions de sauvegarde AVANT tout HTML
if ($page === 'database_backup' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    // Vérifier si c'est un téléchargement direct
    if (isset($_POST['backup_destination']) && $_POST['backup_destination'] === 'download') {
        // Transférer toutes les données POST vers le script de sauvegarde direct
        $_SESSION['backup_post_data'] = $_POST;
        
        // Inclure et exécuter le script de sauvegarde direct
        require_once 'actions/database_backup.php';
        exit(); // Important : arrêter l'exécution après le téléchargement
    } else {
        // Pour la sauvegarde serveur, utiliser l'action normale
        require_once 'actions/database_backup.php';
        // Rediriger après traitement pour éviter la resoumission
        header('Location: index.php?page=database_backup');
        exit();
    }
}

// Fonction pour déterminer si un élément de menu est actif
function isMenuActive($menuPage, $currentPage) {
    // Cas spéciaux pour les groupes de pages
    $pageGroups = [
        'interventions' => ['interventions_list', 'interventions_add', 'interventions_view', 'interventions_edit', 'interventions_print', 'statuts_list'],
        'clients' => ['clients', 'add_client', 'edit_client', 'clients_view'],
        'agenda' => ['agenda_list', 'agenda_add', 'agenda_edit'],
        'downloads' => ['downloads_list', 'downloads_add', 'downloads_edit'],
        'liens' => ['liens_list', 'liens_add', 'liens_edit'],
        'gestion_caisse' => ['dashboard_caisse', 'feuille_caisse_add', 'feuille_caisse_list', 'feuille_caisse_view', 'moyens_paiement', 'resume_journalier', 'tableau_recapitulatif'],
        'cyber' => ['cyber_add', 'cyber_list', 'cyber_credits_list', 'cyber_credits_add', 'cyber_credits_history'],
        'transactions' => ['transaction_add', 'transactions_list'],
        'stock' => ['stock_list', 'orders_list', 'stock_add', 'inventory_list', 'inventory_view'],
        'messages' => ['messages', 'helpdesk_categories'],
        'settings' => ['settings', 'server_info', 'docker_info', 'script_contributors', 'intervention_sheet_config', 'cyber_pricing_config', 'acadia_config', 'api_keys_config', 'mail_config', 'oauth2_config', 'scheduled_tasks', 'reports_config', 'downloads_add', 'downloads_edit', 'liens_add', 'liens_edit', 'helpdesk_categories', 'fournisseurs_list', 'moyens_paiement', 'statuts_list', 'print_generator', 'catalog_import', 'photos_settings', 'timezone_settings', 'user_add', 'users_list', 'change_password', 'database_backup', 'files_manager', 'autoit_logiciels_list', 'autoit_commandes_list', 'autoit_nettoyage_list', 'autoit_personnalisation_list', 'autoit_installeur_list', 'client_import', 'gemini_config']
    ];
    
    // Vérifier si la page actuelle correspond directement
    if ($menuPage === $currentPage) {
        return true;
    }
    
    // Vérifier si la page actuelle fait partie d'un groupe
    foreach ($pageGroups as $group => $pages) {
        if ($menuPage === $group && in_array($currentPage, $pages)) {
            return true;
        }
    }
    
    return false;
}

// Liste blanche des pages autorisées pour éviter les attaques LFI
$allowedPages = [
    'dashboard', 'interventions_list', 'interventions_add', 'interventions_view',
    'interventions_edit', 'interventions_print', 'clients', 'add_client', 'edit_client', 'clients_view',
    'downloads_list', 'downloads_add', 'downloads_edit', 'liens_list',
    'liens_add', 'liens_edit', 'helpdesk_categories', 'messages', 'notes_list',
    'liens_add', 'liens_edit', 'helpdesk_categories', 'messages',
    'stock_list', 'orders_list', 'orders_edit', 'stock_add', 'inventory_list', 'inventory_view', 'fournisseurs_list', 'catalog_import',
    'dashboard_caisse', 'moyens_paiement', 'cyber_list', 'cyber_add',
    'cyber_edit', 'cyber_credits_list', 'cyber_credits_add', 'cyber_credits_history', 'transactions_list', 'transaction_add', 'transaction_edit',
    'feuille_caisse_add', 'feuille_caisse_list', 'feuille_caisse_view',
    'feuille_caisse_delete', 'resume_journalier',
    'tableau_recapitulatif', 'photos_settings', 'settings', 'server_info', 'docker_info', 'script_contributors', 'intervention_sheet_config', 'cyber_pricing_config', 'acadia_config', 'api_keys_config', 'mail_config', 'oauth2_config', 'scheduled_tasks', 'reports_config',
    'users_list', 'user_add', 'change_password', 'database_backup', 'files_manager',
    'statuts_list', 'timezone_settings', 'autoit_logiciels_list',
    'autoit_commandes_list', 'autoit_nettoyage_list', 'autoit_personnalisation_list', 'autoit_installeur_list',
    'print_generator', 'agenda_list', 'agenda_add', 'agenda_edit', 'vnc', 'vnc_fullscreen', 'client_import', 'rustdesk_backup', 
    'gemini_config', 'mail_ai_assistant', 'theme_config'
];

// Validation sécurisée du paramètre page
if (!in_array($page, $allowedPages)) {
    // Log de la tentative d'accès non autorisée
    error_log("SÉCURITÉ: Tentative d'accès à une page non autorisée: " .
              htmlspecialchars($_GET['page'] ?? 'undefined') .
              " depuis l'IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Rediriger vers le dashboard par défaut
    $page = 'dashboard';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>TechSuivi - Tableau de bord</title>
    <?php
    // Récupérer le thème actif
    $currentTheme = 'default';
    try {
        if ($pdo) {
             $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'app_theme'");
             $stmt->execute();
             $res = $stmt->fetch(PDO::FETCH_ASSOC);
             if ($res) $currentTheme = $res['config_value'];
        }
    } catch (Exception $e) {}
    
    // Sécurité : valider que le thème existe, sinon fallback
    if (!is_dir("css/themes/$currentTheme")) {
        $currentTheme = 'default';
    }
    ?>
    <link rel="stylesheet" href="css/themes/<?= htmlspecialchars($currentTheme) ?>/theme.css">
    <link rel="stylesheet" href="css/style.css?v=1.3">
    <link rel="stylesheet" href="css/modals.css">
    <link rel="stylesheet" href="css/awesomplete.css?v=1.1"> <!-- Autocomplete CSS -->
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script src="js/awesomplete.min.js?v=1.1"></script> <!-- Autocomplete JS -->
</head>
<body>
    <script>
        // Check theme immediately to prevent flash
        (function() {
            var savedTheme = localStorage.getItem('theme') || 'dark';
            document.body.classList.add(savedTheme);
        })();
    </script>
    <div class="sidebar">
        <ul class="menu">
            <li class="menu-item">
                <a href="index.php" <?php echo ($page === 'dashboard') ? 'class="active"' : ''; ?>>🏠 Accueil</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=interventions_list" <?php echo isMenuActive('interventions', $page) ? 'class="active"' : ''; ?>>🔧 Interventions</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=clients" <?php echo isMenuActive('clients', $page) ? 'class="active"' : ''; ?>>👥 Clients</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=agenda_list" <?php echo isMenuActive('agenda', $page) ? 'class="active"' : ''; ?>>📅 Agenda</a>
                <ul class="submenu">
                    <li><a href="index.php?page=agenda_list" <?php echo ($page === 'agenda_list') ? 'class="active"' : ''; ?>>📋 Liste des tâches</a></li>
                    <li><a href="index.php?page=agenda_add" <?php echo ($page === 'agenda_add') ? 'class="active"' : ''; ?>>➕ Nouvelle tâche</a></li>
                </ul>
            </li>
            <li class="menu-item">
                <a href="index.php?page=messages&category=all" id="messages-main-link" <?php echo isMenuActive('messages', $page) ? 'class="active"' : ''; ?>>💬 Messages</a>
                <ul class="submenu" id="messages-submenu">
                    <!-- Les catégories seront chargées dynamiquement -->
                </ul>
            </li>
            <li class="menu-item">
                <a href="index.php?page=notes_list" <?php echo ($page === 'notes_list') ? 'class="active"' : ''; ?>>📓 Notes</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=downloads_list" <?php echo isMenuActive('downloads', $page) ? 'class="active"' : ''; ?>>📥 Téléchargements</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=liens_list" <?php echo isMenuActive('liens', $page) ? 'class="active"' : ''; ?>>🔗 Liens</a>
            </li>
            <li class="menu-item">
                <a href="index.php?page=dashboard_caisse" <?php echo isMenuActive('gestion_caisse', $page) ? 'class="active"' : ''; ?>>📋 Gestion Caisse</a>
                <ul class="submenu">
                    <li><a href="index.php?page=dashboard_caisse" <?php echo ($page === 'dashboard_caisse') ? 'class="active"' : ''; ?>>💰 Tableau de bord</a></li>
                    <li><a href="index.php?page=feuille_caisse_add" <?php echo ($page === 'feuille_caisse_add') ? 'class="active"' : ''; ?>>📋 Nouvelle feuille</a></li>
                    <li><a href="index.php?page=feuille_caisse_list" <?php echo ($page === 'feuille_caisse_list') ? 'class="active"' : ''; ?>>📚 Historique feuilles</a></li>
                    <li><a href="index.php?page=resume_journalier" <?php echo ($page === 'resume_journalier') ? 'class="active"' : ''; ?>>📄 Résumé journalier</a></li>
                    <li><a href="index.php?page=tableau_recapitulatif" <?php echo ($page === 'tableau_recapitulatif') ? 'class="active"' : ''; ?>>📈 Tableau récapitulatif</a></li>
                </ul>
            </li>
            <li class="menu-item">
                <a href="index.php?page=cyber_list" <?php echo isMenuActive('cyber', $page) ? 'class="active"' : ''; ?>>🖥️ Sessions Cyber</a>
                <ul class="submenu">
                    <li><a href="index.php?page=cyber_add" <?php echo ($page === 'cyber_add') ? 'class="active"' : ''; ?>>➕ Nouvelle session</a></li>
                    <li><a href="index.php?page=cyber_list" <?php echo ($page === 'cyber_list') ? 'class="active"' : ''; ?>>🖥️ Liste des sessions</a></li>
                    <li><a href="index.php?page=cyber_credits_list" <?php echo ($page === 'cyber_credits_list') ? 'class="active"' : ''; ?>>💳 Crédits clients</a></li>
                </ul>
            </li>
            <li class="menu-item">
                <a href="index.php?page=transactions_list" <?php echo isMenuActive('transactions', $page) ? 'class="active"' : ''; ?>>💳 Transactions</a>
                <ul class="submenu">
                    <li><a href="index.php?page=transaction_add" <?php echo ($page === 'transaction_add') ? 'class="active"' : ''; ?>>💸 Nouvelle transaction</a></li>
                    <li><a href="index.php?page=transactions_list" <?php echo ($page === 'transactions_list') ? 'class="active"' : ''; ?>>💳 Liste des transactions</a></li>
                </ul>
            </li>
            <li class="menu-item">
                <a href="index.php?page=orders_list" <?php echo isMenuActive('stock', $page) ? 'class="active"' : ''; ?>>📦 Stock</a>
                <ul class="submenu">
                    <li><a href="index.php?page=stock_list" <?php echo ($page === 'stock_list') ? 'class="active"' : ''; ?>>📋 Liste</a></li>
                    <li><a href="index.php?page=orders_list" <?php echo ($page === 'orders_list') ? 'class="active"' : ''; ?>>📋 Liste commande</a></li>
                    <li><a href="index.php?page=inventory_list" <?php echo ($page === 'inventory_list' || $page === 'inventory_view') ? 'class="active"' : ''; ?>>📊 Inventaire</a></li>
                    <li><a href="index.php?page=stock_add" <?php echo ($page === 'stock_add') ? 'class="active"' : ''; ?>>➕ Ajouter</a></li>
                </ul>
            </li>
        </ul>
    </div>
    <div class="content">
        <header>
            <div class="header-left">
                <a href="index.php" style="text-decoration: none;">
                    <img src="img/logo_techsuivi_light_banniere.png" alt="TechSuivi" style="height: 60px; cursor: pointer;">
                </a>
            </div>
            <div class="header-controls">
                <a href="index.php?page=vnc" class="settings-btn btn-vnc">🖥️ VNC</a>
                <a href="index.php?page=mail_ai_assistant" class="settings-btn btn-ai">🤖 Assistant IA</a>
                <a href="index.php?page=settings" class="settings-btn">⚙️ Paramètres</a>
                <button id="theme-toggle">🌙 Light Mode</button>
                <a href="logout.php" class="logout-btn">🚪 Déconnexion</a>
            </div>
        </header>
        <main>
            <?php
            define('TECHSUIVI_INCLUDED', true); // Définir la constante ici

            if (isset($dbConnectionError)) {
                echo "<p style='color: red;'>" . htmlspecialchars($dbConnectionError) . "</p>";
            } elseif ($page === 'interventions_list') {
                if ($pdo) {
                    include 'pages/interventions/interventions_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des interventions ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'interventions_add') {
                if ($pdo) {
                    include 'pages/interventions/interventions_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout d'intervention ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'interventions_view') {
                if ($pdo) {
                    include 'pages/interventions/interventions_view.php';
                } else {
                     echo "<p style='color: red;'>Le détail de l'intervention ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'interventions_edit') {
                if ($pdo) {
                    include 'pages/interventions/interventions_edit.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification d'intervention ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'interventions_print') {
                if ($pdo) {
                    include 'pages/interventions/interventions_print.php';
                } else {
                     echo "<p style='color: red;'>La fiche d'impression ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'clients') {
                if ($pdo) {
                    include 'pages/clients/clients.php';
                } else {
                    echo "<p style='color: red;'>La liste des clients ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'add_client') {
                if ($pdo) {
                    include 'pages/clients/add_client.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de client ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'edit_client') {
                if ($pdo) {
                    include 'pages/clients/edit_client.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de client ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'clients_view') {
                if ($pdo) {
                    include 'pages/clients_view.php';
                } else {
                     echo "<p style='color: red;'>La vue du client ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'downloads_list') {
                if ($pdo) {
                    include 'pages/downloads/downloads_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des téléchargements ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'downloads_add') {
                if ($pdo) {
                    include 'pages/downloads/downloads_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de téléchargement ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'downloads_edit') {
                if ($pdo) {
                    include 'pages/downloads/downloads_edit.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de téléchargement ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'liens_list') {
                if ($pdo) {
                    include 'pages/liens/liens_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des liens ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'liens_add') {
                if ($pdo) {
                    include 'pages/liens/liens_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de lien ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'liens_edit') {
                if ($pdo) {
                    include 'pages/liens/liens_edit.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de lien ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'helpdesk_categories') {
                if ($pdo) {
                    include 'pages/helpdesk/helpdesk_categories_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des catégories helpdesk ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'messages') {
                if ($pdo) {
                    include 'pages/helpdesk/helpdesk_messages.php';
                } else {
                     echo "<p style='color: red;'>Les messages ne peuvent pas être affichés car la connexion à la base de données n'est pas disponible.</p>";
                }
            } elseif ($page === 'notes_list') {
                if ($pdo) {
                    include 'pages/notes/notes_list.php';
                } else {
                     echo "<p style='color: red;'>La page des notes ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'stock_list') {
                if ($pdo) {
                    include 'pages/stock/stock_list.php';
                } else {
                     echo "<p style='color: red;'>La liste du stock ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'orders_list') {
                if ($pdo) {
                    include 'pages/stock/orders_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des commandes ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'stock_add') {
                if ($pdo) {
                    include 'pages/stock/stock_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de produit ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'stock_mobile_upload') {
                if ($pdo) {
                    include 'pages/stock/stock_mobile_upload.php';
                } else {
                     echo "<p style='color: red;'>La page d'upload mobile ne peut pas être affichée.</p>";
                }
            } elseif ($page === 'inventory_list') {
                if ($pdo) {
                    include 'pages/stock/inventory_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des inventaires ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'inventory_view') {
                if ($pdo) {
                    include 'pages/stock/inventory_view.php';
                } else {
                     echo "<p style='color: red;'>L'inventaire ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'fournisseurs_list') {
                if ($pdo) {
                    include 'pages/fournisseurs/fournisseurs_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion des fournisseurs ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'catalog_import') {
                if ($pdo) {
                    include 'pages/catalog_import.php';
                } else {
                     echo "<p style='color: red;'>L'import du catalogue ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'client_import') {
                if ($pdo) {
                    include 'pages/client_import.php';
                } else {
                     echo "<p style='color: red;'>L'import client ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'dashboard_caisse') {
                if ($pdo) {
                    include 'pages/fiche_caisse/dashboard_caisse.php';
                } else {
                     echo "<p style='color: red;'>Le tableau de bord de la fiche de caisse ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'moyens_paiement') {
                if ($pdo) {
                    include 'pages/fiche_caisse/moyens_paiement.php';
                } else {
                     echo "<p style='color: red;'>La gestion des moyens de paiement ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_list') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des sessions cyber ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_add') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de session ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_edit') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_add.php'; // Même page pour édition
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de session ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_credits_list') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_credits_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des crédits clients ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_credits_add') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_credits_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de gestion des crédits ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_credits_history') {
                if ($pdo) {
                    include 'pages/fiche_caisse/cyber_credits_history.php';
                } else {
                     echo "<p style='color: red;'>L'historique des crédits ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'transactions_list') {
                if ($pdo) {
                    include 'pages/fiche_caisse/transactions_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des transactions ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'transaction_add') {
                if ($pdo) {
                    include 'pages/fiche_caisse/transaction_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de transaction ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'transaction_edit') {
                if ($pdo) {
                    include 'pages/fiche_caisse/transaction_add.php'; // Même page pour édition
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de transaction ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'feuille_caisse_add') {
                if ($pdo) {
                    include 'pages/fiche_caisse/feuille_caisse_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de feuille de caisse ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'feuille_caisse_list') {
                if ($pdo) {
                    include 'pages/fiche_caisse/feuille_caisse_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des feuilles de caisse ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'feuille_caisse_view') {
                if ($pdo) {
                    include 'pages/fiche_caisse/feuille_caisse_view.php';
                } else {
                     echo "<p style='color: red;'>Le détail de la feuille de caisse ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'feuille_caisse_delete') {
                if ($pdo) {
                    include 'actions/feuille_caisse_delete.php';
                } else {
                     echo "<p style='color: red;'>La suppression de la feuille de caisse ne peut pas être effectuée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'resume_journalier') {
                if ($pdo) {
                    include 'pages/fiche_caisse/resume_journalier.php';
                } else {
                     echo "<p style='color: red;'>Le résumé journalier ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'tableau_recapitulatif') {
                if ($pdo) {
                    include 'pages/fiche_caisse/tableau_recapitulatif.php';
                } else {
                     echo "<p style='color: red;'>Le tableau récapitulatif ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'photos_settings') {
                if ($pdo) {
                    include 'pages/admin/photos_settings.php';
                } else {
                     echo "<p style='color: red;'>Les paramètres photos ne peuvent pas être affichés car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'settings') {
                if ($pdo) {
                    include 'pages/settings.php';
                } else {
                     echo "<p style='color: red;'>Les paramètres ne peuvent pas être affichés car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'users_list') {
                if ($pdo) {
                    include 'pages/users/users_list.php';
                } else {
                     echo "<p style='color: red;'>La liste des utilisateurs ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'user_add') {
                if ($pdo) {
                    include 'pages/users/user_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout d'utilisateur ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'change_password') {
                if ($pdo) {
                    include 'pages/users/change_password.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire de changement de mot de passe ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'database_backup') {
                if ($pdo) {
                    include 'pages/admin/database_backup.php';
                } else {
                     echo "<p style='color: red;'>La sauvegarde de base de données ne peut pas être effectuée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'statuts_list') {
                if ($pdo) {
                    include 'pages/interventions/statuts_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion des statuts ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'timezone_settings') {
                // Cette page ne nécessite pas forcément de connexion à la base de données
                include 'pages/admin/timezone_settings.php';
            } elseif ($page === 'autoit_logiciels_list') {
                if ($pdo) {
                    include 'pages/autoit/autoit_logiciels_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion des logiciels AutoIT ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'autoit_commandes_list') {
                if ($pdo) {
                    include 'pages/autoit/autoit_commandes_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion des commandes AutoIT ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'autoit_nettoyage_list') {
                if ($pdo) {
                    include 'pages/autoit/autoit_nettoyage_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion du nettoyage AutoIT ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'autoit_personnalisation_list') {
                if ($pdo) {
                    include 'pages/autoit/autoit_personnalisation_list.php';
                } else {
                     echo "<p style='color: red;'>La gestion de la personnalisation AutoIT ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'autoit_installeur_list') {
                // Cette page ne nécessite pas de connexion à la base de données (gestion de fichiers)
                include 'pages/autoit/autoit_installeur_list.php';
            } elseif ($page === 'server_info') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/server_info.php';
            } elseif ($page === 'docker_info') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/docker_info.php';
            } elseif ($page === 'script_contributors') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/script_contributors.php';
            } elseif ($page === 'intervention_sheet_config') {
                if ($pdo) {
                    include 'pages/config/intervention_sheet_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'cyber_pricing_config') {
                if ($pdo) {
                    include 'pages/config/cyber_pricing_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration des tarifs cyber ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'acadia_config') {
                if ($pdo) {
                    include 'pages/config/acadia_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration Acadia ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'theme_config') {
                if ($pdo) {
                    include 'pages/config/theme_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration du thème ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'gemini_config') {
                if ($pdo) {
                    include 'pages/config/gemini_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration Gemini ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'mail_ai_assistant') {
                if ($pdo) {
                    include 'pages/config/mail_ai_assistant.php';
                } else {
                     echo "<p style='color: red;'>L'assistant IA ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'api_keys_config') {
                if ($pdo) {
                    include 'pages/config/api_keys_config.php';
                } else {
                     echo "<p style='color: red;'>La gestion des clés API ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'mail_config') {
                if ($pdo) {
                    include 'pages/config/mail_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration mail ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'oauth2_config') {
                if ($pdo) {
                    include 'pages/config/oauth2_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration OAuth2 ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'scheduled_tasks') {
                if ($pdo) {
                    include 'pages/config/scheduled_tasks.php';
                } else {
                     echo "<p style='color: red;'>La gestion des tâches programmées ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'reports_config') {
                if ($pdo) {
                    include 'pages/config/reports_config.php';
                } else {
                     echo "<p style='color: red;'>La configuration des rapports ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'orders_edit') {
                if ($pdo) {
                     include 'pages/stock/orders_edit.php';
                } else {
                     echo "<p style='color: red;'>La modification de commande ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'files_manager') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/admin/files_manager.php';
            } elseif ($page === 'rustdesk_backup') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/admin/rustdesk_backup.php';
            } elseif ($page === 'print_generator') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/print/print_generator.php';
            } elseif ($page === 'agenda_list') {
                if ($pdo) {
                    include 'pages/agenda/agenda_list.php';
                } else {
                     echo "<p style='color: red;'>La liste de l'agenda ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'agenda_add') {
                if ($pdo) {
                    include 'pages/agenda/agenda_add.php';
                } else {
                     echo "<p style='color: red;'>Le formulaire d'ajout de tâche ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'agenda_edit') {
                if ($pdo) {
                    include 'pages/agenda/agenda_add.php'; // Même page pour édition
                } else {
                     echo "<p style='color: red;'>Le formulaire de modification de tâche ne peut pas être affiché car la connexion à la base de données a échoué.</p>";
                }
            } elseif ($page === 'vnc') {
                // Cette page ne nécessite pas de connexion à la base de données
                include 'pages/vnc.php';
            } elseif ($page === 'vnc_fullscreen') {
                if ($pdo) {
                    include 'pages/vnc_fullscreen.php';
                } else {
                    echo "<p style='color: red;'>La page VNC plein écran ne peut pas être affichée car la connexion à la base de données a échoué.</p>";
                }

            } else {
                // Default dashboard content
                include 'pages/dashboard.php';
            }
            ?>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js?v=<?= time() ?>"></script>
    <script>
    // Charger les catégories dans le menu Messages
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($pdo)): ?>
        // Définir le lien principal sur "Tous"
        const messagesMainLink = document.getElementById('messages-main-link');
        if (messagesMainLink) {
            messagesMainLink.href = 'index.php?page=messages&category=all';
        }
        
        // Charger toutes les catégories pour le sous-menu
        fetch('api/get_categories.php')
            .then(response => response.json())
            .then(categories => {
                const submenu = document.getElementById('messages-submenu');
                
                // Récupérer la catégorie actuelle depuis l'URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentCategory = urlParams.get('category');
                const currentPage = urlParams.get('page');

                if (submenu && categories.length > 0) {
                    submenu.innerHTML = '';
                    categories.forEach(category => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = `index.php?page=messages&category=${category.ID}`;
                        a.textContent = category.CATEGORIE;
                        
                        // Ajouter la classe active si c'est la catégorie courante
                        if (currentPage === 'messages' && currentCategory === category.ID.toString()) {
                            a.classList.add('active');
                        }

                        li.appendChild(a);
                        submenu.appendChild(li);
                    });
                } else if (submenu) {
                    submenu.innerHTML = '<li><a href="index.php?page=helpdesk_categories">Aucune catégorie - Créer une catégorie</a></li>';
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des catégories:', error);
                const submenu = document.getElementById('messages-submenu');
                if (submenu) {
                    submenu.innerHTML = '<li><a href="index.php?page=helpdesk_categories">Erreur - Gérer les catégories</a></li>';
                }
            });
        <?php endif; ?>
    });
    </script>
</body>
</html>
