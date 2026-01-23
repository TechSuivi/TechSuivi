<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    try {
        $presets = $_POST['stock_margin_presets'] ?? '20,30,40,50';
        
        // Nettoyage : garder uniquement les chiffres et virgules
        $presets = preg_replace('/[^0-9,.]/', '', $presets);
        
        // Vérifier si la clé existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuration WHERE config_key = 'stock_margin_presets'");
        $stmt->execute();
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = 'stock_margin_presets'");
            $stmt->execute([$presets]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuration (config_key, config_value, config_type, description, category) VALUES ('stock_margin_presets', ?, 'text', 'Marges prédéfinies pour le stock', 'stock')");
            $stmt->execute([$presets]);
        }
        
        $success_message = "Configuration des marges sauvegardée avec succès !";
    } catch (Exception $e) {
        $error_message = "Erreur lors de la sauvegarde : " . $e->getMessage();
    }
}

// Récupération de la configuration actuelle
$stock_margin_presets = '20,30,40,50'; // Valeur par défaut
try {
    $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'stock_margin_presets'");
    $stmt->execute();
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $stock_margin_presets = $res['config_value'];
    }
} catch (Exception $e) {
    // Erreur silencieuse, on garde la défaut
}
?>

<div class="container container-center max-w-800">
    <div class="page-header">
        <h1>📦 Configuration Stock</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success mb-20">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger mb-20">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="card p-20 shadow-sm border">
        <div class="mb-20 p-15 bg-info-light border-l-4 border-info text-info-dark rounded">
            Configurez ici les boutons de raccourcis pour les marges qui apparaîtront sur la page d'ajout de stock.
        </div>
        
        <form method="POST">
            <div class="mb-25">
                <label for="stock_margin_presets" class="block mb-10 font-bold text-lg">Marges prédéfinies (%)</label>
                <input type="text" 
                       id="stock_margin_presets" 
                       name="stock_margin_presets" 
                       value="<?= htmlspecialchars($stock_margin_presets) ?>" 
                       class="form-control w-full p-15 border rounded bg-input text-lg font-mono"
                       placeholder="Ex: 10,20,30,50,100"
                       required>
                <small class="text-muted block mt-10">Séparez les valeurs par des virgules (ex: 20, 30, 50, 100). <strong>La première valeur sera sélectionnée par défaut.</strong></small>
            </div>
            
            <div class="border-t border-border pt-20">
                <button type="submit" name="save_config" class="btn btn-primary flex items-center gap-10">
                    <span>💾</span> Sauvegarder
                </button>
            </div>
        </form>
    </div>

    <!-- Aperçu -->
    <div class="mt-30">
        <h3 class="text-muted mb-15 text-sm uppercase font-bold">Aperçu en temps réel</h3>
        <div class="card p-20 flex flex-wrap gap-10 justify-center border-dashed border-2 border-border">
            <?php 
            $margins = explode(',', $stock_margin_presets);
            // Nettoyage et tri pour l'aperçu
            $marginsClean = [];
            foreach($margins as $m) {
                if(trim($m) !== '' && is_numeric(trim($m))) $marginsClean[] = floatval(trim($m));
            }
            sort($marginsClean);
            
            foreach ($marginsClean as $margin): 
            ?>
                <button type="button" class="btn btn-sm btn-secondary pointer-events-none">
                    <?= htmlspecialchars($margin) ?>%
                </button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-secondary opacity-50 pointer-events-none">...</button>
        </div>
    </div>
</div>
