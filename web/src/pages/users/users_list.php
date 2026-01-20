<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$users = [];
$errorMessage = '';
$sessionMessage = '';
$errorSessionMessage = '';

if (isset($_SESSION['user_message'])) {
    $sessionMessage = $_SESSION['user_message'];
    unset($_SESSION['user_message']);
}

if (isset($_SESSION['error_message'])) {
    $errorSessionMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des utilisateurs : " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = "Erreur de configuration : la connexion à la base de données n'est pas disponible.";
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<div class="max-w-1200 mx-auto px-20 pb-20">
    <div class="page-header flex-between-center mb-25">
        <h1 class="m-0 text-white flex items-center gap-10 text-xl font-medium"><span>👥</span> Gestion des Utilisateurs</h1>
        <button onclick="openAddUserModal()" class="btn btn-light text-primary font-bold shadow-sm hover:translate-y-px transition-all hover:shadow-md flex items-center gap-5">
            <span>➕</span> Ajouter un utilisateur
        </button>
    </div>

    <?php if (!empty($sessionMessage)): ?>
        <div class="alert alert-success mb-25 flex items-center gap-10">
            <span class="text-xl">✅</span>
            <?= $sessionMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorSessionMessage)): ?>
        <div class="alert alert-danger mb-25 flex items-center gap-10">
            <span class="text-xl">⚠️</span>
            <?= $errorSessionMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger mb-25 flex items-center gap-10">
            <span class="text-xl">⚠️</span>
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <?php if (empty($users) && empty($errorMessage)): ?>
        <div class="card p-40 text-center text-muted">
            <p>Aucun utilisateur trouvé.</p>
        </div>
    <?php elseif (!empty($users)): ?>
        <div class="card p-0 overflow-hidden border border-border shadow-sm rounded-lg">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-light border-b border-border text-left">
                        <th class="p-15 font-bold text-muted text-xs uppercase tracking-wider w-50">ID</th>
                        <th class="p-15 font-bold text-muted text-xs uppercase tracking-wider">Utilisateur</th>
                        <th class="p-15 font-bold text-muted text-xs uppercase tracking-wider">Email</th>
                        <th class="p-15 font-bold text-muted text-xs uppercase tracking-wider">Date de création</th>
                        <th class="p-15 font-bold text-muted text-xs uppercase tracking-wider w-120">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-hover transition-colors">
                            <td class="p-15 text-dark"><?= htmlspecialchars((string)($user['id'] ?? '')) ?></td>
                            <td class="p-15">
                                <div class="flex items-center gap-10 font-bold text-dark">
                                    <span class="w-36 h-36 rounded-full bg-primary text-white flex items-center justify-center text-sm shadow-sm opacity-90">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </span>
                                    <span><?= htmlspecialchars($user['username'] ?? '') ?></span>
                                </div>
                            </td>
                            <td class="p-15 text-muted text-sm"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td class="p-15 text-dark"><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                            <td class="p-15">
                                <div class="flex gap-10">
                                    <button onclick="openEditUserModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>')" 
                                            class="w-32 h-32 rounded flex items-center justify-center border-0 bg-transparent cursor-pointer hover:bg-hover hover:scale-110 transition-all text-lg" title="Modifier">
                                        ✏️
                                    </button>
                                    <?php if ($user['username'] !== $_SESSION['username']): ?>
                                        <a href="actions/user_delete.php?id=<?= htmlspecialchars((string)($user['id'])) ?>" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');" 
                                           class="w-32 h-32 rounded flex items-center justify-center border-0 bg-transparent cursor-pointer hover:bg-hover hover:scale-110 transition-all text-lg no-underline" title="Supprimer">
                                            🗑️
                                        </a>
                                    <?php else: ?>
                                        <span class="bg-info-light text-info px-10 py-5 rounded-full text-xs font-bold uppercase tracking-wider">Moi</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Ajouter Utilisateur -->
