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

<?php if ($credit): ?>
    <h1>Historique - <?= htmlspecialchars($credit['nom_client']) ?></h1>
<?php else: ?>
    <h1>Historique du crédit</h1>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <a href="index.php?page=cyber_credits_list" style="color: var(--accent-color); text-decoration: none; margin-right: 15px;">
        ← Retour à la liste des crédits
    </a>
    <?php if ($credit): ?>
        <a href="index.php?page=cyber_credits_add&id=<?= $credit['id'] ?>" style="color: var(--accent-color); text-decoration: none;">
            ✏️ Modifier ce crédit
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($errorMessage)): ?>
    <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #ffe6e6; border-radius: 4px;">
        <?= $errorMessage ?>
    </div>
<?php elseif ($credit): ?>
    
    <!-- Informations du crédit -->
    <div style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <h3 style="margin: 0 0 10px 0; color: var(--accent-color);">Client</h3>
                <div style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($credit['nom_client']) ?></div>
                <?php if (!empty($credit['notes'])): ?>
                    <div style="color: var(--text-secondary); margin-top: 5px;">
                        <?= htmlspecialchars($credit['notes']) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <h3 style="margin: 0 0 10px 0; color: var(--accent-color);">Solde actuel</h3>
                <?php 
                $solde = $credit['solde_actuel'];
                $color = $solde > 0 ? 'var(--success-color, #28a745)' : ($solde < 0 ? 'var(--danger-color, #dc3545)' : 'var(--text-secondary)');
                ?>
                <div style="font-size: 24px; font-weight: bold; color: <?= $color ?>;">
                    <?= number_format($solde, 2) ?> €
                </div>
            </div>
            
            <div>
                <h3 style="margin: 0 0 10px 0; color: var(--accent-color);">Créé le</h3>
                <div style="font-size: 16px;">
                    <?= date('d/m/Y à H:i', strtotime($credit['date_creation'])) ?>
                </div>
            </div>
            
            <div>
                <h3 style="margin: 0 0 10px 0; color: var(--accent-color);">Dernière modification</h3>
                <div style="font-size: 16px;">
                    <?= date('d/m/Y à H:i', strtotime($credit['date_modification'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: var(--accent-color);"><?= $nb_mouvements ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">Mouvements</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: var(--success-color, #28a745);">+<?= number_format($total_ajouts, 2) ?> €</div>
            <div style="color: var(--text-secondary); font-size: 14px;">Total ajouts</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: var(--danger-color, #dc3545);">-<?= number_format($total_deductions, 2) ?> €</div>
            <div style="color: var(--text-secondary); font-size: 14px;">Total déductions</div>
        </div>
        
        <div style="background-color: var(--card-bg); padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 20px; font-weight: bold; color: var(--info-color, #17a2b8);"><?= number_format($total_ajouts - $total_deductions, 2) ?> €</div>
            <div style="color: var(--text-secondary); font-size: 14px;">Différence</div>
        </div>
    </div>

    <!-- Historique des mouvements -->
    <?php if (empty($historique)): ?>
        <div style="text-align: center; padding: 40px; background-color: var(--card-bg); border-radius: 8px;">
            <p style="color: var(--text-secondary); font-size: 18px;">Aucun mouvement enregistré</p>
        </div>
    <?php else: ?>
        <div style="background-color: var(--card-bg); border-radius: 8px; overflow: hidden;">
            <div style="background-color: var(--accent-color); color: white; padding: 15px; font-weight: bold;">
                Historique complet des mouvements
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: var(--bg-color);">
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Date & Heure</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Type</th>
                        <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ddd;">Montant</th>
                        <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ddd;">Solde avant</th>
                        <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ddd;">Solde après</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Description</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 1px solid #ddd;">Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $index => $mouvement): ?>
                        <tr style="border-bottom: 1px solid #eee; <?= $index % 2 === 0 ? 'background-color: var(--bg-color);' : '' ?>">
                            <td style="padding: 12px;">
                                <div style="font-weight: bold;"><?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?></div>
                                <div style="color: var(--text-secondary); font-size: 12px;"><?= date('H:i:s', strtotime($mouvement['date_mouvement'])) ?></div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <?php
                                $type_colors = [
                                    'AJOUT' => 'var(--success-color, #28a745)',
                                    'DEDUCTION' => 'var(--danger-color, #dc3545)',
                                    'CORRECTION' => 'var(--warning-color, #ffc107)'
                                ];
                                $color = $type_colors[$mouvement['type_mouvement']] ?? 'var(--text-secondary)';
                                ?>
                                <span style="background-color: <?= $color ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                    <?= $mouvement['type_mouvement'] ?>
                                </span>
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: bold; font-size: 16px;">
                                <?php if ($mouvement['type_mouvement'] === 'DEDUCTION'): ?>
                                    <span style="color: var(--danger-color, #dc3545);">-<?= number_format($mouvement['montant'], 2) ?> €</span>
                                <?php else: ?>
                                    <span style="color: var(--success-color, #28a745);">+<?= number_format($mouvement['montant'], 2) ?> €</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: right; color: var(--text-secondary);">
                                <?= number_format($mouvement['solde_avant'], 2) ?> €
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: bold;">
                                <?php
                                $solde = $mouvement['solde_apres'];
                                $color = $solde > 0 ? 'var(--success-color, #28a745)' : ($solde < 0 ? 'var(--danger-color, #dc3545)' : 'var(--text-secondary)');
                                ?>
                                <span style="color: <?= $color ?>;"><?= number_format($solde, 2) ?> €</span>
                            </td>
                            <td style="padding: 12px;">
                                <div><?= htmlspecialchars($mouvement['description']) ?></div>
                                <?php if ($mouvement['session_nom']): ?>
                                    <div style="margin-top: 5px; padding: 2px 6px; background-color: var(--info-color, #17a2b8); color: white; border-radius: 3px; font-size: 11px; display: inline-block;">
                                        Session: <?= htmlspecialchars($mouvement['session_nom']) ?>
                                        <?php if ($mouvement['session_date']): ?>
                                            (<?= date('d/m/Y', strtotime($mouvement['session_date'])) ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; color: var(--text-secondary); font-size: 12px;">
                                <?= htmlspecialchars($mouvement['utilisateur'] ?? 'Système') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background-color: var(--card-bg); border-radius: 8px;">
    <h3>Légende</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <span style="background-color: var(--success-color, #28a745); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">AJOUT</span>
            <span style="margin-left: 10px;">Crédit ajouté au compte</span>
        </div>
        <div>
            <span style="background-color: var(--danger-color, #dc3545); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">DEDUCTION</span>
            <span style="margin-left: 10px;">Crédit utilisé ou retiré</span>
        </div>
        <div>
            <span style="background-color: var(--warning-color, #ffc107); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">CORRECTION</span>
            <span style="margin-left: 10px;">Ajustement manuel du solde</span>
        </div>
    </div>
</div>