<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Inclure le système de permissions
require_once __DIR__ . '/../../utils/permissions_helper.php';

require_once 'config/database.php';
require_once 'utils/mail_helper.php';

$message = '';
$messageType = '';

// Initialiser la connexion à la base de données
$pdo = getDatabaseConnection();

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $from_email = $_POST['from_email'] ?? '';
        $from_name = $_POST['from_name'] ?? '';
        $reports_enabled = isset($_POST['reports_enabled']) ? 1 : 0;
        $report_frequency = $_POST['report_frequency'] ?? 'weekly';
        $report_recipients = $_POST['report_recipients'] ?? '';
        
        // Convertir les destinataires en JSON
        $recipients_array = array_filter(array_map('trim', explode(',', $report_recipients)));
        $report_recipients_json = json_encode($recipients_array);
        
        // Vérifier si une configuration existe déjà
        $stmt = $pdo->query("SELECT COUNT(*) FROM mail_config");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Mise à jour
            $stmt = $pdo->prepare("
                UPDATE mail_config SET
                smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?,
                smtp_encryption = ?, from_email = ?, from_name = ?,
                reports_enabled = ?, report_frequency = ?, report_recipients = ?
                WHERE id = 1
            ");
            $stmt->execute([
                $smtp_host, $smtp_port, $smtp_username, $smtp_password,
                $smtp_encryption, $from_email, $from_name,
                $reports_enabled, $report_frequency, $report_recipients_json
            ]);
        } else {
            // Insertion
            $stmt = $pdo->prepare("
                INSERT INTO mail_config
                (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption,
                 from_email, from_name, reports_enabled, report_frequency, report_recipients)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption,
                $from_email, $from_name, $reports_enabled, $report_frequency, $report_recipients_json
            ]);
        }
        
        $message = 'Configuration mail sauvegardée avec succès !';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer la configuration actuelle
$config = null;
try {
    // Vérifier d'abord si la table existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'mail_config'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Créer automatiquement la table
        $createTableSQL = "
        CREATE TABLE mail_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            smtp_host VARCHAR(255) NOT NULL,
            smtp_port INT NOT NULL DEFAULT 587,
            smtp_username VARCHAR(255) NOT NULL,
            smtp_password VARCHAR(255) NOT NULL,
            smtp_encryption ENUM('none', 'tls', 'ssl') NOT NULL DEFAULT 'tls',
            from_name VARCHAR(255) NOT NULL,
            from_email VARCHAR(255) NOT NULL,
            reports_enabled BOOLEAN NOT NULL DEFAULT FALSE,
            report_frequency ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
            report_recipients TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSQL);
        $message = 'Table mail_config créée automatiquement avec succès !';
        $messageType = 'success';
    }
    
    // Récupérer la configuration
    $stmt = $pdo->query("SELECT * FROM mail_config ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Décoder les destinataires JSON
    if ($config && !empty($config['report_recipients'])) {
        $recipients_array = json_decode($config['report_recipients'], true);
        if (is_array($recipients_array)) {
            $config['report_recipients_string'] = implode(', ', $recipients_array);
        } else {
            $config['report_recipients_string'] = '';
        }
    } else {
        // S'assurer que $config est un array avant d'ajouter des clés
        if (!is_array($config)) {
            $config = [];
        }
        $config['report_recipients_string'] = '';
    }
    
} catch (Exception $e) {
    $message = 'Erreur lors du chargement de la configuration : ' . $e->getMessage();
    $messageType = 'error';
}

// Valeurs par défaut si pas de configuration
if (!$config) {
    $config = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'TechSuivi',
        'reports_enabled' => 0,
        'report_frequency' => 'weekly',
        'report_recipients_string' => ''
    ];
} else {
    // S'assurer que toutes les clés existent avec des valeurs par défaut
    $config = array_merge([
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'TechSuivi',
        'reports_enabled' => 0,
        'report_frequency' => 'weekly',
        'report_recipients_string' => ''
    ], $config ?: []);
}

