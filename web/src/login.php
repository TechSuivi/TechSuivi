<?php
session_start();

// Vérifier si une installation est en cours
if (file_exists(__DIR__ . '/install_in_progress.lock')) {
    header('Location: installing.php');
    exit();
}

if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Variables pour la gestion des utilisateurs
$noUsersFound = false;
$firstUserCreated = false;
$createUserError = '';

// Traitement de la création du premier utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_first_user'])) {
    $username = trim($_POST['first_username'] ?? '');
    $email = trim($_POST['first_email'] ?? '');
    $password = trim($_POST['first_password'] ?? '');
    $confirmPassword = trim($_POST['first_password_confirm'] ?? '');
    
    if (empty($username) || empty($password)) {
        $createUserError = 'Le nom d\'utilisateur et le mot de passe sont obligatoires.';
    } elseif ($password !== $confirmPassword) {
        $createUserError = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $createUserError = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        try {
            $pdo = getDatabaseConnection();
            
            // Vérifier qu'il n'y a toujours aucun utilisateur
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $userCount = $stmt->fetchColumn();
            
            if ($userCount == 0) {
                // Créer le premier utilisateur
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $passwordHash])) {
                    $firstUserCreated = true;
                } else {
                    $createUserError = 'Erreur lors de la création de l\'utilisateur.';
                }
            } else {
                $createUserError = 'Un utilisateur existe déjà dans le système.';
            }
        } catch (Exception $e) {
            $createUserError = 'Erreur de base de données : ' . $e->getMessage();
            error_log("Erreur lors de la création du premier utilisateur : " . $e->getMessage());
        }
    }
}

// Récupérer les liens, téléchargements et notes à afficher sur la page de login
$publicLinks = [];
$publicDownloads = [];
$publicNotes = [];

