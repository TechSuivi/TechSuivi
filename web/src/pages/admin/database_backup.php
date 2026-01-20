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

<div class="page-header">
    <h1>Sauvegarde et Restauration de la Base de Données</h1>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType === 'error' ? 'error' : ($messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info')) ?> mb-20">
        <?php if ($messageType === 'warning' || $messageType === 'success'): ?>
            <div style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.4;">
                <?= $message ?>
            </div>
        <?php else: ?>
            <?= $message ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="alert alert-warning mb-20">
    <h3 class="mt-0 text-warning">⚠️ Important</h3>
    <ul class="m-0 pl-20">
        <li>La sauvegarde inclura toutes les tables et données de la base</li>
        <li>Conservez vos sauvegardes dans un endroit sûr</li>
        <li>Testez régulièrement vos sauvegardes</li>
        <li>La sauvegarde peut prendre quelques secondes selon la taille de la base</li>
    </ul>
</div>

<!-- Conteneur principal pour sauvegarde et restauration -->
<div class="grid-2 dashboard-grid mb-30">

    <!-- Section Sauvegarde -->
    <div class="card h-full">
        <h3 class="card-title mb-15">💾 Sauvegarde</h3>
        <p class="text-muted mb-20">Créer une sauvegarde de la base de données</p>
        
        <!-- Formulaire pour sauvegarde serveur -->
        <div class="mb-20 p-15 bg-secondary rounded-4 border">
            <h4 class="mt-0 mb-10 text-success">🗂️ Sauvegarde sur le Serveur</h4>
            <form method="post" action="actions/database_backup_v2.php">
                <input type="hidden" name="create_backup" value="1">
                <input type="hidden" name="backup_type" value="full">
                <input type="hidden" name="backup_format" value="sql">
                <input type="hidden" name="backup_destination" value="server">
                
                <button type="submit" class="btn btn-success w-full mb-10">
                    💾 Sauvegarde Complète sur Serveur
                </button>
                <small class="text-muted block text-center">Stockée dans : /uploads/backups/</small>
            </form>
        </div>
        
        <!-- Formulaire pour téléchargement direct -->
        <div class="mb-20 p-15 bg-secondary rounded-4 border">
            <h4 class="mt-0 mb-10 text-info">⬇️ Téléchargement Direct</h4>
            <form method="post" action="../../actions/database_backup_v2.php">
                <input type="hidden" name="create_backup" value="1">
                <input type="hidden" name="backup_type" value="full">
                <input type="hidden" name="backup_format" value="sql">
                <input type="hidden" name="backup_destination" value="download">
                
                <button type="submit" class="btn btn-primary w-full mb-10">
                    📥 Télécharger Sauvegarde Complète
                </button>
                <small class="text-muted block text-center">Téléchargement automatique du fichier SQL</small>
            </form>
        </div>
        
        <!-- Options avancées -->
        <details class="mt-15">
            <summary class="cursor-pointer font-bold text-muted">⚙️ Options Avancées</summary>
            <div class="mt-15 p-15 bg-secondary rounded-4">
                
                <!-- Sauvegarde partielle serveur -->
                <div class="mb-15">
                    <h5 class="mt-0 mb-10 text-muted">📂 Sauvegarde Partielle (Serveur)</h5>
                    <form method="post" action="actions/database_backup_v2.php">
                        <input type="hidden" name="create_backup" value="1">
                        <input type="hidden" name="backup_type" value="partial">
                        <input type="hidden" name="backup_format" value="sql">
                        <input type="hidden" name="backup_destination" value="server">
                        
                        <div class="table-list bg-light border p-10 rounded-4 mb-10" style="max-height: 150px; overflow-y: auto;">
                            <?php if (!empty($dbInfo)): ?>
                                <?php foreach ($dbInfo as $table): ?>
                                    <div class="flex items-center gap-10 mb-5">
                                        <input type="checkbox" name="selected_tables[]" value="<?= htmlspecialchars($table['TABLE_NAME']) ?>" class="table-checkbox">
                                        <span class="font-bold"><?= htmlspecialchars($table['TABLE_NAME']) ?></span>
                                        <span class="text-muted text-sm">(<?= number_format($table['TABLE_ROWS']) ?> lignes, <?= $table['SIZE_MB'] ?> MB)</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted m-0">Aucune table trouvée</p>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-warning btn-sm-action">
                            📦 Sauvegarde Partielle (Serveur)
                        </button>
                    </form>
                </div>
                
                <!-- Sauvegarde partielle téléchargement -->
                <div class="mb-15">
                    <h5 class="mt-0 mb-10 text-muted">📥 Sauvegarde Partielle (Téléchargement)</h5>
                    <form method="post" action="../../actions/database_backup_v2.php">
                        <input type="hidden" name="create_backup" value="1">
                        <input type="hidden" name="backup_type" value="partial">
                        <input type="hidden" name="backup_format" value="sql">
                        <input type="hidden" name="backup_destination" value="download">
                        
                        <div class="table-list bg-light border p-10 rounded-4 mb-10" style="max-height: 150px; overflow-y: auto;">
                            <?php if (!empty($dbInfo)): ?>
                                <?php foreach ($dbInfo as $table): ?>
                                    <div class="flex items-center gap-10 mb-5">
                                        <input type="checkbox" name="selected_tables[]" value="<?= htmlspecialchars($table['TABLE_NAME']) ?>" class="table-checkbox">
                                        <span class="font-bold"><?= htmlspecialchars($table['TABLE_NAME']) ?></span>
                                        <span class="text-muted text-sm">(<?= number_format($table['TABLE_ROWS']) ?> lignes, <?= $table['SIZE_MB'] ?> MB)</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted m-0">Aucune table trouvée</p>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-info btn-sm-action">
                            📥 Télécharger Sauvegarde Partielle
                        </button>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <!-- Section Restauration -->
    <div class="card h-full border-left-red">
        <h3 class="card-title mb-15 text-danger">🔄 Restauration</h3>
        <p class="text-danger font-bold mb-20">⚠️ ATTENTION : La restauration remplacera complètement toutes les données actuelles !</p>
        
        <!-- Upload de fichier -->
        <div class="mb-20 p-15 bg-soft-red rounded-4 border border-danger">
            <h4 class="mt-0 mb-10 text-danger">📁 Upload Fichier</h4>
            <form method="post" enctype="multipart/form-data" action="actions/database_backup_v2.php">
                <input type="file" name="backup_file" accept=".sql" class="form-control mb-10 w-full">
                <div class="mb-10">
                    <label class="flex items-center gap-5 cursor-pointer">
                        <input type="checkbox" name="drop_tables" value="1">
                        ⚠️ Vider la base avant restauration
                    </label>
                </div>
                <button type="submit" name="restore_upload" class="btn btn-warning w-full">
                    🔄 Restaurer depuis Upload
                </button>
            </form>
        </div>
        
        <!-- Fichiers serveur -->
        <div class="mb-20 p-15 bg-secondary rounded-4 border">
            <h4 class="mt-0 mb-10 text-info">🗂️ Fichiers Serveur</h4>
            
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
                    <select name="server_backup_file" required class="form-control mb-10">
                        <option value="">-- Sélectionner un fichier --</option>
                        <?php foreach ($serverBackups as $backup): ?>
                            <option value="<?= htmlspecialchars($backup['name']) ?>">
                                <?= htmlspecialchars($backup['name']) ?>
                                (<?= number_format($backup['size'] / 1024 / 1024, 2) ?> MB - <?= date('d/m/Y H:i:s', $backup['date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mb-10">
                        <label class="flex items-center gap-5 cursor-pointer">
                            <input type="checkbox" name="drop_tables" value="1">
                            ⚠️ Vider la base avant restauration
                        </label>
                    </div>
                    <button type="submit" name="restore_from_server" class="btn btn-info w-full">
                        📁 Restaurer depuis Serveur
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted italic">Aucun fichier de sauvegarde trouvé sur le serveur.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Informations sur la base de données -->
<div class="card border-left-blue">
    <h3 class="card-title text-info mb-15">📋 Informations sur la Base de Données</h3>
    <div class="grid-3 mb-20">
        <p class="m-0"><strong>Base de données :</strong> <?= htmlspecialchars($dbName ?? 'Non défini') ?></p>
        <p class="m-0"><strong>Serveur :</strong> <?= htmlspecialchars($host ?? 'Non défini') ?></p>
        <p class="m-0"><strong>Date/Heure :</strong> <?= date('d/m/Y H:i:s') ?></p>
    </div>
    
    <?php if (!empty($dbInfo)): ?>
        <h4 class="mb-10">Tables dans la base de données :</h4>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="text-left">Table</th>
                        <th class="text-right">Lignes</th>
                        <th class="text-right">Taille (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbInfo as $table): ?>
                        <tr>
                            <td><?= htmlspecialchars($table['TABLE_NAME']) ?></td>
                            <td class="text-right"><?= number_format($table['TABLE_ROWS']) ?></td>
                            <td class="text-right"><?= $table['SIZE_MB'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted italic">Aucune information de table disponible</p>
    <?php endif; ?>
</div>

<p class="mt-30">
    <a href="index.php?page=settings&tab=sauvegarde" class="btn btn-sm-action">← Retour aux paramètres</a>
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