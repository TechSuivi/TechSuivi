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
        $configs = [
            'cyber_price_nb_page' => $_POST['cyber_price_nb_page'] ?? '0.20',
            'cyber_price_color_page' => $_POST['cyber_price_color_page'] ?? '0.30',
            'cyber_price_time_base' => $_POST['cyber_price_time_base'] ?? '0.75',
            'cyber_price_time_minimum' => $_POST['cyber_price_time_minimum'] ?? '0.50',
            'cyber_time_minimum_threshold' => $_POST['cyber_time_minimum_threshold'] ?? '10',
            'cyber_time_increment' => $_POST['cyber_time_increment'] ?? '15'
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $success_message = "Configuration des tarifs cyber sauvegardée avec succès !";
    } catch (Exception $e) {
        $error_message = "Erreur lors de la sauvegarde : " . $e->getMessage();
    }
}

// Récupération des configurations actuelles
$current_configs = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'cyber_pricing'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_configs[$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    $error_message = "Erreur lors du chargement de la configuration : " . $e->getMessage();
}

// Valeurs par défaut si pas de configuration
$cyber_price_nb_page = $current_configs['cyber_price_nb_page'] ?? '0.20';
$cyber_price_color_page = $current_configs['cyber_price_color_page'] ?? '0.30';
$cyber_price_time_base = $current_configs['cyber_price_time_base'] ?? '0.75';
$cyber_price_time_minimum = $current_configs['cyber_price_time_minimum'] ?? '0.50';
$cyber_time_minimum_threshold = $current_configs['cyber_time_minimum_threshold'] ?? '10';
$cyber_time_increment = $current_configs['cyber_time_increment'] ?? '15';
?>

