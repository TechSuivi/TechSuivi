<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure les fonctions d'historique des statuts
require_once __DIR__ . '/../../utils/statuts_historique.php';

$message = '';
$intervention = null;
$intervention_id = trim($_GET['id'] ?? '');

// Fonction pour parser les données de nettoyage
function parseNettoyageData($nettoyageString) {
    if (empty($nettoyageString)) {
        return [];
    }
    
    $logiciels = [];
    $items = explode(';', $nettoyageString);
    
    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) continue;
        
        // Format: nomdulogiciel=1passé0nonpassé=information
        if (preg_match('/^([^=]+)=([01])=(.*)$/', $item, $matches)) {
            $nom = $matches[1];
            $passe = $matches[2] == '1';
            $info = $matches[3];
            
            $logiciels[] = [
                'nom' => $nom,
                'passe' => $passe,
                'info' => $info
            ];
        }
    }
    
    return $logiciels;
}

// Fonction pour convertir les données de nettoyage en string
function buildNettoyageString($logiciels) {
    $items = [];
    foreach ($logiciels as $logiciel) {
        if (!empty($logiciel['nom'])) {
            $passe = $logiciel['passe'] ? '1' : '0';
            $info = $logiciel['info'] ?? '';
            $items[] = $logiciel['nom'] . '=' . $passe . '=' . $info;
        }
    }
    return implode(';', $items);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    // Traitement spécial pour la mise à jour rapide du statut uniquement
    if (isset($_POST['action']) && $_POST['action'] === 'update_status_only') {
        $intervention_id_update = trim($_POST['intervention_id'] ?? '');
        $nouveau_statut_id = intval($_POST['statut_id'] ?? 0);
        
        if (!empty($intervention_id_update) && $nouveau_statut_id > 0) {
            try {
                // Vérifier si le nouveau statut est "Clôturée"
                $stmt_statut = $pdo->prepare("SELECT nom FROM intervention_statuts WHERE id = :statut_id");
                $stmt_statut->execute([':statut_id' => $nouveau_statut_id]);
                $statut_nom = $stmt_statut->fetchColumn();
                $is_cloturee = ($statut_nom === 'Clôturée');
                
                // Récupérer l'état actuel de l'intervention
                $stmt_current_state = $pdo->prepare("SELECT en_cours FROM inter WHERE id = :id");
                $stmt_current_state->execute([':id' => $intervention_id_update]);
                $current_en_cours = $stmt_current_state->fetchColumn();
                
                // Vérifier si la colonne statuts_historique existe
                $hasHistoriqueColumn = false;
                try {
                    $pdo->query("SELECT statuts_historique FROM inter LIMIT 1");
                    $hasHistoriqueColumn = true;
                } catch (PDOException $e) {
                    // Colonne n'existe pas encore
                }
                
                if ($hasHistoriqueColumn) {
                    // Récupérer l'historique actuel
                    $stmt_current = $pdo->prepare("SELECT statut_id, statuts_historique FROM inter WHERE id = :id");
                    $stmt_current->execute([':id' => $intervention_id_update]);
                    $current_data = $stmt_current->fetch();
                    
                    if ($current_data) {
                        $ancien_statut_id = $current_data['statut_id'];
                        $historique_actuel = $current_data['statuts_historique'] ?? '';
                        
                        // Gérer l'historique des statuts
                        // Pour la mise à jour rapide du statut, toujours ajouter à l'historique
                        // car l'utilisateur a explicitement cliqué sur "Valider le statut"
                        // (la confirmation a déjà été gérée côté JavaScript)
                        $nouvel_historique = ajouterStatutHistorique($historique_actuel, $nouveau_statut_id);
                        
                        // Mettre à jour le statut, l'historique ET le champ en_cours selon la logique de cohérence
                        if ($is_cloturee) {
                            // Si le statut est "Clôturée", forcer en_cours = 0
                            $sql = "UPDATE inter SET statut_id = :statut_id, statuts_historique = :statuts_historique, en_cours = 0 WHERE id = :id";
                        } elseif ($current_en_cours == 0) {
                            // Si le statut n'est pas "Clôturée" mais en_cours = 0, remettre en_cours = 1
                            $sql = "UPDATE inter SET statut_id = :statut_id, statuts_historique = :statuts_historique, en_cours = 1 WHERE id = :id";
                        } else {
                            // Cas normal, pas de changement de en_cours
                            $sql = "UPDATE inter SET statut_id = :statut_id, statuts_historique = :statuts_historique WHERE id = :id";
                        }
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':statut_id' => $nouveau_statut_id,
                            ':statuts_historique' => $nouvel_historique,
                            ':id' => $intervention_id_update
                        ]);
                        
                        echo '<p style="color: green;">Statut mis à jour avec succès !</p>';
                        exit;
                    }
                } else {
                    // Fallback sans historique
                    if ($is_cloturee) {
                        // Si le statut est "Clôturée", forcer en_cours = 0
                        $sql = "UPDATE inter SET statut_id = :statut_id, en_cours = 0 WHERE id = :id";
                    } elseif ($current_en_cours == 0) {
                        // Si le statut n'est pas "Clôturée" mais en_cours = 0, remettre en_cours = 1
                        $sql = "UPDATE inter SET statut_id = :statut_id, en_cours = 1 WHERE id = :id";
                    } else {
                        // Cas normal, pas de changement de en_cours
                        $sql = "UPDATE inter SET statut_id = :statut_id WHERE id = :id";
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':statut_id' => $nouveau_statut_id,
                        ':id' => $intervention_id_update
                    ]);
                    
                    echo '<p style="color: green;">Statut mis à jour avec succès !</p>';
                    exit;
                }
            } catch (PDOException $e) {
                echo '<p style="color: red;">Erreur lors de la mise à jour du statut : ' . htmlspecialchars($e->getMessage()) . '</p>';
                exit;
            }
        }
        
        echo '<p style="color: red;">Données invalides pour la mise à jour du statut.</p>';
        exit;
    }
    
    // Vérifier d'abord si l'intervention est clôturée
    $stmt_check = $pdo->prepare("SELECT en_cours FROM inter WHERE id = :id");
    $stmt_check->execute([':id' => $intervention_id]);
    $current_intervention = $stmt_check->fetch();
    
    if ($current_intervention && $current_intervention['en_cours'] == 0) {
        $message = '<div class="alert alert-error">Cette intervention est clôturée et ne peut plus être modifiée.</div>';
    } else {
    $id_client = trim($_POST['id_client'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $statut_id = intval($_POST['statut_id'] ?? 0);
    $info = trim($_POST['info'] ?? '');
    $info_log = trim($_POST['info_log'] ?? '');
    $note_user = trim($_POST['note_user'] ?? '');
    
    // Traitement des données de nettoyage
    $nettoyage_logiciels = [];
    if (isset($_POST['nettoyage_nom']) && is_array($_POST['nettoyage_nom'])) {
        for ($i = 0; $i < count($_POST['nettoyage_nom']); $i++) {
            $nom = trim($_POST['nettoyage_nom'][$i] ?? '');
            if (!empty($nom)) {
                $nettoyage_logiciels[] = [
                    'nom' => $nom,
                    'passe' => isset($_POST['nettoyage_passe'][$i]),
                    'info' => trim($_POST['nettoyage_info'][$i] ?? '')
                ];
            }
        }
    }
    $nettoyage = buildNettoyageString($nettoyage_logiciels);

    $errors = [];
    if (empty($intervention_id)) {
        $errors[] = 'ID d\'intervention manquant.';
    }
    if (empty($id_client) || !is_numeric($id_client)) {
        $errors[] = 'Veuillez sélectionner un client valide.';
    }
    if (empty($date)) {
        $errors[] = 'La date est obligatoire.';
    }
    if (empty($info)) {
        $errors[] = 'Les informations sont obligatoires.';
    }

    if (!empty($errors)) {
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
    } else {
        try {
            // Préparer la valeur statut_id
            $statut_id_value = $statut_id > 0 ? $statut_id : null;
            
            // Récupérer l'état actuel de l'intervention
            $stmt_current_state = $pdo->prepare("SELECT en_cours FROM inter WHERE id = :id");
            $stmt_current_state->execute([':id' => $intervention_id]);
            $current_en_cours = $stmt_current_state->fetchColumn();
            
            // Vérifier si le nouveau statut est "Clôturée"
            $is_cloturee = false;
            if ($statut_id_value) {
                $stmt_statut = $pdo->prepare("SELECT nom FROM intervention_statuts WHERE id = :statut_id");
                $stmt_statut->execute([':statut_id' => $statut_id_value]);
                $statut_nom = $stmt_statut->fetchColumn();
                $is_cloturee = ($statut_nom === 'Clôturée');
            }
            
            // Vérifier si la colonne statuts_historique existe
            $hasHistoriqueColumn = false;
            try {
                $pdo->query("SELECT statuts_historique FROM inter LIMIT 1");
                $hasHistoriqueColumn = true;
            } catch (PDOException $e) {
                // Colonne n'existe pas encore
            }
            
            if ($hasHistoriqueColumn) {
                // Récupérer l'historique actuel
                $stmt_current = $pdo->prepare("SELECT statut_id, statuts_historique FROM inter WHERE id = :id");
                $stmt_current->execute([':id' => $intervention_id]);
                $current_data = $stmt_current->fetch();
                
                $ancien_statut_id = $current_data['statut_id'];
                $historique_actuel = $current_data['statuts_historique'] ?? '';
                
                // Gérer l'historique des statuts
                // Ne mettre à jour l'historique que si le statut a réellement changé
                $nouvel_historique = $historique_actuel;
                if ($statut_id_value && $statut_id_value != $ancien_statut_id) {
                    $nouvel_historique = ajouterStatutHistorique($historique_actuel, $statut_id_value);
                }
                
                // Ne pas mettre à jour info_log car il doit être en lecture seule
                // Mettre à jour en_cours selon la logique de cohérence
                if ($is_cloturee) {
                    // Si le statut est "Clôturée", forcer en_cours = 0
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, statuts_historique = :statuts_historique, info = :info, nettoyage = :nettoyage, note_user = :note_user, en_cours = 0 WHERE id = :id";
                } elseif ($current_en_cours == 0 && $statut_id_value) {
                    // Si le statut n'est pas "Clôturée" mais en_cours = 0, remettre en_cours = 1
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, statuts_historique = :statuts_historique, info = :info, nettoyage = :nettoyage, note_user = :note_user, en_cours = 1 WHERE id = :id";
                } else {
                    // Cas normal, pas de changement de en_cours
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, statuts_historique = :statuts_historique, info = :info, nettoyage = :nettoyage, note_user = :note_user WHERE id = :id";
                }
                $stmt = $pdo->prepare($sql);
                
                $stmt->bindParam(':id', $intervention_id);
                $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
                $stmt->bindParam(':date', $date);
                $stmt->bindParam(':statut_id', $statut_id_value, PDO::PARAM_INT);
                $stmt->bindParam(':statuts_historique', $nouvel_historique);
                $stmt->bindParam(':info', $info);
                $stmt->bindParam(':nettoyage', $nettoyage);
                $stmt->bindParam(':note_user', $note_user);
            } else {
                // Fallback sans historique
                // Mettre à jour en_cours selon la logique de cohérence
                if ($is_cloturee) {
                    // Si le statut est "Clôturée", forcer en_cours = 0
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, info = :info, nettoyage = :nettoyage, note_user = :note_user, en_cours = 0 WHERE id = :id";
                } elseif ($current_en_cours == 0 && $statut_id_value) {
                    // Si le statut n'est pas "Clôturée" mais en_cours = 0, remettre en_cours = 1
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, info = :info, nettoyage = :nettoyage, note_user = :note_user, en_cours = 1 WHERE id = :id";
                } else {
                    // Cas normal, pas de changement de en_cours
                    $sql = "UPDATE inter SET id_client = :id_client, date = :date, statut_id = :statut_id, info = :info, nettoyage = :nettoyage, note_user = :note_user WHERE id = :id";
                }
                $stmt = $pdo->prepare($sql);
                
                $stmt->bindParam(':id', $intervention_id);
                $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
                $stmt->bindParam(':date', $date);
                $stmt->bindParam(':statut_id', $statut_id_value, PDO::PARAM_INT);
                $stmt->bindParam(':info', $info);
                $stmt->bindParam(':nettoyage', $nettoyage);
                $stmt->bindParam(':note_user', $note_user);
            }

            if ($stmt->execute()) {
                $_SESSION['edit_message'] = 'Intervention modifiée avec succès !';
                // Utiliser JavaScript pour la redirection car les headers sont déjà envoyés
                $message = '<p style="color: green;">Intervention modifiée avec succès ! Redirection en cours...</p>';
                $message .= '<script>setTimeout(function() { window.location.href = "index.php?page=interventions_view&id=' . urlencode($intervention_id) . '"; }, 1500);</script>';
            } else {
                $message = '<p style="color: red;">Erreur lors de la modification de l\'intervention.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    } // Fermeture du else pour la vérification de clôture
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<p style="color: red;">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</p>';
}

// Récupération des données existantes
if (empty($intervention_id)) {
    $message = '<p style="color: red;">ID d\'intervention manquant.</p>';
} else {
    if (isset($pdo)) {
        try {
            // Vérifier si la colonne statuts_historique existe
            $hasHistoriqueColumn = false;
            try {
                $pdo->query("SELECT statuts_historique FROM inter LIMIT 1");
                $hasHistoriqueColumn = true;
            } catch (PDOException $e) {
                // Colonne n'existe pas encore
            }
            
            if ($hasHistoriqueColumn) {
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.statut_id,
                        i.statuts_historique,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.note_user,
                        CONCAT(c.nom, ' ', c.prenom) as client_nom
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    WHERE i.id = :id
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.statut_id,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.note_user,
                        CONCAT(c.nom, ' ', c.prenom) as client_nom
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    WHERE i.id = :id
                ");
            }
            $stmt->execute([':id' => $intervention_id]);
            $intervention = $stmt->fetch();
            
            if (!$intervention) {
                $message = '<p style="color: red;">Intervention non trouvée.</p>';
            } else {
                // Pré-remplir les variables pour le formulaire
                $id_client = $intervention['id_client'];
                $client_name = $intervention['client_nom'];
                $date = $intervention['date'];
                $statut_id = $intervention['statut_id'];
                $info = $intervention['info'];
                $info_log = $intervention['info_log'];
                $note_user = $intervention['note_user'];
                $nettoyage_logiciels = parseNettoyageData($intervention['nettoyage']);
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur lors de la récupération de l\'intervention : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $message = "<p style='color: red;'>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
    }
}

// Récupération des statuts disponibles
$statuts_disponibles = [];
if (isset($pdo)) {
    try {
        // Vérifier si la table intervention_statuts existe
        $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
        $stmt_statuts = $pdo->query("SELECT id, nom, couleur FROM intervention_statuts WHERE actif = 1 ORDER BY ordre_affichage ASC, nom ASC");
        $statuts_disponibles = $stmt_statuts->fetchAll();
    } catch (PDOException $e) {
        // En cas d'erreur (table n'existe pas), on continue sans les statuts
    }
}

// Valeurs par défaut si pas encore définies
if (!isset($id_client)) $id_client = '';
if (!isset($client_name)) $client_name = '';
if (!isset($date)) $date = '';
if (!isset($statut_id)) $statut_id = null;
if (!isset($info)) $info = '';
if (!isset($info_log)) $info_log = '';
if (!isset($note_user)) $note_user = '';
if (!isset($nettoyage_logiciels)) $nettoyage_logiciels = [];
?>

<!-- Inline CSS Removed for Audit -->

<div class="intervention-page">
    <div class="page-header">
        <h1>
            <span>✏️</span>
            Modifier l'intervention
        </h1>
    </div>

    <?php if ($message): ?>
        <?php
        // Determine message type
        $isSuccess = strpos($message, 'succès') !== false || strpos($message, 'green') !== false;
        $isWarning = strpos($message, 'clôturée') !== false || strpos($message, '#fff3cd') !== false;
        $alertClass = $isSuccess ? 'alert-success' : ($isWarning ? 'alert-warning' : 'alert-success');
        $alertIcon = $isSuccess ? '✅' : ($isWarning ? '⚠️' : 'ℹ️');
        
        // Strip inline styles from message
        $cleanMessage = strip_tags($message, '<a><br><button><script>');
        ?>
        <div class="alert <?= $alertClass ?>">
            <span class="alert-icon"><?= $alertIcon ?></span>
            <div><?= $cleanMessage ?></div>
        </div>
    <?php endif; ?>

    <?php if ($intervention): ?>
        <?php if ($intervention['en_cours'] == 0): ?>
            <div class="alert alert-warning">
                <span class="alert-icon">⚠️</span>
                <div>
                    <strong>Intervention clôturée</strong><br>
                    Cette intervention est clôturée et ne peut plus être modifiée. Seules les interventions en cours peuvent être modifiées.
                </div>
            </div>
            <div class="form-actions">
                <a href="index.php?page=interventions_view&id=<?= htmlspecialchars($intervention_id) ?>" class="btn btn-primary">Voir l'intervention</a>
                <a href="index.php?page=interventions_list" class="btn btn-secondary">Retour à la liste</a>
            </div>
        <?php else: ?>
    <div class="form-card">
    <form action="index.php?page=interventions_edit&id=<?= htmlspecialchars($intervention_id) ?>" method="POST">
        <div class="form-group">
            <label for="client_search">Client</label>
            <div class="client-search-container">
                <input type="text"
                       id="client_search"
                       name="client_name"
                       class="form-control"
                       value="<?= htmlspecialchars($client_name) ?>"
                       placeholder="Tapez pour rechercher un client..."
                       required
                       autocomplete="off">
                <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($id_client) ?>">
                <div id="client_suggestions"></div>
            </div>
            <small class="form-hint">Tapez au moins 2 caractères pour rechercher par nom, prénom ou téléphone</small>
        </div>
        
        <div class="form-group">
            <label for="date">Date et heure</label>
            <input type="datetime-local" 
                   id="date" 
                   name="date" 
                   class="form-control"
                   value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($date))) ?>" 
                   required>
        </div>
        
        <?php if (!empty($statuts_disponibles)): ?>
        <div class="form-group">
            <label for="statut_id">Statut de l'intervention</label>
            <div class="status-group">
                <select id="statut_id" name="statut_id" class="form-control">
                    <option value="">-- Sélectionner un statut --</option>
                    <?php foreach ($statuts_disponibles as $statut): ?>
                        <option value="<?= $statut['id'] ?>" <?= $statut_id == $statut['id'] ? 'selected' : '' ?>
                                data-color="<?= htmlspecialchars($statut['couleur']) ?>">
                            <?= htmlspecialchars($statut['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="update-status-btn" onclick="updateStatusOnly()" class="btn btn-success"
                        title="Modifier uniquement le statut sans sauvegarder les autres champs">
                    ✓ Valider le statut
                </button>
            </div>
            <small class="form-hint">
                Utilisez le bouton "Valider le statut" pour modifier rapidement le statut sans sauvegarder les autres champs.
            </small>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <span class="alert-icon">ℹ️</span>
            <div>
                <strong>Statuts d'intervention non configurés</strong><br>
                <small>Les statuts personnalisés ne sont pas encore configurés. <a href="migrate_statuts.php">Cliquez ici pour les configurer</a>.</small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Affichage de l'historique des statuts -->
        <?php if (isset($intervention['statuts_historique']) && !empty($intervention['statuts_historique'])): ?>
        <div class="historique-card historique-statuts-container">
            <h4>📊 Historique des statuts</h4>
            <?php
            $historique = getHistoriqueComplet($pdo, $intervention['statuts_historique']);
            if (!empty($historique)):
            ?>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($historique as $index => $entry): ?>
                        <div class="historique-entry" style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-light, #eee);">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: <?= htmlspecialchars($entry['statut']['couleur']) ?>; margin-right: 10px;"></span>
                            <div style="flex: 1;">
                                <strong style="color: var(--text-color, #333);"><?= htmlspecialchars($entry['statut']['nom']) ?></strong>
                                <small style="color: var(--text-muted, #666); margin-left: 10px;">
                                    <?= formatDateHistorique($entry['date_heure']) ?>
                                    <?php if ($index < count($historique) - 1): ?>
                                        (<?= calculerDureeStatut($entry['date_heure'], $historique[$index + 1]['date_heure']) ?>)
                                    <?php else: ?>
                                        (<?= calculerDureeStatut($entry['date_heure']) ?>)
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php if ($index === 0): ?>
                                <span class="badge-actuel" style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">Actuel</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted, #666); font-style: italic; margin: 0;">Aucun historique disponible</p>
            <?php endif; ?>
        </div>
        <?php elseif (!empty($statuts_disponibles)): ?>
        <!-- Message pour activer l'historique -->
        <div class="historique-card historique-info-container" style="background-color: #e7f3ff; border-color: #b3d9ff;">
            <h4 style="color: #0066cc;">📊 Historique des statuts</h4>
            <p style="margin: 0; color: #0066cc;">
                <strong>💡 Fonctionnalité disponible !</strong><br>
                <small>L'historique des changements de statut peut être activé. <a href="migrate_historique_statuts.php" style="color: #0066cc; text-decoration: underline;">Cliquez ici pour l'activer</a>.</small>
            </p>
        </div>
        <?php endif; ?>
    
        
        <div class="form-group">
            <label for="info">Informations</label>
            <textarea id="info" 
                      name="info" 
                      rows="6" 
                      class="form-control"
                      required 
                      placeholder="Décrivez l'intervention à effectuer..."><?= htmlspecialchars($info) ?></textarea>
        </div>

        <div class="form-group">
            <label style="font-weight: bold;">Nettoyage</label>
            <div id="nettoyage-container">
                <?php if (!empty($nettoyage_logiciels)): ?>
                    <?php foreach ($nettoyage_logiciels as $index => $logiciel): ?>
                        <div class="nettoyage-item">
                            <input type="text" name="nettoyage_nom[]" value="<?= htmlspecialchars($logiciel['nom']) ?>" placeholder="Nom du logiciel">
                            <label>
                                <input type="checkbox" name="nettoyage_passe[<?= $index ?>]" <?= $logiciel['passe'] ? 'checked' : '' ?>>
                                Passé
                            </label>
                            <input type="text" name="nettoyage_info[]" value="<?= htmlspecialchars($logiciel['info']) ?>" placeholder="Information">
                            <button type="button" onclick="removeNettoyageItem(this)" class="btn btn-secondary" style="padding: 5px 10px; background: #dc3545;">Supprimer</button> </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" onclick="addNettoyageItem()" class="btn btn-success" style="margin-top: 10px;">Ajouter un logiciel</button>
        </div>
        
        <div class="form-group">
            <label for="info_log">Log d'informations (lecture seule)</label>
            <textarea id="info_log" 
                      name="info_log" 
                      rows="8" 
                      readonly 
                      class="form-control"
                      style="background-color: #1e1e1e; color: #d4d4d4; cursor: not-allowed; font-family: 'Courier New', Consolas, monospace; font-size: 0.9em; white-space: pre-wrap; word-wrap: break-word; line-height: 1.5;" 
                      placeholder="Informations techniques, logs..."><?= htmlspecialchars($info_log) ?></textarea>
            <small class="form-hint">Ce champ est en lecture seule et affiche les logs techniques au format console.</small>
        </div>
        
        <div class="form-group">
            <label for="note_user">Notes utilisateur</label>
            <textarea id="note_user" 
                      name="note_user" 
                      rows="4" 
                      class="form-control"
                      placeholder="Notes personnelles..."><?= htmlspecialchars($note_user) ?></textarea>
        </div>
        
        <!-- Section Photos -->
        <div class="form-group">
            <label style="font-weight: bold;">Photos</label>
            
            <!-- Zone d'upload -->
            <div class="photo-upload-zone">
                <input type="file" id="photo-upload" accept="image/*" multiple style="display: none;">
                <button type="button" onclick="document.getElementById('photo-upload').click()" class="btn btn-primary" style="margin-bottom: 10px;">
                    📷 Ajouter des photos
                </button>
                <p style="margin: 0; color: var(--text-muted, #666); font-size: 14px;">
                    Formats acceptés: JPG, PNG, GIF, WebP (max 10MB par photo)
                </p>
            </div>
            
            <!-- Galerie de photos -->
            <div id="photos-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                <!-- Les photos seront chargées ici via JavaScript -->
            </div>
            
            <!-- Zone de progression d'upload -->
            <div id="upload-progress" style="display: none; margin-top: 15px;">
                <div style="background-color: var(--bg-secondary, #f0f0f0); border-radius: 4px; overflow: hidden;">
                    <div id="progress-bar" style="height: 20px; background-color: #28a745; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <p id="upload-status" style="margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted, #666);"></p>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span>✔️</span>
                Modifier l'intervention
            </button>
            <a href="index.php?page=interventions_view&id=<?= htmlspecialchars($intervention_id) ?>" class="btn btn-secondary">
                <span>❌</span>
                Annuler
            </a>
            <a href="pwa/?intervention_id=<?= htmlspecialchars($intervention_id) ?>" target="_blank" class="btn btn-success">
                <span>📱</span>
                App Mobile
            </a>
        </div>
    </div>
</form>

<script>
let nettoyageIndex = <?= count($nettoyage_logiciels) ?>;

function addNettoyageItem() {
    const container = document.getElementById('nettoyage-container');
    const div = document.createElement('div');
    div.className = 'nettoyage-item';
    div.style.cssText = 'display: flex; align-items: center; margin-bottom: 10px; padding: 10px; border: 1px solid var(--border-color, #ddd); border-radius: 4px; background-color: var(--bg-secondary, #f9f9f9);';
    
    div.innerHTML = `
        <input type="text" name="nettoyage_nom[]" placeholder="Nom du logiciel" style="flex: 1; margin-right: 10px; padding: 5px; border: 1px solid var(--border-color, #ccc); border-radius: 4px; background-color: var(--input-bg, white); color: var(--text-color, #333);">
        <label style="margin-right: 10px; white-space: nowrap; color: var(--text-color, #333);">
            <input type="checkbox" name="nettoyage_passe[${nettoyageIndex}]" style="margin-right: 5px;">
            Passé
        </label>
        <input type="text" name="nettoyage_info[]" placeholder="Information" style="flex: 1; margin-right: 10px; padding: 5px; border: 1px solid var(--border-color, #ccc); border-radius: 4px; background-color: var(--input-bg, white); color: var(--text-color, #333);">
        <button type="button" onclick="removeNettoyageItem(this)" style="padding: 5px 10px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Supprimer</button>
    `;
    
    container.appendChild(div);
    nettoyageIndex++;
}

function removeNettoyageItem(button) {
    button.parentElement.remove();
}

document.addEventListener('DOMContentLoaded', function() {
    const clientSearch = document.getElementById('client_search');
    const clientId = document.getElementById('id_client');
    const suggestions = document.getElementById('client_suggestions');
    let searchTimeout;
    let selectedIndex = -1;

    // Fonction pour effectuer la recherche
    function searchClients(query) {
        if (query.length < 2) {
            suggestions.style.display = 'none';
            return;
        }

        fetch(`api/search_clients.php?q=${encodeURIComponent(query)}&limit=10`)
            .then(response => response.json())
            .then(data => {
                suggestions.innerHTML = '';
                selectedIndex = -1;

                if (data.length === 0) {
                    suggestions.innerHTML = '<div style="padding: 8px; color: var(--text-muted, #666); font-style: italic;">Aucun client trouvé</div>';
                } else {
                    data.forEach((client, index) => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid var(--border-light, #eee); color: var(--text-color, #333); transition: background-color 0.2s ease;';
                        div.innerHTML = client.label;
                        div.dataset.id = client.id;
                        div.dataset.value = client.value;
                        div.dataset.index = index;

                        div.addEventListener('mouseenter', function() {
                            suggestions.querySelectorAll('div').forEach(d => d.style.backgroundColor = '');
                            this.style.backgroundColor = 'var(--hover-color, #f0f0f0)';
                            selectedIndex = parseInt(this.dataset.index);
                        });

                        div.addEventListener('click', function() {
                            selectClient(this.dataset.id, this.dataset.value);
                        });

                        suggestions.appendChild(div);
                    });
                }

                suggestions.style.display = 'block';
            })
            .catch(error => {
                console.error('Erreur lors de la recherche:', error);
                suggestions.innerHTML = '<div style="padding: 8px; color: var(--error-color, red);">Erreur lors de la recherche</div>';
                suggestions.style.display = 'block';
            });
    }

    // Fonction pour sélectionner un client
    function selectClient(id, name) {
        clientId.value = id;
        clientSearch.value = name;
        suggestions.style.display = 'none';
        selectedIndex = -1;
    }

    // Événement de saisie dans le champ de recherche
    clientSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Réinitialiser l'ID si le champ est vidé
        if (query === '') {
            clientId.value = '';
        }

        // Annuler la recherche précédente
        clearTimeout(searchTimeout);
        
        // Lancer une nouvelle recherche après un délai
        searchTimeout = setTimeout(() => {
            searchClients(query);
        }, 300);
    });

    // Gestion des touches du clavier
    clientSearch.addEventListener('keydown', function(e) {
        const items = suggestions.querySelectorAll('div[data-index]');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                const item = items[selectedIndex];
                selectClient(item.dataset.id, item.dataset.value);
            }
        } else if (e.key === 'Escape') {
            suggestions.style.display = 'none';
            selectedIndex = -1;
        }
    });

    // Fonction pour mettre à jour la sélection visuelle
    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.style.backgroundColor = 'var(--hover-color, #f0f0f0)';
            } else {
                item.style.backgroundColor = '';
            }
        });
    }

    // Fermer les suggestions en cliquant ailleurs
    document.addEventListener('click', function(e) {
        if (!clientSearch.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
            selectedIndex = -1;
        }
    });

    // Validation du formulaire
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!clientId.value) {
            e.preventDefault();
            alert('Veuillez sélectionner un client dans la liste de suggestions.');
            clientSearch.focus();
        }
    });
});

