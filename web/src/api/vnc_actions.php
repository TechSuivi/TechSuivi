<?php
// API pour les actions VNC (désactivation, activation, etc.)

// Inclure la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit();
}

// Récupérer l'action et les données
$action = $_POST['action'] ?? '';
$interventionId = $_POST['intervention_id'] ?? '';

if (empty($interventionId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID d\'intervention manquant'
    ]);
    exit();
}

try {
    switch ($action) {
        case 'disable_vnc':
            // Désactiver le VNC en supprimant les informations ip_vnc et pass_vnc
            $stmt = $pdo->prepare("UPDATE inter SET ip_vnc = NULL, pass_vnc = NULL WHERE id = ?");
            $stmt->execute([$interventionId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'VNC désactivé avec succès'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Intervention non trouvée ou VNC déjà désactivé'
                ]);
            }
            break;
            
        case 'enable_vnc':
            // Activer le VNC en enregistrant l'IP et le mot de passe
            $ipVnc = $_POST['ip_vnc'] ?? '';
            $passVnc = $_POST['pass_vnc'] ?? '';
            
            if (empty($ipVnc)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Adresse IP VNC requise'
                ]);
                break;
            }
            
            $stmt = $pdo->prepare("UPDATE inter SET ip_vnc = ?, pass_vnc = ? WHERE id = ?");
            $stmt->execute([$ipVnc, $passVnc, $interventionId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'VNC activé avec succès'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Intervention non trouvée'
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'exécution: ' . $e->getMessage()
    ]);
}
?>
