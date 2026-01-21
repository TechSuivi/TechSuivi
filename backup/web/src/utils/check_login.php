<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Inclure la configuration centralisée de la base de données
    require_once __DIR__ . '/../config/database.php';

    try {
        // Connexion PDO avec gestion des exceptions
        $pdo = getDatabaseConnection();
    } catch (Exception $e) {
        die("Échec de la connexion : " . $e->getMessage());
    }

    // 4) Récupération et sanitation des données POST
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        header('Location: ../login.php?error=1');
        exit();
    }

    // 5) Préparation de la requête pour récupérer l’utilisateur
    $sql = "SELECT password_hash FROM users WHERE username = :username LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // 6) Authentification réussie
        $_SESSION['username'] = $username;
        header('Location: ../index.php');
        exit();
    }

    // 7) En cas d’échec (utilisateur non trouvé ou mot de passe incorrect)
    header('Location: ../login.php?error=1');
    exit();
}
?>
