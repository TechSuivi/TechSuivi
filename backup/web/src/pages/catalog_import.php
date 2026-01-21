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
    $message = '<p style="color: red;">❌ Erreur de connexion à la base de données principale.</p>';
} elseif (empty($catalogUrl)) {
    $message = '<div style="background-color: #fff3cd; padding: 15px; border-radius: 4px; border: 1px solid #ffc107; margin-bottom: 20px;">
                <h4 style="color: #856404; margin-top: 0;">⚠️ Configuration requise</h4>
                <p style="color: #856404; margin-bottom: 10px;">
                    L\'URL du catalogue Acadia n\'est pas configurée. Vous devez d\'abord configurer l\'URL et le token API.
                </p>
                <a href="index.php?page=acadia_config"
                   style="background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
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
                $message = '<div style="background-color: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                           <h4 style="color: #1976d2; margin-top: 0;">📋 En-têtes CSV détectés :</h4>
                           <p style="font-family: monospace; font-size: 12px; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">' .
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
                
                $message .= '<p style="color: green;">✅ Fichier CSV téléchargé et analysé avec succès ! Aperçu des 10 premières lignes :</p>';
            } else {
                $message = '<p style="color: red;">❌ Erreur lors de l\'ouverture du fichier CSV</p>';
            }
            
            // Nettoyer le fichier temporaire
            unlink($tempFile);
        } else {
            $message = '<p style="color: red;">❌ Erreur lors du téléchargement du fichier CSV</p>';
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
                    echo '<div style="background-color: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                          <h4 style="color: #1976d2; margin-top: 0;">📋 En-têtes CSV détectés :</h4>
                          <p style="font-family: monospace; font-size: 12px; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">' .
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
                            echo "<p style='color: red; font-size: 12px;'>Ligne $lineNumber: Nombre de colonnes incorrect (" . count($row) . " vs " . count($headers) . ")</p>";
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
                            echo "<p style='color: green; font-size: 12px;'>Traité: " . ($imported + $updated) . " produits...</p>";
                            flush();
                        }
                        
                    } catch (PDOException $e) {
                        $errors++;
                        // En mode debug, afficher les erreurs
                        if (isset($_POST['debug_mode'])) {
                            echo "<p style='color: red; font-size: 12px;'>Erreur ligne $lineNumber: " . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "<p style='color: orange; font-size: 11px;'>Données: " . htmlspecialchars(substr(json_encode($data), 0, 200)) . "...</p>";
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
                    $message = "<div style='background-color: #d4edda; padding: 15px; border-radius: 4px; border: 1px solid #c3e6cb;'>
                               <h4 style='color: #155724; margin-top: 0;'>✅ Importation terminée !</h4>
                               <p><strong>🆕 Nouveaux produits :</strong> {$imported}</p>
                               <p><strong>🔄 Produits mis à jour :</strong> {$updated}</p>
                               <p><strong>❌ Erreurs :</strong> {$errors}</p>
                               <p><strong>📊 Total traité :</strong> {$importStats['total']}</p>
                               <p style='color: #666; font-size: 14px; margin-bottom: 0;'>
                                   <em>Le champ updated_at a été automatiquement mis à jour pour tous les produits modifiés.</em>
                               </p>
                               </div>";
                }
            }
        }
    }
}
?>


<h1>Import Catalogue Acadia</h1>

<?php echo $message; ?>

