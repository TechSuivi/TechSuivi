<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';

require_once __DIR__ . '/../utils/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

// === Configuration ===
$uploadDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Champs disponibles dans la BDD
// 'db_field' => 'Label'
$availableFields = [
    'nom' => 'Nom',
    'prenom' => 'Prénom',
    'adresse1' => 'Adresse 1',
    'adresse2' => 'Adresse 2',
    'cp' => 'Code Postal',
    'ville' => 'Ville',
    'telephone' => 'Téléphone',
    'portable' => 'Portable',
    'mail' => 'Email'
];

$step = 'upload';
$message = '';
$messageType = ''; // success, error, warning

// === Traitement du formulaire ===

// ÉTAPE 1 : Upload du fichier
if (isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $fileSize = $_FILES['import_file']['size'];
        $fileType = $_FILES['import_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $dest_path = $uploadDir . $newFileName;

        if ($fileExtension === 'xlsx') {
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $step = 'mapping';
                $uploadedFile = $newFileName;
                
                // Analyser le fichier pour les en-têtes
                if ($xlsx = SimpleXLSX::parse($dest_path)) {
                    $headerRow = $xlsx->rows()[0];
                    // Prévisualisation de la première ligne de données (ligne 2)
                    $firstDataRow = $xlsx->rows()[1] ?? [];
                } else {
                    $message = "Erreur lecture Excel: " . SimpleXLSX::parseError();
                    $messageType = 'error';
                    $step = 'upload';
                }
            } else {
                $message = "Erreur lors du déplacement du fichier uploadé.";
                $messageType = 'error';
            }
        } else {
            $message = "Format de fichier non supporté. Veuillez utiliser .xlsx";
            $messageType = 'error';
        }
    } else {
        $message = "Erreur lors de l'upload ou aucun fichier sélectionné.";
        $messageType = 'error';
    }
}

// ÉTAPE 2 : Prévisualisation & Vérification Doublons
elseif (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $uploadedFile = $_POST['uploaded_file'];
    $dest_path = $uploadDir . $uploadedFile;
    $mappings = $_POST['mapping'] ?? []; // Array [col_index => db_field]

    if (file_exists($dest_path) && $xlsx = SimpleXLSX::parse($dest_path)) {
        $rows = $xlsx->rows();
        $header = array_shift($rows); // Retirer les en-têtes
        
        $clientsprepare = [];
        $duplicates = [];
        $newClients = [];

        foreach ($rows as $rowIndex => $row) {
            // Construire le client à partir du mapping
            $clientData = [];
            foreach ($availableFields as $fieldKey => $fieldLabel) {
                $clientData[$fieldKey] = '';
            }

            // Appliquer le mapping (concaténation si plusieurs colonnes pointent vers le même champ)
            foreach ($mappings as $colIndex => $targetField) {
                if ($targetField !== 'ignore' && isset($row[$colIndex])) {
                    $val = trim($row[$colIndex]);
                    if (!empty($val)) {
                        if (!empty($clientData[$targetField])) {
                            $clientData[$targetField] .= ' ' . $val;
                        } else {
                            $clientData[$targetField] = $val;
                        }
                    }
                }
            }

            // Ignorer les lignes sans Nom
            if (empty($clientData['nom'])) {
                continue;
            }

            // Vérifier doublon (Nom + Prénom) - Insensible à la casse
            $isDuplicate = false;
            try {
                $sql = "SELECT ID FROM clients WHERE LOWER(nom) = LOWER(:nom) AND (LOWER(prenom) = LOWER(:prenom) OR (:prenom = '' AND (prenom IS NULL OR prenom = '')))";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $clientData['nom'],
                    ':prenom' => $clientData['prenom']
                ]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $isDuplicate = true;
                    $clientData['duplicate_id'] = $existing['ID'];
                }
            } catch (Exception $e) {
                // Ignore error
            }

            $clientData['row_index'] = $rowIndex;
            
            if ($isDuplicate) {
                $duplicates[] = $clientData;
            } else {
                $newClients[] = $clientData;
            }
        }
        $step = 'preview';
    } else {
        $message = "Session expirée ou fichier introuvable. Veuillez recommencer.";
        $messageType = 'error';
        $step = 'upload';
    }
}

