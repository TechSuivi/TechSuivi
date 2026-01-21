<?php
/**
 * Configuration du générateur de feuilles imprimables
 * Ce fichier contient tous les paramètres configurables
 */

// Empêcher l'accès direct
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

/**
 * Configuration générale
 */
define('PRINT_GENERATOR_VERSION', '1.0.0');
define('PRINT_GENERATOR_NAME', 'Générateur de Feuilles Imprimables');

/**
 * Limites et validations
 */
define('MAX_MESSAGE_LENGTH', 500);
define('MIN_MESSAGE_LENGTH', 1);
define('MAX_FONT_SIZE', 72);
define('MIN_FONT_SIZE', 12);

/**
 * Tailles de police disponibles
 */
$PRINT_FONT_SIZES = [
    12 => '12px - Très petit',
    14 => '14px - Petit',
    16 => '16px - Normal',
    18 => '18px - Moyen',
    24 => '24px - Grand',
    36 => '36px - Très grand',
    48 => '48px - Extra grand',
    72 => '72px - Géant'
];

/**
 * Couleurs disponibles
 */
$PRINT_COLORS = [
    '#000000' => 'Noir',
    '#FF0000' => 'Rouge',
    '#0000FF' => 'Bleu',
    '#008000' => 'Vert',
    '#FFA500' => 'Orange',
    '#800080' => 'Violet',
    '#FF1493' => 'Rose',
    '#8B4513' => 'Marron',
    '#808080' => 'Gris',
    '#FFD700' => 'Or',
    '#DC143C' => 'Rouge foncé',
    '#4169E1' => 'Bleu royal',
    '#228B22' => 'Vert forêt',
    '#FF6347' => 'Tomate',
    '#4B0082' => 'Indigo',
    '#FF69B4' => 'Rose vif'
];

/**
 * Alignements disponibles
 */
$PRINT_ALIGNMENTS = [
    'left' => 'Gauche',
    'center' => 'Centre',
    'right' => 'Droite',
    'justify' => 'Justifié'
];

/**
 * Styles de texte disponibles
 */
$PRINT_STYLES = [
    'normal' => 'Normal',
    'bold' => 'Gras',
    'italic' => 'Italique',
    'bold-italic' => 'Gras + Italique'
];

/**
 * Paramètres par défaut
 */
$PRINT_DEFAULTS = [
    'font_size' => 36,
    'text_color' => '#000000',
    'text_align' => 'center',
    'text_style' => 'bold'
];

/**
 * Format de page
 */
$PRINT_PAGE_FORMAT = [
    'width' => 297, // mm
    'height' => 210, // mm
    'orientation' => 'landscape',
    'margins' => [
        'top' => 20,
        'right' => 20,
        'bottom' => 20,
        'left' => 20
    ],
    'usable_width' => 257, // 297 - 40
    'usable_height' => 170  // 210 - 40
];

/**
 * Messages d'exemple
 */
$PRINT_EXAMPLES = [
    [
        'title' => 'Fermeture exceptionnelle',
        'text' => "Fermeture exceptionnelle\nSamedi 19 Juillet",
        'settings' => [
            'font_size' => 48,
            'text_color' => '#FF0000',
            'text_align' => 'center',
            'text_style' => 'bold'
        ]
    ],
    [
        'title' => 'Promotion spéciale',
        'text' => "PROMOTION SPÉCIALE\n-50% sur tous les services\nJusqu'au 31 décembre",
        'settings' => [
            'font_size' => 36,
            'text_color' => '#FFA500',
            'text_align' => 'center',
            'text_style' => 'bold'
        ]
    ],
    [
        'title' => 'Maintenance programmée',
        'text' => "MAINTENANCE PROGRAMMÉE\nSystème indisponible\nDimanche 2h-6h",
        'settings' => [
            'font_size' => 24,
            'text_color' => '#0000FF',
            'text_align' => 'center',
            'text_style' => 'bold-italic'
        ]
    ],
    [
        'title' => 'Nouveau horaire',
        'text' => "NOUVEAU HORAIRE\nOuvert 7j/7\n9h-19h non-stop",
        'settings' => [
            'font_size' => 32,
            'text_color' => '#008000',
            'text_align' => 'center',
            'text_style' => 'bold'
        ]
    ],
    [
        'title' => 'Information importante',
        'text' => "INFORMATION IMPORTANTE\nMerci de votre compréhension",
        'settings' => [
            'font_size' => 28,
            'text_color' => '#800080',
            'text_align' => 'center',
            'text_style' => 'normal'
        ]
    ]
];

/**
 * Configuration des notifications
 */
