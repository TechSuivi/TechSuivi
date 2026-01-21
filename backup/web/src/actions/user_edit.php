<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($id)) {
        $_SESSION['error_message'] = "Identifiant utilisateur manquant.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $_SESSION['error_message'] = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $pdo = getDatabaseConnection();
            $params = ['email' => $email, 'id' => $id];
            $sql = "UPDATE users SET email = :email";

            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password_hash = :password_hash";
                $params['password_hash'] = $password_hash;
            }

            $sql .= " WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['user_message'] = "Utilisateur modifié avec succès.";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erreur lors de la modification de l'utilisateur : " . $e->getMessage();
        }
    }
}

// Redirection
header('Location: ../index.php?page=users_list');
exit();
?>
