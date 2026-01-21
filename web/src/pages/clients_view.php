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

                // --- Récupérer l'historique CYBER (Sessions + Crédits) ---
                $cyberHistory = [];
                
                // 1. Sessions Cyber
                $sqlCyberSessions = "
                    SELECT 
                        id, 
                        date_cyber as date, 
                        'SESSION' as type, 
                        tarif as montant, 
                        CONCAT('Session Cyber - ', IFNULL(duree_minutes, '?'), ' min') as description,
                        '#8e44ad' as color
                    FROM FC_cyber
                    WHERE id_client = :id
                ";
                // Note: FC_cyber n'a pas forcément duree_minutes stocké, on fera le calcul ou affichage simple
                // Correction : on prend juste les champs dispos
                $sqlCyberSessions = "
                    SELECT 
                        id, 
                        date_cyber as date, 
                        'SESSION' as type, 
                        tarif as montant, 
                        'Session Cyber' as description,
                        '#8e44ad' as color,
                        moyen_payement
                    FROM FC_cyber
                    WHERE id_client = :id
                ";
                $stmtCyber = $pdo->prepare($sqlCyberSessions);
                $stmtCyber->execute([':id' => $clientId]);
                $cyberSessions = $stmtCyber->fetchAll(PDO::FETCH_ASSOC);
                
                // 2. Historique Crédits
                $sqlCyberCredits = "
                    SELECT 
                        h.id, 
                        h.date_mouvement as date, 
                        h.type_mouvement as type, 
                        h.montant, 
                        h.description,
                        CASE 
                            WHEN h.type_mouvement = 'AJOUT' THEN '#27ae60'
                            WHEN h.type_mouvement = 'DEDUCTION' THEN '#e67e22'
                            ELSE '#95a5a6'
                        END as color
                    FROM FC_cyber_credits_historique h
                    JOIN FC_cyber_credits c ON h.credit_id = c.id
                    WHERE c.id_client = :id
                ";
                $stmtCredits = $pdo->prepare($sqlCyberCredits);
                $stmtCredits->execute([':id' => $clientId]);
                $cyberCredits = $stmtCredits->fetchAll(PDO::FETCH_ASSOC);
                
                // Fusionner
                $cyberHistory = array_merge($cyberSessions, $cyberCredits);
                
                // Trier par date décroissante
                usort($cyberHistory, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                // Récupérer les notes liées au client
                $sqlNotes = "SELECT * FROM notes_globales WHERE id_client = :id ORDER BY date_note DESC";
                $stmtNotes = $pdo->prepare($sqlNotes);
                $stmtNotes->execute([':id' => $clientId]);
                $clientNotes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de connexion à la base de données.</div>';
    }
}
?>

