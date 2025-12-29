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

// Fonction pour formater le log
function formatLogContent($logContent) {
    if (empty($logContent)) return '';

    $lines = explode("\n", $logContent);
    $formattedHtml = '<div class="log-container">';
    
    $rawBuffer = [];

    // Helper closure to flush buffer
    $flushBuffer = function() use (&$formattedHtml, &$rawBuffer) {
        if (empty($rawBuffer)) return;

        $count = count($rawBuffer);
        if ($count > 5) {
            $formattedHtml .= '<details class="log-details">';
            $formattedHtml .= '<summary class="log-summary">Afficher ' . $count . ' lignes masquées...</summary>';
            $formattedHtml .= '<div class="log-details-content">';
            foreach ($rawBuffer as $rawLine) {
                $formattedHtml .= '<div class="log-raw">' . htmlspecialchars($rawLine) . '</div>';
            }
            $formattedHtml .= '</div>';
            $formattedHtml .= '</details>';
        } else {
            foreach ($rawBuffer as $rawLine) {
                $formattedHtml .= '<div class="log-raw">' . htmlspecialchars($rawLine) . '</div>';
            }
        }
        $rawBuffer = [];
    };

    foreach ($lines as $line) {
        // Nettoyage agressif des espaces invisibles :
        // 1. Décodage des entités HTML (pour les &nbsp; et autres)
        $cleanLine = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 2. Suppression des codes ANSI (couleurs terminal, curseur, etc.)
        $cleanLine = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $cleanLine);

        // 3. Suppression via Regex :
        // \p{Z} : tous les séparateurs (espaces, insécables, etc.)
        // \p{C} : tous les caractères de contrôle
        // \x{2800} : Caractère "Braille Pattern Blank" souvent utilisé dans les barres de progression CLI
        $cleanLine = preg_replace('/[\p{Z}\p{C}\x{2800}]/u', '', $cleanLine);
        
        if (empty($cleanLine)) {
            continue; // Skip empty lines
        }

        // Check if line starts with [YYYY-MM-DD HH:MM:SS]
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.*)$/', $line, $matches)) {
            // Flush any pending raw lines before adding a new structured entry
            $flushBuffer();

            $date = $matches[1];
            $rest = $matches[2];

            $formattedHtml .= '<div class="log-entry">';
            $formattedHtml .= '<span class="log-date">[' . $date . ']</span>';

            // Extract Category [Category]
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $rest, $catMatches)) {
                $category = $catMatches[1];
                $message = $catMatches[2];
                $formattedHtml .= ' <span class="log-category">[' . htmlspecialchars($category) . ']</span>';
                
                // Extract Type [Type] (Optional)
                if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $message, $typeMatches)) {
                     $type = $typeMatches[1];
                     $finalMessage = $typeMatches[2];
                     $formattedHtml .= ' <span class="log-type">[' . htmlspecialchars($type) . ']</span>';
                     $formattedHtml .= ' <span class="log-message">' . htmlspecialchars($finalMessage) . '</span>';
                } else {
                     $formattedHtml .= ' <span class="log-message">' . htmlspecialchars($message) . '</span>';
                }

            } else {
                $formattedHtml .= ' <span class="log-message">' . htmlspecialchars($rest) . '</span>';
            }
            $formattedHtml .= '</div>';
        } else {
            // Add to buffer
            $rawBuffer[] = $line;
        }
    }
    
    // Flush remaining buffer at the end
    $flushBuffer();

    $formattedHtml .= '</div>';
    return $formattedHtml;
}

