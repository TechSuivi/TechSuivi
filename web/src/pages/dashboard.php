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

<!-- dashboard.php -->

<div class="dashboard-container">
    
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-error"><?= $errorMessage ?></div>
    <?php else: ?>
        
        <!-- Quick Actions -->
        <!-- Quick Actions - SaaS Enterprise Style -->
        <div class="quick-actions-grid">
            <!-- CLIENTS -->
            <div class="saas-quick-action">
                <a href="index.php?page=clients" class="saas-action-main">
                    <span class="saas-icon">👥</span>
                    <span class="saas-label">Clients</span>
                </a>
                <a href="index.php?page=add_client" class="saas-action-add" onclick="event.preventDefault(); openNestedClientModal('quick_add');" title="Ajouter Client">
                    <span>+</span>
                </a>
            </div>

            <!-- CYBER -->
            <div class="saas-quick-action">
                <a href="index.php?page=cyber_list" class="saas-action-main">
                    <span class="saas-icon">💻</span>
                    <span class="saas-label">Cyber</span>
                </a>
                <a href="index.php?page=cyber_add" class="saas-action-add" title="Nouvelle Session">
                    <span>+</span>
                </a>
            </div>

            <!-- CAISSE -->
            <div class="saas-quick-action">
                <a href="index.php?page=feuille_caisse_list" class="saas-action-main">
                    <span class="saas-icon">💰</span>
                    <span class="saas-label">Caisse</span>
                </a>
                <a href="index.php?page=feuille_caisse_add" class="saas-action-add" title="Nouvelle Feuille">
                    <span>+</span>
                </a>
            </div>

            <!-- TRANSACTION -->
            <div class="saas-quick-action">
                <a href="index.php?page=transactions_list" class="saas-action-main">
                    <span class="saas-icon">💳</span>
                    <span class="saas-label">Transaction</span>
                </a>
                <a href="index.php?page=transaction_add" class="saas-action-add" title="Nouvelle Transaction">
                    <span>+</span>
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
        <div class="three-col-grid">
            
            <!-- Left content (Interventions & Messages) -->
            <div class="col-span-2 flex flex-col gap-30">
                <!-- Interventions Column -->
                <div class="dashboard-column">
                    <h3 class="column-title" style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <span>🔧 Interventions</span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button onclick="openAddInterventionModal()" class="btn-sm-action" style="background: #3498db; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600; cursor: pointer;" title="Nouvelle intervention">➕</button>
                            <a href="index.php?page=interventions_list" class="view-all" style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted); text-decoration: none;">Voir tout →</a>
                        </div>
                    </h3>
                    
                    <?php if (empty($recentInterventions)): ?>
                        <div class="no-messages">Rien à signaler 🎉</div>
                    <?php else: ?>
                        <div class="card p-0 overflow-hidden shadow-sm">
                            <table class="m-0 border-none w-full" style="table-layout: fixed;">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">Statut / Date</th>
                                            <th style="width: 25%;">Client</th>
                                            <th style="width: 40%;">Informations / Problème</th>
                                            <th style="width: 20%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentInterventions as $inter): ?>
                                            <tr>
                                                <td>
                                                    <div class="flex flex-col gap-5">
                                                        <div><span class="badge badge-blue">En cours</span></div>
                                                        <div class="text-xs text-muted">
                                                            📅 <?= date('d/m H:i', strtotime($inter['date'])) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <a href="index.php?page=clients_view&id=<?= $inter['id_client'] ?>" class="font-semibold text-sm hover:underline">
                                                            <?= htmlspecialchars($inter['client_nom'] ?? 'Inconnu') ?>
                                                            <span class="text-muted font-mono" style="font-size: 0.8em; margin-left: 5px;">#<?= $inter['id'] ?></span>
                                                        </a>
                                                        <span class="text-xs text-muted">
                                                            <?php if (!empty($inter['client_portable'])): ?>
                                                                📱 <?= htmlspecialchars($inter['client_portable']) ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-sm">
                                                    <div class="line-clamp-2" title="<?= htmlspecialchars($inter['info']) ?>">
                                                        <?= nl2br(htmlspecialchars($inter['info'])) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex gap-5">
                                                        <?php if (!empty($inter['ip_vnc'])): ?>
                                                            <a href="vnc_viewer.php?id=<?= $inter['id'] ?>" target="_blank" class="btn btn-sm-action" style="padding: 4px 5px;" title="Accès VNC">🖥️</a>
                                                        <?php endif; ?>
                                                        <a href="index.php?page=interventions_view&id=<?= $inter['id'] ?>" class="btn btn-sm-action px-10">Voir</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Messages Column -->
                    <div class="dashboard-column">
                        <h3 class="column-title" style="color: #2ecc71; border-bottom: 2px solid #2ecc71; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <span>💬 Messages</span>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <button id="addMessageBtn" class="btn-sm-action" style="background: #2ecc71; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600; cursor: pointer;" title="Nouveau message">➕</button>
                                <a href="index.php?page=messages&category=all" class="view-all" style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted); text-decoration: none;">Voir tout →</a>
                            </div>
                        </h3>
                        
                        <?php if (empty($recentMessages)): ?>
                            <div class="no-messages">Aucun message en attente 🎉</div>
                        <?php else: ?>
                            <div class="card p-0 overflow-hidden shadow-sm">
                                <table class="m-0 border-none w-full" style="table-layout: fixed;">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">Cat. / Date</th>
                                            <th style="width: 25%;">Client</th>
                                            <th style="width: 40%;">Objet / Message / Rép.</th>
                                            <th style="width: 20%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentMessages as $msg): ?>
                                            <tr>
                                                <td>
                                                    <div class="flex flex-col gap-5">
                                                        <?php if (!empty($msg['CATEGORIE_NOM'])): ?>
                                                            <div>
                                                                <span class="badge" style="background-color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR'] . '26') ?? 'rgba(46, 204, 113, 0.15)' ?>; color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR']) ?? '#27ae60' ?>;">
                                                                    <?= htmlspecialchars($msg['CATEGORIE_NOM']) ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-xs text-muted">
                                                            📅 <?= date('d/m H:i', strtotime($msg['DATE'])) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <a href="index.php?page=clients_view&id=<?= $msg['id_client'] ?>" class="font-semibold text-sm hover:underline">
                                                            <?= htmlspecialchars($msg['client_nom'] . ' ' . $msg['client_prenom']) ?>
                                                        </a>
                                                        <span class="text-xs text-muted">
                                                            <?php if (!empty($msg['client_portable'])): ?>
                                                                📱 <?= htmlspecialchars($msg['client_portable']) ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-sm">
                                                    <div class="font-semibold mb-5 truncate" title="<?= htmlspecialchars($msg['TITRE']) ?>">
                                                        Objet: <?= htmlspecialchars($msg['TITRE']) ?>
                                                    </div>
                                                    <div class="line-clamp-2" title="<?= htmlspecialchars($msg['MESSAGE']) ?>">
                                                        <?= nl2br(htmlspecialchars($msg['MESSAGE'])) ?>
                                                    </div>
                                                    <?php if (!empty($msg['REPLIES'])): ?>
                                                        <div class="mt-5 pt-5 border-t border-dashed border-color-muted">
                                                            <?php foreach ($msg['REPLIES'] as $reply): ?>
                                                                <div class="flex items-start gap-5 text-xs text-muted mb-2">
                                                                    <span class="font-bold text-success">↪</span>
                                                                    <span class="line-clamp-1"><?= htmlspecialchars($reply['MESSAGE']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="flex gap-5">
                                                        <button onclick="openViewMessageModal(<?= $msg['ID'] ?>)" class="btn btn-sm-action px-10" title="Voir">Voir</button>
                                                        <button onclick="openReplyModal(<?= $msg['ID'] ?>, '<?= htmlspecialchars(addslashes($msg['TITRE'])) ?>', '<?= htmlspecialchars(addslashes(substr(str_replace(["\r", "\n"], ' ', $msg['MESSAGE']), 0, 50))) ?>...')" class="btn btn-sm btn-primary px-10">Répondre</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Agenda Column -->
            <div class="dashboard-column">
                <h3 class="column-title" style="color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <span>📅 Agenda</span>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button onclick="openAddAgendaModal()" class="btn-sm-action" style="background: #e67e22; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-weight: 600; cursor: pointer;" title="Nouvel événement">➕</button>
                        <a href="index.php?page=agenda_list" class="view-all" style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted); text-decoration: none;">Voir tout →</a>
                    </div>
                </h3>
                
                <?php if (empty($recentAgendaItems)): ?>
                    <div class="no-messages">Rien de prévu 📅</div>
                <?php else: ?>
                    <div class="card p-0 overflow-hidden shadow-sm">
                        <table class="m-0 border-none w-full" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Date / Statut</th>
                                    <th style="width: 45%;">Événement / Client</th>
                                    <th style="width: 20%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAgendaItems as $task): ?>
                                    <?php $isOverdue = strtotime($task['date_planifiee']) < time() && $task['statut'] !== 'termine'; ?>
                                    <tr>
                                        <td>
                                            <div class="flex flex-col gap-5">
                                                <div class="text-xs font-semibold <?= $isOverdue ? 'text-danger' : 'text-muted' ?>">
                                                    📅 <?= date('d/m H:i', strtotime($task['date_planifiee'])) ?>
                                                </div>
                                                <div>
                                                    <?php if ($isOverdue): ?>
                                                        <span class="badge badge-red">Retard</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-orange" style="font-size: 0.75em;"><?= ucfirst($task['statut']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <div class="font-semibold text-sm truncate" title="<?= htmlspecialchars($task['titre']) ?>">
                                                    <?= htmlspecialchars($task['titre']) ?>
                                                </div>
                                                <?php if (!empty($task['client_nom'])): ?>
                                                    <a href="index.php?page=clients_view&id=<?= $task['id_client'] ?>" class="text-xs text-muted hover:underline truncate">
                                                        👤 <?= htmlspecialchars($task['client_nom'] . ' ' . $task['client_prenom']) ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button onclick="openViewAgendaModal(<?= $task['id'] ?>)" class="btn btn-sm-action px-10">Voir</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
