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

<div class="page-header">
    <h1 class="m-0 text-dark">📥 Importation des Clients</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> mb-20 font-bold">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="flex justify-between px-50 mb-30 text-lg font-medium text-muted">
    <div class="<?= $step == 'upload' ? 'text-primary border-b-2 border-primary' : '' ?> p-10">1. Upload</div>
    <div class="<?= $step == 'mapping' ? 'text-primary border-b-2 border-primary' : '' ?> p-10">2. Mapping</div>
    <div class="<?= $step == 'preview' ? 'text-primary border-b-2 border-primary' : '' ?> p-10">3. Validation</div>
</div>

<div class="card p-25 bg-white border shadow-sm">
    <?php if ($step === 'upload'): ?>
        <h2 class="mt-0 text-dark">Sélectionnez votre fichier Excel (.xlsx)</h2>
        <p class="mb-20 text-muted">Le fichier doit contenir une ligne d'en-tête.</p>
        
        <form action="index.php?page=client_import" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div class="my-30 p-40 text-center border-2 border-dashed border-border rounded hover:border-primary transition-colors cursor-pointer bg-light">
                <input type="file" name="import_file" accept=".xlsx" required class="text-lg text-dark">
            </div>
            
            <div class="text-right">
                <button type="submit" class="btn btn-primary font-bold">Continuer ➜</button>
            </div>
        </form>

    <?php elseif ($step === 'mapping'): ?>
        <h2 class="mt-0 text-dark">Correspondance des colonnes</h2>
        <p class="mb-20 text-muted">Associez les colonnes de votre fichier Excel aux champs de la base de données. <br>
        <strong>Astuce :</strong> Vous pouvez sélectionner le même champ destination plusieurs fois (ex: "Nom") pour concaténer les valeurs (utile pour Raison Sociale + Forme Juridique).</p>
        
        <form action="index.php?page=client_import" method="post">
            <input type="hidden" name="action" value="preview">
            <input type="hidden" name="uploaded_file" value="<?= htmlspecialchars($uploadedFile) ?>">
            
            <table class="w-full mb-20 border-collapse">
                <thead class="bg-light">
                    <tr>
                        <th class="p-10 border-b border-border text-left font-bold text-dark">Colonne Excel (En-tête)</th>
                        <th class="p-10 border-b border-border text-left font-bold text-dark">Exemple de donnée (Ligne 1)</th>
                        <th class="p-10 border-b border-border text-left font-bold text-dark">Champ Destination</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($headerRow as $index => $colName): ?>
                        <tr class="hover:bg-hover transition-colors">
                            <td class="p-10 border-b border-border font-medium text-dark"><?= htmlspecialchars($colName) ?></td>
                            <td class="p-10 border-b border-border text-muted italic text-sm"><?= htmlspecialchars($firstDataRow[$index] ?? '') ?></td>
                            <td class="p-10 border-b border-border">
                                <select name="mapping[<?= $index ?>]" class="form-control w-full p-8 border rounded bg-input text-dark">
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
            
            <div class="flex justify-between mt-20">
                <a href="index.php?page=client_import" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary font-bold">Prévisualiser ➜</button>
            </div>
        </form>

    <?php elseif ($step === 'preview'): ?>
        <h2 class="mt-0 text-dark">Validation avant import</h2>
        
        <form id="importForm" action="index.php?page=client_import" method="post">
            <input type="hidden" name="action" value="import">
            <input type="hidden" id="importDataInput" name="import_data" value="">
            <input type="hidden" name="uploaded_file" value="<?= htmlspecialchars($uploadedFile) ?>">

            <?php if (!empty($newClients)): ?>
                <div class="mb-30">
                    <h3 class="text-success border-b border-success pb-10 mt-30 text-xl font-bold flex items-center gap-10">
                        <span>✅</span> Nouveaux clients prêts à être importés (<?= count($newClients) ?>)
                    </h3>
                    <div class="border border-border rounded overflow-y-auto max-h-300">
                        <table class="w-full text-xs box-border">
                            <thead class="sticky top-0 bg-light shadow-sm">
                                <tr>
                                    <th class="p-10 border-b border-border text-left font-bold text-dark">Nom</th>
                                    <th class="p-10 border-b border-border text-left font-bold text-dark">Prénom</th>
                                    <th class="p-10 border-b border-border text-left font-bold text-dark">Email</th>
                                    <th class="p-10 border-b border-border text-left font-bold text-dark">Ville</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($newClients as $c): ?>
                                    <tr class="hover:bg-white transition-colors">
                                        <td class="p-10 border-b border-border text-dark"><?= htmlspecialchars($c['nom']) ?></td>
                                        <td class="p-10 border-b border-border text-dark"><?= htmlspecialchars($c['prenom']) ?></td>
                                        <td class="p-10 border-b border-border text-dark"><?= htmlspecialchars($c['mail']) ?></td>
                                        <td class="p-10 border-b border-border text-dark"><?= htmlspecialchars($c['ville']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($duplicates)): ?>
                <div class="mb-30">
                    <h3 class="text-danger border-b border-danger pb-10 text-xl font-bold flex items-center gap-10">
                        <span>⚠️</span> Doublons détectés (<?= count($duplicates) ?>) - Ne seront PAS importés par défaut
                    </h3>
                    <p class="mb-10 text-muted">Cochez les cases pour forcer l'importation (créera un doublon).</p>
                    <div class="border border-danger rounded overflow-y-auto max-h-300">
                        <table class="w-full text-xs bg-red-50">
                            <thead class="sticky top-0 bg-red-100 shadow-sm">
                                <tr>
                                    <th class="p-10 border-b border-danger text-left px-15"><input type="checkbox" onclick="toggleAllDuplicates(this)" class="cursor-pointer size-4"></th>
                                    <th class="p-10 border-b border-danger text-left font-bold text-danger-dark">Nom</th>
                                    <th class="p-10 border-b border-danger text-left font-bold text-danger-dark">Prénom</th>
                                    <th class="p-10 border-b border-danger text-left font-bold text-danger-dark">Email</th>
                                    <th class="p-10 border-b border-danger text-left font-bold text-danger-dark">Existe déjà (ID)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($duplicates as $index => $c): ?>
                                    <tr class="hover:bg-red-100 transition-colors">
                                        <td class="p-10 border-b border-danger-light px-15"><input type="checkbox" class="duplicate-checkbox cursor-pointer size-4" data-index="<?= $index ?>"></td>
                                        <td class="p-10 border-b border-danger-light text-danger-dark"><?= htmlspecialchars($c['nom']) ?></td>
                                        <td class="p-10 border-b border-danger-light text-danger-dark"><?= htmlspecialchars($c['prenom']) ?></td>
                                        <td class="p-10 border-b border-danger-light text-danger-dark"><?= htmlspecialchars($c['mail']) ?></td>
                                        <td class="p-10 border-b border-danger-light text-danger-dark font-mono"><?= htmlspecialchars($c['duplicate_id']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-right mt-30">
                <a href="index.php?page=client_import" class="btn btn-secondary mr-10">Recommencer</a>
                <button type="button" onclick="submitImport()" class="btn btn-primary font-bold">
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
