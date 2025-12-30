<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Récupérer l'ID du client depuis l'URL
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$client = null;
$interventions = [];
$message = '';

if ($clientId <= 0) {
    $message = '<div class="alert alert-error">ID client invalide.</div>';
} else {
    if (isset($pdo)) {
        try {
            // Récupérer les infos du client
            $sqlClient = "SELECT * FROM clients WHERE ID = :id";
            $stmtClient = $pdo->prepare($sqlClient);
            $stmtClient->execute([':id' => $clientId]);
            $client = $stmtClient->fetch();

            if (!$client) {
                $message = '<div class="alert alert-error">Client non trouvé.</div>';
            } else {
                // Récupérer les interventions du client
                // On vérifie d'abord si la table intervention_statuts existe pour la jointure
                $tableExists = false;
                try {
                    $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
                    $tableExists = true;
                } catch (PDOException $e) {
                    // Table n'existe pas
                }

                if ($tableExists) {
                    $sqlInter = "
                        SELECT i.*, s.nom as statut_nom, s.couleur as statut_couleur
                        FROM inter i
                        LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                        WHERE i.id_client = :id
                        ORDER BY i.date DESC, i.id DESC
                    ";
                } else {
                    $sqlInter = "
                        SELECT *
                        FROM inter
                        WHERE id_client = :id
                        ORDER BY date DESC, id DESC
                    ";
                }
                
                $stmtInter = $pdo->prepare($sqlInter);
                $stmtInter->execute([':id' => $clientId]);
                $interventions = $stmtInter->fetchAll();

                // Récupérer l'historique agenda
                $sqlAgenda = "
                    SELECT * FROM agenda 
                    WHERE id_client = :id 
                    ORDER BY date_planifiee DESC
                ";
                $stmtAgenda = $pdo->prepare($sqlAgenda);
                $stmtAgenda->execute([':id' => $clientId]);
                $agendaItems = $stmtAgenda->fetchAll();

                // Récupérer l'historique des messages helpdesk
                $sqlMessages = "
                    SELECT 
                        m.*, 
                        cat.CATEGORIE as CATEGORIE_NOM, 
                        cat.couleur as CATEGORIE_COULEUR
                    FROM helpdesk_msg m 
                    LEFT JOIN helpdesk_cat cat ON m.CATEGORIE = cat.ID
                    WHERE m.id_client = :id 
                    ORDER BY m.DATE DESC
                ";
                $stmtMessages = $pdo->prepare($sqlMessages);
                $stmtMessages->execute([':id' => $clientId]);
                $messagesHistory = $stmtMessages->fetchAll();

                // Fetch replies if there are messages
                if (!empty($messagesHistory)) {
                    $msgIds = array_column($messagesHistory, 'ID');
                    if (!empty($msgIds)) {
                        $inQuery = implode(',', array_fill(0, count($msgIds), '?'));
                        $sqlReplies = "SELECT * FROM helpdesk_reponses WHERE MESSAGE_ID IN ($inQuery) ORDER BY DATE_REPONSE ASC";
                        $stmtReplies = $pdo->prepare($sqlReplies);
                        $stmtReplies->execute($msgIds);
                        $allReplies = $stmtReplies->fetchAll();

                        $repliesByMsg = [];
                        foreach ($allReplies as $r) {
                            $repliesByMsg[$r['MESSAGE_ID']][] = $r;
                        }

                        foreach ($messagesHistory as &$m) {
                            $m['REPLIES'] = $repliesByMsg[$m['ID']] ?? [];
                        }
                        unset($m);
                    }
                }

                // Récupérer l'historique des transactions
                $sqlTransactions = "
                    SELECT * FROM FC_transactions 
                    WHERE id_client = :id 
                    ORDER BY date_transaction DESC
                ";
                $stmtTransactions = $pdo->prepare($sqlTransactions);
                $stmtTransactions->execute([':id' => $clientId]);
                $transactionsHistory = $stmtTransactions->fetchAll();
            }

        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de connexion à la base de données.</div>';
    }
}
?>

</style>
<style>
/* Modern Purple Theme for Client View */
.client-view-page {
    background: var(--bg-color);
    color: var(--text-color);
    padding-bottom: 40px;
}

.page-header {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    font-size: 1.6em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.client-details-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.85em;
    color: var(--text-muted);
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.detail-value {
    font-size: 1.1em;
    font-weight: 500;
    color: var(--text-color);
}

.detail-value a {
    color: #8e44ad;
    text-decoration: none;
}

.detail-value a:hover {
    text-decoration: underline;
}

.section-title {
    font-size: 1.3em;
    margin-bottom: 20px;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 10px;
}

/* Interventions Table */
.interventions-table-container {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

table {
    width: 100%;
    border-collapse: collapse;
}

table thead {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); /* Blue for interventions */
    color: white;
}

table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9em;
    text-transform: uppercase;
}

table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}

