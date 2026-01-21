<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$message = '';
$error = '';

// Gestion des messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'deleted':
            $message = "Feuille de caisse supprimée avec succès.";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_id':
            $error = "ID de feuille manquant.";
            break;
        case 'not_found':
            $error = "Feuille de caisse introuvable.";
            break;
        case 'delete_failed':
            $error = "Erreur lors de la suppression de la feuille de caisse.";
            break;
    }
}

// Récupération des feuilles de caisse
$feuilles = [];
try {
    $stmt = $pdo->query("
        SELECT *,
               COALESCE(total_retrait_pieces, 0) as total_retrait_pieces,
               COALESCE(total_retrait_billets, 0) as total_retrait_billets,
               COALESCE(total_retrait_especes, 0) as total_retrait_especes
        FROM FC_feuille_caisse
        ORDER BY date_comptage DESC
    ");
    $feuilles = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des feuilles de caisse : " . $e->getMessage();
}

// Informations de base
$derniere_feuille = !empty($feuilles) ? $feuilles[0] : null;
$total_feuilles = count($feuilles);
?>

<h1>📊 Historique des Feuilles de Caisse</h1>

<?php if ($message): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Résumé simple -->
<?php if ($derniere_feuille): ?>
<div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4CAF50;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; color: #4CAF50;">📊 Dernière feuille de caisse</h3>
            <p style="margin: 5px 0 0 0; color: var(--text-color, #666);">
                <?= date('d/m/Y', strtotime($derniere_feuille['date_comptage'])) ?> -
                <?= $total_feuilles ?> feuille<?= $total_feuilles > 1 ? 's' : '' ?> au total
            </p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 24px; font-weight: bold; color: #4CAF50;">
                <?= number_format($derniere_feuille['total_especes'], 2) ?> € comptés
            </div>
            <div style="font-size: 14px; color: var(--text-color, #666);">
                (+ <?= number_format($derniere_feuille['montant_cheques'], 2) ?> € chèques)
                <?php if ($derniere_feuille['total_retrait_especes'] > 0): ?>
                    <br>🏦 <?= number_format($derniere_feuille['total_retrait_especes'], 2) ?> € retirés
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Actions -->
<div style="margin-bottom: 20px;">
    <a href="index.php?page=feuille_caisse_add" style="background-color: var(--accent-color); color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;">
        ➕ Nouvelle Feuille de Caisse
    </a>
    <button onclick="window.print()" class="no-print" style="background-color: #17a2b8; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
        🖨️ Imprimer la liste
    </button>
    <a href="index.php?page=dashboard_caisse" style="background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px;">
        ↩️ Retour au Dashboard
    </a>
</div>

<!-- Liste des feuilles -->
<?php if (!empty($feuilles)): ?>
    <div style="background-color: var(--card-bg); border-radius: 8px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: var(--accent-color); color: white;">
                <tr>
                    <th style="padding: 12px; text-align: left;">Date</th>
                    <th style="padding: 12px; text-align: right;">📊 Comptage<br><small>Pièces | Billets</small></th>
                    <th style="padding: 12px; text-align: right;">🏦 Retraits<br><small>Pièces | Billets</small></th>
                    <th style="padding: 12px; text-align: right;">💰 Espèces<br><small>Comptées</small></th>
                    <th style="padding: 12px; text-align: right;">📄 Chèques</th>
                    <th style="padding: 12px; text-align: right;">💼 Total Caisse</th>
                    <th style="padding: 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feuilles as $index => $feuille): ?>
                    <tr style="border-bottom: 1px solid #eee; <?= $index % 2 === 0 ? 'background-color: #f8f9fa;' : '' ?>">
                        <td style="padding: 15px;">
                            <strong><?= date('d/m/Y', strtotime($feuille['date_comptage'])) ?></strong>
                            <br>
                            <small style="color: #666;">
                                <?= date('d/m/Y H:i', strtotime($feuille['created_at'])) ?>
                            </small>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <div style="color: #2196F3; font-weight: bold;">
                                <?= number_format($feuille['total_pieces'], 2) ?> € | <?= number_format($feuille['total_billets'], 2) ?> €
                            </div>
                            <small style="color: #666;">= <?= number_format($feuille['total_especes'], 2) ?> €</small>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <div style="color: #FF9800; font-weight: bold;">
                                    <?= number_format($feuille['total_retrait_pieces'], 2) ?> € | <?= number_format($feuille['total_retrait_billets'], 2) ?> €
                                </div>
                                <small style="color: #666;">= <?= number_format($feuille['total_retrait_especes'], 2) ?> €</small>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: bold;">
                            <?= number_format($feuille['total_especes'], 2) ?> €
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <br><small style="color: #FF9800;">(-<?= number_format($feuille['total_retrait_especes'], 2) ?> €)</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <?= number_format($feuille['montant_cheques'], 2) ?> €
                            <?php if (isset($feuille['nb_cheques']) && $feuille['nb_cheques'] > 0): ?>
                                <br><small style="color: #666;">(<?= $feuille['nb_cheques'] ?> chèque<?= $feuille['nb_cheques'] > 1 ? 's' : '' ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: bold; font-size: 16px; color: var(--accent-color);">
                            <?= number_format($feuille['total_caisse'], 2) ?> €
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <br><small style="color: #28a745;">Net: <?= number_format($feuille['total_caisse'] - $feuille['total_retrait_especes'], 2) ?> €</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;" class="action-buttons">
                            <a href="index.php?page=feuille_caisse_view&id=<?= $feuille['id'] ?>"
                               style="background-color: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                👁️ Voir
                            </a>
                            <a href="index.php?page=feuille_caisse_view&id=<?= $feuille['id'] ?>"
                               onclick="setTimeout(() => window.print(), 500); return true;"
                               style="background-color: #28a745; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                🖨️ Imprimer
                            </a>
                            <?php if ($feuille['date_comptage'] === date('Y-m-d')): ?>
                                <a href="index.php?page=feuille_caisse_add&date=<?= $feuille['date_comptage'] ?>"
                                   style="background-color: #ffc107; color: #212529; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                    ✏️ Modifier
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div style="background-color: var(--card-bg); padding: 40px; border-radius: 8px; text-align: center;">
        <h3>📋 Aucune feuille de caisse</h3>
        <p>Vous n'avez pas encore créé de feuille de caisse.</p>
        <a href="index.php?page=feuille_caisse_add" style="background-color: var(--accent-color); color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px;">
            ➕ Créer ma première feuille
        </a>
    </div>
<?php endif; ?>

<style>
/* Amélioration de l'affichage en mode sombre */
body.dark table {
    background-color: var(--card-bg, #1a202c) !important;
    color: var(--text-color, #e2e8f0) !important;
}

body.dark thead {
    background-color: var(--accent-color) !important;
    color: white !important;
}

body.dark tbody tr {
    background-color: var(--card-bg, #1a202c) !important;
    color: var(--text-color, #e2e8f0) !important;
    border-bottom: 1px solid var(--border-color, #4a5568) !important;
}

body.dark tbody tr:nth-child(even) {
    background-color: var(--secondary-bg, #2d3748) !important;
}

body.dark tbody tr:hover {
    background-color: var(--hover-bg, #374151) !important;
}

body.dark td, body.dark th {
    color: var(--text-color, #e2e8f0) !important;
    border-color: var(--border-color, #4a5568) !important;
}

body.dark small {
    color: var(--muted-text, #a0aec0) !important;
}

/* Amélioration générale du tableau */
table {
    border-collapse: collapse;
    width: 100%;
}

tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: var(--hover-bg, #f5f5f5);
}

td, th {
    border-bottom: 1px solid var(--border-color, #eee);
}

/* Styles pour les boutons d'action */
.action-buttons a {
    display: inline-block;
    margin: 2px;
    transition: opacity 0.2s ease;
}

.action-buttons a:hover {
    opacity: 0.8;
}

/* Styles d'impression */
@media print {
    /* Masquer les éléments non essentiels */
    .no-print, .sidebar, header, .menu, nav, button {
        display: none !important;
    }
    
    /* Réinitialiser le body pour l'impression */
    body {
        background: white !important;
        color: black !important;
        font-family: Arial, sans-serif !important;
        font-size: 10pt !important;
        margin: 0 !important;
        padding: 10px !important;
        line-height: 1.3 !important;
    }
    
    /* Masquer le contenu principal et afficher seulement la liste */
    .content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    /* Titre d'impression */
    h1 {
        text-align: center;
        margin: 0 0 20px 0;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        font-size: 14pt !important;
    }
    
    /* Réinitialiser tous les éléments */
    * {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
    }
    
    /* Styles pour les tableaux */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 9pt !important;
    }
    
    table, th, td {
        border: 1px solid black !important;
        background: white !important;
        color: black !important;
    }
    
    th {
        background: #f0f0f0 !important;
        font-weight: bold !important;
        padding: 8px 4px !important;
    }
    
    td {
        padding: 6px 4px !important;
        vertical-align: top !important;
    }
    
    /* Masquer la colonne actions */
    th:last-child, td:last-child {
        display: none !important;
    }
    
    /* Conteneurs */
    div[style*="background-color"] {
        background: white !important;
        border: 1px solid #000 !important;
        margin-bottom: 10px !important;
        padding: 10px !important;
    }
    
    /* Éviter les coupures de page */
    table, tr {
        page-break-inside: avoid;
    }
    
    /* Réduire les espacements */
    div, p {
        margin: 5px 0 !important;
        padding: 5px !important;
    }
    
    /* Résumé en haut */
    .print-summary {
        border: 2px solid #000 !important;
        padding: 15px !important;
        margin-bottom: 20px !important;
        text-align: center !important;
    }
}
</style>