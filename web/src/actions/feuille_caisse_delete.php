<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<script>window.location.href = "index.php?page=feuille_caisse_list&error=missing_id";</script>';
    exit();
}

$id = (int)$_GET['id'];

try {
    // Vérifier que la feuille existe
    $stmt = $pdo->prepare("SELECT id, date_comptage FROM FC_feuille_caisse WHERE id = ?");
    $stmt->execute([$id]);
    $feuille = $stmt->fetch();
    
    if (!$feuille) {
        echo '<script>window.location.href = "index.php?page=feuille_caisse_list&error=not_found";</script>';
        exit();
    }
    
    // Supprimer la feuille de caisse
    $stmt = $pdo->prepare("DELETE FROM FC_feuille_caisse WHERE id = ?");
    $stmt->execute([$id]);
    
    // Redirection avec message de succès
    echo '<script>window.location.href = "index.php?page=feuille_caisse_list&success=deleted";</script>';
    exit();
    
} catch (PDOException $e) {
    // Redirection avec message d'erreur
    echo '<script>window.location.href = "index.php?page=feuille_caisse_list&error=delete_failed";</script>';
    exit();
}
?>