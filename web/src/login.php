<?php
session_start();
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

// Récupérer les liens et téléchargements à afficher sur la page de login
$publicLinks = [];
$publicDownloads = [];

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
                grid-template-columns: 1fr 1fr;
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
            display: flex;
            flex-direction: column;
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
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            text-decoration: none;
            color: var(--accent-color-light, #4dabf7);
            font-size: 0.9em;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .link-item:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .link-desc {
            display: block;
            color: var(--text-color-muted, #888);
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        @media (max-width: 767px) {
            .login-wrapper {
                max-width: 450px;
            }
            
            .info-column {
                border-left: none;
                border-top: 1px solid var(--border-color, #333);
            }
        }
    </style>
</head>
<body class="dark">
    <?php 
       $hasInfo = !empty($publicDownloads) || !empty($publicLinks);
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
            <?php if (!empty($publicDownloads)): ?>
                <div class="info-header">Téléchargements</div>
                <div class="link-grid">
                    <?php foreach ($publicDownloads as $download): ?>
                        <a href="<?= htmlspecialchars($download['URL']) ?>" target="_blank" class="link-item">
                            <span>📥 <?= htmlspecialchars($download['NOM']) ?></span>
                            <?php if (!empty($download['DESCRIPTION'])): ?>
                                <span class="link-desc"><?= htmlspecialchars($download['DESCRIPTION']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($publicLinks)): ?>
                <div class="info-header">Liens Utiles</div>
                <div class="link-grid">
                    <?php foreach ($publicLinks as $link): ?>
                        <a href="<?= htmlspecialchars($link['URL']) ?>" target="_blank" class="link-item">
                            <span>🔗 <?= htmlspecialchars($link['NOM']) ?></span>
                            <?php if (!empty($link['DESCRIPTION'])): ?>
                                <span class="link-desc"><?= htmlspecialchars($link['DESCRIPTION']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
</body>
</html>
