<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Inclure le système de permissions
require_once __DIR__ . '/../../utils/permissions_helper.php';

$message = '';
$messageType = '';

// Ancien code de restauration supprimé - maintenant géré par database_backup_v2.php

// Récupérer les messages de session
if (isset($_SESSION['backup_message'])) {
    $message = $_SESSION['backup_message'];
    $messageType = $_SESSION['backup_message_type'] ?? 'info';
    unset($_SESSION['backup_message'], $_SESSION['backup_message_type']);
}

if (isset($_SESSION['restore_message'])) {
    $message = $_SESSION['restore_message'];
    $messageType = $_SESSION['restore_message_type'] ?? 'info';
    unset($_SESSION['restore_message'], $_SESSION['restore_message_type']);
}

// Configuration de la base de données
require_once __DIR__ . '/../../config/database.php';

// Initialiser la connexion PDO
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    $message = "Erreur de connexion à la base de données : " . $e->getMessage();
    $messageType = 'error';
}

// Obtenir des informations sur la base de données
$dbInfo = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT 
                TABLE_NAME,
                TABLE_ROWS,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'SIZE_MB'
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '{$dbName}'
            ORDER BY TABLE_NAME
        ");
        $dbInfo = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Ignorer les erreurs d'information_schema
    }
}
?>

<h1>Sauvegarde et Restauration de la Base de Données</h1>

<?php if (!empty($message)): ?>
    <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; <?= $messageType === 'error' ? 'color: #721c24; border: 1px solid #f5c6cb; background-color: #f8d7da;' : ($messageType === 'success' ? 'color: #155724; border: 1px solid #c3e6cb; background-color: #d4edda;' : ($messageType === 'warning' ? 'color: #856404; border: 1px solid #ffeaa7; background-color: #fff3cd;' : 'color: #004085; border: 1px solid #b3d7ff; background-color: #cce7ff;')) ?>">
        <?php if ($messageType === 'warning' || $messageType === 'success'): ?>
            <div style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.4;">
                <?= $message ?>
            </div>
        <?php else: ?>
            <?= $message ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #856404;">⚠️ Important</h3>
    <ul style="color: #856404;">
        <li>La sauvegarde inclura toutes les tables et données de la base</li>
        <li>Conservez vos sauvegardes dans un endroit sûr</li>
        <li>Testez régulièrement vos sauvegardes</li>
        <li>La sauvegarde peut prendre quelques secondes selon la taille de la base</li>
    </ul>
</div>

<!-- Conteneur principal pour sauvegarde et restauration côte à côte -->
<div style="display: flex; gap: 20px; margin-bottom: 30px;">

