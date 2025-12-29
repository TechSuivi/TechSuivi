<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Inclure l'utilitaire de permissions
require_once __DIR__ . '/../../utils/permissions_helper.php';

$message = '';

// Traitement de l'installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_system']) && isset($pdo)) {
    try {
        // Créer la table des photos
        $sql_photos = "
        CREATE TABLE IF NOT EXISTS intervention_photos (
            id int(11) NOT NULL AUTO_INCREMENT,
            intervention_id text COLLATE utf8mb4_general_ci NOT NULL,
            filename varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
            original_filename varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
            file_size int(11) NOT NULL,
            mime_type varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
            width int(11) DEFAULT NULL,
            height int(11) DEFAULT NULL,
            description text COLLATE utf8mb4_general_ci DEFAULT NULL,
            uploaded_at timestamp DEFAULT current_timestamp(),
            uploaded_by varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_intervention_id (intervention_id(50))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $sql_settings = "
        CREATE TABLE IF NOT EXISTS photos_settings (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_name varchar(50) NOT NULL UNIQUE,
            setting_value varchar(255) NOT NULL,
            updated_at timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $pdo->exec($sql_photos);
        $pdo->exec($sql_settings);
        
        // Insérer les paramètres par défaut
        $default_settings = [
            'max_width' => 1920,
            'max_height' => 1080,
            'thumb_size' => 300,
            'max_file_size' => 10,
            'quality' => 85
        ];
        
        foreach ($default_settings as $name => $value) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO photos_settings (setting_name, setting_value)
                VALUES (:name, :value)
            ");
            $stmt->execute([':name' => $name, ':value' => $value]);
        }
        
        // Créer le dossier d'upload avec gestion d'erreurs améliorée
        $uploadDir = __DIR__ . '/../../uploads/interventions/';
        $result = createDirectoryWithPermissions($uploadDir, 0775, true);
        
        if ($result['success']) {
            $message = '<p style="color: green;">✅ Installation réussie ! Tables créées et dossier d\'upload configuré.</p>';
            $message .= '<p style="color: blue;">ℹ️ ' . htmlspecialchars($result['message']) . '</p>';
        } else {
            $message = '<p style="color: red;">❌ Problème lors de la création du dossier d\'upload.</p>';
            $message .= '<p style="color: orange;">⚠️ ' . htmlspecialchars($result['message']) . '</p>';
            $message .= getPermissionErrorMessage('installation du système de photos', $uploadDir);
        }
    } catch (Exception $e) {
        $message = '<p style="color: red;">❌ Erreur lors de l\'installation : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// Traitement du formulaire de paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings']) && isset($pdo)) {
    $max_width = (int)($_POST['max_width'] ?? 1920);
    $max_height = (int)($_POST['max_height'] ?? 1080);
    $thumb_size = (int)($_POST['thumb_size'] ?? 300);
    $max_file_size = (int)($_POST['max_file_size'] ?? 10);
    $quality = (int)($_POST['quality'] ?? 85);
    
    // Validation
    $errors = [];
    if ($max_width < 100 || $max_width > 4000) {
        $errors[] = 'La largeur maximale doit être entre 100 et 4000 pixels.';
    }
    if ($max_height < 100 || $max_height > 4000) {
        $errors[] = 'La hauteur maximale doit être entre 100 et 4000 pixels.';
    }
    if ($thumb_size < 50 || $thumb_size > 500) {
        $errors[] = 'La taille des miniatures doit être entre 50 et 500 pixels.';
    }
    if ($max_file_size < 1 || $max_file_size > 50) {
        $errors[] = 'La taille maximale des fichiers doit être entre 1 et 50 MB.';
    }
    if ($quality < 10 || $quality > 100) {
        $errors[] = 'La qualité doit être entre 10 et 100%.';
    }
    
    if (empty($errors)) {
        try {
            // Vérifier si la table de paramètres existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'photos_settings'");
            if ($stmt->rowCount() == 0) {
                // Créer la table si elle n'existe pas
                $pdo->exec("
                    CREATE TABLE photos_settings (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        setting_name varchar(50) NOT NULL UNIQUE,
                        setting_value varchar(255) NOT NULL,
                        updated_at timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
            }
            
            // Sauvegarder les paramètres
            $settings = [
                'max_width' => $max_width,
                'max_height' => $max_height,
                'thumb_size' => $thumb_size,
                'max_file_size' => $max_file_size,
                'quality' => $quality
            ];
            
            foreach ($settings as $name => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO photos_settings (setting_name, setting_value) 
                    VALUES (:name, :value) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([':name' => $name, ':value' => $value]);
            }
            
            $message = '<p style="color: green;">✅ Paramètres sauvegardés avec succès !</p>';
        } catch (PDOException $e) {
            $message = '<p style="color: red;">❌ Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $message = '<p style="color: red;">❌ ' . implode('<br>', $errors) . '</p>';
    }
}

// Fonction de vérification du système
function checkSystemStatus($pdo) {
    $status = [
        'photos_table' => false,
        'settings_table' => false,
        'upload_dir' => false,
        'upload_writable' => false,
        'api_accessible' => false
    ];
    
    try {
        // Vérifier la table photos
        $stmt = $pdo->query("SHOW TABLES LIKE 'intervention_photos'");
        $status['photos_table'] = $stmt->rowCount() > 0;
        
        // Vérifier la table settings
        $stmt = $pdo->query("SHOW TABLES LIKE 'photos_settings'");
        $status['settings_table'] = $stmt->rowCount() > 0;
        
        // Vérifier le dossier d'upload
        $uploadDir = __DIR__ . '/../../uploads/interventions/';
        $status['upload_dir'] = is_dir($uploadDir);
        $status['upload_writable'] = is_writable($uploadDir);
        
        // Vérifier l'API
        $apiFile = __DIR__ . '/../../api/photos.php';
        $status['api_accessible'] = file_exists($apiFile);
        
    } catch (Exception $e) {
        // Erreur lors de la vérification
    }
    
    return $status;
}

// Traitement de la vérification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_system']) && isset($pdo)) {
    $systemStatus = checkSystemStatus($pdo);
    $allOk = array_reduce($systemStatus, function($carry, $item) { return $carry && $item; }, true);
    
    if ($allOk) {
        $message = '<p style="color: green;">✅ Système entièrement fonctionnel ! Tous les composants sont opérationnels.</p>';
    } else {
        $message = '<p style="color: orange;">⚠️ Certains composants nécessitent une attention. Voir les détails ci-dessous.</p>';
    }
}

// Récupération des paramètres actuels
$settings = [
    'max_width' => 1920,
    'max_height' => 1080,
    'thumb_size' => 300,
    'max_file_size' => 10,
    'quality' => 85
];

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM photos_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Table n'existe pas encore, utiliser les valeurs par défaut
    }
}

