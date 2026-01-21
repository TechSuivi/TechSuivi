<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';
$editData = null;

// Vérifier si on est en mode édition
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
        if (!$editData) {
            $errorMessage = "Session non trouvée.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération de la session : " . htmlspecialchars($e->getMessage());
    }
}

// Récupération des configurations de tarifs cyber
$cyber_config = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'cyber_pricing'");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $cyber_config[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {
    // Utiliser les valeurs par défaut si erreur
}

// Valeurs par défaut si pas de configuration
$price_nb_page = floatval($cyber_config['cyber_price_nb_page'] ?? '0.20');
$price_color_page = floatval($cyber_config['cyber_price_color_page'] ?? '0.30');
$price_time_base = floatval($cyber_config['cyber_price_time_base'] ?? '0.75');
$price_time_minimum = floatval($cyber_config['cyber_price_time_minimum'] ?? '0.50');
$time_minimum_threshold = intval($cyber_config['cyber_time_minimum_threshold'] ?? '10');
$time_increment = intval($cyber_config['cyber_time_increment'] ?? '15');

// Récupération des crédits clients actifs
$credits_clients = [];
try {
    $stmt = $pdo->query("SELECT id, nom_client, solde_actuel FROM FC_cyber_credits WHERE actif = 1 ORDER BY nom_client ASC");
    $credits_clients = $stmt->fetchAll();
} catch (PDOException $e) {
    // Pas critique, on continue sans les crédits
}