$PRINT_NOTIFICATIONS = [
    'duration' => 3000, // ms
    'position' => 'top-right',
    'animation' => 'slide'
];

/**
 * Configuration de sécurité
 */
$PRINT_SECURITY = [
    'max_requests_per_minute' => 10,
    'allowed_file_types' => ['html', 'pdf'],
    'sanitize_input' => true,
    'log_actions' => true
];

/**
 * Fonctions utilitaires
 */

/**
 * Valider une taille de police
 */
function validateFontSize($size) {
    global $PRINT_FONT_SIZES;
    return array_key_exists(intval($size), $PRINT_FONT_SIZES);
}

/**
 * Valider une couleur
 */
function validateColor($color) {
    global $PRINT_COLORS;
    return array_key_exists($color, $PRINT_COLORS) || preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

/**
 * Valider un alignement
 */
function validateAlignment($align) {
    global $PRINT_ALIGNMENTS;
    return array_key_exists($align, $PRINT_ALIGNMENTS);
}

/**
 * Valider un style
 */
function validateStyle($style) {
    global $PRINT_STYLES;
    return array_key_exists($style, $PRINT_STYLES);
}

/**
 * Obtenir les paramètres par défaut
 */
function getDefaultSettings() {
    global $PRINT_DEFAULTS;
    return $PRINT_DEFAULTS;
}

/**
 * Obtenir un exemple aléatoire
 */
function getRandomExample() {
    global $PRINT_EXAMPLES;
    return $PRINT_EXAMPLES[array_rand($PRINT_EXAMPLES)];
}

/**
 * Nettoyer et valider les données d'entrée
 */
function sanitizeInput($data) {
    $clean = [];
    
    // Message
    $clean['message_text'] = trim($data['message_text'] ?? '');
    if (strlen($clean['message_text']) > MAX_MESSAGE_LENGTH) {
        $clean['message_text'] = substr($clean['message_text'], 0, MAX_MESSAGE_LENGTH);
    }
    
    // Taille de police
    $clean['font_size'] = intval($data['font_size'] ?? 36);
    if (!validateFontSize($clean['font_size'])) {
        $clean['font_size'] = 36;
    }
    
    // Couleur
    $clean['text_color'] = $data['text_color'] ?? '#000000';
    if (!validateColor($clean['text_color'])) {
        $clean['text_color'] = '#000000';
    }
    
    // Alignement
    $clean['text_align'] = $data['text_align'] ?? 'center';
    if (!validateAlignment($clean['text_align'])) {
        $clean['text_align'] = 'center';
    }
    
    // Style
    $clean['text_style'] = $data['text_style'] ?? 'bold';
    if (!validateStyle($clean['text_style'])) {
        $clean['text_style'] = 'bold';
    }
    
    return $clean;
}

/**
 * Logger les actions
 */
function logPrintAction($action, $data = []) {
    global $PRINT_SECURITY;
    
    if (!$PRINT_SECURITY['log_actions']) {
        return;
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'action' => $action,
        'data' => $data
    ];
    
    // Ici, vous pourriez ajouter la logique pour écrire dans un fichier de log
    // ou dans une base de données
    error_log('PRINT_GENERATOR: ' . json_encode($log_entry));
}

/**
 * Vérifier les limites de taux
 */
function checkRateLimit() {
    global $PRINT_SECURITY;
    
    // Implémentation simple basée sur la session
    if (!isset($_SESSION['print_requests'])) {
        $_SESSION['print_requests'] = [];
    }
    
    $now = time();
    $minute_ago = $now - 60;
    
    // Nettoyer les anciennes requêtes
    $_SESSION['print_requests'] = array_filter(
        $_SESSION['print_requests'],
        function($timestamp) use ($minute_ago) {
            return $timestamp > $minute_ago;
        }
    );
    
    // Vérifier la limite
    if (count($_SESSION['print_requests']) >= $PRINT_SECURITY['max_requests_per_minute']) {
        return false;
    }
    
    // Ajouter la requête actuelle
    $_SESSION['print_requests'][] = $now;
    
    return true;
}

/**
 * Générer les options HTML pour un select
 */
function generateSelectOptions($options, $selected = null) {
    $html = '';
    foreach ($options as $value => $label) {
        $selected_attr = ($value == $selected) ? ' selected' : '';
        $html .= "<option value=\"{$value}\"{$selected_attr}>{$label}</option>\n";
    }
    return $html;
}

/**
 * Obtenir les informations de version
 */
function getPrintGeneratorInfo() {
    return [
        'name' => PRINT_GENERATOR_NAME,
        'version' => PRINT_GENERATOR_VERSION,
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'max_message_length' => MAX_MESSAGE_LENGTH
    ];
}

?>