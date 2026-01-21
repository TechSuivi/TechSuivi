<?php
/**
 * API pour la gestion des configurations de rapports automatiques
 * Gère les opérations CRUD pour les rapports configurés
 */

// Vérifier que le script est inclus correctement
if (!defined('TECHSUIVI_INCLUDED')) {
    define('TECHSUIVI_INCLUDED', true);
}

// Désactiver l'affichage des erreurs pour ne pas polluer la réponse JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// S'assurer qu'un buffer est actif pour capturer les éventuels output inattendus (includes, etc)
if (ob_get_level() == 0) {
    ob_start();
}

// Headers pour l'API JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Nettoyer le buffer de sortie pour éviter toute pollution avant le JSON
// On utilise ob_get_length() pour vérifier s'il y a quelque chose à nettoyer
if (ob_get_length()) {
    ob_clean();
}

// Vérifier la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

// Inclure la configuration de base de données
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/mail_helper.php';
require_once __DIR__ . '/../utils/report_generator.php';

try {
    $pdo = getDatabaseConnection();
    
    // Gérer les données JSON si présentes
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if ($jsonData && is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
    }

    // Récupérer l'action demandée
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'list':
            handleListReports($pdo);
            break;
            
        case 'get':
            handleGetReport($pdo);
            break;
            
        case 'create':
            handleCreateReport($pdo);
            break;
            
        case 'update':
            handleUpdateReport($pdo);
            break;
            
        case 'delete':
            handleDeleteReport($pdo);
            break;
            
        case 'toggle':
            handleToggleReport($pdo);
            break;

        case 'preview':
            handleGeneratePreview($pdo);
            break;

        case 'get_statuses':
            handleGetStatuses($pdo);
            break;
            
        case 'preview':
            handlePreviewReport($pdo);
            break;
            
        case 'test':
            handleTestReport($pdo);
            break;
            
        case 'get_stats':
            handleGetStats($pdo);
            break;

        case 'check_schema':
            handleCheckSchema($pdo);
            break;
            
        default:
            throw new Exception('Action non reconnue: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

/**
 * Lister toutes les configurations de rapports
 */
function handleListReports($pdo) {
    try {
        $stmt = $pdo->query('
            SELECT
                id,
                name,
                description,
                report_type,
                parameters,
                content_template,
                is_active,
                created_at,
                updated_at
            FROM report_templates
            ORDER BY created_at DESC
        ');
        
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données pour l'affichage
        foreach ($reports as &$report) {
            $report['parameters_array'] = json_decode($report['parameters'], true) ?: [];
            $report['created_at_formatted'] = date('d/m/Y H:i', strtotime($report['created_at']));
            $report['report_type_label'] = getReportTypeLabel($report['report_type']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'count' => count($reports)
        ]);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la récupération des rapports: ' . $e->getMessage());
    }
}

/**
 * Récupérer une configuration de rapport spécifique
 */
function handleGetReport($pdo) {
    $id = $_POST['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID de rapport invalide');
    }
    
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM report_templates
            WHERE id = ?
        ');
        $stmt->execute([$id]);
        
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            throw new Exception('Rapport non trouvé');
        }
        
        // Décoder les données JSON
        $report['parameters_array'] = json_decode($report['parameters'], true) ?: [];
        
        echo json_encode([
            'success' => true,
            'data' => $report
        ]);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la récupération du rapport: ' . $e->getMessage());
    }
}

/**
 * Créer une nouvelle configuration de rapport
 */
