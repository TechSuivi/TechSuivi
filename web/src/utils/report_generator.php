<?php
/**
 * Générateur de rapports automatiques pour TechSuivi
 * Intégré au système de tâches programmées
 */

if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

require_once __DIR__ . '/../config/database.php';

// Inclure FPDF si présent
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}

class ReportGenerator {
    private $pdo;
    
    // Styles CSS inline pour les emails
    private $styles = [
        'body' => 'font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f6f9; margin: 0; padding: 20px;',
        'container' => 'max-width: 800px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);',
        'header' => 'background: #ffffff; padding: 30px; border-bottom: 1px solid #e9ecef;',
        'header_title' => 'margin: 0; color: #0d6efd; font-size: 24px; font-weight: 600;',
        'header_meta' => 'margin: 5px 0 0; color: #6c757d; font-size: 14px;',
        'content' => 'padding: 30px;',
        'section_title' => 'margin: 30px 0 20px; color: #495057; font-size: 18px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;',
        'card' => 'background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);',
        'card_title' => 'margin: 0 0 10px; font-size: 16px; font-weight: 600; color: #212529;',
        'card_meta' => 'margin: 0 0 10px; font-size: 13px; color: #6c757d;',
        'card_text' => 'margin: 0; color: #495057; font-size: 14px;',
        'badge' => 'display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;',
        'badge_success' => 'background-color: #d1e7dd; color: #0f5132;',
        'badge_warning' => 'background-color: #fff3cd; color: #664d03;',
        'badge_danger' => 'background-color: #f8d7da; color: #842029;',
        'badge_info' => 'background-color: #cff4fc; color: #055160;',
        'badge_primary' => 'background-color: #cfe2ff; color: #084298;',
        'table' => 'width: 100%; border-collapse: collapse; margin-bottom: 20px;',
        'th' => 'text-align: left; padding: 12px; border-bottom: 2px solid #e9ecef; color: #495057; font-weight: 600;',
        'td' => 'padding: 12px; border-bottom: 1px solid #e9ecef; color: #212529;',
        'footer' => 'background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #e9ecef;'
    ];
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }
    
    /**
     * Génère un rapport basé sur un template
     */
    public function generateReport($templateId, $dateRange = 'today') {
        try {
            // Récupérer le template
            $stmt = $this->pdo->prepare('SELECT * FROM report_templates WHERE id = ? AND is_active = 1');
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception("Template de rapport non trouvé ou inactif: ID $templateId");
            }
            
            // Décoder les paramètres
            $parameters = json_decode($template['parameters'], true) ?: [];
            
            // Aplatir les paramètres si nécessaire (cas où parameters = {"messages": {...}})
            // Si le type est 'messages' et qu'il y a une clé 'messages', on utilise le sous-tableau
            if (isset($parameters[$template['report_type']])) {
                $parameters = array_merge($parameters, $parameters[$template['report_type']]);
            }
            
            // Générer les données selon le type de rapport
            $data = [];
            switch ($template['report_type']) {
                case 'interventions':
                    $data['interventions'] = $this->fetchInterventions($parameters, $dateRange);
                    break;
                case 'messages':
                    $data['messages'] = $this->fetchMessages($parameters, $dateRange);
                    break;
                case 'agenda':
                    $data['agenda'] = $this->fetchAgenda($parameters, $dateRange);
                    break;
                case 'resume_caisse':
                    $dateOption = $parameters['date_option'] ?? 'today';
                    $targetDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
                    // Override dateRange if we are in a resume_caisse context specific date
                    // But wait, dateRange argument might be used for generic range. 
                    // Let's use targetDate for resume_caisse specifically.
                    $data['resume_caisse'] = $this->fetchResumeCaisse($targetDate);
                    break;
                case 'mixed':
                    // Pour mixed, on suppose que les clés sont dans parameters
                    if (isset($parameters['interventions'])) $data['interventions'] = $this->fetchInterventions($parameters['interventions'], $dateRange);
                    if (isset($parameters['messages'])) $data['messages'] = $this->fetchMessages($parameters['messages'], $dateRange);
                    if (isset($parameters['agenda'])) $data['agenda'] = $this->fetchAgenda($parameters['agenda'], $dateRange);
                    break;
                default:
                    // Essayer de parser le type comme une liste séparée par des virgules (nouveau format)
                    $types = explode(',', $template['report_type']);
                    foreach ($types as $type) {
                        $type = trim($type);
                        if ($type === 'interventions') $data['interventions'] = $this->fetchInterventions($parameters['interventions'] ?? $parameters, $dateRange);
                        if ($type === 'messages') $data['messages'] = $this->fetchMessages($parameters['messages'] ?? $parameters, $dateRange);
                        if ($type === 'agenda') $data['agenda'] = $this->fetchAgenda($parameters['agenda'] ?? $parameters, $dateRange);
                        if ($type === 'resume_caisse') {
                             $rcParams = $parameters['resume_caisse'] ?? $parameters;
                             $dateOption = $rcParams['date_option'] ?? 'today';
                             $targetDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
                             $data['resume_caisse'] = $this->fetchResumeCaisse($targetDate);
                        }
                    }
            }
            
            // Vérifier le format demandé pour resume_caisse
            $paramsResume = $parameters['resume_caisse'] ?? null;
            $format = $paramsResume['format'] ?? 'html';

            if ($template['report_type'] === 'resume_caisse' && $format === 'pdf') {
                if (!class_exists('FPDF')) {
                     // Fallback to HTML if FPDF not active
                     $content = $this->generateModernHtml($template, $data);
                     $mime = 'text/html';
                     $filename = 'rapport.html';
                } else {
                     $content = $this->generateResumeCaissePdf($template, $data['resume_caisse']);
                     $mime = 'application/pdf';
                     $filename = 'Resume_Caisse_' . date('Y-m-d', strtotime($data['resume_caisse']['date'])) . '.pdf';
                }
            } else {
                // Default HTML
                $content = $this->generateModernHtml($template, $data);
                $mime = 'text/html';
                $filename = 'rapport.html';
            }
            
            return [
                'success' => true,
                'template_name' => $template['name'],
                'report_type' => $template['report_type'],
                'content' => $content,
                'mime_type' => $mime,
                'filename' => $filename,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les données des interventions
     */
    private function fetchInterventions($params, $dateRange) {
        $limit = $params['max_items'] ?? 10;
        $execParams = []; // Initialiser le tableau des paramètres
        
        // Vérifier si la table intervention_statuts existe pour la jointure
        $hasStatuts = false;
        try {
            $this->pdo->query("SELECT 1 FROM intervention_statuts LIMIT 1");
            $hasStatuts = true;
        } catch (Exception $e) {}

        try {
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
                if (!empty($params['status_ids'])) {
                    $statusIds = $params['status_ids'];
                    if (is_string($statusIds)) {
                        $statusIds = explode(',', $statusIds);
                        $statusIds = array_filter($statusIds, function($id) { return $id !== ''; });
                    }
                    if (is_array($statusIds) && !empty($statusIds)) {
                        $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
                        $sql .= " AND i.statut_id IN ($placeholders)";
                        $execParams = array_merge($execParams, $statusIds);
                    }
                }
                
                $sql .= ' ORDER BY i.date DESC LIMIT ?';
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

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($execParams);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur fetchInterventions: " . $e->getMessage());
            return []; // Table inter might not exist or other error
        }
    }

    /**
     * Récupère les données des messages
     */
    private function fetchMessages($params, $dateRange) {
        $limit = $params['max_items'] ?? 10;
        try {
            // Colonnes vérifiées: ID, TITRE, MESSAGE, DATE, FAIT
            // Pas de colonne EMAIL trouvée dans le code source
            $stmt = $this->pdo->prepare('
                SELECT ID as id, TITRE as subject, MESSAGE as message, FAIT as status, DATE as created_at
                FROM helpdesk_msg
                ORDER BY DATE DESC
                LIMIT ?
            ');
            $stmt->execute([$limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Normaliser les statuts et ajouter un email par défaut si manquant
            foreach ($results as &$row) {
                $row['status'] = $row['status'] == 1 ? 'resolved' : 'open';

            }
            return $results;
        } catch (Exception $e) {
            // Log l'erreur si possible ou retourner vide
            return [];
        }
    }

    /**
     * Récupère les données de l'agenda
     */
    private function fetchAgenda($params, $dateRange) {
        $limit = $params['max_items'] ?? 10;
        try {
            $stmt = $this->pdo->prepare('
                SELECT id, titre as title, description, date_planifiee as date_event 
                FROM agenda 
                ORDER BY date_planifiee DESC 
                LIMIT ?
            ');
            $stmt->execute([$limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter un champ time_event factice ou extrait si besoin, ici on utilise date_event
            foreach ($results as &$row) {
                $row['time_event'] = date('H:i', strtotime($row['date_event']));
                $row['status'] = 'planned'; // Par défaut
            }
            return $results;
        } catch (Exception $e) {
            return []; // Table agenda might not exist
        }
    }

    /**
     * Récupère les données du Résumé Caisse
     */
    private function fetchResumeCaisse($date) {
        $resume = [
            'date' => $date,
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
            $stmt = $this->pdo->prepare("SELECT * FROM FC_feuille_caisse WHERE DATE(date_comptage) = ? ORDER BY date_comptage ASC LIMIT 1");
            $stmt->execute([$date]);
            $resume['feuille_caisse'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resume['feuille_caisse']) {
                $resume['totaux']['feuille_matin'] = $resume['feuille_caisse']['total_caisse'];
            }

            // 2. Transactions (Entrées/Sorties)
            $stmt = $this->pdo->prepare("
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
            $stmt = $this->pdo->prepare("SELECT * FROM FC_cyber WHERE DATE(date_cyber) = ? ORDER BY date_cyber ASC");
            $stmt->execute([$date]);
            $resume['sessions_cyber'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($resume['sessions_cyber'] as $s) {
                $resume['totaux']['recettes_cyber'] += ($s['tarif'] ?? 0);
            }

            // Calculs
            $resume['totaux']['solde_journee'] = $resume['totaux']['entrees'] - $resume['totaux']['sorties'];
            $resume['totaux']['total_final'] = $resume['totaux']['feuille_matin'] + $resume['totaux']['recettes_cyber'] + $resume['totaux']['solde_journee'];

        } catch (Exception $e) {
            error_log("Erreur fetchResumeCaisse: " . $e->getMessage());
        }

        return $resume;
    }

    /**
     * Génère le HTML moderne pour l'email
     */
    private function generateModernHtml($template, $data) {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="' . $this->styles['body'] . '">';
        $html .= '<div style="' . $this->styles['container'] . '">';
        
        // Header
        $html .= '<div style="' . $this->styles['header'] . '">';
        $html .= '<h1 style="' . $this->styles['header_title'] . '">' . htmlspecialchars($template['name']) . '</h1>';
        $html .= '<p style="' . $this->styles['header_meta'] . '">Généré le ' . date('d/m/Y à H:i') . '</p>';
        $html .= '</div>';
        
        // Content
        $html .= '<div style="' . $this->styles['content'] . '">';
        
        // Interventions Section
        if (!empty($data['interventions'])) {
            $html .= '<h2 style="' . $this->styles['section_title'] . '">🛠️ Dernières Interventions</h2>';
            foreach ($data['interventions'] as $item) {
                $statusStyle = $item['statut'] === 'Terminé' ? $this->styles['badge_success'] : $this->styles['badge_warning'];
                
                $html .= '<div style="' . $this->styles['card'] . '">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">';
                $html .= '<h3 style="' . $this->styles['card_title'] . '">' . htmlspecialchars($item['client_nom']) . '</h3>';
                $html .= '<span style="' . $this->styles['badge'] . ' ' . $statusStyle . '">' . htmlspecialchars($item['statut']) . '</span>';
                $html .= '</div>';
                $html .= '<p style="' . $this->styles['card_meta'] . '">' . date('d/m/Y H:i', strtotime($item['date_intervention'])) . '</p>';
                $html .= '<p style="' . $this->styles['card_text'] . '">' . htmlspecialchars($item['description']) . '</p>';
                $html .= '</div>';
            }
        }
        
        // Messages Section
        if (!empty($data['messages'])) {
            $html .= '<h2 style="' . $this->styles['section_title'] . '">📨 Messages Helpdesk</h2>';
            foreach ($data['messages'] as $item) {
                $statusStyle = $item['status'] === 'resolved' ? $this->styles['badge_success'] : ($item['status'] === 'open' ? $this->styles['badge_danger'] : $this->styles['badge_info']);
                $statusLabel = $item['status'] === 'resolved' ? 'Résolu' : ($item['status'] === 'open' ? 'Ouvert' : 'En cours');
                
                $html .= '<div style="' . $this->styles['card'] . '">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">';
                $html .= '<h3 style="' . $this->styles['card_title'] . '">' . htmlspecialchars($item['subject']) . '</h3>';
                $html .= '<span style="' . $this->styles['badge'] . ' ' . $statusStyle . '">' . $statusLabel . '</span>';
                $html .= '</div>';
                $html .= '<p style="' . $this->styles['card_meta'] . '">' . date('d/m/Y H:i', strtotime($item['created_at'])) . '</p>';
                $html .= '<p style="' . $this->styles['card_text'] . '">' . htmlspecialchars(substr($item['message'], 0, 150)) . (strlen($item['message']) > 150 ? '...' : '') . '</p>';
                $html .= '</div>';
            }
        }
        
        // Agenda Section
        if (!empty($data['agenda'])) {
            $html .= '<h2 style="' . $this->styles['section_title'] . '">📅 Agenda à venir</h2>';
            $html .= '<table style="' . $this->styles['table'] . '">';
            $html .= '<thead><tr><th style="' . $this->styles['th'] . '">Date</th><th style="' . $this->styles['th'] . '">Événement</th><th style="' . $this->styles['th'] . '">Statut</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($data['agenda'] as $item) {
                $statusStyle = $item['status'] === 'completed' ? $this->styles['badge_success'] : $this->styles['badge_primary'];
                $statusLabel = $item['status'] === 'completed' ? 'Terminé' : 'Prévu';
                
                $html .= '<tr>';
                $html .= '<td style="' . $this->styles['td'] . '">' . date('d/m/Y', strtotime($item['date_event'])) . ' ' . substr($item['time_event'], 0, 5) . '</td>';
                $html .= '<td style="' . $this->styles['td'] . '"><strong>' . htmlspecialchars($item['title']) . '</strong><br><span style="font-size: 12px; color: #6c757d;">' . htmlspecialchars($item['description']) . '</span></td>';
                $html .= '<td style="' . $this->styles['td'] . '"><span style="' . $this->styles['badge'] . ' ' . $statusStyle . '">' . $statusLabel . '</span></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // Resume Caisse Section
        if (!empty($data['resume_caisse'])) {
            $resume = $data['resume_caisse'];
            $html .= '<h2 style="' . $this->styles['section_title'] . '">📊 Résumé Caisse: ' . date('d/m/Y', strtotime($resume['date'])) . '</h2>';

            // Styles spécifiques (Inline pour robustesse email)
            $styleTh = 'background-color: #f0f0f0; font-weight: bold; padding: 6px; border-bottom: 1px solid #ddd; text-align: left; font-size: 11px; color: #000;';
            $styleTd = 'padding: 6px; border-bottom: 1px solid #ddd; text-align: left; font-size: 11px; color: #000;';
            $styleBox = $this->styles['card'];
            $styleHeader = 'background-color: #f0f0f0; padding: 8px; margin: -15px -15px 10px -15px; border-left: 4px solid #333; border-radius: 8px 8px 0 0; color: #000; font-size: 14px; font-weight: bold;';

            // 1. Fond de Caisse
            $html .= '<div style="' . $styleBox . '">';
            $html .= '<h3 style="' . $styleHeader . '">💰 Fond de Caisse (Matin)</h3>';
            $html .= '<p style="font-size: 14px; margin: 0;"><strong>Montant: </strong>' . number_format($resume['totaux']['feuille_matin'], 2) . ' €</p>';
            if (!$resume['feuille_caisse']) {
                $html .= '<p style="color: red; font-style: italic; margin-top: 5px;">(Pas de feuille de caisse enregistrée ce matin)</p>';
            }
            $html .= '</div>';

            // 2. Cyber
            if (!empty($resume['sessions_cyber'])) {
                $html .= '<div style="' . $styleBox . '">';
                $html .= '<h3 style="' . $styleHeader . '">💻 Espace Cyber</h3>';
                $html .= '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<thead><tr><th style="' . $styleTh . '">Poste</th><th style="' . $styleTh . '">Début</th><th style="' . $styleTh . '">Fin</th><th style="' . $styleTh . '">Tarif</th></tr></thead>';
                $html .= '<tbody>';
                foreach ($resume['sessions_cyber'] as $s) {
                    $html .= '<tr>';
                    $html .= '<td style="' . $styleTd . '">' . htmlspecialchars($s['poste'] ?? '') . '</td>';
                    $html .= '<td style="' . $styleTd . '">' . htmlspecialchars(substr($s['heure_debut'], 0, 5)) . '</td>';
                    $html .= '<td style="' . $styleTd . '">' . htmlspecialchars(substr($s['heure_fin'], 0, 5)) . '</td>';
                    $html .= '<td style="' . $styleTd . '"><strong>' . number_format($s['tarif'], 2) . ' €</strong></td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                $html .= '<p style="text-align: right; margin-top: 10px; font-weight: bold; color: #000;">Total Cyber: ' . number_format($resume['totaux']['recettes_cyber'], 2) . ' €</p>';
                $html .= '</div>';
            }

            // 3. Transactions
            $html .= '<div style="' . $styleBox . '">';
            $html .= '<h3 style="' . $styleHeader . '">📝 Transactions</h3>';
            if (empty($resume['transactions'])) {
                $html .= '<p style="font-style: italic; margin: 0;">Aucune transaction.</p>';
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
            $html .= '<div style="background-color: #333; color: white; padding: 20px; text-align: center; font-size: 18px; font-weight: bold; border-radius: 8px; margin-bottom: 20px;">';
            $html .= 'Solde Théorique (Fin de journée) : ' . number_format($resume['totaux']['total_final'], 2) . ' €';
            $html .= '</div>';
        }

        
        $html .= '</div>'; // End content
        
        // Footer
        $html .= '<div style="' . $this->styles['footer'] . '">';
        $html .= '<p>Rapport généré automatiquement par TechSuivi</p>';
        $html .= '</div>';
        
        $html .= '</div>'; // End container
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Génère le PDF pour Resume Caisse (Style "Pixel-Perfect")
     */
    private function generateResumeCaissePdf($template, $resume) {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        
        // --- Couleurs et Styles ---
        $colorHeaderBg = [240, 240, 240]; // #f0f0f0
        $colorDarkBg   = [51, 51, 51];    // #333
        
        // --- Titre ---
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(0);
        $pdf->Cell(0, 10, $this->decode('Résumé Journalier de Caisse'), 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, $this->decode('Date : ' . date('d/m/Y', strtotime($resume['date']))), 0, 1, 'C');
        $pdf->Ln(8);

        $pdf->Ln(2);

        // --- Total Final Box (Top) ---
        // On Web Page, it is at the top.
        $pdf->SetFillColor(51, 51, 51); // #333
        $pdf->Rect($pdf->GetX(), $pdf->GetY(), 190, 15, 'F');
        
        $pdf->SetXY($pdf->GetX(), $pdf->GetY() + 3);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(190, 10, $this->decode('Total Final de la Journée : ' . number_format($resume['totaux']['total_final'], 2) . ' €'), 0, 1, 'C');
        
        $pdf->SetTextColor(0);
        $pdf->Ln(8);

        // --- 1. Feuille de Caisse (Détails) ---
        // Label on web: "📊 Feuille de Caisse" -> icon removed for PDF usually, or keep text
        $this->pdfSectionHeader($pdf, 'Feuille de Caisse');
        
        if (!$resume['feuille_caisse']) {
             $pdf->SetFont('Arial', 'I', 10);
             $pdf->Cell(0, 8, $this->decode('Pas de feuille de caisse enregistrée.'), 0, 1);
        } else {
             $fc = $resume['feuille_caisse'];
             
             // Top Info
             $pdf->SetFont('Arial', '', 10);
             // Web: "Date : ..."
             $pdf->Cell(0, 6, $this->decode('Date : ' . date('d/m/Y H:i', strtotime($fc['date_comptage']))), 0, 1);
             $pdf->SetFont('Arial', 'B', 10);
             // Web: "Total : ..."
             $pdf->Cell(0, 6, $this->decode('Total : ' . number_format($fc['total_caisse'], 2) . ' €'), 0, 1);
             $pdf->Ln(4);
             
             // -- 3 Colonnes : Pièces | Billets | Récap --
             $yStart = $pdf->GetY();
             $colWidth = 60;
             $colGap = 5;
             $x1 = 10;
             $x2 = 10 + $colWidth + $colGap;
             $x3 = 10 + ($colWidth + $colGap) * 2;
             
             // Draw Col 1
             $pdf->SetXY($x1, $yStart);
             $this->pdfSubHeader($pdf, 'Pièces', $colWidth);
             $this->pdfCoinTable($pdf, $fc, $colWidth, $x1);
             
             // Draw Col 2
             $pdf->SetXY($x2, $yStart);
             $this->pdfSubHeader($pdf, 'Billets', $colWidth);
             $this->pdfBillTable($pdf, $fc, $colWidth, $x2);
             
             // Draw Col 3
             $pdf->SetXY($x3, $yStart);
             $this->pdfSubHeader($pdf, 'Récapitulatif', $colWidth);
             $this->pdfRecapTable($pdf, $fc, $colWidth, $x3);
             
             // Reset Y to max height of columns + margin
             $pdf->SetXY(10, 105); // Approximate fixed height for simplicity, or calc max Y 
             // Better: move to safe Y. The tables are fixed size roughly.
             $pdf->SetY($yStart + 60);

             // Détails Chèques
             if ($fc['montant_cheques'] > 0) {
                 $pdf->Ln(5);
                 $pdf->SetFont('Arial', 'B', 10);
                 $pdf->Cell(0, 8, utf8_decode('Détail des Chèques'), 0, 1);
                 
                 $cheques = json_decode($fc['cheques_details'], true) ?: [];
                 if (!empty($cheques)) {
                     // Header
                     $pdf->SetFillColor(245, 245, 245);
                     $pdf->SetFont('Arial', 'B', 9);
                     $pdf->Cell(40, 7, 'Montant', 1, 0, 'R', true);
                     $pdf->Cell(80, 7, utf8_decode('Emetteur'), 1, 0, 'L', true);
                     $pdf->Cell(70, 7, utf8_decode('Numéro'), 1, 1, 'L', true);
                     
                     $pdf->SetFont('Arial', '', 9);
                     foreach ($cheques as $ch) {
                         $pdf->Cell(40, 7, number_format($ch['montant'], 2) . ' ' . chr(128), 1, 0, 'R');
                         $pdf->Cell(80, 7, utf8_decode($ch['emetteur']), 1, 0, 'L');
                         $pdf->Cell(70, 7, utf8_decode($ch['numero']), 1, 1, 'L');
                     }
                 }
             }

             // Commentaire
             if (!empty($fc['commentaire'])) {
                 $pdf->Ln(4);
                 $pdf->SetFont('Arial', 'B', 10);
                 $pdf->Write(6, utf8_decode('Commentaire : '));
                 $pdf->SetFont('Arial', 'I', 10);
                 $pdf->Write(6, utf8_decode($fc['commentaire']));
                 $pdf->Ln(8);
             }
             $pdf->Ln(4);
        }

        // --- 2. Cyber ---
        if (!empty($resume['sessions_cyber'])) {
            $this->pdfSectionHeader($pdf, 'Sessions Cyber (' . count($resume['sessions_cyber']) . ') - Total : ' . number_format($resume['totaux']['recettes_cyber'], 2) . ' €');
            
            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(15, 7, 'Heure', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Client', 1, 0, 'L', true);
            $pdf->Cell(35, 7, utf8_decode('Durée'), 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Impressions', 1, 0, 'L', true);
            $pdf->Cell(40, 7, 'Tarif', 1, 1, 'R', true);
            
            // Rows
            $pdf->SetFont('Arial', '', 9);
            foreach ($resume['sessions_cyber'] as $s) {
                // Durée
                $duree = ($s['ha'] && $s['hd']) 
                    ? substr($s['ha'], 0, 5) . ' - ' . substr($s['hd'], 0, 5) 
                    : 'Impression';
                
                // Impressions
                $imps = [];
                if ($s['imp']) $imps[] = $s['imp'] . ' NB';
                if ($s['imp_c']) $imps[] = $s['imp_c'] . ' Coul';
                $impStr = empty($imps) ? '-' : implode(', ', $imps);

                $pdf->Cell(15, 7, date('H:i', strtotime($s['date_cyber'])), 1, 0, 'C');
                $pdf->Cell(50, 7, $this->decode(substr($s['nom'] ?: 'Anonyme', 0, 25)), 1, 0, 'L');
                $pdf->Cell(35, 7, $this->decode($duree), 1, 0, 'C');
                $pdf->Cell(50, 7, $this->decode($impStr), 1, 0, 'L');
                
                $pdf->SetTextColor(40, 167, 69); // Green
                $pdf->Cell(40, 7, number_format($s['tarif'], 2) . ' €', 1, 1, 'R');
                $pdf->SetTextColor(0);
            }
            // Total Cyber
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(150, 8, 'Total Cyber', 1, 0, 'R');
            $pdf->Cell(40, 8, number_format($resume['totaux']['recettes_cyber'], 2) . ' €', 1, 1, 'R');
            $pdf->Ln(6);
        }

        // --- 3. Transactions ---
        $this->pdfSectionHeader($pdf, 'Transactions (' . count($resume['transactions']) . ') - Solde : ' . number_format($resume['totaux']['solde_journee'], 2) . ' €');
        
        if (empty($resume['transactions'])) {
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 8, $this->decode('Aucune transaction enregistrée.'), 0, 1);
        } else {
            // Table Header
            $pdf->SetFont('Arial', 'B', 8); // Smaller font for many cols
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(15, 7, 'Heure', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Description / Client', 1, 0, 'L', true);
            $pdf->Cell(40, 7, 'Moyen', 1, 0, 'L', true);
            $pdf->Cell(20, 7, 'Acompte', 1, 0, 'R', true);
            $pdf->Cell(20, 7, 'Solde', 1, 0, 'R', true);
            $pdf->Cell(25, 7, 'Montant', 1, 0, 'R', true);
            $pdf->Cell(20, 7, 'Facture', 1, 1, 'L', true);
            
            // Rows
            $pdf->SetFont('Arial', '', 8);
            foreach ($resume['transactions'] as $t) {
                // Client/Desc
                $client = $t['client_nom'] 
                    ? ($t['client_nom'] . ' ' . ($t['client_prenom'] ?? ''))
                    : ($t['nom'] ?? '');
                
                // Moyen (Type + details)
                $type = $t['type'];
                if ((stripos($type, 'chèque') !== false || stripos($type, 'cheque') !== false) && !empty($t['num_cheque'])) {
                    $type .= ' (' . $t['num_cheque'] . ')';
                }

                $pdf->Cell(15, 7, date('H:i', strtotime($t['date_transaction'])), 1, 0, 'C');
                $pdf->Cell(50, 7, $this->decode(substr($client, 0, 30)), 1, 0, 'L');
                $pdf->Cell(40, 7, $this->decode(substr($type, 0, 25)), 1, 0, 'L');
                
                $pdf->Cell(20, 7, ($t['acompte'] ? number_format($t['acompte'], 2) : '-'), 1, 0, 'R');
                $pdf->Cell(20, 7, ($t['solde'] ? number_format($t['solde'], 2) : '-'), 1, 0, 'R');
                
                // Montant color
                if ($t['montant'] < 0) $pdf->SetTextColor(220, 53, 69);
                else $pdf->SetTextColor(40, 167, 69);
                $pdf->Cell(25, 7, number_format($t['montant'], 2) . ' €', 1, 0, 'R');
                $pdf->SetTextColor(0);

                $pdf->Cell(20, 7, $this->decode($t['num_facture'] ?? ''), 1, 1, 'L');
            }
            
            // Solde Journée
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(145, 8, $this->decode('Solde Journée (Entrées - Sorties)'), 1, 0, 'R');
            
            $solde = $resume['totaux']['solde_journee'];
            if ($solde < 0) $pdf->SetTextColor(220, 53, 69);
            else $pdf->SetTextColor(40, 167, 69);
            
            $pdf->Cell(25, 8, number_format($solde, 2) . ' €', 1, 1, 'R');
            $pdf->SetTextColor(0);
        }
        $pdf->Ln(10);


        // Footer
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100);
        $pdf->Cell(0, 10, utf8_decode('Généré automatiquement par TechSuivi le ' . date('d/m/Y à H:i')), 0, 1, 'R');

        return $pdf->Output('S');
    }

    /**
     * Helper pour encoder en Windows-1252 (FPDF default) -> Support Euro
     */
    private function decode($str) {
        // Remplacer € par le placeholder cp1252 si nécessaire, mais iconv gère souvent bien
        // Le caractère € en UTF-8 est (0xE2 0x82 0xAC). En CP1252 c'est 0x80 (128).
        // utf8_decode sort du ISO-8859-1 (pas d'euro).
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
    }

    private function pdfSubHeader($pdf, $title, $w) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($w, 6, $this->decode($title), 1, 1, 'L', true);
    }

    private function pdfCoinTable($pdf, $fc, $w, $x) {
        $this->pdfMoneyRow($pdf, '0.01', $fc['pieces_001'], 0.01, $w, $x);
        $this->pdfMoneyRow($pdf, '0.02', $fc['pieces_002'], 0.02, $w, $x);
        $this->pdfMoneyRow($pdf, '0.05', $fc['pieces_005'], 0.05, $w, $x);
        $this->pdfMoneyRow($pdf, '0.10', $fc['pieces_010'], 0.10, $w, $x);
        $this->pdfMoneyRow($pdf, '0.20', $fc['pieces_020'], 0.20, $w, $x);
        $this->pdfMoneyRow($pdf, '0.50', $fc['pieces_050'], 0.50, $w, $x);
        $this->pdfMoneyRow($pdf, '1.00', $fc['pieces_100'], 1.00, $w, $x);
        $this->pdfMoneyRow($pdf, '2.00', $fc['pieces_200'], 2.00, $w, $x);
        
        // Total
        $pdf->SetX($x);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w/2, 6, 'TOTAL', 1, 0);
        $pdf->Cell($w/2, 6, number_format($fc['total_pieces'], 2), 1, 1, 'R');
    }

    private function pdfBillTable($pdf, $fc, $w, $x) {
        $this->pdfMoneyRow($pdf, '5', $fc['billets_005'], 5, $w, $x);
        $this->pdfMoneyRow($pdf, '10', $fc['billets_010'], 10, $w, $x);
        $this->pdfMoneyRow($pdf, '20', $fc['billets_020'], 20, $w, $x);
        $this->pdfMoneyRow($pdf, '50', $fc['billets_050'], 50, $w, $x);
        $this->pdfMoneyRow($pdf, '100', $fc['billets_100'], 100, $w, $x);
        $this->pdfMoneyRow($pdf, '200', $fc['billets_200'], 200, $w, $x);
        $this->pdfMoneyRow($pdf, '500', $fc['billets_500'], 500, $w, $x);
        
        // Total
        $pdf->SetX($x);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w/2, 6, 'TOTAL', 1, 0);
        $pdf->Cell($w/2, 6, number_format($fc['total_billets'], 2), 1, 1, 'R');
    }

    private function pdfRecapTable($pdf, $fc, $w, $x) {
        $pdf->SetFont('Arial', '', 8);
        
        $pdf->SetX($x);
        $pdf->Cell($w/2, 6, $this->decode('Pièces'), 1, 0);
        $pdf->Cell($w/2, 6, number_format($fc['total_pieces'], 2), 1, 1, 'R');
        
        $pdf->SetX($x);
        $pdf->Cell($w/2, 6, $this->decode('Billets'), 1, 0);
        $pdf->Cell($w/2, 6, number_format($fc['total_billets'], 2), 1, 1, 'R');
        
        $pdf->SetX($x);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($w/2, 6, $this->decode('Total Espèces'), 1, 0, 'L', true);
        $pdf->Cell($w/2, 6, number_format($fc['total_especes'], 2), 1, 1, 'R', true);
        
        $pdf->SetX($x);
        $pdf->Cell($w/2, 6, $this->decode('Chèques'), 1, 0);
        $pdf->Cell($w/2, 6, number_format($fc['montant_cheques'], 2), 1, 1, 'R');
        
        $pdf->SetX($x);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w/2, 8, 'TOTAL CAISSE', 1, 0);
        $pdf->Cell($w/2, 8, number_format($fc['total_caisse'], 2), 1, 1, 'R');
    }

    private function pdfMoneyRow($pdf, $label, $qty, $val, $w, $x) {
        $pdf->SetX($x);
        $pdf->SetFont('Arial', '', 8);
        // Val | Qty | Total
        $w1 = $w * 0.3;
        $w2 = $w * 0.3;
        $w3 = $w * 0.4;
        
        if ($qty > 0) {
            $pdf->Cell($w1, 5, $label, 1, 0, 'R');
            $pdf->Cell($w2, 5, $qty, 1, 0, 'C');
            $pdf->Cell($w3, 5, number_format($qty * $val, 2), 1, 1, 'R');
        } else {
             // Empty row
             $pdf->Cell($w1, 5, $label, 1, 0, 'R');
             $pdf->Cell($w2, 5, '-', 1, 0, 'C');
             $pdf->Cell($w3, 5, '-', 1, 1, 'R');
        }
    }



        
    /**
     * Helper pour afficher un en-tête de section stylisé
     */
    private function pdfSectionHeader($pdf, $title) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 240, 240); // #f0f0f0
        $pdf->SetDrawColor(200, 200, 200);
        
        // Draw rounded look (simulation via Rect with border)
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $w = 190;
        $h = 10;
        
        $pdf->Rect($x, $y, $w, $h, 'F'); // Fill
        
        $pdf->SetXY($x + 5, $y);
        $pdf->Cell($w - 10, $h, $this->decode($title), 0, 1, 'L');
        
        // Reset pointers
        $pdf->SetDrawColor(0);
        $pdf->Ln(2);
    }
}