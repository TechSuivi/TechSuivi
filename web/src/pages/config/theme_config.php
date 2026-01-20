<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Fonction pour lister les thèmes disponibles
function listAvailableThemes() {
    $themesDir = __DIR__ . '/../../css/themes/';
    $themes = [];
    
    if (is_dir($themesDir)) {
        $scanned = scandir($themesDir);
        foreach ($scanned as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($themesDir . $item)) {
                $themes[] = $item;
            }
        }
    }
    return $themes;
}

// Traitement du formulaire
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    $selected_theme = $_POST['app_theme'] ?? 'default';
    
    // Validation basique (vérifier si le dossier existe)
    if (is_dir(__DIR__ . '/../../css/themes/' . $selected_theme)) {
        try {
            // Sauvegarde ou update
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = 'app_theme'");
            $stmt->execute([$selected_theme]);
            
            if ($stmt->rowCount() === 0) {
                 // Si pas de mise à jour, peut-être que la ligne n'existe pas
                 $check = $pdo->prepare("SELECT id FROM configuration WHERE config_key = 'app_theme'");
                 $check->execute();
                 if (!$check->fetch()) {
                     $insert = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('app_theme', ?, 'text', 'Thème visuel de l\'application', 'ui')");
                     $insert->execute([$selected_theme]);
                 }
            }
            echo "<script>window.location.href='index.php?page=theme_config&success=1';</script>";
            exit;
        } catch (Exception $e) {
            $error_message = "Erreur lors de la sauvegarde : " . $e->getMessage();
        }
    } else {
        $error_message = "Le thème sélectionné n'existe pas.";
    }
}

// Message de succès via GET
if (isset($_GET['success'])) {
    $success_message = "Thème mis à jour avec succès !";
}

// Récupération de la configuration actuelle
$current_theme = 'default';
try {
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'app_theme'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) $current_theme = $result['config_value'];
} catch (Exception $e) {
    // Silent fail, default to 'default'
}

$available_themes = listAvailableThemes();
?>

<div class="container">
    <h2>🎨 Personnalisation du Thème</h2>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="config-section">
        <p class="config-description">
            Choisissez l'apparence de l'application. Le thème détermine les couleurs et le style général.
        </p>
        
        <form method="POST" class="config-form">
            <div class="form-group">
                <label for="app_theme">Thème Actif</label>
                <select id="app_theme" name="app_theme" class="form-control">
                    <?php foreach ($available_themes as $theme): ?>
                        <option value="<?= htmlspecialchars($theme) ?>" <?= $current_theme === $theme ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($theme)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_theme" class="btn btn-primary">
                    💾 Enregistrer le thème
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Réutilisation des styles de gemini_config.php pour la cohérence */
.config-section {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 25px;
    margin-top: 20px;
    border: 1px solid var(--border-color);
}

.config-description {
    background: rgba(59, 130, 246, 0.1);
    border-left: 4px solid #3b82f6;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 4px;
    color: var(--text-color);
}

.config-form {
    max-width: 600px;
}
</style>