<div class="container">
    <h2>🖥️ Configuration Tarifs Cyber</h2>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="config-section">
        <p class="config-description">
            Configurez les tarifs appliqués pour les sessions cyber café et les impressions.
            Ces tarifs seront utilisés pour le calcul automatique des prix dans les nouvelles sessions.
        </p>
        
        <form method="POST" class="config-form">
            <!-- Tarifs impressions -->
            <div class="form-section">
                <h3>📄 Tarifs Impressions</h3>
                
                <div class="form-grid">
                    <div class="settings-card">
                        <div class="card-header-icon">💰</div>
                        <label for="cyber_price_nb_page">Prix page N&B</label>
                        <div class="input-wrapper">
                            <input type="number" 
                                   id="cyber_price_nb_page" 
                                   name="cyber_price_nb_page" 
                                   value="<?= htmlspecialchars($cyber_price_nb_page) ?>" 
                                   class="form-control"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.20"
                                   required>
                            <span class="currency-suffix">€</span>
                        </div>
                        <small class="form-help">Prix facturé par page imprimée en noir et blanc</small>
                    </div>
                    
                    <div class="settings-card">
                        <div class="card-header-icon">🌈</div>
                        <label for="cyber_price_color_page">Prix page Couleur</label>
                        <div class="input-wrapper">
                            <input type="number" 
                                   id="cyber_price_color_page" 
                                   name="cyber_price_color_page" 
                                   value="<?= htmlspecialchars($cyber_price_color_page) ?>" 
                                   class="form-control"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.30"
                                   required>
                            <span class="currency-suffix">€</span>
                        </div>
                        <small class="form-help">Prix facturé par page imprimée en couleur</small>
                    </div>
                </div>
            </div>
            
            <!-- Tarifs temps de connexion -->
            <div class="form-section">
                <h3>⏰ Tarifs Temps de Connexion</h3>
                
                <div class="form-grid">
                    <div class="settings-card">
                        <div class="card-header-icon">⚡</div>
                        <label for="cyber_price_time_minimum">Prix minimum</label>
                        <div class="input-wrapper">
                            <input type="number"
                                   id="cyber_price_time_minimum"
                                   name="cyber_price_time_minimum"
                                   value="<?= htmlspecialchars($cyber_price_time_minimum) ?>"
                                   class="form-control"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.50"
                                   required>
                            <span class="currency-suffix">€</span>
                        </div>
                        <small class="form-help">Prix minimum pour les sessions courtes</small>
                    </div>
                    
                    <div class="settings-card">
                        <div class="card-header-icon">⏱️</div>
                        <label for="cyber_time_minimum_threshold">Seuil session courte</label>
                        <div class="input-wrapper">
                            <input type="number"
                                   id="cyber_time_minimum_threshold"
                                   name="cyber_time_minimum_threshold"
                                   value="<?= htmlspecialchars($cyber_time_minimum_threshold) ?>"
                                   class="form-control"
                                   min="1"
                                   max="30"
                                   placeholder="10"
                                   required>
                            <span class="currency-suffix">min</span>
                        </div>
                        <small class="form-help">Durée maximum pour appliquer le prix minimum</small>
                    </div>

                    <div class="settings-card">
                        <div class="card-header-icon">📊</div>
                        <label for="cyber_time_increment">Incrément de temps</label>
                        <div class="input-wrapper">
                            <input type="number"
                                   id="cyber_time_increment"
                                   name="cyber_time_increment"
                                   value="<?= htmlspecialchars($cyber_time_increment) ?>"
                                   class="form-control"
                                   min="1"
                                   max="60"
                                   placeholder="15"
                                   required>
                             <span class="currency-suffix">min</span>
                        </div>
                        <small class="form-help">Tranche de temps pour la facturation (au-delà du seuil)</small>
                    </div>

                    <div class="settings-card">
                        <div class="card-header-icon">💸</div>
                        <label for="cyber_price_time_base">Prix par tranche</label>
                        <div class="input-wrapper">
                            <input type="number" 
                                   id="cyber_price_time_base" 
                                   name="cyber_price_time_base" 
                                   value="<?= htmlspecialchars($cyber_price_time_base) ?>" 
                                   class="form-control"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.75"
                                   required>
                             <span class="currency-suffix">€</span>
                        </div>
                        <small class="form-help">Prix facturé par tranche de temps (au-delà du minimum)</small>
                    </div>
                </div>
            </div>
            
            <!-- Simulation de calcul -->
            <div class="simulation-section">
                <h3>🧮 Simulation de Calcul</h3>
                <div class="simulation-grid">
                    <div class="simulation-card">
                        <h4>Exemple 1 : Session courte</h4>
                        <div class="simulation-details">
                            <p><strong>Durée :</strong> <span id="sim1_duree"><?= $cyber_time_minimum_threshold - 2 ?></span> minutes</p>
                            <p><strong>Calcul :</strong> Prix minimum (≤ <span id="sim1_seuil"><?= $cyber_time_minimum_threshold ?></span> min)</p>
                            <p class="simulation-result">Total : <span id="sim1_result"><?= number_format($cyber_price_time_minimum, 2) ?> €</span></p>
                        </div>
                    </div>
                    
                    <div class="simulation-card">
                        <h4>Exemple 2 : Session moyenne</h4>
                        <div class="simulation-details">
                            <p><strong>Durée :</strong> <span id="sim2_duree">35</span> minutes</p>
                            <p><strong>Calcul :</strong> <span id="sim2_tranches">3</span> tranches × <span id="sim2_prix"><?= $cyber_price_time_base ?></span> €</p>
                            <p class="simulation-result">Total : <span id="sim2_result"><?= number_format(ceil(35 / $cyber_time_increment) * $cyber_price_time_base, 2) ?> €</span></p>
                        </div>
                    </div>
                    
                    <div class="simulation-card">
                        <h4>Exemple 3 : Avec impressions</h4>
                        <div class="simulation-details">
                            <p><strong>Durée :</strong> <span id="sim3_duree">20</span> minutes</p>
                            <p><strong>Impressions :</strong> 5 N&B + 2 couleur</p>
                            <p><strong>Temps :</strong> <span id="sim3_temps"><?= number_format(ceil(20 / $cyber_time_increment) * $cyber_price_time_base, 2) ?></span> €</p>
                            <p><strong>Impressions :</strong> <span id="sim3_impressions"><?= number_format((5 * $cyber_price_nb_page) + (2 * $cyber_price_color_page), 2) ?></span> €</p>
                            <p class="simulation-result">Total : <span id="sim3_result"><?= number_format(ceil(20 / $cyber_time_increment) * $cyber_price_time_base + (5 * $cyber_price_nb_page) + (2 * $cyber_price_color_page), 2) ?> €</span></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="form-actions">
                <button type="submit" name="save_config" class="btn btn-primary">
                    💾 Sauvegarder la Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.config-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
    margin-top: 20px;
    border: 1px solid #dee2e6;
}

.config-description {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 4px;
    color: #1565c0;
    font-size: 14px;
}

.config-form {
    max-width: 800px;
}

.form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: var(--accent-color);
    font-size: 18px;
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-gap: 30px; /* Legacy support */
    gap: 30px;
    row-gap: 40px; /* Huge vertical gap to prevent overlap */
    margin-bottom: 30px;
}

.settings-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    /* height: 100%; Removed to let content dictate height naturally */
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.settings-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-color: var(--accent-color);
}

.settings-card label {
    font-weight: bold;
    color: var(--text-color);
    margin: 2px 0 5px 0; /* Minimal margin */
    font-size: 14px; /* Slightly smaller text */
}

.card-header-icon {
    font-size: 20px; /* Smaller icon */
    margin-bottom: 2px;
}

.input-wrapper {
    position: relative;
    margin-bottom: 8px; /* Reduced margin */
    width: 100%; /* Ensure wrapper takes full width */
}

.currency-suffix {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-weight: bold;
    pointer-events: none; /* Prevent interference with input */
}