if (empty($intervention_id)) {
    $message = '<p style="color: red;">ID d\'intervention manquant.</p>';
} else {
    if (isset($pdo)) {
        // Récupérer les données de l'intervention avec les informations du client
        try {
            // Vérifier si la table intervention_statuts existe
            $tableExists = false;
            try {
                $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
                $tableExists = true;
            } catch (PDOException $e) {
                // Table n'existe pas encore
            }
            
            // Vérifier si la colonne statuts_historique existe
            $hasHistoriqueColumn = false;
            try {
                $pdo->query("SELECT statuts_historique FROM inter LIMIT 1");
                $hasHistoriqueColumn = true;
            } catch (PDOException $e) {
                // Colonne n'existe pas encore
            }
            
            if ($tableExists && $hasHistoriqueColumn) {
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
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail,
                        s.nom as statut_nom,
                        s.couleur as statut_couleur,
                        s.description as statut_description
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                    WHERE i.id = :id
                ");
            } else if ($tableExists) {
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
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail,
                        s.nom as statut_nom,
                        s.couleur as statut_couleur,
                        s.description as statut_description
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                    WHERE i.id = :id
                ");
            } else {
                // Fallback sans les statuts
                $stmt = $pdo->prepare("
                    SELECT
                        i.id,
                        i.id_client,
                        i.date,
                        i.en_cours,
                        i.info,
                        i.nettoyage,
                        i.info_log,
                        i.info_log,
                        i.note_user,
                        i.ip_vnc,
                        i.pass_vnc,
                        c.nom as client_nom,
                        c.prenom as client_prenom,
                        c.adresse1,
                        c.adresse2,
                        c.cp,
                        c.ville,
                        c.telephone,
                        c.portable,
                        c.mail
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    WHERE i.id = :id
                ");
            }
            $stmt->execute([':id' => $intervention_id]);
            $intervention = $stmt->fetch();
            
            if (!$intervention) {
                $message = '<p style="color: red;">Intervention non trouvée.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur lors de la récupération de l\'intervention : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $message = "<p style='color: red;'>Erreur de configuration : la connexion à la base de données n'est pas disponible.</p>";
    }
}
?>

<style>
/* Modern styles for interventions view page */
.intervention-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
}

.intervent.view-container {
    max-width: 95%; /* Était 1200px */
    width: 100%; /* S'assurer qu'on prend la largeur dispo */
    margin: 0 auto;
    padding: 20px;
    box-sizing: border-box; /* Important pour inclure le padding */
}

.intervention-detail {
    max-width: 95%; /* 95% de largeur */
    width: 100%;
    margin: 0 auto;
    background: transparent; /* Plus de fond blanc */
    padding: 0; /* Plus de padding inutile */
    border-radius: 0;
    box-shadow: none; /* Plus d'ombre sur le conteneur principal */
}

.intervention-header {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.intervention-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.95em;
    margin-bottom: 15px;
}

.intervention-id {
    font-size: 1.5em;
    font-weight: bold;
    color: #3498db;
    margin: 10px 0;
}

.intervention-date {
    color: var(--text-muted);
    font-size: 1em;
    margin-top: 8px;
}

.detail-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.detail-section h3 {
   margin-top: 0;
    color: #3498db;
    border-bottom: 2px solid #3498db;
    padding-bottom: 12px;
    font-size: 1.2em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.client-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.client-field {
    margin-bottom: 8px;
}

.client-field strong {
    display: block;
    color: var(--text-muted);
    margin-bottom: 5px;
    font-size: 0.85em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.text-content {
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    margin-top: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    min-height: 60px;
}

.log-content {
    background-color: #1e1e1e;
    color: #d4d4d4;
    border: 1px solid #333;
    border-radius: 8px;
    padding: 15px;
    margin-top: 12px;
    font-family: 'Courier New', Consolas, monospace;
    font-size: 0.9em;
    line-height: 1.5;
    white-space: pre-wrap;
    word-wrap: break-word;
    min-height: 60px;
    overflow-x: auto;
}

.actions-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 30px;
    padding: 25px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1em;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
    text-decoration: none;
    color: white;
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
    text-decoration: none;
}

.btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
    text-decoration: none;
    color: white;
}

.nettoyage-list {
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.logiciel-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 0;
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    border-left: 4px solid var(--border-color);
    transition: all 0.2s ease;
}

.logiciel-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.logiciel-item.passe {
    border-left-color: #28a745;
    background-color: rgba(40, 167, 69, 0.05);
}

.logiciel-item.non-passe {
    border-left-color: #dc3545;
    background-color: rgba(220, 53, 69, 0.05);
}

.logiciel-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    margin-right: 15px;
    min-width: 80px;
    text-align: center;
}

.logiciel-status.passe {
    background-color: #28a745;
    color: white;
}

.logiciel-status.non-passe {
    background-color: #dc3545;
    color: white;
}

.logiciel-nom {
    font-weight: 600;
    margin-right: 15px;
    min-width: 200px;
    color: var(--text-color);
}

.logiciel-info {
    color: var(--text-muted);
    font-style: italic;
}

.historique-card {
    background: var(--bg-secondary, #f8f9fa);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}

.historique-card h4 {
    margin-top: 0;
    color: #3498db;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
    text-decoration: none;
    color: white;
}

/* Dark mode */
body.dark .log-content {
    background-color: #0d0d0d;
    border-color: #555;
    color: #e0e0e0;
}

body.dark .client-info-box {
    background: #1e1e1e !important;
    border-color: #333 !important;
}

body.dark .client-info-box h3 {
    color: #fff !important;
    border-color: #333 !important;
}

body.dark .client-info-box strong {
    color: #aaa !important;
}

body.dark .client-info-box span {
    color: #fff !important;
}

/* Log Styles */
.log-container {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.9em;
    background-color: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 8px;
    overflow-x: auto;
    line-height: 1.5;
    border: 1px solid #333;
}

.log-entry {
    margin-bottom: 2px;
    white-space: pre-wrap; 
}

.log-date {
    color: #569cd6; /* Blue */
}

.log-category {
    color: #c586c0; /* Purple */
    font-weight: bold;
}

.log-type {
    color: #4ec9b0; /* Teal */
}

.log-message {
    color: #ce9178; /* Orange/Redish */
}

.log-raw {
    color: #d4d4d4; /* Default text */
    padding-left: 20px; /* Indentation for command output */
    white-space: pre-wrap;
    opacity: 0.9;
}

.log-empty-line {
    height: 1em;
}

.log-details {
    margin: 5px 0;
    border: 1px solid #444;
    border-radius: 4px;
    background-color: #252526;
}

.log-summary {
    cursor: pointer;
    padding: 5px 10px;
    color: #858585;
    font-style: italic;
    user-select: none;
}

.log-summary:hover {
    color: #d4d4d4;
    background-color: #2d2d2d;
}

.log-details-content {
    padding: 5px 0;
    border-top: 1px solid #444;
}
</style>

<div class="intervention-page">
    <div class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <h1 style="margin: 0;">
                <span>🔍</span>
                Détail de l'intervention
            </h1>
            
            <?php if ($intervention): ?>
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="text-align: right;">
                    <div style="font-size: 1.2em; font-weight: bold; margin-bottom: 2px;">ID: <?= htmlspecialchars($intervention['id']) ?></div>
                    <div style="font-size: 0.85em; opacity: 0.9;">Créée le <?= date('d/m/Y à H:i', strtotime($intervention['date'])) ?></div>
                </div>
                
                <?php if (!empty($intervention['statut_nom'])): ?>
                    <span class="intervention-status" style="background-color: <?= htmlspecialchars($intervention['statut_couleur']) ?>; color: white; display: inline-flex; align-items: center; gap: 8px; margin: 0; border: 1px solid rgba(255,255,255,0.3); padding: 8px 15px; font-size: 1em;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: rgba(255,255,255,0.8);"></span>
                        <?= htmlspecialchars($intervention['statut_nom']) ?>
                    </span>
                    <?php if (!empty($intervention['statut_description'])): ?>
                        <!-- Description masquée à la demande de l'utilisateur -->
                    <?php endif; ?>
                <?php else: ?>
                    <span class="intervention-status <?= $intervention['en_cours'] ? 'en-cours' : 'cloturee' ?>" style="margin: 0;">
                        <?= $intervention['en_cours'] ? 'En cours' : 'Clôturée' ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
<div class="intervention-detail">

    <?php echo $message; ?>

    <?php if ($intervention): ?>
        <!-- Nouvelle disposition : Info Client (Gauche) + Historique (Droite) dans un seul bloc -->
        <div class="detail-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
            
            <!-- Colonne Gauche : Info Client -->
            <div>
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1em; color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span>👤</span> Coordonnées Client
                </h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <strong style="display: block; font-size: 0.85em; color: var(--text-muted); margin-bottom: 2px;">NOM COMPLET</strong>
                        <span style="font-weight: 500; font-size: 1.1em;"><?= htmlspecialchars(($intervention['client_nom'] ?? '') . ' ' . ($intervention['client_prenom'] ?? '')) ?></span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong style="display: block; font-size: 0.85em; color: var(--text-muted); margin-bottom: 2px;">EMAIL</strong>
                            <a href="mailto:<?= htmlspecialchars($intervention['mail'] ?? '') ?>" style="color: #3498db; text-decoration: none;"><?= htmlspecialchars($intervention['mail'] ?? 'Non renseigné') ?></a>
                        </div>
                        <div>
                            <strong style="display: block; font-size: 0.85em; color: var(--text-muted); margin-bottom: 2px;">TÉLÉPHONE</strong>
                            <?= htmlspecialchars($intervention['telephone'] ?? '') ?> 
                            <?php if(!empty($intervention['portable'])) echo ' / ' . htmlspecialchars($intervention['portable']); ?>
                            <?php if(empty($intervention['telephone']) && empty($intervention['portable'])) echo 'Non renseigné'; ?>
                        </div>
                    </div>
                    <div>
                        <strong style="display: block; font-size: 0.85em; color: var(--text-muted); margin-bottom: 2px;">ADRESSE</strong>
                        <?= htmlspecialchars($intervention['adresse1'] ?? '') ?>
                        <?php if (!empty($intervention['adresse2'])): ?>
                            <br><?= htmlspecialchars($intervention['adresse2']) ?>
                        <?php endif; ?>
                        <br><?= htmlspecialchars(($intervention['cp'] ?? '') . ' ' . ($intervention['ville'] ?? '')) ?>
                    </div>
                </div>
            </div>

            <!-- Colonne Droite : Historique des statuts -->
            <div style="border-left: 1px solid var(--border-color); padding-left: 30px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1em; color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span>📊</span> Historique des statuts
                </h3>
                
                <?php if (isset($intervention['statuts_historique']) && !empty($intervention['statuts_historique'])): ?>
                    <?php
                    $historique = getHistoriqueComplet($pdo, $intervention['statuts_historique']);
                    if (!empty($historique)):
                    ?>
                        <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach ($historique as $index => $entry): ?>
                                <div class="historique-entry" style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background-color: var(--bg-secondary, #f8f9fa); border-left: 4px solid <?= htmlspecialchars($entry['statut']['couleur']) ?>; border-radius: 4px; border: 1px solid var(--border-color, #dee2e6);">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;">
                                            <strong class="statut-nom" style="color: var(--text-color, #333); font-size: 0.95em;"><?= htmlspecialchars($entry['statut']['nom']) ?></strong>
                                            <span class="date-historique" style="font-size: 0.8em; color: var(--text-muted);"><?= formatDateHistorique($entry['date_heure']) ?></span>
                                        </div>
                                        <?php if ($index === 0): ?>
                                            <div style="margin-top: 2px;">
                                                <span class="badge-actuel" style="background-color: #28a745; color: white; padding: 1px 6px; border-radius: 10px; font-size: 0.7em; font-weight: bold; text-transform: uppercase;">ACTUEL</span>
                                                <?php if ($index < count($historique) - 1): ?>
                                                    <span class="duree-statut" style="font-size: 0.8em; color: var(--text-muted); margin-left: 8px;">Durée: <?= calculerDureeStatut($entry['date_heure'], $historique[$index + 1]['date_heure']) ?></span>
                                                <?php else: ?>
                                                    <span class="duree-statut" style="font-size: 0.8em; color: var(--text-muted); margin-left: 8px;">Durée: <?= calculerDureeStatut($entry['date_heure']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted, #666); font-style: italic; font-size: 0.9em;">Aucun historique disponible</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted, #666); font-style: italic; font-size: 0.9em;">Aucun historique de statut disponible</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($intervention['ip_vnc'])): 
            // Régénérer les tokens VNC pour s'assurer qu'ils sont à jour
            try {
                require_once __DIR__ . '/../../actions/generate_vnc_tokens.php';
            } catch (Exception $e) {
                // Silencieux sur l'erreur de génération pour ne pas casser l'affichage
                error_log("Erreur génération tokens VNC: " . $e->getMessage());
            }

            $vncHost = $_SERVER['SERVER_NAME']; // IP du serveur Docker
            
            // Calcul du port déterministe (Même algo que generate_vnc_tokens.php)
            $portHash = hexdec(substr(md5('inter_' . $intervention['id']), -4));
            $vncPort = 10000 + ($portHash % 20000);
            
            $vncPassword = $intervention['pass_vnc'] ?? '';
            
            // URL : Charge le viewer sur 8080, connecte le socket sur le port dédié
            $vncUrl = "http://{$vncHost}:8080/vnc_lite.html?host={$vncHost}&port={$vncPort}&password={$vncPassword}&autoconnect=true&scale=true";
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px;">
            <!-- Colonne Gauche : Informations -->
            <div class="detail-section" style="margin-bottom: 0; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;">Informations de l'intervention</h3>
                    <div style="text-align: center; background: white; padding: 5px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode("http://" . $_SERVER['HTTP_HOST'] . "/pwa/?intervention_id=" . $intervention['id']) ?>" 
                             alt="QR Code PWA" 
                             style="width: 120px; height: 120px; display: block;" 
                             title="Scanner pour ouvrir dans l'application mobile">
                        <div style="font-size: 11px; color: #666; margin-top: 4px; font-weight: 500;">App Mobile</div>
                    </div>
                </div>
                <div class="text-content" style="flex: 1;">
                    <?= nl2br(htmlspecialchars($intervention['info'] ?? '')) ?>
                </div>
            </div>

            <!-- Colonne Droite : VNC -->
            <div class="detail-section" style="margin-bottom: 0; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        🖥️ Accès VNC
                        <span id="ping-indicator" title="Statut de connexion PC (Ping)" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: #999; animation: blink-grey 2s infinite;"></span>
                    </h3>
                </div>
                <!-- Styles pour le ping indicator -->
                <style>
                    @keyframes blink-grey {
                        0% { opacity: 0.4; }
                        50% { opacity: 1; }
                        100% { opacity: 0.4; }
                    }
                    .ping-online {
                        background-color: #2ecc71 !important; /* Green */
                        box-shadow: 0 0 8px rgba(46, 204, 113, 0.6);
                        animation: none !important;
                    }
                    .ping-offline {
                        background-color: #e74c3c !important; /* Red */
                        animation: none !important;
                    }
                </style>
                <div style="position: relative; width: 100%; height: 600px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); background: #000; display: flex;">
                    <iframe 
                        src="<?= $vncUrl ?>" 
                        style="width: 100%; height: 100%; border: none;"
                        allowfullscreen
                    ></iframe>
                </div>

                <script>
                // Script de gestion du ping
                (function() {
                    const pingIndicator = document.getElementById('ping-indicator');
                    // On recupere l'IP depuis PHP, on s'assure qu'elle est dispo
                    const ipVnc = '<?= htmlspecialchars($intervention['ip_vnc'] ?? '') ?>';
                    
                    if (ipVnc) {
                        function checkPing() {
                            fetch(`api/ping.php?ip=${encodeURIComponent(ipVnc)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        pingIndicator.classList.remove('ping-offline');
                                        pingIndicator.classList.add('ping-online');
                                        pingIndicator.title = "PC en ligne (Ping OK)";
                                    } else {
                                        pingIndicator.classList.remove('ping-online');
                                        pingIndicator.classList.add('ping-offline');
                                        pingIndicator.title = "PC injoignable (Ping KO)";
                                    }
                                })
                                .catch(err => {
                                    console.error('Ping check failed', err);
                                    pingIndicator.classList.remove('ping-online');
                                    pingIndicator.classList.add('ping-offline');
                                });
                        }

                        // Premier check immédiat
                        checkPing();
                        
                        // Puis toutes les 15 secondes
                        setInterval(checkPing, 15000);
                    }
                })();
                </script>
                <div style="margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px; align-items: center;">
                    <button onclick="disableVNC('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-danger" style="font-size: 0.9em; padding: 6px 12px;">
                        🚫 Désactiver VNC
                    </button>
                    <a href="index.php?page=vnc_fullscreen&id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.9em; padding: 6px 12px;">
                        Ouvrir en plein écran ↗
                    </a>
                </div>
            </div>
        </div>
        <div style="clear: both;"></div>
        <?php else: ?>
            <!-- Section pour activer le VNC quand il n'est pas configuré -->
            <div class="detail-section">
                <h3>🖥️ Configuration VNC</h3>
                <p style="color: var(--text-muted); margin-bottom: 15px;">Le VNC n'est pas configuré pour cette intervention.</p>
                <button onclick="showEnableVNCModal('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-success">
                    ✅ Activer le VNC
                </button>
            </div>
            
            <div class="detail-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;">Informations de l'intervention</h3>
                    <div style="text-align: center; background: white; padding: 5px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode("http://" . $_SERVER['HTTP_HOST'] . "/pwa/?intervention_id=" . $intervention['id']) ?>" 
                             alt="QR Code PWA" 
                             style="width: 120px; height: 120px; display: block;" 
                             title="Scanner pour ouvrir dans l'application mobile">
                        <div style="font-size: 11px; color: #666; margin-top: 4px; font-weight: 500;">App Mobile</div>
                    </div>
                </div>
                <div class="text-content">
                    <?= nl2br(htmlspecialchars($intervention['info'] ?? '')) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($intervention['nettoyage'])): ?>
        <div class="detail-section">
            <h3>Nettoyage</h3>
            <?php
            $logiciels = parseNettoyageData($intervention['nettoyage']);
            if (!empty($logiciels)): ?>
                <div class="nettoyage-list">
                    <?php foreach ($logiciels as $logiciel): ?>
                        <div class="logiciel-item <?= $logiciel['passe'] ? 'passe' : 'non-passe' ?>">
                            <span class="logiciel-status <?= $logiciel['passe'] ? 'passe' : 'non-passe' ?>">
                                <?= $logiciel['passe'] ? 'Passé' : 'Non passé' ?>
                            </span>
                            <span class="logiciel-nom"><?= htmlspecialchars($logiciel['nom']) ?></span>
                            <?php if (!empty($logiciel['info'])): ?>
                                <span class="logiciel-info"><?= htmlspecialchars($logiciel['info']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-content">
                    <?= nl2br(htmlspecialchars($intervention['nettoyage'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($intervention['info_log'])): ?>
        <div class="detail-section">
            <h3>📝 Log d'informations</h3>
            <?= formatLogContent($intervention['info_log']) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($intervention['note_user'])): ?>
        <div class="detail-section">
            <h3>Notes utilisateur</h3>
            <div class="text-content">
                <?= nl2br(htmlspecialchars($intervention['note_user'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section Photos -->
        <div class="detail-section">
            <h3>Photos</h3>
            <div id="photos-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <!-- Les photos seront chargées ici via JavaScript -->
            </div>
            <div id="no-photos" style="display: none; text-align: center; padding: 40px; color: var(--text-muted, #666); font-style: italic;">
                Aucune photo disponible pour cette intervention
            </div>
        </div>

        <div class="actions-bar">
            <?php if ($intervention['en_cours'] == 1): ?>
                <a href="index.php?page=interventions_edit&id=<?= htmlspecialchars($intervention['id']) ?>" class="btn btn-primary">
                    <span>✏️</span>
                    Modifier l'intervention
                </a>
                <a href="pwa/?intervention_id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" class="btn btn-success">
                    <span>📷</span>
                    Ajouter des photos (Mobile)
                </a>
            <?php else: ?>
                <span style="color: var(--text-muted); font-style: italic; padding: 10px 20px;">
                    Intervention clôturée - Modification non autorisée
                </span>
                <a href="pwa/?intervention_id=<?= htmlspecialchars($intervention['id']) ?>" target="_blank" class="btn btn-success">
                    <span>📷</span>
                    Voir photos (Mobile)
                </a>
            <?php endif; ?>
            <a href="actions/interventions_delete.php?id=<?= htmlspecialchars($intervention['id']) ?>" 
               onclick="return confirm('❗ Êtes-vous sûr de vouloir supprimer cette intervention ?\n\nCette action est irréversible et supprimera également :\n- Toutes les photos associées\n- L\'historique des notes');" 
               class="btn btn-danger" 
               style="background-color: #dc3545; color: white;">
                <span>🗑️</span>
                Supprimer
            </a>
            <button onclick="printIntervention('<?= htmlspecialchars($intervention['id']) ?>')" class="btn btn-secondary" style="background-color: #6c757d; color: white;">
                <span>🖨️</span>
                Imprimer
            </button>
            <a href="index.php?page=interventions_list" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste
            </a>
        </div>

        <script>
        function printIntervention(id) {
            window.open('print_intervention.php?id=' + id, '_blank', 'width=1000,height=800');
        }
        </script>

    <?php else: ?>
        <div class="actions-bar">
            <a href="index.php?page=interventions_list" class="btn btn-secondary">
                <span>←</span>
                Retour à la liste des interventions
            </a>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal pour activer le VNC -->
<div id="enableVNCModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #3498db; margin-bottom: 20px;">✅ Activer le VNC</h2>
        <form id="enableVNCForm" onsubmit="return submitEnableVNC(event);">
            <input type="hidden" id="vnc_intervention_id" name="intervention_id">
            
            <div style="margin-bottom: 20px;">
                <label for="vnc_ip" style="display: block; margin-bottom: 5px; font-weight: 600;">Adresse IP VNC *</label>
                <input type="text" id="vnc_ip" name="ip_vnc" required 
                    placeholder="Exemple: 192.168.1.100"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--input-bg); color: var(--text-color); font-size: 1em;">
            </div>
            
            <div style="margin-bottom: 25px;">
                <label for="vnc_pass" style="display: block; margin-bottom: 5px; font-weight: 600;">Mot de passe VNC (optionnel)</label>
                <input type="text" id="vnc_pass" name="pass_vnc" 
                    placeholder="Laisser vide si pas de mot de passe"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--input-bg); color: var(--text-color); font-size: 1em;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEnableVNCModal()" class="btn btn-secondary" style="padding: 10px 20px;">
                    Annuler
                </button>
                <button type="submit" class="btn btn-success" style="padding: 10px 20px;">
                    ✅ Activer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Gestion des photos pour la page de visualisation
let currentInterventionId = '<?= htmlspecialchars($intervention_id) ?>';

// Charger les photos existantes
function loadPhotos() {
    if (!currentInterventionId) return;
    
    fetch(`api/photos.php?intervention_id=${encodeURIComponent(currentInterventionId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPhotos(data.data);
            } else {
                console.error('Erreur lors du chargement des photos:', data.message);
                showNoPhotos();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNoPhotos();
        });
}

// Afficher les photos dans la galerie
function displayPhotos(photos) {
    const gallery = document.getElementById('photos-gallery');
    const noPhotos = document.getElementById('no-photos');
    
    if (!photos || photos.length === 0) {
        showNoPhotos();
        return;
    }
    
    gallery.innerHTML = '';
    noPhotos.style.display = 'none';
    
    photos.forEach(photo => {
        const photoDiv = document.createElement('div');
        photoDiv.style.cssText = `
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background-color: var(--bg-color, white);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        `;
        
        // Effet hover
        photoDiv.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.2)';
        });
        
        photoDiv.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });
        
        photoDiv.innerHTML = `
            <div style="position: relative;">
                <img src="${photo.thumbnail_url}" alt="${photo.original_filename}"
                     style="width: 100%; height: 150px; object-fit: cover;"
                     onclick="openPhotoModal('${photo.url}', '${photo.original_filename}', '${photo.description || ''}', '${photo.uploaded_at}')">
                <button onclick="deletePhoto('${photo.id}', event)" 
                        style="position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s;"
                        onmouseover="this.style.transform='scale(1.1)'"
                        onmouseout="this.style.transform='scale(1)'"
                        title="Supprimer cette photo">
                    🗑️
                </button>
            </div>
            <div style="padding: 12px;">
                <p style="margin: 0; font-size: 13px; font-weight: 500; color: var(--text-color, #333); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${photo.original_filename}">
                    ${photo.original_filename}
                </p>
                ${photo.description ? `<p style="margin: 6px 0 0 0; font-size: 12px; color: var(--text-muted, #666); line-height: 1.3;">${photo.description}</p>` : ''}
                <p style="margin: 6px 0 0 0; font-size: 11px; color: var(--text-muted, #888);">
                    ${formatDate(photo.uploaded_at)}
                </p>
            </div>
        `;
        
        gallery.appendChild(photoDiv);
    });
}

// Supprimer une photo
function deletePhoto(photoId, event) {
    if (event) event.stopPropagation();
    
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette photo ?')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳';
    
    fetch(`api/photos.php?photo_id=${photoId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger les photos
            loadPhotos();
        } else {
            alert('Erreur: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression');
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}

// Afficher le message "aucune photo"
function showNoPhotos() {
    const gallery = document.getElementById('photos-gallery');
    const noPhotos = document.getElementById('no-photos');
    
    gallery.innerHTML = '';
    noPhotos.style.display = 'block';
}

// Formater la date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Modal pour afficher les photos en grand
function openPhotoModal(imageUrl, filename, description, uploadedAt) {
    // Créer le modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.9); z-index: 10000;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; animation: fadeIn 0.3s ease;
    `;
    
    // Ajouter l'animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: scale(0.8) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    modal.innerHTML = `
        <div style="max-width: 90%; max-height: 90%; position: relative; animation: slideIn 0.3s ease;" onclick="event.stopPropagation()">
            <img src="${imageUrl}" alt="${filename}"
                 style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
            <div style="position: absolute; bottom: -80px; left: 0; right: 0; text-align: center; color: white; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 20px 0 10px 0; border-radius: 0 0 8px 8px;">
                <p style="margin: 0; font-size: 16px; font-weight: 500;">${filename}</p>
                ${description ? `<p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; line-height: 1.4;">${description}</p>` : ''}
                <p style="margin: 8px 0 0 0; font-size: 12px; opacity: 0.7;">
                    Ajoutée le ${formatDate(uploadedAt)}
                </p>
            </div>
            <button onclick="this.parentElement.parentElement.remove(); document.head.removeChild(document.head.lastElementChild);"
                    style="position: absolute; top: -15px; right: -15px; background-color: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 18px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.3); transition: background-color 0.2s ease;"
                    onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 1)'"
                    onmouseout="this.style.backgroundColor='rgba(220, 53, 69, 0.9)'">×</button>
        </div>
    `;
    
    // Fermer en cliquant sur le fond
    modal.addEventListener('click', function() {
        modal.remove();
        document.head.removeChild(style);
    });
    
    // Fermer avec la touche Escape
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            modal.remove();
            document.head.removeChild(style);
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
    
    document.body.appendChild(modal);
}

// Fonction pour désactiver le VNC
function disableVNC(interventionId) {
    if (!confirm('Êtes-vous sûr de vouloir désactiver le VNC pour cette intervention ?\n\nCette action supprimera les informations de connexion VNC (ip_vnc et pass_vnc).')) {
        return;
    }
    
    // Afficher un loader ou désactiver le bouton
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Désactivation...';
    
    // Envoyer la requête à l'API
    fetch('api/vnc_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=disable_vnc&intervention_id=${encodeURIComponent(interventionId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ VNC désactivé avec succès');
            // Recharger la page pour mettre à jour l'affichage
            window.location.reload();
        } else {
            alert('❌ Erreur: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur lors de la désactivation du VNC');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

// === Fonctions pour activer le VNC ===
function showEnableVNCModal(interventionId) {
    document.getElementById('vnc_intervention_id').value = interventionId;
    document.getElementById('enableVNCModal').style.display = 'flex';
    // Focus sur le champ IP
    setTimeout(() => document.getElementById('vnc_ip').focus(), 100);
}

function closeEnableVNCModal() {
    document.getElementById('enableVNCModal').style.display = 'none';
    document.getElementById('enableVNCForm').reset();
}

function submitEnableVNC(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'enable_vnc');
    
    // Désactiver le bouton de soumission
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Activation...';
    
    fetch('api/vnc_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ VNC activé avec succès');
            closeEnableVNCModal();
            // Recharger la page pour afficher le VNC
            window.location.reload();
        } else {
            alert('❌ Erreur: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('❌ Erreur lors de l\'activation du VNC');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
    
    return false;
}

// Fermer le modal en cliquant en dehors
document.getElementById('enableVNCModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEnableVNCModal();
    }
});

// Charger les photos au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    if (currentInterventionId) {
        loadPhotos();
    }
    
    // Auto-print support
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_print') === '1') {
        // Petit délai pour laisser charger la page
        setTimeout(() => {
            printIntervention(currentInterventionId);
        }, 500);
    }
});
</script>