function handleCreateReport($pdo) {
    // Validation des champs requis
    $requiredFields = ['name', 'report_type'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est requis");
        }
    }
    
    try {
        // Préparer les données
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $report_type = $_POST['report_type'];
        $parameters = $_POST['parameters'] ?? '{}';
        $content_template = $_POST['content_template'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Valider le type de rapport
        // Valider le type de rapport
        $validTypes = ['interventions', 'messages', 'agenda', 'resume_caisse', 'mixed'];
        $requestedTypes = explode(',', $report_type);
        foreach ($requestedTypes as $type) {
            if (!in_array(trim($type), $validTypes)) {
                throw new Exception('Type de rapport invalide: ' . $type);
            }
        }
        
        // Valider les paramètres JSON
        if (is_string($parameters)) {
            $parametersArray = json_decode($parameters, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide pour les paramètres');
            }
        }
        
        // Insérer en base de données
        $stmt = $pdo->prepare('
            INSERT INTO report_templates
            (name, description, report_type, parameters, content_template, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        
        $stmt->execute([
            $name,
            $description,
            $report_type,
            $parameters,
            $content_template,
            $is_active
        ]);
        
        $reportId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template de rapport créé avec succès',
            'data' => ['id' => $reportId]
        ]);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la création du rapport: ' . $e->getMessage());
    }
}

/**
 * Mettre à jour une configuration de rapport
 */
function handleUpdateReport($pdo) {
    $id = $_POST['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID de rapport invalide');
    }
    
    // Validation des champs requis
    $requiredFields = ['name', 'report_type'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est requis");
        }
    }
    
    try {
        // Vérifier que le rapport existe
        $stmt = $pdo->prepare('SELECT id FROM report_templates WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new Exception('Template non trouvé');
        }
        
        // Préparer les données
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $report_type = $_POST['report_type'];
        $parameters = $_POST['parameters'] ?? '{}';
        $content_template = $_POST['content_template'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Valider le type de rapport
        // Valider le type de rapport
        $validTypes = ['interventions', 'messages', 'agenda', 'resume_caisse', 'mixed'];
        $requestedTypes = explode(',', $report_type);
        foreach ($requestedTypes as $type) {
            if (!in_array(trim($type), $validTypes)) {
                throw new Exception('Type de rapport invalide: ' . $type);
            }
        }
        
        // Valider les paramètres JSON
        if (is_string($parameters)) {
            $parametersArray = json_decode($parameters, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide pour les paramètres');
            }
        }
        
        // Mettre à jour en base de données
        $stmt = $pdo->prepare('
            UPDATE report_templates
            SET name = ?, description = ?, report_type = ?, parameters = ?,
                content_template = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            $name,
            $description,
            $report_type,
            $parameters,
            $content_template,
            $is_active,
            $id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template de rapport mis à jour avec succès'
        ]);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la mise à jour du rapport: ' . $e->getMessage());
    }
}

/**
 * Supprimer une configuration de rapport
 */
function handleDeleteReport($pdo) {
    $id = $_POST['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID de rapport invalide');
    }
    
    try {
        // Vérifier que le rapport existe
        $stmt = $pdo->prepare('SELECT name FROM report_templates WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        
        if (!$report) {
            throw new Exception('Template non trouvé');
        }
        
        // Supprimer le rapport
        $stmt = $pdo->prepare('DELETE FROM report_templates WHERE id = ?');
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => "Template de rapport '{$report['name']}' supprimé avec succès"
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la suppression du rapport: ' . $e->getMessage());
    }
}

/**
 * Activer/désactiver une configuration de rapport
 */
