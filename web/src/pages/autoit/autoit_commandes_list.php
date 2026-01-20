<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Traitement des actions (ajout, modification, suppression)
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO autoit_commandes (nom, commande, description, defaut) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['commande'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Commande ajoutée avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_commandes SET nom=?, commande=?, description=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['commande'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Commande modifiée avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM autoit_commandes WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Commande supprimée avec succès !";
                break;
        }
    }
}

// Récupération des commandes
$stmt = $pdo->query("SELECT * FROM autoit_commandes ORDER BY nom");
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'une commande pour édition
$editCommande = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_commandes WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCommande = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container container-center max-w-1600">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-20 items-start">
        
        <!-- Colonne de gauche : Formulaire -->
        <div class="lg:col-span-1 card bg-secondary border p-20 sticky top-20">
            <?php if (isset($success)): ?>
                <div class="alert alert-success mb-20"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <h2 class="mt-0 mb-20 text-lg border-b border-border pb-10">
                <?= $editCommande ? 'Modifier la commande' : 'Ajouter une commande' ?>
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editCommande ? 'edit' : 'add' ?>">
                <?php if ($editCommande): ?>
                    <input type="hidden" name="id" value="<?= $editCommande['id'] ?>">
                <?php endif; ?>
                
                <div class="mb-15">
                    <label for="nom" class="block mb-5 font-bold">Nom de la commande :</label>
                    <input type="text" id="nom" name="nom" class="form-control w-full p-8 border rounded bg-input text-dark" required value="<?= htmlspecialchars($editCommande['nom'] ?? '') ?>">
                </div>
                
                <div class="mb-15">
                    <label for="commande" class="block mb-5 font-bold">Commande :</label>
                    <textarea id="commande" name="commande" rows="4" class="form-control w-full p-8 border rounded bg-input text-dark font-mono text-sm" required placeholder="ex: powershell -Command ..."><?= htmlspecialchars($editCommande['commande'] ?? '') ?></textarea>
                </div>
                
                <div class="mb-15">
                    <label for="description" class="block mb-5 font-bold">Description :</label>
                    <textarea id="description" name="description" rows="3" class="form-control w-full p-8 border rounded bg-input text-dark" placeholder="Description de ce que fait cette commande"><?= htmlspecialchars($editCommande['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-20">
                    <label class="flex items-center gap-10 cursor-pointer">
                        <input type="checkbox" name="defaut" value="1" <?= ($editCommande['defaut'] ?? 0) ? 'checked' : '' ?>>
                        <span>Commande par défaut</span>
                    </label>
                </div>
                
                <div class="flex flex-wrap gap-10">
                    <button type="submit" class="btn btn-primary flex-1"><?= $editCommande ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editCommande): ?>
                        <a href="index.php?page=autoit_commandes_list" class="btn btn-secondary flex-1 text-center">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Colonne de droite : Liste -->
        <div class="lg:col-span-3 card bg-secondary border p-20">
            <h2 class="mt-0 mb-20 text-lg border-b border-border pb-10">Liste des commandes</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-left border-b border-border text-muted uppercase text-xs">
                            <th class="p-10">Nom</th>
                            <th class="p-10">Commande</th>
                            <th class="p-10">Description</th>
                            <th class="p-10 text-center">Défaut</th>
                            <th class="p-10 text-right" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-10 align-top font-bold"><?= htmlspecialchars($commande['nom']) ?></td>
                            <td class="p-10 align-top">
                                <div class="flex flex-wrap gap-5 items-center">
                                    <code class="bg-light px-5 py-2 rounded text-xs font-mono break-all"><?= htmlspecialchars(substr($commande['commande'], 0, 50)) ?><?= strlen($commande['commande']) > 50 ? '...' : '' ?></code>
                                    <button class="btn btn-xs btn-info" onclick="showFullCommand(<?= $commande['id'] ?>)">Voir complet</button>
                                </div>
                            </td>
                            <td class="p-10 align-top">
                                <?php if ($commande['description']): ?>
                                    <div class="text-sm text-muted"><?= htmlspecialchars($commande['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top text-center">
                                <?php if ($commande['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary opacity-50">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top text-right whitespace-nowrap">
                                <a href="index.php?page=autoit_commandes_list&edit=<?= $commande['id'] ?>" class="btn btn-xs btn-primary p-5" title="Modifier">✏️</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $commande['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger bg-transparent text-danger p-5 hover:scale-110" title="Supprimer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Ligne cachée pour afficher la commande complète -->
                        <tr id="full-command-<?= $commande['id'] ?>" class="hidden bg-light">
                            <td colspan="5" class="p-15 border-b border-border">
                                <div class="bg-card border border-border rounded p-15">
                                    <strong class="block mb-5">Commande complète :</strong>
                                    <pre class="bg-dark text-light p-10 rounded text-sm overflow-x-auto font-mono whitespace-pre-wrap select-all mb-10 command-full"><?= htmlspecialchars($commande['commande']) ?></pre>
                                    <div class="flex gap-10">
                                        <button class="btn btn-sm btn-secondary" onclick="hideFullCommand(<?= $commande['id'] ?>)">Masquer</button>
                                        <button class="btn btn-sm btn-success" onclick="copyCommand(<?= $commande['id'] ?>, this)">Copier</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showFullCommand(id) {
    document.getElementById('full-command-' + id).classList.remove('hidden');
}

function hideFullCommand(id) {
    document.getElementById('full-command-' + id).classList.add('hidden');
}

function copyCommand(id, btn) {
    const commandElement = document.querySelector('#full-command-' + id + ' .command-full');
    const text = commandElement.textContent;
    
    navigator.clipboard.writeText(text).then(function() {
        const originalText = btn.innerHTML;
        const originalClass = btn.className;
        
        btn.innerHTML = 'Copié !';
        
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.className = originalClass;
        }, 2000);
    }).catch(function(err) {
        console.error('Erreur lors de la copie: ', err);
        alert('Erreur lors de la copie dans le presse-papiers');
    });
}
</script>