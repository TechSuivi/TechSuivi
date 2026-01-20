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



<div class="resume-journalier">

<div class="resume-journalier">

<div class="no-print">
    <div class="flex-between-center mb-20">
        <h1 class="m-0">Résumé Journalier</h1>
        <a href="index.php?page=dashboard_caisse" class="btn btn-secondary">
            ← Retour au tableau de bord
        </a>
    </div>
    
    <div class="card p-20 mb-20">
        <form method="GET" class="flex align-center gap-15 flex-wrap">
            <input type="hidden" name="page" value="resume_journalier">
            
            <div class="flex align-center gap-10">
                <label for="date" class="font-bold">Date :</label>
                <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_selectionnee) ?>"
                       class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary">
                Afficher
            </button>
            
            <?php
            // Calculer la date précédente
            $date_precedente = date('Y-m-d', strtotime($date_selectionnee . ' -1 day'));
            ?>
            <a href="index.php?page=resume_journalier&date=<?= $date_precedente ?>"
               class="btn btn-warning"
               title="Résumé du <?= date('d/m/Y', strtotime($date_precedente)) ?>">
                ← Jour précédent
            </a>
            
            <div class="flex gap-10">
                <button type="button" onclick="downloadPDF()" class="btn btn-danger">
                    📥 PDF
                </button>
                <button type="button" onclick="sendPDFByEmail()" id="btn-email-pdf" class="btn btn-info">
                    ✉️ Envoyer par Mail
                </button>
                <button type="button" onclick="window.print()" class="btn btn-success">
                    🖨️ Imprimer
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error mb-15">
        <?= $errorMessage ?>
    </div>
<?php else: ?>

<div id="pdf-content" class="p-20">
<div class="text-center mb-30 pb-10 border-b-2 border-accent">
    <h1 class="m-0">Résumé Journalier - <?= date('d/m/Y', strtotime($date_selectionnee)) ?></h1>
</div>

<!-- Total final -->
<div class="resume-journalier-total-final">
    Total Final de la Journée : <?= number_format($resume['totaux']['total_final'], 2) ?> €
</div>

