<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

$message = '';
$current_offset = 0;

// Charger la configuration actuelle depuis la base de données
if (isset($pdo)) {
    try {
        // Créer la table de configuration si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_config (
            config_key VARCHAR(100) PRIMARY KEY,
            config_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Récupérer la configuration actuelle
        $stmt = $pdo->prepare("SELECT config_value FROM app_config WHERE config_key = 'timezone_offset'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            $current_offset = floatval($result['config_value']);
        }
    } catch (PDOException $e) {
        $message = '<p style="color: red;">Erreur lors de l\'accès à la configuration : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $new_offset = floatval($_POST['timezone_offset'] ?? 0);
    
    // Valider l'offset (entre -12 et +14 heures)
    if ($new_offset < -12 || $new_offset > 14) {
        $message = '<p style="color: red;">L\'offset doit être compris entre -12 et +14 heures.</p>';
    } else {
        try {
            // Sauvegarder la configuration dans la base de données
            $stmt = $pdo->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('timezone_offset', ?) 
                                   ON DUPLICATE KEY UPDATE config_value = ?, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$new_offset, $new_offset]);
            
            $current_offset = $new_offset;
            $message = '<p style="color: green;">Configuration du fuseau horaire sauvegardée avec succès !</p>';
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}

// Calculer l'heure actuelle avec l'offset
$utc_time = time();
$local_time = $utc_time + ($current_offset * 3600);
?>

<style>
.timezone-config {
    max-width: 600px;
    margin: 0 auto;
}

.time-display {
    background-color: var(--bg-secondary, #f8f9fa);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
}

.time-display h3 {
    margin-top: 0;
    color: var(--accent-color, #007bff);
}

.time-value {
    font-size: 24px;
    font-weight: bold;
    color: var(--text-color, #333);
    margin: 10px 0;
}

.offset-selector {
    margin: 20px 0;
}

.offset-selector label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
}

.offset-selector select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color, #ccc);
    border-radius: 4px;
    background-color: var(--input-bg, white);
    color: var(--text-color, #333);
    font-size: 16px;
}

.timezone-info {
    background-color: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}

.timezone-info h4 {
    margin-top: 0;
    color: #0066cc;
}

/* Mode sombre */
body.dark .time-display {
    background-color: #2b2b2b;
    border-color: #555;
}

body.dark .time-value {
    color: #fff;
}

body.dark .offset-selector select {
    background-color: #1a1a1a;
    border-color: #555;
    color: #fff;
}

body.dark .timezone-info {
    background-color: #1a3a5c;
    border-color: #4dabf7;
}

body.dark .timezone-info h4 {
    color: #4dabf7;
}
</style>

<div class="timezone-config">
    <h1>⏰ Configuration du Fuseau Horaire</h1>
    
    <?php echo $message; ?>
    
    <?php if (!isset($pdo)): ?>
        <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0; color: #721c24;">
            <h4>❌ Erreur de connexion</h4>
            <p>La configuration du fuseau horaire nécessite une connexion à la base de données.</p>
        </div>
    <?php else: ?>
    
    <div class="time-display">
        <h3>🌍 Heure Actuelle</h3>
        <div class="time-value">
            <?= date('d/m/Y à H:i:s', $utc_time) ?> (UTC)
        </div>
        <div class="time-value">
            <?= date('d/m/Y à H:i:s', $local_time) ?> (Local)
        </div>
        <p>Décalage configuré : <?= $current_offset >= 0 ? '+' : '' ?><?= $current_offset ?> heures</p>
    </div>
    
    <div class="timezone-info">
        <h4>ℹ️ Information</h4>
        <p>Cette configuration permet de corriger l'affichage des heures dans l'application lorsque le serveur utilise un fuseau horaire différent de votre fuseau horaire local.</p>
        <p><strong>Exemples courants :</strong></p>
        <ul>
            <li><strong>France (heure d'hiver) :</strong> +1 heure</li>
            <li><strong>France (heure d'été) :</strong> +2 heures</li>
            <li><strong>Belgique/Suisse :</strong> +1 ou +2 heures selon la saison</li>
            <li><strong>Canada (EST) :</strong> -5 heures</li>
        </ul>
    </div>
    
    <form method="POST" style="margin-top: 30px;">
        <div class="offset-selector">
            <label for="timezone_offset">Décalage horaire (en heures) :</label>
            <select id="timezone_offset" name="timezone_offset" required>
                <?php for ($i = -12; $i <= 14; $i += 0.5): ?>
                    <option value="<?= $i ?>" <?= $i == $current_offset ? 'selected' : '' ?>>
                        <?= $i >= 0 ? '+' : '' ?><?= $i ?> heures
                        <?php if ($i == 1): ?> (France hiver)<?php endif; ?>
                        <?php if ($i == 2): ?> (France été)<?php endif; ?>
                        <?php if ($i == 0): ?> (UTC)<?php endif; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" style="padding: 12px 30px; background-color: var(--accent-color); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                💾 Sauvegarder la Configuration
            </button>
            <a href="index.php?page=settings&tab=system" style="margin-left: 15px; text-decoration: none; padding: 12px 20px; background-color: #6c757d; color: white; border-radius: 4px; display: inline-block;">
                ← Retour aux Paramètres
            </a>
        </div>
    </form>
    
    <?php endif; ?>
    
    <div style="margin-top: 30px; text-align: center;">
        <a href="test_timezone.php" target="_blank" style="color: var(--accent-color); text-decoration: underline;">
            🔍 Tester la Configuration du Fuseau Horaire
        </a>
    </div>
</div>

<script>
// Mise à jour en temps réel de l'heure
function updateTime() {
    const offset = parseFloat(document.getElementById('timezone_offset').value);
    // Optionnel : mise à jour dynamique de l'affichage
}

document.getElementById('timezone_offset').addEventListener('change', updateTime);
</script>