<div id="addInterventionModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">🛠️ Nouvelle Intervention</h3>
            <span class="modal-close" onclick="closeAddInterventionModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="interventionAlerts"></div>
            <form id="addInterventionForm">
                <!-- Client Search -->
                <div class="form-group">
                    <label for="interv_client_search" class="form-label">Client *</label>
                    <div class="flex gap-10">
                        <div class="client-search-container flex-1">
                            <input type="text" id="interv_client_search" class="form-control" autocomplete="off" placeholder="Rechercher un client (nom, email...)" required>
                            <input type="hidden" id="interv_id_client" name="id_client" required>
                            <div id="interv_client_suggestions" class="client-suggestions"></div>
                        </div>
                        <button type="button" class="btn btn-primary" style="padding: 0 15px;" onclick="openNestedClientModal('intervention')" title="Créer un nouveau client">
                            <span>➕</span>
                        </button>
                    </div>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label for="interv_date" class="form-label">Date et Heure *</label>
                    <input type="datetime-local" id="interv_date" name="date" class="form-control" required>
                </div>

                <!-- En cours -->
                <div class="checkbox-group mb-20">
                    <input type="checkbox" id="interv_en_cours" name="en_cours" value="1" checked>
                    <label for="interv_en_cours" class="form-label">Intervention en cours (visible sur l'accueil)</label>
                </div>

                <!-- Informations -->
                <div class="form-group">
                    <label for="interv_info" class="form-label">Informations / Problème *</label>
                    <textarea id="interv_info" name="info" class="form-control" placeholder="Description du problème à résoudre..." required rows="4"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddInterventionModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="submitAddInterventionForm()">Ajouter l'intervention</button>
        </div>
    </div>
</div>

</div>



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

    /* Close modals on outside click - DISABLED as per user request
    window.addEventListener('click', (e) => {
        if (e.target === replyMessageModal) closeModal(replyMessageModal);
        if (e.target === addMessageModal) closeModal(addMessageModal);
    });
    */

    if (closeReplyMessageModal) {
        closeReplyMessageModal.addEventListener('click', () => closeModal(replyMessageModal));
    }
    
    if (cancelReplyMessage) {
        cancelReplyMessage.addEventListener('click', () => closeModal(replyMessageModal));
    }

    // Reply Message Form Submit logic moved to function submitReplyMessageForm()
    // Listener removed.

    // Functions moved to global scope


    
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
        toggleBtn.textContent = isDone ? '✅ Marquer comme à faire' : '✅ Marquer comme fait';
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
        
        const getStatusClass = (status) => {
            const map = {
                'planifie': 'status-planned',
                'en_cours': 'status-progress',
                'termine': 'status-completed',
                'reporte': 'status-postponed',
                'annule': 'status-cancelled'
            };
            return map[status] || 'status-planned';
        };

        const getPriorityClass = (priority) => {
            const map = {
                'urgente': 'priority-urgent',
                'haute': 'priority-high',
                'normale': 'priority-normal',
                'basse': 'priority-low'
            };
            return map[priority] || 'priority-normal';
        };

        const statusClass = getStatusClass(item.statut);
        const priorityClass = getPriorityClass(item.priorite);

        const statusLabel = document.getElementById('viewAgendaStatus');
        statusLabel.textContent = item.statut.charAt(0).toUpperCase() + item.statut.slice(1);
        statusLabel.className = 'badge badge-agenda ' + statusClass;
        
        const priorityLabel = document.getElementById('viewAgendaPriority');
        priorityLabel.textContent = item.priorite ? (item.priorite.charAt(0).toUpperCase() + item.priorite.slice(1)) : 'Normale';
        priorityLabel.className = 'badge badge-agenda ' + priorityClass;
        
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

    /* Close modals on outside click - DISABLED as per user request
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    });
    */
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

    // Expose functions globally for onclick events
    window.submitReplyMessageForm = function() {
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
                document.getElementById('replyMessageModal').style.display = 'none';
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
    };

    window.openReplyModal = function(id, title, messageSnippet) {
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

    window.closeReplyMessageModal = function() {
        document.getElementById('replyMessageModal').style.display = 'none';
    };
</script>