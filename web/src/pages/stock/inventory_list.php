<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

// Création rapide d'une session via POST (si pas JS) ou traitement simple
// Ici on va surtout lister.

// Récupérer les sessions
try {
    $stmt = $pdo->query("SELECT * FROM inventory_sessions ORDER BY created_at DESC");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container container-center p-20">
    <div class="flex-between-center mb-20">
        <h1 class="text-color">📦 Inventaires Physiques</h1>
        <button onclick="createNewSession()" class="btn btn-primary font-bold">
            + Nouvel Inventaire
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card p-0 overflow-hidden">
        <table class="w-full border-collapse">
            <thead>
                    <tr class="bg-input border-b border-border text-muted uppercase text-xs font-bold">
                        <th class="p-12 text-left">Nom</th>
                        <th class="p-12 text-left">Date Création</th>
                        <th class="p-12 text-center">Statut</th>
                        <th class="p-12 text-right">Action</th>
                    </tr>
                </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr><td colspan="4" class="p-20 text-center text-muted">Aucun inventaire. Créez-en un pour commencer !</td></tr>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                    <tr class="border-b border-border hover:bg-hover transition-colors">
                        <td class="p-12 font-bold">
                            <a href="index.php?page=inventory_view&id=<?= $session['id'] ?>" class="text-primary no-underline hover:text-primary-dark">
                                <?= htmlspecialchars($session['name']) ?>
                            </a>
                        </td>
                        <td class="p-12 text-muted"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></td>
                        <td class="p-12 text-center">
                            <?php if ($session['status'] === 'OPEN'): ?>
                                <span class="badge badge-success">OUVERT</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">CLÔTURÉ</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-12 text-right">
                            <a href="index.php?page=inventory_view&id=<?= $session['id'] ?>" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function createNewSession() {
    const name = prompt("Nom de l'inventaire (ex: Inventaire 2024) :");
    if (name) {
        const formData = new FormData();
        formData.append('action', 'create_session');
        formData.append('name', name);

        fetch('api/inventory_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert("Erreur: " + data.error);
            }
        });
    }
}
</script>
