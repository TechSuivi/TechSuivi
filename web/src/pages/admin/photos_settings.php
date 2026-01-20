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
            $message = '<div class="alert alert-success">✅ Installation réussie ! Tables créées et dossier d\'upload configuré.</div>';
            $message .= '<div class="alert alert-info">ℹ️ ' . htmlspecialchars($result['message']) . '</div>';
        } else {
            $message = '<div class="alert alert-error">❌ Problème lors de la création du dossier d\'upload.</div>';
            $message .= '<div class="alert alert-warning">⚠️ ' . htmlspecialchars($result['message']) . '</div>';
            $message .= '<div class="alert alert-error">' . getPermissionErrorMessage('installation du système de photos', $uploadDir) . '</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-error">❌ Erreur lors de l\'installation : ' . htmlspecialchars($e->getMessage()) . '</div>';
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
            
            $message = '<div class="alert alert-success">✅ Paramètres sauvegardés avec succès !</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">❌ Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">❌ ' . implode('<br>', $errors) . '</div>';
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
        $message = '<div class="alert alert-success">✅ Système entièrement fonctionnel ! Tous les composants sont opérationnels.</div>';
    } else {
        $message = '<div class="alert alert-warning">⚠️ Certains composants nécessitent une attention. Voir les détails ci-dessous.</div>';
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

<div class="page-header">
    <h1>Paramètres des Photos</h1>
</div>

<?php echo $message; ?>

<form method="POST">
    <input type="hidden" name="save_settings" value="1">
    
    <div class="card mb-20">
        <h3 class="card-title text-primary mb-15">Redimensionnement des Images</h3>
        
        <div class="grid-2 mb-15">
            <div class="mb-15">
                <label for="max_width" class="font-bold mb-5 block">Largeur maximale (pixels)</label>
                <input type="number" id="max_width" name="max_width" class="form-control" 
                       value="<?= htmlspecialchars($settings['max_width']) ?>" 
                       min="100" max="4000" required>
                <div class="text-xs text-muted mt-5">Les images plus larges seront redimensionnées</div>
            </div>
            
            <div class="mb-15">
                <label for="max_height" class="font-bold mb-5 block">Hauteur maximale (pixels)</label>
                <input type="number" id="max_height" name="max_height" class="form-control" 
                       value="<?= htmlspecialchars($settings['max_height']) ?>" 
                       min="100" max="4000" required>
                <div class="text-xs text-muted mt-5">Les images plus hautes seront redimensionnées</div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="mb-15">
                <label for="thumb_size" class="font-bold mb-5 block">Taille des miniatures (pixels)</label>
                <input type="number" id="thumb_size" name="thumb_size" class="form-control" 
                       value="<?= htmlspecialchars($settings['thumb_size']) ?>" 
                       min="50" max="500" required>
                <div class="text-xs text-muted mt-5">Taille carrée des miniatures (ex: 300x300)</div>
            </div>
            
            <div class="mb-15">
                <label for="quality" class="font-bold mb-5 block">Qualité JPEG (%)</label>
                <input type="number" id="quality" name="quality" class="form-control" 
                       value="<?= htmlspecialchars($settings['quality']) ?>" 
                       min="10" max="100" required>
                <div class="text-xs text-muted mt-5">Qualité de compression (85% recommandé)</div>
            </div>
        </div>
    </div>
    
    <div class="card mb-20">
        <h3 class="card-title text-primary mb-15">Limites de Fichiers</h3>
        
        <div class="mb-15">
            <label for="max_file_size" class="font-bold mb-5 block">Taille maximale par fichier (MB)</label>
            <input type="number" id="max_file_size" name="max_file_size" class="form-control" 
                   value="<?= htmlspecialchars($settings['max_file_size']) ?>" 
                   min="1" max="50" required>
            <div class="text-xs text-muted mt-5">Taille maximale autorisée pour l'upload</div>
        </div>
    </div>
    
    <div class="card mb-20">
        <h3 class="card-title text-primary mb-15">Aperçu des Paramètres</h3>
        <div class="bg-light p-15 border rounded-4">
            <p class="m-0 mb-5"><strong>Images redimensionnées à :</strong> <span id="preview-size"><?= $settings['max_width'] ?>x<?= $settings['max_height'] ?></span> pixels maximum</p>
            <p class="m-0 mb-5"><strong>Miniatures :</strong> <span id="preview-thumb"><?= $settings['thumb_size'] ?>x<?= $settings['thumb_size'] ?></span> pixels</p>
            <p class="m-0 mb-5"><strong>Qualité :</strong> <span id="preview-quality"><?= $settings['quality'] ?></span>%</p>
            <p class="m-0"><strong>Taille max :</strong> <span id="preview-filesize"><?= $settings['max_file_size'] ?></span> MB</p>
        </div>
    </div>
    
    <div class="mt-20 flex gap-15 items-center">
        <button type="submit" class="btn btn-primary">
                💾 Sauvegarder les Paramètres
        </button>
        <a href="index.php?page=interventions_list" class="btn btn-sm-action">
            ← Retour aux Interventions
        </a>
    </div>
</form>

<div class="mt-30 border-t pt-20">
    <div class="card" style="background: transparent; border: 1px dashed var(--border-color);">
        <h3 class="card-title mb-10">📱 Application Mobile</h3>
        <p class="mb-15">Utilisez l'application PWA pour envoyer des photos depuis votre mobile.</p>
        <a href="pwa/" target="_blank" class="btn" style="background-color: #6f42c1; color: white;">
            Ouvrir l'App PWA ↗
        </a>
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