// Vérifier si on vient avec un crédit pré-sélectionné
$credit_preselectionne = null;
if (isset($_GET['credit_id']) && is_numeric($_GET['credit_id'])) {
    $credit_id = (int)$_GET['credit_id'];
    foreach ($credits_clients as $credit) {
        if ($credit['id'] == $credit_id) {
            $credit_preselectionne = $credit;
            break;
        }
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '') ?: 'CYBER';
    $ha = !empty($_POST['ha']) ? $_POST['ha'] : null;
    $hd = !empty($_POST['hd']) ? $_POST['hd'] : null;
    $imp_nb = !empty($_POST['imp_nb']) ? (int)$_POST['imp_nb'] : null;
    $imp_couleur = !empty($_POST['imp_couleur']) ? (int)$_POST['imp_couleur'] : null;
    $tarif_specifique = !empty($_POST['tarif']) ? $_POST['tarif'] : null;
    $moyen_payement = $_POST['moyen_payement'] ?? '';
    $credit_client_id = !empty($_POST['credit_client_id']) ? (int)$_POST['credit_client_id'] : null;
    $id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
    // DEBUG TEMP
    if($id_client === null && !empty($_POST['nom'])) {
        // error_log("CyberAdd: ID Client is NULL for nom: " . $_POST['nom']);
    }
    // FIN DEBUG
    $paye_par_credit = isset($_POST['paye_par_credit']) && $_POST['paye_par_credit'] === '1' ? 1 : 0;
    $ajout_credit = isset($_POST['ajout_credit']) && $_POST['ajout_credit'] === '1' ? 1 : 0;
    
// Vérifier quel bouton a été cliqué
    $is_save_only = isset($_POST['save_only']);

    // Si payé par crédit, forcer le moyen de paiement
    if ($paye_par_credit) {
        $moyen_payement = 'Crédit client';
    }
    $banque = trim($_POST['banque'] ?? '');
    $num_cheque = trim($_POST['num_cheque'] ?? '');
    
    // Construction de info_chq si c'est un chèque
    $info_chq = '';
    if ($moyen_payement === 'Chèque' && ($banque || $num_cheque)) {
        $info_chq = "banque={$banque}|numero={$num_cheque}";
    }
    
    // Calcul du coût global si pas de tarif spécifique
    $tarif_global = null;
    if (!$tarif_specifique) {
        $prix_temps = 0;
        $prix_impressions = 0;
        
        // Calcul du prix du temps
        if ($ha && $hd) {
            $debut = new DateTime($ha);
            $fin = new DateTime($hd);
            $diff = $debut->diff($fin);
            $minutes = ($diff->h * 60) + $diff->i;
            
            if ($minutes <= $time_minimum_threshold) {
                $prix_temps = $price_time_minimum;
            } else {
                $prix_temps = ceil($minutes / $time_increment) * $price_time_base;
            }
        }
        
        // Calcul du prix des impressions
        if ($imp_nb) {
            $prix_impressions += $imp_nb * $price_nb_page;
        }
        if ($imp_couleur) {
            $prix_impressions += $imp_couleur * $price_color_page;
        }
        
        $tarif_global = $prix_temps + $prix_impressions;
        $tarif_global = $tarif_global > 0 ? $tarif_global : null;
    } else {
        $tarif_global = $tarif_specifique;
    }
    
    // Validation
    $errors = [];
    
    if ($ha && $hd) {
        $debut = new DateTime($ha);
        $fin = new DateTime($hd);
        if ($fin <= $debut) {
            $errors[] = "L'heure de départ doit être postérieure à l'heure d'arrivée.";
        }
    }
    
    // Validation du moyen de paiement (sauf si payé par crédit ou si sauvegarde simple)
    if (!$paye_par_credit && !$is_save_only && empty($moyen_payement)) {
        $errors[] = "Le moyen de paiement est obligatoire pour valider le paiement.";
    }
    
    // Validation spécifique au crédit
    if ($paye_par_credit && $credit_client_id) {
        // Vérifier que le crédit existe
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber_credits WHERE id = ? AND actif = 1");
        $stmt->execute([$credit_client_id]);
        $credit_info = $stmt->fetch();
        
        if (!$credit_info) {
            $errors[] = "Crédit client non trouvé.";
        } elseif ($credit_info['solde_actuel'] < $tarif_global) {
            // Avertissement pour solde insuffisant mais on permet quand même (découvert)
            $solde_apres_deduction = $credit_info['solde_actuel'] - $tarif_global;
            $_SESSION['credit_warning'] = "⚠️ ATTENTION : Cette opération va mettre le compte en découvert !<br>" .
                                        "Solde actuel: " . number_format($credit_info['solde_actuel'], 2) . " €<br>" .
                                        "Montant à déduire: " . number_format($tarif_global, 2) . " €<br>" .
                                        "Solde après opération: <strong style='color: red;'>" . number_format($solde_apres_deduction, 2) . " €</strong>";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($editData) {
                // Modification - on garde les tarifs existants pour préserver l'historique
                $stmt = $pdo->prepare("UPDATE FC_cyber SET nom = ?, ha = ?, hd = ?, imp = ?, imp_c = ?, tarif = ?, moyen_payement = ?, info_chq = ?, credit_id = ?, paye_par_credit = ?, id_client = ? WHERE id = ?");
                $stmt->execute([$nom, $ha, $hd, $imp_nb, $imp_couleur, $tarif_global, $moyen_payement, $info_chq, $credit_client_id, $paye_par_credit, $id_client, $editData['id']]);
                $_SESSION['cyber_message'] = "Session modifiée avec succès.";
            } else {
                // Ajout - on sauvegarde les tarifs actuels avec la session
                $stmt = $pdo->prepare("INSERT INTO FC_cyber (nom, ha, hd, imp, imp_c, tarif, moyen_payement, info_chq, price_nb_page, price_color_page, price_time_base, price_time_minimum, time_minimum_threshold, time_increment, credit_id, paye_par_credit, id_client) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $ha, $hd, $imp_nb, $imp_couleur, $tarif_global, $moyen_payement, $info_chq, $price_nb_page, $price_color_page, $price_time_base, $price_time_minimum, $time_minimum_threshold, $time_increment, $credit_client_id, $paye_par_credit, $id_client]);
                $session_id = $pdo->lastInsertId();
                
                // Si c'est un ajout de crédit, ajouter au solde et enregistrer le mouvement
                if ($ajout_credit && $credit_client_id && $tarif_global > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM FC_cyber_credits WHERE id = ?");
                    $stmt->execute([$credit_client_id]);
                    $credit_info = $stmt->fetch();
                    
                    $nouveau_solde = $credit_info['solde_actuel'] + $tarif_global;
                    
                    // Mettre à jour le solde
                    $stmt = $pdo->prepare("UPDATE FC_cyber_credits SET solde_actuel = ? WHERE id = ?");
                    $stmt->execute([$nouveau_solde, $credit_client_id]);
                    
                    // Enregistrer le mouvement
                    $stmt = $pdo->prepare("
                        INSERT INTO FC_cyber_credits_historique
                        (credit_id, type_mouvement, montant, solde_avant, solde_apres, description, session_cyber_id, utilisateur)
                        VALUES (?, 'AJOUT', ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $credit_client_id,
                        $tarif_global,
                        $credit_info['solde_actuel'],
                        $nouveau_solde,
                        $_POST['credit_description'] ?? "Ajout de crédit - " . $moyen_payement,
                        $session_id,
                        $_SESSION['username'] ?? 'Système'
                    ]);
                    
                    $_SESSION['cyber_message'] = "Crédit ajouté avec succès.";
                }
                // Si payé par crédit, déduire du solde mais NE PAS créer de session (argent déjà en société)
                elseif ($paye_par_credit && $credit_client_id && $tarif_global > 0) {
                    // Supprimer la session qui vient d'être créée car c'est un paiement par crédit
                    $stmt = $pdo->prepare("DELETE FROM FC_cyber WHERE id = ?");
                    $stmt->execute([$session_id]);
                    
                    $stmt = $pdo->prepare("SELECT * FROM FC_cyber_credits WHERE id = ?");
                    $stmt->execute([$credit_client_id]);
                    $credit_info = $stmt->fetch();
                    
                    $nouveau_solde = $credit_info['solde_actuel'] - $tarif_global;
                    
                    // Mettre à jour le solde
                    $stmt = $pdo->prepare("UPDATE FC_cyber_credits SET solde_actuel = ? WHERE id = ?");
                    $stmt->execute([$nouveau_solde, $credit_client_id]);
                    
                    // Enregistrer le mouvement UNIQUEMENT dans l'historique (pas de session_cyber_id)
                    $stmt = $pdo->prepare("
                        INSERT INTO FC_cyber_credits_historique
                        (credit_id, type_mouvement, montant, solde_avant, solde_apres, description, utilisateur)
                        VALUES (?, 'DEDUCTION', ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $credit_client_id,
                        $tarif_global,
                        $credit_info['solde_actuel'],
                        $nouveau_solde,
                        "Paiement session cyber - " . $nom,
                        $_SESSION['username'] ?? 'Système'
                    ]);
                    
                    $_SESSION['cyber_message'] = "Session payée par crédit avec succès.";
                } else {
                    $_SESSION['cyber_message'] = "Session enregistrée avec succès.";
                }
            }
            
            $pdo->commit();
            echo '<script>window.location.href = "index.php?page=cyber_list";</script>';
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Récupération des tarifs paramétrés
$tarifs_parametres = [];
try {
    $stmt = $pdo->query("SELECT * FROM FC_parametres_tarifs ORDER BY type_tarif");
    $tarifs_parametres = $stmt->fetchAll();
} catch (PDOException $e) {
    // Pas critique, on continue sans les tarifs paramétrés
}
?>


<style>
/* Modern Cyber Session Layout */
.cost-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    background: var(--secondary-bg, rgba(0,0,0,0.03));
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
    align-items: center;
    border: 1px solid var(--border-color);
}

.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.payment-box, .credit-box, .success-box, .empty-box {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm, 0 2px 4px rgba(0,0,0,0.05));
    height: 100%;
    transition: transform 0.3s ease;
}

.empty-box {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: var(--text-muted);
}

.payment-box:hover, .credit-box:hover, .success-box:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md, 0 4px 8px rgba(0,0,0,0.1));
}