<div style="max-width: 1000px;">
    <!-- Section d'information -->
    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; border: 1px solid #2196f3; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #1976d2;">📦 Import du catalogue Acadia</h3>
        <p style="color: #666;">
            Cet outil télécharge automatiquement le catalogue depuis Acadia et l'importe dans la table <strong>catalog</strong> de votre base de données.
        </p>
        <p style="color: #666;">
            <strong>URL source :</strong> <a href="<?= $catalogUrl ?>" target="_blank" style="color: #2196f3;">Catalogue Acadia</a>
        </p>
        
        <div style="background-color: #fff; padding: 15px; border-radius: 4px; margin-top: 15px; border-left: 4px solid #2196f3;">
            <h4 style="margin-top: 0; color: #1976d2;">🕒 Gestion automatique des dates</h4>
            <ul style="color: #666; margin-bottom: 0;">
                <li><strong>Nouveaux produits :</strong> Le champ <code>updated_at</code> est défini à la date/heure actuelle</li>
                <li><strong>Mises à jour :</strong> Le champ <code>updated_at</code> est automatiquement mis à jour lors de modifications</li>
                <li><strong>Traçabilité :</strong> Vous pouvez ainsi suivre quand chaque produit a été modifié pour la dernière fois</li>
            </ul>
        </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 20px; margin-bottom: 30px;">
        <form method="POST" style="flex: 1;">
            <div style="background-color: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffc107;">
                <h4 style="margin-top: 0; color: #856404;">🔍 Aperçu du fichier CSV</h4>
                <p style="color: #856404; margin-bottom: 15px;">
                    Téléchargez et analysez les 10 premières lignes pour vérifier la structure.
                </p>
                <button type="submit" name="download_csv" 
                        style="width: 100%; padding: 12px; background-color: #ffc107; color: #212529; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
                    📥 Télécharger et Analyser
                </button>
            </div>
        </form>

        <form method="POST" style="flex: 1;">
            <div style="background-color: #d4edda; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb;">
                <h4 style="margin-top: 0; color: #155724;">🚀 Import complet</h4>
                <p style="color: #155724; margin-bottom: 15px;">
                    Importer tout le catalogue dans la base de données.
                </p>
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                        <input type="checkbox" name="debug_mode" value="1">
                        <span>Mode debug (afficher les erreurs détaillées)</span>
                    </label>
                </div>
                <button type="submit" name="import_catalog"
                        style="width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;"
                        onclick="return confirm('Êtes-vous sûr de vouloir importer tout le catalogue ? Cette opération peut prendre plusieurs minutes.')">
                    🗄️ Importer le Catalogue
                </button>
            </div>
        </form>
    </div>

    <!-- Aperçu des données CSV -->
    <?php if (!empty($csvData)): ?>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #495057;">📊 Aperçu des données CSV</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background-color: #e9ecef;">
                        <?php foreach (array_keys($csvData[0]) as $header): ?>
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($csvData as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?= htmlspecialchars(substr($cell, 0, 50)) ?><?= strlen($cell) > 50 ? '...' : '' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mapping des colonnes -->
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;">
        <h3 style="margin-top: 0; color: #495057;">🔄 Mapping des colonnes</h3>
        <p style="color: #666; margin-bottom: 15px;">Correspondance entre les colonnes CSV et la base de données :</p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
            <div><strong>CSV Acadia → Base de données</strong></div>
            <div></div>
            <div>MARQUE → marque</div>
            <div>FAMILLE → famille</div>
            <div>Part Number → part_number</div>
            <div>REF ACADIA → ref_acadia</div>
            <div>EAN CODE → ean_code</div>
            <div>REF CONSTRUCTEUR → ref_constructeur</div>
            <div>DESIGNATION 1 → designation</div>
            <div>STOCK REEL → stock_reel</div>
            <div>PRIX HT → prix_ht</div>
            <div>PRIX CLIENT → prix_client</div>
            <div>ECOTAXE → ecotaxe</div>
            <div>COPIE PRIVEE → copie_privee</div>
            <div>POIDS → poids</div>
            <div>IMAGE → image</div>
            <div>CATEGORIE PRINCIPALE → categorie_principale</div>
            <div>CATEGORIE SECONDAIRE → categorie_secondaire</div>
            <div>CATEGORIE TERTIAIRE → categorie_tertiaire</div>
        </div>
    </div>
</div>

<style>
.content h1 {
    margin-bottom: 20px;
}

button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

table {
    font-family: monospace;
}
</style>