table tr:last-child td {
    border-bottom: none;
}

table tr:hover {
    background: var(--hover-bg);
}

/* Column width adjustments */
table th:nth-child(1),
table td:nth-child(1) {
    width: 60px; /* ID column */
}

table th:nth-child(2),
table td:nth-child(2) {
    width: 140px; /* Date column */
}

table th:nth-child(3),
table td:nth-child(3) {
    width: 120px; /* Statut column */
}

table th:nth-child(4),
table td:nth-child(4) {
    width: auto; /* Description column - takes remaining space */
}

table th:nth-child(5),
table td:nth-child(5) {
    width: 80px; /* Actions column */
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 600;
    color: white;
    background: #95a5a6; /* Default gray */
}

.status-open {
    background: #2ecc71;
}

.status-closed {
    background: #95a5a6;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    font-size: 0.95em;
}

.btn-primary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
}

.btn-action {
    padding: 4px 8px;
    font-size: 0.85em;
    background: #3498db;
    color: white;
    border-radius: 4px;
}

.btn-action:hover {
    background: #2980b9;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.empty-state {
    padding: 40px;
    text-align: center;
    color: var(--text-muted);
}
</style>
<!-- Modals CSS is now included in index.php -->
<style>
/* New Dashboard Grid Layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
}

.two-col-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 30px;
}

.full-width-section {
    width: 100%;
}

@media (max-width: 1000px) {
    .two-col-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="client-view-page">
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="page-header">
            <h1>
                <span>👤</span>
                <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?>
            </h1>
            <div class="header-actions">
                <a href="index.php?page=clients" class="btn btn-primary">
                    <span>←</span> Retour liste
                </a>
            </div>
        </div>

        <div class="client-details-card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
                <h2 style="font-size: 1.3em; margin: 0; color: var(--text-color); display: flex; align-items: center; gap: 10px;">
                    <span>📋</span> Informations Client
                </h2>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="openEditClientModal()" style="background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%); color: white; border: none; font-size: 0.9em; padding: 6px 12px;">
                        <span>✏️</span> Modifier
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="confirmDeleteClient()" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border: none; font-size: 0.9em; padding: 6px 12px;">
                        <span>🗑️</span> Supprimer
                    </button>
                </div>
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Nom complet</span>
                    <span class="detail-value"><?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <?php if (!empty($client['mail'])): ?>
                            <a href="mailto:<?= htmlspecialchars($client['mail']) ?>"><?= htmlspecialchars($client['mail']) ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Téléphone</span>
                    <span class="detail-value">
                        <?php if (!empty($client['telephone'])): ?>
                            <a href="tel:<?= htmlspecialchars($client['telephone']) ?>"><?= htmlspecialchars($client['telephone']) ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Portable</span>
                    <span class="detail-value">
                        <?php if (!empty($client['portable'])): ?>
                            <a href="tel:<?= htmlspecialchars($client['portable']) ?>"><?= htmlspecialchars($client['portable']) ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Adresse</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($client['adresse1']) ?><br>
                        <?php if (!empty($client['adresse2'])): ?>
                            <?= htmlspecialchars($client['adresse2']) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($client['cp'] . ' ' . $client['ville']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <!-- Row 1: Agenda & Messages (Side by Side) -->
            <div class="two-col-grid">
                <?php if (!empty($agendaItems)): ?>
        <div class="agenda-section" style="margin-bottom: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <span>📅</span> Historique Agenda
                </h2>
            </div>

            <div class="interventions-table-container">

                    <table>
                        <thead>
                            <tr>
                                <th style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 14%;">Date</th>
                                <th style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 50%;">Titre</th>
                                <th style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 12%;">Statut</th>
                                <th style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 14%;">Priorité</th>
                                <th style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 10%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agendaItems as $item): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($item['date_planifiee'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['titre']) ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <div style="font-size: 0.9em; color: var(--text-muted); margin-top: 4px;">
                                                <?= htmlspecialchars(substr($item['description'], 0, 80)) . (strlen($item['description']) > 80 ? '...' : '') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusLabels = [
                                            'planifie' => ['label' => 'Planifié', 'color' => '#3498db'],
                                            'en_cours' => ['label' => 'En cours', 'color' => '#f39c12'],
                                            'termine' => ['label' => 'Terminé', 'color' => '#27ae60'],
                                            'reporte' => ['label' => 'Reporté', 'color' => '#e67e22'],
                                            'annule' => ['label' => 'Annulé', 'color' => '#e74c3c']
                                        ];
                                        $status = $statusLabels[$item['statut']] ?? ['label' => $item['statut'], 'color' => '#95a5a6'];
                                        ?>
                                        <span class="status-badge" style="background-color: <?= $status['color'] ?>;">
                                            <?= htmlspecialchars($status['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $prioLabels = [
                                            'basse' => ['label' => 'Basse', 'color' => '#95a5a6'],
                                            'normale' => ['label' => 'Normale', 'color' => '#3498db'],
                                            'haute' => ['label' => 'Haute', 'color' => '#f39c12'],
                                            'urgente' => ['label' => 'Urgente', 'color' => '#e74c3c']
                                        ];
                                        $prio = $prioLabels[$item['priorite']] ?? ['label' => $item['priorite'], 'color' => '#95a5a6'];
                                        ?>
                                        <span style="color: <?= $prio['color'] ?>; font-weight: 600; font-size: 0.9em;">
                                            <?= htmlspecialchars($prio['label']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 5px;">
                                            <button type="button" class="btn-action" style="background-color: #3498db; border: none; cursor: pointer;"
                                                onclick="openViewAgendaModal(this)"
                                                data-titre="<?= htmlspecialchars($item['titre']) ?>"
                                                data-date="<?= date('d/m/Y H:i', strtotime($item['date_planifiee'])) ?>"
                                                data-statut="<?= htmlspecialchars($item['statut']) ?>"
                                                data-priorite="<?= htmlspecialchars($item['priorite']) ?>"
                                                data-description="<?= htmlspecialchars($item['description']) ?>"
                                                title="Voir le détail">
                                                👁️
                                            </button>
                                            <a href="index.php?page=agenda_edit&id=<?= $item['id'] ?>" class="btn-action" style="background-color: #e67e22;" title="Modifier">
                                                ✏️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

            </div>
        </div>



        <?php endif; ?>

        <?php if (!empty($messagesHistory)): ?>
        <div class="messages-section" style="margin-bottom: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <span>💬</span> Historique Messages
                </h2>
            </div>

            <div class="interventions-table-container">

                    <table>
                        <thead>
                            <tr>
                                <th style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 14%;">Date</th>
                                <th style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 14%;">Catégorie</th>
                                <th style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 50%;">Titre</th>
                                <th style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 12%;">Statut</th>
                                <th style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 10%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messagesHistory as $msg): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($msg['DATE'])) ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR'] . '26') ?? 'rgba(46, 204, 113, 0.15)' ?>; color: <?= htmlspecialchars($msg['CATEGORIE_COULEUR']) ?? '#27ae60' ?>; display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">
                                            <?= htmlspecialchars($msg['CATEGORIE_NOM']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($msg['TITRE']) ?></strong>
                                        <?php if (!empty($msg['MESSAGE'])): ?>
                                            <div style="font-size: 0.9em; color: var(--text-muted); margin-top: 4px;">
                                                <?= htmlspecialchars(substr($msg['MESSAGE'], 0, 80)) . (strlen($msg['MESSAGE']) > 80 ? '...' : '') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($msg['FAIT']): ?>
                                            <span style="color: #27ae60; font-weight: 600;">✅ Terminé</span>
                                        <?php else: ?>
                                            <span style="color: #e67e22; font-weight: 600;">🕒 À faire</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-action" style="background-color: #3498db; border: none; cursor: pointer;" 
                                            onclick="openViewMessageModal(this)"
                                            data-title="<?= htmlspecialchars($msg['TITRE']) ?>"
                                            data-date="<?= date('d/m/Y H:i', strtotime($msg['DATE'])) ?>"
                                            data-category="<?= htmlspecialchars($msg['CATEGORIE_NOM']) ?>"
                                            data-content="<?= htmlspecialchars($msg['MESSAGE']) ?>"
                                            data-replies='<?= json_encode($msg['REPLIES'] ?? []) ?>'
                                            title="Voir le message">
                                            👁️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
        </div>
        </div>
        <?php endif; ?>
        </div> <!-- End Two Col Grid -->
        
        <!-- DEBUG: Interventions count = <?= count($interventions ?? []) ?>, Transactions count = <?= count($transactionsHistory ?? []) ?> -->

        <?php if (!empty($interventions)): ?>
        <div class="full-width-section">
        <div class="interventions-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <span>🔧</span> Historique des Interventions
                </h2>
            </div>

            <div class="interventions-table-container">

                    <table>
                        <thead>
                            <tr>
                                <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); width: 8%;">ID</th>
<th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); width: 14%;">Date</th>
<th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); width: 12%;">Statut</th>
<th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); width: 56%;">Description</th>
<th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interventions as $inter): ?>
                                <tr>
                                    <td><?= htmlspecialchars($inter['id']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($inter['date'])) ?></td>
                                    <td>
                                        <?php if (isset($inter['statut_nom'])): ?>
                                            <span class="status-badge" style="background-color: <?= htmlspecialchars($inter['statut_couleur']) ?>;">
                                                <?= htmlspecialchars($inter['statut_nom']) ?>
                                            </span>
                                        <?php else: ?>
                                            <?php if ($inter['en_cours']): ?>
                                                <span class="status-badge status-open">En cours</span>
                                            <?php else: ?>
                                                <span class="status-badge status-closed">Clôturée</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(substr(strip_tags($inter['info']), 0, 100)) ?>
                                        <?php if (strlen(strip_tags($inter['info'])) > 100) echo '...'; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="index.php?page=interventions_view&id=<?= $inter['id'] ?>" class="btn-action" style="background-color: #3498db; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px;" title="Voir l'intervention">👁️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

            </div>
        </div>
        </div>

        <?php endif; ?>




        <?php if (!empty($transactionsHistory)): ?>
        <div class="full-width-section">
        <div class="transactions-section" style="margin-top: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <span>💰</span> Historique Transactions
                </h2>
            </div>

            <div class="interventions-table-container">

                    <table>
                        <thead>
                            <tr>
                                <th style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); width: 14%;">Date</th>
                                <th style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); width: 44%;">Description</th>
                                <th style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); width: 14%;">Type</th>
                                <th style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); width: 14%;">Montant</th>
                                <th style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); width: 14%;">Acompte/Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactionsHistory as $trans): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($trans['date_transaction'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($trans['nom']) ?>
                                        <?php if (!empty($trans['num_facture'])): ?>
                                            <div style="font-size: 0.85em; color: var(--text-muted);">Facture : <?= htmlspecialchars($trans['num_facture']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?= $trans['type'] === 'entree' ? '#2ecc71' : ($trans['type'] === 'sortie' ? '#e74c3c' : '#95a5a6') ?>;">
                                            <?= htmlspecialchars(ucfirst($trans['type'])) ?>
                                        </span>
                                        <div style="font-size: 0.85em; margin-top: 4px;"><?= htmlspecialchars($trans['type_paiement'] ?? '') ?></div>
                                    </td>
                                    <td style="font-weight: bold; color: <?= $trans['type'] === 'entree' ? '#27ae60' : '#c0392b' ?>;">
                                        <?= $trans['type'] === 'sortie' ? '-' : '' ?><?= number_format($trans['montant'], 2) ?> €
                                    </td>
                                    <td>
                                        <?php if (!empty($trans['acompte']) || !empty($trans['solde'])): ?>
                                            <?php if (!empty($trans['acompte'])): ?>
                                                <div>Acompte: <?= number_format($trans['acompte'], 2) ?> €</div>
                                            <?php endif; ?>
                                            <?php if (!empty($trans['solde'])): ?>
                                                <div>Solde: <?= number_format($trans['solde'], 2) ?> €</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>


            </div>
        </div>
        </div>
        <?php endif; ?>

    </div> <!-- End Dashboard Grid -->


<!-- Modal d'édition de client -->
<?php include 'includes/modals/edit_client.php'; ?>

<!-- Modal de confirmation de suppression -->
<?php include 'includes/modals/delete_client.php'; ?>



<script src="../js/awesomplete.min.js"></script>
<link rel="stylesheet" href="../css/awesomplete.css" />
<script>
// ===== GESTION DE LA MODAL D'ÉDITION =====
function openEditClientModal() {
    document.getElementById('editClientModal').style.display = 'block';
    document.getElementById('editClientAlerts').innerHTML = '';
    // Populate form with existing data
    document.getElementById('client_edit_id').value = "<?= htmlspecialchars($client['ID']) ?>";
    document.getElementById('client_edit_nom').value = "<?= htmlspecialchars($client['nom'] ?? '') ?>";
    document.getElementById('client_edit_prenom').value = "<?= htmlspecialchars($client['prenom'] ?? '') ?>";
    document.getElementById('client_edit_mail').value = "<?= htmlspecialchars($client['mail'] ?? '') ?>";
    document.getElementById('client_edit_adresse1').value = "<?= htmlspecialchars($client['adresse1'] ?? '') ?>";
    document.getElementById('client_edit_adresse2').value = "<?= htmlspecialchars($client['adresse2'] ?? '') ?>";
    document.getElementById('client_edit_cp').value = "<?= htmlspecialchars($client['cp'] ?? '') ?>";
    document.getElementById('client_edit_ville').value = "<?= htmlspecialchars($client['ville'] ?? '') ?>";
    document.getElementById('client_edit_telephone').value = "<?= htmlspecialchars($client['telephone'] ?? '') ?>";
    document.getElementById('client_edit_portable').value = "<?= htmlspecialchars($client['portable'] ?? '') ?>";
    
    // Focus sur le champ nom
    setTimeout(() => {
        document.getElementById('client_edit_nom').focus();
    }, 100);
}

function closeEditClientModal() {
    document.getElementById('editClientModal').style.display = 'none';
}

// ===== SUPPRESSION DU CLIENT =====
function confirmDeleteClient() {
    document.getElementById('deleteClientAlerts').innerHTML = '';
    document.getElementById('client_delete_name').textContent = "<?= htmlspecialchars($client['nom'] . ' ' . $client['prenom']) ?>";
    document.getElementById('client_delete_id').value = "<?= (int)$client['ID'] ?>";
    document.getElementById('deleteClientModal').style.display = 'block';
}

function closeDeleteClientModal() {
    document.getElementById('deleteClientModal').style.display = 'none';
}

function executeDeleteClient() {
    const clientId = document.getElementById('client_delete_id').value;
    const alertsDiv = document.getElementById('deleteClientAlerts');
    
    // Envoyer la requête de suppression
    const formData = new FormData();
    formData.append('id', clientId);
    
    fetch('actions/client_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert-modal success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            // Rediriger vers la liste des clients après 1 seconde
            setTimeout(() => {
                window.location.href = 'index.php?page=clients';
            }, 1000);
        } else {
            alertsDiv.innerHTML = `<div class="alert-modal error">
                <span>⚠️</span>
                <div>${data.error || 'Erreur inconnue'}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}

// Fermer avec Escape
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditClientModal();
        closeDeleteClientModal();
    }
});

// ===== FORMATAGE DES NUMÉROS DE TÉLÉPHONE =====
document.addEventListener('DOMContentLoaded', function() {
    const telInput = document.getElementById('client_edit_telephone');
    const portableInput = document.getElementById('client_edit_portable');
    
    function formatPhoneNumber(inputElement) {
        let value = inputElement.value.replace(/\D/g, '');
        let formattedValue = '';
        for (let i = 0; i < value.length && i < 10; i++) {
            if (i > 0 && i % 2 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        inputElement.value = formattedValue;
    }
    
    function validatePhoneNumber(inputElement) {
        const value = inputElement.value.replace(/\D/g, '');
        if (value.length > 0 && value.length !== 10) {
            inputElement.style.borderColor = '#e74c3c';
        } else {
            inputElement.style.borderColor = '';
        }
    }
    
    if (telInput) {
        formatPhoneNumber(telInput); // Format initial value
        telInput.addEventListener('input', function() { formatPhoneNumber(this); });
        telInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
    if (portableInput) {
        formatPhoneNumber(portableInput); // Format initial value
        portableInput.addEventListener('input', function() { formatPhoneNumber(this); });
        portableInput.addEventListener('blur', function() { validatePhoneNumber(this); });
    }
    
    // ===== AUTOCOMPLÉTION D'ADRESSE =====
    const adresse1Input = document.getElementById('client_edit_adresse1');
    const cpInput = document.getElementById('client_edit_cp');
    const villeInput = document.getElementById('client_edit_ville');
    
    if (window.Awesomplete && adresse1Input) {
        let addressFetchTimeout;
        const awesompleteInstance = new Awesomplete(adresse1Input, {
            minChars: 3,
            autoFirst: true,
            list: [],
            data: function (item, input) { 
                return { label: item.label, value: item.properties };
            },
            item: function (suggestionData, input) {
                return Awesomplete.ITEM(suggestionData.label, input);
            },
            replace: function(suggestionData) {
                this.input.value = suggestionData.value.name || '';
            }
        });
        
        adresse1Input.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 3) {
                awesompleteInstance.list = [];
                return;
            }
            
            clearTimeout(addressFetchTimeout);
            addressFetchTimeout = setTimeout(() => {
                fetch('../api/get_addresses.php?q=' + encodeURIComponent(query))
                    .then(response => response.ok ? response.json() : Promise.reject('Erreur réseau'))
                    .then(data => {
                        if (data.features && Array.isArray(data.features)) {
                            const suggestions = data.features.map(feature => ({
                                label: feature.properties.label || '', 
                                properties: feature.properties
                            }));
                            awesompleteInstance.list = suggestions;
                        } else {
                            awesompleteInstance.list = [];
                        }
                    })
                    .catch(error => {
                        console.error('Erreur autocomplétion adresse:', error);
                        awesompleteInstance.list = [];
                    });
            }, 300);
        });
        
        adresse1Input.addEventListener('awesomplete-selectcomplete', function(event) {
            const selectedProperties = event.text.value;
            if (selectedProperties && typeof selectedProperties === 'object') {
                if (cpInput) cpInput.value = selectedProperties.postcode || '';
                if (villeInput) villeInput.value = selectedProperties.city || '';
            }
        });
    }
});

// ===== SOUMISSION DU FORMULAIRE =====
function submitEditClientForm() {
    const form = document.getElementById('editClientForm');
    const alertsDiv = document.getElementById('editClientAlerts');
    
    // Validation côté client
    const nom = document.getElementById('client_edit_nom').value.trim();
    const telephone = document.getElementById('client_edit_telephone').value.trim();
    const portable = document.getElementById('client_edit_portable').value.trim();
    const mail = document.getElementById('client_edit_mail').value.trim();
    
    let errors = [];
    
    if (nom === '') {
        errors.push('Le nom est obligatoire.');
    }
    
    if (telephone === '' && portable === '') {
        errors.push('Au moins un numéro de téléphone est obligatoire.');
    }
    
    if (mail !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) {
        errors.push("L'adresse email n'est pas valide.");
    }
    
    if (errors.length > 0) {
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>${errors.join('<br>')}</div>
        </div>`;
        return;
    }
    
    // Préparer les données du formulaire
    const formData = new FormData(form);
    
    // Envoyer via AJAX
    fetch('actions/client_edit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertsDiv.innerHTML = `<div class="alert-modal success">
                <span>✅</span>
                <div>${data.message}</div>
            </div>`;
            
            // Recharger la page après 1 seconde pour voir les modifications
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : (data.error || 'Erreur inconnue');
            alertsDiv.innerHTML = `<div class="alert-modal error">
                <span>⚠️</span>
                <div>${errorMsg}</div>
            </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alertsDiv.innerHTML = `<div class="alert-modal error">
            <span>⚠️</span>
            <div>Erreur de communication avec le serveur.</div>
        </div>`;
    });
}
</script>


<!-- Modal Visualisation Agenda -->
<?php include 'includes/modals/view_agenda.php'; ?>


<!-- Modal Visualisation Message -->
<?php include 'includes/modals/view_message.php'; ?>

<script>
function openViewMessageModal(btn) {
    const title = btn.getAttribute('data-title');
    const date = btn.getAttribute('data-date');
    const category = btn.getAttribute('data-category');
    const content = btn.getAttribute('data-content');
    const repliesJson = btn.getAttribute('data-replies');
    let replies = [];
    try {
        replies = JSON.parse(repliesJson);
    } catch(e) {
        replies = [];
    }
    
    document.getElementById('viewMessageTitle').textContent = title;
    document.getElementById('viewMessageDate').textContent = '📅 ' + date;
    document.getElementById('viewMessageCategory').textContent = category || 'Général';
    document.getElementById('viewMessageContent').textContent = content;

    // Gérer les réponses
    const repliesContainer = document.getElementById('viewMessageReplies');
    const repliesList = document.getElementById('viewMessageRepliesList');
    
    if (replies && replies.length > 0) {
        repliesContainer.style.display = 'block';
        repliesList.innerHTML = replies.map(reply => `
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

    document.getElementById('viewMessageModal').style.display = 'flex';
}

function closeViewMessageModal() {
    document.getElementById('viewMessageModal').style.display = 'none';
}

// ===== MODAL VISUALISATION AGENDA =====
function openViewAgendaModal(btn) {
    const titre = btn.getAttribute('data-titre');
    const date = btn.getAttribute('data-date');
    const statut = btn.getAttribute('data-statut');
    const priorite = btn.getAttribute('data-priorite');
    const description = btn.getAttribute('data-description');
    
    document.getElementById('viewAgendaTitle').textContent = titre;
    document.getElementById('viewAgendaDate').textContent = '📅 ' + date;
    
    // Statut formatting
    const statusLabels = {
        'planifie': { label: 'Planifié', color: '#3498db' },
        'en_cours': { label: 'En cours', color: '#f39c12' },
        'termine': { label: 'Terminé', color: '#27ae60' },
        'reporte': { label: 'Reporté', color: '#e67e22' },
        'annule': { label: 'Annulé', color: '#e74c3c' }
    };
    const stObj = statusLabels[statut] || { label: statut, color: '#95a5a6' };
    const statusEl = document.getElementById('viewAgendaStatus');
    statusEl.textContent = stObj.label;
    statusEl.style.backgroundColor = stObj.color;
    statusEl.style.color = 'white';
    
    // Priority formatting
    const prioLabels = {
        'basse': { label: 'Basse', color: '#95a5a6' },
        'normale': { label: 'Normale', color: '#3498db' },
        'haute': { label: 'Haute', color: '#f39c12' },
        'urgente': { label: 'Urgente', color: '#e74c3c' }
    };
    const prObj = prioLabels[priorite] || { label: priorite, color: '#95a5a6' };
    const prioEl = document.getElementById('viewAgendaPriority');
    prioEl.textContent = prObj.label;
    prioEl.style.color = prObj.color;
    
    document.getElementById('viewAgendaDescription').textContent = description || 'Aucune description.';
    
    document.getElementById('viewAgendaModal').style.display = 'flex';
}

function closeViewAgendaModal() {
    document.getElementById('viewAgendaModal').style.display = 'none';
}

// Close on outside click
window.addEventListener('click', function(event) {
    const msgModal = document.getElementById('viewMessageModal');
    const agdModal = document.getElementById('viewAgendaModal');
    if (event.target === msgModal) closeViewMessageModal();
    if (event.target === agdModal) closeViewAgendaModal();
});
</script>
<?php endif; ?>
