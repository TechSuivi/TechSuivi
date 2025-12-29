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
?>

<?php
// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<style>
/* Styles inspirés de helpdesk_categories */
.users-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #2A4C9C 0%, #1a3a7a 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: none; /* Override default */
}

.page-header h1 {
    margin: 0;
    font-size: 1.6em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

/* Alert styles */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid transparent;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.alert-info {
    background-color: #cce5ff;
    color: #004085;
    border-color: #b8daff;
}

.alert-icon {
    font-size: 1.4em;
}

/* Button styles */
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 0.95em;
}

.btn-primary {
    background: white;
    color: var(--accent-color);
    font-weight: 600;
}

.btn-primary:hover {
    background: #f0f0f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-modal-primary {
    background: var(--accent-color);
    color: white;
}

.btn-modal-primary:hover {
    opacity: 0.9;
}

.btn-secondary {
    background: #e9ecef;
    color: var(--text-color);
}

.btn-secondary:hover {
    background: #dde2e6;
}

/* Table styles */
.table-responsive {
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid var(--border-color, #eee);
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    font-size: 0.85em;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e9ecef;
}

.users-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
    color: var(--text-color);
}

.users-table tr:last-child td {
    border-bottom: none;
}

.users-table tr:hover {
    background-color: #f8f9fa;
}

/* Cell specific styles */
.username-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

.avatar-placeholder {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #6c5ce7, #a29bfe);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(108, 92, 231, 0.3);
}

.email-text {
    color: #6c757d;
    font-size: 0.95em;
}

.actions-cell {
    display: flex;
    gap: 10px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1.1em;
}

.btn-icon:hover {
    background-color: #e9ecef;
    transform: scale(1.1);
}

.current-user-badge {
    background-color: #e3f2fd;
    color: #0d47a1;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Modal Styles */
.modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.5); 
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: var(--card-bg, #fff);
    margin: 5% auto; 
    padding: 0;
    border: none;
    width: 100%;
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    animation: slideIn 0.3s;
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.2em;
    color: var(--text-color);
}

.close {
    color: #adb5bd;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
    line-height: 1;
}

.close:hover {
    color: var(--text-color);
}

.modal-form {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 0.9em;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    transition: all 0.2s;
    background: var(--input-bg, #fff);
    color: var(--text-color);
}

.form-group input:focus {
    border-color: var(--accent-color);
    outline: none;
    box-shadow: 0 0 0 4px rgba(42, 79, 156, 0.1);
}

.required {
    color: #dc3545;
}

.modal-footer {
    padding: 20px 25px;
    background-color: #f8f9fa;
    border-top: 1px solid #eee;
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

@keyframes fadeIn {
    from {opacity: 0} 
    to {opacity: 1}
}

@keyframes slideIn {
    from {transform: translateY(-30px); opacity: 0;} 
    to {transform: translateY(0); opacity: 1;}
}

/* Dark mode overrides */
body.dark .page-header {
    /* Garder le gradient ou adapter */
}

body.dark .users-table th {
    background-color: #333;
    color: #ddd;
    border-bottom-color: #444;
}

body.dark .users-table td {
    border-bottom-color: #444;
    color: #eee;
}

body.dark .users-table tr:hover {
    background-color: #333;
}

body.dark .btn-icon:hover {
    background-color: #444;
}

body.dark .email-text {
    color: #aaa;
}

body.dark .modal-header,
body.dark .modal-footer {
    background-color: #333;
    border-color: #444;
}

body.dark .form-group input {
    background-color: #2b2b2b;
    border-color: #444;
}
</style>

<div class="users-container">
    <div class="page-header">
        <h1><span>👥</span> Gestion des Utilisateurs</h1>
        <button onclick="openAddUserModal()" class="btn btn-primary">
            <span class="icon">➕</span> Ajouter un utilisateur
        </button>
    </div>

    <?php if (!empty($sessionMessage)): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✅</span>
            <?= $sessionMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorSessionMessage)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠️</span>
            <?= $errorSessionMessage ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠️</span>
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <?php if (empty($users) && empty($errorMessage)): ?>
        <div class="empty-state">
            <p>Aucun utilisateur trouvé.</p>
        </div>
    <?php elseif (!empty($users)): ?>
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Date de création</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($user['id'] ?? '')) ?></td>
                            <td class="username-cell">
                                <span class="avatar-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                                <span class="username-text"><?= htmlspecialchars($user['username'] ?? '') ?></span>
                            </td>
                            <td><span class="email-text"><?= htmlspecialchars($user['email'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                            <td class="actions-cell">
                                <button onclick="openEditUserModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>')" class="btn-icon header-btn" title="Modifier">✏️</button>
                                <?php if ($user['username'] !== $_SESSION['username']): ?>
                                    <a href="actions/user_delete.php?id=<?= htmlspecialchars((string)($user['id'])) ?>" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');" 
                                       class="btn-icon delete" title="Supprimer">🗑️</a>
                                <?php else: ?>
                                    <span class="current-user-badge" title="Utilisateur actuel">Moi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Ajouter Utilisateur -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un utilisateur</h2>
            <span class="close" onclick="closeAddUserModal()">&times;</span>
        </div>
        <form action="actions/user_add.php" method="post" class="modal-form">
            <div class="form-group">
                <label for="new_username">Nom d'utilisateur <span class="required">*</span></label>
                <input type="text" id="new_username" name="username" required placeholder="Ex: jean.dupont">
            </div>

            <div class="form-group">
                <label for="new_email">Email</label>
                <input type="email" id="new_email" name="email" placeholder="Ex: jean.dupont@example.com">
            </div>
            
            <div class="form-group">
                <label for="new_password">Mot de passe <span class="required">*</span></label>
                <input type="password" id="new_password" name="password" required minlength="6" placeholder="Minimum 6 caractères">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Annuler</button>
                <button type="submit" class="btn btn-modal-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier Utilisateur -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier : <span id="edit_username_display"></span></h2>
            <span class="close" onclick="closeEditUserModal()">&times;</span>
        </div>
        <form action="actions/user_edit.php" method="post" class="modal-form">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-group">
                <label for="edit_email">Email</label>
                <input type="email" id="edit_email" name="email" placeholder="Ex: jean.dupont@example.com">
            </div>
            
            <div class="alert alert-info">
                Laissez les champs mot de passe vides pour ne pas le modifier.
            </div>
            
            <div class="form-group">
                <label for="edit_password">Nouveau mot de passe</label>
                <input type="password" id="edit_password" name="password" minlength="6" placeholder="Minimum 6 caractères">
            </div>
            
            <div class="form-group">
                <label for="edit_confirm_password">Confirmer le nouveau mot de passe</label>
                <input type="password" id="edit_confirm_password" name="confirm_password" minlength="6">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Annuler</button>
                <button type="submit" class="btn btn-modal-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functionality
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
    document.getElementById('new_username').focus();
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