<div id="addUserModal" class="modal fixed inset-0 z-50 hidden bg-black-opacity overflow-auto backdrop-blur-sm">
    <div class="modal-content bg-white m-auto p-0 border-0 w-full max-w-500 rounded-lg shadow-lg relative top-5-percent animate-slideIn">
        <div class="modal-header p-20 bg-light border-b border-border flex-between-center">
            <h2 class="m-0 text-lg text-dark font-bold">Ajouter un utilisateur</h2>
            <span class="close text-2xl font-bold cursor-pointer hover:text-dark text-muted leading-none" onclick="closeAddUserModal()">&times;</span>
        </div>
        <form action="actions/user_add.php" method="post" class="p-25">
            <div class="mb-20">
                <label for="new_username" class="block mb-8 font-bold text-dark text-sm">Nom d'utilisateur <span class="text-danger">*</span></label>
                <input type="text" id="new_username" name="username" required placeholder="Ex: jean.dupont" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>

            <div class="mb-20">
                <label for="new_email" class="block mb-8 font-bold text-dark text-sm">Email</label>
                <input type="email" id="new_email" name="email" placeholder="Ex: jean.dupont@example.com" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="mb-20">
                <label for="new_password" class="block mb-8 font-bold text-dark text-sm">Mot de passe <span class="text-danger">*</span></label>
                <input type="password" id="new_password" name="password" required minlength="6" placeholder="Minimum 6 caractères" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="mb-20">
                <label for="confirm_password" class="block mb-8 font-bold text-dark text-sm">Confirmer le mot de passe <span class="text-danger">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="modal-footer p-20 bg-light border-t border-border text-right flex justify-end gap-10">
                <button type="button" class="btn btn-secondary font-medium" onclick="closeAddUserModal()">Annuler</button>
                <button type="submit" class="btn btn-primary font-bold">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier Utilisateur -->
<div id="editUserModal" class="modal fixed inset-0 z-50 hidden bg-black-opacity overflow-auto backdrop-blur-sm">
    <div class="modal-content bg-white m-auto p-0 border-0 w-full max-w-500 rounded-lg shadow-lg relative top-5-percent animate-slideIn">
        <div class="modal-header p-20 bg-light border-b border-border flex-between-center">
            <h2 class="m-0 text-lg text-dark font-bold">Modifier : <span id="edit_username_display" class="text-primary"></span></h2>
            <span class="close text-2xl font-bold cursor-pointer hover:text-dark text-muted leading-none" onclick="closeEditUserModal()">&times;</span>
        </div>
        <form action="actions/user_edit.php" method="post" class="p-25">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="mb-20">
                <label for="edit_email" class="block mb-8 font-bold text-dark text-sm">Email</label>
                <input type="email" id="edit_email" name="email" placeholder="Ex: jean.dupont@example.com" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="alert alert-info mb-20 text-sm">
                Laissez les champs mot de passe vides pour ne pas le modifier.
            </div>
            
            <div class="mb-20">
                <label for="edit_password" class="block mb-8 font-bold text-dark text-sm">Nouveau mot de passe</label>
                <input type="password" id="edit_password" name="password" minlength="6" placeholder="Minimum 6 caractères" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="mb-20">
                <label for="edit_confirm_password" class="block mb-8 font-bold text-dark text-sm">Confirmer le nouveau mot de passe</label>
                <input type="password" id="edit_confirm_password" name="confirm_password" minlength="6" class="w-full p-12 border-2 border-border rounded-lg bg-input text-dark focus:border-primary focus:outline-none transition-colors">
            </div>
            
            <div class="modal-footer p-20 bg-light border-t border-border text-right flex justify-end gap-10">
                <button type="button" class="btn btn-secondary font-medium" onclick="closeEditUserModal()">Annuler</button>
                <button type="submit" class="btn btn-primary font-bold">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functionality
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
    setTimeout(() => document.getElementById('new_username').focus(), 100);
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function openEditUserModal(id, username, email) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username_display').textContent = username;
    document.getElementById('edit_email').value = email; // Populate email
    document.getElementById('editUserModal').style.display = 'block';
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
}
</script>