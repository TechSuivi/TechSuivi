<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date_comptage = $_POST['date_comptage'] ?? date('Y-m-d');
        
        // Récupération des valeurs des pièces (comptage)
        $pieces_001 = (int)($_POST['pieces_001'] ?? 0);
        $pieces_002 = (int)($_POST['pieces_002'] ?? 0);
        $pieces_005 = (int)($_POST['pieces_005'] ?? 0);
        $pieces_010 = (int)($_POST['pieces_010'] ?? 0);
        $pieces_020 = (int)($_POST['pieces_020'] ?? 0);
        $pieces_050 = (int)($_POST['pieces_050'] ?? 0);
        $pieces_100 = (int)($_POST['pieces_100'] ?? 0);
        $pieces_200 = (int)($_POST['pieces_200'] ?? 0);
        
        // Récupération des valeurs des billets (comptage)
        $billets_005 = (int)($_POST['billets_005'] ?? 0);
        $billets_010 = (int)($_POST['billets_010'] ?? 0);
        $billets_020 = (int)($_POST['billets_020'] ?? 0);
        $billets_050 = (int)($_POST['billets_050'] ?? 0);
        $billets_100 = (int)($_POST['billets_100'] ?? 0);
        $billets_200 = (int)($_POST['billets_200'] ?? 0);
        $billets_500 = (int)($_POST['billets_500'] ?? 0);
        
        // Récupération des valeurs des pièces (retrait banque)
        $retrait_pieces_001 = (int)($_POST['retrait_pieces_001'] ?? 0);
        $retrait_pieces_002 = (int)($_POST['retrait_pieces_002'] ?? 0);
        $retrait_pieces_005 = (int)($_POST['retrait_pieces_005'] ?? 0);
        $retrait_pieces_010 = (int)($_POST['retrait_pieces_010'] ?? 0);
        $retrait_pieces_020 = (int)($_POST['retrait_pieces_020'] ?? 0);
        $retrait_pieces_050 = (int)($_POST['retrait_pieces_050'] ?? 0);
        $retrait_pieces_100 = (int)($_POST['retrait_pieces_100'] ?? 0);
        $retrait_pieces_200 = (int)($_POST['retrait_pieces_200'] ?? 0);
        
        // Récupération des valeurs des billets (retrait banque)
        $retrait_billets_005 = (int)($_POST['retrait_billets_005'] ?? 0);
        $retrait_billets_010 = (int)($_POST['retrait_billets_010'] ?? 0);
        $retrait_billets_020 = (int)($_POST['retrait_billets_020'] ?? 0);
        $retrait_billets_050 = (int)($_POST['retrait_billets_050'] ?? 0);
        $retrait_billets_100 = (int)($_POST['retrait_billets_100'] ?? 0);
        $retrait_billets_200 = (int)($_POST['retrait_billets_200'] ?? 0);
        $retrait_billets_500 = (int)($_POST['retrait_billets_500'] ?? 0);
        
        // Gestion du solde précédent, ajustement et écart
        $ajustement_especes = (float)($_POST['ajustement_especes'] ?? 0);
        $solde_precedent = (float)($_POST['solde_precedent'] ?? 0);
        $ecart_constate = 0;
        
        // Gestion des chèques
        $cheques_details = [];
        $montant_cheques = 0;
        $nb_cheques = 0;
        
        if (isset($_POST['cheques']) && is_array($_POST['cheques'])) {
            foreach ($_POST['cheques'] as $cheque) {
                if (!empty($cheque['montant']) && $cheque['montant'] > 0) {
                    $cheque_data = [
                        'montant' => (float)$cheque['montant'],
                        'emetteur' => trim($cheque['emetteur'] ?? ''),
                        'numero' => trim($cheque['numero'] ?? '')
                    ];
                    $cheques_details[] = $cheque_data;
                    $montant_cheques += $cheque_data['montant'];
                    $nb_cheques++;
                }
            }
        }
        
        // Notes
        $notes = $_POST['notes'] ?? '';
        
        // Calculs automatiques - Comptage
        $total_pieces = ($pieces_001 * 0.01) + ($pieces_002 * 0.02) + ($pieces_005 * 0.05) +
                       ($pieces_010 * 0.10) + ($pieces_020 * 0.20) + ($pieces_050 * 0.50) +
                       ($pieces_100 * 1.00) + ($pieces_200 * 2.00);
        
        $total_billets = ($billets_005 * 5) + ($billets_010 * 10) + ($billets_020 * 20) +
                        ($billets_050 * 50) + ($billets_100 * 100) + ($billets_200 * 200) +
                        ($billets_500 * 500);
        
        // Calculs automatiques - Retraits banque
        $total_retrait_pieces = ($retrait_pieces_001 * 0.01) + ($retrait_pieces_002 * 0.02) + ($retrait_pieces_005 * 0.05) +
                               ($retrait_pieces_010 * 0.10) + ($retrait_pieces_020 * 0.20) + ($retrait_pieces_050 * 0.50) +
                               ($retrait_pieces_100 * 1.00) + ($retrait_pieces_200 * 2.00);
        
        $total_retrait_billets = ($retrait_billets_005 * 5) + ($retrait_billets_010 * 10) + ($retrait_billets_020 * 20) +
                                ($retrait_billets_050 * 50) + ($retrait_billets_100 * 100) + ($retrait_billets_200 * 200) +
                                ($retrait_billets_500 * 500);
        
        $total_especes = $total_pieces + $total_billets;
        $total_retrait_especes = $total_retrait_pieces + $total_retrait_billets;
        $total_caisse = $total_especes + $montant_cheques;
        
        // Calcul de l'écart (différence entre espèces comptées et solde précédent)
        $ecart_constate = $total_especes - $solde_precedent;
        
        // Vérifier si la table existe et ajouter les colonnes si nécessaire
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'cheques_details'");
            if ($stmt->rowCount() == 0) {
                // Ajouter les nouvelles colonnes
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN cheques_details text COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Détails des chèques (JSON)'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN nb_cheques int(11) DEFAULT 0 COMMENT 'Nombre de chèques'");
            }
            
            // Vérifier et ajouter les colonnes de contrôle
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'solde_precedent'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN solde_precedent decimal(10,2) DEFAULT 0.00 COMMENT 'Solde espèces de la feuille précédente'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN ajustement_especes decimal(10,2) DEFAULT 0.00 COMMENT 'Ajustement espèces (entrées/sorties)'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN ecart_constate decimal(10,2) DEFAULT 0.00 COMMENT 'Écart entre attendu et réel'");
            }
            
            // Vérifier et ajouter la colonne ajustement_especes si elle n'existe pas
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'ajustement_especes'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN ajustement_especes decimal(10,2) DEFAULT 0.00 COMMENT 'Ajustement espèces (entrées/sorties)' AFTER solde_precedent");
            }
            
            // Ajouter les colonnes pour les retraits bancaires - Pièces
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'retrait_pieces_001'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_001 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,01€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_002 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,02€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_005 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,05€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_010 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,10€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_020 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,20€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_050 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,50€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_100 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 1,00€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_pieces_200 int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 2,00€'");
            }
            
            // Ajouter les colonnes pour les retraits bancaires - Billets
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'retrait_billets_005'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_005 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 5€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_010 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 10€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_020 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 20€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_050 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 50€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_100 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 100€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_200 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 200€'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN retrait_billets_500 int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 500€'");
            }
            
            // Ajouter les colonnes pour les totaux de retrait
            $stmt = $pdo->query("SHOW COLUMNS FROM FC_feuille_caisse LIKE 'total_retrait_pieces'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN total_retrait_pieces decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits pièces'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN total_retrait_billets decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits billets'");
                $pdo->exec("ALTER TABLE FC_feuille_caisse ADD COLUMN total_retrait_especes decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits espèces'");
            }
        } catch (PDOException $e) {
            // Ignorer si les colonnes existent déjà
        }
        
        // Insertion en base
        $stmt = $pdo->prepare("
            INSERT INTO FC_feuille_caisse (
                date_comptage, pieces_001, pieces_002, pieces_005, pieces_010, pieces_020, pieces_050, pieces_100, pieces_200,
                billets_005, billets_010, billets_020, billets_050, billets_100, billets_200, billets_500,
                retrait_pieces_001, retrait_pieces_002, retrait_pieces_005, retrait_pieces_010, retrait_pieces_020, retrait_pieces_050, retrait_pieces_100, retrait_pieces_200,
                retrait_billets_005, retrait_billets_010, retrait_billets_020, retrait_billets_050, retrait_billets_100, retrait_billets_200, retrait_billets_500,
                montant_cheques, cheques_details, nb_cheques, total_pieces, total_billets, total_especes, total_caisse,
                total_retrait_pieces, total_retrait_billets, total_retrait_especes,
                solde_precedent, ajustement_especes, ecart_constate, notes
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                pieces_001 = VALUES(pieces_001), pieces_002 = VALUES(pieces_002), pieces_005 = VALUES(pieces_005),
                pieces_010 = VALUES(pieces_010), pieces_020 = VALUES(pieces_020), pieces_050 = VALUES(pieces_050),
                pieces_100 = VALUES(pieces_100), pieces_200 = VALUES(pieces_200),
                billets_005 = VALUES(billets_005), billets_010 = VALUES(billets_010), billets_020 = VALUES(billets_020),
                billets_050 = VALUES(billets_050), billets_100 = VALUES(billets_100), billets_200 = VALUES(billets_200),
                billets_500 = VALUES(billets_500),
                retrait_pieces_001 = VALUES(retrait_pieces_001), retrait_pieces_002 = VALUES(retrait_pieces_002), retrait_pieces_005 = VALUES(retrait_pieces_005),
                retrait_pieces_010 = VALUES(retrait_pieces_010), retrait_pieces_020 = VALUES(retrait_pieces_020), retrait_pieces_050 = VALUES(retrait_pieces_050),
                retrait_pieces_100 = VALUES(retrait_pieces_100), retrait_pieces_200 = VALUES(retrait_pieces_200),
                retrait_billets_005 = VALUES(retrait_billets_005), retrait_billets_010 = VALUES(retrait_billets_010), retrait_billets_020 = VALUES(retrait_billets_020),
                retrait_billets_050 = VALUES(retrait_billets_050), retrait_billets_100 = VALUES(retrait_billets_100), retrait_billets_200 = VALUES(retrait_billets_200),
                retrait_billets_500 = VALUES(retrait_billets_500),
                montant_cheques = VALUES(montant_cheques), cheques_details = VALUES(cheques_details), nb_cheques = VALUES(nb_cheques),
                total_pieces = VALUES(total_pieces), total_billets = VALUES(total_billets),
                total_especes = VALUES(total_especes), total_caisse = VALUES(total_caisse),
                total_retrait_pieces = VALUES(total_retrait_pieces), total_retrait_billets = VALUES(total_retrait_billets), total_retrait_especes = VALUES(total_retrait_especes),
                solde_precedent = VALUES(solde_precedent), ajustement_especes = VALUES(ajustement_especes),
                ecart_constate = VALUES(ecart_constate), notes = VALUES(notes), updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $date_comptage, $pieces_001, $pieces_002, $pieces_005, $pieces_010, $pieces_020, $pieces_050, $pieces_100, $pieces_200,
            $billets_005, $billets_010, $billets_020, $billets_050, $billets_100, $billets_200, $billets_500,
            $retrait_pieces_001, $retrait_pieces_002, $retrait_pieces_005, $retrait_pieces_010, $retrait_pieces_020, $retrait_pieces_050, $retrait_pieces_100, $retrait_pieces_200,
            $retrait_billets_005, $retrait_billets_010, $retrait_billets_020, $retrait_billets_050, $retrait_billets_100, $retrait_billets_200, $retrait_billets_500,
            $montant_cheques, json_encode($cheques_details), $nb_cheques, $total_pieces, $total_billets, $total_especes, $total_caisse,
            $total_retrait_pieces, $total_retrait_billets, $total_retrait_especes,
            $solde_precedent, $ajustement_especes, $ecart_constate, $notes
        ]);
        
        $message = "Feuille de caisse enregistrée avec succès ! Total caisse : " . number_format($total_caisse, 2) . " € (dont " . $nb_cheques . " chèque(s))" .
                   ($total_retrait_especes > 0 ? " - Retrait banque : " . number_format($total_retrait_especes, 2) . " €" : "");
        
    } catch (PDOException $e) {
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Récupérer la feuille existante pour la date spécifiée ou aujourd'hui
$feuille_existante = null;
$date_defaut = $_GET['date'] ?? date('Y-m-d');
$date_aujourd_hui = date('Y-m-d');
$modification_autorisee = ($date_defaut === $date_aujourd_hui);

try {
    $stmt = $pdo->prepare("SELECT * FROM FC_feuille_caisse WHERE date_comptage = ?");
    $stmt->execute([$date_defaut]);
    $feuille_existante = $stmt->fetch();
} catch (PDOException $e) {
    // Si la table n'existe pas encore, afficher un message d'erreur spécifique
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "La table des feuilles de caisse n'existe pas encore. Veuillez d'abord créer la table via les Paramètres → 🔧 Table Feuille Caisse.";
    }
}