.payment-box { border-top: 4px solid var(--accent-color, #10b981); }
.credit-box { border-top: 4px solid var(--info-color, #3b82f6); }
.success-box { border-top: 4px solid var(--success-color, #28a745); }

.box-title, .box-title-info, .box-title-success {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.box-title { color: var(--accent-color); }
.box-title-info { color: var(--info-color); }
.box-title-success { color: var(--success-color); }

.info-bubble {
    background: var(--bg-color, #f8f9fa);
    padding: 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-bottom: 15px;
    border-left: 3px solid var(--border-color);
}

.info-bubble div {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.info-bubble div:last-child {
    margin-bottom: 0;
}

.form-control-sm {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
    color: var(--text-color);
    font-size: 0.9rem;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* Custom grid for the form fields */
.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

@media (max-width: 992px) {
    .form-grid-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .cost-summary-grid, .payment-methods-grid, .form-grid-3 {
        grid-template-columns: 1fr;
    }
    .cost-summary-grid .border-l {
        border-left: none;
        border-top: 1px solid var(--border-color);
        padding-top: 10px;
        padding-left: 0;
    }
}
</style>

<h1><?= $editData ? 'Modifier la session' : 'Nouvelle session cyber' ?></h1>

<p><a href="index.php?page=cyber_list" style="color: var(--accent-color);">← Retour à la liste</a></p>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['credit_warning'])): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Avertissement Crédit :</strong><br>
        <?= $_SESSION['credit_warning'] ?>
        <br><br>
        <small><em>L'opération sera quand même autorisée (découvert accepté).</em></small>
    </div>
    <?php unset($_SESSION['credit_warning']); ?>
<?php endif; ?>

<div style="max-width: 1200px; margin: 0 auto;">
<form method="POST" class="card">
    
    
    <!-- Informations principales -->
    <div class="form-grid-3 mb-15">
        <div>
            <label for="nom">Nom / Référence :</label>
            <div style="position: relative;">
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($editData['nom'] ?? $_POST['nom'] ?? 'CYBER') ?>"
                       class="form-control"
                       style="padding-right: 30px;"
                       placeholder="CYBER">
                <span id="client_link_status" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: none; cursor: help;" title="Client lié">
                    ✅
                </span>
            </div>
            <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($editData['id_client'] ?? $_POST['id_client'] ?? '') ?>">
        </div>
        
        <div>
            <label for="ha">Heure d'arrivée :</label>
            <input type="time" id="ha" name="ha"
                   value="<?= $editData['ha'] ?? $_POST['ha'] ?? '' ?>"
                   class="form-control">
        </div>
        
        <div>
            <label for="hd">Heure de départ :</label>
            <input type="time" id="hd" name="hd"
                   value="<?= $editData['hd'] ?? $_POST['hd'] ?? '' ?>"
                   class="form-control">
        </div>
    </div>
    
    <!-- Impressions et tarif -->
    <div class="payment-methods-grid mb-15">
        <div>
            <label for="imp_nb">Pages N&B :</label>
            <input type="number" id="imp_nb" name="imp_nb" min="0"
                   value="<?= htmlspecialchars($editData['imp'] ?? $_POST['imp_nb'] ?? '') ?>"
                   class="form-control"
                   placeholder="0">
            <small class="text-muted text-xs">
                <?= number_format($price_nb_page, 2) ?> €/page
            </small>
        </div>
        
        <div>
            <label for="imp_couleur">Pages couleur :</label>
            <input type="number" id="imp_couleur" name="imp_couleur" min="0"
                   value="<?= htmlspecialchars($editData['imp_c'] ?? $_POST['imp_couleur'] ?? '') ?>"
                   class="form-control"
                   placeholder="0">
            <small class="text-muted text-xs">
                <?= number_format($price_color_page, 2) ?> €/page
            </small>
        </div>
        
        <div>
            <label for="tarif_saisi">Tarif spécifique (€) :</label>
            <div class="flex gap-5 align-center">
                <input type="number" id="tarif_saisi" step="0.01" min="0"
                       value="<?= htmlspecialchars($editData['tarif'] ?? $_POST['tarif'] ?? '') ?>"
                       class="form-control flex-1"
                       placeholder="Auto">
                <input type="hidden" id="tarif" name="tarif" value="<?= htmlspecialchars($editData['tarif'] ?? $_POST['tarif'] ?? '') ?>">
                
                <div class="flex-col gap-2">
                    <label class="flex align-center gap-3 text-xs pointer whitespace-nowrap" title="Ajouter le prix des impressions au tarif spécifique">
                        <input type="checkbox" id="add_prints_option">
                        ➕ Imp.
                    </label>
                    
                    <label class="flex align-center gap-3 text-xs pointer whitespace-nowrap" title="Ajouter le prix du temps cyber au tarif spécifique">
                        <input type="checkbox" id="add_cyber_option">
                        ➕ Cyber
                    </label>
                </div>
                
                <button type="button" id="recalculer_tarif"
                        class="btn-sm btn-secondary font-bold"
                        title="Recalculer le tarif automatiquement">
                    🔄
                </button>
            </div>
            <small class="text-muted text-xs">
                Vide = calcul auto
            </small>
        </div>
    </div>
    
    <!-- Résumé des coûts -->
    <!-- Résumé des coûts -->
    <!-- Résumé des coûts -->
    <div class="cost-summary-grid">
        <div class="text-center text-sm">
            <span class="text-muted">Temps :</span> <strong class="text-accent"><span id="tarif_cyber">0,00 €</span></strong>
        </div>
        <div class="text-center text-sm">
            <span class="text-muted">Impressions :</span> <strong class="text-accent"><span id="total_impressions">0,00 €</span></strong>
        </div>
        <div class="text-center border-l pl-10">
            <div class="text-xs uppercase text-muted mb-2">Total à payer</div>
            <strong class="text-success text-2xl"><span id="cout_total_display">0,00 €</span></strong>
        </div>
    </div>
    
    <!-- Sections de paiement et gestion crédit -->
    <div class="payment-methods-grid mb-15">
        <!-- Paiement classique -->
        <div id="paiement_classique" class="payment-box">
            <h4 class="box-title">💰 Paiement classique</h4>
            
            <div class="mb-10">
                <select id="moyen_payement" name="moyen_payement" class="form-control-sm w-full">
                    <option value="">Sélectionner...</option>
                    <?php
                    // Récupération des moyens de paiement
                    try {
                        $stmt = $pdo->query("SELECT moyen FROM FC_moyens_paiement ORDER BY moyen ASC");
                        $moyens = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($moyens as $moyen) {
                            $selected = ($editData['moyen_payement'] ?? '') === $moyen ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($moyen) . "\" {$selected}>" . htmlspecialchars($moyen) . "</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option value=\"Espèces\">Espèces</option>";
                        echo "<option value=\"Chèque\">Chèque</option>";
                        echo "<option value=\"Carte bancaire\">Carte bancaire</option>";
                    }
                    ?>
                </select>
            </div>
            
            <!-- Champs spécifiques au chèque -->
            <div id="cheque_fields" style="display: none;">
                <div class="grid-2 gap-8">
                    <input type="text" id="banque" name="banque"
                           value="<?php
                           if ($editData && $editData['info_chq']) {
                               $info_parts = explode('|', $editData['info_chq']);
                               foreach ($info_parts as $part) {
                                   if (strpos($part, 'banque=') === 0) {
                                       echo htmlspecialchars(substr($part, 7));
                                       break;
                                   }
                               }
                           }
                           ?>"
                           class="form-control-sm"
                           placeholder="Banque">
                    <input type="text" id="num_cheque" name="num_cheque"
                           value="<?php
                           if ($editData && $editData['info_chq']) {
                               $info_parts = explode('|', $editData['info_chq']);
                               foreach ($info_parts as $part) {
                                   if (strpos($part, 'numero=') === 0) {
                                       echo htmlspecialchars(substr($part, 7));
                                       break;
                                   }
                               }
                           }
                           ?>"
                           class="form-control-sm"
                           placeholder="N° chèque">
                </div>
            </div>
            
            <?php if (!$editData): ?>
            <!-- Bouton paiement classique -->
            <div class="mt-10">
                <button type="button" id="payer_maintenant" class="btn btn-accent w-full btn-sm">
                    💰 Payer maintenant
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Paiement par crédit -->
        <?php if (!empty($credits_clients)): ?>
        <div class="credit-box">
            <h4 class="box-title-info">💳 Paiement par crédit</h4>
            
            <div class="mb-10">
                <select id="credit_client_id" name="credit_client_id" class="form-control-sm w-full">
                    <option value="">Sélectionner un client...</option>
                    <?php foreach ($credits_clients as $credit): ?>
                        <option value="<?= $credit['id'] ?>"
                                data-solde="<?= $credit['solde_actuel'] ?>"
                                <?= $credit_preselectionne && $credit['id'] == $credit_preselectionne['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($credit['nom_client']) ?>
                            (<?= number_format($credit['solde_actuel'], 2) ?> €)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="credit_info" class="info-bubble">
                <div><strong>Disponible :</strong> <span id="solde_disponible">0,00 €</span></div>
                <div><strong>Session :</strong> <span id="montant_session">0,00 €</span></div>
                <div><strong>Après :</strong> <span id="solde_apres" class="font-bold">0,00 €</span></div>
            </div>
            
            <?php if (!$editData): ?>
            <!-- Bouton paiement par crédit -->
            <div class="mt-10">
                <button type="button" id="payer_par_credit" class="btn btn-info w-full btn-sm">
                    💳 Payer par crédit
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div></div>
        <?php endif; ?>

        <!-- Ajouter crédit -->
        <?php if (!empty($credits_clients)): ?>
        <div class="success-box">
            <h4 class="box-title-success">💰 Ajouter crédit</h4>
            
            <div class="mb-8">
                <select id="credit_gestion_client" name="credit_gestion_client" class="form-control-sm w-full">
                    <option value="">Sélectionner un client...</option>
                    <?php foreach ($credits_clients as $credit): ?>
                        <option value="<?= $credit['id'] ?>" data-solde="<?= $credit['solde_actuel'] ?>">
                            <?= htmlspecialchars($credit['nom_client']) ?>
                            (<?= number_format($credit['solde_actuel'], 2) ?> €)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-8">
                <input type="number" id="credit_montant" step="0.01" min="0"
                       class="form-control-sm w-full" placeholder="Montant à ajouter">
            </div>
            
            <div class="mb-8">
                <select id="credit_moyen_payement" class="form-control-sm w-full">
                    <option value="">Moyen de paiement...</option>
                    <?php
                    // Récupération des moyens de paiement pour le crédit
                    try {
                        $stmt = $pdo->query("SELECT moyen FROM FC_moyens_paiement ORDER BY moyen ASC");
                        $moyens = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($moyens as $moyen) {
                            echo "<option value=\"" . htmlspecialchars($moyen) . "\">" . htmlspecialchars($moyen) . "</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option value=\"Espèces\">Espèces</option>";
                        echo "<option value=\"Chèque\">Chèque</option>";
                        echo "<option value=\"Carte bancaire\">Carte bancaire</option>";
                    }
                    ?>
                </select>
            </div>
            
            <input type="text" id="credit_description"
                   class="form-control-sm w-full mb-8"
                   placeholder="Description (optionnel)">
            
            <button type="button" id="gerer_credit"
                    class="btn btn-success w-full btn-sm">
                💳 Ajouter crédit
            </button>
            
            <div id="credit_gestion_info" class="info-bubble">
                <div><strong>Solde actuel :</strong> <span id="solde_actuel_gestion">0,00 €</span></div>
                <div><strong>Nouveau solde :</strong> <span id="nouveau_solde_gestion">0,00 €</span></div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-box">
            <small>Aucun crédit client<br>disponible</small>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Boutons d'action -->
    <div class="text-center mt-20">
        <button type="submit" name="save_only" value="1" class="btn btn-success mr-10">
            <?= $editData ? '💾 Mettre à jour' : '💾 Enregistrer' ?>
        </button>
        
        <?php if ($editData): ?>
            <button type="submit" class="btn btn-accent mr-10">
                💰 Enregistrer le paiement
            </button>
        <?php endif; ?>
        
        <a href="index.php?page=cyber_list" class="btn btn-secondary mr-10">
            ❌ Annuler
        </a>

        <?php if ($editData): ?>
            <a href="actions/cyber_delete.php?id=<?= $editData['id'] ?>" 
               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette session ?');"
               class="btn btn-danger">
                🗑️ Supprimer
            </a>
        <?php endif; ?>
    </div>
</form>
</div>

<!-- Aide compacte -->
<div class="card mt-20 p-15">
    <h4 class="text-accent mb-10">💡 Aide rapide</h4>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; font-size: 13px;">
        <div><strong>Session complète :</strong> Nom + heures d'arrivée/départ</div>
        <div><strong>Impressions seules :</strong> Seulement pages N&B/couleur</div>
        <div><strong>Calcul auto :</strong> ≤<?= $time_minimum_threshold ?>min = <?= number_format($price_time_minimum, 2) ?>€, <?= $time_increment ?>min = <?= number_format($price_time_base, 2) ?>€</div>
        <div><strong>Tarif spécifique :</strong> Remplace le calcul automatique</div>
    </div>
    
    <?php if (!empty($tarifs_parametres)): ?>
    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
        <strong>Tarifs paramétrés :</strong>
        <?php foreach ($tarifs_parametres as $index => $tarif): ?>
            <?= $index > 0 ? ' • ' : '' ?><?= htmlspecialchars($tarif['description']) ?> (<?= number_format($tarif['prix'], 2) ?>€)
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmation personnalisée -->
<div id="confirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); max-width: 500px; width: 90%; margin: 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background-color: var(--success-color, #28a745); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                💳
            </div>
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px;">Confirmer l'ajout de crédit</h3>
        </div>
        
        <div id="confirmContent" style="background-color: var(--bg-color, #f8f9fa); padding: 20px; border-radius: 8px; margin-bottom: 25px; line-height: 1.6; font-size: 14px;">
            <!-- Contenu dynamique -->
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button id="confirmCancel" style="padding: 12px 25px; background-color: var(--secondary-color, #6c757d); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; min-width: 100px;">
                ❌ Annuler
            </button>
            <button id="confirmOk" style="padding: 12px 25px; background-color: var(--success-color, #28a745); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; min-width: 100px;">
                ✅ Confirmer
            </button>
        </div>
    </div>
</div>

<script src="js/awesomplete.min.js"></script>
<link rel="stylesheet" href="css/awesomplete.css" />

<script>
// Calcul automatique du prix en temps réel
// Calcul automatique du prix en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const ha = document.getElementById('ha');
    const hd = document.getElementById('hd');
    const impNb = document.getElementById('imp_nb');
    const impCouleur = document.getElementById('imp_couleur');
    const tarifSaisi = document.getElementById('tarif_saisi');
    const tarifHidden = document.getElementById('tarif');
    const addPrintsOption = document.getElementById('add_prints_option');
    const addCyberOption = document.getElementById('add_cyber_option');
    
    // --- Vérification des éléments critiques ---
    if (!tarifSaisi || !tarifHidden) {
        console.error("Éléments critiques manquants (tarif_saisi ou tarif)");
        // On ne throw pas d'erreur pour ne pas bloquer le reste si jamais
    }
    
    function calculerTotalImpressions() {
        let total = 0;
        
        if (impNb.value) {
            total += parseInt(impNb.value) * <?= $price_nb_page ?>;
        }
        
        if (impCouleur.value) {
            total += parseInt(impCouleur.value) * <?= $price_color_page ?>;
        }
        
        const totalImpressions = document.getElementById('total_impressions');
        if (totalImpressions) totalImpressions.textContent = total.toFixed(2) + ' €';
        return total;
    }
    
    function calculerTarifCyber() {
        let prixTemps = 0;
        
        // Calcul du temps
        if (ha.value && hd.value) {
            const debut = new Date('2000-01-01 ' + ha.value);
            const fin = new Date('2000-01-01 ' + hd.value);
            const diffMs = fin - debut;
            const minutes = Math.floor(diffMs / (1000 * 60));
            
            if (minutes > 0) {
                if (minutes <= <?= $time_minimum_threshold ?>) {
                    prixTemps = <?= $price_time_minimum ?>;
                } else {
                    prixTemps = Math.ceil(minutes / <?= $time_increment ?>) * <?= $price_time_base ?>;
                }
            }
        }
        
        const tarifCyber = document.getElementById('tarif_cyber');
        if (tarifCyber) tarifCyber.textContent = prixTemps.toFixed(2) + ' €';
        return prixTemps;
    }
    
    function calculerPrixTotal() {
        // Calcul des deux parties séparément
        const prixTemps = calculerTarifCyber();
        const prixImpressions = calculerTotalImpressions();
        const prixCalc = prixTemps + prixImpressions;
        let prixFinal = prixCalc;
        
        let visualValue = tarifSaisi.value;
        let isManual = visualValue !== '' && !isNaN(parseFloat(visualValue));
        
        if (isManual) {
            let manualAmount = parseFloat(visualValue);
            prixFinal = manualAmount;
            
            // Si la case Imp est cochée
            if (addPrintsOption && addPrintsOption.checked) {
                prixFinal += prixImpressions;
            }
            
            // Si la case Cyber est cochée
            if (addCyberOption && addCyberOption.checked) {
                prixFinal += prixTemps;
            }
            
            // On met à jour le champ caché qui sera envoyé
            tarifHidden.value = prixFinal.toFixed(2);
        } else {
            // Mode Auto
            // On met à jour le champ caché avec le calcul auto
            tarifHidden.value = prixCalc.toFixed(2);
            
            // On remplit le champ visuel si on veut montrer le calcul
             if (!tarifSaisi.value) {
                // Option : afficher ou non le calcul dans le champ saisie ?
                // Le comportement original remplissait le champ.
                // tarifSaisi.value = prixCalc.toFixed(2); // Désactivé : on ne pré-remplit pas
            }
        }
        
        // Mise à jour de l'affichage du total avec le VRAI prix final
        const displayTotal = document.getElementById('cout_total_display');
        if (displayTotal) {
            displayTotal.textContent = prixFinal.toFixed(2) + ' €';
        }
        
        return prixFinal;
    }
    
    // Calcul initial
    calculerTarifCyber();
    calculerTotalImpressions();
    calculerPrixTotal();
    
    // Event listeners
    ha.addEventListener('change', function() {
        calculerTarifCyber();
        calculerPrixTotal();
    });
    hd.addEventListener('change', function() {
        calculerTarifCyber();
        calculerPrixTotal();
    });
    impNb.addEventListener('input', function() {
        calculerTotalImpressions();
        calculerPrixTotal();
    });
    impCouleur.addEventListener('input', function() {
        calculerTotalImpressions();
        calculerPrixTotal();
    });
    
    // Listener pour le tarif spécifique
    tarifSaisi.addEventListener('input', function() {
        // Si on saisit manuellement, on décoche "Auto calc" implicitement
        // Le hidden sera mis à jour par calculerPrixTotal
        calculerPrixTotal();
    });
    
    if (addPrintsOption) {
        addPrintsOption.addEventListener('change', function() {
            calculerPrixTotal();
        });
    }
    
    if (addCyberOption) {
        addCyberOption.addEventListener('change', function() {
            calculerPrixTotal();
        });
    }
    
    // Bouton recalculer tarif
    const recalculerBtn = document.getElementById('recalculer_tarif');
    if (recalculerBtn) {
        recalculerBtn.addEventListener('click', function() {
            // Force le recalcul en vidant temporairement le champ tarif
            tarifSaisi.value = '';
            const nouveauPrix = calculerPrixTotal();
            
            // Animation visuelle
            tarifSaisi.style.backgroundColor = '#e6ffe6';
            setTimeout(() => {
                tarifSaisi.style.backgroundColor = '';
            }, 1000);
        });
    }
    
    // Gestion de l'affichage des champs de chèque
    const moyenPayement = document.getElementById('moyen_payement');
    const chequeFields = document.getElementById('cheque_fields');
    
    function toggleChequeFields() {
        if (moyenPayement.value === 'Chèque') {
            chequeFields.style.display = 'block';
        } else {
            chequeFields.style.display = 'none';
        }
    }
    
    // Affichage initial
    toggleChequeFields();
    
    // Event listener pour le changement de moyen de paiement
    moyenPayement.addEventListener('change', toggleChequeFields);
    
    // Gestion des boutons de paiement
    const payerMaintenant = document.getElementById('payer_maintenant');
    const payerParCredit = document.getElementById('payer_par_credit');
    const creditClientId = document.getElementById('credit_client_id');
    const creditInfo = document.getElementById('credit_info');
    
    // Fonction pour mettre à jour les infos crédit
    function updateCreditInfo() {
        if (creditClientId && creditClientId.value) {
            const option = creditClientId.options[creditClientId.selectedIndex];
            const soldeDisponible = parseFloat(option.dataset.solde || 0);
            const montantSession = parseFloat(tarifHidden.value || 0);
            const soldeApres = soldeDisponible - montantSession;
            
            document.getElementById('solde_disponible').textContent = soldeDisponible.toFixed(2) + ' €';
            document.getElementById('montant_session').textContent = montantSession.toFixed(2) + ' €';
            
            const soldeApresElement = document.getElementById('solde_apres');
            soldeApresElement.textContent = soldeApres.toFixed(2) + ' €';
            soldeApresElement.style.color = soldeApres >= 0 ? 'var(--success-color, #28a745)' : 'var(--danger-color, #dc3545)';
            
            creditInfo.style.display = 'block';
        } else {
            if (creditInfo) creditInfo.style.display = 'none';
        }
    }
    
    // Event listeners pour les infos crédit
    if (creditClientId) {
        creditClientId.addEventListener('change', updateCreditInfo);
        tarifSaisi.addEventListener('input', updateCreditInfo);
        if (addPrintsOption) addPrintsOption.addEventListener('change', updateCreditInfo);
        if (addCyberOption) addCyberOption.addEventListener('change', updateCreditInfo);
        
        // Initialisation
        if (creditClientId.value) {
            updateCreditInfo();
        }
    }
    
    // Bouton "Payer maintenant" (paiement classique)
    if (payerMaintenant) {
        payerMaintenant.addEventListener('click', function() {
            // Validation du moyen de paiement
            if (!moyenPayement.value) {
                alert('Veuillez sélectionner un moyen de paiement.');
                return;
            }
            
            // Soumettre le formulaire normalement
            const form = document.querySelector('form');
            form.submit();
        });
    }
    
    // Bouton "Payer par crédit"
    if (payerParCredit) {
        payerParCredit.addEventListener('click', function() {
            // Validation du client crédit
            if (!creditClientId.value) {
                alert('Veuillez sélectionner un client crédit.');
                return;
            }
            
            // Validation du tarif
            if (!tarifHidden.value || parseFloat(tarifHidden.value) <= 0) {
                alert('Veuillez saisir un montant valide pour la session.');
                return;
            }
            
            // Créer un formulaire pour le paiement par crédit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            // Récupérer toutes les données du formulaire
            const formData = new FormData(document.querySelector('form'));
            
            // Ajouter les champs spécifiques au paiement par crédit
            formData.set('paye_par_credit', '1');
            formData.set('credit_client_id', creditClientId.value);
            formData.set('moyen_payement', 'Crédit client');
            
            // Créer les inputs cachés
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
    }
    

    
    // Gestion d'ajout de crédit
    const creditGestionClient = document.getElementById('credit_gestion_client');
    const creditMontant = document.getElementById('credit_montant');
    const creditMoyenPayement = document.getElementById('credit_moyen_payement');
    const creditDescription = document.getElementById('credit_description');
    const gererCreditBtn = document.getElementById('gerer_credit');
    const creditGestionInfo = document.getElementById('credit_gestion_info');
    
    if (creditGestionClient) {
        function updateCreditGestionInfo() {
            if (creditGestionClient.value && creditMontant.value) {
                const option = creditGestionClient.options[creditGestionClient.selectedIndex];
                const soldeActuel = parseFloat(option.dataset.solde || 0);
                const montant = parseFloat(creditMontant.value || 0);
                
                // Toujours ajouter (plus de déduction)
                const nouveauSolde = soldeActuel + montant;
                
                document.getElementById('solde_actuel_gestion').textContent = soldeActuel.toFixed(2) + ' €';
                const nouveauSoldeElement = document.getElementById('nouveau_solde_gestion');
                nouveauSoldeElement.textContent = nouveauSolde.toFixed(2) + ' €';
                nouveauSoldeElement.style.color = 'var(--success-color, #28a745)';
                
                creditGestionInfo.style.display = 'block';
            } else {
                creditGestionInfo.style.display = 'none';
            }
        }
        
        creditGestionClient.addEventListener('change', updateCreditGestionInfo);
        creditMontant.addEventListener('input', updateCreditGestionInfo);
        
        gererCreditBtn.addEventListener('click', function() {
            if (!creditGestionClient.value) {
                alert('Veuillez sélectionner un client.');
                return;
            }
            
            if (!creditMontant.value || parseFloat(creditMontant.value) <= 0) {
                alert('Veuillez saisir un montant valide.');
                return;
            }
            
            if (!creditMoyenPayement.value) {
                alert('Veuillez sélectionner un moyen de paiement.');
                return;
            }
            
            const clientNom = creditGestionClient.options[creditGestionClient.selectedIndex].text.split(' (')[0];
            const montant = parseFloat(creditMontant.value);
            const moyenPayement = creditMoyenPayement.value;
            const description = creditDescription.value || 'Ajout de crédit - ' + moyenPayement;
            
            // Afficher la modal personnalisée
            showConfirmModal(
                `<div style="text-align: left;">
                    <div style="margin-bottom: 12px;"><strong>Client :</strong> ${clientNom}</div>
                    <div style="margin-bottom: 12px;"><strong>Montant :</strong> <span style="color: var(--success-color, #28a745); font-weight: bold;">${montant.toFixed(2)} €</span></div>
                    <div style="margin-bottom: 12px;"><strong>Moyen de paiement :</strong> ${moyenPayement}</div>
                    <div style="margin-bottom: 12px;"><strong>Description :</strong> ${description}</div>
                    <div style="margin-top: 15px; padding: 10px; background-color: #e8f5e8; border-left: 3px solid var(--success-color, #28a745); border-radius: 4px;">
                        <small><strong>💡 Info :</strong> Cette opération apparaîtra dans la liste des sessions cyber.</small>
                    </div>
                </div>`,
                function() {
                    // Créer un formulaire pour soumettre comme session cyber (ajout de crédit)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href; // Même page
                    
                    const inputs = [
                        {name: 'nom', value: clientNom + ' - AJOUT CRÉDIT'},
                        {name: 'tarif', value: montant},
                        {name: 'moyen_payement', value: moyenPayement},
                        {name: 'credit_client_id', value: creditGestionClient.value},
                        {name: 'ajout_credit', value: '1'},
                        {name: 'credit_description', value: description}
                    ];
                    
                    inputs.forEach(input => {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = input.name;
                        hiddenInput.value = input.value;
                        form.appendChild(hiddenInput);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        });
    }
    
    // Fonction pour afficher la modal de confirmation
    function showConfirmModal(content, onConfirm) {
        const modal = document.getElementById('confirmModal');
        const confirmContent = document.getElementById('confirmContent');
        const confirmOk = document.getElementById('confirmOk');
        const confirmCancel = document.getElementById('confirmCancel');
        
        confirmContent.innerHTML = content;
        modal.style.display = 'flex';
        
        // Gestionnaires d'événements
        const handleConfirm = function() {
            modal.style.display = 'none';
            confirmOk.removeEventListener('click', handleConfirm);
            confirmCancel.removeEventListener('click', handleCancel);
            onConfirm();
        };
        
        const handleCancel = function() {
            modal.style.display = 'none';
            confirmOk.removeEventListener('click', handleConfirm);
            confirmCancel.removeEventListener('click', handleCancel);
        };
        
        confirmOk.addEventListener('click', handleConfirm);
        confirmCancel.addEventListener('click', handleCancel);
        
        // Fermer avec Escape
        const handleEscape = function(e) {
            if (e.key === 'Escape') {
                handleCancel();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
        
        // Fermer en cliquant sur le fond
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                handleCancel();
            }
        });
    }
    
    // --- Autocomplétion Client ---
    const nomInput = document.getElementById('nom');
    if (nomInput) {
        let awesomplete = new Awesomplete(nomInput, {
            minChars: 2,
            maxItems: 15,
            autoFirst: true
        });

        let clientsList = []; // Stockage local des résultats

        // Source de données via API
        nomInput.addEventListener('input', function() {
            if (this.value.length < 2) return;
            
            // Appel API
            fetch('api/search_clients.php?q=' + encodeURIComponent(this.value))
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        clientsList = data; // Sauvegarde
                        awesomplete.list = data;
                    } else {
                        clientsList = [];
                    }
                })
                .catch(err => {
                    console.error('Erreur recherche client:', err);
                    clientsList = [];
                });
        });
        
        const linkStatus = document.getElementById('client_link_status');
        
        // Optionnel : Gérer la sélection si on voulait remplir d'autres champs
        nomInput.addEventListener('awesomplete-selectcomplete', function(e) {
            // e.text est souvent juste la "valeur" affichée, pas l'objet complet
            console.log('Selected item text:', e.text); 
            
            // Recherche de l'objet complet dans notre liste locale
            let selectedItem = null;
            
            // Essai 1: e.text est peut-être déjà l'objet (si Awesomplete est gentil)
            if (e.text && e.text.id) {
                selectedItem = e.text;
            } 
            // Essai 2: Lookup par valeur
            else if (clientsList.length > 0) {
                // e.text.value si e.text est un objet Suggestion, sinon e.text direct
                const selectedValue = (e.text && e.text.value) ? e.text.value : e.text;
                selectedItem = clientsList.find(c => c.value === selectedValue || c.label === selectedValue);
            }

            if(selectedItem && selectedItem.id) {
                document.getElementById('id_client').value = selectedItem.id;
                if(linkStatus) {
                    linkStatus.style.display = 'block';
                    linkStatus.title = 'Client lié (ID: ' + selectedItem.id + ')';
                }
            } else {
                console.warn('Selected item has no ID found via lookup:', e.text);
            }
        });
        
        // Clear ID if user changes the name manually
        nomInput.addEventListener('input', function() {
             document.getElementById('id_client').value = '';
             if(linkStatus) linkStatus.style.display = 'none';
        });
        
        // Check initial state
        if(document.getElementById('id_client').value && linkStatus) {
             linkStatus.style.display = 'block';
        }
    }
});
</script>