// Obtenir le statut du système pour l'affichage
$systemStatus = isset($pdo) ? checkSystemStatus($pdo) : [];
?>

<style>
.settings-container {
    width: 100%;
    padding: 0 20px;
    box-sizing: border-box;
}

.settings-section {
    background-color: var(--bg-secondary, #f8f9fa);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

body.dark .settings-section {
    background-color: #2b2b2b;
    border-color: #555;
    color: var(--text-color-dark);
}

.settings-section h3 {
    margin-top: 0;
    color: var(--accent-color);
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--text-color, #333);
}

body.dark .form-group label {
    color: var(--text-color-dark, #e2e8f0);
}

.settings-container * {
    box-sizing: border-box;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color, #ccc);
    border-radius: 4px;
    font-size: 14px;
    background-color: var(--input-bg, white);
    color: var(--text-color, #333);
    box-sizing: border-box; /* Ensure padding doesn't increase width */
}

body.dark .form-control {
    background-color: #1a1a1a;
    border-color: #555;
    color: var(--text-color-dark, #e2e8f0);
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 2px rgba(44, 90, 160, 0.2);
}

.help-text {
    font-size: 12px;
    color: var(--text-muted, #666);
    margin-top: 3px;
}

body.dark .help-text {
    color: #aaa;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
}

.btn-primary {
    background-color: var(--accent-color);
    color: white;
}

.btn-primary:hover {
    background-color: #23428a;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.preview-box {
    background-color: var(--bg-color, white);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: 4px;
    padding: 15px;
    margin-top: 10px;
}

body.dark .preview-box {
    background-color: #1a1a1a;
    border-color: #555;
}

.status-item {
    padding: 10px;
    border-radius: 4px;
    font-size: 14px;
}

.status-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.status-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

body.dark .status-success {
    background-color: #1e4d2b;
    border-color: #2d5a3d;
    color: #a3d9a5;
}

body.dark .status-error {
    background-color: #4d1e1e;
    border-color: #5a2d2d;
    color: #f5a3a3;
}

.warning-box {
    margin-top: 15px;
    padding: 10px;
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    color: #856404;
}

body.dark .warning-box {
    background-color: #4d4419;
    border-color: #5a4f1f;
    color: #f4e79d;
}

.warning-box code {
    background: #f4f4f4;
    padding: 2px 4px;
    border-radius: 3px;
    color: #333;
}

body.dark .warning-box code {
    background: #2a2a2a;
    color: #e2e8f0;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
    align-items: end; /* Align bottoms of inputs if labels have different heights */
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="settings-container">
    <h1>Paramètres des Photos</h1>
    
    <?php echo $message; ?>
    

    
    <form method="POST">
        <input type="hidden" name="save_settings" value="1">
        <div class="settings-section">
            <h3>Redimensionnement des Images</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="max_width">Largeur maximale (pixels)</label>
                    <input type="number" id="max_width" name="max_width" class="form-control" 
                           value="<?= htmlspecialchars($settings['max_width']) ?>" 
                           min="100" max="4000" required>
                    <div class="help-text">Les images plus larges seront redimensionnées</div>
                </div>
                
                <div class="form-group">
                    <label for="max_height">Hauteur maximale (pixels)</label>
                    <input type="number" id="max_height" name="max_height" class="form-control" 
                           value="<?= htmlspecialchars($settings['max_height']) ?>" 
                           min="100" max="4000" required>
                    <div class="help-text">Les images plus hautes seront redimensionnées</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="thumb_size">Taille des miniatures (pixels)</label>
                    <input type="number" id="thumb_size" name="thumb_size" class="form-control" 
                           value="<?= htmlspecialchars($settings['thumb_size']) ?>" 
                           min="50" max="500" required>
                    <div class="help-text">Taille carrée des miniatures (ex: 300x300)</div>
                </div>
                
                <div class="form-group">
                    <label for="quality">Qualité JPEG (%)</label>
                    <input type="number" id="quality" name="quality" class="form-control" 
                           value="<?= htmlspecialchars($settings['quality']) ?>" 
                           min="10" max="100" required>
                    <div class="help-text">Qualité de compression (85% recommandé)</div>
                </div>
            </div>
        </div>
        
        <div class="settings-section">
            <h3>Limites de Fichiers</h3>
            
            <div class="form-group">
                <label for="max_file_size">Taille maximale par fichier (MB)</label>
                <input type="number" id="max_file_size" name="max_file_size" class="form-control" 
                       value="<?= htmlspecialchars($settings['max_file_size']) ?>" 
                       min="1" max="50" required>
                <div class="help-text">Taille maximale autorisée pour l'upload</div>
            </div>
        </div>
        
        <div class="settings-section">
            <h3>Aperçu des Paramètres</h3>
            <div class="preview-box">
                <p><strong>Images redimensionnées à :</strong> <span id="preview-size"><?= $settings['max_width'] ?>x<?= $settings['max_height'] ?></span> pixels maximum</p>
                <p><strong>Miniatures :</strong> <span id="preview-thumb"><?= $settings['thumb_size'] ?>x<?= $settings['thumb_size'] ?></span> pixels</p>
                <p><strong>Qualité :</strong> <span id="preview-quality"><?= $settings['quality'] ?></span>%</p>
                <p><strong>Taille max :</strong> <span id="preview-filesize"><?= $settings['max_file_size'] ?></span> MB</p>
            </div>
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px;">
                 💾 Sauvegarder les Paramètres
            </button>
            <a href="index.php?page=interventions_list" class="btn btn-secondary" style="padding: 12px 24px; font-size: 16px;">
                ← Retour aux Interventions
            </a>
        </div>
    </form>
    
    <div style="margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 20px;">
        <div class="settings-section" style="background: transparent; border: 1px dashed var(--border-color);">
            <h3 style="border-bottom: none; margin-bottom: 10px;">📱 Application Mobile</h3>
            <p style="margin-bottom: 15px;">Utilisez l'application PWA pour envoyer des photos depuis votre mobile.</p>
            <a href="pwa/" target="_blank" class="btn" style="background-color: #6f42c1; color: white; text-decoration: none;">
                Ouvrir l'App PWA ↗
            </a>
        </div>
    </div>
</div>

<script>
// Mise à jour de l'aperçu en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['max_width', 'max_height', 'thumb_size', 'quality', 'max_file_size'];
    
    inputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updatePreview);
        }
    });
    
    function updatePreview() {
        const maxWidth = document.getElementById('max_width').value;
        const maxHeight = document.getElementById('max_height').value;
        const thumbSize = document.getElementById('thumb_size').value;
        const quality = document.getElementById('quality').value;
        const maxFileSize = document.getElementById('max_file_size').value;
        
        document.getElementById('preview-size').textContent = maxWidth + 'x' + maxHeight;
        document.getElementById('preview-thumb').textContent = thumbSize + 'x' + thumbSize;
        document.getElementById('preview-quality').textContent = quality;
        document.getElementById('preview-filesize').textContent = maxFileSize;
    }
});
</script>