<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';

$message = '';
$csvData = [];
$importStats = [];

// Récupérer l'URL du catalogue depuis la configuration
function getCatalogUrl($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM configuration WHERE config_key = 'acadia_catalog_url'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['config_value'];
        }
    } catch (Exception $e) {
        // Si erreur, retourner null
    }
    
    return null;
}

$catalogUrl = getCatalogUrl($pdo);

// Utiliser la connexion principale (la table catalog est dans techsuivi_db)
$catalogPdo = $pdo;
$catalogExists = isset($pdo);

if (!$catalogExists) {
    $message = '<p class="text-danger">❌ Erreur de connexion à la base de données principale.</p>';
} elseif (empty($catalogUrl)) {
    $message = '<div class="alert alert-warning mb-20">
                <h4 class="text-warning mt-0">⚠️ Configuration requise</h4>
                <p class="text-warning mb-10">
                    L\'URL du catalogue Acadia n\'est pas configurée. Vous devez d\'abord configurer l\'URL et le token API.
                </p>
                <a href="index.php?page=acadia_config" class="btn btn-warning font-bold">
                    🔧 Configurer maintenant
                </a>
                </div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['download_csv'])) {
        // Télécharger le fichier CSV
        $csvContent = file_get_contents($catalogUrl);
        
        if ($csvContent !== false) {
            // Convertir l'encodage si nécessaire
            $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
            }
            
            // Sauvegarder le fichier temporairement
            $tempFile = sys_get_temp_dir() . '/acadia_catalog.csv';
            file_put_contents($tempFile, $csvContent);
            
            // Analyser le CSV
            $handle = fopen($tempFile, 'r');
            if ($handle !== false) {
                $headers = fgetcsv($handle, 0, ';'); // Première ligne = en-têtes
                $rowCount = 0;
                
                // Afficher les en-têtes pour debug
                $message = '<div class="alert alert-info mb-15">
                           <h4 class="text-info mt-0">📋 En-têtes CSV détectés :</h4>
                           <p class="font-mono text-xs bg-light p-10 rounded">' .
                           htmlspecialchars(implode(' | ', $headers)) . '</p></div>';
                
                while (($row = fgetcsv($handle, 0, ';')) !== false && $rowCount < 10) {
                    if (count($headers) === count($row)) {
                        $csvData[] = array_combine($headers, $row);
                    } else {
                        $csvData[] = ['ERREUR' => 'Nombre de colonnes incorrect: ' . count($row) . ' vs ' . count($headers)];
                    }
                    $rowCount++;
                }
                fclose($handle);
                
                $message .= '<p class="text-success font-bold">✅ Fichier CSV téléchargé et analysé avec succès ! Aperçu des 10 premières lignes :</p>';
            } else {
                $message = '<p class="text-danger">❌ Erreur lors de l\'ouverture du fichier CSV</p>';
            }
            
            // Nettoyer le fichier temporaire
            unlink($tempFile);
        } else {
            $message = '<p class="text-danger">❌ Erreur lors du téléchargement du fichier CSV</p>';
        }
    } elseif (isset($_POST['import_catalog']) && isset($catalogPdo)) {
        // Vérifier si c'est un appel AJAX
        $isAjax = isset($_POST['ajax_sync']);
        
        if ($isAjax) {
            // Pour les appels AJAX, ne pas afficher le HTML de la page
            ob_start();
        }
        // Importer le catalogue complet
        $csvContent = file_get_contents($catalogUrl);
        
        if ($csvContent !== false) {
            // Convertir l'encodage si nécessaire
            $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
            }
            
            $tempFile = sys_get_temp_dir() . '/acadia_catalog.csv';
            file_put_contents($tempFile, $csvContent);
            
            $handle = fopen($tempFile, 'r');
            if ($handle !== false) {
                $headers = fgetcsv($handle, 0, ';');
                
                // Afficher les en-têtes en mode debug
                if (isset($_POST['debug_mode'])) {
                    echo '<div class="alert alert-info mb-15">
                          <h4 class="text-info mt-0">📋 En-têtes CSV détectés :</h4>
                          <p class="font-mono text-xs bg-light p-10 rounded">' .
                          htmlspecialchars(implode(' | ', $headers)) . '</p></div>';
                    flush();
                }
                
                // Préparer la requête d'insertion avec mise à jour automatique
                $sql = "INSERT INTO catalog (
                    marque, famille, part_number, ref_acadia, ean_code, ref_constructeur,
                    designation, stock_reel, prix_ht, prix_client, ecotaxe, copie_privee,
                    poids, image, categorie_principale, categorie_secondaire, categorie_tertiaire
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    marque = VALUES(marque),
                    famille = VALUES(famille),
                    part_number = VALUES(part_number),
                    ref_constructeur = VALUES(ref_constructeur),
                    designation = VALUES(designation),
                    stock_reel = VALUES(stock_reel),
                    prix_ht = VALUES(prix_ht),
                    prix_client = VALUES(prix_client),
                    ecotaxe = VALUES(ecotaxe),
                    copie_privee = VALUES(copie_privee),
                    poids = VALUES(poids),
                    image = VALUES(image),
                    categorie_principale = VALUES(categorie_principale),
                    categorie_secondaire = VALUES(categorie_secondaire),
                    categorie_tertiaire = VALUES(categorie_tertiaire)";
                
                $stmt = $catalogPdo->prepare($sql);
                
                $imported = 0;
                $updated = 0;
                $errors = 0;
                $lineNumber = 0;
                
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    $lineNumber++;
                    
                    // Vérifier que le nombre de colonnes correspond
                    if (count($row) !== count($headers)) {
                        $errors++;
                        if (isset($_POST['debug_mode'])) {
                            echo "<p class='text-danger text-xs'>Ligne $lineNumber: Nombre de colonnes incorrect (" . count($row) . " vs " . count($headers) . ")</p>";
                        }
                        continue;
                    }
                    
                    $data = array_combine($headers, $row);
                    
                    // Nettoyer et valider les données selon les vrais en-têtes CSV
                    $cleanData = [
                        trim($data['MARQUE'] ?? ''),
                        trim($data['FAMILLE'] ?? ''),
                        trim($data['Part Number'] ?? ''),
                        trim($data['REF ACADIA'] ?? ''),
                        trim($data['EAN CODE'] ?? ''),
                        trim($data['REF CONSTRUCTEUR'] ?? ''),
                        trim($data['DESIGNATION 1'] ?? ''),
                        !empty(trim($data['STOCK REEL'] ?? '')) ? (int)$data['STOCK REEL'] : null,
                        !empty(trim($data['PRIX HT'] ?? '')) ? (float)str_replace(',', '.', $data['PRIX HT']) : null,
                        !empty(trim($data['PRIX CLIENT'] ?? '')) ? (float)str_replace(',', '.', $data['PRIX CLIENT']) : null,
                        !empty(trim($data['ECOTAXE'] ?? '')) ? (float)str_replace(',', '.', $data['ECOTAXE']) : null,
                        !empty(trim($data['COPIE PRIVEE'] ?? '')) ? (float)str_replace(',', '.', $data['COPIE PRIVEE']) : null,
                        !empty(trim($data['POIDS'] ?? '')) ? (float)str_replace(',', '.', $data['POIDS']) : null,
                        trim($data['IMAGE'] ?? ''),
                        trim($data['CATEGORIE PRINCIPALE'] ?? ''),
                        trim($data['CATEGORIE SECONDAIRE'] ?? ''),
                        trim($data['CATEGORIE TERTIAIRE'] ?? '')
                    ];
                    
                    try {
                        $stmt->execute($cleanData);
                        
                        // Vérifier si c'est une insertion ou une mise à jour
                        if ($stmt->rowCount() == 1) {
                            $imported++; // Nouveau produit
                        } else if ($stmt->rowCount() == 2) {
                            $updated++; // Produit mis à jour
                        }
                        
                        // Afficher le progrès tous les 100 produits
                        if (($imported + $updated) % 100 === 0 && isset($_POST['debug_mode'])) {
                            echo "<p class='text-success text-xs'>Traité: " . ($imported + $updated) . " produits...</p>";
                            flush();
                        }
                        
                    } catch (PDOException $e) {
                        $errors++;
                        // En mode debug, afficher les erreurs
                        if (isset($_POST['debug_mode'])) {
                            echo "<p class='text-danger text-xs'>Erreur ligne $lineNumber: " . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "<p class='text-warning text-xxs'>Données: " . htmlspecialchars(substr(json_encode($data), 0, 200)) . "...</p>";
                            flush();
                        }
                    }
                }
                
                fclose($handle);
                unlink($tempFile);
                
                $importStats = [
                    'imported' => $imported,
                    'updated' => $updated,
                    'errors' => $errors,
                    'total' => $imported + $updated + $errors
                ];
                
                if ($isAjax) {
                    // Réponse JSON pour AJAX
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'imported' => $imported,
                        'updated' => $updated,
                        'errors' => $errors,
                        'total' => $importStats['total'],
                        'message' => "Importation terminée ! Nouveaux produits : {$imported}, Produits mis à jour : {$updated}, Erreurs : {$errors}"
                    ]);
                    exit;
                } else {
                    $message = "<div class='alert alert-success mt-20'>
                               <h4 class='text-success mt-0'>✅ Importation terminée !</h4>
                               <p><strong>🆕 Nouveaux produits :</strong> {$imported}</p>
                               <p><strong>🔄 Produits mis à jour :</strong> {$updated}</p>
                               <p><strong>❌ Erreurs :</strong> {$errors}</p>
                               <p><strong>📊 Total traité :</strong> {$importStats['total']}</p>
                               <p class='text-muted text-sm m-0'>
                                   <em>Le champ updated_at a été automatiquement mis à jour pour tous les produits modifiés.</em>
                               </p>
                               </div>";
                }
            }
        }
    }
}
?>