function handleToggleReport($pdo) {
    $id = $_POST['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID de rapport invalide');
    }
    
    try {
        // Récupérer l'état actuel
        $stmt = $pdo->prepare('SELECT name, is_active FROM report_templates WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        
        if (!$report) {
            throw new Exception('Template non trouvé');
        }
        
        // Inverser l'état
        $newStatus = !$report['is_active'];
        
        $stmt = $pdo->prepare('UPDATE report_templates SET is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newStatus, $id]);
        
        $statusText = $newStatus ? 'activé' : 'désactivé';
        
        echo json_encode([
            'success' => true,
            'message' => "Template '{$report['name']}' $statusText avec succès",
            'new_status' => $newStatus
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors du changement de statut: ' . $e->getMessage());
    }
}

/**
 * Prévisualiser un rapport
 */
function handlePreviewReport($pdo) {
    $report_type = $_POST['report_type'] ?? null;
    $parameters = $_POST['parameters'] ?? '{}';
    
    if (!$report_type) {
        throw new Exception('Type de rapport requis pour la prévisualisation');
    }
    
    try {
        $parametersArray = json_decode($parameters, true) ?: [];
        $previewData = generateReportPreview($pdo, $report_type, $parametersArray);
        
        echo json_encode([
            'success' => true,
            'data' => $previewData
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la prévisualisation: ' . $e->getMessage());
    }
}

/**
 * Tester l'envoi d'un rapport
 */
function handleTestReport($pdo) {
    $id = $_POST['id'] ?? null;
    $test_email = $_POST['test_email'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID de rapport invalide');
    }
    
    if (!$test_email || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Adresse email de test invalide');
    }
    
    try {
        // Récupérer la configuration du rapport
        $stmt = $pdo->prepare('SELECT * FROM report_templates WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            throw new Exception('Template non trouvé');
        }
        
        // Générer et envoyer le rapport de test
        $testResult = sendTestReport($pdo, $report, $test_email);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rapport de test envoyé avec succès',
            'data' => $testResult
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de l\'envoi du test: ' . $e->getMessage());
    }
}

/**
 * Récupérer les statistiques pour le dashboard
 */
function handleGetStats($pdo) {
    try {
        // Statistiques des interventions
        $stmt = $pdo->query('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN statut = "En cours" THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN statut = "Terminé" THEN 1 ELSE 0 END) as terminees,
                SUM(CASE WHEN DATE(date_intervention) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui
            FROM interventions
        ');
        $interventions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques des messages helpdesk
        $stmt = $pdo->query('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN FAIT = 0 THEN 1 ELSE 0 END) as ouverts,
                SUM(CASE WHEN FAIT = 1 THEN 1 ELSE 0 END) as fermes,
                SUM(CASE WHEN DATE(DATE) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui
            FROM helpdesk_msg
        ');
        $messages = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques des interventions
        $interventions = ['total' => 0, 'en_cours' => 0, 'terminees' => 0];
        try {
            $stmt = $pdo->query('
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN en_cours = 1 THEN 1 ELSE 0 END) as en_cours,
                    SUM(CASE WHEN en_cours = 0 THEN 1 ELSE 0 END) as terminees
                FROM inter
            ');
            $interventions = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table inter n'existe pas, utiliser les valeurs par défaut
        }
        
        // Statistiques de l'agenda (si la table existe)
        $agenda = ['total' => 0, 'aujourdhui' => 0, 'cette_semaine' => 0];
        try {
            $stmt = $pdo->query('
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN DATE(date_planifiee) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui,
                    SUM(CASE WHEN YEARWEEK(date_planifiee) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as cette_semaine
                FROM agenda
            ');
            $agenda = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table agenda n'existe pas, utiliser les valeurs par défaut
        }
        
        // Statistiques des templates de rapports
        $stmt = $pdo->query('
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as actifs
            FROM report_templates
        ');
        $reports = $stmt->fetch(PDO::FETCH_ASSOC);
        $reports['generes'] = 0; // Pas de suivi de génération dans les templates
        
        echo json_encode([
            'success' => true,
            'data' => [
                'interventions' => $interventions,
                'messages' => $messages,
                'agenda' => $agenda,
                'reports' => $reports,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la récupération des statistiques: ' . $e->getMessage());
    }
}

/**
 * Récupérer la liste des statuts d'intervention
 */
function handleGetStatuses($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, nom, couleur FROM intervention_statuts WHERE actif = 1 ORDER BY ordre_affichage ASC, nom ASC");
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $statuses
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Gérer la demande de prévisualisation
 */
function handleGeneratePreview($pdo) {
    try {
        $report_type = $_POST['report_type'] ?? '';
        $parameters = $_POST['parameters'] ?? [];
        
        if (empty($report_type)) {
            throw new Exception('Type de rapport manquant');
        }
        
        // Si parameters est une chaîne JSON (cas possible selon l'envoi JS), la décoder
        if (is_string($parameters)) {
            $decoded = json_decode($parameters, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parameters = $decoded;
            }
        }
        
        $preview = generateReportPreview($pdo, $report_type, $parameters);
        
        echo json_encode([
            'success' => true,
            'html' => $preview['html'],
            'data' => $preview['data']
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Générer une prévisualisation de rapport
 */
function generateReportPreview($pdo, $report_type, $parameters) {
    // Handle comma-separated types
    $types = is_array($report_type) ? $report_type : explode(',', $report_type);
    $types = array_map('trim', $types);
    
    $preview = [
        'type' => implode(', ', $types),
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => []
    ];

    // Générer le HTML moderne
    $html = '<div class="report-preview-content">';
    
    // En-tête du rapport
    $html .= '<div class="report-header-modern mb-4 p-4 rounded-3 bg-light border">';
    $html .= '<div class="d-flex justify-content-between align-items-center">';
    $html .= '<div>';
    $html .= '<h3 class="mb-1 text-primary">Rapport Personnalisé</h3>';
    $html .= '<p class="mb-0 text-muted"><i class="fas fa-calendar-alt me-2"></i>Généré le ' . date('d/m/Y à H:i') . '</p>';
    $html .= '</div>';
    $html .= '<div class="text-end">';
    $html .= '<span class="badge bg-primary rounded-pill px-3 py-2">Aperçu</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    foreach ($types as $type) {
        if ($type === 'interventions') {
            $limit = $parameters['interventions']['max_items'] ?? 10;
            
            // Vérifier si la table intervention_statuts existe pour la jointure
            $hasStatuts = false;
            try {
                $pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
                $hasStatuts = true;
            } catch (Exception $e) {}

            if ($hasStatuts) {
                $sql = '
                    SELECT 
                        i.id, 
                        CONCAT(c.nom, " ", c.prenom) as client_nom, 
                        i.info as description, 
                        s.nom as statut, 
                        i.date as date_intervention 
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    LEFT JOIN intervention_statuts s ON i.statut_id = s.id
                    WHERE 1=1
                ';
                
                // Filter by status_ids if provided
                if (!empty($parameters['interventions']['status_ids'])) {
                    $statusIds = $parameters['interventions']['status_ids'];
                    if (is_string($statusIds)) {
                        $statusIds = explode(',', $statusIds);
                        // Remove empty values
                        $statusIds = array_filter($statusIds, function($id) { return $id !== ''; });
                    }
                    if (is_array($statusIds) && !empty($statusIds)) {
                        $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
                        $sql .= " AND i.statut_id IN ($placeholders)";
                    }
                }
                
                $sql .= ' ORDER BY i.date DESC LIMIT ?';
                
                // Prepare params
                $execParams = [];
                if (!empty($parameters['interventions']['status_ids'])) {
                     $statusIds = $parameters['interventions']['status_ids'];
                     if (is_string($statusIds)) {
                         $statusIds = explode(',', $statusIds);
                         $statusIds = array_filter($statusIds, function($id) { return $id !== ''; });
                     }
                     if (is_array($statusIds) && !empty($statusIds)) {
                         $execParams = array_merge($execParams, $statusIds);
                     }
                }
                $execParams[] = $limit;

            } else {
                $sql = '
                    SELECT 
                        i.id, 
                        CONCAT(c.nom, " ", c.prenom) as client_nom, 
                        i.info as description, 
                        CASE WHEN i.en_cours = 1 THEN "En cours" ELSE "Terminé" END as statut, 
                        i.date as date_intervention 
                    FROM inter i
                    LEFT JOIN clients c ON i.id_client = c.ID
                    ORDER BY i.date DESC 
                    LIMIT ?
                ';
                $execParams = [$limit];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($execParams);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $preview['data']['interventions'] = $data;

            if (!empty($data)) {
                $html .= '<h5 class="section-title mb-3"><i class="fas fa-tools me-2 text-primary"></i>Dernières Interventions</h5>';
                $html .= '<div class="row g-3 mb-4">';
                foreach ($data as $row) {
                    // Adapter la classe de statut
                    $statusClass = 'primary';
                    if (stripos($row['statut'], 'Terminé') !== false || stripos($row['statut'], 'Clôturée') !== false) $statusClass = 'success';
                    elseif (stripos($row['statut'], 'En cours') !== false) $statusClass = 'warning';
                    elseif (stripos($row['statut'], 'Urgent') !== false) $statusClass = 'danger';

                    $html .= '<div class="col-12">';
                    $html .= '<div class="card border-start border-4 border-' . $statusClass . ' shadow-sm">';
                    $html .= '<div class="card-body">';
                    $html .= '<div class="d-flex justify-content-between align-items-start">';
                    $html .= '<div>';
                    $html .= '<h5 class="card-title mb-1">' . htmlspecialchars($row['client_nom'] ?? 'Client inconnu') . '</h5>';
                    $html .= '<p class="text-muted small mb-2"><i class="far fa-clock me-1"></i>' . htmlspecialchars($row['date_intervention']) . '</p>';
                    $html .= '<p class="card-text">' . htmlspecialchars($row['description']) . '</p>';
                    $html .= '</div>';
                    $html .= '<span class="badge bg-' . $statusClass . ' bg-opacity-10 text-' . $statusClass . ' px-3 py-2 rounded-pill">' . htmlspecialchars($row['statut']) . '</span>';
                    $html .= '</div>';
                    $html .= '</div></div></div>';
                }
                $html .= '</div>';
            } else {
                $html .= '<p class="text-muted mb-4">Aucune intervention récente.</p>';
            }
        } elseif ($type === 'messages') {
            $limit = $parameters['messages']['max_items'] ?? 10;
            $onlyOpen = filter_var($parameters['messages']['only_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            $sql = 'SELECT ID as id, TITRE as sujet, FAIT as statut, DATE as date_creation FROM helpdesk_msg';
            
            if ($onlyOpen) {
                $sql .= ' WHERE FAIT = 0';
            }
            
            $sql .= ' ORDER BY DATE DESC LIMIT ?';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$row) {
                $row['statut'] = $row['statut'] == 1 ? 'Fait' : 'En cours';
            }
            $preview['data']['messages'] = $results;

            if (!empty($results)) {
                $html .= '<h5 class="section-title mb-3"><i class="fas fa-comments me-2 text-info"></i>Messages Helpdesk</h5>';
                $html .= '<div class="list-group mb-4 shadow-sm">';
                foreach ($results as $row) {
                    $statusIcon = $row['statut'] === 'Fait' ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-warning';
                    $html .= '<div class="list-group-item border-start-0 border-end-0 d-flex align-items-center p-3">';
                    $html .= '<div class="me-3"><i class="fas ' . $statusIcon . ' fa-lg"></i></div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<h6 class="mb-1">' . htmlspecialchars($row['sujet']) . '</h6>';
                    $html .= '<small class="text-muted">' . date('d/m/Y H:i', strtotime($row['date_creation'])) . '</small>';
                    $html .= '</div>';
                    $html .= '<span class="badge bg-light text-dark border">' . $row['statut'] . '</span>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            } else {
                $html .= '<p class="text-muted mb-4">Aucun message récent.</p>';
            }
        } elseif ($type === 'agenda') {
            $limit = $parameters['agenda']['max_items'] ?? 10;
            try {
                $stmt = $pdo->prepare('
                    SELECT id, titre, date_planifiee as date_evenement, description 
                    FROM agenda 
                    ORDER BY date_planifiee DESC 
                    LIMIT ?
                ');
                $stmt->execute([$limit]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $preview['data']['agenda'] = $data;

                if (!empty($data)) {
                    $html .= '<h5 class="section-title mb-3"><i class="fas fa-calendar-alt me-2 text-warning"></i>Agenda</h5>';
                    $html .= '<div class="timeline mb-4">';
                    foreach ($data as $row) {
                        $html .= '<div class="timeline-item pb-3 ps-4 border-start border-2 border-warning position-relative">';
                        $html .= '<div class="position-absolute top-0 start-0 translate-middle bg-warning rounded-circle" style="width: 12px; height: 12px; border: 2px solid #fff;"></div>';
                        $html .= '<div class="card shadow-sm border-0 bg-light">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<h6 class="mb-1">' . htmlspecialchars($row['titre']) . '</h6>';
                        $html .= '<small class="text-muted"><i class="far fa-calendar me-1"></i>' . date('d/m/Y H:i', strtotime($row['date_evenement'])) . '</small>';
                        $html .= '</div></div></div>';
                    }
                    $html .= '</div>';
                } else {
                    $html .= '<p class="text-muted mb-4">Aucun événement à venir.</p>';
                }
            } catch (Exception $e) {
                $preview['data']['agenda'] = [];
                $html .= '<p class="text-danger mb-4">Erreur lors de la récupération de l\'agenda.</p>';
            }
        } elseif ($type === 'resume_caisse') {
            $dateOption = $parameters['resume_caisse']['date_option'] ?? 'today';
            $targetDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
            
            $resumeHtml = generateResumeCaisseHtml($pdo, $targetDate);
            $preview['data']['resume_caisse'] = ['date' => $targetDate]; // Minimal data for preview response
            $html .= $resumeHtml;
        }
    }
    
    $html .= '</div>';
    $preview['html'] = $html;
    
    return $preview;
}

/**
 * Envoyer un rapport de test
 */
function sendTestReport($pdo, $report, $test_email) {
    // Utiliser le générateur de rapport pour créer le contenu réel
    $generator = new ReportGenerator();
    
    // Pour resume_caisse, on doit peut-être passer des options de date si c'est défini dans le rapport
    // Mais generateReport utilise le template ID et lit les params depuis la DB.
    // Si le rapport a 'hier' comme option, il le respectera.
    $result = $generator->generateReport($report['id']);
    
    if (!$result['success']) {
        return ['status' => 'error', 'message' => 'Erreur génération: ' . $result['error']];
    }
    
    // Envoyer l'email
    // Envoyer l'email
    $mailHelper = new MailHelper();
    
    $subject = '[TEST] ' . $report['name'];
    $body = $result['content'];
    $attachments = [];
    $isHtml = true;

    // Si PDF
    if (isset($result['mime_type']) && $result['mime_type'] === 'application/pdf') {
        $body = "<html><body>";
        $body .= "<h3>Rapport : " . htmlspecialchars($report['name']) . "</h3>";
        $body .= "<p>Veuillez trouver ci-joint le rapport généré le " . date('d/m/Y à H:i') . ".</p>";
        $body .= "<p>Cordialement,<br>TechSuivi</p>";
        $body .= "</body></html>";
        
        $attachments[] = [
            'content' => $result['content'],
            'name' => $result['filename'] ?? 'rapport.pdf',
            'type' => 'application/pdf'
        ];
    }

    $sent = $mailHelper->sendMail($test_email, $subject, $body, $isHtml, $attachments);
    
    if ($sent) {
        return [
            'report_name' => $report['name'],
            'test_email' => $test_email,
            'sent_at' => date('Y-m-d H:i:s'),
            'status' => 'success'
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'Erreur lors de l\'envoi de l\'email'
        ];
    }
}

/**
 * Obtenir le libellé d'un type de rapport
 */
function getReportTypeLabel($type) {
    $labels = [
        'interventions' => 'Interventions',
        'messages' => 'Messages Helpdesk',
        'agenda' => 'Agenda',
        'resume_caisse' => 'Résumé Caisse',
        'mixed' => 'Rapport Mixte'
    ];
    
    if (strpos($type, ',') !== false) {
        $types = explode(',', $type);
        $result = [];
        foreach ($types as $t) {
            $t = trim($t);
            $result[] = $labels[$t] ?? $t;
        }
        return implode(', ', $result);
    }
    
    return $labels[$type] ?? $type;
}

/**
 * Vérifier et corriger le schéma de la base de données
 */
function handleCheckSchema($pdo) {
    try {
        // Créer la table si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS report_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            report_type VARCHAR(255) NOT NULL,
            parameters TEXT,
            content_template TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Forcer le type de la colonne report_type à VARCHAR(255) pour supporter les valeurs multiples
        // Cela corrige le problème si la colonne était précédemment un ENUM
        try {
            $pdo->exec("ALTER TABLE report_templates MODIFY COLUMN report_type VARCHAR(255) NOT NULL");
        } catch (Exception $e) {
            // Ignorer si déjà correct ou autre erreur mineure
        }

        echo json_encode(['success' => true, 'message' => 'Schema verified']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

/**
 * Générer le HTML pour le Résumé Caisse
 */
function generateResumeCaisseHtml($pdo, $date) {
    $resume = [
        'feuille_caisse' => null,
        'transactions' => [],
        'sessions_cyber' => [],
        'totaux' => [
            'feuille_matin' => 0,
            'recettes_cyber' => 0,
            'entrees' => 0,
            'sorties' => 0,
            'solde_journee' => 0,
            'total_final' => 0
        ]
    ];

    try {
        // 1. Feuille de caisse (Matin)
        $stmt = $pdo->prepare("SELECT * FROM FC_feuille_caisse WHERE DATE(date_comptage) = ? ORDER BY date_comptage ASC LIMIT 1");
        $stmt->execute([$date]);
        $resume['feuille_caisse'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resume['feuille_caisse']) {
            $resume['totaux']['feuille_matin'] = $resume['feuille_caisse']['total_caisse'];
        }

        // 2. Transactions (Entrées/Sorties)
        $stmt = $pdo->prepare("
            SELECT t.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
            WHERE DATE(t.date_transaction) = ?
            ORDER BY t.date_transaction ASC
        ");
        $stmt->execute([$date]);
        $resume['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resume['transactions'] as $t) {
            $resume['totaux']['entrees'] += $t['montant'];
        }

        // 3. Cyber
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber WHERE DATE(date_cyber) = ? ORDER BY date_cyber ASC");
        $stmt->execute([$date]);
        $resume['sessions_cyber'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resume['sessions_cyber'] as $s) {
            $resume['totaux']['recettes_cyber'] += ($s['tarif'] ?? 0);
        }

        // Calculs
        $resume['totaux']['solde_journee'] = $resume['totaux']['entrees'] - $resume['totaux']['sorties'];
        $resume['totaux']['total_final'] = $resume['totaux']['feuille_matin'] + $resume['totaux']['recettes_cyber'] + $resume['totaux']['solde_journee'];

    } catch (Exception $e) {
        return '<div class="alert alert-danger">Erreur génération résumé: ' . $e->getMessage() . '</div>';
    }

    // Génération HTML (Style Inline pour Email)
    $styleTh = 'background-color: #f0f0f0; font-weight: bold; padding: 6px; border-bottom: 1px solid #ddd; text-align: left; font-size: 11px; color: #000;';
    $styleTd = 'padding: 6px; border-bottom: 1px solid #ddd; text-align: left; font-size: 11px; color: #000;';
    $styleBox = 'background-color: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;';
    $styleHeader = 'background-color: #f0f0f0; padding: 8px; margin: -15px -15px 10px -15px; border-left: 4px solid #333; border-radius: 8px 8px 0 0; color: #000; font-size: 14px; font-weight: bold;';

    $html = '<div style="font-family: Arial, sans-serif; background-color: #fff; color: #000; padding: 10px;">';
    
    // Titre
    $html .= '<h2 style="color: #333; border-bottom: 2px solid #333; padding-bottom: 10px;">Résumé Caisse: ' . date('d/m/Y', strtotime($date)) . '</h2>';

    // 1. Fond de Caisse
    $html .= '<div style="' . $styleBox . '">';
    $html .= '<h3 style="' . $styleHeader . '">💰 Fond de Caisse (Matin)</h3>';
    $html .= '<p style="font-size: 14px;"><strong>Montant: </strong>' . number_format($resume['totaux']['feuille_matin'], 2) . ' €</p>';
    if (!$resume['feuille_caisse']) {
        $html .= '<p style="color: red; font-style: italic;">(Pas de feuille de caisse enregistrée ce matin)</p>';
    }
    $html .= '</div>';

    // 2. Cyber
    if (!empty($resume['sessions_cyber'])) {
        $html .= '<div style="' . $styleBox . '">';
        $html .= '<h3 style="' . $styleHeader . '">💻 Espace Cyber</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr><th style="' . $styleTh . '">Poste</th><th style="' . $styleTh . '">Début</th><th style="' . $styleTh . '">Fin</th><th style="' . $styleTh . '">Tarif</th></tr></thead>';
        $html .= 'tbody';
        foreach ($resume['sessions_cyber'] as $s) {
            $html .= '<tr>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars($s['poste'] ?? '') . '</td>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars(substr($s['heure_debut'], 0, 5)) . '</td>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars(substr($s['heure_fin'], 0, 5)) . '</td>';
            $html .= '<td style="' . $styleTd . '"><strong>' . number_format($s['tarif'], 2) . ' €</strong></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p style="text-align: right; margin-top: 10px; font-weight: bold;">Total Cyber: ' . number_format($resume['totaux']['recettes_cyber'], 2) . ' €</p>';
        $html .= '</div>';
    }

    // 3. Transactions
    $html .= '<div style="' . $styleBox . '">';
    $html .= '<h3 style="' . $styleHeader . '">📝 Transactions</h3>';
    if (empty($resume['transactions'])) {
        $html .= '<p style="font-style: italic;">Aucune transaction.</p>';
    } else {
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead><tr><th style="' . $styleTh . '">Heure</th><th style="' . $styleTh . '">Client</th><th style="' . $styleTh . '">Description</th><th style="' . $styleTh . '">Moyen</th><th style="' . $styleTh . '">Montant</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($resume['transactions'] as $t) {
            $color = ($t['montant'] >= 0) ? '#4CAF50' : '#F44336';
            $html .= '<tr>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars(substr($t['heure'], 0, 5)) . '</td>';
            $clientName = trim(($t['client_nom'] ?? '') . ' ' . ($t['client_prenom'] ?? ''));
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars($clientName ?: 'Client de passage') . '</td>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars($t['description']) . '</td>';
            $html .= '<td style="' . $styleTd . '">' . htmlspecialchars($t['moyen_paiement']) . '</td>';
            $html .= '<td style="' . $styleTd . ' color: ' . $color . '; font-weight: bold;">' . number_format($t['montant'], 2) . ' €</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</div>';

    // 4. Totaux
    $html .= '<div style="background-color: #333; color: white; padding: 20px; text-align: center; font-size: 18px; font-weight: bold; border-radius: 8px;">';
    $html .= 'Solde Théorique (Fin de journée) : ' . number_format($resume['totaux']['total_final'], 2) . ' €';
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}