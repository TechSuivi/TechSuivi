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
    <div class="alert alert-success">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Résumé simple -->
<?php if ($derniere_feuille): ?>
<div class="card border-left-green mb-20">
    <div class="flex-between-center">
        <div>
            <h3 class="card-title text-green">📊 Dernière feuille de caisse</h3>
            <p class="text-muted mt-5">
                <?= date('d/m/Y', strtotime($derniere_feuille['date_comptage'])) ?> -
                <?= $total_feuilles ?> feuille<?= $total_feuilles > 1 ? 's' : '' ?> au total
            </p>
        </div>
        <div class="text-right">
            <div class="text-xl font-bold text-green">
                <?= number_format($derniere_feuille['total_especes'], 2) ?> € comptés
            </div>
            <div class="text-sm text-muted">
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
<div class="mb-20">
    <a href="index.php?page=feuille_caisse_add" class="btn btn-accent mr-10">
        ➕ Nouvelle Feuille de Caisse
    </a>
    <button onclick="window.print()" class="no-print btn btn-info mr-10">
        🖨️ Imprimer la liste
    </button>
    <a href="index.php?page=dashboard_caisse" class="btn btn-secondary">
        ↩️ Retour au Dashboard
    </a>
</div>

<!-- Liste des feuilles -->
<?php if (!empty($feuilles)): ?>
    <div class="card p-0 overflow-hidden">
        <table>
            <thead>
                <tr>
                    <th class="text-left p-12">Date</th>
                    <th class="text-right p-12">📊 Comptage<br><small>Pièces | Billets</small></th>
                    <th class="text-right p-12">🏦 Retraits<br><small>Pièces | Billets</small></th>
                    <th class="text-right p-12">💰 Espèces<br><small>Comptées</small></th>
                    <th class="text-right p-12">📄 Chèques</th>
                    <th class="text-right p-12">💼 Total Caisse</th>
                    <th class="text-center p-12">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feuilles as $index => $feuille): ?>
                    <tr class="<?= $index % 2 === 0 ? 'bg-secondary-light' : '' ?>">
                        <td class="p-15">
                            <strong><?= date('d/m/Y', strtotime($feuille['date_comptage'])) ?></strong>
                            <br>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($feuille['created_at'])) ?>
                            </small>
                        </td>
                        <td class="text-right p-12">
                            <div class="text-blue font-bold">
                                <?= number_format($feuille['total_pieces'], 2) ?> € | <?= number_format($feuille['total_billets'], 2) ?> €
                            </div>
                            <small class="text-muted">= <?= number_format($feuille['total_especes'], 2) ?> €</small>
                        </td>
                        <td class="text-right p-12">
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <div class="text-orange font-bold">
                                    <?= number_format($feuille['total_retrait_pieces'], 2) ?> € | <?= number_format($feuille['total_retrait_billets'], 2) ?> €
                                </div>
                                <small class="text-muted">= <?= number_format($feuille['total_retrait_especes'], 2) ?> €</small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right p-12 font-bold">
                            <?= number_format($feuille['total_especes'], 2) ?> €
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <br><small class="text-orange">(-<?= number_format($feuille['total_retrait_especes'], 2) ?> €)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right p-12">
                            <?= number_format($feuille['montant_cheques'], 2) ?> €
                            <?php if (isset($feuille['nb_cheques']) && $feuille['nb_cheques'] > 0): ?>
                                <br><small class="text-muted">(<?= $feuille['nb_cheques'] ?> chèque<?= $feuille['nb_cheques'] > 1 ? 's' : '' ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right p-12 font-bold text-lg text-accent">
                            <?= number_format($feuille['total_caisse'], 2) ?> €
                            <?php if ($feuille['total_retrait_especes'] > 0): ?>
                                <br><small class="text-green">Net: <?= number_format($feuille['total_caisse'] - $feuille['total_retrait_especes'], 2) ?> €</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center p-12 action-buttons">
                            <a href="index.php?page=feuille_caisse_view&id=<?= $feuille['id'] ?>" class="btn-sm btn-info text-none">
                                👁️ Voir
                            </a>
                            <a href="index.php?page=feuille_caisse_view&id=<?= $feuille['id'] ?>"
                               onclick="setTimeout(() => window.print(), 500); return true;"
                               class="btn-sm btn-success text-none">
                                🖨️ Imprimer
                            </a>
                            <?php if ($feuille['date_comptage'] === date('Y-m-d')): ?>
                                <a href="index.php?page=feuille_caisse_add&date=<?= $feuille['date_comptage'] ?>"
                                   class="btn-sm btn-warning text-none">
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
    <div class="card p-40 text-center">
        <h3>📋 Aucune feuille de caisse</h3>
        <p>Vous n'avez pas encore créé de feuille de caisse.</p>
        <a href="index.php?page=feuille_caisse_add" class="btn btn-accent mt-15">
            ➕ Créer ma première feuille
        </a>
    </div>
<?php endif; ?>
