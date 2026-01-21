<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';
$date_selectionnee = $_GET['date'] ?? date('Y-m-d');

// Validation de la date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_selectionnee)) {
    $date_selectionnee = date('Y-m-d');
}

$resume = [
    'feuille_caisse' => null,
    'transactions' => [],
    'sessions_cyber' => [],
    'totaux' => [
        'feuille_matin' => 0,
        'recettes_cyber' => 0,
        'entrees' => 0,
        'sorties' => 0,
        'solde_journee' => 0,
        'total_final' => 0
    ]
];

if (isset($pdo)) {
    try {
        // Récupération de la feuille de caisse du matin
        $stmt = $pdo->prepare("SELECT * FROM FC_feuille_caisse WHERE DATE(date_comptage) = ? ORDER BY date_comptage ASC LIMIT 1");
        $stmt->execute([$date_selectionnee]);
        $resume['feuille_caisse'] = $stmt->fetch();
        
        if ($resume['feuille_caisse']) {
            $resume['totaux']['feuille_matin'] = $resume['feuille_caisse']['total_caisse'];
        }
        
        // Récupération des transactions de la journée
        $stmt = $pdo->prepare("
            SELECT t.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
            WHERE DATE(t.date_transaction) = ?
            ORDER BY t.date_transaction ASC
        ");
        $stmt->execute([$date_selectionnee]);
        $resume['transactions'] = $stmt->fetchAll();
        
        // Calcul des totaux des transactions
        foreach ($resume['transactions'] as $transaction) {
            $resume['totaux']['entrees'] += $transaction['montant'];
        }
        
        // Récupération des sessions cyber de la journée
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber WHERE DATE(date_cyber) = ? ORDER BY date_cyber ASC");
        $stmt->execute([$date_selectionnee]);
        $resume['sessions_cyber'] = $stmt->fetchAll();
        
        // Calcul des recettes cyber
        foreach ($resume['sessions_cyber'] as $session) {
            $resume['totaux']['recettes_cyber'] += ($session['tarif'] ?? 0);
        }
        
        // Calculs finaux
        $resume['totaux']['solde_journee'] = $resume['totaux']['entrees'] - $resume['totaux']['sorties'];
        $resume['totaux']['total_final'] = $resume['totaux']['feuille_matin'] + $resume['totaux']['recettes_cyber'] + $resume['totaux']['solde_journee'];
        
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Fonction pour afficher le détail des pièces/billets
function afficherDetail($nombre, $valeur, $libelle) {
    if ($nombre > 0) {
        $total = $nombre * $valeur;
        return "<tr>
            <td style='padding: 4px; border-bottom: 1px solid #ddd; font-size: 10px;'>$libelle</td>
            <td style='padding: 4px; border-bottom: 1px solid #ddd; text-align: center; font-size: 10px;'>$nombre</td>
            <td style='padding: 4px; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 10px;'>" . number_format($total, 2) . " €</td>
        </tr>";
    }
    return '';
}
?>

<style>
    /* Variables CSS pour le thème */
    .resume-journalier {
        --bg-color: #ffffff;
        --text-color: #000000;
        --card-bg: #f9f9f9;
        --secondary-bg: #f0f0f0;
        --border-color: #ddd;
        --input-bg: #ffffff;
        --accent-color: #333;
    }
    
    /* Mode sombre */
    body.dark .resume-journalier {
        --bg-color: #121212;
        --text-color: #dddddd;
        --card-bg: #2b2b2b;
        --secondary-bg: #333333;
        --border-color: #444444;
        --input-bg: #2b2b2b;
        --accent-color: #2A4F9C;
    }
    
    .resume-journalier {
        font-family: Arial, sans-serif;
        font-size: 12px;
        background-color: var(--bg-color);
        color: var(--text-color);
    }
    
    .resume-journalier .form-container {
        background-color: var(--card-bg);
        color: var(--text-color);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }
    
    .resume-journalier .form-container input,
    .resume-journalier .form-container button,
    .resume-journalier .form-container a {
        background-color: var(--input-bg);
        color: var(--text-color);
        border: 1px solid var(--border-color);
    }
    
    .resume-journalier .section {
        background-color: var(--card-bg);
        color: var(--text-color);
        margin-bottom: 20px;
        break-inside: avoid;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .resume-journalier .section h3 {
        background-color: var(--secondary-bg);
        color: var(--text-color);
        padding: 8px;
        margin: -15px -15px 10px -15px;
        border-left: 4px solid var(--accent-color);
        border-radius: 8px 8px 0 0;
    }
    
    .resume-journalier table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        background-color: var(--card-bg);
    }
    
    .resume-journalier th,
    .resume-journalier td {
        padding: 6px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        font-size: 11px;
        background-color: var(--card-bg);
        color: var(--text-color);
    }
    
    .resume-journalier th {
        background-color: var(--secondary-bg);
        font-weight: bold;
    }
    
    .resume-journalier .total-row {
        background-color: var(--secondary-bg);
        font-weight: bold;
    }
    
    .resume-journalier .montant-positif { color: #4CAF50; font-weight: bold; }
    .resume-journalier .montant-negatif { color: #F44336; font-weight: bold; }
    
    .resume-journalier .total-final {
        background-color: var(--accent-color);
        color: white;
        padding: 15px;
        text-align: center;
        font-size: 16px;
        font-weight: bold;
        margin: 20px 0;
        border-radius: 8px;
    }
    
    .resume-journalier .feuille-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin: 15px 0;
    }
    
    .resume-journalier .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .resume-journalier .detail-table {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .resume-journalier .detail-table h4 {
        background-color: var(--secondary-bg);
        color: var(--text-color);
        margin: 0;
        padding: 8px;
        font-size: 12px;
        border-bottom: 1px solid var(--border-color);
    }
    
    @media print {
        .no-print, .sidebar, header, .menu, nav, button, .content header {
            display: none !important;
        }
        
        .sidebar, .menu, .menu-item, .submenu {
            display: none !important;
        }
        
        header, footer, .header, .footer {
            display: none !important;
        }
        
        .logo, .sidebar-footer, .theme-toggle {
            display: none !important;
        }
        
        .total-final {
            display: none !important;
        }
        
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            color: black !important;
            font-family: Arial, sans-serif !important;
            font-size: 10pt !important;
        }
        
        html {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .resume-journalier {
            margin: 0 !important;
            padding: 10px !important;
        }
        
        @page {
            margin: 0;
            size: A4;
        }
        
        /* Tentative de masquage des en-têtes/pieds de page du navigateur */
        @page :first {
            margin-top: 0;
        }
        
        @page :left {
            margin: 0;
        }
        
        @page :right {
            margin: 0;
        }
        
        .content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
        }
        
        .resume-journalier .section,
        .resume-journalier .form-container,
        .resume-journalier table,
        .resume-journalier th,
        .resume-journalier td,
        .resume-journalier div,
        .resume-journalier p,
        .resume-journalier span {
            background: white !important;
            color: black !important;
            border-color: black !important;
        }
        
        .resume-journalier .section h3 {
            background: #f0f0f0 !important;
            color: black !important;
            border-left: none !important;
            margin: 0 !important;
            border-radius: 8px 8px 0 0 !important;
        }
        
        .resume-journalier table {
            font-size: 8pt !important;
            border: 1px solid black !important;
        }
        
        .resume-journalier th,
        .resume-journalier td {
            padding: 2px !important;
            border: 1px solid black !important;
            font-size: 8pt !important;
        }
        
        .resume-journalier .section {
            margin-bottom: 10px !important;
            padding: 8px !important;
            border: 1px solid black !important;
        }
        
        .resume-journalier .total-final {
            background: #333 !important;
            color: white !important;
            margin: 10px 0 !important;
            padding: 10px !important;
        }
        
        .resume-journalier .section,
        .resume-journalier table,
        .resume-journalier .detail-table {
            page-break-inside: avoid;
        }
    }

    /* Styles spécifiques pour la génération PDF (identiques à l'impression) */
    .generating-pdf .section,
    .generating-pdf .form-container,
    .generating-pdf table,
    .generating-pdf th,
    .generating-pdf td,
    .generating-pdf div,
    .generating-pdf p,
    .generating-pdf span {
        background: white !important;
        color: black !important;
        border-color: black !important;
        box-sizing: border-box !important;
    }
    
    .generating-pdf * {
        box-sizing: border-box !important;
    }
    
    .generating-pdf .section h3 {
        background: #f0f0f0 !important;
        color: black !important;
        border-left: none !important;
        margin: 0 !important;
        border-radius: 8px 8px 0 0 !important;
    }
    
    .generating-pdf table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 9pt !important; /* Slightly larger for readability */
        margin: 0 !important;
    }
    
    .generating-pdf th,
    .generating-pdf td {
        padding: 4px !important; /* More breathing room */
        border: 1px solid #333 !important; /* Slightly softer black */
        font-size: 9pt !important;
    }
    
    .generating-pdf .section {
        margin-bottom: 10px !important;
        padding: 8px !important;
        border: 1px solid black !important;
        box-shadow: none !important;
        border-radius: 8px !important;
    }

    .generating-pdf .detail-table {
        border: 1px solid black !important;
        border-radius: 6px !important;
        overflow: hidden !important;
    }
    
    .generating-pdf .total-final {
        display: none !important;
    }

    .generating-pdf .montant-positif,
    .generating-pdf .montant-negatif {
        color: black !important; /* Force black for PDF readability if desired, or keep colors */
    }
</style>

<div class="resume-journalier">

<div class="no-print">
    <h1>Résumé Journalier</h1>
    
    <div class="form-container">
        <form method="GET" style="display: flex; align-items: center; gap: 15px;">
            <input type="hidden" name="page" value="resume_journalier">
            <label for="date">Date :</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_selectionnee) ?>"
                   style="padding: 8px; border-radius: 4px;">
            <button type="submit" style="padding: 8px 16px; background-color: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Afficher
            </button>
            <?php
            // Calculer la date précédente
            $date_precedente = date('Y-m-d', strtotime($date_selectionnee . ' -1 day'));
            ?>
            <a href="index.php?page=resume_journalier&date=<?= $date_precedente ?>"
               style="padding: 8px 16px; background-color: #FF9800; color: white; text-decoration: none; border-radius: 4px; cursor: pointer;"
               title="Résumé du <?= date('d/m/Y', strtotime($date_precedente)) ?>">
                ← Jour précédent
            </a>
            <button type="button" onclick="downloadPDF()" style="padding: 8px 16px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                📥 PDF
            </button>
            <button type="button" onclick="sendPDFByEmail()" id="btn-email-pdf" style="padding: 8px 16px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">
                ✉️ Envoyer par Mail
            </button>
            <button type="button" onclick="window.print()" style="padding: 8px 16px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                🖨️ Imprimer
            </button>
            <a href="index.php?page=dashboard_caisse" style="padding: 8px 16px; background-color: #666; color: white; text-decoration: none; border-radius: 4px;">
                ← Retour au tableau de bord
            </a>
        </form>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #ffe6e6;">
        <?= $errorMessage ?>
    </div>
<?php else: ?>

<div id="pdf-content" style="padding: 20px;">
<div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid var(--accent-color, #333); padding-bottom: 10px;">
    <h1>Résumé Journalier - <?= date('d/m/Y', strtotime($date_selectionnee)) ?></h1>
</div>

<!-- Total final -->
<div class="total-final">
    Total Final de la Journée : <?= number_format($resume['totaux']['total_final'], 2) ?> €
</div>

<!-- Détail de la feuille de caisse -->
<div class="section">
    <h3>📊 Feuille de Caisse</h3>
    <?php if ($resume['feuille_caisse']): ?>
        <div class="feuille-details">
            <div><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($resume['feuille_caisse']['date_comptage'])) ?></div>
            <div><strong>Total :</strong> <span class="montant-positif"><?= number_format($resume['feuille_caisse']['total_caisse'], 2) ?> €</span></div>
        </div>
        
        <!-- Détail des pièces, billets et totaux -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin: 20px 0;">
            <!-- Détail des pièces -->
            <div class="detail-table">
                <h4>🪙 Détail des Pièces</h4>
                <table style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="font-size: 10px;">Valeur</th>
                            <th style="font-size: 10px; text-align: center;">Qté</th>
                            <th style="font-size: 10px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_001'], 0.01, '0,01 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_002'], 0.02, '0,02 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_005'], 0.05, '0,05 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_010'], 0.10, '0,10 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_020'], 0.20, '0,20 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_050'], 0.50, '0,50 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_100'], 1.00, '1,00 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['pieces_200'], 2.00, '2,00 €') ?>
                        <tr class="total-row">
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">TOTAL</td>
                            <td style="padding: 6px; text-align: center; font-size: 10px;">-</td>
                            <td style="padding: 6px; text-align: right; font-weight: bold; font-size: 10px;">
                                <?= number_format($resume['feuille_caisse']['total_pieces'], 2) ?> €
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Détail des billets -->
            <div class="detail-table">
                <h4>💵 Détail des Billets</h4>
                <table style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="font-size: 10px;">Valeur</th>
                            <th style="font-size: 10px; text-align: center;">Qté</th>
                            <th style="font-size: 10px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= afficherDetail($resume['feuille_caisse']['billets_005'], 5, '5 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_010'], 10, '10 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_020'], 20, '20 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_050'], 50, '50 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_100'], 100, '100 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_200'], 200, '200 €') ?>
                        <?= afficherDetail($resume['feuille_caisse']['billets_500'], 500, '500 €') ?>
                        <tr class="total-row">
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">TOTAL</td>
                            <td style="padding: 6px; text-align: center; font-size: 10px;">-</td>
                            <td style="padding: 6px; text-align: right; font-weight: bold; font-size: 10px;">
                                <?= number_format($resume['feuille_caisse']['total_billets'], 2) ?> €
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totaux et récapitulatif -->
            <div class="detail-table">
                <h4>📊 Récapitulatif</h4>
                <table style="margin: 0;">
                    <tbody>
                        <tr>
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">🪙 Pièces</td>
                            <td style="padding: 6px; text-align: right; font-size: 10px; color: #17a2b8; font-weight: bold;">
                                <?= number_format($resume['feuille_caisse']['total_pieces'], 2) ?> €
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">💵 Billets</td>
                            <td style="padding: 6px; text-align: right; font-size: 10px; color: #28a745; font-weight: bold;">
                                <?= number_format($resume['feuille_caisse']['total_billets'], 2) ?> €
                            </td>
                        </tr>
                        <tr style="background-color: var(--secondary-bg, #f8f9fa);">
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">💰 Espèces (Pièces + Billets)</td>
                            <td style="padding: 6px; text-align: right; font-size: 10px; color: #007bff; font-weight: bold;">
                                <?= number_format($resume['feuille_caisse']['total_especes'], 2) ?> €
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 6px; font-size: 10px; font-weight: bold;">📄 Chèques</td>
                            <td style="padding: 6px; text-align: right; font-size: 10px; color: #ffc107; font-weight: bold;">
                                <?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €
                            </td>
                        </tr>
                        <?php if (!empty($resume['feuille_caisse']['nb_cheques'])): ?>
                        <tr>
                            <td style="padding: 6px; font-size: 9px; color: #666; padding-left: 20px;">Nombre de chèques</td>
                            <td style="padding: 6px; text-align: right; font-size: 9px; color: #666;">
                                <?= $resume['feuille_caisse']['nb_cheques'] ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row" style="border-top: 2px solid var(--border-color, #ddd);">
                            <td style="padding: 8px 6px; font-size: 11px; font-weight: bold;">🏦 TOTAL CAISSE</td>
                            <td style="padding: 8px 6px; text-align: right; font-size: 11px; font-weight: bold; color: #333;">
                                <?= number_format($resume['feuille_caisse']['total_caisse'], 2) ?> €
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Détail des chèques -->
        <?php if (!empty($resume['feuille_caisse']['montant_cheques']) && $resume['feuille_caisse']['montant_cheques'] > 0): ?>
            <?php
            $cheques_details = [];
            if (!empty($resume['feuille_caisse']['cheques_details'])) {
                $cheques_details = json_decode($resume['feuille_caisse']['cheques_details'], true) ?: [];
            }
            ?>
            <div style="margin-top: 20px;">
                <h4 style="margin: 0 0 10px 0; padding: 8px; background-color: var(--secondary-bg, #f8f9fa); border-radius: 6px; font-size: 12px;">
                    📄 Détail des Chèques (<?= $resume['feuille_caisse']['nb_cheques'] ?? count($cheques_details) ?> chèque<?= ($resume['feuille_caisse']['nb_cheques'] ?? count($cheques_details)) > 1 ? 's' : '' ?>)
                </h4>
                
                <?php if (!empty($cheques_details)): ?>
                    <table style="width: 100%; margin: 0; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 8px; text-align: left; background-color: var(--secondary-bg, #f8f9fa); border: 1px solid var(--border-color, #ddd); font-size: 10px;">Montant</th>
                                <th style="padding: 8px; text-align: left; background-color: var(--secondary-bg, #f8f9fa); border: 1px solid var(--border-color, #ddd); font-size: 10px;">Émetteur</th>
                                <th style="padding: 8px; text-align: left; background-color: var(--secondary-bg, #f8f9fa); border: 1px solid var(--border-color, #ddd); font-size: 10px;">N° Chèque</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques_details as $cheque): ?>
                                <tr>
                                    <td style="padding: 6px 8px; border: 1px solid var(--border-color, #ddd); font-weight: bold; color: #ffc107; font-size: 10px;">
                                        <?= number_format($cheque['montant'], 2) ?> €
                                    </td>
                                    <td style="padding: 6px 8px; border: 1px solid var(--border-color, #ddd); font-size: 10px;">
                                        <?= htmlspecialchars($cheque['emetteur'] ?: 'Non renseigné') ?>
                                    </td>
                                    <td style="padding: 6px 8px; border: 1px solid var(--border-color, #ddd); font-size: 10px;">
                                        <?= htmlspecialchars($cheque['numero'] ?: 'Non renseigné') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td style="padding: 8px; border: 1px solid var(--border-color, #ddd); font-weight: bold; background-color: var(--secondary-bg, #f8f9fa); font-size: 10px;">
                                    TOTAL
                                </td>
                                <td style="padding: 8px; border: 1px solid var(--border-color, #ddd); background-color: var(--secondary-bg, #f8f9fa); font-size: 10px;">
                                    <?= count($cheques_details) ?> chèque<?= count($cheques_details) > 1 ? 's' : '' ?>
                                </td>
                                <td style="padding: 8px; border: 1px solid var(--border-color, #ddd); font-weight: bold; color: #ffc107; background-color: var(--secondary-bg, #f8f9fa); font-size: 10px;">
                                    <?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 10px; background-color: var(--secondary-bg, #f8f9fa); border-radius: 4px; font-size: 11px; color: #666; text-align: center;">
                        Montant total des chèques : <strong style="color: #ffc107;"><?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €</strong><br>
                        <em>Détail des chèques non disponible</em>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($resume['feuille_caisse']['commentaire'])): ?>
            <div style="margin-top: 15px;">
                <strong>Commentaire :</strong>
                <div style="background-color: var(--secondary-bg, #f8f9fa); padding: 10px; border-radius: 4px; margin-top: 5px; font-size: 11px;">
                    <?= nl2br(htmlspecialchars($resume['feuille_caisse']['commentaire'])) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p style="font-style: italic;">Aucune feuille de caisse enregistrée pour cette date.</p>
    <?php endif; ?>
</div>

<!-- Sessions cyber -->
<div class="section">
    <h3>💻 Sessions Cyber (<?= count($resume['sessions_cyber']) ?>) - Total : <?= number_format($resume['totaux']['recettes_cyber'], 2) ?> €</h3>
    <?php if (!empty($resume['sessions_cyber'])): ?>
        <table>
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Client</th>
                    <th>Durée</th>
                    <th>Impressions</th>
                    <th style="text-align: right;">Tarif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resume['sessions_cyber'] as $session): ?>
                    <tr>
                        <td><?= date('H:i', strtotime($session['date_cyber'])) ?></td>
                        <td><?= htmlspecialchars($session['nom'] ?: 'Anonyme') ?></td>
                        <td>
                            <?php if ($session['ha'] && $session['hd']): ?>
                                <?= date('H:i', strtotime($session['ha'])) ?> - <?= date('H:i', strtotime($session['hd'])) ?>
                            <?php else: ?>
                                Impression
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($session['imp'] || $session['imp_c']): ?>
                                <?= ($session['imp'] ?? 0) ?> N&B, <?= ($session['imp_c'] ?? 0) ?> Coul.
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;" class="montant-positif">
                            <?= number_format($session['tarif'] ?? 0, 2) ?> €
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="font-style: italic;">Aucune session cyber enregistrée.</p>
    <?php endif; ?>
</div>

<!-- Transactions -->
<div class="section">
    <h3>💰 Transactions (<?= count($resume['transactions']) ?>) - Solde : <?= number_format($resume['totaux']['solde_journee'], 2) ?> €</h3>
    <?php if (!empty($resume['transactions'])): ?>
        <table>
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Description</th>
                    <th>Moyen</th>
                    <th>Acompte</th>
                    <th>Solde</th>
                    <th style="text-align: right;">Montant</th>
                    <th>N° Facture</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resume['transactions'] as $transaction): ?>
                    <tr>
                        <td><?= date('H:i', strtotime($transaction['date_transaction'])) ?></td>
                        <td>
                            <?php if (!empty($transaction['client_nom'])): ?>
                                <span title="Client lié">👤 <?= htmlspecialchars($transaction['client_nom'] . ' ' . ($transaction['client_prenom'] ?? '')) ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($transaction['nom']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction['type']) ?>
                            <?php if (stripos($transaction['type'], 'chèque') !== false || stripos($transaction['type'], 'cheque') !== false): ?>
                                <?php if (!empty($transaction['banque']) || !empty($transaction['num_cheque'])): ?>
                                    <br><small style="font-size: 9px; color: #666;">
                                        <?php if (!empty($transaction['banque'])): ?>
                                            <?= htmlspecialchars($transaction['banque']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($transaction['num_cheque'])): ?>
                                            <?php if (!empty($transaction['banque'])): ?> - <?php endif; ?>
                                            N°<?= htmlspecialchars($transaction['num_cheque']) ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $transaction['acompte'] ? number_format($transaction['acompte'], 2) . ' €' : '-' ?></td>
                        <td><?= $transaction['solde'] ? number_format($transaction['solde'], 2) . ' €' : '-' ?></td>
                        <td style="text-align: right;" class="montant-positif">
                            <?= number_format($transaction['montant'], 2) ?> €
                        </td>
                        <td><?= htmlspecialchars($transaction['num_facture'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="font-style: italic;">Aucune transaction enregistrée.</p>
    <?php endif; ?>
</div>

<?php endif; ?>
</div> <!-- Fin de pdf-content -->

</div>

<!-- Librairie html2pdf.js pour la génération de PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function downloadPDF() {
    // Sélectionner l'élément à convertir
    const element = document.getElementById('pdf-content');
    
    // Obtenir la date pour le nom du fichier
    const dateStr = document.getElementById('date').value;
    const filename = 'Resume_Caisse_' + dateStr + '.pdf';

    // Options pour html2pdf
    const opt = {
        margin:       10,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 3, useCORS: true, letterRendering: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // Générer le PDF
    // On ajoute une classe temporaire pour ajuster le style si nécessaire
    element.classList.add('generating-pdf');
    // Forcer la largeur pour simuler une page A4 avec marge de sécurité
    const originalWidth = element.style.width;
    element.style.width = '180mm'; // Réduit à 180mm pour éviter la coupure à droite
    
    html2pdf().set(opt).from(element).save().then(function() {
        // Nettoyage après génération
        element.classList.remove('generating-pdf');
        element.style.width = originalWidth;
    });
}

function sendPDFByEmail() {
    const btn = document.getElementById('btn-email-pdf');
    const originalText = btn.innerHTML;
    
    // Feedback visuel
    btn.innerHTML = '⏳ Envoi...';
    btn.disabled = true;

    // Sélectionner l'élément
    const element = document.getElementById('pdf-content');
    const dateStr = document.getElementById('date').value;
    
    // Options identiques au téléchargement
    const opt = {
        margin:       10,
        filename:     'Resume_Caisse_' + dateStr + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 3, useCORS: true, letterRendering: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // Style temp
    element.classList.add('generating-pdf');
    const originalWidth = element.style.width;
    element.style.width = '180mm';

    html2pdf().set(opt).from(element).outputPdf('blob').then(function(blob) {
        // Restaurer styles
        element.classList.remove('generating-pdf');
        element.style.width = originalWidth;

        // Préparer l'envoi
        const formData = new FormData();
        formData.append('pdf_file', blob, 'Resume_Caisse_' + dateStr + '.pdf');
        formData.append('date', dateStr);

        // Envoyer à l'API
        fetch('api/send_daily_pdf.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
            } else {
                alert('❌ Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('❌ Erreur technique lors de l\'envoi.');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
}
</script>