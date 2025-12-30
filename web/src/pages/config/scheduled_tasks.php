<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Vérification simple des permissions (admin seulement)
// Dans un vrai système, vous pourriez avoir une vérification plus sophistiquée
if (!isset($_SESSION['username'])) {
    echo "<div class='alert alert-danger'>Accès refusé. Connexion requise.</div>";
    return;
}

// Créer les tables si elles n'existent pas
try {
    // Table des tâches programmées
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS scheduled_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        task_type ENUM('report', 'notification', 'backup_reminder', 'custom') DEFAULT 'custom',
        frequency_type ENUM('once', 'daily', 'weekly', 'monthly', 'custom_cron') NOT NULL,
        frequency_value VARCHAR(100),
        recipients TEXT NOT NULL,
        content_template TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        last_executed DATETIME NULL,
        next_execution DATETIME NULL,
        execution_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        report_template_id INT NULL,
        FOREIGN KEY (report_template_id) REFERENCES report_templates(id) ON DELETE SET NULL
    )";
    $pdo->exec($createTableSQL);

    // Vérifier si la colonne report_template_id existe, sinon l'ajouter
    $columns = $pdo->query("SHOW COLUMNS FROM scheduled_tasks LIKE 'report_template_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE scheduled_tasks ADD COLUMN report_template_id INT NULL");
        $pdo->exec("ALTER TABLE scheduled_tasks ADD CONSTRAINT fk_scheduled_tasks_report_template FOREIGN KEY (report_template_id) REFERENCES report_templates(id) ON DELETE SET NULL");
    }
    $pdo->exec($createTableSQL);
    
    // Table des logs d'envoi des mails
    $createMailLogsTableSQL = "
    CREATE TABLE IF NOT EXISTS scheduled_tasks_mail_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        status ENUM('success', 'failed') NOT NULL,
        error_message TEXT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        execution_time_ms INT DEFAULT 0,
        mail_size_bytes INT DEFAULT 0,
        INDEX idx_task_id (task_id),
        INDEX idx_sent_at (sent_at),
        INDEX idx_status (status),
        FOREIGN KEY (task_id) REFERENCES scheduled_tasks(id) ON DELETE CASCADE
    )";
    $pdo->exec($createMailLogsTableSQL);
    
    // Pas d'insertion de données d'exemple
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erreur lors de la création de la table : " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Récupérer les tâches existantes
try {
    $stmt = $pdo->query("SELECT * FROM scheduled_tasks ORDER BY created_at DESC");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tasks = [];
    echo "<div class='alert alert-danger'>Erreur lors de la récupération des tâches : " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Récupérer les modèles de rapports actifs
try {
    // Récupérer tous les templates pour le debug, on filtrera plus tard si besoin
    $stmt = $pdo->query("SELECT id, name, report_type, is_active FROM report_templates ORDER BY name ASC");
    $reportTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Filtrer en PHP pour être sûr
    $reportTemplates = array_filter($reportTemplates, function($t) {
        return $t['is_active'] == 1 || $t['is_active'] === '1' || $t['is_active'] === true;
    });
} catch (Exception $e) {
    $reportTemplates = [];
    // Ne pas bloquer l'affichage si erreur ici
}
echo "<script>console.log('📊 Templates chargés PHP:', " . count($reportTemplates) . ");</script>";

?>

<style>
/* Styles spécifiques pour la page des tâches programmées */
.scheduled-tasks-page {
    background: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.page-header h1 {
    margin: 0;
    font-size: 2.2em;
    font-weight: 300;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.main-content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 30px;
    align-items: start;
}

.config-panel {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.config-panel h2 {
    color: #3498db;
    margin: 0 0 20px 0;
    font-size: 1.4em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.create-task-btn {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    border: none;
    padding: 15px 25px;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    margin-bottom: 25px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.create-task-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--input-bg);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #3498db;
    display: block;
}

.stat-label {
    font-size: 0.9em;
    color: var(--text-muted);
    margin-top: 5px;
}

.help-section {
    background: var(--input-bg);
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #f39c12;
}

.help-section h3 {
    color: #f39c12;
    margin: 0 0 15px 0;
    font-size: 1.1em;
}

.help-section ul {
    margin: 0;
    padding-left: 20px;
}

.help-section li {
    margin-bottom: 8px;
    color: var(--text-muted);
}

.tasks-panel {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.tasks-header {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    color: white;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tasks-header h2 {
    margin: 0;
    font-size: 1.3em;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.task-count {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
}

.tasks-list {
    max-height: 600px;
    overflow-y: auto;
}

.task-item {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.task-item:hover {
    background: var(--hover-bg);
}

.task-item:last-child {
    border-bottom: none;
}

.task-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.task-name {
    font-weight: 600;
    color: var(--text-color);
    font-size: 1.1em;
}

.task-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.task-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.task-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
    color: var(--text-muted);
}

.task-actions {
    display: flex;
    gap: 10px;
}

.btn-sm {
    padding: 6px 12px;
    border-radius: 6px;
    border: none;
    font-size: 0.85em;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #3498db;
    color: white;
}

.btn-edit:hover {
    background: #2980b9;
}

.btn-toggle {
    background: #f39c12;
    color: white;
}

.btn-toggle:hover {
    background: #e67e22;
}

.btn-delete {
    background: #e74c3c;
    color: white;
}

.btn-delete:hover {
    background: #c0392b;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--card-bg);
    margin: 2% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 95vh;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
    display: flex;
    flex-direction: column;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 20px 25px;
    border-radius: 12px 12px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.3em;
    font-weight: 500;
}

.close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5em;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.close:hover {
    background: rgba(255,255,255,0.2);
}

.modal-body {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(95vh - 140px); /* Réserver de l'espace pour header et footer */
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-color);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1em;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
    background: var(--card-bg);
    border-radius: 0 0 12px 12px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 1em;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
}

/* Responsive design */
@media (max-width: 768px) {
    .main-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .task-details {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 1% auto;
        max-height: 98vh;
    }
    
    .modal-body {
        max-height: calc(98vh - 140px);
        padding: 15px;
    }
    
    .modal-header,
    .modal-footer {
        padding: 15px;
    }
}

/* Optimisation de la modal sans scroll horizontal */
.modal-body::-webkit-scrollbar {
    width: 4px;
}

.modal-body::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 2px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--primary-hover);
}

/* Styles optimisés pour les sélecteurs d'heure */
.time-picker-row, .weekly-picker-row, .monthly-picker-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    align-items: flex-end;
}

.time-picker-group, .weekly-picker-group, .monthly-picker-group {
    display: flex;
    flex-direction: column;
    min-width: 70px;
    flex: 1;
    max-width: 90px;
}

.time-picker-group label, .weekly-picker-group label, .monthly-picker-group label {
    font-size: 0.8em;
    font-weight: 500;
    margin-bottom: 4px;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.time-select {
    padding: 6px 8px;
    font-size: 0.9em;
    min-width: 60px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--input-bg);
    color: var(--text-color);
}

.time-preview {
    background: var(--success-bg);
    color: var(--success-color);
    padding: 8px 10px;
    border-radius: 4px;
    border: 1px solid var(--success-border);
    text-align: center;
    font-size: 0.85em;
    margin-top: 8px;
    word-break: break-word;
}

.time-preview strong {
    color: var(--success-color);
    font-weight: 600;
}

/* Validation d'erreur optimisée */
.form-control.error {
    border-color: var(--danger-color);
    box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.1);
}

.error-message {
    color: var(--danger-color);
    font-size: 0.8em;
    margin-top: 4px;
    display: none;
    word-break: break-word;
}

.error-message.show {
    display: block;
}

/* Animation pour les transitions */
.time-picker-container, .weekly-picker-container, .monthly-picker-container {
    transition: all 0.2s ease;
}

/* Responsive optimisé */
@media (max-width: 768px) {
    .time-picker-row, .weekly-picker-row, .monthly-picker-row {
        gap: 6px;
        gap: 10px;
    }
}

</style>

<div class="scheduled-tasks-page">
    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Panneau de configuration -->
        <div class="config-panel">
            <h2>
                <span>⚙️</span>
                Configuration
            </h2>
            
            <button class="create-task-btn" onclick="openCreateTaskModal()">
                <span>➕</span>
                Nouvelle Tâche Programmée
            </button>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo count(array_filter($tasks, function($t) { return $t['is_active']; })); ?></span>
                    <div class="stat-label">Tâches actives</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($tasks); ?></span>
                    <div class="stat-label">Total tâches</div>
                </div>
            </div>
            
            <div class="help-section">
                <h3>💡 Guide rapide</h3>
                <ul>
                    <li><strong>Quotidien :</strong> Spécifiez l'heure (ex: 08:00)</li>
                    <li><strong>Hebdomadaire :</strong> Jour et heure (ex: monday:09:00)</li>
                    <li><strong>Mensuel :</strong> Jour du mois et heure (ex: 1:10:00)</li>
                    <li><strong>Cron personnalisé :</strong> Expression complète (ex: */30 * * * *)</li>
                    <li><strong>Une fois :</strong> Date et heure précises</li>
                </ul>
            </div>
        </div>

        <!-- Panneau des tâches -->
        <div class="tasks-panel">
            <div class="tasks-header">
                <h2>
                    <span>📋</span>
                    Tâches Configurées
                </h2>
                <div class="task-count"><?php echo count($tasks); ?> tâche(s)</div>
            </div>
            
            <div class="tasks-list">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>Aucune tâche configurée</h3>
                        <p>Créez votre première tâche programmée pour automatiser l'envoi d'emails.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-item">
                            <div class="task-header">
                                <div class="task-name"><?php echo htmlspecialchars($task['name'] ?? 'Tâche sans nom'); ?></div>
                                <div class="task-status <?php echo $task['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $task['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </div>
                            </div>
                            
                            <div class="task-details">
                                <div class="task-detail">
                                    <span>🔄</span>
                                    <span><?php
                                        switch($task['frequency_type']) {
                                            case 'daily': echo 'Quotidien à ' . ($task['frequency_value'] ?? ''); break;
                                            case 'weekly': echo 'Hebdomadaire ' . ($task['frequency_value'] ?? ''); break;
                                            case 'monthly': echo 'Mensuel ' . ($task['frequency_value'] ?? ''); break;
                                            case 'custom_cron': echo 'Cron: ' . ($task['frequency_value'] ?? ''); break;
                                            case 'once': echo 'Une fois le ' . ($task['frequency_value'] ?? ''); break;
                                            default: echo $task['frequency_type'] ?? 'Non défini';
                                        }
                                    ?></span>
                                </div>
                                <div class="task-detail">
                                    <span>📧</span>
                                    <span><?php
                                        $recipients = json_decode($task['recipients'] ?? '[]', true);
                                        echo count($recipients) . ' destinataire(s)';
                                    ?></span>
                                </div>
                                <div class="task-detail">
                                    <span>🏷️</span>
                                    <span><?php
                                        switch($task['task_type'] ?? 'custom') {
                                            case 'report': echo 'Rapport'; break;
                                            case 'notification': echo 'Notification'; break;
                                            case 'backup_reminder': echo 'Rappel sauvegarde'; break;
                                            default: echo 'Personnalisé';
                                        }
                                    ?></span>
                                </div>
                                <div class="task-detail">
                                    <span>📊</span>
                                    <span><?php echo ($task['execution_count'] ?? 0); ?> exécution(s)</span>
                                </div>
                            </div>
                            
                            <div class="task-actions">
                                <button class="btn-sm btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                    ✏️ Modifier
                                </button>
                                <button class="btn-sm btn-toggle" onclick="toggleTask(<?php echo $task['id']; ?>, <?php echo $task['is_active'] ? 'false' : 'true'; ?>)">
                                    <?php echo $task['is_active'] ? '⏸️ Désactiver' : '▶️ Activer'; ?>
                                </button>
                                <button class="btn-sm btn-delete" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                    🗑️ Supprimer
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php
// Fonction helper pour formater les tailles de fichier
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>

<!-- Modal de création/édition de tâche -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Nouvelle Tâche Programmée</h3>
            <button class="close" onclick="closeTaskModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="taskForm">
                <input type="hidden" id="taskId" name="task_id">
                
                <div class="form-group">
                    <label for="taskName">Nom de la tâche *</label>
                    <input type="text" id="taskName" name="task_name" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <!-- Type de tâche fixé à 'report' -->
                    <input type="hidden" id="taskType" name="task_type" value="report">
                    
                    <div class="form-group" id="reportTemplateGroup">
                        <label for="reportTemplateId">Modèle de rapport *</label>
                        <select id="reportTemplateId" name="report_template_id" class="form-control">
                            <option value="">-- Sélectionner un rapport --</option>
                            <?php if (empty($reportTemplates)): ?>
                                <option value="" disabled>Aucun modèle actif trouvé</option>
                            <?php else: ?>
                                <?php foreach ($reportTemplates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['name']); ?> (<?php echo htmlspecialchars($template['report_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($reportTemplates)): ?>
                            <small class="text-danger">Aucun modèle de rapport actif n'est disponible. <a href="index.php?page=reports_config">Créer un modèle</a></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="frequencyType">Fréquence *</label>
                        <select id="frequencyType" name="frequency_type" class="form-control" required onchange="updateFrequencyFields()">
                            <option value="once">Une fois</option>
                            <option value="daily">Quotidien</option>
                            <option value="weekly">Hebdomadaire</option>
                            <option value="monthly">Mensuel</option>
                            <option value="custom_cron">Cron personnalisé</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="frequencyValueGroup">
                    <label for="frequencyValue">Valeur de fréquence *</label>
                    <div id="timePickerContainer" style="display: none;">
                        <div class="time-picker-row">
                            <div class="time-picker-group">
                                <label for="hourSelect">Heure</label>
                                <select id="hourSelect" class="form-control time-select">
                                    <?php for($h = 0; $h < 24; $h++): ?>
                                        <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?>h</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="time-picker-group">
                                <label for="minuteSelect">Minutes</label>
                                <select id="minuteSelect" class="form-control time-select">
                                    <?php for($m = 0; $m < 60; $m += 5): ?>
                                        <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="time-preview">
                            <span>⏰ Heure sélectionnée : <strong id="timePreview">08:00</strong></span>
                        </div>
                    </div>
                    <div id="weeklyPickerContainer" style="display: none;">
                        <div class="weekly-picker-row">
                            <div class="weekly-picker-group">
                                <label for="daySelect">Jour de la semaine</label>
                                <select id="daySelect" class="form-control">
                                    <option value="monday">Lundi</option>
                                    <option value="tuesday">Mardi</option>
                                    <option value="wednesday">Mercredi</option>
                                    <option value="thursday">Jeudi</option>
                                    <option value="friday">Vendredi</option>
                                    <option value="saturday">Samedi</option>
                                    <option value="sunday">Dimanche</option>
                                </select>
                            </div>
                            <div class="weekly-picker-group">
                                <label for="weeklyHourSelect">Heure</label>
                                <select id="weeklyHourSelect" class="form-control time-select">
                                    <?php for($h = 0; $h < 24; $h++): ?>
                                        <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?>h</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="weekly-picker-group">
                                <label for="weeklyMinuteSelect">Minutes</label>
                                <select id="weeklyMinuteSelect" class="form-control time-select">
                                    <?php for($m = 0; $m < 60; $m += 5): ?>
                                        <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="time-preview">
                            <span>📅 Programmation : <strong id="weeklyPreview">Lundi à 08:00</strong></span>
                        </div>
                    </div>
                    <div id="monthlyPickerContainer" style="display: none;">
                        <div class="monthly-picker-row">
                            <div class="monthly-picker-group">
                                <label for="dayOfMonthSelect">Jour du mois</label>
                                <select id="dayOfMonthSelect" class="form-control">
                                    <?php for($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?php echo $d; ?>"><?php echo $d; ?><?php echo ($d == 1) ? 'er' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="monthly-picker-group">
                                <label for="monthlyHourSelect">Heure</label>
                                <select id="monthlyHourSelect" class="form-control time-select">
                                    <?php for($h = 0; $h < 24; $h++): ?>
                                        <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?>h</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="monthly-picker-group">
                                <label for="monthlyMinuteSelect">Minutes</label>
                                <select id="monthlyMinuteSelect" class="form-control time-select">
                                    <?php for($m = 0; $m < 60; $m += 5): ?>
                                        <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="time-preview">
                            <span>📅 Programmation : <strong id="monthlyPreview">Le 1er à 08:00</strong></span>
                        </div>
                    </div>
                    <input type="text" id="frequencyValue" name="frequency_value" class="form-control" placeholder="Ex: 08:00 pour quotidien" style="display: none;">
                </div>
                
                <div class="form-group" id="cronExpressionGroup" style="display: none;">
                    <label for="cronExpression">Expression Cron *</label>
                    <input type="text" id="cronExpression" name="custom_cron_expression" class="form-control" placeholder="Ex: */30 * * * * (toutes les 30 minutes)">
                </div>
                
                <div class="form-group">
                    <label for="recipients">Destinataires (un par ligne) *</label>
                    <textarea id="recipients" name="recipients" class="form-control" rows="3" required placeholder="admin@example.com&#10;support@example.com"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="subjectTemplate">Modèle de sujet *</label>
                    <input type="text" id="subjectTemplate" name="subject_template" class="form-control" required placeholder="Ex: Rapport TechSuivi - {{date}}">
                </div>
                
                <div class="form-group">
                    <label for="contentTemplate">Modèle de contenu *</label>
                    <textarea id="contentTemplate" name="content_template" class="form-control" rows="4" required placeholder="Contenu de l'email..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isActive" name="is_active" checked>
                        Tâche active
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="saveTask()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
// Variables globales
let currentTaskId = null;

// Fonction pour ouvrir le modal de création
function openCreateTaskModal() {
    currentTaskId = null;
    document.getElementById('modalTitle').textContent = 'Nouvelle Tâche Programmée';
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';
    document.getElementById('isActive').checked = true;
    updateFrequencyFields();
    updateTaskTypeFields();
    document.getElementById('taskModal').style.display = 'block';
}

// Fonction pour fermer le modal
function closeTaskModal() {
    document.getElementById('taskModal').style.display = 'none';
    currentTaskId = null;
}

// Fonction pour mettre à jour les champs selon le type de tâche
function updateTaskTypeFields() {
    // Cette fonction est simplifiée car le type est maintenant toujours 'report'
    const reportTemplateGroup = document.getElementById('reportTemplateGroup');
    const reportTemplateId = document.getElementById('reportTemplateId');
    
    if (reportTemplateGroup && reportTemplateId) {
        reportTemplateGroup.style.display = 'block';
        reportTemplateId.required = true;
    }
}

// Fonction pour mettre à jour les champs de fréquence
function updateFrequencyFields() {
    const frequencyType = document.getElementById('frequencyType').value;
    const frequencyValueGroup = document.getElementById('frequencyValueGroup');
    const cronExpressionGroup = document.getElementById('cronExpressionGroup');
    const frequencyValue = document.getElementById('frequencyValue');
    
    // Masquer tous les conteneurs
    const timePickerContainer = document.getElementById('timePickerContainer');
    const weeklyPickerContainer = document.getElementById('weeklyPickerContainer');
    const monthlyPickerContainer = document.getElementById('monthlyPickerContainer');
    
    timePickerContainer.style.display = 'none';
    weeklyPickerContainer.style.display = 'none';
    monthlyPickerContainer.style.display = 'none';
    frequencyValue.style.display = 'none';
    
    if (frequencyType === 'custom_cron') {
        frequencyValueGroup.style.display = 'none';
        cronExpressionGroup.style.display = 'block';
        frequencyValue.required = false;
        document.getElementById('cronExpression').required = true;
    } else {
        frequencyValueGroup.style.display = 'block';
        cronExpressionGroup.style.display = 'none';
        frequencyValue.required = true;
        document.getElementById('cronExpression').required = false;
        
        // Afficher le bon sélecteur selon le type
        switch(frequencyType) {
            case 'daily':
                timePickerContainer.style.display = 'block';
                updateTimePreview();
                break;
            case 'weekly':
                weeklyPickerContainer.style.display = 'block';
                updateWeeklyPreview();
                break;
            case 'monthly':
                monthlyPickerContainer.style.display = 'block';
                updateMonthlyPreview();
                break;
            case 'once':
                frequencyValue.style.display = 'block';
                frequencyValue.placeholder = 'Ex: 2024-12-25 10:00:00';
                frequencyValue.type = 'datetime-local';
                break;
        }
    }
}

// Fonction pour mettre à jour l'aperçu de l'heure quotidienne
function updateTimePreview() {
    const hourSelect = document.getElementById('hourSelect');
    const minuteSelect = document.getElementById('minuteSelect');
    const timePreview = document.getElementById('timePreview');
    const frequencyValue = document.getElementById('frequencyValue');
    
    if (!hourSelect || !minuteSelect || !timePreview || !frequencyValue) {
        return; // Éléments non encore disponibles
    }
    
    const hour = hourSelect.value || '00';
    const minute = minuteSelect.value || '00';
    
    const timeString = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    timePreview.textContent = timeString;
    frequencyValue.value = timeString;
    
    // Supprimer les messages d'erreur existants
    clearValidationErrors();
}

// Fonction pour mettre à jour l'aperçu hebdomadaire
function updateWeeklyPreview() {
    const daySelect = document.getElementById('daySelect');
    const hourSelect = document.getElementById('weeklyHourSelect');
    const minuteSelect = document.getElementById('weeklyMinuteSelect');
    const weeklyPreview = document.getElementById('weeklyPreview');
    const frequencyValue = document.getElementById('frequencyValue');
    
    if (!daySelect || !hourSelect || !minuteSelect || !weeklyPreview || !frequencyValue) {
        return; // Éléments non encore disponibles
    }
    
    const day = daySelect.value || 'monday';
    const hour = hourSelect.value || '00';
    const minute = minuteSelect.value || '00';
    
    const dayNames = {
        'monday': 'Lundi',
        'tuesday': 'Mardi',
        'wednesday': 'Mercredi',
        'thursday': 'Jeudi',
        'friday': 'Vendredi',
        'saturday': 'Samedi',
        'sunday': 'Dimanche'
    };
    
    const timeString = `${day}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    weeklyPreview.innerHTML = `📅 Programmé : <strong>${dayNames[day]} à ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}</strong>`;
    frequencyValue.value = timeString;
    
    // Supprimer les messages d'erreur existants
    clearValidationErrors();
}

// Fonction pour mettre à jour l'aperçu mensuel
function updateMonthlyPreview() {
    const dayOfMonthSelect = document.getElementById('dayOfMonthSelect');
    const hourSelect = document.getElementById('monthlyHourSelect');
    const minuteSelect = document.getElementById('monthlyMinuteSelect');
    const monthlyPreview = document.getElementById('monthlyPreview');
    const frequencyValue = document.getElementById('frequencyValue');
    
    if (!dayOfMonthSelect || !hourSelect || !minuteSelect || !monthlyPreview || !frequencyValue) {
        return; // Éléments non encore disponibles
    }
    
    const dayOfMonth = dayOfMonthSelect.value || '1';
    const hour = hourSelect.value || '00';
    const minute = minuteSelect.value || '00';
    
    const suffix = dayOfMonth == 1 ? 'er' : '';
    const timeString = `${dayOfMonth}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    monthlyPreview.innerHTML = `📅 Programmé : <strong>Le ${dayOfMonth}${suffix} à ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}</strong>`;
    frequencyValue.value = timeString;
    
    // Supprimer les messages d'erreur existants
    clearValidationErrors();
}

// Fonction de validation des heures
function validateTimeInput(frequencyType, value) {
    const errors = [];
    
    // Pour les types avec sélecteurs, récupérer directement les valeurs des sélecteurs
    let actualValue = value;
    
    switch(frequencyType) {
        case 'daily':
            // Récupérer directement depuis les sélecteurs
            const hourSelect = document.getElementById('hourSelect');
            const minuteSelect = document.getElementById('minuteSelect');
            if (hourSelect && minuteSelect) {
                const hour = hourSelect.value || '00';
                const minute = minuteSelect.value || '00';
                actualValue = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            
            // Validation du format HH:MM
            if (!/^\d{1,2}:\d{2}$/.test(actualValue)) {
                errors.push('Format d\'heure invalide. Utilisez HH:MM');
            } else {
                const [hour, minute] = actualValue.split(':').map(Number);
                if (hour < 0 || hour > 23) errors.push('L\'heure doit être entre 00 et 23');
                if (minute < 0 || minute > 59) errors.push('Les minutes doivent être entre 00 et 59');
            }
            break;
            
        case 'weekly':
            // Récupérer directement depuis les sélecteurs
            const daySelect = document.getElementById('daySelect');
            const weeklyHourSelect = document.getElementById('weeklyHourSelect');
            const weeklyMinuteSelect = document.getElementById('weeklyMinuteSelect');
            if (daySelect && weeklyHourSelect && weeklyMinuteSelect) {
                const day = daySelect.value || 'monday';
                const hour = weeklyHourSelect.value || '00';
                const minute = weeklyMinuteSelect.value || '00';
                actualValue = `${day}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            
            // Validation du format jour:HH:MM
            if (!/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday):\d{1,2}:\d{2}$/.test(actualValue)) {
                errors.push('Format invalide. Utilisez jour:HH:MM');
            } else {
                const parts = actualValue.split(':');
                const hour = parseInt(parts[1]);
                const minute = parseInt(parts[2]);
                if (hour < 0 || hour > 23) errors.push('L\'heure doit être entre 00 et 23');
                if (minute < 0 || minute > 59) errors.push('Les minutes doivent être entre 00 et 59');
            }
            break;
            
        case 'monthly':
            // Récupérer directement depuis les sélecteurs
            const dayOfMonthSelect = document.getElementById('dayOfMonthSelect');
            const monthlyHourSelect = document.getElementById('monthlyHourSelect');
            const monthlyMinuteSelect = document.getElementById('monthlyMinuteSelect');
            if (dayOfMonthSelect && monthlyHourSelect && monthlyMinuteSelect) {
                const dayOfMonth = dayOfMonthSelect.value || '1';
                const hour = monthlyHourSelect.value || '00';
                const minute = monthlyMinuteSelect.value || '00';
                actualValue = `${dayOfMonth}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            
            // Validation du format J:HH:MM
            if (!/^\d{1,2}:\d{1,2}:\d{2}$/.test(actualValue)) {
                errors.push('Format invalide. Utilisez J:HH:MM');
            } else {
                const parts = actualValue.split(':');
                const day = parseInt(parts[0]);
                const hour = parseInt(parts[1]);
                const minute = parseInt(parts[2]);
                if (day < 1 || day > 31) errors.push('Le jour doit être entre 1 et 31');
                if (hour < 0 || hour > 23) errors.push('L\'heure doit être entre 00 et 23');
                if (minute < 0 || minute > 59) errors.push('Les minutes doivent être entre 00 et 59');
            }
            break;
            
        case 'once':
            // Pour 'once', vérifier le champ datetime-local
            const frequencyValueField = document.getElementById('frequencyValue');
            if (frequencyValueField) {
                actualValue = frequencyValueField.value;
            }
            if (!actualValue || actualValue.length < 16) {
                errors.push('Veuillez sélectionner une date et heure valides');
            }
            break;
            
        case 'custom_cron':
            // Pour les expressions cron, récupérer depuis le champ cron
            const cronExpression = document.getElementById('cronExpression');
            if (cronExpression) {
                actualValue = cronExpression.value;
            }
            if (!actualValue || actualValue.trim() === '') {
                errors.push('Veuillez saisir une expression cron');
            }
            break;
    }
    
    return errors;
}

// Fonction pour supprimer les erreurs de validation
function clearValidationErrors() {
    const existingErrors = document.querySelectorAll('.error-message');
    existingErrors.forEach(error => error.remove());
}

// Fonction pour afficher les erreurs de validation
function showValidationErrors(errors) {
    // Supprimer les anciens messages d'erreur
    clearValidationErrors();
    
    if (errors.length > 0) {
        const frequencyValueGroup = document.getElementById('frequencyValueGroup');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message show';
        errorDiv.innerHTML = errors.join('<br>');
        frequencyValueGroup.appendChild(errorDiv);
        return false;
    }
    return true;
}

// Fonction pour sauvegarder une tâche
function saveTask() {
    console.log('🎯 Début de saveTask()');
    
    const form = document.getElementById('taskForm');
    const formData = new FormData();
    
    // Validation avant soumission
    const frequencyType = document.getElementById('frequencyType').value;
    
    // S'assurer que la valeur de fréquence est mise à jour avant validation
    let frequencyValue = document.getElementById('frequencyValue').value;
    
    // Forcer la mise à jour de la valeur selon le type de fréquence
    switch(frequencyType) {
        case 'daily':
            updateTimePreview();
            frequencyValue = document.getElementById('frequencyValue').value;
            break;
        case 'weekly':
            updateWeeklyPreview();
            frequencyValue = document.getElementById('frequencyValue').value;
            break;
        case 'monthly':
            updateMonthlyPreview();
            frequencyValue = document.getElementById('frequencyValue').value;
            break;
        case 'custom_cron':
            frequencyValue = document.getElementById('cronExpression').value;
            break;
        case 'once':
            // Pour 'once', la valeur est directement dans le champ
            frequencyValue = document.getElementById('frequencyValue').value;
            break;
    }
    
    console.log('📋 Données de validation:', {
        frequencyType: frequencyType,
        frequencyValue: frequencyValue
    });
    
    // Valider les données de fréquence
    const validationErrors = validateTimeInput(frequencyType, frequencyValue);
    console.log('🔍 Erreurs de validation:', validationErrors);
    
    if (!showValidationErrors(validationErrors)) {
        console.log('❌ Validation échouée, arrêt de la fonction');
        return; // Arrêter si validation échoue
    }
    
    console.log('✅ Validation réussie, préparation des données');
    
    // Ajouter l'action
    formData.append('action', currentTaskId ? 'update' : 'create');
    
    // Si c'est une mise à jour, ajouter l'ID
    if (currentTaskId) {
        formData.append('id', currentTaskId);
    }
    
    // Ajouter les données avec les bons noms de paramètres
    const taskName = document.getElementById('taskName').value;
    const taskType = document.getElementById('taskType').value;
    const recipientsText = document.getElementById('recipients').value;
    const contentTemplate = document.getElementById('contentTemplate').value;
    const subjectTemplate = document.getElementById('subjectTemplate').value;

    const isActive = document.getElementById('isActive').checked;
    const reportTemplateId = document.getElementById('reportTemplateId').value;
    
    console.log('📝 Données du formulaire:', {
        taskName: taskName,
        taskType: taskType,
        frequencyType: frequencyType,
        frequencyValue: frequencyValue,
        recipientsText: recipientsText,
        contentTemplate: contentTemplate,
        subjectTemplate: subjectTemplate,
        isActive: isActive,
        reportTemplateId: reportTemplateId
    });
    
    // Vérifier les champs requis
    if (!taskName.trim()) {
        alert('Le nom de la tâche est requis');
        return;
    }
    if (taskType === 'report' && !reportTemplateId) {
        alert('Veuillez sélectionner un modèle de rapport');
        return;
    }
    if (!recipientsText.trim()) {
        alert('Au moins un destinataire est requis');
        return;
    }
    if (!contentTemplate.trim()) {
        alert('Le contenu du template est requis');
        return;
    }
    if (!subjectTemplate.trim()) {
        alert('Le sujet du template est requis');
        return;
    }
    
    formData.append('name', taskName);
    formData.append('description', ''); // Description optionnelle
    formData.append('task_type', taskType);
    formData.append('frequency_type', frequencyType);
    if (taskType === 'report') {
        formData.append('report_template_id', reportTemplateId);
    }
    
    // Gérer la valeur de fréquence selon le type - récupérer directement depuis les sélecteurs
    let finalFrequencyValue = frequencyValue;
    
    switch(frequencyType) {
        case 'daily':
            const hourSelect = document.getElementById('hourSelect');
            const minuteSelect = document.getElementById('minuteSelect');
            if (hourSelect && minuteSelect) {
                const hour = hourSelect.value || '00';
                const minute = minuteSelect.value || '00';
                finalFrequencyValue = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            break;
            
        case 'weekly':
            const daySelect = document.getElementById('daySelect');
            const weeklyHourSelect = document.getElementById('weeklyHourSelect');
            const weeklyMinuteSelect = document.getElementById('weeklyMinuteSelect');
            if (daySelect && weeklyHourSelect && weeklyMinuteSelect) {
                const day = daySelect.value || 'monday';
                const hour = weeklyHourSelect.value || '00';
                const minute = weeklyMinuteSelect.value || '00';
                finalFrequencyValue = `${day}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            break;
            
        case 'monthly':
            const dayOfMonthSelect = document.getElementById('dayOfMonthSelect');
            const monthlyHourSelect = document.getElementById('monthlyHourSelect');
            const monthlyMinuteSelect = document.getElementById('monthlyMinuteSelect');
            if (dayOfMonthSelect && monthlyHourSelect && monthlyMinuteSelect) {
                const dayOfMonth = dayOfMonthSelect.value || '1';
                const hour = monthlyHourSelect.value || '00';
                const minute = monthlyMinuteSelect.value || '00';
                finalFrequencyValue = `${dayOfMonth}:${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
            }
            break;
            
        case 'custom_cron':
            finalFrequencyValue = document.getElementById('cronExpression').value;
            break;
            
        case 'once':
            // Pour 'once', utiliser la valeur du champ datetime-local
            finalFrequencyValue = document.getElementById('frequencyValue').value;
            break;
    }
    
    console.log('🎯 Valeur finale de fréquence:', finalFrequencyValue);
    formData.append('frequency_value', finalFrequencyValue);
    
    // Convertir les destinataires en JSON
    const recipientsArray = recipientsText.split('\n').filter(email => email.trim() !== '');
    formData.append('recipients', JSON.stringify(recipientsArray));
    
    // Ajouter les templates
    formData.append('content_template', contentTemplate);
    formData.append('subject_template', subjectTemplate);
    
    // Ajouter le statut actif
    formData.append('is_active', isActive ? '1' : '0');
    
    console.log('🚀 Envoi de la requête à l\'API');
    
    fetch('api/scheduled_tasks_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📡 Réponse reçue:', response);
        return response.json();
    })
    .then(data => {
        console.log('📊 Données de réponse:', data);
        if (data.success) {
            console.log('✅ Succès, fermeture du modal et rechargement');
            closeTaskModal();
            location.reload();
        } else {
            console.log('❌ Erreur API:', data.message);
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('💥 Erreur fetch:', error);
        alert('Erreur lors de la sauvegarde: ' + error.message);
    });
}

// Fonction pour éditer une tâche
function editTask(taskId) {
    currentTaskId = taskId;
    document.getElementById('modalTitle').textContent = 'Modifier la Tâche';
    document.getElementById('taskId').value = taskId;
    
    // Charger les données de la tâche via AJAX
    fetch(`api/scheduled_tasks_actions.php?action=get&id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const task = data.data;
                
                // Remplir le formulaire avec les données existantes
                document.getElementById('taskName').value = task.name || '';
                document.getElementById('taskType').value = task.task_type || 'custom';
                document.getElementById('frequencyType').value = task.frequency_type || 'daily';
                document.getElementById('frequencyValue').value = task.frequency_value || '';
                
                // Gérer le modèle de rapport
                if (task.task_type === 'report' && task.report_template_id) {
                    document.getElementById('reportTemplateId').value = task.report_template_id;
                } else {
                    document.getElementById('reportTemplateId').value = '';
                }
                
                // Gérer l'expression cron si nécessaire
                if (task.frequency_type === 'custom_cron') {
                    document.getElementById('cronExpression').value = task.frequency_value || '';
                }
                
                // Convertir les destinataires JSON en texte
                if (task.recipients) {
                    try {
                        const recipients = JSON.parse(task.recipients);
                        document.getElementById('recipients').value = recipients.join('\n');
                    } catch (e) {
                        document.getElementById('recipients').value = task.recipients;
                    }

                }
                
                document.getElementById('subjectTemplate').value = task.subject_template || task.content_template || '';
                document.getElementById('contentTemplate').value = task.content_template || '';
                document.getElementById('isActive').checked = task.is_active == 1;
                
                // Mettre à jour les champs de fréquence
                updateFrequencyFields();
                updateTaskTypeFields();
                
                // Pré-remplir les sélecteurs d'heure selon le type de fréquence
                setTimeout(function() {
                    if (task.frequency_type === 'daily' && task.frequency_value) {
                        const timeParts = task.frequency_value.split(':');
                        if (timeParts.length >= 2) {
                            const hourSelect = document.getElementById('hourSelect');
                            const minuteSelect = document.getElementById('minuteSelect');
                            if (hourSelect) hourSelect.value = timeParts[0].padStart(2, '0');
                            if (minuteSelect) minuteSelect.value = timeParts[1].padStart(2, '0');
                            updateTimePreview();
                        }
                    } else if (task.frequency_type === 'weekly' && task.frequency_value) {
                        const parts = task.frequency_value.split(':');
                        if (parts.length >= 3) {
                            const daySelect = document.getElementById('daySelect');
                            const weeklyHourSelect = document.getElementById('weeklyHourSelect');
                            const weeklyMinuteSelect = document.getElementById('weeklyMinuteSelect');
                            if (daySelect) daySelect.value = parts[0];
                            if (weeklyHourSelect) weeklyHourSelect.value = parts[1].padStart(2, '0');
                            if (weeklyMinuteSelect) weeklyMinuteSelect.value = parts[2].padStart(2, '0');
                            updateWeeklyPreview();
                        }
                    } else if (task.frequency_type === 'monthly' && task.frequency_value) {
                        const parts = task.frequency_value.split(':');
                        if (parts.length >= 3) {
                            const dayOfMonthSelect = document.getElementById('dayOfMonthSelect');
                            const monthlyHourSelect = document.getElementById('monthlyHourSelect');
                            const monthlyMinuteSelect = document.getElementById('monthlyMinuteSelect');
                            if (dayOfMonthSelect) dayOfMonthSelect.value = parts[0];
                            if (monthlyHourSelect) monthlyHourSelect.value = parts[1].padStart(2, '0');
                            if (monthlyMinuteSelect) monthlyMinuteSelect.value = parts[2].padStart(2, '0');
                            updateMonthlyPreview();
                        }
                    }
                }, 200);
                
                // Ouvrir le modal
                document.getElementById('taskModal').style.display = 'block';
            } else {
                alert('Erreur lors du chargement de la tâche: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement de la tâche');
        });
}

// Fonction pour activer/désactiver une tâche
function toggleTask(taskId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', taskId);
    formData.append('is_active', newStatus);
    
    fetch('api/scheduled_tasks_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la modification');
    });
}

// Fonction pour supprimer une tâche
function deleteTask(taskId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', taskId);
        
        fetch('api/scheduled_tasks_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la suppression');
        });
    }
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('taskModal');
    if (event.target === modal) {
        closeTaskModal();
    }
}

// Fermer le modal avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTaskModal();
    }
});

// Initialiser les champs de fréquence au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Gestionnaires pour le sélecteur quotidien
    const hourSelect = document.getElementById('hourSelect');
    const minuteSelect = document.getElementById('minuteSelect');
    if (hourSelect) hourSelect.addEventListener('change', updateTimePreview);
    if (minuteSelect) minuteSelect.addEventListener('change', updateTimePreview);
    
    // Gestionnaires pour le sélecteur hebdomadaire
    const daySelect = document.getElementById('daySelect');
    const weeklyHourSelect = document.getElementById('weeklyHourSelect');
    const weeklyMinuteSelect = document.getElementById('weeklyMinuteSelect');
    if (daySelect) daySelect.addEventListener('change', updateWeeklyPreview);
    if (weeklyHourSelect) weeklyHourSelect.addEventListener('change', updateWeeklyPreview);
    if (weeklyMinuteSelect) weeklyMinuteSelect.addEventListener('change', updateWeeklyPreview);
    
    // Gestionnaires pour le sélecteur mensuel
    const dayOfMonthSelect = document.getElementById('dayOfMonthSelect');
    const monthlyHourSelect = document.getElementById('monthlyHourSelect');
    const monthlyMinuteSelect = document.getElementById('monthlyMinuteSelect');
    if (dayOfMonthSelect) dayOfMonthSelect.addEventListener('change', updateMonthlyPreview);
    if (monthlyHourSelect) monthlyHourSelect.addEventListener('change', updateMonthlyPreview);
    if (monthlyMinuteSelect) monthlyMinuteSelect.addEventListener('change', updateMonthlyPreview);
    
    // Initialiser les champs de fréquence
    updateFrequencyFields();
    
    // Initialiser les aperçus par défaut
    setTimeout(function() {
        updateTimePreview();
        updateWeeklyPreview();
        updateMonthlyPreview();
    }, 100);
});

</script>