<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$sessions_cyber = [];
$errorMessage = '';
$sessionMessage = '';

// Gestion des messages de session
if (isset($_SESSION['cyber_message'])) {
    $sessionMessage = $_SESSION['cyber_message'];
    unset($_SESSION['cyber_message']);
}

// Récupération des sessions cyber
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT s.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_cyber s
            LEFT JOIN clients c ON s.id_client = c.ID
            ORDER BY s.date_cyber DESC
        ");
        $sessions_cyber = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des sessions : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Fonction pour calculer la durée et afficher le prix
function calculerDureeEtPrix($ha, $hd, $imp, $imp_c, $tarif) {
    $duree = '';
    
    if ($ha && $hd) {
        $debut = new DateTime($ha);
        $fin = new DateTime($hd);
        $diff = $debut->diff($fin);
        $minutes = ($diff->h * 60) + $diff->i;
        $duree = $minutes . ' min';
    }
    
    // Le prix est maintenant directement stocké dans tarif
    $prix_affiche = $tarif ?? 0;
    
    return ['duree' => $duree, 'prix' => $prix_affiche];
}

// Récupération des tarifs actuels pour l'affichage en bas de page
$current_pricing = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'cyber_pricing'");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $current_pricing[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {
    // Utiliser les valeurs par défaut si erreur
}

$current_price_nb = $current_pricing['cyber_price_nb_page'] ?? '0.20';
$current_price_color = $current_pricing['cyber_price_color_page'] ?? '0.30';
$current_price_minimum = $current_pricing['cyber_price_time_minimum'] ?? '0.50';
$current_time_threshold = $current_pricing['cyber_time_minimum_threshold'] ?? '10';
$current_time_increment = $current_pricing['cyber_time_increment'] ?? '15';
$current_price_base = $current_pricing['cyber_price_time_base'] ?? '0.75';
?>

<h1>Fiche de Caisse - Sessions Cyber</h1>

<div class="mb-20">
    <a href="index.php?page=cyber_add" class="btn btn-accent">
        Nouvelle session
    </a>
</div>

<?php if (!empty($sessionMessage)): ?>
    <div class="alert alert-success mb-15">
        <?= htmlspecialchars($sessionMessage) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($errorMessage) ?>
    </div>
<?php endif; ?>

<?php if (empty($sessions_cyber) && empty($errorMessage)): ?>
    <p>Aucune session enregistrée.</p>
<?php elseif (!empty($sessions_cyber)): ?>
    <div class="card p-0 overflow-hidden">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Heure d'arrivée</th>
                    <th>Heure de départ</th>
                    <th>Durée</th>
                    <th>Imp. N&B</th>
                    <th>Imp. Couleur</th>
                    <th>Total Imp.</th>
                    <th>Moyen paiement</th>
                    <th>Prix total</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_jour = 0;
                foreach ($sessions_cyber as $index => $session):
                    $calcul = calculerDureeEtPrix($session['ha'], $session['hd'], $session['imp'], $session['imp_c'], $session['tarif']);
                    $total_jour += $calcul['prix'];
                    
                    // Maintenant imp et imp_c contiennent directement le nombre de pages
                    $pages_nb = $session['imp'] ?? 0;
                    $pages_couleur = $session['imp_c'] ?? 0;
                    
                    // Utiliser les tarifs sauvegardés avec la session, ou les valeurs par défaut
                    $session_price_nb = $session['price_nb_page'] ?? 0.20;
                    $session_price_color = $session['price_color_page'] ?? 0.30;
                    
                    // Calcul du total des impressions en euros avec les tarifs de la session
                    $total_impressions = ($pages_nb * $session_price_nb) + ($pages_couleur * $session_price_color);
                    
                    // Affichage du moyen de paiement avec info chèque si applicable
                    $moyen_affichage = htmlspecialchars($session['moyen_payement'] ?? '');
                    if ($session['moyen_payement'] === 'Chèque' && $session['info_chq']) {
                        $info_parts = explode('|', $session['info_chq']);
                        $banque = '';
                        $numero = '';
                        foreach ($info_parts as $part) {
                            if (strpos($part, 'banque=') === 0) {
                                $banque = substr($part, 7);
                            } elseif (strpos($part, 'numero=') === 0) {
                                $numero = substr($part, 7);
                            }
                        }
                        if ($banque || $numero) {
                            $moyen_affichage .= '<br><small>' . ($banque ? 'Banque: ' . htmlspecialchars($banque) : '') .
                                               ($banque && $numero ? '<br>' : '') .
                                               ($numero ? 'N°: ' . htmlspecialchars($numero) : '') . '</small>';
                        }
                    }
                ?>
                    <tr class="<?= $index % 2 === 0 ? 'bg-secondary-light' : '' ?>">
                        <td><?= htmlspecialchars($session['id']) ?></td>
                        <td>
                            <?php if (!empty($session['client_nom'])): ?>
                                <a href="index.php?page=clients_view&id=<?= $session['id_client'] ?>" class="font-medium text-inherit text-none" title="Voir la fiche client">
                                    <span title="Client lié">👤 <?= htmlspecialchars($session['client_nom'] . ' ' . ($session['client_prenom'] ?? '')) ?></span>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($session['nom'] ?? 'Anonyme') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $session['ha'] ? date('H:i', strtotime($session['ha'])) : '-' ?></td>
                        <td><?= $session['hd'] ? date('H:i', strtotime($session['hd'])) : '-' ?></td>
                        <td><?= $calcul['duree'] ?: '-' ?></td>
                        <td><?= $pages_nb > 0 ? $pages_nb . ' pages' : '-' ?></td>
                        <td><?= $pages_couleur > 0 ? $pages_couleur . ' pages' : '-' ?></td>
                        <td><?= $total_impressions > 0 ? number_format($total_impressions, 2) . ' €' : '-' ?></td>
                        <td><?= $moyen_affichage ?: '-' ?></td>
                        <td><strong><?= number_format($calcul['prix'], 2) ?> €</strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($session['date_cyber'])) ?></td>
                        <td>
                            <?php
                            $sessionDate = date('Y-m-d', strtotime($session['date_cyber']));
                            $today = date('Y-m-d');
                            if ($sessionDate === $today):
                            ?>
                            <a href="index.php?page=cyber_edit&id=<?= htmlspecialchars($session['id']) ?>"
                               class="text-accent mr-10 action-link">Modifier</a>
                            <a href="actions/cyber_delete.php?id=<?= htmlspecialchars($session['id']) ?>"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette session ?');"
                               class="text-red action-link-danger">Supprimer</a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-accent text-white font-bold">
                    <td colspan="9" class="text-right p-10">Total du jour :</td>
                    <td class="p-10"><?= number_format($total_jour, 2) ?> €</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>

<div class="card mt-25">
    <h3 class="card-title">Tarifs actuels</h3>
    <ul>
        <li>≤<?= $current_time_threshold ?> minutes : <?= number_format($current_price_minimum, 2) ?> €</li>
        <li><?= $current_time_increment ?> minutes : <?= number_format($current_price_base, 2) ?> €</li>
        <li>Impression noir et blanc : <?= number_format($current_price_nb, 2) ?> € par page</li>
        <li>Impression couleur : <?= number_format($current_price_color, 2) ?> € par page</li>
    </ul>
    <p class="text-sm text-muted">Ces tarifs s'appliquent aux nouvelles sessions. Les sessions existantes conservent leurs tarifs d'origine.</p>
    <p class="text-sm"><a href="index.php?page=cyber_pricing_config" class="text-accent">⚙️ Modifier les tarifs</a></p>
</div>