// ÉTAPE 3 : Import Final
elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
    $dataToImport = json_decode($_POST['import_data'], true);
    $count = 0;
    
    if (is_array($dataToImport)) {
        $sql = "INSERT INTO clients (nom, prenom, adresse1, adresse2, cp, ville, telephone, portable, mail) 
                VALUES (:nom, :prenom, :adresse1, :adresse2, :cp, :ville, :telephone, :portable, :mail)";
        $stmt = $pdo->prepare($sql);

        foreach ($dataToImport as $client) {
            // Exclude non-db fields
            $params = [
                ':nom' => $client['nom'],
                ':prenom' => $client['prenom'],
                ':adresse1' => $client['adresse1'],
                ':adresse2' => $client['adresse2'],
                ':cp' => $client['cp'],
                ':ville' => $client['ville'],
                ':telephone' => $client['telephone'],
                ':portable' => $client['portable'],
                ':mail' => $client['mail']
            ];
            
            try {
                if ($stmt->execute($params)) {
                    $count++;
                }
            } catch (Exception $e) {
                // Log error potentially
            }
        }
        $message = "Importation terminée avec succès ! $count clients ajoutés.";
        $messageType = 'success';
        
        // Cleanup
        if (!empty($_POST['uploaded_file'])) {
            @unlink($uploadDir . $_POST['uploaded_file']);
        }
        $step = 'upload'; // Reset
    } else {
        $message = "Données invalides.";
        $messageType = 'error';
    }
}

?>

<style>
/* Reusing styles from clients.php roughly */
.page-header {
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}
.card {
    background: var(--card-bg, white);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    border: 1px solid var(--border-color, #eee);
}
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    font-weight: 500;
    color: white;
    background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
    text-decoration: none;
    display: inline-block;
}
.btn-secondary {
    background: #6c757d;
}
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

table.mapping-table {
    width: 100%;
    border-collapse: collapse;
}
table.mapping-table th, table.mapping-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
}
select.mapping-select {
    width: 100%;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
}
.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding: 0 50px;
}
.step-item {
    position: relative;
    font-weight: bold;
    color: #ccc;
}
.step-item.active {
    color: #8e44ad;
}
.preview-section h3 {
    border-bottom: 2px solid #8e44ad;
    padding-bottom: 10px;
    margin-top: 30px;
}
</style>

<div class="page-header">
    <h1 style="margin:0;">📥 Importation des Clients</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="step-indicator">
    <div class="step-item <?= $step == 'upload' ? 'active' : '' ?>">1. Upload</div>
    <div class="step-item <?= $step == 'mapping' ? 'active' : '' ?>">2. Mapping</div>
    <div class="step-item <?= $step == 'preview' ? 'active' : '' ?>">3. Validation</div>
</div>