<!-- clients_view.php -->
<!-- Inline CSS Removed for Audit -->

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

        <div class="details-card">
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

        <!-- Bloc Commentaire -->
        <div class="details-card" style="margin-top: 20px; border-left: 5px solid #2ecc71;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <h2 style="font-size: 1.25em; margin: 0; color: var(--text-color); display: flex; align-items: center; gap: 10px;">
                    <span>📝</span> Commentaire
                </h2>
                <button type="button" class="btn btn-secondary" 
                        onclick="openEditCommentModal(<?= (int)$client['ID'] ?>, `<?= addslashes($client['commentaire'] ?? '') ?>`)" 
                        style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; font-size: 0.85em; padding: 5px 10px;">
                    <span>✏️</span> Modifier / Ajouter
                </button>
            </div>
            <div style="white-space: pre-wrap; color: var(--text-color); min-height: 40px; font-style: <?= empty($client['commentaire']) ? 'italic' : 'normal' ?>; color: <?= empty($client['commentaire']) ? 'var(--text-muted)' : 'var(--text-color)' ?>; font-size: 1.05em; line-height: 1.5;">
                <?= !empty($client['commentaire']) ? htmlspecialchars($client['commentaire']) : 'Aucun commentaire pour ce client.' ?>
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
                                <th style="background: #34495e; width: 14%;">Date</th>
                                <th style="background: #34495e; width: 50%;">Titre</th>
                                <th style="background: #34495e; width: 12%;">Statut</th>
                                <th style="background: #34495e; width: 14%;">Priorité</th>
                                <th style="background: #34495e; width: 10%; text-align: center;">Actions</th>
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
                                            <button type="button" class="btn-action" style="background-color: #3498db; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; margin-right: 5px; border: none; cursor: pointer;"
                                                onclick="openViewAgendaModal(this)"
                                                data-titre="<?= htmlspecialchars($item['titre']) ?>"
                                                data-date="<?= date('d/m/Y H:i', strtotime($item['date_planifiee'])) ?>"
                                                data-statut="<?= htmlspecialchars($item['statut']) ?>"
                                                data-priorite="<?= htmlspecialchars($item['priorite']) ?>"
                                                data-description="<?= htmlspecialchars($item['description']) ?>"
                                                title="Voir le détail">
                                                👁️
                                            </button>
                                            <a href="index.php?page=agenda_edit&id=<?= $item['id'] ?>" class="btn-action" style="background-color: #e67e22; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer;" title="Modifier">
                                                ✏️
                                            </a>
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
                                <th style="background: #34495e; width: 14%;">Date</th>
                                <th style="background: #34495e; width: 14%;">Catégorie</th>
                                <th style="background: #34495e; width: 50%;">Titre</th>
                                <th style="background: #34495e; width: 12%;">Statut</th>
                                <th style="background: #34495e; width: 10%; text-align: center;">Actions</th>
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
                                        <button type="button" class="btn-action" style="background-color: #3498db; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer;" 
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

        <?php if (!empty($clientNotes)): ?>
        <div class="full-width-section">
            <div class="notes-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                        <span>📓</span> Notes Associées
                    </h2>
                </div>
                <div class="interventions-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="background: #34495e; width: 15%;">Date</th>
                                <th style="background: #34495e; width: 20%;">Titre</th>
                                <th style="background: #34495e; width: 50%;">Contenu</th>
                                <th style="background: #34495e; width: 15%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientNotes as $note): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($note['date_note'])) ?></td>
                                    <td><strong><?= htmlspecialchars($note['titre']) ?></strong></td>
                                    <td>
                                        <div style="font-size: 0.9em; color: var(--text-color);">
                                            <?= htmlspecialchars(mb_strimwidth(strip_tags($note['contenu']), 0, 150, "...")) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <?php 
                                        $noteForJs = $note;
                                        $noteForJs['client_nom'] = $client['nom'];
                                        $noteForJs['client_prenom'] = $client['prenom'];
                                        ?>
                                        <button onclick='openViewNoteModal(<?= json_encode($noteForJs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-action" style="background-color: #3498db; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; margin-right: 5px; border: none; cursor: pointer;" title="Voir la note">👁️</button>
                                        <button onclick='openEditNoteModal(<?= json_encode($noteForJs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-action" style="background-color: #f39c12; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; margin-right: 5px; border: none; cursor: pointer;" title="Modifier">✏️</button>
                                        <button onclick="confirmDeleteNote(<?= $note['id'] ?>)" class="btn-action" style="background-color: #e74c3c; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; margin-right: 5px; border: none; cursor: pointer;" title="Supprimer">🗑️</button>
                                        
                                        <?php if ($note['fichier_path']): ?>
                                            <a href="<?= htmlspecialchars($note['fichier_path']) ?>" download target="_blank" class="btn-action" style="background-color: #7f8c8d; display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px;" title="Télécharger le fichier">📥</a>
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
                                <th style="background: #34495e; width: 8%;">ID</th>
