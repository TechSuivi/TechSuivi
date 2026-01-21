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

<div class="container" style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📦 Inventaires Physiques</h1>
        <button onclick="createNewSession()" style="padding: 10px 20px; background-color: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            + Nouvel Inventaire
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="background: var(--card-bg); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; color: var(--text-color);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: rgba(0,0,0,0.05); border-bottom: 2px solid var(--border-color);">
                <tr>
                    <th style="padding: 12px; text-align: left; color: var(--text-color);">Nom</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-color);">Date Création</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-color);">Statut</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-color);">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr><td colspan="4" style="padding: 20px; text-align: center; color: var(--text-muted);">Aucun inventaire. Créez-en un pour commencer !</td></tr>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td style="padding: 12px; font-weight: bold;">
                            <a href="index.php?page=inventory_view&id=<?= $session['id'] ?>" style="text-decoration: none; color: #2196f3;">
                                <?= htmlspecialchars($session['name']) ?>
                            </a>
                        </td>
                        <td style="padding: 12px; color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <?php if ($session['status'] === 'OPEN'): ?>
                                <span style="background-color: rgba(46, 125, 50, 0.2); color: #4caf50; padding: 4px 8px; border-radius: 12px; font-size: 0.8em;">OUVERT</span>
                            <?php else: ?>
                                <span style="background-color: rgba(69, 90, 100, 0.2); color: #90a4ae; padding: 4px 8px; border-radius: 12px; font-size: 0.8em;">CLÔTURÉ</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <a href="index.php?page=inventory_view&id=<?= $session['id'] ?>" style="padding: 6px 12px; background: rgba(33, 150, 243, 0.1); color: #2196f3; text-decoration: none; border-radius: 4px;">Ouvrir</a>
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