try {
    $pdo = getDatabaseConnection();
    
    // Vérifier s'il y a des utilisateurs dans la base de données
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $userCount = $stmt->fetchColumn();
    $noUsersFound = ($userCount == 0);
    
    // Récupérer les liens publics
    $stmt = $pdo->prepare("SELECT NOM, DESCRIPTION, URL FROM liens WHERE show_on_login = 1 ORDER BY NOM ASC");
    $stmt->execute();
    $publicLinks = $stmt->fetchAll();
    
    // Récupérer les téléchargements publics
    $stmt = $pdo->prepare("SELECT NOM, DESCRIPTION, URL FROM download WHERE show_on_login = 1 ORDER BY NOM ASC");
    $stmt->execute();
    $publicDownloads = $stmt->fetchAll();

    // Récupérer les notes publiques
    $stmt = $pdo->prepare("SELECT titre, contenu, fichier_path, date_note FROM notes_globales WHERE show_on_login = 1 ORDER BY date_note DESC");
    $stmt->execute();
    $publicNotes = $stmt->fetchAll();
} catch (Exception $e) {
    // En cas d'erreur, continuer sans afficher les liens/téléchargements
    error_log("Erreur lors de la récupération des données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechSuivi - Connexion</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        body {
            background-color: var(--bg-color-dark, #121212);
            color: var(--text-color-dark, #eee);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-wrapper {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            background: var(--card-bg, #1e1e1e);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            border: 1px solid var(--border-color, #333);
        }

        @media (min-width: 768px) {
            .has-info-column {
                grid-template-columns: 400px 1fr;
                max-width: 1350px;
            }
        }

        /* Colonne Login */
        .login-column {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 600;
            color: var(--text-color, #fff);
            background: -webkit-linear-gradient(45deg, #4dabf7, #2A4C9C);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-header p {
            margin: 10px 0 0;
            opacity: 0.7;
            font-size: 0.9em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color, #ccc);
            font-size: 0.9em;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            background: var(--input-bg, #2b2b2b);
            border: 2px solid var(--border-color, #444);
            border-radius: 8px;
            color: var(--text-color, #eee);
            font-size: 1em;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: var(--accent-color, #2A4C9C);
            outline: none;
            box-shadow: 0 0 0 3px rgba(42, 76, 156, 0.2);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--accent-color, #2A4C9C);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .forgot-password {
            display: block;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
            font-size: 0.85em;
            color: var(--text-color-muted, #888);
            text-decoration: none;
        }
        
        .forgot-password:hover {
            color: var(--accent-color-light, #4dabf7);
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .alert-warning {
             background: rgba(255, 193, 7, 0.1);
             color: #ffc107;
             border: 1px solid rgba(255, 193, 7, 0.2);
        }

        /* Colonne Info */
        .info-column {
            background: rgba(255,255,255,0.03);
            border-left: 1px solid var(--border-color, #333);
            padding: 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            align-content: start;
        }
        
        @media (min-width: 1200px) {
            .info-column {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .info-section {
            display: flex;
            flex-direction: column;
        }

        /* Styles pour les notes extensibles */
        .note-content-wrapper {
            position: relative;
            max-height: 100px;
            overflow: hidden;
            text-align: left !important;
        }
        .note-content-wrapper.expanded {
            max-height: 1000px;
        }
        .btn-read-more {
            background: none;
            border: none;
            color: var(--accent-color-light, #4dabf7);
            font-size: 0.8em;
            cursor: pointer;
            padding: 5px 0;
            text-align: left;
            font-weight: 500;
        }
        .btn-read-more:hover {
            text-decoration: underline;
        }

        /* Modal Styles */
        .note-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .note-modal-content {
            background-color: var(--card-bg, #1e1e1e);
            margin: auto;
            padding: 30px;
            border: 1px solid var(--border-color, #333);
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5);
        }
        .note-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color, #333);
        }
        .note-modal-title {
            margin: 0;
            font-size: 1.4em;
            color: var(--text-color, #fff);
        }
        .note-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        .note-modal-close:hover {
            color: #fff;
        }
        .note-modal-body {
            color: var(--text-color, #eee);
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .info-header {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--text-color, #eee);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color, #333);
        }

        .link-grid {
            display: grid;
            gap: 10px;
            margin-bottom: 30px;
        }

        .link-item {
            display: block;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            text-decoration: none;
            color: var(--accent-color-light, #4dabf7);
            font-size: 0.9em;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: left; /* Force l'alignement à gauche */
        }

        .link-item:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .link-desc {
            display: block;
            color: var(--text-color-muted, #999);
            font-size: 0.85em;
            margin-top: 5px;
            text-align: left !important;
            width: 100%;
        }
        
        @media (max-width: 767px) {
            .login-wrapper {
                max-width: 450px;
            }
            
            .info-column {
                border-top: 1px solid var(--border-color, #333);
            }
        }
        
        .view-all-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--text-color-muted, #888);
            font-size: 0.85em;
            text-decoration: none;
            padding: 8px;
            border: 1px dashed var(--border-color, #444);
            border-radius: 6px;
            transition: all 0.2s;
        }
        .view-all-link:hover {
            background: rgba(255,255,255,0.05);
            color: var(--accent-color-light, #4dabf7);
            border-color: var(--accent-color-light, #4dabf7);
        }
        
        /* Attachment Button in Modal */
        .modal-attachment-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background: rgba(77, 171, 247, 0.1);
            color: #4dabf7;
            text-decoration: none;
            border-radius: 6px;
            border: 1px solid rgba(77, 171, 247, 0.3);
            transition: all 0.2s;
            margin-left: 10px;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .modal-attachment-btn:hover {
            background: rgba(77, 171, 247, 0.2);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="dark">
    <?php 
       $hasInfo = !empty($publicDownloads) || !empty($publicLinks) || !empty($publicNotes);
       $wrapperClass = $hasInfo ? 'login-wrapper has-info-column' : 'login-wrapper';
    ?>
    <div class="<?= $wrapperClass ?>">
        
        <!-- Colonne Gauche: Login -->
        <div class="login-column">
            <div class="login-header">
                <h1>TechSuivi</h1>
                <p>Connexion à votre espace</p>
            </div>
            
            <?php if ($firstUserCreated): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>Compte administrateur créé !<br>Vous pouvez vous connecter.</div>
                </div>
            <?php endif; ?>

            <?php if ($noUsersFound && !$firstUserCreated): ?>
                <div class="alert alert-warning">
                    <span>🚀</span>
                    <div>Bienvenue ! Créez votre premier compte administrateur.</div>
                </div>
                
                <?php if (!empty($createUserError)): ?>
                    <div class="alert alert-error">
                        <span>⚠️</span>
                        <?= htmlspecialchars($createUserError) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="create_first_user" value="1">
                    
                    <div class="form-group">
                        <label for="first_username">Nom d'utilisateur</label>
                        <input type="text" id="first_username" name="first_username" required placeholder="Ex: admin" value="<?= htmlspecialchars($_POST['first_username'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="first_email">Email</label>
                        <input type="email" id="first_email" name="first_email" placeholder="admin@example.com" value="<?= htmlspecialchars($_POST['first_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="first_password">Mot de passe</label>
                        <input type="password" id="first_password" name="first_password" required placeholder="Minimum 6 caractères">
                    </div>
                    
                    <div class="form-group">
                        <label for="first_password_confirm">Confirmer le mot de passe</label>
                        <input type="password" id="first_password_confirm" name="first_password_confirm" required>
                    </div>
                    
                    <button type="submit" class="btn-submit">Créer le compte</button>
                </form>

            <?php else: ?>
                <form action="utils/check_login.php" method="post">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" required placeholder="Identifiant">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required placeholder="Mot de passe">
                    </div>
                    
                    <a href="forgot_password.php" class="forgot-password">Mot de passe oublié ?</a>
                    
                    <button type="submit" class="btn-submit">Se connecter</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Colonne Droite: Info (si contenu) -->
        <?php if ($hasInfo): ?>
        <div class="info-column">
            <?php if (!empty($publicNotes)): ?>
                <div class="info-section">
                    <div class="info-header">Notes & Informations</div>
                    <div class="link-grid">
                        <?php foreach (array_slice($publicNotes, 0, 5) as $index => $note): ?>
                            <div class="link-item" onclick="showNote(<?= $index ?>)" style="cursor: pointer; display: flex; flex-direction: column; gap: 5px;">
                                <div style="font-weight: 600; color: var(--text-color); display: flex; justify-content: space-between; align-items: start;">
                                    <span>📓 <?= htmlspecialchars($note['titre']) ?></span>
                                    <span style="font-size: 0.75em; opacity: 0.5; font-weight: normal;"><?= date('d/m/Y', strtotime($note['date_note'])) ?></span>
                                </div>
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.85em; color: var(--text-color-muted, #999); opacity: 0.9;">
                                    <?= htmlspecialchars(strip_tags($note['contenu'])) ?>
                                </div>
                                <?php if ($note['fichier_path']): ?>
                                    <div class="text-xs" style="color: var(--accent-color-light); opacity: 0.8; font-size: 0.8em;">📎 Pièce jointe incluse</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($publicNotes) > 5): ?>
                            <a href="public_info.php" class="view-all-link">Voir toutes les notes (<?= count($publicNotes) ?>)</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($publicDownloads)): ?>
                <div class="info-section">
                    <div class="info-header">Téléchargements</div>
                    <div class="link-grid">
                        <?php foreach (array_slice($publicDownloads, 0, 5) as $download): ?>
                            <a href="<?= htmlspecialchars($download['URL']) ?>" target="_blank" class="link-item">
                                <span>📥 <?= htmlspecialchars($download['NOM']) ?></span>
                                <?php if (!empty($download['DESCRIPTION'])): ?>
                                    <span class="link-desc"><?= htmlspecialchars($download['DESCRIPTION']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($publicDownloads) > 5): ?>
                            <a href="public_info.php" class="view-all-link">Voir tous les téléchargements (<?= count($publicDownloads) ?>)</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($publicLinks)): ?>
                <div class="info-section">
                    <div class="info-header">Liens Utiles</div>
                    <div class="link-grid">
                        <?php foreach (array_slice($publicLinks, 0, 5) as $link): ?>
                            <a href="<?= htmlspecialchars($link['URL']) ?>" target="_blank" class="link-item">
                                <span>🔗 <?= htmlspecialchars($link['NOM']) ?></span>
                                <?php if (!empty($link['DESCRIPTION'])): ?>
                                    <span class="link-desc"><?= htmlspecialchars($link['DESCRIPTION']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($publicLinks) > 5): ?>
                            <a href="public_info.php" class="view-all-link">Voir tous les liens (<?= count($publicLinks) ?>)</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- Note Modal -->
    <div id="publicNoteModal" class="note-modal" onclick="closeNoteModal(event)">
        <div class="note-modal-content" onclick="event.stopPropagation()">
            <div class="note-modal-header">
                <h3 class="note-modal-title" id="noteModalTitle">📓 Note</h3>
                <span class="note-modal-close" onclick="closeNoteModal()">&times;</span>
            </div>
            <div class="note-modal-body" id="noteModalBody"></div>
            <div id="noteModalFooter" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color, #333); font-size: 0.85em; opacity: 0.7; text-align: right;"></div>
        </div>
    </div>

    <script>
        const publicNotes = <?= json_encode($publicNotes) ?>;

        function showNote(index) {
            const note = publicNotes[index];
            document.getElementById('noteModalTitle').textContent = '📓 ' + note.titre;
            document.getElementById('noteModalBody').textContent = note.contenu;
            
            let footerHtml = '<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">';
            footerHtml += '<span>Posté le ' + new Date(note.date_note).toLocaleDateString('fr-FR') + '</span>';
            
            if (note.fichier_path) {
                // Bouton plus visible pour la pièce jointe
                footerHtml += '<a href="' + note.fichier_path + '" target="_blank" class="modal-attachment-btn">📎 Télécharger la pièce jointe</a>';
            }
            footerHtml += '</div>';
            document.getElementById('noteModalFooter').innerHTML = footerHtml;
            
            document.getElementById('publicNoteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Empêcher le scroll
        }

        function closeNoteModal(event) {
            document.getElementById('publicNoteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

    </script>
</body>
</html>