.settings-card .form-control {
    width: 100%;
    box-sizing: border-box; /* Critical for padding calculation */
    padding-right: 40px; /* Space for suffix */
}

.settings-card .form-help {
    margin-top: auto;
    color: #6c757d;
    font-size: 12px; /* Smaller help text */
    line-height: 1.3;
    padding-top: 8px;
    padding-bottom: 0px;
    border-top: 1px dashed #dee2e6;
}

/* Dark mode adjustments */
body.dark .settings-card {
    background: #2c2c2c;
    border-color: #555; /* Visible border against #333 bg */
    box-shadow: 0 4px 6px rgba(0,0,0,0.2); /* Lift it up a bit */
}

body.dark .currency-suffix {
    color: #e0e0e0; /* Brighter suffix */
}

body.dark .settings-card .form-help {
    border-top-color: #555;
    color: #aaa;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(52, 144, 220, 0.1);
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 12px;
    font-style: italic;
}

.simulation-section {
    margin-top: 30px;
    padding: 20px;
    background: #f0f8ff;
    border-radius: 8px;
    border: 1px solid #b3d9ff;
}

.simulation-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #0066cc;
}

.simulation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.simulation-card {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #cce7ff;
}

.simulation-card h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #0066cc;
    font-size: 14px;
}

.simulation-details p {
    margin: 5px 0;
    font-size: 13px;
}

.simulation-result {
    font-weight: bold;
    color: #28a745;
    border-top: 1px solid #eee;
    padding-top: 8px;
    margin-top: 8px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(52, 144, 220, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
    color: white;
    text-decoration: none;
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: bold;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Mode sombre */
body.dark .config-section {
    background-color: #2c2c2c;
    border-color: #444;
}

body.dark .config-description {
    background-color: #1a365d;
    color: #90cdf4;
    border-left-color: #3182ce;
}

body.dark .form-section {
    background-color: #333;
    border-color: #555;
}

body.dark .form-control {
    background-color: #333;
    border-color: #555;
    color: var(--text-color-dark);
}

body.dark .form-control:focus {
    border-color: var(--accent-color);
}

body.dark .form-help {
    color: #aaa;
}

body.dark .simulation-section {
    background-color: #1a2332;
    border-color: #2d4a66;
}

body.dark .simulation-card {
    background-color: #333;
    border-color: #555;
}

body.dark .alert-success {
    background-color: #1e4d2b;
    color: #a3d9a5;
    border-color: #2d5a3d;
}

body.dark .alert-error {
    background-color: #4d1e1e;
    color: #f5a3a3;
    border-color: #5a2d2d;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .simulation-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>

<script>
// Mise à jour en temps réel des simulations
document.addEventListener('DOMContentLoaded', function() {
    const inputs = {
        nb_page: document.getElementById('cyber_price_nb_page'),
        color_page: document.getElementById('cyber_price_color_page'),
        time_base: document.getElementById('cyber_price_time_base'),
        time_minimum: document.getElementById('cyber_price_time_minimum'),
        time_increment: document.getElementById('cyber_time_increment')
    };
    
    function updateSimulations() {
        const prices = {
            nb_page: parseFloat(inputs.nb_page.value) || 0.20,
            color_page: parseFloat(inputs.color_page.value) || 0.30,
            time_base: parseFloat(inputs.time_base.value) || 0.75,
            time_minimum: parseFloat(inputs.time_minimum.value) || 0.50,
            time_increment: parseInt(inputs.time_increment.value) || 15
        };
        
        // Simulation 1 : 8 minutes (prix minimum)
        document.getElementById('sim1_result').textContent = prices.time_minimum.toFixed(2) + ' €';
        
        // Simulation 2 : 35 minutes
        const tranches2 = Math.ceil(35 / prices.time_increment);
        const total2 = tranches2 * prices.time_base;
        document.getElementById('sim2_tranches').textContent = tranches2;
        document.getElementById('sim2_prix').textContent = prices.time_base.toFixed(2);
        document.getElementById('sim2_result').textContent = total2.toFixed(2) + ' €';
        
        // Simulation 3 : 20 minutes + impressions
        const tranches3 = Math.ceil(20 / prices.time_increment);
        const temps3 = tranches3 * prices.time_base;
        const impressions3 = (5 * prices.nb_page) + (2 * prices.color_page);
        const total3 = temps3 + impressions3;
        document.getElementById('sim3_temps').textContent = temps3.toFixed(2);
        document.getElementById('sim3_impressions').textContent = impressions3.toFixed(2);
        document.getElementById('sim3_result').textContent = total3.toFixed(2) + ' €';
    }
    
    // Event listeners pour mise à jour en temps réel
    Object.values(inputs).forEach(input => {
        input.addEventListener('input', updateSimulations);
    });
    
    // Mise à jour initiale
    updateSimulations();
});
</script>