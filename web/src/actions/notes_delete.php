<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Session expirée']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Optionnel : Supprimer le fichier physiquement
    $stmtFile = $pdo->prepare("SELECT fichier_path FROM notes_globales WHERE id = :id");
    $stmtFile->execute(['id' => $id]);
    $filePath = $stmtFile->fetchColumn();
    
    if ($filePath && file_exists(__DIR__ . '/../' . $filePath)) {
        unlink(__DIR__ . '/../' . $filePath);
    }

    $stmt = $pdo->prepare("DELETE FROM notes_globales WHERE id = :id");
    if ($stmt->execute(['id' => $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
