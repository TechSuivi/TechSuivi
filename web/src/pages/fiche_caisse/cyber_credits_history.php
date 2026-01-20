<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';
$credit = null;
$historique = [];

// Vérifier l'ID du crédit
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $errorMessage = "ID de crédit invalide.";
} else {
    $creditId = (int)$_GET['id'];
    
    try {
        // Récupérer les informations du crédit
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber_credits WHERE id = ? AND actif = 1");
        $stmt->execute([$creditId]);
        $credit = $stmt->fetch();
        
        if (!$credit) {
            $errorMessage = "Crédit client non trouvé.";
        } else {
            // Récupérer l'historique complet
            $stmt = $pdo->prepare("
                SELECT h.*, s.nom as session_nom, s.date_cyber as session_date
                FROM FC_cyber_credits_historique h 
                LEFT JOIN FC_cyber s ON h.session_cyber_id = s.id 
                WHERE h.credit_id = ? 
                ORDER BY h.date_mouvement DESC
            ");
            $stmt->execute([$creditId]);
            $historique = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage());
    }
}

// Calculs statistiques
$total_ajouts = 0;
$total_deductions = 0;
$nb_mouvements = count($historique);

foreach ($historique as $mouvement) {
    if ($mouvement['type_mouvement'] === 'AJOUT') {
        $total_ajouts += $mouvement['montant'];
    } elseif ($mouvement['type_mouvement'] === 'DEDUCTION') {
        $total_deductions += $mouvement['montant'];
    }
}
?>



<div class="flex-between-center mb-20">
    <h1 class="m-0">
        <?php if ($credit): ?>
            Historique - <?= htmlspecialchars($credit['nom_client']) ?>
        <?php else: ?>
            Historique du crédit
        <?php endif; ?>
    </h1>
    <div>
        <a href="index.php?page=cyber_credits_list" class="btn btn-secondary mr-10">
            ← Retour à la liste des crédits
        </a>
        <?php if ($credit): ?>
            <a href="index.php?page=cyber_credits_add&id=<?= $credit['id'] ?>" class="btn btn-accent">
                ✏️ Modifier ce crédit
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error mb-15">
        <?= $errorMessage ?>
    </div>
<?php elseif ($credit): ?>
    
    <!-- Résumé Global 3 Colonnes -->
    <div class="card p-0 mb-20 overflow-hidden bg-secondary-light border-light">
        <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-light">
            
            <!-- Colonne 1 : Client & Infos -->
            <div class="p-15 flex flex-col justify-center">
                <div class="flex items-center gap-15">
                    <div class="text-3xl">👤</div>
                    <div>
                        <div class="font-bold text-lg mb-2 text-primary"><?= htmlspecialchars($credit['nom_client']) ?></div>
                        <div class="text-xs text-muted flex flex-col gap-2">
                            <span>📅 Créé le <?= date('d/m/Y', strtotime($credit['date_creation'])) ?></span>
                            <span>📝 Modifié le <?= date('d/m/Y', strtotime($credit['date_modification'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($credit['notes'])): ?>
                    <div class="mt-10 pt-10 border-t border-light text-sm text-muted italic">
                        "<?= htmlspecialchars($credit['notes']) ?>"
                    </div>
                <?php endif; ?>
            </div>

            <!-- Colonne 2 : Statistiques (Grille 2x2) -->
            <div class="p-15 bg-card">
                <div class="grid grid-cols-2 gap-10 h-full">
                    <div class="flex flex-col justify-center p-5 rounded hover:bg-secondary-light transition-fast">
                        <span class="text-muted text-xs uppercase tracking-wide">Mouvements</span>
                        <span class="font-bold text-accent"><?= $nb_mouvements ?></span>
                    </div>
                    <div class="flex flex-col justify-center p-5 rounded hover:bg-secondary-light transition-fast">
                        <span class="text-muted text-xs uppercase tracking-wide">Différence</span>
                        <span class="font-bold text-info"><?= number_format($total_ajouts - $total_deductions, 2) ?> €</span>
                    </div>
                    <div class="flex flex-col justify-center p-5 rounded hover:bg-secondary-light transition-fast">
                        <span class="text-muted text-xs uppercase tracking-wide">Total Ajouts</span>
                        <span class="font-bold text-success">+<?= number_format($total_ajouts, 2) ?> €</span>
                    </div>
                    <div class="flex flex-col justify-center p-5 rounded hover:bg-secondary-light transition-fast">
                        <span class="text-muted text-xs uppercase tracking-wide">Total Déductions</span>
                        <span class="font-bold text-danger">-<?= number_format($total_deductions, 2) ?> €</span>
                    </div>
                </div>
            </div>

            <!-- Colonne 3 : Solde Actuel -->
            <div class="p-15 flex flex-col justify-center items-center lg:items-end text-center lg:text-right">
                <div class="text-sm text-muted uppercase tracking-wider mb-5">Solde Actuel</div>
                <?php 
                $solde = $credit['solde_actuel'];
                $color_class = $solde > 0 ? 'text-success' : ($solde < 0 ? 'text-danger' : 'text-muted');
                ?>
                <div class="text-4xl font-bold <?= $color_class ?> mb-5">
                    <?= number_format($solde, 2) ?> €
                </div>
                <div class="text-xs text-muted">Solde disponible immédiat</div>
            </div>
        </div>
    </div>

    <!-- Historique des mouvements -->
    <?php if (empty($historique)): ?>
        <div class="card p-40 text-center">
            <p class="text-muted text-lg">Aucun mouvement enregistré</p>
        </div>
    <?php else: ?>
        <div class="card overflow-hidden">
            <div class="bg-accent text-white p-15 font-bold">
                Historique complet des mouvements
            </div>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-secondary-light">
                        <th class="p-12 text-left border-b border-light">Date & Heure</th>
                        <th class="p-12 text-center border-b border-light">Type</th>
                        <th class="p-12 text-right border-b border-light">Montant</th>
                        <th class="p-12 text-right border-b border-light">Solde avant</th>
                        <th class="p-12 text-right border-b border-light">Solde après</th>
                        <th class="p-12 text-left border-b border-light">Description</th>
                        <th class="p-12 text-center border-b border-light">Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $index => $mouvement): ?>
                        <tr class="border-b border-light <?= $index % 2 === 0 ? 'bg-secondary-light' : '' ?>">
                            <td class="p-12">
                                <div class="font-bold"><?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?></div>
                                <div class="text-muted text-xs"><?= date('H:i:s', strtotime($mouvement['date_mouvement'])) ?></div>
                            </td>
                            <td class="p-12 text-center">
                                <?php
                                $type_classes = [
                                    'AJOUT' => 'badge-success',
                                    'DEDUCTION' => 'badge-danger',
                                    'CORRECTION' => 'badge-warning'
                                ];
                                $badge_class = $type_classes[$mouvement['type_mouvement']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= $mouvement['type_mouvement'] ?>
                                </span>
                            </td>
                            <td class="p-12 text-right font-bold text-lg">
                                <?php if ($mouvement['type_mouvement'] === 'DEDUCTION'): ?>
                                    <span class="text-danger">-<?= number_format($mouvement['montant'], 2) ?> €</span>
                                <?php else: ?>
                                    <span class="text-success">+<?= number_format($mouvement['montant'], 2) ?> €</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-12 text-right text-muted">
                                <?= number_format($mouvement['solde_avant'], 2) ?> €
                            </td>
                            <td class="p-12 text-right font-bold">
                                <?php
                                $solde = $mouvement['solde_apres'];
                                $color_class = $solde > 0 ? 'text-success' : ($solde < 0 ? 'text-danger' : 'text-muted');
                                ?>
                                <span class="<?= $color_class ?>"><?= number_format($solde, 2) ?> €</span>
                            </td>
                            <td class="p-12">
                                <div><?= htmlspecialchars($mouvement['description']) ?></div>
                                <?php if ($mouvement['session_nom']): ?>
                                    <div class="mt-5 inline-block badge badge-info">
                                        Session: <?= htmlspecialchars($mouvement['session_nom']) ?>
                                        <?php if ($mouvement['session_date']): ?>
                                            (<?= date('d/m/Y', strtotime($mouvement['session_date'])) ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-12 text-center text-muted text-xs">
                                <?= htmlspecialchars($mouvement['utilisateur'] ?? 'Système') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>
