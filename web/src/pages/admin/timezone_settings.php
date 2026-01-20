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
        $message = '<div class="alert alert-error">Erreur lors de l\'accès à la configuration : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $new_offset = floatval($_POST['timezone_offset'] ?? 0);
    
    // Valider l'offset (entre -12 et +14 heures)
    if ($new_offset < -12 || $new_offset > 14) {
        $message = '<div class="alert alert-error">L\'offset doit être compris entre -12 et +14 heures.</div>';
    } else {
        try {
            // Sauvegarder la configuration dans la base de données
            $stmt = $pdo->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('timezone_offset', ?) 
                                   ON DUPLICATE KEY UPDATE config_value = ?, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$new_offset, $new_offset]);
            
            $current_offset = $new_offset;
            $message = '<div class="alert alert-success">Configuration du fuseau horaire sauvegardée avec succès !</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Calculer l'heure actuelle avec l'offset
$utc_time = time();
$local_time = $utc_time + ($current_offset * 3600);
?>

<div class="max-w-600 container-center">
    <div class="page-header">
        <h1>⏰ Configuration du Fuseau Horaire</h1>
    </div>
    
    <?php echo $message; ?>
    
    <?php if (!isset($pdo)): ?>
        <div class="alert alert-error mb-20">
            <h4 class="mt-0 text-danger">❌ Erreur de connexion</h4>
            <p>La configuration du fuseau horaire nécessite une connexion à la base de données.</p>
        </div>
    <?php else: ?>
    
    <div class="card mb-20 text-center">
        <h3 class="card-title text-primary mb-15">🌍 Heure Actuelle</h3>
        <div class="text-2xl font-bold my-10">
            <?= date('d/m/Y à H:i:s', $utc_time) ?> (UTC)
        </div>
        <div class="text-2xl font-bold my-10 text-success">
            <?= date('d/m/Y à H:i:s', $local_time) ?> (Local)
        </div>
        <p class="text-muted">Décalage configuré : <?= $current_offset >= 0 ? '+' : '' ?><?= $current_offset ?> heures</p>
    </div>
    
    <div class="alert alert-info mb-30">
        <h4 class="mt-0 text-info">ℹ️ Information</h4>
        <p>Cette configuration permet de corriger l'affichage des heures dans l'application lorsque le serveur utilise un fuseau horaire différent de votre fuseau horaire local.</p>
        <p><strong>Exemples courants :</strong></p>
        <ul class="pl-20">
            <li><strong>France (heure d'hiver) :</strong> +1 heure</li>
            <li><strong>France (heure d'été) :</strong> +2 heures</li>
            <li><strong>Belgique/Suisse :</strong> +1 ou +2 heures selon la saison</li>
            <li><strong>Canada (EST) :</strong> -5 heures</li>
        </ul>
    </div>
    
    <form method="POST" class="card mt-30">
        <div class="mb-20">
            <label for="timezone_offset" class="font-bold block mb-10">Décalage horaire (en heures) :</label>
            <select id="timezone_offset" name="timezone_offset" required class="form-control">
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
        
        <div class="text-center mt-20 flex justify-center gap-15">
            <button type="submit" class="btn btn-primary">
                💾 Sauvegarder la Configuration
            </button>
            <a href="index.php?page=settings&tab=system" class="btn btn-secondary">
                ← Retour aux Paramètres
            </a>
        </div>
    </form>
    
    <?php endif; ?>
    
    <div class="text-center mt-30">
        <a href="test_timezone.php" target="_blank" class="text-primary underline">
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