// Fonction pour mettre à jour uniquement le statut
function updateStatusOnly() {
    const statutSelect = document.getElementById('statut_id');
    const nouveauStatutId = statutSelect.value;
    const updateBtn = document.getElementById('update-status-btn');
    
    if (!nouveauStatutId) {
        alert('Veuillez sélectionner un statut avant de valider.');
        return;
    }
    
    // Vérifier si le statut a changé par rapport au statut actuel
    const statutActuel = '<?= $statut_id ?>';
    if (nouveauStatutId === statutActuel) {
        // Le statut n'a pas changé, demander confirmation
        const confirmation = confirm(
            'Le statut sélectionné est identique au statut actuel.\n\n' +
            'Voulez-vous vraiment ajouter une nouvelle entrée dans l\'historique des statuts ?\n\n' +
            'Cela créera un doublon dans l\'historique (par exemple pour marquer qu\'un client a été "téléphoné" plusieurs fois).'
        );
        
        if (!confirmation) {
            return; // L'utilisateur a annulé
        }
    }
    
    // Désactiver le bouton pendant la requête
    updateBtn.disabled = true;
    updateBtn.textContent = '⏳ Mise à jour...';
    updateBtn.style.backgroundColor = '#6c757d';
    
    // Préparer les données pour la mise à jour du statut uniquement
    const formData = new FormData();
    formData.append('action', 'update_status_only');
    formData.append('intervention_id', currentInterventionId);
    formData.append('statut_id', nouveauStatutId);
    
    fetch('index.php?page=interventions_edit&id=' + encodeURIComponent(currentInterventionId), {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Vérifier si la mise à jour a réussi
        if (data.includes('Statut mis à jour avec succès')) {
            // Afficher un message de succès
            const successMsg = document.createElement('div');
            successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background-color: #28a745; color: white; padding: 15px 20px; border-radius: 8px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
            successMsg.innerHTML = '✅ Statut mis à jour avec succès !';
            document.body.appendChild(successMsg);
            
            // Supprimer le message après 3 secondes
            setTimeout(() => {
                successMsg.remove();
            }, 3000);
            
            // Recharger la page pour afficher l'historique mis à jour
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            alert('Erreur lors de la mise à jour du statut. Veuillez réessayer.');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de connexion lors de la mise à jour du statut.');
    })
    .finally(() => {
        // Réactiver le bouton
        updateBtn.disabled = false;
        updateBtn.textContent = '✓ Valider le statut';
        updateBtn.style.backgroundColor = '#28a745';
    });
}

// Gestion des photos
let currentInterventionId = '<?= htmlspecialchars($intervention_id) ?>';

// Charger les photos existantes
function loadPhotos() {
    fetch(`api/photos.php?intervention_id=${encodeURIComponent(currentInterventionId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPhotos(data.data);
            } else {
                console.error('Erreur lors du chargement des photos:', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

// Afficher les photos dans la galerie
function displayPhotos(photos) {
    const gallery = document.getElementById('photos-gallery');
    gallery.innerHTML = '';
    
    photos.forEach(photo => {
        const photoDiv = document.createElement('div');
        photoDiv.style.cssText = 'position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background-color: var(--bg-color, white);';
        
        photoDiv.innerHTML = `
            <img src="${photo.thumbnail_url}" alt="${photo.original_filename}"
                 style="width: 100%; height: 120px; object-fit: cover; cursor: pointer;"
                 onclick="openPhotoModal('${photo.url}', '${photo.original_filename}', '${photo.description || ''}')">
            <div style="padding: 8px;">
                <p style="margin: 0; font-size: 12px; color: var(--text-muted, #666); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${photo.original_filename}">
                    ${photo.original_filename}
                </p>
                ${photo.description ? `<p style="margin: 4px 0 0 0; font-size: 11px; color: var(--text-muted, #888);">${photo.description}</p>` : ''}
                <button onclick="deletePhoto(${photo.id})"
                        style="position: absolute; top: 5px; right: 5px; background-color: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;"
                        title="Supprimer la photo">×</button>
            </div>
        `;
        
        gallery.appendChild(photoDiv);
    });
}

// Gestion de l'upload de photos
document.getElementById('photo-upload').addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;
    
    uploadPhotos(files);
});

// Upload des photos
function uploadPhotos(files) {
    console.log('🔍 Début upload de', files.length, 'fichier(s)');
    console.log('🔍 ID intervention:', currentInterventionId);
    
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    const statusText = document.getElementById('upload-status');
    
    progressDiv.style.display = 'block';
    let completed = 0;
    const total = files.length;
    
    statusText.textContent = `Upload en cours... (0/${total})`;
    
    files.forEach((file, index) => {
        console.log(`🔍 Upload fichier ${index + 1}:`, file.name, file.size, 'bytes');
        
        const formData = new FormData();
        formData.append('photo', file);
        formData.append('intervention_id', currentInterventionId);
        
        console.log('🔍 FormData créé, envoi vers api/photos.php');
        
        fetch('api/photos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('🔍 Réponse reçue, status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('🔍 Données JSON reçues:', data);
            completed++;
            const progress = (completed / total) * 100;
            progressBar.style.width = progress + '%';
            statusText.textContent = `Upload en cours... (${completed}/${total})`;
            
            if (!data.success) {
                console.error(`❌ Erreur upload ${file.name}:`, data.message);
                alert(`Erreur upload ${file.name}: ${data.message}`);
            } else {
                console.log(`✅ Upload réussi ${file.name}`);
            }
            
            if (completed === total) {
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                    loadPhotos(); // Recharger la galerie
                    document.getElementById('photo-upload').value = ''; // Reset input
                }, 1000);
            }
        })
        .catch(error => {
            console.error('❌ Erreur upload:', error);
            alert(`Erreur réseau: ${error.message}`);
            completed++;
            if (completed === total) {
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                    loadPhotos();
                    document.getElementById('photo-upload').value = '';
                }, 1000);
            }
        });
    });
}

// Supprimer une photo
function deletePhoto(photoId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette photo ?')) {
        return;
    }
    
    fetch(`api/photos.php?photo_id=${photoId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPhotos(); // Recharger la galerie
        } else {
            alert('Erreur lors de la suppression: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression');
    });
}

// Modal pour afficher les photos en grand
function openPhotoModal(imageUrl, filename, description) {
    // Créer le modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.8); z-index: 10000;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
    `;
    
    modal.innerHTML = `
        <div style="max-width: 90%; max-height: 90%; position: relative;" onclick="event.stopPropagation()">
            <img src="${imageUrl}" alt="${filename}"
                 style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
            <div style="position: absolute; bottom: -40px; left: 0; right: 0; text-align: center; color: white; font-size: 14px;">
                <p style="margin: 0;">${filename}</p>
                ${description ? `<p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">${description}</p>` : ''}
            </div>
            <button onclick="this.parentElement.parentElement.remove()"
                    style="position: absolute; top: -10px; right: -10px; background-color: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 16px;">×</button>
        </div>
    `;
    
    // Fermer en cliquant sur le fond
    modal.addEventListener('click', function() {
        modal.remove();
    });
    
    document.body.appendChild(modal);
}

// Charger les photos au chargement de la page
if (currentInterventionId) {
    loadPhotos();
}
</script>
    <?php endif; // Fin de la condition pour intervention en cours ?>

<?php else: ?>
    <div style="margin-top: 20px;">
        <a href="index.php?page=interventions_list" style="text-decoration: none; padding: 10px 15px; background-color: #6c757d; color: white; border-radius: 4px;">Retour à la liste des interventions</a>
    </div>
<?php endif; ?>