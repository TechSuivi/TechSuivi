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
            'company_name' => $_POST['company_name'] ?? '',
            'intervention_tarifs' => $_POST['intervention_tarifs'] ?? '',
            'intervention_cgv' => $_POST['intervention_cgv'] ?? '',
            'intervention_verifications' => $_POST['intervention_verifications'] ?? ''
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $pdo->prepare("UPDATE configuration SET config_value = ?, updated_at = NOW() WHERE config_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $success_message = "Configuration sauvegardée avec succès !";
    } catch (Exception $e) {
        $error_message = "Erreur lors de la sauvegarde : " . $e->getMessage();
    }
}

// Récupération des configurations actuelles
$current_configs = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'intervention_sheet'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_configs[$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    $error_message = "Erreur lors du chargement de la configuration : " . $e->getMessage();
}

// Valeurs par défaut si pas de configuration
$company_name = $current_configs['company_name'] ?? 'QLE INFORMATIQUE';
$intervention_tarifs = $current_configs['intervention_tarifs'] ?? '';
$intervention_cgv = $current_configs['intervention_cgv'] ?? '';
$intervention_verifications = $current_configs['intervention_verifications'] ?? '';

// Récupérer une intervention existante pour la prévisualisation
$sample_intervention_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM inter ORDER BY date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $sample_intervention_id = $result['id'];
    }
} catch (Exception $e) {
    // Pas d'intervention trouvée
}
?>

<div class="container">
    <h2>📄 Configuration Feuille d'Intervention</h2>
    
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
            Configurez les éléments qui apparaîtront sur la feuille de prise en charge d'intervention.
            Ces modifications seront appliquées à toutes les nouvelles impressions.
        </p>
        
        <form method="POST" class="config-form">
            <!-- Nom de la société -->
            <div class="form-group">
                <label for="company_name">🏢 Nom de la Société</label>
                <input type="text" 
                       id="company_name" 
                       name="company_name" 
                       value="<?= htmlspecialchars($company_name) ?>" 
                       class="form-control"
                       placeholder="Ex: QLE INFORMATIQUE"
                       required>
                <small class="form-help">Nom affiché en en-tête de la feuille d'intervention</small>
            </div>
            
            <!-- Tarifs -->
            <div class="form-group">
                <label for="intervention_tarifs">💰 Tarifs</label>
                <textarea id="intervention_tarifs" 
                          name="intervention_tarifs" 
                          class="form-control textarea-large"
                          rows="12"
                          placeholder="TARIFS : FRAIS DE PRISE EN CHARGE = 30 €&#10;Petit Dépannage : 10 € TTC&#10;..."
                          required><?= htmlspecialchars($intervention_tarifs) ?></textarea>
                <small class="form-help">Liste des tarifs affichés sur la feuille (une ligne par tarif)</small>
            </div>
            
            <!-- Conditions Générales de Vente -->
            <div class="form-group">
                <label for="intervention_cgv">📋 Conditions Générales de Vente</label>
                <textarea id="intervention_cgv" 
                          name="intervention_cgv" 
                          class="form-control textarea-large"
                          rows="10"
                          placeholder="CONDITIONS GÉNÉRALES DE VENTE&#10;1. ..."
                          required><?= htmlspecialchars($intervention_cgv) ?></textarea>
                <small class="form-help">Conditions générales affichées sur la feuille</small>
            </div>
            
            <!-- Vérifications effectuées -->
            <div class="form-group">
                <label for="intervention_verifications">✅ Vérifications Effectuées</label>
                <textarea id="intervention_verifications" 
                          name="intervention_verifications" 
                          class="form-control textarea-medium"
                          rows="8"
                          placeholder="SEATOOLS&#10;MEMTEST&#10;ADW/ROGUE/MBAM/ESET&#10;..."
                          required><?= htmlspecialchars($intervention_verifications) ?></textarea>
                <small class="form-help">Liste des vérifications (une par ligne)</small>
            </div>
            
            <!-- Boutons d'action -->
            <div class="form-actions">
                <button type="submit" name="save_config" class="btn btn-primary">
                    💾 Sauvegarder la Configuration
                </button>
                <?php if ($sample_intervention_id): ?>
                    <a href="print_intervention.php?id=<?= htmlspecialchars($sample_intervention_id) ?>" target="_blank" class="btn btn-secondary">
                        👁️ Prévisualiser une Feuille
                    </a>
                <?php else: ?>
                    <span class="btn btn-disabled" title="Aucune intervention disponible pour la prévisualisation">
                        👁️ Prévisualiser une Feuille
                    </span>
                <?php endif; ?>
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

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: var(--accent-color);
    font-size: 16px;
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

.textarea-large {
    min-height: 200px;
    font-family: 'Courier New', monospace;
    line-height: 1.4;
}

.textarea-medium {
    min-height: 150px;
    font-family: 'Courier New', monospace;
    line-height: 1.4;
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 12px;
    font-style: italic;
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
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>