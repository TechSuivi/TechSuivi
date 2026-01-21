<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username)) {
        $_SESSION['error_message'] = "Le nom d'utilisateur est requis.";
    } elseif (empty($password)) {
        $_SESSION['error_message'] = "Le mot de passe est requis.";
    } elseif (strlen($password) < 6) {
        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $pdo = getDatabaseConnection();
            
            // Vérifier si l'utilisateur existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Ce nom d'utilisateur existe déjà.";
            } else {
                // Créer l'utilisateur
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password_hash
                ]);
                
                $_SESSION['user_message'] = "Utilisateur '$username' créé avec succès.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erreur lors de la création de l'utilisateur : " . $e->getMessage();
        }
    }
}

// Redirection
header('Location: ../index.php?page=users_list');
exit();
?>