<!-- Section Sauvegarde -->
<div class="backup-section-main backup-section-success">
    <h3>💾 Sauvegarde</h3>
    <p class="backup-section-description">Créer une sauvegarde de la base de données</p>
    
    <!-- Formulaire pour sauvegarde serveur -->
    <div class="backup-section-content">
        <h4>🗂️ Sauvegarde sur le Serveur</h4>
        <form method="post" action="actions/database_backup_v2.php">
            <input type="hidden" name="create_backup" value="1">
            <input type="hidden" name="backup_type" value="full">
            <input type="hidden" name="backup_format" value="sql">
            <input type="hidden" name="backup_destination" value="server">
            
            <button type="submit" style="background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 10px;">
                💾 Sauvegarde Complète sur Serveur
            </button>
            <br>
            <small style="color: #6c757d;">Stockée dans : /uploads/backups/</small>
        </form>
    </div>
    
    <!-- Formulaire pour téléchargement direct -->
    <div class="backup-section-content backup-section-info">
        <h4>⬇️ Téléchargement Direct</h4>
        <form method="post" action="../../actions/database_backup_v2.php">
            <input type="hidden" name="create_backup" value="1">
            <input type="hidden" name="backup_type" value="full">
            <input type="hidden" name="backup_format" value="sql">
            <input type="hidden" name="backup_destination" value="download">
            
            <button type="submit" style="background-color: #2196f3; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 10px;">
                📥 Télécharger Sauvegarde Complète
            </button>
            <br>
            <small style="color: #6c757d;">Téléchargement automatique du fichier SQL</small>
        </form>
    </div>
    
    <!-- Options avancées -->
    <details style="margin-top: 15px;">
        <summary style="cursor: pointer; font-weight: bold; color: #495057;">⚙️ Options Avancées</summary>
        <div style="margin-top: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">
            
            <!-- Sauvegarde partielle serveur -->
            <div style="margin-bottom: 15px;">
                <h5 style="color: #495057;">📂 Sauvegarde Partielle (Serveur)</h5>
                <form method="post" action="actions/database_backup_v2.php">
                    <input type="hidden" name="create_backup" value="1">
                    <input type="hidden" name="backup_type" value="partial">
                    <input type="hidden" name="backup_format" value="sql">
                    <input type="hidden" name="backup_destination" value="server">
                    
                    <div class="table-list backup-section-content" style="max-height: 150px; overflow-y: auto; margin-bottom: 10px;">
                        <?php if (!empty($dbInfo)): ?>
                            <?php foreach ($dbInfo as $table): ?>
                                <div class="backup-item">
                                    <input type="checkbox" name="selected_tables[]" value="<?= htmlspecialchars($table['TABLE_NAME']) ?>" class="table-checkbox">
                                    <span class="table-name"><?= htmlspecialchars($table['TABLE_NAME']) ?></span>
                                    <span class="table-stats">(<?= number_format($table['TABLE_ROWS']) ?> lignes, <?= $table['SIZE_MB'] ?> MB)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #6c757d; margin: 0;">Aucune table trouvée</p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" style="background-color: #ffc107; color: #212529; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                        📦 Sauvegarde Partielle (Serveur)
                    </button>
                </form>
            </div>
            
            <!-- Sauvegarde partielle téléchargement -->
            <div style="margin-bottom: 15px;">
                <h5 style="color: #495057;">📥 Sauvegarde Partielle (Téléchargement)</h5>
                <form method="post" action="../../actions/database_backup_v2.php">
                    <input type="hidden" name="create_backup" value="1">
                    <input type="hidden" name="backup_type" value="partial">
                    <input type="hidden" name="backup_format" value="sql">
                    <input type="hidden" name="backup_destination" value="download">
                    
                    <div class="table-list backup-section-content" style="max-height: 150px; overflow-y: auto; margin-bottom: 10px;">
                        <?php if (!empty($dbInfo)): ?>
                            <?php foreach ($dbInfo as $table): ?>
                                <div class="backup-item">
                                    <input type="checkbox" name="selected_tables[]" value="<?= htmlspecialchars($table['TABLE_NAME']) ?>" class="table-checkbox">
                                    <span class="table-name"><?= htmlspecialchars($table['TABLE_NAME']) ?></span>
                                    <span class="table-stats">(<?= number_format($table['TABLE_ROWS']) ?> lignes, <?= $table['SIZE_MB'] ?> MB)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #6c757d; margin: 0;">Aucune table trouvée</p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" style="background-color: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                        📥 Télécharger Sauvegarde Partielle
                    </button>
                </form>
            </div>
        </div>
    </details>
</div>