// Décoder les détails des chèques existants
$cheques_existants = [];
if ($feuille_existante && !empty($feuille_existante['cheques_details'])) {
    $cheques_existants = json_decode($feuille_existante['cheques_details'], true) ?: [];
}

// Récupérer la feuille précédente pour le contrôle
$feuille_precedente = null;
$solde_precedent_auto = 0;
try {
    $stmt = $pdo->prepare("
        SELECT total_especes, date_comptage
        FROM FC_feuille_caisse
        WHERE date_comptage < ?
        ORDER BY date_comptage DESC
        LIMIT 1
    ");
    $stmt->execute([$date_defaut]);
    $feuille_precedente = $stmt->fetch();
    if ($feuille_precedente) {
        $solde_precedent_auto = $feuille_precedente['total_especes'];
    }
} catch (PDOException $e) {
    // Ignorer l'erreur si la table n'existe pas encore
}

// Vérifier si la modification est autorisée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$modification_autorisee) {
    $error = "Modification non autorisée : vous ne pouvez modifier que la feuille de caisse du jour même.";
}
?>

<style>
/* Styles spécifiques pour la feuille de caisse - VERSION COMPACTE */
.feuille-caisse-container {
    background-color: var(--card-bg);
    color: var(--text-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.feuille-caisse-input {
    width: 100%;
    padding: 4px 6px;
    border: 1px solid var(--border-color, #ddd);
    border-radius: 3px;
    background-color: var(--input-bg, white);
    color: var(--text-color);
    font-size: 13px;
    height: 28px;
    box-sizing: border-box;
}

.feuille-caisse-label {
    display: block;
    margin-bottom: 2px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 11px;
    line-height: 1.2;
}

.feuille-caisse-section {
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 8px;
    margin-bottom: 12px;
    color: var(--accent-color);
    font-size: 16px;
}

.feuille-caisse-total {
    background-color: var(--secondary-bg, #f8f9fa);
    padding: 8px;
    border-radius: 4px;
    margin-top: 10px;
}

.feuille-caisse-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.feuille-caisse-grid-compact {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
}

.field-group {
    display: flex;
    flex-direction: column;
    min-height: 50px;
}

.cheque-item {
    background-color: var(--secondary-bg, #f8f9fa);
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color, #ddd);
}

.btn-add-cheque {
    background-color: var(--accent-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 10px;
}

.btn-remove-cheque {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

/* Mode sombre spécifique */
body.dark .feuille-caisse-input {
    background-color: #2b2b2b;
    border-color: #444444;
    color: #dddddd;
}

body.dark .feuille-caisse-total {
    background-color: #333333;
}

body.dark .cheque-item {
    background-color: #333333;
    border-color: #444444;
}

body.dark .feuille-caisse-container {
    background-color: #2b2b2b;
    color: #dddddd;
}

body.dark .feuille-caisse-label {
    color: #dddddd;
}

body.dark .feuille-caisse-section {
    color: var(--accent-color);
}

/* Styles d'impression simplifiés */
@media print {
    /* Masquer tous les éléments non essentiels */
    .no-print, .sidebar, header, .menu, nav, button, .btn-add-cheque, .btn-remove-cheque {
        display: none !important;
    }
    
    /* Réinitialiser le body pour l'impression */
    body {
        background: white !important;
        color: black !important;
        font-family: Arial, sans-serif !important;
        font-size: 9pt !important;
        margin: 0 !important;
        padding: 10px !important;
        line-height: 1.2 !important;
    }
    
    /* Masquer le contenu principal et afficher seulement la feuille */
    .content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    /* Styles pour les conteneurs */
    .feuille-caisse-container {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        border: 1px solid #000 !important;
        margin-bottom: 5px !important;
        padding: 5px !important;
        page-break-inside: avoid;
    }
    
    /* Styles pour les champs de saisie */
    .feuille-caisse-input {
        border: 1px solid #000 !important;
        background: white !important;
        color: black !important;
        font-weight: bold !important;
    }
    
    /* Styles pour les chèques */
    .cheque-item {
        background: white !important;
        border: 1px solid #000 !important;
        margin-bottom: 5px !important;
    }
    
    /* Titres et sections */
    h1, h2, h3 {
        color: black !important;
        page-break-after: avoid;
        margin: 5px 0 !important;
        font-size: 11pt !important;
    }
    
    /* Réduire les espacements */
    div, p {
        margin: 2px 0 !important;
        padding: 2px !important;
    }
    
    /* Grilles compactes */
    .feuille-caisse-grid {
        gap: 3px !important;
    }
    
    /* Labels compacts */
    .feuille-caisse-label {
        margin-bottom: 2px !important;
        font-size: 8pt !important;
    }
    
    /* Inputs compacts */
    .feuille-caisse-input {
        padding: 2px !important;
        font-size: 8pt !important;
    }
    
    /* Totaux */
    .feuille-caisse-total {
        background: #f0f0f0 !important;
        border: 1px solid #000 !important;
        font-weight: bold !important;
    }
    
    /* Afficher le titre d'impression */
    .print-only {
        display: block !important;
    }
}

/* Amélioration du mode sombre */
body.dark {
    background-color: #121212 !important;
    color: #dddddd !important;
}

body.dark .feuille-caisse-container {
    background-color: #2b2b2b !important;
    color: #dddddd !important;
}

body.dark div[style*="background-color: var(--secondary-bg, #f8f9fa)"],
body.dark div[style*="background-color: #f8f9fa"],
body.dark div[style*="background-color: var(--card-bg)"] {
    background-color: #333333 !important;
    color: #dddddd !important;
}

body.dark div[style*="background-color: #e7f3ff"] {
    background-color: #1a365d !important;
    color: #dddddd !important;
}

body.dark h1, body.dark h2, body.dark h3 {
    color: #dddddd !important;
}

body.dark p, body.dark span, body.dark div, body.dark label {
    color: #dddddd !important;
}

body.dark small {
    color: #999999 !important;
}

body.dark strong {
    color: #dddddd !important;
}

/* Forcer les couleurs pour les montants dans le récapitulatif */
body.dark div[style*="color: #2196F3"] {
    color: #63b3ed !important;
}

body.dark div[style*="color: #FF9800"] {
    color: #f6ad55 !important;
}

body.dark div[style*="color: #4CAF50"] {
    color: #68d391 !important;
}

body.dark div[style*="color: var(--accent-color)"] {
    color: var(--accent-color) !important;
}

/* Conteneurs avec bordures spéciales */
body.dark div[style*="border: 2px solid #4CAF50"] {
    background-color: #2d3748 !important;
    border-color: #68d391 !important;
}

body.dark div[style*="border-left: 4px solid #2196F3"] {
    background-color: #2a4a6b !important;
    border-left-color: #63b3ed !important;
}

/* Champs de saisie en mode sombre */
body.dark .feuille-caisse-input {
    background-color: #374151 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

body.dark .feuille-caisse-input:focus {
    border-color: var(--accent-color) !important;
    box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb), 0.2) !important;
}

body.dark .feuille-caisse-input[readonly] {
    background-color: #2d3748 !important;
    color: #a0aec0 !important;
}

/* Éléments de chèques */
body.dark .cheque-item {
    background-color: #374151 !important;
    border-color: #4a5568 !important;
}

/* Boutons */
body.dark .btn-add-cheque {
    background-color: var(--accent-color) !important;
}

body.dark .btn-remove-cheque {
    background-color: #dc3545 !important;
}

/* Totaux et affichages */
body.dark .feuille-caisse-total {
    background-color: #374151 !important;
}

/* Cases grisées en mode sombre */
body.dark div[style*="background-color: #f5f5f5"] {
    background-color: #2b2b2b !important;
    color: #666666 !important;
}

/* Améliorer la lisibilité des titres retrait en mode sombre */
body.dark div[style*="background-color: #fff3e0"] {
    background-color: #4a3728 !important;
    color: #fbbf24 !important;
}

body.dark div[style*="background-color: #fce4ec"] {
    background-color: #4c1d3d !important;
    color: #f472b6 !important;
}

/* Masquer les flèches d'incrémentation des champs numériques */
.feuille-caisse-input::-webkit-outer-spin-button,
.feuille-caisse-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.feuille-caisse-input[type=number] {
    -moz-appearance: textfield;
}
</style>

<h1 class="no-print">📊 Nouvelle Feuille de Caisse</h1>

<!-- Titre pour l'impression -->
<div style="display: none;" class="print-only">
    <h1 style="text-align: center; margin: 0 0 10px 0; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 14pt;">
        FEUILLE DE CAISSE - <?= date('d/m/Y', strtotime($feuille_existante['date_comptage'] ?? $date_defaut)) ?>
    </h1>
</div>

<?php if ($message): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?= htmlspecialchars($error) ?>
        <?php if (strpos($error, "n'existe pas encore") !== false): ?>
            <br><br>
            <a href="index.php?page=create_feuille_caisse_table" 
               style="background-color: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;">
                🔧 Créer la table maintenant
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($feuille_existante): ?>
    <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
        <strong>⚠️ Une feuille de caisse existe déjà pour cette date.</strong><br>
        Total actuel : <?= number_format($feuille_existante['total_caisse'], 2) ?> €<br>
        <?php if ($modification_autorisee): ?>
            Le formulaire ci-dessous mettra à jour cette feuille.
        <?php else: ?>
            <strong>⚠️ Modification non autorisée :</strong> Vous ne pouvez modifier que la feuille du jour même (<?= date('d/m/Y') ?>).
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$modification_autorisee && $date_defaut !== $date_aujourd_hui): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <strong>🔒 Mode consultation uniquement</strong><br>
        Cette feuille de caisse est en lecture seule car elle ne correspond pas à la date d'aujourd'hui.<br>
        Seule la feuille du jour (<?= date('d/m/Y') ?>) peut être modifiée.
        <br><br>
        <button onclick="window.print()" class="no-print" style="background-color: #17a2b8; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
            🖨️ Imprimer cette feuille
        </button>
        <a href="index.php?page=feuille_caisse_add" class="no-print" style="background-color: var(--accent-color); color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;">
            📊 Feuille du jour
        </a>
    </div>
<?php endif; ?>

<form method="POST" class="feuille-caisse-container" <?= !$modification_autorisee ? 'onsubmit="return false;"' : '' ?>>
    <div style="margin-bottom: 20px;">
        <label for="date_comptage" class="feuille-caisse-label">Date du comptage :</label>
        <input type="date" id="date_comptage" name="date_comptage"
               value="<?= $feuille_existante['date_comptage'] ?? $date_defaut ?>"
               class="feuille-caisse-input" style="width: 200px;" <?= !$modification_autorisee ? 'readonly' : '' ?>>
    </div>

    <!-- Contrôle avec feuille précédente - DÉPLACÉ EN HAUT -->
    <div class="feuille-caisse-container">
        <h3 class="feuille-caisse-section">🔍 Contrôle avec la feuille précédente</h3>
        
        <?php if ($feuille_precedente): ?>
            <div style="background-color: #e7f3ff; padding: 15px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #2196F3;">
                <strong>📅 Feuille précédente (<?= date('d/m/Y', strtotime($feuille_precedente['date_comptage'])) ?>) :</strong>
                <span style="font-size: 16px; font-weight: bold; color: #2196F3; margin-left: 10px;">
                    <?= number_format($solde_precedent_auto, 2) ?> € en espèces
                </span>
            </div>
        <?php endif; ?>
        
        <div class="feuille-caisse-grid">
            <div class="field-group">
                <label class="feuille-caisse-label">
                    Solde de départ (€)
                    <small style="color: #666; display: block;">(Espèces précédentes)</small>
                </label>
                <input type="number" id="solde_base" step="0.01"
                       value="<?= $solde_precedent_auto ?>"
                       class="feuille-caisse-input" readonly style="background-color: #f0f0f0;">
            </div>
            <div class="field-group">
                <label for="ajustement_especes" class="feuille-caisse-label">
                    Ajustement (€)
                    <small style="color: #666; display: block;">(+ entrées / - sorties)</small>
                </label>
                <input type="number" id="ajustement_especes" name="ajustement_especes" step="0.01"
                       value="<?= $feuille_existante['ajustement_especes'] ?? 0 ?>"
                       class="feuille-caisse-input"
                       onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?>
                       placeholder="Ex: +50 ou -20">
            </div>
        </div>
        
        <input type="hidden" id="solde_precedent" name="solde_precedent" value="0">
        
        <div class="feuille-caisse-total" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; text-align: center;">
                <div>
                    <div style="font-size: 12px; color: var(--text-color, #666);">Espèces attendues</div>
                    <div style="font-size: 18px; font-weight: bold; color: #2196F3;" id="solde_attendu_display">0,00 €</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: var(--text-color, #666);">Espèces comptées</div>
                    <div style="font-size: 18px; font-weight: bold; color: #FF9800;" id="especes_comptees_display">0,00 €</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: var(--text-color, #666);">Écart</div>
                    <div style="font-size: 20px; font-weight: bold;" id="ecart_display">0,00 €</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Espèces - Layout compact avec 4 colonnes et valeurs sur le côté -->
    <div class="feuille-caisse-container">
        <h3 class="feuille-caisse-section">💰 Espèces - Comptage et Retraits</h3>
        
        <!-- En-têtes des colonnes -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 10px; font-weight: bold; text-align: center; font-size: 12px;">
            <div style="font-weight: bold;">Billets</div>
            <div style="background-color: #e3f2fd; padding: 6px; border-radius: 4px; border: 2px solid #2196F3;">
                📊 Comptage
            </div>
            <div style="background-color: #fff3e0; padding: 6px; border-radius: 4px; border: 2px solid #FF9800;">
                🏦 Retrait
            </div>
            <div style="font-weight: bold;">Pièces</div>
            <div style="background-color: #e8f5e8; padding: 6px; border-radius: 4px; border: 2px solid #4CAF50;">
                📊 Comptage
            </div>
            <div style="background-color: #fce4ec; padding: 6px; border-radius: 4px; border: 2px solid #E91E63;">
                🏦 Retrait
            </div>
        </div>
        
        <!-- Lignes de saisie avec 6 colonnes -->
        
        <!-- 500 € / 2,00 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">500 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_500" name="billets_500" min="0"
                       value="<?= $feuille_existante['billets_500'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="1">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_500" name="retrait_billets_500" min="0"
                       value="<?= $feuille_existante['retrait_billets_500'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="15">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">2,00 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_200" name="pieces_200" min="0"
                       value="<?= $feuille_existante['pieces_200'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="29">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_200" name="retrait_pieces_200" min="0"
                       value="<?= $feuille_existante['retrait_pieces_200'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="43">
            </div>
        </div>
        
        <!-- 200 € / 1,00 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">200 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_200" name="billets_200" min="0"
                       value="<?= $feuille_existante['billets_200'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="2">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_200" name="retrait_billets_200" min="0"
                       value="<?= $feuille_existante['retrait_billets_200'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="16">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">1,00 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_100" name="pieces_100" min="0"
                       value="<?= $feuille_existante['pieces_100'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="30">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_100" name="retrait_pieces_100" min="0"
                       value="<?= $feuille_existante['retrait_pieces_100'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="44">
            </div>
        </div>
        
        <!-- 100 € / 0,50 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">100 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_100" name="billets_100" min="0"
                       value="<?= $feuille_existante['billets_100'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="3">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_100" name="retrait_billets_100" min="0"
                       value="<?= $feuille_existante['retrait_billets_100'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="17">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,50 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_050" name="pieces_050" min="0"
                       value="<?= $feuille_existante['pieces_050'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="31">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_050" name="retrait_pieces_050" min="0"
                       value="<?= $feuille_existante['retrait_pieces_050'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="45">
            </div>
        </div>
        
        <!-- 50 € / 0,20 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">50 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_050" name="billets_050" min="0"
                       value="<?= $feuille_existante['billets_050'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="4">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_050" name="retrait_billets_050" min="0"
                       value="<?= $feuille_existante['retrait_billets_050'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="18">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,20 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_020" name="pieces_020" min="0"
                       value="<?= $feuille_existante['pieces_020'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="32">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_020" name="retrait_pieces_020" min="0"
                       value="<?= $feuille_existante['retrait_pieces_020'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="46">
            </div>
        </div>
        
        <!-- 20 € / 0,10 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">20 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_020" name="billets_020" min="0"
                       value="<?= $feuille_existante['billets_020'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="5">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_020" name="retrait_billets_020" min="0"
                       value="<?= $feuille_existante['retrait_billets_020'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="19">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,10 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_010" name="pieces_010" min="0"
                       value="<?= $feuille_existante['pieces_010'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="33">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_010" name="retrait_pieces_010" min="0"
                       value="<?= $feuille_existante['retrait_pieces_010'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="47">
            </div>
        </div>
        
        <!-- 10 € / 0,05 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">10 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_010" name="billets_010" min="0"
                       value="<?= $feuille_existante['billets_010'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="6">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_010" name="retrait_billets_010" min="0"
                       value="<?= $feuille_existante['retrait_billets_010'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="20">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,05 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_005" name="pieces_005" min="0"
                       value="<?= $feuille_existante['pieces_005'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="34">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_005" name="retrait_pieces_005" min="0"
                       value="<?= $feuille_existante['retrait_pieces_005'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="48">
            </div>
        </div>
        
        <!-- 5 € / 0,02 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 6px;">
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">5 €</div>
            <div class="field-group" style="border: 1px solid #2196F3; border-radius: 3px; padding: 4px;">
                <input type="number" id="billets_005" name="billets_005" min="0"
                       value="<?= $feuille_existante['billets_005'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="7">
            </div>
            <div class="field-group" style="border: 1px solid #FF9800; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_billets_005" name="retrait_billets_005" min="0"
                       value="<?= $feuille_existante['retrait_billets_005'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="21">
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,02 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_002" name="pieces_002" min="0"
                       value="<?= $feuille_existante['pieces_002'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="35">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_002" name="retrait_pieces_002" min="0"
                       value="<?= $feuille_existante['retrait_pieces_002'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="49">
            </div>
        </div>
        
        <!-- - / 0,01 € -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; justify-content: center; padding: 8px;">
                <!-- Colonne vide sous les billets -->
            </div>
            <div style="background-color: #f5f5f5; border-radius: 3px; padding: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">
                -
            </div>
            <div style="background-color: #f5f5f5; border-radius: 3px; padding: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">
                -
            </div>
            <div style="display: flex; align-items: center; font-weight: bold; font-size: 14px; padding: 8px;">0,01 €</div>
            <div class="field-group" style="border: 1px solid #4CAF50; border-radius: 3px; padding: 4px;">
                <input type="number" id="pieces_001" name="pieces_001" min="0"
                       value="<?= $feuille_existante['pieces_001'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="36">
            </div>
            <div class="field-group" style="border: 1px solid #E91E63; border-radius: 3px; padding: 4px;">
                <input type="number" id="retrait_pieces_001" name="retrait_pieces_001" min="0"
                       value="<?= $feuille_existante['retrait_pieces_001'] ?? 0 ?>"
                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?> tabindex="50">
            </div>
        </div>
        
        <!-- Totaux compacts -->
        <div style="display: grid; grid-template-columns: 80px 1fr 1fr 80px 1fr 1fr; gap: 8px; margin-top: 15px; font-weight: bold;">
            <div style="display: flex; align-items: center; font-size: 12px;">TOTAUX</div>
            <div class="feuille-caisse-total" style="background-color: #e3f2fd; border: 2px solid #2196F3; padding: 6px; text-align: center; font-size: 12px;">
                <div style="margin-bottom: 2px;">BILLETS</div>
                <span id="total_billets_display">0,00 €</span>
            </div>
            <div class="feuille-caisse-total" style="background-color: #fff3e0; border: 2px solid #FF9800; padding: 6px; text-align: center; font-size: 12px;">
                <div style="margin-bottom: 2px;">RETRAIT B.</div>
                <span id="total_retrait_billets_display">0,00 €</span>
            </div>
            <div style="display: flex; align-items: center; font-size: 12px;">TOTAUX</div>
            <div class="feuille-caisse-total" style="background-color: #e8f5e8; border: 2px solid #4CAF50; padding: 6px; text-align: center; font-size: 12px;">
                <div style="margin-bottom: 2px;">PIÈCES</div>
                <span id="total_pieces_display">0,00 €</span>
            </div>
            <div class="feuille-caisse-total" style="background-color: #fce4ec; border: 2px solid #E91E63; padding: 6px; text-align: center; font-size: 12px;">
                <div style="margin-bottom: 2px;">RETRAIT P.</div>
                <span id="total_retrait_pieces_display">0,00 €</span>
            </div>
        </div>
    </div>

    <!-- Chèques améliorés -->
    <div class="feuille-caisse-container">
        <h3 class="feuille-caisse-section">💳 Chèques</h3>
        
        <div id="cheques-container">
            <?php if (!empty($cheques_existants)): ?>
                <?php foreach ($cheques_existants as $index => $cheque): ?>
                    <div class="cheque-item">
                        <div style="display: flex; gap: 15px; align-items: end;">
                            <div class="field-group" style="flex: 0 0 120px;">
                                <label class="feuille-caisse-label">Montant (€)</label>
                                <input type="number" name="cheques[<?= $index ?>][montant]" step="0.01" min="0"
                                       value="<?= $cheque['montant'] ?>"
                                       class="feuille-caisse-input" onchange="calculerTotaux()" <?= !$modification_autorisee ? 'readonly' : '' ?>>
                            </div>
                            <div class="field-group" style="flex: 0 0 100px;">
                                <label class="feuille-caisse-label">Émetteur</label>
                                <input type="text" name="cheques[<?= $index ?>][emetteur]"
                                       value="<?= htmlspecialchars($cheque['emetteur']) ?>"
                                       class="feuille-caisse-input" placeholder="Nom de l'émetteur">
                            </div>
                            <div class="field-group" style="flex: 1;">
                                <label class="feuille-caisse-label">N° chèque</label>
                                <input type="text" name="cheques[<?= $index ?>][numero]"
                                       value="<?= htmlspecialchars($cheque['numero']) ?>"
                                       class="feuille-caisse-input" placeholder="Numéro">
                            </div>
                            <div class="field-group" style="flex: 0 0 40px;">
                                <label class="feuille-caisse-label">&nbsp;</label>
                                <button type="button" class="btn-remove-cheque" onclick="supprimerCheque(this)" style="height: 28px; font-size: 10px; padding: 2px 4px; width: 100%;">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="btn-add-cheque <?= !$modification_autorisee ? 'no-print' : '' ?>"
                onclick="ajouterCheque()" <?= !$modification_autorisee ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
            ➕ Ajouter un chèque
        </button>
        
        <button type="button" class="btn-add-cheque <?= !$modification_autorisee ? 'no-print' : '' ?>"
                onclick="recupererCheques()" 
                style="background-color: #607d8b; margin-left: 10px;"
                <?= !$modification_autorisee ? 'disabled style="opacity: 0.5; cursor: not-allowed; background-color: #607d8b; margin-left: 10px;"' : '' ?>>
            🔄 Récupérer derniers chèques
        </button>
        
        <div class="feuille-caisse-total" style="margin-top: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <strong>Nombre de chèques : <span id="nb_cheques_display">0</span></strong>
                </div>
                <div>
                    <strong>Total chèques : <span id="total_cheques_display">0,00 €</span></strong>
                </div>
            </div>
        </div>
    </div>


    <!-- Totaux finaux -->
    <div class="feuille-caisse-container" style="background-color: var(--secondary-bg, #f8f9fa); border: 2px solid #4CAF50;">
        <h3 style="color: #4CAF50; margin-top: 0; text-align: center;">💰 RÉCAPITULATIF FINAL</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="background-color: var(--card-bg); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 14px; color: var(--text-color, #666);">Total Espèces</div>
                <div style="font-size: 24px; font-weight: bold; color: #2196F3;" id="total_especes_display">0,00 €</div>
            </div>
            <div style="background-color: var(--card-bg); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 14px; color: var(--text-color, #666);">Total Chèques</div>
                <div style="font-size: 24px; font-weight: bold; color: #FF9800;" id="total_cheques_display_final">0,00 €</div>
            </div>
            <div style="background-color: var(--card-bg); padding: 15px; border-radius: 4px; text-align: center; border: 2px solid #4CAF50;">
                <div style="font-size: 14px; color: var(--text-color, #666);">TOTAL CAISSE</div>
                <div style="font-size: 28px; font-weight: bold; color: #4CAF50;" id="total_caisse_display">0,00 €</div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div style="margin-bottom: 20px;">
        <label for="notes" class="feuille-caisse-label">Notes (optionnel) :</label>
        <textarea id="notes" name="notes" rows="3" 
                  class="feuille-caisse-input" style="resize: vertical;"
                  placeholder="Remarques sur le comptage..."><?= htmlspecialchars($feuille_existante['notes'] ?? '') ?></textarea>
    </div>

    <div style="text-align: center;">
        <?php if ($modification_autorisee): ?>
            <button type="submit" style="background-color: var(--accent-color); color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">
                💾 Enregistrer la Feuille de Caisse
            </button>
        <?php else: ?>
            <button onclick="window.print()" type="button" class="no-print" style="background-color: #17a2b8; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-right: 15px;">
                🖨️ Imprimer cette feuille
            </button>
        <?php endif; ?>
        <a href="index.php?page=dashboard_caisse" style="margin-left: 15px; padding: 12px 30px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
            ↩️ Retour au Dashboard
        </a>
    </div>
</form>

<script>
let chequeIndex = <?= count($cheques_existants) ?>;

function ajouterCheque() {
    const container = document.getElementById('cheques-container');
    const chequeDiv = document.createElement('div');
    chequeDiv.className = 'cheque-item';
    chequeDiv.innerHTML = `
        <div style="display: flex; gap: 15px; align-items: end;">
            <div class="field-group" style="flex: 0 0 120px;">
                <label class="feuille-caisse-label">Montant (€)</label>
                <input type="number" name="cheques[${chequeIndex}][montant]" step="0.01" min="0"
                       class="feuille-caisse-input" onchange="calculerTotaux()" placeholder="0.00">
            </div>
            <div class="field-group" style="flex: 0 0 100px;">
                <label class="feuille-caisse-label">Émetteur</label>
                <input type="text" name="cheques[${chequeIndex}][emetteur]"
                       class="feuille-caisse-input" placeholder="Nom de l'émetteur">
            </div>
            <div class="field-group" style="flex: 1;">
                <label class="feuille-caisse-label">N° chèque</label>
                <input type="text" name="cheques[${chequeIndex}][numero]"
                       class="feuille-caisse-input" placeholder="Numéro">
            </div>
            <div class="field-group" style="flex: 0 0 40px;">
                <label class="feuille-caisse-label">&nbsp;</label>
                <button type="button" class="btn-remove-cheque" onclick="supprimerCheque(this)" style="height: 28px; font-size: 10px; padding: 2px 4px; width: 100%;">
                    🗑️
                </button>
            </div>
        </div>
    `;
    container.appendChild(chequeDiv);
    chequeIndex++;
}

function supprimerCheque(button) {
    button.closest('.cheque-item').remove();
    calculerTotaux();
}

function recupererCheques() {
    if (!confirm('Voulez-vous vraiment récupérer les chèques de la dernière feuille ? Cela les ajoutera à la liste actuelle.')) {
        return;
    }

    // Date actuelle pour référence
    const dateComptage = document.getElementById('date_comptage').value;
    
    fetch(`api/get_last_cheques.php?date=${dateComptage}`)
        .then(response => response.json())
        .then(cheques => {
            if (cheques && cheques.length > 0) {
                let count = 0;
                cheques.forEach(cheque => {
                    // Utiliser la fonction existante pour créer la structure
                    ajouterCheque();
                    
                    // Récupérer le dernier élément ajouté (index actuel - 1 car ajouterCheque incrémente)
                    const lastIndex = chequeIndex - 1;
                    
                    // Remplir les champs
                    const montantInput = document.querySelector(`input[name="cheques[${lastIndex}][montant]"]`);
                    const emetteurInput = document.querySelector(`input[name="cheques[${lastIndex}][emetteur]"]`);
                    // On ne récupère pas le numéro du chèque car il change forcément
                    // const numeroInput = document.querySelector(`input[name="cheques[${lastIndex}][numero]"]`);
                    
                    if (montantInput) montantInput.value = cheque.montant;
                    if (emetteurInput) emetteurInput.value = cheque.emetteur;
                    
                    count++;
                });
                
                calculerTotaux();
                alert(`${count} chèque(s) récupéré(s) avec succès !`);
            } else {
                alert("Aucun chèque trouvé dans la feuille précédente.");
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert("Une erreur est survenue lors de la récupération des chèques.");
        });
}

function calculerTotaux() {
    // Calcul du total des pièces
    const pieces = [
        { id: 'pieces_001', valeur: 0.01 },
        { id: 'pieces_002', valeur: 0.02 },
        { id: 'pieces_005', valeur: 0.05 },
        { id: 'pieces_010', valeur: 0.10 },
        { id: 'pieces_020', valeur: 0.20 },
        { id: 'pieces_050', valeur: 0.50 },
        { id: 'pieces_100', valeur: 1.00 },
        { id: 'pieces_200', valeur: 2.00 }
    ];
    
    let totalPieces = 0;
    pieces.forEach(piece => {
        const nombre = parseInt(document.getElementById(piece.id).value) || 0;
        totalPieces += nombre * piece.valeur;
    });
    
    // Calcul du total des billets
    const billets = [
        { id: 'billets_005', valeur: 5 },
        { id: 'billets_010', valeur: 10 },
        { id: 'billets_020', valeur: 20 },
        { id: 'billets_050', valeur: 50 },
        { id: 'billets_100', valeur: 100 },
        { id: 'billets_200', valeur: 200 },
        { id: 'billets_500', valeur: 500 }
    ];
    
    let totalBillets = 0;
    billets.forEach(billet => {
        const nombre = parseInt(document.getElementById(billet.id).value) || 0;
        totalBillets += nombre * billet.valeur;
    });
    
    // Calcul du total des retraits pièces
    const retraitPieces = [
        { id: 'retrait_pieces_001', valeur: 0.01 },
        { id: 'retrait_pieces_002', valeur: 0.02 },
        { id: 'retrait_pieces_005', valeur: 0.05 },
        { id: 'retrait_pieces_010', valeur: 0.10 },
        { id: 'retrait_pieces_020', valeur: 0.20 },
        { id: 'retrait_pieces_050', valeur: 0.50 },
        { id: 'retrait_pieces_100', valeur: 1.00 },
        { id: 'retrait_pieces_200', valeur: 2.00 }
    ];
    
    let totalRetraitPieces = 0;
    retraitPieces.forEach(piece => {
        const element = document.getElementById(piece.id);
        if (element) {
            const nombre = parseInt(element.value) || 0;
            totalRetraitPieces += nombre * piece.valeur;
        }
    });
    
    // Calcul du total des retraits billets
    const retraitBillets = [
        { id: 'retrait_billets_005', valeur: 5 },
        { id: 'retrait_billets_010', valeur: 10 },
        { id: 'retrait_billets_020', valeur: 20 },
        { id: 'retrait_billets_050', valeur: 50 },
        { id: 'retrait_billets_100', valeur: 100 },
        { id: 'retrait_billets_200', valeur: 200 },
        { id: 'retrait_billets_500', valeur: 500 }
    ];
    
    let totalRetraitBillets = 0;
    retraitBillets.forEach(billet => {
        const element = document.getElementById(billet.id);
        if (element) {
            const nombre = parseInt(element.value) || 0;
            totalRetraitBillets += nombre * billet.valeur;
        }
    });
    
    // Calcul du total des chèques
    let totalCheques = 0;
    let nbCheques = 0;
    const chequesInputs = document.querySelectorAll('input[name*="[montant]"]');
    chequesInputs.forEach(input => {
        const montant = parseFloat(input.value) || 0;
        if (montant > 0) {
            totalCheques += montant;
            nbCheques++;
        }
    });
    
    // Calculs finaux
    const totalEspeces = totalPieces + totalBillets;
    const totalRetraitEspeces = totalRetraitPieces + totalRetraitBillets;
    const totalCaisse = totalEspeces + totalCheques;
    
    // Mise à jour de l'affichage - Comptage
    document.getElementById('total_pieces_display').textContent = totalPieces.toFixed(2) + ' €';
    document.getElementById('total_billets_display').textContent = totalBillets.toFixed(2) + ' €';
    document.getElementById('total_especes_display').textContent = totalEspeces.toFixed(2) + ' €';
    
    // Mise à jour de l'affichage - Retraits
    const retraitPiecesDisplay = document.getElementById('total_retrait_pieces_display');
    if (retraitPiecesDisplay) {
        retraitPiecesDisplay.textContent = totalRetraitPieces.toFixed(2) + ' €';
    }
    const retraitBilletsDisplay = document.getElementById('total_retrait_billets_display');
    if (retraitBilletsDisplay) {
        retraitBilletsDisplay.textContent = totalRetraitBillets.toFixed(2) + ' €';
    }
    
    // Mise à jour de l'affichage - Chèques et total
    document.getElementById('total_cheques_display').textContent = totalCheques.toFixed(2) + ' €';
    document.getElementById('total_cheques_display_final').textContent = totalCheques.toFixed(2) + ' €';
    document.getElementById('total_caisse_display').textContent = totalCaisse.toFixed(2) + ' €';
    document.getElementById('nb_cheques_display').textContent = nbCheques;
    
    // Calcul du montant attendu (solde de base + ajustement)
    const soldeBase = parseFloat(document.getElementById('solde_base').value) || 0;
    const ajustement = parseFloat(document.getElementById('ajustement_especes').value) || 0;
    const montantAttendu = soldeBase + ajustement;
    
    // Mise à jour du champ caché pour la sauvegarde
    document.getElementById('solde_precedent').value = montantAttendu.toFixed(2);
    
    // Calcul de l'écart avec le montant attendu
    const ecart = totalEspeces - montantAttendu;
    
    // Mise à jour de l'affichage du contrôle
    if (document.getElementById('solde_attendu_display')) {
        document.getElementById('solde_attendu_display').textContent = montantAttendu.toFixed(2) + ' €';
        document.getElementById('especes_comptees_display').textContent = totalEspeces.toFixed(2) + ' €';
        
        const ecartDisplay = document.getElementById('ecart_display');
        ecartDisplay.textContent = (ecart >= 0 ? '+' : '') + ecart.toFixed(2) + ' €';
        
        // Couleur selon l'écart
        if (ecart > 0) {
            ecartDisplay.style.color = '#4CAF50'; // Vert pour excédent
        } else if (ecart < 0) {
            ecartDisplay.style.color = '#F44336'; // Rouge pour manque
        } else {
            ecartDisplay.style.color = '#2196F3'; // Bleu pour équilibré
        }
    }
}

// Calcul initial au chargement de la page
document.addEventListener('DOMContentLoaded', calculerTotaux);
</script>