<!-- Détail de la feuille de caisse -->
<div class="resume-journalier-section">
    <h3>📊 Feuille de Caisse</h3>
    <?php if ($resume['feuille_caisse']): ?>
        <div class="resume-journalier-feuille-details">
            <div><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($resume['feuille_caisse']['date_comptage'])) ?></div>
            <div><strong>Total :</strong> <span class="montant-positif"><?= number_format($resume['feuille_caisse']['total_caisse'], 2) ?> €</span></div>
        </div>
        
        <!-- Détail des pièces, billets et totaux -->
        <div class="grid-3 gap-15 my-20">
            <!-- Détail des pièces -->
            <div class="resume-journalier-detail-table">
                <h4>🪙 Détail des Pièces</h4>
                <table class="m-0 resume-journalier-table">
                    <thead>
                        <tr>
                            <th class="text-xs">Valeur</th>
                            <th class="text-xs text-center">Qté</th>
                            <th class="text-xs text-right">Total</th>
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
                            <td class="p-6 text-xs font-bold">TOTAL</td>
                            <td class="p-6 text-center text-xs">-</td>
                            <td class="p-6 text-right font-bold text-xs">
                                <?= number_format($resume['feuille_caisse']['total_pieces'], 2) ?> €
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Détail des billets -->
            <div class="resume-journalier-detail-table">
                <h4>💵 Détail des Billets</h4>
                <table class="m-0 resume-journalier-table">
                    <thead>
                        <tr>
                            <th class="text-xs">Valeur</th>
                            <th class="text-xs text-center">Qté</th>
                            <th class="text-xs text-right">Total</th>
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
                            <td class="p-6 text-xs font-bold">TOTAL</td>
                            <td class="p-6 text-center text-xs">-</td>
                            <td class="p-6 text-right font-bold text-xs">
                                <?= number_format($resume['feuille_caisse']['total_billets'], 2) ?> €
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totaux et récapitulatif -->
            <div class="resume-journalier-detail-table">
                <h4>📊 Récapitulatif</h4>
                <table class="m-0 resume-journalier-table">
                    <tbody>
                        <tr>
                            <td class="p-6 text-xs font-bold">🪙 Pièces</td>
                            <td class="p-6 text-right text-xs font-bold text-info">
                                <?= number_format($resume['feuille_caisse']['total_pieces'], 2) ?> €
                            </td>
                        </tr>
                        <tr>
                            <td class="p-6 text-xs font-bold">💵 Billets</td>
                            <td class="p-6 text-right text-xs font-bold text-success">
                                <?= number_format($resume['feuille_caisse']['total_billets'], 2) ?> €
                            </td>
                        </tr>
                        <tr class="bg-secondary-light">
                            <td class="p-6 text-xs font-bold">💰 Espèces (Pièces + Billets)</td>
                            <td class="p-6 text-right text-xs font-bold text-primary">
                                <?= number_format($resume['feuille_caisse']['total_especes'], 2) ?> €
                            </td>
                        </tr>
                        <tr>
                            <td class="p-6 text-xs font-bold">📄 Chèques</td>
                            <td class="p-6 text-right text-xs font-bold text-warning">
                                <?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €
                            </td>
                        </tr>
                        <?php if (!empty($resume['feuille_caisse']['nb_cheques'])): ?>
                        <tr>
                            <td class="p-6 text-xs text-muted pl-20">Nombre de chèques</td>
                            <td class="p-6 text-right text-xs text-muted">
                                <?= $resume['feuille_caisse']['nb_cheques'] ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row border-t-2 border-light">
                            <td class="p-8 text-xs font-bold">🏦 TOTAL CAISSE</td>
                            <td class="p-8 text-right text-xs font-bold text-accent">
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
            <div class="mt-20">
                <h4 class="m-0 mb-10 p-8 bg-secondary border rounded-4 text-sm font-bold">
                    📄 Détail des Chèques (<?= $resume['feuille_caisse']['nb_cheques'] ?? count($cheques_details) ?> chèque<?= ($resume['feuille_caisse']['nb_cheques'] ?? count($cheques_details)) > 1 ? 's' : '' ?>)
                </h4>
                
                <?php if (!empty($cheques_details)): ?>
                    <table class="w-full m-0 border-collapse">
                        <thead>
                            <tr>
                                <th class="p-8 text-left bg-secondary border text-xs">Montant</th>
                                <th class="p-8 text-left bg-secondary border text-xs">Émetteur</th>
                                <th class="p-8 text-left bg-secondary border text-xs">N° Chèque</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques_details as $cheque): ?>
                                <tr>
                                    <td class="p-6 border font-bold text-warning text-xs">
                                        <?= number_format($cheque['montant'], 2) ?> €
                                    </td>
                                    <td class="p-6 border text-xs">
                                        <?= htmlspecialchars($cheque['emetteur'] ?: 'Non renseigné') ?>
                                    </td>
                                    <td class="p-6 border text-xs">
                                        <?= htmlspecialchars($cheque['numero'] ?: 'Non renseigné') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td class="p-8 border font-bold bg-secondary text-xs">
                                    TOTAL
                                </td>
                                <td class="p-8 border bg-secondary text-xs">
                                    <?= count($cheques_details) ?> chèque<?= count($cheques_details) > 1 ? 's' : '' ?>
                                </td>
                                <td class="p-8 border font-bold text-warning bg-secondary text-xs">
                                    <?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="p-10 bg-secondary rounded-4 text-xs text-muted text-center">
                        Montant total des chèques : <strong class="text-warning"><?= number_format($resume['feuille_caisse']['montant_cheques'], 2) ?> €</strong><br>
                        <em>Détail des chèques non disponible</em>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($resume['feuille_caisse']['commentaire'])): ?>
            <div class="mt-15">
                <strong class="text-sm">Commentaire :</strong>
                <div class="bg-secondary p-10 rounded-4 mt-5 text-sm">
                    <?= nl2br(htmlspecialchars($resume['feuille_caisse']['commentaire'])) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="italic text-muted">Aucune feuille de caisse enregistrée pour cette date.</p>
    <?php endif; ?>
</div>

<!-- Sessions cyber -->
<div class="resume-journalier-section">
    <h3>💻 Sessions Cyber (<?= count($resume['sessions_cyber']) ?>) - Total : <?= number_format($resume['totaux']['recettes_cyber'], 2) ?> €</h3>
    <?php if (!empty($resume['sessions_cyber'])): ?>
        <table class="resume-journalier-table">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Client</th>
                    <th>Durée</th>
                    <th>Impressions</th>
                    <th class="text-right">Tarif</th>
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
                        <td class="text-right montant-positif">
                            <?= number_format($session['tarif'] ?? 0, 2) ?> €
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="italic text-muted">Aucune session cyber enregistrée.</p>
    <?php endif; ?>
</div>

<!-- Transactions -->
<div class="resume-journalier-section">
    <h3>💰 Transactions (<?= count($resume['transactions']) ?>) - Solde : <?= number_format($resume['totaux']['solde_journee'], 2) ?> €</h3>
    <?php if (!empty($resume['transactions'])): ?>
        <table class="resume-journalier-table">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Description</th>
                    <th>Moyen</th>
                    <th>Acompte</th>
                    <th>Solde</th>
                    <th class="text-right">Montant</th>
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
                                    <br><small class="text-xs text-muted">
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
                        <td class="text-right montant-positif">
                            <?= number_format($transaction['montant'], 2) ?> €
                        </td>
                        <td><?= htmlspecialchars($transaction['num_facture'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="italic text-muted">Aucune transaction enregistrée.</p>
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