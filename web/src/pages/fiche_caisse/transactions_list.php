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

// Filtres
$dateStart = $_GET['date_start'] ?? '';
$dateEnd = $_GET['date_end'] ?? '';

// Récupération des transactions
if (isset($pdo)) {
    try {
        // Construction de la requête avec filtres
        $sql = "
            SELECT t.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
        ";
        
        $where = [];
        $params = [];
        
        if (!empty($dateStart)) {
            $where[] = "t.date_transaction >= :date_start";
            $params[':date_start'] = $dateStart . ' 00:00:00';
        }
        
        if (!empty($dateEnd)) {
            $where[] = "t.date_transaction <= :date_end";
            $params[':date_end'] = $dateEnd . ' 23:59:59';
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY t.date_transaction DESC";

        // Récupérer toutes les transactions FILTRÉES pour la pagination
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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

// Calcul des totaux du jour (Indépendant des filtres actuels pour toujours afficher l'activité du jour)
$total_jour = 0;
$nb_transactions_jour = 0;
try {
    $stmtToday = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(montant) as total 
        FROM FC_transactions 
        WHERE date_transaction >= :start AND date_transaction <= :end
    ");
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $stmtToday->execute([':start' => $todayStart, ':end' => $todayEnd]);
    $resultToday = $stmtToday->fetch(PDO::FETCH_ASSOC);
    
    if ($resultToday) {
        $nb_transactions_jour = $resultToday['count'];
        $total_jour = $resultToday['total'] ?? 0;
    }
} catch (Exception $e) {
    // Silent fail for stats
}
?>

<div class="toolbar-stock bg-card p-15 rounded shadow-sm mb-20 flex-between-center">
    <h1 class="text-color m-0 text-2xl">💳 Transactions - Fiche de Caisse</h1>
    <a href="index.php?page=transaction_add" class="btn btn-primary">
        <span>➕ Nouvelle transaction</span>
    </a>
</div>

<?php if (!empty($sessionMessage)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($sessionMessage) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>

<!-- Résumé du jour -->
<div class="card border-left-green mb-20 flex-between-center">
    <div>
        <strong class="text-accent">📅 Transactions du jour (<?= date('d/m/Y') ?>)</strong>
    </div>
    <div class="flex-wrap gap-20">
        <div>
            <span class="text-muted">Nombre :</span>
            <strong><?= $nb_transactions_jour ?></strong>
        </div>
        <div>
            <span class="text-muted">Total :</span>
            <strong class="text-green"><?= number_format($total_jour, 2) ?> €</strong>
        </div>
    </div>
</div>

<!-- BARRE DE FILTRES -->
<div class="card p-15 mb-20 bg-light border border-border rounded">
    <form method="GET" action="index.php" class="flex flex-wrap items-end gap-10">
        <input type="hidden" name="page" value="transactions_list">
        
        <div>
            <label class="block text-xs font-bold text-muted uppercase mb-5">Du</label>
            <input type="date" name="date_start" value="<?= htmlspecialchars($dateStart) ?>" class="form-control p-5 text-sm h-35">
        </div>
        
        <div>
            <label class="block text-xs font-bold text-muted uppercase mb-5">Au</label>
            <input type="date" name="date_end" value="<?= htmlspecialchars($dateEnd) ?>" class="form-control p-5 text-sm h-35">
        </div>
        
        <div>
            <button type="submit" class="btn btn-secondary h-35 px-15">🔍 Filtrer</button>
        </div>

        <div class="border-l border-border mx-5 h-35"></div>

        <!-- Boutons Rapides -->
        <div class="flex gap-5">
            <a href="index.php?page=transactions_list&date_start=<?= date('Y-m-d') ?>&date_end=<?= date('Y-m-d') ?>" class="btn h-35 flex-center <?= ($dateStart == date('Y-m-d') && $dateEnd == date('Y-m-d')) ? 'btn-primary' : 'btn-outline-primary' ?>">
                ⚡ Aujourd'hui
            </a>
            <a href="index.php?page=transactions_list&date_start=<?= date('Y-m-01') ?>&date_end=<?= date('Y-m-t') ?>" class="btn h-35 flex-center <?= ($dateStart == date('Y-m-01')) ? 'btn-primary' : 'btn-outline-primary' ?>">
                📅 Ce mois
            </a>
            <a href="index.php?page=transactions_list" class="btn h-35 flex-center <?= (empty($dateStart) && empty($dateEnd)) ? 'btn-primary' : 'btn-secondary' ?>">
                ♾️ Tout
            </a>
        </div>
    </form>
</div>

<!-- Contrôles de pagination -->
<div class="pagination-container">
    <div class="pagination-controls">
        <label for="per_page">Afficher :</label>
        <select id="per_page" onchange="changePerPage(this.value)" class="form-control" style="width: auto; padding: 5px;">
            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
            <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>20</option>
            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
        </select>
        <span>par page</span>
    </div>
    <div class="text-muted">
        <?php if ($totalTransactions > 0): ?>
            Affichage <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalTransactions) ?> sur <?= $totalTransactions ?> transactions
        <?php else: ?>
            Aucune transaction trouvée
        <?php endif; ?>
    </div>
    <div class="pagination-controls">
        <?php
            // Helper pour garder les params de filtre dans l'URL de pagination
            $filterParams = "&date_start=" . urlencode($dateStart) . "&date_end=" . urlencode($dateEnd);
        ?>
        <?php if ($page > 1): ?>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=1<?= $filterParams ?>" class="pagination-btn">«</a>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $page - 1 ?><?= $filterParams ?>" class="pagination-btn">‹</a>
        <?php endif; ?>
        <span class="pagination-info">Page <?= $page ?>/<?= max(1, $totalPages) ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $page + 1 ?><?= $filterParams ?>" class="pagination-btn">›</a>
            <a href="index.php?page=transactions_list&per_page=<?= $perPage ?>&p=<?= $totalPages ?><?= $filterParams ?>" class="pagination-btn">»</a>
        <?php endif; ?>
    </div>
</div>

<script>
function changePerPage(value) {
    const urlParams = new URLSearchParams(window.location.search);
    const dateStart = urlParams.get('date_start') || '';
    const dateEnd = urlParams.get('date_end') || '';
    window.location.href = 'index.php?page=transactions_list&per_page=' + value + '&p=1&date_start=' + dateStart + '&date_end=' + dateEnd;
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
                <tr class="<?= $transaction['type'] === 'sortie' ? 'bg-soft-red' : '' ?>">
                    <td><?= htmlspecialchars($transaction['id']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($transaction['date_transaction'])) ?></td>

                    <td>
                        <?php if (!empty($transaction['client_nom'])): ?>
                            <!-- Affichage propre pour les clients liés -->
                            <a href="index.php?page=clients_view&id=<?= $transaction['id_client'] ?>" class="text-reset" style="font-weight: 500;" title="Voir la fiche client">
                                <span title="Client lié">👤 <?= htmlspecialchars($transaction['client_nom'] . ' ' . ($transaction['client_prenom'] ?? '')) ?></span>
                            </a>
                        <?php else: ?>
                            <!-- Affichage standard (description libre ou ancien format) -->
                            <?= htmlspecialchars($transaction['nom'] ?? '') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $typeClass = $transaction['type'] === 'entree' ? 'bg-green' : ($transaction['type'] === 'sortie' ? 'bg-red' : 'bg-secondary'); ?>
                        <span class="badge <?= $typeClass ?>">
                            <?= htmlspecialchars(ucfirst($transaction['type'] ?? 'N/A')) ?>
                        </span>
                    </td>
                    <td style="font-weight: bold;" class="<?= $transaction['type'] === 'entree' ? 'text-green' : ($transaction['type'] === 'sortie' ? 'text-red' : '') ?>">
                        <?= $transaction['type'] === 'sortie' ? '-' : '' ?><?= number_format($transaction['montant'], 2) ?> €
                    </td>
                    <td><?= htmlspecialchars($transaction['banque'] ?? '') ?></td>
                    <td><?= htmlspecialchars($transaction['num_cheque'] ?? '') ?></td>
                    <td><?= $transaction['acompte'] ? number_format($transaction['acompte'], 2) . ' €' : '' ?></td>
                    <td><?= $transaction['solde'] ? number_format($transaction['solde'], 2) . ' €' : '' ?></td>
                    <td><?= htmlspecialchars($transaction['num_facture'] ?? '') ?></td>
                    <td><?= (!empty($transaction['paye_le']) && $transaction['paye_le'] !== '0000-00-00') ? date('d/m/Y', strtotime($transaction['paye_le'])) : '' ?></td>
                    <td>
                        <div class="flex items-center gap-10">
                            <?php
                            $transactionDate = date('Y-m-d', strtotime($transaction['date_transaction']));
                            $today = date('Y-m-d');
                            if ($transactionDate === $today):
                            ?>
                            <a href="index.php?page=transaction_edit&id=<?= htmlspecialchars($transaction['id']) ?>" 
                               class="btn-sm-action" title="Modifier">✏️</a>
                            <a href="actions/transaction_delete.php?id=<?= htmlspecialchars($transaction['id']) ?>" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?');" 
                               class="btn-sm-action text-danger" title="Supprimer">🗑️</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