<!-- Section Restauration -->
<div class="backup-section-main backup-section-warning">
    <h3>🔄 Restauration</h3>
    <p class="backup-section-description backup-warning-text"><strong>⚠️ ATTENTION :</strong> La restauration remplacera complètement toutes les données actuelles !</p>
    
    <!-- Upload de fichier -->
    <div class="backup-section-content">
        <h4>📁 Upload Fichier</h4>
        <form method="post" enctype="multipart/form-data" action="actions/database_backup_v2.php">
            <input type="file" name="backup_file" accept=".sql" style="margin-bottom: 10px; width: 100%;">
            <div style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="drop_tables" value="1">
                    ⚠️ Vider la base avant restauration
                </label>
            </div>
            <button type="submit" name="restore_upload" style="background-color: #ff9800; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                🔄 Restaurer depuis Upload
            </button>
        </form>
    </div>
    
    <!-- Fichiers serveur -->
    <div class="backup-section-content backup-section-info">
        <h4>🗂️ Fichiers Serveur</h4>
        
        <?php
        // Lister les fichiers de sauvegarde disponibles sur le serveur
        $backupDir = __DIR__ . '/../../uploads/backups/';
        $serverBackups = [];
        
        // Utiliser le système de permissions pour créer le dossier
        if (!is_dir($backupDir)) {
            createDirectoryWithPermissions($backupDir);
        }
        
        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $filePath = $backupDir . $file;
                    $serverBackups[] = [
                        'name' => $file,
                        'size' => filesize($filePath),
                        'date' => filemtime($filePath)
                    ];
                }
            }
            
            // Trier par date (plus récent en premier)
            usort($serverBackups, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        ?>
        
        <?php if (!empty($serverBackups)): ?>
            <form method="post" action="actions/database_backup_v2.php">
                <select name="server_backup_file" required style="width: 100%; margin-bottom: 10px; padding: 5px;">
                    <option value="">-- Sélectionner un fichier --</option>
                    <?php foreach ($serverBackups as $backup): ?>
                        <option value="<?= htmlspecialchars($backup['name']) ?>">
                            <?= htmlspecialchars($backup['name']) ?>
                            (<?= number_format($backup['size'] / 1024 / 1024, 2) ?> MB - <?= date('d/m/Y H:i:s', $backup['date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-bottom: 10px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="drop_tables" value="1">
                        ⚠️ Vider la base avant restauration
                    </label>
                </div>
                <button type="submit" name="restore_from_server" style="background-color: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                    📁 Restaurer depuis Serveur
                </button>
            </form>
        <?php else: ?>
            <p style="color: #6c757d; font-style: italic;">Aucun fichier de sauvegarde trouvé sur le serveur.</p>
        <?php endif; ?>
    </div>
</div>

</div>

<!-- Informations sur la base de données -->
<div style="background-color: #e3f2fd; border: 1px solid #2196f3; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; color: #1976d2;">📋 Informations sur la Base de Données</h3>
    <p><strong>Base de données :</strong> <?= htmlspecialchars($dbName ?? 'Non défini') ?></p>
    <p><strong>Serveur :</strong> <?= htmlspecialchars($host ?? 'Non défini') ?></p>
    <p><strong>Date/Heure :</strong> <?= date('d/m/Y H:i:s') ?></p>
    
    <?php if (!empty($dbInfo)): ?>
        <h4>Tables dans la base de données :</h4>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Table</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Lignes</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Taille (MB)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dbInfo as $table): ?>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?= htmlspecialchars($table['TABLE_NAME']) ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= number_format($table['TABLE_ROWS']) ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $table['SIZE_MB'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">Aucune information de table disponible</p>
    <?php endif; ?>
</div>

<p style="margin-top: 30px;">
    <a href="index.php?page=settings&tab=sauvegarde">← Retour aux paramètres</a>
</p>

<script>
// Validation simple pour les sauvegardes partielles
document.addEventListener('DOMContentLoaded', function() {
    const partialForms = document.querySelectorAll('form[action*="backup"]');
    
    partialForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const backupType = form.querySelector('input[name="backup_type"]');
            if (backupType && backupType.value === 'partial') {
                const selectedTables = form.querySelectorAll('input[name="selected_tables[]"]:checked');
                if (selectedTables.length === 0) {
                    alert('⚠️ Veuillez sélectionner au moins une table pour la sauvegarde partielle.');
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
    
    // Confirmation pour la restauration
    const restoreForms = document.querySelectorAll('form[method="post"]:not([action*="backup"])');
    restoreForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (form.querySelector('input[name="restore_upload"]') || form.querySelector('input[name="restore_from_server"]')) {
                if (!confirm('⚠️ ATTENTION ⚠️\n\nCette action va REMPLACER toutes les données actuelles de la base !\n\nÊtes-vous absolument sûr de vouloir continuer ?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});
</script>