<div class="page-header">
    <h1 class="m-0 text-dark">Import Catalogue Acadia</h1>
</div>

<?php echo $message; ?>

<div class="max-w-1000 mx-auto">
    <!-- Section d'information -->
    <div class="alert alert-info mb-20 bg-secondary border-l-4 border-l-primary p-20">
        <h3 class="mt-0 text-primary">📦 Import du catalogue Acadia</h3>
        <p class="text-muted">
            Cet outil télécharge automatiquement le catalogue depuis Acadia et l'importe dans la table <strong>catalog</strong> de votre base de données.
        </p>
        <p class="text-muted">
            <strong>URL source :</strong> <a href="<?= $catalogUrl ?>" target="_blank" class="text-primary underline">Catalogue Acadia</a>
        </p>
        
        <div class="bg-white p-15 rounded-md mt-15 border border-border">
            <h4 class="mt-0 text-primary">🕒 Gestion automatique des dates</h4>
            <ul class="text-muted m-0 pl-20">
                <li><strong>Nouveaux produits :</strong> Le champ <code>updated_at</code> est défini à la date/heure actuelle</li>
                <li><strong>Mises à jour :</strong> Le champ <code>updated_at</code> est automatiquement mis à jour lors de modifications</li>
                <li><strong>Traçabilité :</strong> Vous pouvez ainsi suivre quand chaque produit a été modifié pour la dernière fois</li>
            </ul>
        </div>
    </div>

    <!-- Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-20 mb-30">
        <form method="POST" class="h-full">
            <div class="card bg-secondary border border-border h-full flex flex-col justify-between p-20 shadow-sm">
                <div>
                    <h4 class="mt-0 text-warning-dark">🔍 Aperçu du fichier CSV</h4>
                    <p class="text-muted mb-15">
                        Téléchargez et analysez les 10 premières lignes pour vérifier la structure.
                    </p>
                </div>
                <button type="submit" name="download_csv" class="btn btn-warning w-full font-bold">
                    📥 Télécharger et Analyser
                </button>
            </div>
        </form>

        <form method="POST" class="h-full">
            <div class="card bg-secondary border border-border h-full flex flex-col justify-between p-20 shadow-sm">
                <div>
                    <h4 class="mt-0 text-success-dark">🚀 Import complet</h4>
                    <p class="text-muted mb-15">
                        Importer tout le catalogue dans la base de données.
                    </p>
                    <div class="mb-15">
                        <label class="flex items-center gap-10 text-sm cursor-pointer text-dark select-none hover:text-primary transition-colors">
                            <input type="checkbox" name="debug_mode" value="1" class="form-checkbox">
                            <span>Mode debug (afficher les erreurs détaillées)</span>
                        </label>
                    </div>
                </div>
                <button type="submit" name="import_catalog"
                        class="btn btn-success w-full font-bold"
                        onclick="return confirm('Êtes-vous sûr de vouloir importer tout le catalogue ? Cette opération peut prendre plusieurs minutes.')">
                    🗄️ Importer le Catalogue
                </button>
            </div>
        </form>
    </div>

    <!-- Aperçu des données CSV -->
    <?php if (!empty($csvData)): ?>
    <div class="card bg-white border border-border mb-20 p-0 overflow-hidden shadow-sm">
        <div class="p-15 border-b border-border bg-light">
            <h3 class="m-0 text-dark">📊 Aperçu des données CSV</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-secondary text-muted">
                        <?php foreach (array_keys($csvData[0]) as $header): ?>
                            <th class="p-10 border-b border-border text-left font-bold whitespace-nowrap"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($csvData as $row): ?>
                        <tr class="hover:bg-hover transition-colors">
                            <?php foreach ($row as $cell): ?>
                                <td class="p-8 border-b border-border text-dark whitespace-nowrap" title="<?= htmlspecialchars($cell) ?>">
                                    <?= htmlspecialchars(substr($cell, 0, 50)) ?><?= strlen($cell) > 50 ? '...' : '' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mapping des colonnes -->
    <div class="card bg-white border border-border p-20 shadow-sm">
        <h3 class="mt-0 text-dark">🔄 Mapping des colonnes</h3>
        <p class="text-muted mb-15">Correspondance entre les colonnes CSV et la base de données :</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-20 gap-y-10 text-sm">
            <div class="font-bold text-primary mb-5 border-b border-border pb-5">CSV Acadia</div>
            <div class="font-bold text-primary mb-5 border-b border-border pb-5">Base de données</div>
            
            <div class="text-dark">MARQUE</div><div class="text-muted font-mono">marque</div>
            <div class="text-dark">FAMILLE</div><div class="text-muted font-mono">famille</div>
            <div class="text-dark">Part Number</div><div class="text-muted font-mono">part_number</div>
            <div class="text-dark">REF ACADIA</div><div class="text-muted font-mono">ref_acadia</div>
            <div class="text-dark">EAN CODE</div><div class="text-muted font-mono">ean_code</div>
            <div class="text-dark">REF CONSTRUCTEUR</div><div class="text-muted font-mono">ref_constructeur</div>
            <div class="text-dark">DESIGNATION 1</div><div class="text-muted font-mono">designation</div>
            <div class="text-dark">STOCK REEL</div><div class="text-muted font-mono">stock_reel</div>
            <div class="text-dark">PRIX HT</div><div class="text-muted font-mono">prix_ht</div>
            <div class="text-dark">PRIX CLIENT</div><div class="text-muted font-mono">prix_client</div>
            <div class="text-dark">ECOTAXE</div><div class="text-muted font-mono">ecotaxe</div>
            <div class="text-dark">COPIE PRIVEE</div><div class="text-muted font-mono">copie_privee</div>
            <div class="text-dark">POIDS</div><div class="text-muted font-mono">poids</div>
            <div class="text-dark">IMAGE</div><div class="text-muted font-mono">image</div>
            <div class="text-dark">CATEGORIE PRINCIPALE</div><div class="text-muted font-mono">categorie_principale</div>
            <div class="text-dark">CATEGORIE SECONDAIRE</div><div class="text-muted font-mono">categorie_secondaire</div>
            <div class="text-dark">CATEGORIE TERTIAIRE</div><div class="text-muted font-mono">categorie_tertiaire</div>
        </div>
    </div>
</div>