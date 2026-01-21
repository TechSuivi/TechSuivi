<?php
// API pour récupérer et mettre à jour les données AutoIT
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Récupération de la clé API
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Clé API manquante'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Inclure la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    // S'assurer que la connexion utilise UTF-8
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Vérification de la clé API dans la table configuration
try {
    $stmt = $pdo->prepare("SELECT config_key, description FROM configuration WHERE config_value = ? AND config_key = 'api_key_autoit_client' AND category = 'api_keys' AND config_type = 'text'");
    $stmt->execute([$apiKey]);
    $validKey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$validKey) {
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => 'Clé API invalide'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Log de l'utilisation de la clé
    error_log("AutoIt API - Accès autorisé avec la clé: AutoIt Client Access");
    
} catch (Exception $e) {
    // Si la table configuration n'existe pas ou n'a pas de clés API, utiliser les clés par défaut en fallback
    $FALLBACK_API_KEYS = [
        'autoit_key_2025' => 'AutoIt Client Access (Fallback)',
        
    ];
    
    if (!array_key_exists($apiKey, $FALLBACK_API_KEYS)) {
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => 'Clé API invalide (mode fallback)'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    error_log("AutoIt API - Mode fallback activé, table configuration non disponible: " . $e->getMessage());
}

// Récupérer le type de données demandé et la méthode HTTP
$type = $_GET['type'] ?? $_POST['type'] ?? 'all';
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$defaut = $_GET['defaut'] ?? $_POST['defaut'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Gestion des opérations d'écriture (POST) - uniquement pour les interventions
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_intervention':
                // Mettre à jour les champs autorisés d'une intervention
                $result = updateIntervention($pdo, $_POST);
                break;
                
            case 'get_intervention':
                // Récupérer une intervention spécifique
                $result = getIntervention($pdo, $_POST['intervention_id']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Action non supportée']);
                exit();
        }
        
        echo json_encode([
            'success' => true,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $result
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Gestion des opérations de lecture (GET)
    switch ($type) {
        case 'logiciels':
            if ($id) {
                // Récupérer un logiciel spécifique
                $stmt = $pdo->prepare("SELECT * FROM autoit_logiciels WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => 'Logiciel non trouvé']);
                    exit();
                }
            } else {
                // Récupérer tous les logiciels
                $sql = "SELECT * FROM autoit_logiciels";
                if ($defaut) {
                    $sql .= " WHERE defaut = 1";
                }
                $sql .= " ORDER BY nom";
                
                $stmt = $pdo->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'commandes':
            if ($id) {
                // Récupérer une commande spécifique
                $stmt = $pdo->prepare("SELECT * FROM autoit_commandes WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => 'Commande non trouvée']);
                    exit();
                }
            } else {
                // Récupérer toutes les commandes
                $sql = "SELECT * FROM autoit_commandes";
                if ($defaut) {
                    $sql .= " WHERE defaut = 1";
                }
                $sql .= " ORDER BY nom";
                
                $stmt = $pdo->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'nettoyage':
            if ($id) {
                // Récupérer un outil de nettoyage spécifique
                $stmt = $pdo->prepare("SELECT * FROM autoit_nettoyage WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => 'Outil de nettoyage non trouvé']);
                    exit();
                }
            } else {
                // Récupérer tous les outils de nettoyage
                $sql = "SELECT * FROM autoit_nettoyage";
                if ($defaut) {
                    $sql .= " WHERE defaut = 1";
                }
                $sql .= " ORDER BY nom";
                
                $stmt = $pdo->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'personnalisation':
            if ($id) {
                // Récupérer une personnalisation spécifique
                $stmt = $pdo->prepare("SELECT * FROM autoit_personnalisation WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => 'Personnalisation non trouvée']);
                    exit();
                }
                
                // Nettoyer les données
                $result = cleanPersonnalisationData($result);
            } else {
                // Récupérer toutes les personnalisations
                $sql = "SELECT * FROM autoit_personnalisation";
                if ($defaut) {
                    $sql .= " WHERE defaut = 1";
                }
                $sql .= " ORDER BY nom";
                
                $stmt = $pdo->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Nettoyer les données pour chaque personnalisation
                $result = array_map('cleanPersonnalisationData', $result);
            }
            break;
            
        case 'intervention':
            // Récupérer une intervention spécifique
            if ($id) {
                $result = getIntervention($pdo, $id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'ID intervention requis']);
                exit();
            }
            break;
            
        case 'all':
        default:
            // Récupérer toutes les données
            $whereClause = $defaut ? " WHERE defaut = 1" : "";
            
            $logiciels = $pdo->query("SELECT * FROM autoit_logiciels" . $whereClause . " ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            $commandes = $pdo->query("SELECT * FROM autoit_commandes" . $whereClause . " ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            $nettoyage = $pdo->query("SELECT * FROM autoit_nettoyage" . $whereClause . " ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            $personnalisation = $pdo->query("SELECT * FROM autoit_personnalisation" . $whereClause . " ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            
            // Nettoyer les données de personnalisation
            $personnalisation = array_map('cleanPersonnalisationData', $personnalisation);
            
            $result = [
                'logiciels' => $logiciels,
                'commandes' => $commandes,
                'nettoyage' => $nettoyage,
                'personnalisation' => $personnalisation,
                'total_logiciels' => count($logiciels),
                'total_commandes' => count($commandes),
                'total_nettoyage' => count($nettoyage),
                'total_personnalisation' => count($personnalisation)
            ];
            break;
    }
    
    // Ajouter des métadonnées à la réponse
    $response = [
        'success' => true,
        'type' => $type,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $result
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// ===============================================
// Fonctions pour les interventions
// ===============================================

/**
 * Récupérer une intervention
 */
function getIntervention($pdo, $interventionId) {
    $stmt = $pdo->prepare("SELECT id, id_client, date, en_cours, statut_id, info, nettoyage, info_log, note_user, ip_vnc, pass_vnc FROM inter WHERE id = ?");
    $stmt->execute([$interventionId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('Intervention non trouvée');
    }
    
    return $result;
}

/**
 * Mettre à jour les champs autorisés d'une intervention
 */
function updateIntervention($pdo, $data) {
    // Créer un fichier de log pour le debug
    $logFile = '/tmp/intervention_update_debug.log';
    $logDir = dirname($logFile);
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("AutoIt API - Erreur création dossier logs: " . $logDir);
            // Continuer sans logging si impossible de créer le dossier
        }
    }
    
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'received_data' => $data,
        'steps' => []
    ];
    
    $interventionId = $data['intervention_id'] ?? null;
    $logData['steps'][] = "ID intervention reçu: " . ($interventionId ?: 'NULL');
    
    if (!$interventionId) {
        $logData['error'] = 'ID intervention requis';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        throw new Exception('ID intervention requis');
    }
    
    // Vérifier que l'intervention existe
    $stmt = $pdo->prepare("SELECT id FROM inter WHERE id = ?");
    $stmt->execute([$interventionId]);
    $existingIntervention = $stmt->fetch();
    $logData['steps'][] = "Vérification existence intervention: " . ($existingIntervention ? 'TROUVÉE' : 'NON TROUVÉE');
    
    if (!$existingIntervention) {
        $logData['error'] = 'Intervention non trouvée';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        throw new Exception('Intervention non trouvée');
    }
    
    // Construire la requête de mise à jour dynamiquement
    $updateFields = [];
    $updateValues = [];
    
    // Champs autorisés pour la mise à jour
    $allowedFields = ['nettoyage', 'info_log', 'note_user', 'ip_vnc', 'pass_vnc'];
    $logData['steps'][] = "Champs autorisés: " . implode(', ', $allowedFields);
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $data[$field];
            $logData['steps'][] = "Champ '$field' trouvé avec valeur: '" . $data[$field] . "'";
        } else {
            $logData['steps'][] = "Champ '$field' non présent dans les données";
        }
    }
    
    $logData['update_fields'] = $updateFields;
    $logData['update_values'] = $updateValues;
    
    if (empty($updateFields)) {
        $logData['error'] = 'Aucun champ valide à mettre à jour';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        throw new Exception('Aucun champ valide à mettre à jour');
    }
    
    // Ajouter l'ID à la fin pour la clause WHERE
    $updateValues[] = $interventionId;
    
    $sql = "UPDATE inter SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $logData['sql_query'] = $sql;
    $logData['final_values'] = $updateValues;
    
    try {
        $stmt = $pdo->prepare($sql);
        $logData['steps'][] = "Requête SQL préparée avec succès";
        
        $executeResult = $stmt->execute($updateValues);
        $logData['execute_result'] = $executeResult;
        $logData['steps'][] = "Exécution de la requête: " . ($executeResult ? 'SUCCÈS' : 'ÉCHEC');
        
        $rowCount = $stmt->rowCount();
        $logData['affected_rows'] = $rowCount;
        $logData['steps'][] = "Nombre de lignes affectées: $rowCount";
        
        if ($rowCount === 0) {
            $logData['warning'] = 'Aucune ligne affectée par la mise à jour';
        }
        
        // Vérifier l'état de l'intervention après mise à jour
        $updatedIntervention = getIntervention($pdo, $interventionId);
        $logData['updated_intervention'] = $updatedIntervention;
        $logData['steps'][] = "Intervention récupérée après mise à jour";
        
        // Écrire le log de succès
        $logData['status'] = 'SUCCESS';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        return $updatedIntervention;
        
    } catch (Exception $e) {
        $logData['error'] = 'Erreur lors de l\'exécution SQL: ' . $e->getMessage();
        $logData['status'] = 'ERROR';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        throw $e;
    }
}

/**
 * Nettoyer les données de personnalisation pour l'API
 */
function cleanPersonnalisationData($data) {
    if (!is_array($data)) {
        return $data;
    }
    
    // Nettoyer le champ ligne_registre
    if (isset($data['ligne_registre'])) {
        // FORCER la conversion des doubles backslashes en simples backslashes
        $data['ligne_registre'] = str_replace('\\\\', '\\', $data['ligne_registre']);
        
        // Gérer les séquences d'échappement pour les nouvelles lignes
        $data['ligne_registre'] = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $data['ligne_registre']);
        
        // Gérer les vraies nouvelles lignes aussi
        $data['ligne_registre'] = str_replace(["\r\n", "\r"], "\n", $data['ligne_registre']);
        
        // Nettoyer les espaces en fin de ligne mais préserver les nouvelles lignes
        $data['ligne_registre'] = rtrim($data['ligne_registre']);
        
        // Debug : afficher la valeur nettoyée
        error_log("Ligne registre nettoyée pour ID " . ($data['id'] ?? 'unknown') . ": " . $data['ligne_registre']);
    }
    
    // Nettoyer le champ description
    if (isset($data['description'])) {
        // FORCER la conversion des doubles backslashes en simples backslashes
        $data['description'] = str_replace('\\\\', '\\', $data['description']);
        
        // Gérer les séquences d'échappement pour les nouvelles lignes
        $data['description'] = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $data['description']);
        
        // Gérer les vraies nouvelles lignes aussi
        $data['description'] = str_replace(["\r\n", "\r"], "\n", $data['description']);
        
        // Nettoyer les espaces en fin de ligne mais préserver les nouvelles lignes
        $data['description'] = rtrim($data['description']);
    }
    
    return $data;
}
?>