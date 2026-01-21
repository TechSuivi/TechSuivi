<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$allTransactions = [];
$transactions = [];
$errorMessage = '';
$sessionMessage = '';

// Pagination
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 20;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

// Gestion des messages de session
if (isset($_SESSION['transaction_message'])) {
    $sessionMessage = $_SESSION['transaction_message'];
    unset($_SESSION['transaction_message']);
}

// Récupération des transactions
if (isset($pdo)) {
    try {
        // Récupérer toutes les transactions pour le calcul du jour
        // Récupérer toutes les transactions pour le calcul du jour
        $stmt = $pdo->query("
            SELECT t.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
            ORDER BY t.date_transaction DESC
        ");
        $allTransactions = $stmt->fetchAll();
        
        // Pagination
        $totalTransactions = count($allTransactions);
        $totalPages = ceil($totalTransactions / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $transactions = array_slice($allTransactions, $offset, $perPage);
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des transactions : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Calcul des totaux du jour (sur toutes les transactions, pas seulement la page courante)
$total_jour = 0;
$nb_transactions_jour = 0;
$today = date('Y-m-d');
foreach ($allTransactions as $transaction) {
    $transactionDate = date('Y-m-d', strtotime($transaction['date_transaction']));
    if ($transactionDate === $today) {
        $total_jour += $transaction['montant'];
        $nb_transactions_jour++;
    }
}
?>

<h1>Transactions - Fiche de Caisse</h1>

<p><a href="index.php?page=transaction_add" class="button-like" style="text-decoration: none; padding: 8px 15px; background-color: var(--accent-color); color: white; border-radius: 4px;">Nouvelle transaction</a></p>

<?php if (!empty($sessionMessage)): ?>
    <div class="session-message" style="margin-bottom: 15px; padding: 10px; border: 1px solid green; background-color: #e6ffe6;">
        <?= htmlspecialchars($sessionMessage) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <p style="color: red;"><?= htmlspecialchars($errorMessage) ?></p>
<?php endif; ?>

<!-- Résumé du jour -->
<div style="background-color: var(--card-bg); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--accent-color); display: flex; align-items: center; gap: 20px;">
    <div>
        <strong style="color: var(--accent-color);">📅 Transactions du jour (<?= date('d/m/Y') ?>)</strong>
    </div>
    <div style="display: flex; gap: 30px;">
        <div>
            <span style="color: var(--text-muted);">Nombre :</span>
            <strong><?= $nb_transactions_jour ?></strong>
        </div>
        <div>
            <span style="color: var(--text-muted);">Total :</span>
            <strong style="color: green;"><?= number_format($total_jour, 2) ?> €</strong>
        </div>
    </div>
</div>

<!-- Contrôles de pagination -->
<div style="background-color: var(--card-bg); padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <label for="per_page">Afficher :</label>
        <select id="per_page" onchange="changePerPage(this.value)" style="padding: 5px 10px; border-radius: 4px; border: 1px solid var(--border-color);">
            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
            <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>20</option>
            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
        </select>
        <span>par page</span>
    </div>
    <div style="color: var(--text-muted);">
        <?php if ($totalTransactions > 0): ?>
            Affichage <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalTransactions) ?> sur <?= $totalTransactions ?> transactions
        <?php else: ?>
            Aucune transaction
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 5px;">
        <?php if ($page > 1): ?>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=1" style="padding: 5px 10px; background-color: var(--accent-color); color: white; border-radius: 4px; text-decoration: none;">«</a>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $page - 1 ?>" style="padding: 5px 10px; background-color: var(--accent-color); color: white; border-radius: 4px; text-decoration: none;">‹</a>
        <?php endif; ?>
        <span style="padding: 5px 10px; background-color: var(--secondary-color); color: white; border-radius: 4px;">Page <?= $page ?>/<?= max(1, $totalPages) ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $page + 1 ?>" style="padding: 5px 10px; background-color: var(--accent-color); color: white; border-radius: 4px; text-decoration: none;">›</a>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $totalPages ?>" style="padding: 5px 10px; background-color: var(--accent-color); color: white; border-radius: 4px; text-decoration: none;">»</a>
        <?php endif; ?>
    </div>
</div>

<script>
function changePerPage(value) {
    window.location.href = 'index.php?page=transactions_list&per_page=' + value + '&p=1';
}
</script>

<?php if (empty($transactions) && empty($errorMessage)): ?>
    <p>Aucune transaction enregistrée.</p>
<?php elseif (!empty($transactions)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Nom/Description</th>
                <th>Type</th>
                <th>Montant</th>
                <th>Banque</th>
                <th>N° Chèque</th>
                <th>Acompte</th>
                <th>Solde</th>
                <th>N° Facture</th>
                <th>Payé le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <tr style="<?= $transaction['type'] === 'sortie' ? 'background-color: #fff5f5;' : '' ?>">
                    <td><?= htmlspecialchars($transaction['id']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($transaction['date_transaction'])) ?></td>

                    <td>
                        <?php if (!empty($transaction['client_nom'])): ?>
                            <!-- Affichage propre pour les clients liés -->
                            <a href="index.php?page=clients_view&id=<?= $transaction['id_client'] ?>" style="text-decoration: none; color: inherit; font-weight: 500;" title="Voir la fiche client">
                                <span title="Client lié">👤 <?= htmlspecialchars($transaction['client_nom'] . ' ' . ($transaction['client_prenom'] ?? '')) ?></span>
                            </a>
                        <?php else: ?>
                            <!-- Affichage standard (description libre ou ancien format) -->
                            <?= htmlspecialchars($transaction['nom'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="padding: 3px 8px; border-radius: 12px; font-size: 12px; color: white; 
                                     background-color: <?= $transaction['type'] === 'entree' ? 'green' : ($transaction['type'] === 'sortie' ? 'red' : 'gray') ?>;">
                            <?= htmlspecialchars(ucfirst($transaction['type'] ?? 'N/A')) ?>
                        </span>
                    </td>
                    <td style="font-weight: bold; color: <?= $transaction['type'] === 'entree' ? 'green' : ($transaction['type'] === 'sortie' ? 'red' : 'inherit') ?>;">
                        <?= $transaction['type'] === 'sortie' ? '-' : '' ?><?= number_format($transaction['montant'], 2) ?> €
                    </td>
                    <td><?= htmlspecialchars($transaction['banque'] ?? '') ?></td>
                    <td><?= htmlspecialchars($transaction['num_cheque'] ?? '') ?></td>
                    <td><?= $transaction['acompte'] ? number_format($transaction['acompte'], 2) . ' €' : '' ?></td>
                    <td><?= $transaction['solde'] ? number_format($transaction['solde'], 2) . ' €' : '' ?></td>
                    <td><?= htmlspecialchars($transaction['num_facture'] ?? '') ?></td>
                    <td><?= (!empty($transaction['paye_le']) && $transaction['paye_le'] !== '0000-00-00') ? date('d/m/Y', strtotime($transaction['paye_le'])) : '' ?></td>
                    <td>
                        <?php
                        $transactionDate = date('Y-m-d', strtotime($transaction['date_transaction']));
                        $today = date('Y-m-d');
                        if ($transactionDate === $today):
                        ?>
                        <a href="index.php?page=transaction_edit&id=<?= htmlspecialchars($transaction['id']) ?>" 
                           style="margin-right: 10px; color: var(--accent-color);">Modifier</a>
                        <a href="actions/transaction_delete.php?id=<?= htmlspecialchars($transaction['id']) ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?');" 
                           style="color: red;">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background-color: var(--card-bg); border-radius: 8px;">
    <h3>Types de transactions</h3>
    <ul>
        <li><strong>Entrée :</strong> Recettes (ventes, paiements clients, etc.)</li>
        <li><strong>Sortie :</strong> Dépenses (achats, frais, etc.)</li>
        <li><strong>Transfert :</strong> Mouvements entre comptes</li>
    </ul>
    <p><small>Les acomptes et soldes permettent de suivre les paiements partiels et les restes à payer.</small></p>
</div>