// Obtenir des informations sur la méthode d'envoi
$mailHelper = new MailHelper();
$mailMethod = $mailHelper->getMailMethod();
?>

<style>
/* Modern Theme for Mail Config */
.mail-config-page {
    background: var(--bg-color);
    color: var(--text-color);
}

.page-header {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(108, 92, 231, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header h1 {
    margin: 0;
    font-size: 1.6em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.config-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color);
    transition: transform 0.2s, box-shadow 0.2s;
}

.config-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.config-section-title {
    font-size: 1.1em;
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--accent-color);
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.form-label {
    font-weight: 500;
    color: var(--text-color);
    margin-bottom: 8px;
}

.form-control, .form-select {
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    border-radius: 8px;
    padding: 10px 15px;
    transition: all 0.2s;
}

.form-control:focus, .form-select:focus {
    border-color: #6c5ce7;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
    background-color: var(--input-bg);
    color: var(--text-color);
}

.btn-save {
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.4);
    color: white;
}

.btn-test {
    background: var(--card-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-test:hover {
    background: var(--hover-bg);
    border-color: #6c5ce7;
    color: #6c5ce7;
}

.info-box {
    background: rgba(108, 92, 231, 0.05);
    border-left: 4px solid #6c5ce7;
    padding: 15px;
    border-radius: 0 8px 8px 0;
    margin-bottom: 20px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.status-success {
    background: rgba(46, 204, 113, 0.15);
    color: #27ae60;
}

.status-warning {
    background: rgba(241, 196, 15, 0.15);
    color: #f39c12;
}
</style>

<div class="mail-config-page">
    <div class="page-header">
        <h1>
            <span>📧</span>
            Configuration Mail
        </h1>
        <div class="header-actions">
            <span class="status-badge <?= isset($mailMethod['install_instructions']) ? 'status-warning' : 'status-success' ?>">
                <?= isset($mailMethod['install_instructions']) ? '⚠️ Configuration requise' : '✅ Service actif' ?>
            </span>
        </div>
    </div>

    <div class="container-fluid p-0">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" style="border-radius: 12px;">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Colonne Gauche : Paramètres SMTP -->
                <div class="col-lg-8">
                    <div class="config-card">
                        <div class="config-section-title">
                            <span>🔧</span> Paramètres SMTP
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="smtp_host" class="form-label">Serveur SMTP *</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                           value="<?= htmlspecialchars($config['smtp_host']) ?>" 
                                           placeholder="smtp.gmail.com" required>
                                    <div class="form-text">Exemple: smtp.gmail.com, smtp.outlook.com</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="smtp_port" class="form-label">Port SMTP *</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?= $config['smtp_port'] ?>" min="1" max="65535" required>
                                    <div class="form-text">587 (TLS) ou 465 (SSL)</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_username" class="form-label">Nom d'utilisateur *</label>
                                    <input type="email" class="form-control" id="smtp_username" name="smtp_username" 
                                           value="<?= htmlspecialchars($config['smtp_username']) ?>" 
                                           placeholder="votre-email@example.com" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                           value="<?= htmlspecialchars($config['smtp_password']) ?>" 
                                           placeholder="Mot de passe ou mot de passe d'application" required>
                                    <div class="form-text">Pour Gmail, utilisez un mot de passe d'application</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="smtp_encryption" class="form-label">Chiffrement</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                <option value="none" <?= $config['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Aucun</option>
                                <option value="tls" <?= $config['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS (recommandé)</option>
                                <option value="ssl" <?= $config['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Colonne Droite : Expéditeur & Actions -->
                <div class="col-lg-4">


                    <div class="config-card">
                        <div class="config-section-title">
                            <span>👤</span> Expéditeur
                        </div>
                        
                        <div class="mb-3">
                            <label for="from_email" class="form-label">Email expéditeur *</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                   value="<?= htmlspecialchars($config['from_email']) ?>" 
                                   placeholder="noreply@votre-domaine.com" required>
                        </div>

                        <div class="mb-3">
                            <label for="from_name" class="form-label">Nom expéditeur *</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                   value="<?= htmlspecialchars($config['from_name']) ?>" 
                                   placeholder="TechSuivi" required>
                        </div>
                    </div>

                    <div class="config-card">
                        <div class="config-section-title">
                            <span>ℹ️</span> État du service
                        </div>
                        
                        <div class="info-box">
                            <strong>Méthode :</strong> <?= htmlspecialchars($mailMethod['method']) ?><br>
                            <small><?= htmlspecialchars($mailMethod['description']) ?></small>
                        </div>

                        <?php if (isset($mailMethod['install_instructions'])): ?>
                            <div class="alert alert-warning" style="font-size: 0.9em;">
                                <strong>💡 Recommandation :</strong><br>
                                Installez PHPMailer pour une meilleure fiabilité.<br>
                                <a href="#" onclick="showInstallInstructions()" style="color: inherit; text-decoration: underline;">Voir instructions</a>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn-save">
                                💾 Sauvegarder
                            </button>
                            <button type="button" class="btn-test" onclick="testMailConfig()">
                                🧪 Tester config
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Fonction de test de configuration mail
function testMailConfig() {
    const testEmail = prompt('Entrez l\'adresse email pour le test:', '<?= htmlspecialchars($config['from_email']) ?>');
    if (!testEmail) return;
    
    if (!validateEmail(testEmail)) {
        showAlert('Adresse email invalide', 'danger');
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Test en cours...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'test_config');
    formData.append('test_email', testEmail);
    
    fetch('api/mail_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Erreur lors du test : ' + error.message, 'danger');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}



// Fonction utilitaire pour valider les emails
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Fonction utilitaire pour afficher les alertes
function showAlert(message, type) {
    // Supprimer les alertes existantes
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Créer la nouvelle alerte
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.borderRadius = '12px';
    alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insérer l'alerte au début du container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-supprimer après 5 secondes
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Validation du formulaire
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Afficher les instructions d'installation PHPMailer
function showInstallInstructions() {
    const instructions = <?= json_encode($mailMethod['install_instructions'] ?? []) ?>;
    
    let content = '<h4 style="margin-bottom: 20px;">Instructions d\'installation PHPMailer</h4>';
    
    if (instructions.composer) {
        content += '<h5 style="color: #6c5ce7;">' + instructions.composer.title + '</h5>';
        content += '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e9ecef;"><code>';
        instructions.composer.commands.forEach(cmd => {
            content += cmd + '<br>';
        });
        content += '</code></div>';
        content += '<p>' + instructions.composer.description + '</p><hr style="margin: 20px 0; opacity: 0.1;">';
    }
    
    if (instructions.manual) {
        content += '<h5 style="color: #6c5ce7;">' + instructions.manual.title + '</h5>';
        instructions.manual.steps.forEach(step => {
            content += '<p>• ' + step + '</p>';
        });
        content += '<p><strong>Fichiers requis :</strong></p><ul style="background: #f8f9fa; padding: 15px 15px 15px 35px; border-radius: 8px; border: 1px solid #e9ecef;">';
        instructions.manual.files.forEach(file => {
            content += '<li><code>' + file + '</code></li>';
        });
        content += '</ul><hr style="margin: 20px 0; opacity: 0.1;">';
    }
    
    // Créer une modal moderne
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); z-index: 1000; display: flex;
        align-items: center; justify-content: center; backdrop-filter: blur(5px);
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: var(--card-bg, white); padding: 30px; border-radius: 16px;
        max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; 
        color: var(--text-color, black); box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    `;
    
    modalContent.innerHTML = content + '<div style="text-align: right; margin-top: 20px;"><button onclick="this.closest(\'.modal-overlay\').remove()" class="btn-save" style="border: none; cursor: pointer;">Fermer</button></div>';
    modal.className = 'modal-overlay';
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    /* Fermer en cliquant à l'extérieur - DESACTIVE à la demande de l'utilisateur
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    */
}
</script>