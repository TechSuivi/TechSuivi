<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$message = ''; // Pour les messages de succès ou d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    // Récupération et nettoyage des données du formulaire
    $ref_acadia = trim($_POST['ref_acadia'] ?? '');
    $ean_code = trim($_POST['ean_code'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $prix_achat_ht = trim($_POST['prix_achat_ht'] ?? '');
    $prix_vente_ttc = trim($_POST['prix_vente_ttc'] ?? '');
    $fournisseur = trim($_POST['fournisseur'] ?? '');
    $numero_commande = trim($_POST['numero_commande'] ?? '');

    // Validation des champs obligatoires
    $errors = [];
    if (empty($ean_code)) {
        $errors[] = 'Le code EAN est obligatoire.';
    }
    if (empty($designation)) {
        $errors[] = 'La désignation est obligatoire.';
    }
    if (empty($prix_achat_ht) || !is_numeric($prix_achat_ht) || floatval($prix_achat_ht) < 0) {
        $errors[] = 'Le prix d\'achat HT doit être un nombre positif.';
    }
    if (empty($prix_vente_ttc) || !is_numeric($prix_vente_ttc) || floatval($prix_vente_ttc) < 0) {
        $errors[] = 'Le prix de vente TTC doit être un nombre positif.';
    }
    if (empty($fournisseur)) {
        $errors[] = 'Le nom du fournisseur est obligatoire.';
    }
    if (empty($numero_commande)) {
        $errors[] = 'Le numéro de commande est obligatoire.';
    }

    // Vérifier si le code EAN existe déjà - DÉSACTIVÉ pour permettre les doublons
    /*
    if (empty($errors)) {
        try {
            $checkSql = "SELECT COUNT(*) FROM Stock WHERE ean_code = :ean_code";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->bindParam(':ean_code', $ean_code);
            $checkStmt->execute();
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                $errors[] = 'Ce code EAN existe déjà dans la base de données.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la vérification du code EAN : ' . htmlspecialchars($e->getMessage());
        }
    }
    */

    if (!empty($errors)) {
        $message = '<p style="color: red;">' . implode('<br>', $errors) . '</p>';
    } else {
        try {
            // Récupérer les SNs
            $sn_list = trim($_POST['sn_list'] ?? '');
            $sns = [];
            
            if (!empty($sn_list)) {
                // Découper par ligne et nettoyer
                $lines = explode("\n", $sn_list);
                foreach ($lines as $line) {
                    $clean_sn = trim($line);
                    if (!empty($clean_sn)) {
                        $sns[] = $clean_sn;
                    }
                }
            }
            
            // Si aucun SN n'est fourni, on ajoute au moins un produit (sans SN ou SN null selon la DB)
            // Mais l'utilisateur veut ajouter des SNs. Si la liste est vide, on ajoute 1 produit avec SN NULL (ou 0 si int).
            // Comme le champ est INT(11), on va assumer que si vide => NULL ou 0.
            // Si la liste est vide, on met un tableau avec une entrée vide pour faire une itération
            if (empty($sns)) {
                $sns[] = ''; // Default to empty string instead of null to avoid SQL constraint error
            }

            // Récup. date commande
            $date_commande = $_POST['date_commande'] ?? null;
            if (empty($date_commande)) $date_commande = null;

            $success_count = 0;
            $error_count = 0;

            // Si des erreurs d'upload sont survenues, on arrête tout
            if (!empty($errors)) {
                 $message = '<p style="color: red;">' . implode('<br>', $errors) . '</p>';
            } else {

            $sql = "INSERT INTO Stock (ref_acadia, ean_code, designation, prix_achat_ht, prix_vente_ttc, fournisseur, numero_commande, date_commande, SN, date_ajout) 
                    VALUES (:ref_acadia, :ean_code, :designation, :prix_achat_ht, :prix_vente_ttc, :fournisseur, :numero_commande, :date_commande, :sn, NOW())";
            $stmt = $pdo->prepare($sql);

            foreach ($sns as $sn) {
                $stmt->bindParam(':ref_acadia', $ref_acadia);
                $stmt->bindParam(':ean_code', $ean_code);
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':prix_achat_ht', $prix_achat_ht);
                $stmt->bindParam(':prix_vente_ttc', $prix_vente_ttc);
                $stmt->bindParam(':fournisseur', $fournisseur);
                $stmt->bindParam(':numero_commande', $numero_commande);
                $stmt->bindParam(':date_commande', $date_commande);
                $stmt->bindParam(':sn', $sn); // Peut être null

                // $stmt->bindParam(':invoice_file', $invoice_file_path); // Colonne supprimée

                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }

            }


            if ($success_count > 0) {
                $msg = "Produit(s) ajouté(s) avec succès : $success_count";
                if ($error_count > 0) {
                    $msg .= " (Erreurs : $error_count)";
                }
                $message = '<p style="color: green;">' . $msg . '</p>';
                // Réinitialiser les variables pour vider le formulaire
                $ref_acadia = $ean_code = $designation = $prix_achat_ht = $prix_vente_ttc = $fournisseur = $numero_commande = $date_commande = '';
            } else {
                $message = '<p style="color: red;">Erreur lors de l\'ajout du/des produit(s).</p>';
            }
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<p style="color: red;">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</p>';
}

// Récupération des paramètres GET pour pré-remplissage
$preFillSupplier = trim($_GET['supplier'] ?? '');
$preFillOrder = trim($_GET['order'] ?? '');
?>



<!-- Toolbar Compacte : Synchronisation -->
<div style="display: flex; justify-content: space-between; align-items: center; background-color: #f3e5f5; padding: 10px 15px; border-radius: 4px; border: 1px solid #9c27b0; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <span style="font-weight: bold; color: #7b1fa2;">🔄 Base Produits</span>
        <span style="font-size: 0.9em; color: #555;">Dernière synchro : <span id="last_sync_date" style="font-weight: 500;">Chargement...</span></span>
        <span id="sync_status_field" style="font-size: 0.9em; color: #555; background: #fff; padding: 2px 8px; border-radius: 10px; border: 1px solid #ddd;">Prêt</span>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <div id="sync_status" style="font-size: 0.9em;"></div>
        <button type="button" id="sync_catalog_btn" style="padding: 5px 15px; background-color: #9c27b0; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.9em;">
            Actualiser
        </button>
    </div>
</div>

<?php echo $message; ?>

<!-- Section Principale avec Colonne Gauche (Fournisseur + Recherche) et Colonne Droite (Produit) -->
<div style="display: flex; gap: 20px; margin-bottom: 20px; align-items: flex-start;">
    
    <!-- WRAPPER GAUCHE : Fournisseur + Recherche -->
    <div style="flex: 1; display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Section Fournisseur -->
        <div class="info-section" style="padding: 15px; background-color: #e3f2fd; border-radius: 4px; border: 1px solid #2196f3;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; cursor: pointer;" onclick="toggleSupplierSection()">
                <h3 style="margin: 0; color: #1976d2;">Informations Fournisseur</h3>
                <span id="toggle_supplier_icon" style="font-size: 1.2em; color: #1976d2; transition: transform 0.3s;">▼</span>
            </div>
            
            <div id="supplier_content" style="display: flex; gap: 20px; transition: all 0.3s ease-in-out;">
                <!-- Colonne Gauche : Inputs -->
                <div style="flex: 1;">
                    <div style="margin-bottom: 15px;">
                        <label for="supplier_name" style="display: block; margin-bottom: 5px; font-weight: bold;">Nom du fournisseur * :</label>
                        <input type="text" id="supplier_name" list="fournisseurs_list" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Tapez ou sélectionnez un fournisseur" autocomplete="off" value="<?= htmlspecialchars($preFillSupplier) ?>">
                        <datalist id="fournisseurs_list">
                            <!-- Les options seront chargées dynamiquement -->
                        </datalist>
                        <small style="color: #666;">Vous pouvez taper du texte libre ou sélectionner dans la liste</small>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="order_number" style="display: block; margin-bottom: 5px; font-weight: bold;">Numéro de commande * :</label>
                        <input type="text" id="order_number" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Numéro de commande" value="<?= htmlspecialchars($preFillOrder) ?>">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="order_date" style="display: block; margin-bottom: 5px; font-weight: bold;">Date de commande :</label>
                        <input type="date" id="order_date" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    
                    <div style="margin-bottom: 15px; border-top: 1px dashed #ccc; padding-top: 10px;">
                         <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #0056b3;">📄 Documents (Factures / BL) :</label>
                         
                         <!-- Liste des fichiers existants -->
                         <div id="documents_list" style="margin-bottom: 10px;"></div>
    
                         <!-- Zone d'upload -->
                         <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="file" id="invoice_upload" accept=".pdf,image/*" capture="environment" style="display: none;" multiple>
                            <button type="button" id="btn_upload_start" onclick="event.stopPropagation(); triggerUpload()" style="padding: 5px 10px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px; cursor: not-allowed; display: flex; align-items: center; gap: 5px; opacity: 0.6;" disabled>
                                📷 Ajouter un fichier
                            </button>
                         </div>
                         <small style="color: #888; display: block; margin-top: 3px; font-size: 10px;">Remplissez Fournisseur et N° Commande pour activer l'upload.</small>
                         <div id="upload_status"></div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button type="button" id="validate_supplier" style="padding: 8px 15px; background-color: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;" onclick="event.stopPropagation()">Valider</button>
                        <button type="button" id="clear_supplier" style="padding: 8px 15px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="event.stopPropagation()">Effacer</button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div id="supplier_status" style="font-weight: bold;"></div>
                        <a href="index.php?page=fournisseurs_list" target="_blank" style="color: #2196f3; text-decoration: none; font-size: 14px;" onclick="event.stopPropagation()">+ Gérer les fournisseurs</a>
                    </div>
                </div>
    
                <!-- Colonne Droite : QR Code & PWA -->
                <div id="pwa_column" style="flex: 0 0 220px; display: none; flex-direction: column; align-items: center; justify-content: flex-start; border-left: 1px dashed #ccc; padding-left: 15px;">
                     <div style="font-weight: bold; color: #0056b3; margin-bottom: 10px; font-size: 14px; text-align: center;">📱 Ajouter photo via Mobile</div>
                     <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 10px;"></div>
                     <div id="qr_link" style="font-size: 10px; color: #888; margin-bottom: 5px; word-break: break-all; text-align: center;"></div>
                     <small style="color: #666; font-size: 11px; text-align: center;">Scannez pour envoyer</small>
                </div>
            </div>
        </div>

        <!-- Section de recherche de produits existants (Déplacée ici) -->
        <div class="info-section" style="padding: 15px; background-color: #fff3cd; border-radius: 4px; border: 1px solid #ffc107;">
            <h3 style="margin-top: 0; color: #856404;">Vérifier si le produit existe déjà</h3>
            <div style="margin-bottom: 15px;">
                <label for="product_search" style="display: block; margin-bottom: 5px; font-weight: bold;">Rechercher un produit :</label>
                
                <!-- SÉLECTEUR DE SCOPE -->
                <div style="display: flex; gap: 15px; margin-bottom: 8px; font-size: 0.9em; background: rgba(255,255,255,0.5); padding: 5px; border-radius: 4px;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="search_scope" value="auto" checked> 🤖 Auto
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="search_scope" value="catalog"> 📋 Catalogue
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="search_scope" value="stock"> 📦 Stock
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="search_scope" value="web"> 🌐 Web
                    </label>
                </div>

                <input type="text" id="product_search" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Tapez un code EAN, une désignation, une référence ou un fournisseur...">
                <small style="color: #666; display: block; margin-top: 5px;">
                    <strong>Recherche Auto :</strong> 1️⃣ Catalogue (📋) &nbsp;➔&nbsp; 2️⃣ Stock (📦) &nbsp;➔&nbsp; 3️⃣ Web (🌐 UPCItemDB)
                </small>
            </div>
            <div id="search_results" style="display: none;">
                <h4 style="margin: 10px 0; color: #856404;">Produits trouvés :</h4>
                <div id="results_container" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                    <!-- Les résultats seront affichés ici -->
                </div>
            </div>
            <div id="no_results" style="display: none; color: #28a745; font-weight: bold; margin-top: 10px;">
                ✓ Aucun produit trouvé - Vous pouvez ajouter ce nouveau produit
            </div>
        </div>

    </div>
    
    <!-- Colonne Droite : Formulaire Produit -->
    <div class="info-section" style="flex: 1; padding: 15px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">
        <form action="index.php?page=stock_add" method="POST" id="product_form" enctype="multipart/form-data">
    <div style="margin-bottom: 15px;">
        <label for="ref_acadia" style="display: block; margin-bottom: 5px;">Référence Acadia :</label>
        <input type="text" id="ref_acadia" name="ref_acadia" value="<?= htmlspecialchars($ref_acadia ?? '') ?>" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <small style="color: #666;">Optionnel - Référence interne Acadia</small>
    </div>

    <div style="margin-bottom: 15px;">
        <label for="ean_code" style="display: block; margin-bottom: 5px;">Code EAN * :</label>
        <input type="text" id="ean_code" name="ean_code" value="<?= htmlspecialchars($ean_code ?? '') ?>" required style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <small style="color: #666;">Code-barres EAN du produit (Doublons autorisés)</small>
    </div>

    <div style="margin-bottom: 15px;">
        <label for="sn_list" style="display: block; margin-bottom: 5px;">Numéros de Série (SN) :</label>
        <textarea id="sn_list" name="sn_list" style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-height: 100px; font-family: monospace;" placeholder="Entrez un numéro de série par ligne..."></textarea>
        <small style="color: #666;">Pour ajouter plusieurs produits identiques, entrez un SN par ligne. Si vide, un seul produit sera ajouté sans SN.</small>
    </div>

    <div style="margin-bottom: 15px;">
        <label for="designation" style="display: block; margin-bottom: 5px;">Désignation * :</label>
        <textarea id="designation" name="designation" required style="width: 98%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-height: 80px; resize: vertical;"><?= htmlspecialchars($designation ?? '') ?></textarea>
        <small style="color: #666;">Description complète du produit</small>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label for="prix_achat_ht" style="display: block; margin-bottom: 5px;">Prix d'achat HT * :</label>
            <input type="number" id="prix_achat_ht" name="prix_achat_ht" value="<?= htmlspecialchars($prix_achat_ht ?? '') ?>" step="0.01" min="0" required style="width: 90%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <small style="color: #666;">En euros, hors taxes</small>
        </div>
        <div style="flex: 1;">
            <label for="prix_vente_ttc" style="display: block; margin-bottom: 5px;">Prix de vente TTC * :</label>
            <input type="number" id="prix_vente_ttc" name="prix_vente_ttc" value="<?= htmlspecialchars($prix_vente_ttc ?? '') ?>" step="0.01" min="0" required style="width: 90%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <small style="color: #666;">En euros, toutes taxes comprises</small>
        </div>
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px;">Prix suggéré TTC :</label>
            <input type="text" id="prix_suggere" readonly style="width: 95%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; background-color: #f0f8ff; color: #0066cc; font-weight: bold;" placeholder="Calculé automatiquement">
            <small style="color: #666;">HT × 1.2 (TVA) × 1.3 (marge)</small>
            <div id="suggestion-details" style="font-size: 11px; color: #888; margin-top: 2px;"></div>
        </div>
    </div>

    <!-- Champs cachés pour les données fournisseur -->
    <input type="hidden" id="fournisseur" name="fournisseur" value="<?= htmlspecialchars($fournisseur ?? '') ?>">
    <input type="hidden" id="numero_commande" name="numero_commande" value="<?= htmlspecialchars($numero_commande ?? '') ?>">
    <input type="hidden" id="date_commande" name="date_commande" value="<?= htmlspecialchars($date_commande ?? '') ?>">
    <!-- invoice_file_path supprimé car géré par table dédiée -->



            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" style="padding: 10px 20px; background-color: var(--accent-color); color: white; border: none; border-radius: 4px; cursor: pointer;">Ajouter le produit</button>
                <a href="index.php?page=stock_list" style="padding: 10px 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Retour à la liste</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Charger la date de dernière synchronisation
    function loadLastSyncDate() {
        fetch('api/get_last_sync.php')
            .then(response => response.json())
            .then(data => {
                const dateField = document.getElementById('last_sync_date');
                if (dateField) {
                    if (data.success && data.formatted_date) {
                        dateField.innerText = `${data.formatted_date} (${data.relative_time})`;
                    } else {
                        dateField.innerText = 'Jamais';
                    }
                }
            })
            .catch(err => {
                console.error('Erreur loading sync date:', err);
                const dateField = document.getElementById('last_sync_date');
                if (dateField) dateField.innerText = 'Erreur';
            });
    }

    // Charger la liste des fournisseurs
    function loadFournisseurs() {
        fetch('api/get_fournisseurs.php')
            .then(response => response.json())
            .then(data => {
                const datalist = document.getElementById('fournisseurs_list');
                datalist.innerHTML = ''; // Vider la liste existante
                
                if (Array.isArray(data)) {
                    data.forEach(fournisseur => {
                        const option = document.createElement('option');
                        option.value = fournisseur.Fournisseur;
                        datalist.appendChild(option);
                    });
                } else {
                    console.error('Erreur lors du chargement des fournisseurs:', data.error || 'Format de données invalide');
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des fournisseurs:', error);
            });
    }

    // Charger les données au démarrage
    loadFournisseurs();
    loadLastSyncDate();

    // Gestion de la recherche de produits existants
    const productSearchInput = document.getElementById('product_search');
    const searchResults = document.getElementById('search_results');
    const resultsContainer = document.getElementById('results_container');
    const noResults = document.getElementById('no_results');
    let searchTimeout;

    function searchProducts() {
        const searchTerm = productSearchInput.value.trim();
        // Récupérer le scope sélectionné
        const selectedScope = document.querySelector('input[name="search_scope"]:checked').value;
        
        if (searchTerm.length < 2) {
            searchResults.style.display = 'none';
            noResults.style.display = 'none';
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch(`api/search_products.php?q=${encodeURIComponent(searchTerm)}&scope=${selectedScope}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Erreur de recherche:', data.error);
                        return;
                    }

                    if (Array.isArray(data) && data.length > 0) {
                        displaySearchResults(data);
                        searchResults.style.display = 'block';
                        noResults.style.display = 'none';
                    } else {
                        searchResults.style.display = 'none';
                        noResults.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche:', error);
                });
        }, 300);
    }

    function displaySearchResults(products) {
        let html = '';
        products.forEach((product, index) => {
            const dateAjout = product.date_ajout ? new Date(product.date_ajout).toLocaleDateString('fr-FR') : 'N/A';
            const prixAchat = parseFloat(product.prix_achat_ht || 0).toFixed(2);
            const prixVente = parseFloat(product.prix_vente_ttc || 0).toFixed(2);
            
            const source = product.source || 'stock';
            let sourceIcon = '📦';
            let sourceLabel = 'Stock existant';
            let sourceColor = '#007bff';
            let extraInfo = '';

            if (source === 'catalog') {
                sourceIcon = '📋';
                sourceLabel = 'Catalogue Acadia';
                sourceColor = '#28a745';
            } else if (source === 'web') {
                sourceIcon = '🌐';
                sourceLabel = 'UPCItemDB (Web)';
                sourceColor = '#673ab7'; // Deep purple
            }
            
            html += `
                <div class="product-result" style="padding: 10px; border-bottom: 1px solid #eee; background-color: #f8f9fa; cursor: pointer; transition: background-color 0.2s;"
                     onmouseover="this.style.backgroundColor='#e9ecef'"
                     onmouseout="this.style.backgroundColor='#f8f9fa'"
                     onclick="selectProduct(${index})">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong style="color: ${sourceColor}; font-size: 1.1em;">${sourceIcon} ${sourceLabel}</strong><br>
                            <strong>EAN:</strong> ${product.ean_code || 'N/A'}<br>
                            <strong>Désignation:</strong> ${product.designation || 'N/A'}<br>
                            <strong>Marque/Four:</strong> ${product.fournisseur || 'N/A'}
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            ${source === 'stock' || source === 'catalog' ? `
                                <div><strong>Prix Achat HT:</strong> ${prixAchat}€</div>
                                <div><strong>Prix Vente TTC:</strong> ${prixVente}€</div>
                                <div><small>Ajouté le: ${dateAjout}</small></div>
                            ` : `<div style="font-size: 0.9em; color: #888;">(Prix web indicatifs non récupérés)</div>`}
                        </div>
                    </div>
                </div>
            `;
        });
        resultsContainer.innerHTML = html;
        
        // Stocker les produits pour la sélection
        window.searchedProducts = products;
    }
    
    // Écouter les changements sur le scope pour relancer la recherche
    document.querySelectorAll('input[name="search_scope"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (productSearchInput.value.length >= 2) {
                searchProducts();
            }
        });
    });

    productSearchInput.addEventListener('input', searchProducts);

    // Fonction globale pour sélectionner un produit
    window.selectProduct = function(index) {
        const product = window.searchedProducts[index];
        if (!product) {
            console.error('Produit non trouvé à l\'index:', index);
            return;
        }

        console.log('Sélection du produit:', product);

        // Pré-remplir les champs du formulaire
        const refAcadiaField = document.getElementById('ref_acadia');
        const eanCodeField = document.getElementById('ean_code');
        const designationField = document.getElementById('designation');
        const prixAchatField = document.getElementById('prix_achat_ht');
        const prixVenteField = document.getElementById('prix_vente_ttc');

        if (refAcadiaField) refAcadiaField.value = product.ref_acadia || '';
        if (eanCodeField) eanCodeField.value = product.ean_code || '';
        if (designationField) designationField.value = product.designation || '';
        if (prixAchatField) prixAchatField.value = product.prix_achat_ht || '';
        if (prixVenteField) prixVenteField.value = product.prix_vente_ttc || '';
        
        // Calculer le prix suggéré après avoir rempli le prix d'achat
        calculateSuggestedPrice();
        
        // Pré-remplir et valider automatiquement le fournisseur
        // NE PAS modifier les informations fournisseur - garder celles déjà sélectionnées
        // Vérifier si le fournisseur est déjà validé
        const supplierValidated = localStorage.getItem('stock_supplier_validated') === 'true';
        
        if (!supplierValidated) {
            // Si aucun fournisseur n'est validé, utiliser celui du produit trouvé
            const supplierName = product.fournisseur || '';
            const orderNumber = product.numero_commande || '';
            
            if (supplierName && orderNumber) {
                if (supplierNameInput) supplierNameInput.value = supplierName;
                if (orderNumberInput) orderNumberInput.value = orderNumber;
                
                // Déclencher automatiquement la validation du fournisseur
                localStorage.setItem('stock_supplier_name', supplierName);
                localStorage.setItem('stock_order_number', orderNumber);
                localStorage.setItem('stock_supplier_validated', 'true');
                
                if (fournisseurInput) fournisseurInput.value = supplierName;
                if (numeroCommandeInput) numeroCommandeInput.value = orderNumber;
                if (supplierStatus) supplierStatus.innerHTML = '<span style="color: #4caf50;">✓ Fournisseur validé automatiquement</span>';
                if (productForm) productForm.style.display = 'block';
            }
        } else {
            // Si un fournisseur est déjà validé, s'assurer que le formulaire est visible
            if (productForm) productForm.style.display = 'block';
        }

        // Masquer les résultats de recherche
        if (searchResults) searchResults.style.display = 'none';
        if (noResults) noResults.style.display = 'none';
        if (productSearchInput) productSearchInput.value = '';

        // Afficher un message de confirmation
        const searchSection = document.querySelector('.info-section[style*="background-color: #fff3cd"]');
        if (searchSection) {
            const confirmationMsg = document.createElement('div');
            confirmationMsg.style.cssText = 'background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 10px; border: 1px solid #c3e6cb;';
            confirmationMsg.innerHTML = '✓ Produit sélectionné et formulaire pré-rempli';
            searchSection.appendChild(confirmationMsg);
            
            setTimeout(() => {
                if (confirmationMsg.parentNode) {
                    confirmationMsg.remove();
                }
            }, 3000);
        }
    };

    // Gestion de la section fournisseur
    const supplierNameInput = document.getElementById('supplier_name');
    const orderNumberInput = document.getElementById('order_number');
    const orderDateInput = document.getElementById('order_date');
    const validateSupplierBtn = document.getElementById('validate_supplier');
    const clearSupplierBtn = document.getElementById('clear_supplier');
    const supplierStatus = document.getElementById('supplier_status');
    const productForm = document.getElementById('product_form'); // Keep this line

    const fournisseurInput = document.getElementById('fournisseur');
    const numeroCommandeInput = document.getElementById('numero_commande');
    const dateCommandeInput = document.getElementById('date_commande');

    // --- GESTION DES DOCUMENTS (Initialization) ---
    const documentsList = document.getElementById('documents_list');
    const btnUploadStart = document.getElementById('btn_upload_start');
    const invoiceUpload = document.getElementById('invoice_upload');
    const uploadStatus = document.getElementById('upload_status');

    // Charger les données fournisseur du localStorage si elles existent
    const savedSupplierName = localStorage.getItem('stock_supplier_name');
    const savedOrderNumber = localStorage.getItem('stock_order_number');
    const savedOrderDate = localStorage.getItem('stock_order_date');
    const savedSupplierValidated = localStorage.getItem('stock_supplier_validated');

    if (savedSupplierName) supplierNameInput.value = savedSupplierName;
    if (savedOrderNumber) orderNumberInput.value = savedOrderNumber;
    if (savedOrderDate) {
        orderDateInput.value = savedOrderDate;
        dateCommandeInput.value = savedOrderDate; // sync hidden
    } else {
        // Default to today if nothing saved
        const today = new Date().toISOString().split('T')[0];
        orderDateInput.value = today;
        dateCommandeInput.value = today; // sync hidden
    }

    if (savedSupplierName && savedOrderNumber && savedSupplierValidated === 'true') {
        validateSupplier();
    } else {
        // Vérifier si des paramètres de pré-remplissage sont présents (GET)
        if (supplierNameInput.value.trim() !== '' && orderNumberInput.value.trim() !== '') {
             validateSupplier();
        } else {
             productForm.style.display = 'none';
        }
    }

    function validateSupplier() {
        const name = supplierNameInput.value.trim();
        const number = orderNumberInput.value.trim();
        const msgDate = orderDateInput.value;

        if (name && number) {
            supplierStatus.innerHTML = '<span style="color: green; font-weight: bold;">✓ Fournisseur validé</span>';
            fournisseurInput.value = name;
            numeroCommandeInput.value = number;
            dateCommandeInput.value = msgDate; // Update hidden

            productForm.style.display = 'block';

            // Sauvegarder dans localStorage
            localStorage.setItem('stock_supplier_name', name);
            localStorage.setItem('stock_order_number', number);
            localStorage.setItem('stock_order_date', msgDate);
            localStorage.setItem('stock_supplier_validated', 'true');
            
            // Check upload but also check docs
            checkUploadEnabled();

            // Generate QR Code
            const pwaColumn = document.getElementById('pwa_column');
            const qrDiv = document.getElementById('qrcode');
            if (pwaColumn && qrDiv) {
                pwaColumn.style.display = 'flex'; // Use flex for column layout
                qrDiv.innerHTML = '';
                
                const uploadUrl = window.location.origin + '/pwa/?' +
                                  'supplier=' + encodeURIComponent(name) + 
                                  '&order=' + encodeURIComponent(number);
                
                new QRCode(qrDiv, {
                    text: uploadUrl,
                    width: 200,
                    height: 200
                });
                
                // Afficher le lien en clair
                const linkDiv = document.getElementById('qr_link');
                if (linkDiv) {
                    linkDiv.innerHTML = `<a href="${uploadUrl}" target="_blank" style="color: #888; text-decoration: underline;">${uploadUrl}</a>`;
                }
            }
        } else {

            supplierStatus.innerHTML = '<span style="color: #f44336;">⚠ Veuillez remplir tous les champs</span>';
            productForm.style.display = 'none';
        }
    }

    validateSupplierBtn.addEventListener('click', validateSupplier);
    clearSupplierBtn.addEventListener('click', function() {
        supplierNameInput.value = '';
        orderNumberInput.value = '';
        fournisseurInput.value = '';
        numeroCommandeInput.value = '';
        supplierStatus.innerHTML = '';
        productForm.style.display = 'none';
        
        // Cacher QR code
        const pwaColumn = document.getElementById('pwa_column');
        if (pwaColumn) pwaColumn.style.display = 'none';

        // Clear documents list visual
        if (typeof documentsList !== 'undefined') {
            documentsList.innerHTML = '';
        } else {
             const list = document.getElementById('documents_list');
             if(list) list.innerHTML = '';
        }

        // Reset Date to today
        const today = new Date().toISOString().split('T')[0];
        orderDateInput.value = today;
        dateCommandeInput.value = today;
        
        localStorage.removeItem('stock_supplier_name');
        localStorage.removeItem('stock_order_number');
        localStorage.removeItem('stock_supplier_validated');
        localStorage.removeItem('stock_order_date');

        // Reset upload capability
        if (typeof checkUploadEnabled === 'function') {
            checkUploadEnabled();
        }
    });



    // --- GESTION DES DOCUMENTS (Nouvelle Version) ---
    // (Variables déplacées en haut du script)

    window.triggerUpload = function() {
        invoiceUpload.click();
    };

    function checkUploadEnabled() {
        const sup = supplierNameInput.value.trim();
        const ord = orderNumberInput.value.trim();
        
        if (sup && ord) {
            btnUploadStart.disabled = false;
            btnUploadStart.style.cursor = 'pointer';
            btnUploadStart.style.opacity = '1';
            btnUploadStart.title = "Cliquez pour ajouter un fichier";
            loadDocuments(sup, ord); // Charger les docs existants
        } else {
            btnUploadStart.disabled = true;
            btnUploadStart.style.cursor = 'not-allowed';
            btnUploadStart.style.opacity = '0.6';
            btnUploadStart.title = "Remplissez Fournisseur et N° Commande d'abord";
            documentsList.innerHTML = ''; // Vider la liste si incomplet
        }
    }

    function loadDocuments(fournisseur, numeroCommande) {
        if (!fournisseur || !numeroCommande) return;

        fetch(`api/get_order_documents.php?fournisseur=${encodeURIComponent(fournisseur)}&numero_commande=${encodeURIComponent(numeroCommande)}`)
            .then(res => res.json())
            .then(docs => {
                documentsList.innerHTML = '';
                if (docs && docs.length > 0) {
                    docs.forEach(doc => {
                        const div = document.createElement('div');
                        div.style.cssText = "display: flex; justify-content: space-between; align-items: center; background: white; padding: 4px; margin-bottom: 2px; border: 1px solid #eee; border-radius: 3px; font-size: 12px;";
                        div.innerHTML = `
                            <span>📄 <a href="${doc.file_path}" target="_blank">${doc.original_name}</a></span>
                            <span style="color: red; cursor: pointer; font-weight: bold;" onclick="deleteDocument(${doc.id}, '${fournisseur}', '${numeroCommande}')">×</span>
                        `;
                        documentsList.appendChild(div);
                    });
                } else {
                    documentsList.innerHTML = '<em style="font-size: 11px; color: #999;">Aucun document lié</em>';
                }
            })
            .catch(err => console.error("Erreur chargement docs", err));
    }

    window.deleteDocument = function(id, fournisseur, numeroCommande) {
        if (!confirm('Supprimer ce document ?')) return;
        
        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_order_document.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadDocuments(fournisseur, numeroCommande);
            } else {
                alert('Erreur: ' + data.error);
            }
        });
    };

    if (invoiceUpload) {
        invoiceUpload.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                const fournisseur = supplierNameInput.value.trim();
                const numeroCommande = orderNumberInput.value.trim();
                
                if (!fournisseur || !numeroCommande) return;

                uploadStatus.innerHTML = '<span style="color: orange;">Envoi...</span>';

                // Upload simple ou multiple (on traite le premier pour l'instant ou boucle)
                // Ici on gère 1 par 1 pour simplifier l'UX ou boucle si multiple
                const file = this.files[0];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('fournisseur', fournisseur);
                formData.append('numero_commande', numeroCommande);

                fetch('api/upload_stock_document.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        uploadStatus.innerHTML = '<span style="color: green;">✓ Ajouté</span>';
                        setTimeout(() => uploadStatus.innerHTML = '', 2000);
                        loadDocuments(fournisseur, numeroCommande);
                        // Reset input to allow re-uploading same file if needed
                        invoiceUpload.value = ''; 
                    } else {
                        uploadStatus.innerHTML = `<span style="color: red;">Erreur: ${data.error}</span>`;
                    }
                })
                .catch(err => {
                    uploadStatus.innerHTML = '<span style="color: red;">Erreur réseau</span>';
                });
            }
        });
    }

    // Ecouteurs pour activer/désactiver boutons
    supplierNameInput.addEventListener('input', checkUploadEnabled);
    orderNumberInput.addEventListener('input', checkUploadEnabled);
    
    // Au chargement (si pré-rempli par localStorage)
    setTimeout(checkUploadEnabled, 500); // Petit délai pour laisser le temps au localStorage de remplir




    // Validation en temps réel des prix
    const prixAchatInput = document.getElementById('prix_achat_ht');
    const prixVenteInput = document.getElementById('prix_vente_ttc');

    function validatePrice(input) {
        const value = parseFloat(input.value);
        if (input.value !== '' && (isNaN(value) || value < 0)) {
            input.style.borderColor = 'red';
        } else {
            input.style.borderColor = '#ccc';
        }
    }

    function calculateMargin() {
        const prixAchat = parseFloat(prixAchatInput.value);
        const prixVente = parseFloat(prixVenteInput.value);
        
        if (!isNaN(prixAchat) && !isNaN(prixVente) && prixAchat > 0) {
            // Convertir le prix de vente TTC en HT pour calculer la marge réelle
            // Prix de vente HT = Prix de vente TTC / 1.2 (en supposant 20% de TVA)
            const prixVenteHT = prixVente / 1.2;
            const marge = ((prixVenteHT - prixAchat) / prixAchat * 100).toFixed(2);
            
            const marginInfo = document.getElementById('margin-info');
            if (!marginInfo) {
                const info = document.createElement('small');
                info.id = 'margin-info';
                info.style.color = marge >= 0 ? '#28a745' : '#dc3545';
                info.style.display = 'block';
                info.style.marginTop = '5px';
                prixVenteInput.parentNode.appendChild(info);
            }
            document.getElementById('margin-info').textContent = `Marge: ${marge}% (${prixVenteHT.toFixed(2)}€ HT - ${prixAchat.toFixed(2)}€ HT)`;
            document.getElementById('margin-info').style.color = marge >= 0 ? '#28a745' : '#dc3545';
        } else {
            const marginInfo = document.getElementById('margin-info');
            if (marginInfo) {
                marginInfo.remove();
            }
        }
    }

    function calculateSuggestedPrice() {
        const prixAchat = parseFloat(prixAchatInput.value);
        const prixSuggereInput = document.getElementById('prix_suggere');
        const suggestionDetails = document.getElementById('suggestion-details');
        
        if (!isNaN(prixAchat) && prixAchat > 0) {
            // Calcul : Prix HT × 1.2 (TVA) × 1.3 (marge)
            const prixAvecTVA = prixAchat * 1.2;
            const prixSuggere = prixAvecTVA * 1.3;
            
            prixSuggereInput.value = prixSuggere.toFixed(2) + ' €';
            suggestionDetails.textContent = `Détail: ${prixAchat.toFixed(2)}€ HT → ${prixAvecTVA.toFixed(2)}€ TTC → ${prixSuggere.toFixed(2)}€ avec marge`;
        } else {
            prixSuggereInput.value = '';
            suggestionDetails.textContent = '';
        }
    }

    prixAchatInput.addEventListener('input', function() {
        validatePrice(this);
        calculateMargin();
        calculateSuggestedPrice();
    });

    prixVenteInput.addEventListener('input', function() {
        validatePrice(this);
        calculateMargin();
    });

    // Validation du code EAN (doit contenir uniquement des chiffres)
    const eanInput = document.getElementById('ean_code');
    eanInput.addEventListener('input', function() {
        const value = this.value;
        // Permettre uniquement les chiffres
        this.value = value.replace(/[^0-9]/g, '');
        
        // Validation de la longueur (codes EAN courants: 8, 13 chiffres)
        if (this.value.length > 0 && this.value.length !== 8 && this.value.length !== 13) {
            this.style.borderColor = 'orange';
        } else {
            this.style.borderColor = '#ccc';
        }
    });

    // Gestion de la synchronisation du catalogue
    const syncBtn = document.getElementById('sync_catalog_btn');
    const syncStatus = document.getElementById('sync_status');
    const syncStatusField = document.getElementById('sync_status_field');

    // Fonction de masquage de la section fournisseur
    window.toggleSupplierSection = function() {
        const content = document.getElementById('supplier_content');
        const icon = document.getElementById('toggle_supplier_icon');
        
        if (content.style.display === 'none') {
            content.style.display = 'flex';
            icon.innerText = '▼';
            localStorage.setItem('stock_supplier_expanded', 'true');
        } else {
            content.style.display = 'none';
            icon.innerText = '▲';
            localStorage.setItem('stock_supplier_expanded', 'false');
        }
    };

    // Restaurer l'état au chargement
    const savedState = localStorage.getItem('stock_supplier_expanded');
    if (savedState === 'false') {
        // Appliquer l'état fermé sans animation au chargement
        const content = document.getElementById('supplier_content');
        const icon = document.getElementById('toggle_supplier_icon');
        if (content && icon) {
            content.style.display = 'none';
            icon.innerText = '▲';
        }
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            // Désactiver le bouton et changer l'apparence
            syncBtn.disabled = true;
            syncBtn.style.backgroundColor = '#666';
            syncBtn.innerHTML = '⏳ ...';
            
            // Mettre à jour les statuts
            if (syncStatusField) syncStatusField.innerText = 'En cours...';
            
            // Appeler l'API de synchronisation
            fetch('api/sync_catalog.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Inline status
                    if (syncStatusField) {
                        syncStatusField.innerText = '✅ Terminé';
                        syncStatusField.style.color = 'green';
                        syncStatusField.style.fontWeight = 'bold';
                    }
                    
                    syncStatus.innerHTML = `
                        <span style="color: #4caf50; font-weight: bold; font-size: 0.9em;">
                            ✅ (+${data.imported}, 🔄 ${data.updated}, ❌ ${data.errors})
                        </span>
                    `;
                    
                    // Recharger la date de dernière synchronisation
                    loadLastSyncDate();
                } else {
                    syncStatus.innerHTML = '<span style="color: #f44336;">❌ Erreur</span>';
                    if (syncStatusField) {
                        syncStatusField.innerText = 'Erreur';
                        syncStatusField.style.color = 'red';
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                syncStatus.innerHTML = '<span style="color: #f44336;">❌ Erreur réseau</span>';
                if (syncStatusField) syncStatusField.innerText = 'Erreur connexion';
            })
            .finally(() => {
                // Réactiver le bouton
                syncBtn.disabled = false;
                syncBtn.style.backgroundColor = '#9c27b0';
                syncBtn.innerHTML = 'Actualiser';
                
                // Reset status field style after delay
                setTimeout(() => {
                    if (syncStatusField) {
                        syncStatusField.style.color = '#555';
                        syncStatusField.style.fontWeight = 'normal';
                        syncStatusField.innerText = 'Prêt';
                        syncStatus.innerHTML = '';
                    }
                }, 5000);
            });
        });
    }
});
</script>