<th style="background: #34495e; width: 14%;">Date</th>
<th style="background: #34495e; width: 12%;">Statut</th>
<th style="background: #34495e; width: 56%;">Description</th>
<th style="background: #34495e; width: 10%;">Actions</th>
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
                                <th style="background: #34495e; width: 14%;">Date</th>
                                <th style="background: #34495e; width: 44%;">Description</th>
                                <th style="background: #34495e; width: 14%;">Type</th>
                                <th style="background: #34495e; width: 14%;">Montant</th>
                                <th style="background: #34495e; width: 14%;">Acompte/Solde</th>
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

        <?php if (!empty($cyberHistory)): ?>
        <div class="full-width-section">
        <div class="cyber-section" style="margin-top: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <span>🌐</span> Historique Cyber
                </h2>
            </div>

            <div class="interventions-table-container">

                    <table>
                        <thead>
                            <tr>
                                <th style="background: #34495e; width: 14%;">Date</th>
                                <th style="background: #34495e; width: 14%;">Type</th>
                                <th style="background: #34495e; width: 58%;">Description</th>
                                <th style="background: #34495e; width: 14%;">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cyberHistory as $item): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($item['date'])) ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?= htmlspecialchars($item['color']) ?>;">
                                            <?= htmlspecialchars($item['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($item['description']) ?>
                                    </td>
                                    <td style="font-weight: bold;">
                                        <?= number_format($item['montant'], 2) ?> €
                                        <?php if (!empty($item['moyen_payement'])): ?>
                                            <span style="font-size: 0.85em; font-weight: normal; color: var(--text-muted); margin-left: 5px;">
                                                par <?= htmlspecialchars($item['moyen_payement']) ?>
                                            </span>
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
    document.getElementById('client_edit_commentaire').value = `<?= htmlspecialchars_decode($client['commentaire'] ?? '', ENT_QUOTES) ?>`.trim();
    
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

<!-- Modal Modification rapide Commentaire -->
<?php include 'includes/modals/edit_client_comment.php'; ?>


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

<!-- Modal Visualisation Note -->
<div id="viewNoteModal" class="modal-overlay" style="display: none; z-index: 2000;">
    <div class="modal-content" style="max-width: 600px; width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title" id="viewNoteTitle">📓 Détail de la note</h3>
            <span class="modal-close" onclick="closeViewNoteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span id="viewNoteDate" style="color: var(--text-muted); font-size: 0.9em;"></span>
            </div>
            
            <div id="viewNoteContent" style="white-space: pre-wrap; line-height: 1.6; background: var(--card-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);"></div>
            
            <div id="viewNoteFile" style="margin-top: 20px; display: none;">
                <a href="#" target="_blank" download class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 5px;">
                    📥 Télécharger la pièce jointe
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewNoteModal()">Fermer</button>
        </div>
    </div>
</div>

<script>
function openViewNoteModal(note) {
    document.getElementById('viewNoteTitle').textContent = note.titre;
    document.getElementById('viewNoteDate').textContent = '📅 ' + new Date(note.date_note).toLocaleString('fr-FR');
    document.getElementById('viewNoteContent').textContent = note.contenu;
    
    const fileDiv = document.getElementById('viewNoteFile');
    const fileLink = fileDiv.querySelector('a');
    
    if (note.fichier_path) {
        fileDiv.style.display = 'block';
        fileLink.href = note.fichier_path;
    } else {
        fileDiv.style.display = 'none';
    }
    
    document.getElementById('viewNoteModal').style.display = 'flex';
}

function closeViewNoteModal() {
    document.getElementById('viewNoteModal').style.display = 'none';
}

// Close on outside click
window.addEventListener('click', function(event) {
    const noteModal = document.getElementById('viewNoteModal');
    if (event.target === noteModal) closeViewNoteModal();
});
</script>

<!-- INCLUDE NOTE MODAL AND JS -->
<!-- Modal Ajout/Edition Note (Copied from notes_list.php) -->
<div id="noteModal" class="modal-overlay" style="display: none; z-index: 2000;">
    <div class="modal-content" style="max-width: 800px; width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title" id="noteModalTitle">📓 Nouvelle Note</h3>
            <span class="modal-close" onclick="closeNoteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="noteAlerts"></div>
            <form id="noteForm" enctype="multipart/form-data">
                <input type="hidden" id="note_id" name="id">
                
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Titre *</label>
                        <input type="text" id="note_titre" name="titre" class="form-control" required placeholder="Titre de la note...">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Date</label>
                        <input type="datetime-local" id="note_date" name="date_note" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <div class="form-group client-search-container" style="position: relative;">
                    <label class="form-label">Client associé</label>
                    <div class="flex gap-10">
                        <div class="flex-grow relative" style="position: relative;">
                            <input type="text" id="client_search_note" class="form-control" placeholder="Rechercher un client..." autocomplete="off">
                            <input type="hidden" id="note_id_client" name="id_client">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contenu *</label>
                    <textarea id="note_contenu" name="contenu" class="form-control" rows="12" required placeholder="Votre note ici..."></textarea>
                </div>

                <div class="form-row items-center" style="display: flex; gap: 15px; align-items: center;">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Fichier joint</label>
                        <input type="file" name="fichier" class="form-control">
                        <div id="current_file_info" class="text-xs text-muted mt-5"></div>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <div class="checkbox-group" style="margin-top: 25px;">
                            <input type="checkbox" id="note_show_on_login" name="show_on_login" value="1">
                            <label for="note_show_on_login" class="form-label">Afficher sur le login</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">Annuler</button>
            <button type="button" class="btn btn-success" id="noteSaveBtn" onclick="submitNoteForm()">💾 Enregistrer</button>
        </div>
    </div>
</div>

<script>
let awesompleteClient;

document.addEventListener('DOMContentLoaded', function() {
    initClientSearchNote();
});

function initClientSearchNote() {
    const input = document.getElementById('client_search_note');
    const hiddenInput = document.getElementById('note_id_client');
    
    // Only init if elements exist
    if (!input || !hiddenInput) return;

    awesompleteClient = new Awesomplete(input, {
        minChars: 2,
        maxItems: 15,
        autoFirst: true,
        filter: function() { return true; },
        item: function(text, input) {
            const itemLabel = text.label || text; 
            const li = document.createElement("li");
            li.innerHTML = `<div style="padding: 5px 10px;">
                <div style="font-weight: 600;">${itemLabel.split(' - ')[0]}</div>
                <div style="font-size: 0.85em; color: var(--text-muted);">${itemLabel.split(' - ').slice(1).join(' - ')}</div>
            </div>`;
            return li;
        }
    });

    let lastSearchResults = [];
    let debounceTimer;

    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value;
        if (query.length < 2) return;
        
        debounceTimer = setTimeout(() => {
            fetch(`api/search_clients.php?term=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    lastSearchResults = data;
                    awesompleteClient.list = data.map(item => ({
                        label: item.label,
                        value: item.value,
                        id: item.id,
                        original: item
                    }));
                    awesompleteClient.evaluate();
                })
                .catch(err => console.error("Search error:", err));
        }, 300);
    });

    input.addEventListener('awesomplete-selectcomplete', function(e) {
        const match = lastSearchResults.find(item => 
            item.label === e.text.label || item.value === e.text.value
        );
        
        if (match) {
            hiddenInput.value = match.id;
            input.value = match.value;
        } else if (e.text && e.text.id) {
             hiddenInput.value = e.text.id;
             input.value = e.text.value || e.text;
        }
    });

    input.addEventListener('input', function() {
        if (!this.value) {
             hiddenInput.value = '';
        }
    });
}

function openEditNoteModal(note) {
    document.getElementById('noteModalTitle').textContent = '✏️ Modifier la Note';
    document.getElementById('note_id').value = note.id;
    document.getElementById('note_titre').value = note.titre;
    document.getElementById('note_date').value = note.date_note.replace(' ', 'T').slice(0, 16);
    document.getElementById('note_id_client').value = note.id_client || '';
    document.getElementById('client_search_note').value = note.id_client ? (note.client_nom + ' ' + (note.client_prenom || '')) : '';
    document.getElementById('note_contenu').value = note.contenu;
    document.getElementById('note_show_on_login').checked = note.show_on_login == 1;
    document.getElementById('current_file_info').innerHTML = note.fichier_path ? `Fichier actuel : ${note.fichier_path}` : '';
    document.getElementById('noteAlerts').innerHTML = '';
    document.getElementById('noteModal').style.display = 'flex';
    document.getElementById('noteSaveBtn').disabled = false;
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
}

function submitNoteForm() {
    const form = document.getElementById('noteForm');
    const formData = new FormData(form);
    const alerts = document.getElementById('noteAlerts');
    const btn = document.getElementById('noteSaveBtn');
    
    btn.disabled = true;
    btn.innerHTML = '⌛ Sauvegarde...';
    
    fetch('actions/notes_save.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alerts.innerHTML = `<div class="alert alert-success">✅ ${data.message}</div>`;
            setTimeout(() => location.reload(), 800);
        } else {
            alerts.innerHTML = `<div class="alert alert-danger">⚠️ ${data.error}</div>`;
            btn.disabled = false;
            btn.innerHTML = '💾 Enregistrer';
        }
    })
    .catch(e => {
        alerts.innerHTML = `<div class="alert alert-danger">⚠️ Erreur réseau ou serveur.</div>`;
        btn.disabled = false;
        btn.innerHTML = '💾 Enregistrer';
    });
}

function confirmDeleteNote(id) {
    if (confirm('Supprimer cette note définitivement ?')) {
        fetch('actions/notes_delete.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error);
        });
    }
}
</script>
<style>
/* Adjust z-index for awesomplete in this modal if needed */
#noteModal .awesomplete > ul {
    z-index: 2100 !important;
}
</style>
