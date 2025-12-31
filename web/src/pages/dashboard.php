<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$recentMessages = [];
$totalPendingMessages = 0;
$recentInterventions = [];
$totalPendingInterventions = 0;
$recentAgendaItems = [];
$totalPendingAgendaItems = 0;
$messageCategories = [];
$errorMessage = '';

if (isset($pdo)) {
    try {
        // Récupérer le nombre total de messages non terminés
        $stmt = $pdo->query("SELECT COUNT(*) FROM helpdesk_msg WHERE FAIT = 0");
        $totalPendingMessages = $stmt->fetchColumn();
        
        // Récupérer les messages récents (non faits) - LIMITE 5
        $stmt = $pdo->prepare("
            SELECT 
                m.ID, m.Titre as TITRE, m.MESSAGE, m.DATE, m.CATEGORIE, m.FAIT,
                c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_telephone, c.portable as client_portable, c.ID as id_client,
                cat.CATEGORIE as CATEGORIE_NOM, cat.couleur as CATEGORIE_COULEUR
            FROM helpdesk_msg m
            LEFT JOIN helpdesk_cat cat ON m.CATEGORIE = cat.ID
            LEFT JOIN clients c ON m.id_client = c.ID
            WHERE m.FAIT = 0
            ORDER BY m.DATE DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les réponses pour ces messages
        if (!empty($recentMessages)) {
            $messageIds = array_column($recentMessages, 'ID');
            $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';
            
            $repliesQuery = "SELECT ID, MESSAGE_ID, MESSAGE, DATE_REPONSE 
                             FROM helpdesk_reponses 
                             WHERE MESSAGE_ID IN ($placeholders) 
                             ORDER BY DATE_REPONSE ASC";
                             
            $stmt = $pdo->prepare($repliesQuery);
            $stmt->execute($messageIds);
            $allReplies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organiser les réponses par message_id
            $repliesByMessage = [];
            foreach ($allReplies as $reply) {
                $repliesByMessage[$reply['MESSAGE_ID']][] = $reply;
            }
            
            // Attacher les réponses aux messages
            foreach ($recentMessages as &$msg) {
                $msg['REPLIES'] = $repliesByMessage[$msg['ID']] ?? [];
            }
            unset($msg); // Rompre la référence pour éviter les bugs dans les boucles suivantes
        }
        
        // Récupérer le nombre total d'interventions en cours
        $stmt = $pdo->query("SELECT COUNT(*) FROM inter WHERE en_cours = 1");
        $totalPendingInterventions = $stmt->fetchColumn();
        
        // Récupérer le nombre d'interventions affichées (les 5 dernières)
        $totalDisplayedInterventions = min(5, $totalPendingInterventions);
        
        // Récupérer le nombre de messages récents (les 5 derniers)
        $totalDisplayedMessages = min(5, $totalPendingMessages);
        
        // Récupérer les 5 dernières interventions encore en cours avec le nom du client
        $stmt = $pdo->query("
            SELECT
                i.id,
                i.id_client,
                i.date,
                i.en_cours,
                i.info,
                i.ip_vnc,
                i.pass_vnc,
                CONCAT(c.nom, ' ', c.prenom) as client_nom,
                c.telephone as client_telephone,
                c.portable as client_portable
            FROM inter i
            LEFT JOIN clients c ON i.id_client = c.ID
            WHERE i.en_cours = 1
            ORDER BY i.date DESC
            LIMIT 5
        ");
        $recentInterventions = $stmt->fetchAll();
        
        // Récupérer le nombre total de tâches agenda en cours (non terminées)
        $stmt = $pdo->query("SELECT COUNT(*) FROM agenda WHERE statut IN ('planifie', 'en_cours', 'reporte')");
        $totalPendingAgendaItems = $stmt->fetchColumn();
        
        // Récupérer le nombre de tâches en retard
        $stmt = $pdo->query("SELECT COUNT(*) FROM agenda WHERE date_planifiee < NOW() AND statut IN ('planifie', 'en_cours', 'reporte')");
        $totalOverdueAgendaItems = $stmt->fetchColumn();
        
        // Récupérer le nombre de tâches urgentes
        $stmt = $pdo->query("SELECT COUNT(*) FROM agenda WHERE priorite = 'urgente' AND statut IN ('planifie', 'en_cours', 'reporte')");
        $totalUrgentAgendaItems = $stmt->fetchColumn();
        
        // Récupérer les 5 prochaines tâches agenda (non terminées) triées par date
        $stmt = $pdo->query("
            SELECT
                a.id,
                a.id_client,
                a.titre,
                a.description,
                a.date_planifiee,
                a.priorite,
                a.statut,
                a.couleur,
                c.nom as client_nom,

                c.prenom as client_prenom,
                c.telephone as client_telephone,
                c.portable as client_portable
            FROM agenda a
            LEFT JOIN clients c ON a.id_client = c.ID
            WHERE a.statut IN ('planifie', 'en_cours', 'reporte')
            ORDER BY a.date_planifiee ASC
            LIMIT 5
        ");
        $recentAgendaItems = $stmt->fetchAll();
        
        // Régénérer les tokens VNC si des interventions en cours ont une IP VNC
        if (!empty($recentInterventions)) {
            $hasVnc = false;
            foreach ($recentInterventions as $inter) {
                if (!empty($inter['ip_vnc'])) {
                    $hasVnc = true;
                    break;
                }
            }
            
            if ($hasVnc) {
                try {
                    require_once __DIR__ . '/../actions/generate_vnc_tokens.php';
                } catch (Exception $e) {
                    error_log("Erreur génération tokens VNC dashboard: " . $e->getMessage());
                }
            }
        }
        
        // Récupérer les catégories de messages pour la modale d'ajout
        $stmt = $pdo->query("SELECT ID, CATEGORIE FROM helpdesk_cat ORDER BY CATEGORIE ASC");
        $messageCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les sessions Cyber en cours (non payées)
        $stmt = $pdo->query("SELECT id, nom FROM FC_cyber WHERE moyen_payement IS NULL OR moyen_payement = '' ORDER BY date_cyber DESC");
        $activeCyberSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}
?>

<style>
:root {
    --dashboard-header-start: #2c3e50;
    --dashboard-header-end: #34495e;
    --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
    --card-hover-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Welcome Header */
.welcome-header {
    background: linear-gradient(135deg, var(--dashboard-header-start) 0%, var(--dashboard-header-end) 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-text h1 {
    margin: 0 0 5px 0;
    font-size: 1.8em;
    font-weight: 600;
}

.welcome-text p {
    margin: 0;
    opacity: 0.8;
    font-size: 1em;
}

.current-date {
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: var(--card-shadow);
    border: 1px solid var(--border-color);
    transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--card-hover-shadow);
}

.kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2em;
}

.kpi-content {
    flex: 1;
}

.kpi-value {
    font-size: 1.4em;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-color);
}

.kpi-label {
    font-size: 0.75em;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Theme Colors for KPI */
.kpi-blue .kpi-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }
.kpi-blue .kpi-value { color: #2980b9; }

.kpi-green .kpi-icon { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
.kpi-green .kpi-value { color: #27ae60; }

.kpi-orange .kpi-icon { background: rgba(230, 126, 34, 0.1); color: #e67e22; }
.kpi-orange .kpi-value { color: #d35400; }

.kpi-red .kpi-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
.kpi-red .kpi-value { color: #c0392b; }

/* Quick Actions */
.kpi-card {
    background: transparent;
    border-radius: 0;
    padding: 10px 15px;
    border: none;
    border-right: 1px solid var(--border-color);
    box-shadow: none;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.kpi-card:last-child {
    border-right: none;
}

.kpi-card .kpi-icon {
    font-size: 1.2em;
    width: auto;
    height: auto;
    background: none !important;
}

.kpi-card .kpi-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.kpi-card .kpi-value {
    font-size: 1.3em;
    margin: 0;
}

.kpi-card .kpi-label {
    font-size: 0.8em;
    margin: 0;
    white-space: nowrap;
}

.stats-grid {
    display: flex;
    flex-wrap: nowrap;
    gap: 0px;
    margin-bottom: 20px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    padding: 0;
}

.stats-grid .kpi-card {
    flex: 1;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    color: white;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(0,0,0,0.15);
    color: white;
}

.btn-inter { background: linear-gradient(135deg, #3498db, #2980b9); }
.btn-client { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.btn-agenda { background: linear-gradient(135deg, #e67e22, #d35400); }
.btn-cyber { background: linear-gradient(135deg, #1abc9c, #16a085); }
.btn-caisse { background: linear-gradient(135deg, #f1c40f, #f39c12); }
.btn-transaction { background: linear-gradient(135deg, #27ae60, #2ecc71); }

/* Main Columns Layout */
.dashboard-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin: 0;
}

.column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

.column-title {
    font-size: 1.2em;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.view-all {
    font-size: 0.85em;
    text-decoration: none;
    color: var(--text-muted);
    transition: color 0.2s;
}

.view-all:hover {
    color: var(--accent-color);
}

/* Activity Cards */
.activity-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(0,0,0,0.05);
}

/* Card Borders by Type */
.card-inter { border-left: 4px solid #3498db; }
.card-msg { border-left: 4px solid #2ecc71; }
.card-agenda { border-left: 4px solid #e67e22; }

.card-header-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.card-title {
    font-weight: 600;
    font-size: 1em;
    margin: 0;
    color: var(--text-color);
}

.card-meta {
    font-size: 0.8em;
    color: var(--text-muted);
}

.card-content {
    font-size: 0.9em;
    color: var(--text-muted);
    line-height: 1.5;
    margin-bottom: 10px;
}

.card-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.btn-sm-action {
    font-size: 0.8em;
    padding: 4px 10px;
    border-radius: 4px;
    text-decoration: none;
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.btn-sm-action:hover {
    background: var(--hover-bg);
    border-color: var(--accent-color);
}

/* Badges */
.badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    display: inline-block;
}

.badge-blue { background: rgba(52, 152, 219, 0.15); color: #2980b9; }
.badge-green { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
.badge-orange { background: rgba(230, 126, 34, 0.15); color: #d35400; }
.badge-red { background: rgba(231, 76, 60, 0.15); color: #c0392b; }

/* Dark Mode Adjustments */
body.dark .welcome-header {
    background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
}

body.dark .kpi-card,
body.dark .activity-card {
    background: #2b2b2b;
    border-color: #444;
}

body.dark .kpi-value,
body.dark .card-title {
    color: #ecf0f1;
}

body.dark .kpi-label,
body.dark .card-meta,
body.dark .card-content {
    color: #bdc3c7;
}

body.dark .btn-sm-action {
    background: #333;
    border-color: #555;
    color: #ecf0f1;
}

body.dark .btn-sm-action:hover {
    background: #444;
}



.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-muted);
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 0.95em;
    transition: all 0.2s;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
    outline: none;
}

.btn-modern {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-size: 0.9em;
}

.btn-primary {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(46, 204, 113, 0.3);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Ping Indicator Styles */
@keyframes blink-grey {
    0% { opacity: 0.4; }
    50% { opacity: 1; }
    100% { opacity: 0.4; }
}
.ping-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #999;
    animation: blink-grey 2s infinite;
    margin-right: 5px;
}
.ping-online {
    background-color: #2ecc71 !important;
    box-shadow: 0 0 5px rgba(46, 204, 113, 0.6);
    animation: none !important;
}
.ping-offline {
    background-color: #e74c3c !important;
    animation: none !important;
}

/* Split Button Styles */
.action-btn-group {
    display: flex;
    padding: 0;
    overflow: hidden;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.action-btn-group:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(0,0,0,0.15);
}

.action-btn-group .action-btn {
    border-radius: 0;
    box-shadow: none;
    margin: 0;
    padding: 15px 10px;
    transform: none !important; /* Disable hover transform on individual buttons, handled by group */
}

.action-btn-group .action-btn:hover {
    box-shadow: none;
    filter: brightness(1.1);
}

.action-btn-group .btn-add {
    flex: 0 0 50px; /* Fixed width for the add button */
    border-right: 1px solid rgba(255,255,255,0.2);
    justify-content: center;
}

.action-btn-group .btn-main {
    flex: 1;
    justify-content: flex-start; /* Align text to left */
    padding-left: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="dashboard-container">
    
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-error"><?= $errorMessage ?></div>
    <?php else: ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <!-- CLIENTS -->
            <div class="action-btn-group btn-client">
                <a href="#" class="action-btn btn-client btn-add" title="Nouveau Client" onclick="event.preventDefault(); openNestedClientModal('quick_add');">
                    <span>➕</span>
                </a>
                <a href="index.php?page=clients" class="action-btn btn-client btn-main" title="Liste des clients">
                    <span>👥 Clients</span>
                </a>
            </div>

            <!-- CYBER -->
            <div class="action-btn-group btn-cyber">
                <a href="index.php?page=cyber_add" class="action-btn btn-cyber btn-add" title="Nouvelle session">
                    <span>➕</span>
                </a>
                <?php if (!empty($activeCyberSessions)): ?>
                    <?php if (count($activeCyberSessions) === 1): ?>
                        <a href="index.php?page=cyber_add&id=<?= $activeCyberSessions[0]['id'] ?>" class="action-btn btn-cyber btn-main" title="Reprendre session <?= htmlspecialchars($activeCyberSessions[0]['nom']) ?>">
                            <span>🖥️ Sessions Cyber (1)</span>
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=cyber_list" class="action-btn btn-cyber btn-main" title="Voir les <?= count($activeCyberSessions) ?> sessions en cours">
                            <span>🖥️ Sessions Cyber (<?= count($activeCyberSessions) ?>)</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="index.php?page=cyber_list" class="action-btn btn-cyber btn-main" title="Historique des sessions">
                        <span>🖥️ Sessions Cyber</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- CAISSE -->
            <div class="action-btn-group btn-caisse">
                <a href="index.php?page=feuille_caisse_add" class="action-btn btn-caisse btn-add" title="Nouvelle feuille">
                    <span>➕</span>
                </a>
                <a href="index.php?page=feuille_caisse_list" class="action-btn btn-caisse btn-main" title="Historique de caisse">
                    <span>💰 Caisse</span>
                </a>
            </div>

            <!-- TRANSACTION -->
            <div class="action-btn-group btn-transaction">
                <a href="index.php?page=transaction_add" class="action-btn btn-transaction btn-add" title="Nouvelle transaction">
                    <span>➕</span>
                </a>
                <a href="index.php?page=transactions_list" class="action-btn btn-transaction btn-main" title="Liste transactions">
                    <span>💳 Transactions</span>
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="stats-grid">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon">🔧</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= $totalPendingInterventions ?></div>
                    <div class="kpi-label">Interventions en cours</div>
                </div>
            </div>
            
            <div class="kpi-card kpi-green">
                <div class="kpi-icon">💬</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= $totalPendingMessages ?></div>
                    <div class="kpi-label">Messages en attente</div>
                </div>
            </div>
            
            <div class="kpi-card kpi-orange">
                <div class="kpi-icon">📅</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= $totalPendingAgendaItems ?></div>
                    <div class="kpi-label">Événements planifiés</div>
                </div>
            </div>
            
            <?php if ($totalOverdueAgendaItems > 0): ?>
            <div class="kpi-card kpi-red">
                <div class="kpi-icon">⚠️</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= $totalOverdueAgendaItems ?></div>
                    <div class="kpi-label">Événements en retard</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Columns -->
        <div class="dashboard-columns">
            
            <!-- Interventions Column -->
            <div class="dashboard-column">
                <div class="column-header">
                    <h3 class="column-title" style="color: #3498db;">🔧 Interventions</h3>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button onclick="openAddInterventionModal()" class="btn-sm-action" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600; text-decoration: none; cursor: pointer;" title="Nouvelle intervention">➕</button>
                        <a href="index.php?page=interventions_list" class="view-all">Voir tout →</a>
                    </div>
                </div>
                
                <?php if (empty($recentInterventions)): ?>
                    <div class="no-messages">Rien à signaler 🎉</div>
                <?php else: ?>
                    <?php foreach ($recentInterventions as $inter): ?>
                        <div class="activity-card card-inter">
                            <div class="card-header-row">
                                <h4 class="card-title">#<?= $inter['id'] ?></h4>
                                <span class="badge badge-blue">En cours</span>
                            </div>
                            <div style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 5px;">
                                👤 <a href="index.php?page=clients_view&id=<?= $inter['id_client'] ?>" style="color: inherit; text-decoration: none; border-bottom: 1px dotted var(--text-muted); transition: color 0.2s;"><?= htmlspecialchars($inter['client_nom'] ?? 'Inconnu') ?></a>
                                <?php if (!empty($inter['client_telephone'])): ?>
                                    <span style="margin-left: 8px;">📞 <?= htmlspecialchars($inter['client_telephone']) ?></span>
                                <?php elseif (!empty($inter['client_portable'])): ?>
                                    <span style="margin-left: 8px;">📱 <?= htmlspecialchars($inter['client_portable']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <?= nl2br(htmlspecialchars(substr($inter['info'], 0, 80))) . (strlen($inter['info']) > 80 ? '...' : '') ?>
                            </div>
                            <div class="card-footer" style="gap: 10px; justify-content: space-between;">
                                <span class="card-meta" style="margin: 0; font-size: 0.8em;">📅 <?= date('d/m H:i', strtotime($inter['date'])) ?></span>
                                <div style="display: flex; gap: 10px;">
                                    <?php if (!empty($inter['ip_vnc'])): ?>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="ping-indicator" data-ip="<?= htmlspecialchars($inter['ip_vnc']) ?>" title="Statut ping"></span>
                                            <a href="vnc_viewer.php?id=<?= $inter['id'] ?>" target="_blank" class="btn-sm-action" style="background: #e8f5e9; color: #2e7d32; border-color: #a5d6a7;" title="Accès VNC">
                                                🖥️ VNC
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <a href="index.php?page=interventions_view&id=<?= $inter['id'] ?>" class="btn-sm-action">Voir</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Messages Column -->
            <div class="dashboard-column">
                <div class="column-header">
                    <h3 class="column-title" style="color: #2ecc71;">💬 Messages</h3>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button id="addMessageBtn" class="btn-sm-action" style="cursor: pointer; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600;" title="Nouveau message">➕</button>
                        <a href="index.php?page=messages&category=all" class="view-all">Voir tout →</a>
                    </div>
                </div>
                
                <?php if (empty($recentMessages)): ?>
                    <div class="no-messages">Aucun message en attente 🎉</div>
                <?php else: ?>
                    <?php foreach ($recentMessages as $msg): ?>
                        <div class="activity-card card-msg">
                            <div class="card-header-row">
                                <h4 class="card-title"><?= htmlspecialchars($msg['TITRE']) ?></h4>
                                <?php if (!empty($msg['CATEGORIE_NOM'])): ?>
                                    <span class="badge" style="background-color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR'] . '26') ?? 'rgba(46, 204, 113, 0.15)' ?>; color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR']) ?? '#27ae60' ?>;">
                                        <?= htmlspecialchars($msg['CATEGORIE_NOM']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($msg['client_nom'])): ?>
                                <div style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 5px;">
                                    👤 <a href="index.php?page=clients_view&id=<?= $msg['id_client'] ?>" style="color: inherit; text-decoration: none; border-bottom: 1px dotted var(--text-muted); transition: color 0.2s;"><?= htmlspecialchars($msg['client_nom'] . ' ' . $msg['client_prenom']) ?></a>
                                    <?php if (!empty($msg['client_telephone'])): ?>
                                        <span style="margin-left:8px;">📞 <?= htmlspecialchars($msg['client_telephone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['client_portable'])): ?>
                                        <span style="margin-left:8px;">📱 <?= htmlspecialchars($msg['client_portable']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-content">
                                <?= nl2br(htmlspecialchars(substr($msg['MESSAGE'], 0, 80))) . (strlen($msg['MESSAGE']) > 80 ? '...' : '') ?>
                            </div>
                            <?php if (!empty($msg['REPLIES'])): ?>
                                <div class="replies-preview" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                                    <?php foreach ($msg['REPLIES'] as $reply): ?>
                                        <div class="reply-item" style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 5px;">
                                            <span style="font-weight: 600; color: var(--emerald-end);">↪</span> 
                                            <?= nl2br(htmlspecialchars(substr($reply['MESSAGE'], 0, 50))) . (strlen($reply['MESSAGE']) > 50 ? '...' : '') ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-footer" style="justify-content: space-between;">
                                <span class="card-meta" style="margin: 0; font-size: 0.8em;">📅 <?= date('d/m H:i', strtotime($msg['DATE'])) ?></span>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="openViewMessageModal(<?= $msg['ID'] ?>)" class="btn-sm-action" style="cursor: pointer;">Voir</button>
                                    <button onclick="openReplyModal(<?= $msg['ID'] ?>, '<?= htmlspecialchars(addslashes($msg['TITRE'])) ?>', '<?= htmlspecialchars(addslashes(substr(str_replace(["\r", "\n"], ' ', $msg['MESSAGE']), 0, 50))) ?>...')" class="btn-sm-action" style="cursor: pointer; background: transparent; border: 1px solid var(--border-color);">Répondre</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Agenda Column -->
            <div class="dashboard-column">
                <div class="column-header">
                    <h3 class="column-title" style="color: #e67e22;">📅 Agenda</h3>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button onclick="openAddAgendaModal()" class="btn-sm-action" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600; text-decoration: none; cursor: pointer;" title="Nouvel événement">➕</button>
                        <a href="index.php?page=agenda_list" class="view-all">Voir tout →</a>
                    </div>
                </div>
                
                <?php if (empty($recentAgendaItems)): ?>
                    <div class="no-messages">Rien de prévu 📅</div>
                <?php else: ?>
                    <?php foreach ($recentAgendaItems as $task): ?>
                        <?php $isOverdue = strtotime($task['date_planifiee']) < time() && $task['statut'] !== 'termine'; ?>
                        <div class="activity-card card-agenda" style="<?= $isOverdue ? 'border-left-color: #e74c3c;' : '' ?>">
                            <div class="card-header-row">
                                <h4 class="card-title"><?= htmlspecialchars($task['titre']) ?></h4>
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-red">Retard</span>
                                <?php else: ?>
                                    <span class="badge badge-orange"><?= ucfirst($task['statut']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($task['client_nom'])): ?>
                                <div style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 5px;">
                                    👤 <a href="index.php?page=clients_view&id=<?= $task['id_client'] ?>" style="color: inherit; text-decoration: none; border-bottom: 1px dotted var(--text-muted); transition: color 0.2s;"><?= htmlspecialchars($task['client_nom'] . ' ' . $task['client_prenom']) ?></a>
                                    <?php if (!empty($task['client_telephone'])): ?>
                                        <span style="margin-left:8px;">📞 <?= htmlspecialchars($task['client_telephone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($task['client_portable'])): ?>
                                        <span style="margin-left:8px;">📱 <?= htmlspecialchars($task['client_portable']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($task['description'])): ?>
                                <div class="card-content">
                                    <?= nl2br(htmlspecialchars(substr($task['description'], 0, 80))) . (strlen($task['description']) > 80 ? '...' : '') ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-footer" style="justify-content: space-between;">
                                <span class="card-meta" style="margin: 0; font-size: 0.8em;">📅 <?= date('d/m H:i', strtotime($task['date_planifiee'])) ?></span>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="openViewAgendaModal(<?= $task['id'] ?>)" class="btn-sm-action" style="cursor: pointer;">Voir</button>
                                    <a href="index.php?page=agenda_edit&id=<?= $task['id'] ?>" class="btn-sm-action">Modifier</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
        
    <?php endif; ?>

</div>

<script>
// Dashboard Ping Logic
(function() {
    function checkDashboardPings() {
        const indicators = document.querySelectorAll('.ping-indicator[data-ip]');
        if (indicators.length === 0) return;

        indicators.forEach(el => {
            const ip = el.dataset.ip;
            fetch(`api/ping.php?ip=${encodeURIComponent(ip)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        el.classList.remove('ping-offline');
                        el.classList.add('ping-online');
                        el.title = "En ligne";
                    } else {
                        el.classList.remove('ping-online');
                        el.classList.add('ping-offline');
                        el.title = "Hors ligne";
                    }
                })
                .catch(err => {
                    console.error('Ping error', err);
                    el.classList.remove('ping-online');
                    el.classList.add('ping-offline');
                });
        });
    }

    // Initial check
    checkDashboardPings();
    
    // Poll every 15s
    setInterval(checkDashboardPings, 15000);
})();
</script>

<!-- ========================================== -->
<!-- INTÉGRATION MODALES INTERVENTION & CLIENT  -->
<!-- ========================================== -->

<link rel="stylesheet" href="css/awesomplete.css">
<script src="js/awesomplete.min.js"></script>

<!-- Modal d'ajout d'intervention -->
<div id="addInterventionModal" class="modal-overlay" style="display: none; z-index: 1000;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Nouvelle Intervention</h2>
            <span class="modal-close" onclick="closeAddInterventionModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="interventionAlerts"></div>
            <form id="addInterventionForm">
                <!-- Client Search -->
                <div class="form-group">
                    <label for="interv_client_search">Client *</label>
                    <div style="display: flex; gap: 8px; align-items: flex-start;">
                        <div class="client-search-container" style="flex: 1;">
                            <input type="text" id="interv_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)" required>
                            <input type="hidden" id="interv_id_client" name="id_client" required>
                            <div id="interv_client_suggestions" class="client-suggestions"></div>
                        </div>
                        <button type="button" class="btn" onclick="openNestedClientModal('intervention')" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; border: none; padding: 0 20px; white-space: nowrap; height: 45px; display: flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'" title="Créer un nouveau client">
                            <span>➕</span> Nouveau client
                        </button>
                    </div>
                </div>

                <!-- Date (Ligne unique) -->
                <div class="form-group">
                    <label for="interv_date">Date et Heure *</label>
                    <input type="datetime-local" id="interv_date" name="date" class="form-control" required>
                </div>

                <!-- En cours (Ligne) -->
                <div class="checkbox-group">
                    <input type="checkbox" id="interv_en_cours" name="en_cours" value="1" checked>
                    <label for="interv_en_cours">Intervention en cours (visible sur l'accueil)</label>
                </div>

                <!-- Informations -->
                <div class="form-group">
                    <label for="interv_info">Informations / Problème *</label>
                    <textarea id="interv_info" name="info" class="form-control" placeholder="Description du problème à résoudre..." required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddInterventionModal()">
                <span>✕</span>
                Annuler
            </button>
            <button type="button" class="btn btn-primary" onclick="submitAddInterventionForm()">
                <span>✓</span>
                Ajouter l'intervention
            </button>
        </div>
    </div>
</div>

</div>

<style>
/* Styles Modal */
.modal-overlay {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
    background: var(--card-bg);
    margin: 3% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    animation: slideInModal 0.3s ease;
    border: 1px solid var(--border-color);
}

@keyframes slideInModal {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    opacity: 0.8;
}

.modal-close:hover { opacity: 1; }

.modal-body {
    padding: 25px;
    max-height: 65vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Missing Form Styles */
.modal-footer .btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1em;
}

.modal-footer .btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
}

.modal-footer .btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.modal-footer .btn-secondary:hover {
    background: var(--hover-bg);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95em;
    color: var(--text-color);
}

.form-group .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-group .form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

.form-group textarea.form-control {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-hint {
    display: block;
    margin-top: 6px;
    font-size: 0.85em;
    color: var(--text-muted);
    line-height: 1.4;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #3498db;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
    user-select: none;
    font-weight: 500;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.client-search-container { position: relative; }
.client-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 2000; /* Augmenté pour être sûr */
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.client-suggestion-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}
.client-suggestion-item:hover { background: var(--hover-bg); }

/* Fix Awesomplete z-index */
.awesomplete { z-index: 1500; position: relative; }
.awesomplete > ul { z-index: 1500 !important; background: white; border: 1px solid #ccc; }

.alert-modal.success {
    padding: 10px; margin-bottom: 15px; border-radius: 6px;
    background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
}
.alert-modal.error {
    padding: 10px; margin-bottom: 15px; border-radius: 6px;
    background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
}
</style>

<script>
// ===== GESTION MODAL INTERVENTION =====
let clientSearchInitialized = false;
let nestedClientSource = 'intervention'; // 'intervention' or 'agenda'

function openAddInterventionModal() {
    const modal = document.getElementById('addInterventionModal');
    const form = document.getElementById('addInterventionForm');
    const alerts = document.getElementById('interventionAlerts');
    
    modal.style.display = 'block';
    
    // Reset form but keep date if set or set default
    if(!form.getAttribute('data-dirty')) {
        form.reset();
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('interv_date').value = now.toISOString().slice(0, 16);
    }
    
    alerts.innerHTML = '';
    
    setTimeout(() => { document.getElementById('interv_client_search').focus(); }, 100);
    
    // Initialize Search Listeners if not done yet
    if (!clientSearchInitialized) {
        initClientSearch();
        clientSearchInitialized = true;
    }
}

function initClientSearch() {
    const clientSearch = document.getElementById('interv_client_search');
    const clientId = document.getElementById('interv_id_client');
    const suggestions = document.getElementById('interv_client_suggestions');
    let searchTimeout;
    
    if (clientSearch) {
        // Input listener
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            clearTimeout(searchTimeout);
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'client-suggestion-item';
                            div.textContent = client.label; // Utilise le label formaté par le serveur
                            div.onclick = function() {
                                clientSearch.value = client.value;
                                clientId.value = client.id;
                                suggestions.style.display = 'none';
                            };
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                })
                .catch(err => console.error("Search error:", err));
            }, 300);
        });
        
        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (e.target !== clientSearch && e.target !== suggestions) {
                suggestions.style.display = 'none';
            }
        });
    } else {
        console.error("Client search input NOT found in DOM.");
    }
}

function closeAddInterventionModal() {
    document.getElementById('addInterventionModal').style.display = 'none';
}

function submitAddInterventionForm() {
    const form = document.getElementById('addInterventionForm');
    const alertsDiv = document.getElementById('interventionAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    if (!formData.get('id_client')) {
        alertsDiv.innerHTML = '<div class="alert-modal error">Veuillez sélectionner un client existant.</div>';
        return;
    }
    
    fetch('actions/intervention_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert-modal success">Intervention ajoutée ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            alertsDiv.innerHTML = `<div class="alert-modal error">${data.error || 'Erreur inconnue'}</div>`;
        }
    })
    .catch(error => {
        console.error(error);
        alertsDiv.innerHTML = '<div class="alert-modal error">Erreur communication serveur</div>';
    });
}

// ===== GESTION MODAL CLIENT NESTED (Via Shared add_client.php) =====
// La logique a été déplacée directement dans 'includes/modals/add_client.php'
// Pour éviter la duplication et les incohérences.



// ===== AUTOCOMPLÉTION CLIENT =====
document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('interv_client_search');
    const clientId = document.getElementById('interv_id_client');
    const suggestions = document.getElementById('interv_client_suggestions');
    let searchTimeout;
    
    if (clientSearch) {
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            clearTimeout(searchTimeout);
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`api/search_clients.php?term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'client-suggestion-item';
                            div.textContent = `${client.value} (${client.email || 'Pas d\'email'})`;
                            div.onclick = function() {
                                clientSearch.value = client.value;
                                clientId.value = client.id;
                                suggestions.style.display = 'none';
                            };
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                });
            }, 300);
        });
        
        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (e.target !== clientSearch && e.target !== suggestions) {
                suggestions.style.display = 'none';
            }
        });
    }
    
    // Init Awesomplete logic for Shared Modal
    const nestedTel = document.getElementById('client_add_telephone');
    const nestedPortable = document.getElementById('client_add_portable');
    const nestedNom = document.getElementById('client_add_nom');
    const nestedPrenom = document.getElementById('client_add_prenom');
    
    function formatNestedPhone(input) {
        let val = input.value.replace(/\D/g, '');
        let formatted = '';
        for(let i=0; i<val.length && i<10; i++) {
            if(i>0 && i%2===0) formatted += ' ';
            formatted += val[i];
        }
        input.value = formatted;
    }
    
    if(nestedTel) nestedTel.addEventListener('input', function() { formatNestedPhone(this); checkNestedDuplicates(); });
    if(nestedPortable) nestedPortable.addEventListener('input', function() { formatNestedPhone(this); checkNestedDuplicates(); });
    if(nestedNom) nestedNom.addEventListener('input', checkNestedDuplicates);
    if(nestedPrenom) nestedPrenom.addEventListener('input', checkNestedDuplicates);
    
    let checkTimeout;
    function checkNestedDuplicates() {
        const nom = nestedNom ? nestedNom.value.trim() : '';
        const prenom = nestedPrenom ? nestedPrenom.value.trim() : '';
        const telephone = nestedTel ? nestedTel.value.replace(/\s/g,'') : '';
        const portable = nestedPortable ? nestedPortable.value.replace(/\s/g,'') : '';
        
        if (!nom && !telephone && !portable) {
            document.getElementById('duplicateCheckSection').style.display = 'none';
            return;
        }
        
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            fetch('utils/check_duplicate_client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nom, prenom, telephone, portable })
            })
            .then(res => res.json())
            .then(data => {
                const section = document.getElementById('duplicateCheckSection');
                const container = document.getElementById('duplicatesContainer');
                
                if (data.duplicates && data.duplicates.length > 0) {
                    let html = '';
                    data.duplicates.forEach(dup => {
                        html += `<div style="padding: 8px; border-bottom: 1px solid var(--border-color); font-size: 0.9em;">
                            <strong>${dup.nom} ${dup.prenom || ''}</strong><br>
                            ${dup.telephone ? 'Tel: ' + dup.telephone : ''} ${dup.portable ? 'Port: ' + dup.portable : ''}
                        </div>`;
                    });
                    container.innerHTML = html;
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        }, 500);
    }
    
    // Initialize Awesomplete directly
    if (window.Awesomplete) initNestedAddressAutocomplete();
});

function initNestedAddressAutocomplete() {
    const adresseInput = document.getElementById('client_add_adresse1');
    if (adresseInput && window.Awesomplete && !adresseInput.classList.contains('awesomplete-processed')) {
        adresseInput.classList.add('awesomplete-processed');
        const awesomplete = new Awesomplete(adresseInput, { minChars: 3, maxItems: 10, autoFirst: true });
        
        let addressesData = {};
        
        adresseInput.addEventListener('input', function() {
            const query = this.value;
            if (query.length >= 3) {
                fetch(`api/get_addresses.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.features) {
                        const list = [];
                        addressesData = {};
                        data.features.forEach(f => {
                            list.push(f.properties.label);
                            addressesData[f.properties.label] = f.properties;
                        });
                        awesomplete.list = list;
                    }
                });
            }
        });
        
        adresseInput.addEventListener('awesomplete-selectcomplete', function(e) {
            const data = addressesData[e.text.value];
            if (data) {
                if (data.postcode) document.getElementById('client_add_cp').value = data.postcode;
                if (data.city) document.getElementById('client_add_ville').value = data.city;
            }
        });
    }
}
</script>

<!-- Modal de réponse (Shared) -->
<?php include 'includes/modals/reply_message.php'; ?>

<!-- Modal d'ajout de message (Shared) -->
<?php $categories = $messageCategories; include 'includes/modals/add_message.php'; ?>

<!-- ========================================== -->
<!-- MODAL AJOUT AGENDA (POPUP) -->
<!-- ========================================== -->
<!-- Modal Ajout Agenda (Shared) -->
<?php include 'includes/modals/add_agenda.php'; ?>

<script>
// ===== GESTION MODAL AGENDA =====
let agendaSearchInitialized = false;

function initAgendaClientSearch() {
    if (agendaSearchInitialized) return;
    
    const clientSearch = document.getElementById('agenda_client_search');
    const clientId = document.getElementById('agenda_id_client');
    const suggestions = document.getElementById('agenda_client_suggestions');
    let searchTimeout;
    
    if (clientSearch) {
        // Input listener
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            clearTimeout(searchTimeout);
            
            // Si le champ est vidé, on efface l'ID
            if (term.length === 0) {
                 clientId.value = '';
                 suggestions.style.display = 'none';
                 return;
            }
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'client-suggestion-item';
                            div.textContent = client.label;
                            div.onclick = function() {
                                clientSearch.value = client.value;
                                clientId.value = client.id;
                                suggestions.style.display = 'none';
                            };
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                })
                .catch(err => console.error("Search error:", err));
            }, 300);
        });
        
        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (e.target !== clientSearch && e.target !== suggestions) {
                suggestions.style.display = 'none';
            }
        });
        
        agendaSearchInitialized = true;
    }
}

function openAddAgendaModal() {
    initAgendaClientSearch();
    const modal = document.getElementById('addAgendaModal');
    const form = document.getElementById('addAgendaForm');
    const alerts = document.getElementById('agendaAlerts');
    
    modal.style.display = 'flex';
    form.reset();
    document.getElementById('agenda_id_client').value = '';
    alerts.innerHTML = '';
    // Default date: Now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('agenda_date').value = now.toISOString().slice(0, 16);
    
    setTimeout(() => { document.getElementById('agenda_client_search').focus(); }, 100);
}

function closeAddAgendaModal() {
    document.getElementById('addAgendaModal').style.display = 'none';
}

function setAgendaColor(color) {
    document.getElementById('agenda_couleur').value = color;
}

function submitAddAgendaForm() {
    const form = document.getElementById('addAgendaForm');
    const alertsDiv = document.getElementById('agendaAlerts');
    const formData = new FormData(form);
    
    alertsDiv.innerHTML = '';
    
    if (!formData.get('titre')) {
        alertsDiv.innerHTML = '<div class="alert alert-error">Le titre est obligatoire.</div>';
        return;
    }
    
    fetch('actions/agenda_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = '<div class="alert alert-success">Événement créé avec succès ! Rechargement...</div>';
            setTimeout(() => { location.reload(); }, 1000);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert alert-error">${errorMsg}</div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = '<div class="alert alert-error">Erreur de communication avec le serveur.</div>';
    });
}

    function closeModal(modal) {
        if (modal) modal.style.display = 'none';
    }

    function openModal(modal) {
        if (modal) modal.style.display = 'flex';
    }

function submitAddMessageForm() {
    const form = document.getElementById('msg_add_form');
    // If we can't find the form, maybe it's not loaded
    if (!form) {
        console.error("submitAddMessageForm: msg_add_form not found");
        return;
    }
    const alertsDiv = document.getElementById('msg_add_alert');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[onclick="submitAddMessageForm()"]');

    const originalText = submitBtn ? submitBtn.textContent : 'Ajouter';
    
    if(submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi...';
    }
    
    fetch('actions/helpdesk_messages_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            // Robust JSON parsing: clean BOM and find JSON object
            let cleanText = text.trim();
            // Remove BOM if present
            if (cleanText.charCodeAt(0) === 0xFEFF) {
                cleanText = cleanText.slice(1);
            }
            
            // Extract JSON part if there is noise
            const firstBrace = cleanText.indexOf('{');
            const lastBrace = cleanText.lastIndexOf('}');
            if (firstBrace !== -1 && lastBrace !== -1) {
                cleanText = cleanText.substring(firstBrace, lastBrace + 1);
            }

            const data = JSON.parse(cleanText);
            if (data.success) {
                closeModal(document.getElementById('addMessageModal'));
                form.reset();
                // Reload page or dashboard
                if (typeof loadDashboardData === 'function') {
                    loadDashboardData();
                } else {
                    window.location.reload(); 
                }
            } else {
                if(alertsDiv) alertsDiv.innerHTML = 
                    `<div class="alert alert-error">${data.message}</div>`;
            }
        } catch (e) {
            console.error('JS Error:', e);
            console.log('Raw Response:', text);
            if(alertsDiv) alertsDiv.innerHTML = 
                `<div class="alert alert-error">Erreur JS: ${e.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if(alertsDiv) alertsDiv.innerHTML = 
            `<div class="alert alert-error">Erreur de communication</div>`;
    })
    .finally(() => {
        if(submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const replyMessageModal = document.getElementById('replyMessageModal');
    const closeReplyMessageModal = document.getElementById('closeReplyMessageModal'); // Not in shared
    const cancelReplyMessage = document.getElementById('cancelReplyMessage'); // Not in shared
    const replyMessageForm = document.getElementById('msg_reply_form');
    
    // Add Message Modal
    const addMessageModal = document.getElementById('addMessageModal');
    const addMessageBtn = document.getElementById('addMessageBtn');
    const closeAddMessageModal = document.getElementById('closeAddMessageModal'); // Not in shared
    const cancelAddMessage = document.getElementById('cancelAddMessage'); // Not in shared
    const addMessageForm = document.getElementById('msg_add_form');

    // Close modals on outside click
    window.addEventListener('click', (e) => {
        if (e.target === replyMessageModal) closeModal(replyMessageModal);
        if (e.target === addMessageModal) closeModal(addMessageModal);
    });

    if (closeReplyMessageModal) {
        closeReplyMessageModal.addEventListener('click', () => closeModal(replyMessageModal));
    }
    
    if (cancelReplyMessage) {
        cancelReplyMessage.addEventListener('click', () => closeModal(replyMessageModal));
    }

    // Reply Message Form Submit logic moved to function submitReplyMessageForm()
    // Listener removed.

    // Functions moved to global scope

function submitReplyMessageForm() {
    const form = document.getElementById('msg_reply_form');
    // If we can't find the form, maybe it's not loaded
    if (!form) {
        console.error("submitReplyMessageForm: msg_reply_form not found");
        return;
    }
    const alertsDiv = document.getElementById('msg_reply_alert');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[onclick="submitReplyMessageForm()"]');

    const originalText = submitBtn ? submitBtn.textContent : 'Envoyer';
    
    if(submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi...';
    }
    
    fetch('actions/helpdesk_reponses_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal(document.getElementById('replyMessageModal'));
            form.reset();
            // Reload page or dashboard
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            } else {
                window.location.reload(); 
            }
        } else {
            if(alertsDiv) alertsDiv.innerHTML = 
                `<div class="alert alert-error">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if(alertsDiv) alertsDiv.innerHTML = 
            `<div class="alert alert-error">Erreur de communication</div>`;
    })
    .finally(() => {
        if(submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

    function openReplyModal(id, title, messageSnippet) {
        document.getElementById('msg_reply_id').value = id;
        document.getElementById('msg_reply_title').textContent = title;
        document.getElementById('msg_reply_preview').textContent = messageSnippet;
        
        // Reset form and alerts
        document.getElementById('msg_reply_form').reset();
        const alertDiv = document.getElementById('msg_reply_alert');
        if(alertDiv) alertDiv.innerHTML = '';
        
        const modal = document.getElementById('replyMessageModal');
        modal.style.display = 'flex';
        
        // Focus textarea
        setTimeout(() => {
            const el = document.getElementById('msg_reply_content');
            if(el) el.focus();
        }, 100);
    };
    
    // Add Message Modal Listeners
    if (addMessageBtn) {
        addMessageBtn.addEventListener('click', () => {
            initMessageClientSearch(); // Make sure this function exists or is found
            addMessageForm.reset(); // Reset the form
            const alertEl = document.getElementById('msg_add_alert'); // Corrected ID from include
            if(alertEl) alertEl.innerHTML = ''; 
            document.getElementById('msg_add_id_client').value = ''; // Reset client ID
            openModal(addMessageModal);
            setTimeout(() => {
                const el = document.getElementById('msg_add_titre');
                if(el) el.focus();
            }, 100);
        });
    }
    
    if (closeAddMessageModal) {
        closeAddMessageModal.addEventListener('click', () => closeModal(addMessageModal));
    }
    
    if (cancelAddMessage) {
        cancelAddMessage.addEventListener('click', () => closeModal(addMessageModal));
    }
    // Add Message Form Submit logic moved to global function submitAddMessageForm()
    // Listener removed.
});
</script>

<!-- Modal Ajout Client (Shared) -->
<?php include 'includes/modals/add_client.php'; ?>

<!-- Modal Visualisation Message -->
<?php include 'includes/modals/view_message.php'; ?>

<!-- Modal Visualisation Agenda -->
<?php include 'includes/modals/view_agenda.php'; ?>

<script>
    // Injecter les données PHP dans JS
    window.dashboardData = {
        messages: <?= json_encode($recentMessages) ?>,
        agenda: <?= json_encode($recentAgendaItems) ?>
    };

    // --- Fonctions Messages ---
    function openViewMessageModal(id) {
        const message = window.dashboardData.messages.find(m => m.ID == id);
        if (!message) return;

        document.getElementById('viewMessageTitle').textContent = message.TITRE;
        document.getElementById('viewMessageDate').textContent = '📅 ' + new Date(message.DATE).toLocaleString('fr-FR');
        document.getElementById('viewMessageCategory').textContent = message.CATEGORIE_NOM || 'Général';
        document.getElementById('viewMessageContent').textContent = message.MESSAGE;

        // Gérer les réponses
        const repliesContainer = document.getElementById('viewMessageReplies');
        const repliesList = document.getElementById('viewMessageRepliesList');
        
        if (message.REPLIES && message.REPLIES.length > 0) {
            repliesContainer.style.display = 'block';
            repliesList.innerHTML = message.REPLIES.map(reply => `
                <div style="background: var(--card-bg); padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 10px;">
                    <div style="font-size: 0.8em; color: var(--text-muted); margin-bottom: 5px; display: flex; justify-content: space-between;">
                        <span>Réponse</span>
                        <span>${new Date(reply.DATE_REPONSE).toLocaleString('fr-FR')}</span>
                    </div>
                    <div style="white-space: pre-wrap;">${reply.MESSAGE}</div>
                </div>
            `).join('');
        } else {
            repliesContainer.style.display = 'none';
        }

        const modal = document.getElementById('viewMessageModal');
        
        // Configurer les boutons d'action
        const deleteBtn = document.getElementById('viewMessageDeleteBtn');
        const toggleBtn = document.getElementById('viewMessageToggleBtn');

        // Unhide buttons for dashboard
        deleteBtn.style.display = 'block';
        toggleBtn.style.display = 'block';

        
        // Supprimer : redirection vers le script de suppression
        deleteBtn.onclick = function() {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) {
                window.location.href = 'actions/helpdesk_messages_delete.php?id=' + message.ID + '&redirect=dashboard';
            }
        };
        
        // Basculer statut
        const isDone = message.FAIT == 1;
        toggleBtn.textContent = isDone ? '✅ Marquer comme à faire' : '✅ Marquer comme lu';
        toggleBtn.className = isDone ? 'btn-modern btn-status-done' : 'btn-modern btn-primary';
        toggleBtn.onclick = function() {
            toggleMessageStatus(message.ID, message.FAIT);
        };

        modal.style.display = 'flex';
        modal.classList.add('active'); // Pour l'animation si définie
    }

    function toggleMessageStatus(id, currentStatus) {
        const newStatus = currentStatus ? 0 : 1;
        
        fetch('actions/helpdesk_messages_toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}&status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recharger pour mettre à jour la liste
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur de communication');
        });
    }

    function closeViewMessageModal() {
        document.getElementById('viewMessageModal').style.display = 'none';
    }

    // --- Fonctions Agenda ---
    function openViewAgendaModal(id) {
        const item = window.dashboardData.agenda.find(a => a.id == id);
        if (!item) return;

        document.getElementById('viewAgendaTitle').textContent = item.titre;
        // Formatage date plus joli
        const dateObj = new Date(item.date_planifiee);
        const dateStr = dateObj.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
        document.getElementById('viewAgendaDate').textContent = dateStr;
        
        document.getElementById('viewAgendaStatus').textContent = item.statut.charAt(0).toUpperCase() + item.statut.slice(1);
        document.getElementById('viewAgendaPriority').textContent = item.priorite ? (item.priorite.charAt(0).toUpperCase() + item.priorite.slice(1)) : 'Normale';
        
        const descEl = document.getElementById('viewAgendaDescription');
        if (item.description) {
            descEl.textContent = item.description;
            descEl.style.display = 'block';
        } else {
            descEl.textContent = 'Aucune description';
            descEl.style.display = 'block'; // Always show, even if empty
        }
        
        const editLink = document.getElementById('viewAgendaEditLink');
        editLink.href = 'index.php?page=agenda_edit&id=' + item.id;
        editLink.style.display = 'inline-flex'; // Unhide edit link

        const modal = document.getElementById('viewAgendaModal');
        modal.style.display = 'flex';
    }

    function closeViewAgendaModal() {
        document.getElementById('viewAgendaModal').style.display = 'none';
    }

    // Close modals on outside click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    });
    // Message Client Search Logic
    let messageSearchInitialized = false;
    function initMessageClientSearch() {
        if (messageSearchInitialized) return;
        
        const clientSearch = document.getElementById('msg_add_client_search'); // Corrected ID
        const clientId = document.getElementById('msg_add_id_client'); // Corrected ID
        const suggestions = document.getElementById('msg_add_client_suggestions'); // Corrected ID
        let searchTimeout;
        
        if (clientSearch) {
            clientSearch.addEventListener('input', function() {
                const term = this.value;
                clearTimeout(searchTimeout);
                
                if (term.length === 0) {
                     clientId.value = '';
                     suggestions.style.display = 'none';
                     return;
                }
                
                if (term.length < 2) {
                    suggestions.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                    fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        suggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(client => {
                                const div = document.createElement('div');
                                div.className = 'client-suggestion-item';
                                div.textContent = client.label;
                                div.onclick = function() {
                                    clientSearch.value = client.value;
                                    clientId.value = client.id;
                                    suggestions.style.display = 'none';
                                };
                                suggestions.appendChild(div);
                            });
                            suggestions.style.display = 'block';
                        } else {
                            suggestions.style.display = 'none';
                        }
                    })
                    .catch(err => console.error("Search error:", err));
                }, 300);
            });
            
            // Hide suggestions on click outside
            document.addEventListener('click', function(e) {
                if (e.target !== clientSearch && e.target !== suggestions) {
                    suggestions.style.display = 'none';
                }
            });
            
            messageSearchInitialized = true;
        }
    }
</script>