<div class="card">
    <?php if ($step === 'upload'): ?>
        <h2>Sélectionnez votre fichier Excel (.xlsx)</h2>
        <p>Le fichier doit contenir une ligne d'en-tête.</p>
        
        <form action="index.php?page=client_import" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div style="margin: 30px 0; border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 10px;">
                <input type="file" name="import_file" accept=".xlsx" required style="font-size: 1.2em;">
            </div>
            
            <div style="text-align: right;">
                <button type="submit" class="btn">Continuer ➜</button>
            </div>
        </form>

    <?php elseif ($step === 'mapping'): ?>
        <h2>Correspondance des colonnes</h2>
        <p>Associez les colonnes de votre fichier Excel aux champs de la base de données. <br>
        <strong>Astuce :</strong> Vous pouvez sélectionner le même champ destination plusieurs fois (ex: "Nom") pour concaténer les valeurs (utile pour Raison Sociale + Forme Juridique).</p>
        
        <form action="index.php?page=client_import" method="post">
            <input type="hidden" name="action" value="preview">
            <input type="hidden" name="uploaded_file" value="<?= htmlspecialchars($uploadedFile) ?>">
            
            <table class="mapping-table">
                <thead>
                    <tr>
                        <th>Colonne Excel (En-tête)</th>
                        <th>Exemple de donnée (Ligne 1)</th>
                        <th>Champ Destination</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($headerRow as $index => $colName): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($colName) ?></strong></td>
                            <td style="color: #666; font-style: italic;"><?= htmlspecialchars($firstDataRow[$index] ?? '') ?></td>
                            <td>
                                <select name="mapping[<?= $index ?>]" class="mapping-select">
                                    <option value="ignore">-- Ignorer --</option>
                                    <?php foreach ($availableFields as $fieldKey => $fieldLabel): ?>
                                        <?php 
                                            // Auto-detection basique
                                            $selected = '';
                                            $cleanCol = strtolower(trim($colName));
                                            $cleanLabel = strtolower($fieldLabel);
                                            if ($cleanCol == $fieldKey || strpos($cleanCol, $cleanLabel) !== false) {
                                                $selected = 'selected';
                                            }
                                        ?>
                                        <option value="<?= $fieldKey ?>" <?= $selected ?>><?= $fieldLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                <a href="index.php?page=client_import" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn">Prévisualiser ➜</button>
            </div>
        </form>

    <?php elseif ($step === 'preview'): ?>
        <h2>Validation avant import</h2>
        
        <form id="importForm" action="index.php?page=client_import" method="post">
            <input type="hidden" name="action" value="import">
            <input type="hidden" id="importDataInput" name="import_data" value="">
            <input type="hidden" name="uploaded_file" value="<?= htmlspecialchars($uploadedFile) ?>">

            <?php if (!empty($newClients)): ?>
                <div class="preview-section">
                    <h3 style="color: #27ae60;">✅ Nouveaux clients prêts à être importés (<?= count($newClients) ?>)</h3>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee;">
                        <table class="mapping-table" style="font-size: 0.9em;">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Ville</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($newClients as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['nom']) ?></td>
                                        <td><?= htmlspecialchars($c['prenom']) ?></td>
                                        <td><?= htmlspecialchars($c['mail']) ?></td>
                                        <td><?= htmlspecialchars($c['ville']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($duplicates)): ?>
                <div class="preview-section">
                    <h3 style="color: #c0392b;">⚠️ Doublons détectés (<?= count($duplicates) ?>) - Ne seront PAS importés par défaut</h3>
                    <p>Cochez les cases pour forcer l'importation (créera un doublon).</p>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee;">
                        <table class="mapping-table" style="font-size: 0.9em; background: #fff5f5;">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" onclick="toggleAllDuplicates(this)"></th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Existe déjà (ID)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($duplicates as $index => $c): ?>
                                    <tr>
                                        <td><input type="checkbox" class="duplicate-checkbox" data-index="<?= $index ?>"></td>
                                        <td><?= htmlspecialchars($c['nom']) ?></td>
                                        <td><?= htmlspecialchars($c['prenom']) ?></td>
                                        <td><?= htmlspecialchars($c['mail']) ?></td>
                                        <td><?= htmlspecialchars($c['duplicate_id']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 30px; text-align: right;">
                <a href="index.php?page=client_import" class="btn btn-secondary">Recommencer</a>
                <button type="button" onclick="submitImport()" class="btn">
                    Importer <span id="totalImportCount"><?= count($newClients) ?></span> clients
                </button>
            </div>
        </form>

        <script>
        const newClients = <?= json_encode($newClients) ?>;
        const duplicates = <?= json_encode($duplicates) ?>;

        function toggleAllDuplicates(source) {
            document.querySelectorAll('.duplicate-checkbox').forEach(cb => {
                cb.checked = source.checked;
            });
            updateCount();
        }

        function updateCount() {
            let count = newClients.length;
            document.querySelectorAll('.duplicate-checkbox:checked').forEach(() => {
                count++;
            });
            document.getElementById('totalImportCount').innerText = count;
        }

        document.querySelectorAll('.duplicate-checkbox').forEach(cb => {
            cb.addEventListener('change', updateCount);
        });

        function submitImport() {
            // Combiner newClients et les doublons cochés
            let finalImport = [...newClients];
            
            document.querySelectorAll('.duplicate-checkbox:checked').forEach(cb => {
                let index = cb.getAttribute('data-index');
                finalImport.push(duplicates[index]);
            });

            if (finalImport.length === 0) {
                alert("Aucun client à importer.");
                return;
            }

            if (confirm("Confirmer l'importation de " + finalImport.length + " clients ?")) {
                document.getElementById('importDataInput').value = JSON.stringify(finalImport);
                document.getElementById('importForm').submit();
            }
        }
        </script>
    <?php endif; ?>
</div>
