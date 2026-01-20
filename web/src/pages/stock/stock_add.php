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

    if (!empty($errors)) {
        $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
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
            if (empty($sns)) {
                $sns[] = ''; // Default to empty string instead of null to avoid SQL constraint error
            }

            // Récup. date commande
            $date_commande = $_POST['date_commande'] ?? null;
            if (empty($date_commande)) $date_commande = null;

            $success_count = 0;
            $error_count = 0;

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
                $message = '<div class="alert alert-success">' . $msg . '</div>';
                // Réinitialiser les variables pour vider le formulaire
                $ref_acadia = $ean_code = $designation = $prix_achat_ht = $prix_vente_ttc = $fournisseur = $numero_commande = $date_commande = '';
            } else {
                $message = '<div class="alert alert-danger">Erreur lors de l\'ajout du/des produit(s).</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    $message = '<div class="alert alert-danger">Erreur de configuration : la connexion à la base de données n\'est pas disponible.</div>';
}

// Récupération des paramètres GET pour pré-remplissage
$preFillSupplier = trim($_GET['supplier'] ?? '');
$preFillOrder = trim($_GET['order'] ?? '');
?>

<div class="content-wrapper">
    <div class="toolbar-stock bg-card p-15 rounded shadow-sm mb-20 flex-between-center">
        <h1 class="text-color m-0 text-2xl">📦 Ajouter au Stock</h1>
        <a href="index.php?page=stock_list" class="btn btn-secondary">Retour à la liste</a>
    </div>

    <!-- Toolbar Compacte : Synchronisation -->
    <div class="card p-15 mb-20 flex-between-center flex-wrap gap-15 border-l-4 border-l-info">
        <div class="flex items-center gap-15 flex-wrap">
            <span class="font-bold text-info flex items-center gap-5">🔄 Base Produits</span>
            <span class="text-sm text-muted">Dernière synchro : <span id="last_sync_date" class="font-medium">Chargement...</span></span>
            <span id="sync_status_field" class="text-xs bg-input px-10 py-2 rounded border border-border text-color">Prêt</span>
        </div>
        <div class="flex items-center gap-10">
            <div id="sync_status" class="text-xs"></div>
            <button type="button" id="sync_catalog_btn" class="btn btn-sm btn-info">
                Actualiser
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <form action="index.php?page=stock_add" method="POST" id="product_form">    <!-- Grille Principale (Bascule en côte-à-côte dès 768px) -->
        <div class="grid grid-cols-1 md-grid-cols-12 gap-20 items-start">
            
            <!-- COLONNE GAUCHE (Fournisseur + Recherche) -->
            <div class="md-col-span-5 lg-col-span-4 flex flex-col gap-20">
                
                <!-- Section Fournisseur -->
                <div class="card p-15 border-l-4 border-l-primary shadow-sm">
                    <div class="flex-between-center cursor-pointer mb-10" onclick="toggleSupplierSection()">
                        <h4 class="m-0 text-primary font-bold text-sm">Fournisseur</h4>
                        <span id="toggle_supplier_icon" class="text-primary text-sm transition-transform">▼</span>
                    </div>
                    
                    <div id="supplier_content" class="flex flex-col gap-15 transition-all">
                        <div>
                            <label for="supplier_name" class="block mb-3 font-bold text-color text-xs">Nom :</label>
                            <input type="text" id="supplier_name" name="supplier_name_ui" list="fournisseurs_list" class="form-control w-full p-6 border rounded bg-input text-color text-sm" placeholder="Nom..." autocomplete="off" value="<?= htmlspecialchars($preFillSupplier) ?>">
                            <datalist id="fournisseurs_list"></datalist>
                        </div>
                        <div>
                            <label for="order_number" class="block mb-3 font-bold text-color text-sm">N° Cmd :</label>
                            <input type="text" id="order_number" name="order_number_ui" class="form-control w-full p-6 border rounded bg-input text-color text-sm" placeholder="N°..." value="<?= htmlspecialchars($preFillOrder) ?>">
                        </div>
                        <div>
                            <label for="order_date" class="block mb-3 font-bold text-color text-sm">Date :</label>
                            <input type="date" id="order_date" name="date_commande_ui" class="form-control w-full p-6 border rounded bg-input text-color text-sm">
                        </div>

                        <div>
                            <label class="block mb-3 font-bold text-info text-xs">📄 Factures / BL :</label>
                            <div id="documents_list" class="mb-5 text-xs"></div>
                            <div class="flex gap-5 items-center">
                                <input type="file" id="invoice_upload" accept=".pdf,image/*" capture="environment" class="hidden" multiple>
                                <button type="button" id="btn_upload_start" onclick="event.stopPropagation(); triggerUpload()" class="btn btn-xs btn-secondary opacity-60 cursor-not-allowed w-full" disabled>
                                    📷 Photo
                                </button>
                            </div>
                            <div id="upload_status" class="mt-5 text-[10px] text-center italic"></div>
                        </div>

                        <div class="flex gap-10 mt-5 pt-10 border-t border-border border-dashed">
                            <button type="button" id="validate_supplier" class="btn btn-primary btn-xs flex-1">Valider</button>
                            <button type="button" id="clear_supplier" class="btn btn-danger btn-xs">❌</button>
                        </div>
                        <div id="supplier_status" class="font-bold text-xs text-center"></div>

                        <!-- QR / Mobile UI compact -->
                        <div id="pwa_column" class="hidden flex-col items-center border-t border-dashed border-border pt-10 mt-5">
                             <div id="qrcode" class="mb-5"></div>
                             <small class="text-muted text-xs text-center">Mobile Sync</small>
                        </div>
                    </div>
                </div>

                <!-- Section Recherche -->
                <div class="card p-15 border-l-4 border-l-warning shadow-sm">
                    <h4 class="mt-0 mb-10 text-warning-dark font-bold text-sm">Recherche</h4>
                    <div class="mb-10">
                        <div class="flex gap-5 mb-5 text-[10px] bg-input p-3 rounded border border-border flex-wrap">
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="radio" name="search_scope" value="auto" checked> Auto
                            </label>
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="radio" name="search_scope" value="catalog"> Catalog
                            </label>
                            <label class="cursor-pointer flex items-center gap-2">
                                <input type="radio" name="search_scope" value="stock"> Stock
                            </label>
                        </div>
                        <input type="text" id="product_search" class="form-control w-full p-6 border rounded bg-input text-color text-sm shadow-inner" placeholder="EAN, Réf...">
                    </div>
                    <div id="search_results" class="hidden">
                        <div id="results_container" class="max-h-200 overflow-y-auto border border-border rounded bg-card shadow-sm text-xs"></div>
                    </div>
                    <div id="no_results" class="hidden text-success font-bold mt-5 p-5 bg-success-light rounded border border-success text-xs">
                        ✓ Produit unique
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE (Formulaire Produit) -->
            <div class="md-col-span-7 lg-col-span-8">
                <div id="form_card" class="card p-25 shadow-lg border-t-4 border-t-primary">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-25 mb-20 p-20 bg-light rounded-lg border border-border shadow-inner">
                        <div>
                            <label for="ref_acadia" class="block mb-8 font-bold text-color text-base">Référence Acadia :</label>
                            <input type="text" id="ref_acadia" name="ref_acadia" value="<?= htmlspecialchars($ref_acadia ?? '') ?>" class="form-control w-full p-12 border rounded bg-input text-color text-lg" placeholder="Ref Acadia">
                            <small class="text-muted text-xs mt-5 block">Référence interne du catalogue</small>
                        </div>
                        <div>
                            <label for="ean_code" class="block mb-8 font-bold text-color text-base">Code EAN * :</label>
                            <input type="text" id="ean_code" name="ean_code" value="<?= htmlspecialchars($ean_code ?? '') ?>" required class="form-control w-full p-12 border-2 border-primary rounded bg-input text-color text-2xl font-bold">
                            <small class="text-muted text-xs mt-5 block">Code-barres standard du fabricant</small>
                        </div>
                    </div>

                    <div class="mb-20">
                        <label for="designation" class="block mb-8 font-bold text-color text-base">Désignation du produit * :</label>
                        <textarea id="designation" name="designation" required class="form-control w-full p-12 border rounded bg-input text-color text-lg min-h-60 resize-y font-bold"><?= htmlspecialchars($designation ?? '') ?></textarea>
                    </div>

                    <div class="mb-25">
                        <label for="sn_list" class="block mb-8 font-bold text-color text-base">Numéros de Série (SN) :</label>
                        <textarea id="sn_list" name="sn_list" class="form-control w-full p-12 border rounded bg-input text-color font-mono text-base min-h-100" placeholder="Un numéro de série par ligne..."></textarea>
                        <small class="text-muted text-xs mt-5 block">Saisissez plusieurs SN pour créer plusieurs fiches produits identiques d'un coup.</small>
                    </div>

                    <!-- Section Prix compacte (Horizontal) -->
                    <div class="grid grid-cols-1 md-grid-cols-3 gap-15 mb-20 p-15 bg-card rounded-lg border-2 border-dashed border-border items-end">
                        <div style="flex: 1; min-width: 0;">
                            <label for="prix_achat_ht" class="block mb-5 font-bold text-color text-xs">Prix Achat HT *</label>
                            <div class="relative">
                                <input type="number" id="prix_achat_ht" name="prix_achat_ht" value="<?= htmlspecialchars($prix_achat_ht ?? '') ?>" step="0.01" min="0" required class="form-control w-full p-8 border rounded bg-input text-color font-bold text-lg pr-25">
                                <span class="absolute right-8 top-8 text-muted">€</span>
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <label for="prix_vente_ttc" class="block mb-5 font-bold text-color text-xs">Prix Vente TTC *</label>
                            <div class="relative">
                                <input type="number" id="prix_vente_ttc" name="prix_vente_ttc" value="<?= htmlspecialchars($prix_vente_ttc ?? '') ?>" step="0.01" min="0" required class="form-control w-full p-8 border-2 border-success rounded bg-input text-success font-bold text-xl pr-25">
                                <span class="absolute right-8 top-8 text-success font-bold">€</span>
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <label class="block mb-5 font-bold text-color text-xs">Suggéré (Marge)</label>
                            <div class="relative">
                                <input type="text" id="prix_suggere" readonly class="form-control w-full p-8 border rounded bg-input text-info font-bold text-lg opacity-80 pr-25" placeholder="...">
                                <span class="absolute right-8 top-8 text-info font-bold">€</span>
                            </div>
                        </div>
                    </div>
                    <div id="suggestion-details" class="text-sm text-muted mb-25 px-10 border-l-2 border-info italic"></div>

                    <!-- Champs cachés pour les données fournisseur validées -->
                    <input type="hidden" id="fournisseur" name="fournisseur" value="<?= htmlspecialchars($fournisseur ?? '') ?>">
                    <input type="hidden" id="numero_commande" name="numero_commande" value="<?= htmlspecialchars($numero_commande ?? '') ?>">
                    <input type="hidden" id="date_commande" name="date_commande" value="<?= htmlspecialchars($date_commande ?? '') ?>">

                    <div class="flex gap-15 mt-10 pt-25 border-t border-border">
                        <button type="submit" class="btn btn-primary btn-lg px-40 shadow-md">Ajouter au Stock</button>
                        <button type="reset" class="btn btn-secondary btn-lg opacity-50">Réinitialiser</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
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
            searchResults.classList.add('hidden');
            noResults.classList.add('hidden');
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
                        searchResults.classList.remove('hidden');
                        noResults.classList.add('hidden');
                    } else {
                        searchResults.classList.add('hidden');
                        noResults.classList.remove('hidden');
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
            let sourceColor = 'text-primary';
            
            if (source === 'catalog') {
                sourceIcon = '📋';
                sourceLabel = 'Catalogue Acadia';
                sourceColor = 'text-success';
            } else if (source === 'web') {
                sourceIcon = '🌐';
                sourceLabel = 'UPCItemDB (Web)';
                sourceColor = 'text-info-dark';
            }
            
            html += `
                <div class="p-10 border-b border-border hover:bg-hover cursor-pointer transition-colors"
                     onclick="selectProduct(${index})">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <strong class="${sourceColor} text-lg block mb-5">${sourceIcon} ${sourceLabel}</strong>
                            <div class="text-sm">
                                <strong>EAN:</strong> ${product.ean_code || 'N/A'}<br>
                                <strong>Désignation:</strong> ${product.designation || 'N/A'}<br>
                                <strong>Marque/Four:</strong> ${product.fournisseur || 'N/A'}
                            </div>
                        </div>
                        <div class="text-right min-w-120 text-sm">
                            ${source === 'stock' || source === 'catalog' ? `
                                <div><strong>Prix Achat HT:</strong> ${prixAchat}€</div>
                                <div><strong>Prix Vente TTC:</strong> ${prixVente}€</div>
                                <div class="text-xs text-muted mt-5">Ajouté le: ${dateAjout}</div>
                            ` : `<div class="text-xs text-muted">(Prix web indicatifs non récupérés)</div>`}
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
        if (prixVenteField) prixVenteField.value = ''; // On ne remplit pas le TTC, c'est l'utilisateur qui doit le saisir
        
        // Calculer le prix suggéré après avoir rempli le prix d'achat
        calculateSuggestedPrice();
        
        // Validation fournisseur automatique si nécessaire
        const supplierValidated = localStorage.getItem('stock_supplier_validated') === 'true';
        
        if (!supplierValidated) {
            const supplierName = product.fournisseur || '';
            const orderNumber = product.numero_commande || '';
            
            if (supplierName && orderNumber) {
                if (supplierNameInput) supplierNameInput.value = supplierName;
                if (orderNumberInput) orderNumberInput.value = orderNumber;
                
                localStorage.setItem('stock_supplier_name', supplierName);
                localStorage.setItem('stock_order_number', orderNumber);
                localStorage.setItem('stock_supplier_validated', 'true');
                
                if (fournisseurInput) fournisseurInput.value = supplierName;
                if (numeroCommandeInput) numeroCommandeInput.value = orderNumber;
                if (supplierStatus) supplierStatus.innerHTML = '<span class="text-success font-bold">✓ Fournisseur validé automatiquement</span>';
                if (formCard) formCard.classList.remove('hidden'); // Fix: use formCard instead of productForm
                
                checkUploadEnabled(); // Ensure upload is enabled
            }
        }
        
        // Masquer les résultats
        if (searchResults) searchResults.classList.add('hidden');
        if (noResults) noResults.classList.add('hidden');
        if (productSearchInput) productSearchInput.value = '';

        // Message de confirmation
        const searchSection = document.querySelector('.card.border-l-warning');
        if (searchSection) {
            const confirmationMsg = document.createElement('div');
            confirmationMsg.className = 'alert alert-success mt-10';
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
    const productForm = document.getElementById('product_form'); // Direct form
    const formCard = document.getElementById('form_card'); // Container card

    const fournisseurInput = document.getElementById('fournisseur');
    const numeroCommandeInput = document.getElementById('numero_commande');
    const dateCommandeInput = document.getElementById('date_commande');

    // --- GESTION DES DOCUMENTS ---
    const documentsList = document.getElementById('documents_list');
    const btnUploadStart = document.getElementById('btn_upload_start');
    const invoiceUpload = document.getElementById('invoice_upload');
    const uploadStatus = document.getElementById('upload_status');

    // Charger les données fournisseur du localStorage
    const savedSupplierName = localStorage.getItem('stock_supplier_name');
    const savedOrderNumber = localStorage.getItem('stock_order_number');
    const savedOrderDate = localStorage.getItem('stock_order_date');
    const savedSupplierValidated = localStorage.getItem('stock_supplier_validated');

    if (savedSupplierName) supplierNameInput.value = savedSupplierName;
    if (savedOrderNumber) orderNumberInput.value = savedOrderNumber;
    if (savedOrderDate) {
        orderDateInput.value = savedOrderDate;
        dateCommandeInput.value = savedOrderDate;
    } else {
        const today = new Date().toISOString().split('T')[0];
        orderDateInput.value = today;
        dateCommandeInput.value = today;
    }

    if (savedSupplierName && savedOrderNumber && savedSupplierValidated === 'true') {
        validateSupplier();
    } else {
        if (supplierNameInput.value.trim() !== '' && orderNumberInput.value.trim() !== '') {
             validateSupplier();
        } else {
             formCard.classList.add('hidden');
        }
    }

    function validateSupplier() {
        const name = supplierNameInput.value.trim();
        const number = orderNumberInput.value.trim();
        const msgDate = orderDateInput.value;

        if (name && number) {
            supplierStatus.innerHTML = '<span class="text-success font-bold">✓ Fournisseur validé</span>';
            fournisseurInput.value = name;
            numeroCommandeInput.value = number;
            dateCommandeInput.value = msgDate;

            formCard.classList.remove('hidden');

            localStorage.setItem('stock_supplier_name', name);
            localStorage.setItem('stock_order_number', number);
            localStorage.setItem('stock_order_date', msgDate);
            localStorage.setItem('stock_supplier_validated', 'true');
            
            checkUploadEnabled();

            // Generate QR Code
            const pwaColumn = document.getElementById('pwa_column');
            const qrDiv = document.getElementById('qrcode');
            if (pwaColumn && qrDiv) {
                pwaColumn.classList.remove('hidden');
                pwaColumn.classList.add('flex');
                qrDiv.innerHTML = '';
                
                const uploadUrl = window.location.origin + '/pwa/?' +
                                  'supplier=' + encodeURIComponent(name) + 
                                  '&order=' + encodeURIComponent(number);
                
                new QRCode(qrDiv, {
                    text: uploadUrl,
                    width: 200,
                    height: 200
                });
                
                const linkDiv = document.getElementById('qr_link');
                if (linkDiv) {
                    linkDiv.innerHTML = `<a href="${uploadUrl}" target="_blank" class="text-muted underline decoration-dotted">${uploadUrl}</a>`;
                }
            }
        } else {
            supplierStatus.innerHTML = '<span class="text-danger">⚠ Veuillez remplir tous les champs</span>';
            formCard.classList.add('hidden');
        }
    }

    validateSupplierBtn.addEventListener('click', validateSupplier);
    clearSupplierBtn.addEventListener('click', function() {
        supplierNameInput.value = '';
        orderNumberInput.value = '';
        fournisseurInput.value = '';
        numeroCommandeInput.value = '';
        supplierStatus.innerHTML = '';
        formCard.classList.add('hidden');
        
        const pwaColumn = document.getElementById('pwa_column');
        if (pwaColumn) {
            pwaColumn.classList.add('hidden');
            pwaColumn.classList.remove('flex');
        }

        if (documentsList) documentsList.innerHTML = '';

        const today = new Date().toISOString().split('T')[0];
        orderDateInput.value = today;
        dateCommandeInput.value = today;
        
        localStorage.removeItem('stock_supplier_name');
        localStorage.removeItem('stock_order_number');
        localStorage.removeItem('stock_supplier_validated');
        localStorage.removeItem('stock_order_date');

        checkUploadEnabled();
    });

    window.triggerUpload = function() {
        invoiceUpload.click();
    };

    function checkUploadEnabled() {
        const sup = supplierNameInput.value.trim();
        const ord = orderNumberInput.value.trim();
        
        if (sup && ord) {
            btnUploadStart.disabled = false;
            btnUploadStart.classList.remove('opacity-60', 'cursor-not-allowed');
            btnUploadStart.classList.add('cursor-pointer');
            btnUploadStart.title = "Cliquez pour ajouter un fichier";
            loadDocuments(sup, ord);
        } else {
            btnUploadStart.disabled = true;
            btnUploadStart.classList.add('opacity-60', 'cursor-not-allowed');
            btnUploadStart.classList.remove('cursor-pointer');
            btnUploadStart.title = "Remplissez Fournisseur et N° Commande d'abord";
            documentsList.innerHTML = '';
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
                        div.className = "flex justify-between items-center bg-light p-5 mb-2 rounded border border-border";
                        div.innerHTML = `
                            <span>📄 <a href="${doc.file_path}" target="_blank" class="text-dark no-underline hover:text-primary">${doc.original_name}</a></span>
                            <span class="text-danger cursor-pointer font-bold px-5" onclick="deleteDocument(${doc.id}, '${fournisseur}', '${numeroCommande}')">×</span>
                        `;
                        documentsList.appendChild(div);
                    });
                } else {
                    documentsList.innerHTML = '<em class="text-muted text-xs">Aucun document lié</em>';
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

                uploadStatus.innerHTML = '<span class="text-warning">Envoi...</span>';

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
                        uploadStatus.innerHTML = '<span class="text-success">✓ Ajouté</span>';
                        setTimeout(() => uploadStatus.innerHTML = '', 2000);
                        loadDocuments(fournisseur, numeroCommande);
                        invoiceUpload.value = ''; 
                    } else {
                        uploadStatus.innerHTML = `<span class="text-danger">Erreur: ${data.error}</span>`;
                    }
                })
                .catch(err => {
                    uploadStatus.innerHTML = '<span class="text-danger">Erreur réseau</span>';
                });
            }
        });
    }

    supplierNameInput.addEventListener('input', checkUploadEnabled);
    orderNumberInput.addEventListener('input', checkUploadEnabled);
    setTimeout(checkUploadEnabled, 500);

    // Validation en temps réel des prix
    const prixAchatInput = document.getElementById('prix_achat_ht');
    const prixVenteInput = document.getElementById('prix_vente_ttc');

    function validatePrice(input) {
        const value = parseFloat(input.value);
        if (input.value !== '' && (isNaN(value) || value < 0)) {
            input.classList.add('border-danger');
        } else {
            input.classList.remove('border-danger');
        }
    }

    function calculateMargin() {
        const prixAchat = parseFloat(prixAchatInput.value);
        const prixVente = parseFloat(prixVenteInput.value);
        
        if (!isNaN(prixAchat) && !isNaN(prixVente) && prixAchat > 0) {
            const prixVenteHT = prixVente / 1.2;
            const marge = ((prixVenteHT - prixAchat) / prixAchat * 100).toFixed(2);
            
            let marginInfo = document.getElementById('margin-info');
            if (!marginInfo) {
                marginInfo = document.createElement('small');
                marginInfo.id = 'margin-info';
                marginInfo.className = 'block mt-5 font-bold';
                prixVenteInput.parentNode.appendChild(marginInfo);
            }
            marginInfo.textContent = `Marge: ${marge}% (${prixVenteHT.toFixed(2)}€ HT - ${prixAchat.toFixed(2)}€ HT)`;
            
            if (marge >= 0) {
                marginInfo.classList.remove('text-danger');
                marginInfo.classList.add('text-success');
            } else {
                marginInfo.classList.remove('text-success');
                marginInfo.classList.add('text-danger');
            }
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
            const prixAvecTVA = prixAchat * 1.2;
            const prixSuggere = prixAvecTVA * 1.3;
            
            prixSuggereInput.value = prixSuggere.toFixed(2);
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

    // Validation du code EAN
    const eanInput = document.getElementById('ean_code');
    eanInput.addEventListener('input', function() {
        const value = this.value;
        this.value = value.replace(/[^0-9]/g, '');
        
        if (this.value.length > 0 && this.value.length !== 8 && this.value.length !== 13) {
            this.classList.add('border-warning');
        } else {
            this.classList.remove('border-warning');
        }
    });

    // Gestion de la synchronisation du catalogue
    const syncBtn = document.getElementById('sync_catalog_btn');
    const syncStatus = document.getElementById('sync_status');
    const syncStatusField = document.getElementById('sync_status_field');

    const savedState = localStorage.getItem('stock_supplier_expanded');
    if (savedState === 'false') {
        const content = document.getElementById('supplier_content');
        const icon = document.getElementById('toggle_supplier_icon');
        if (content && icon) {
            content.classList.add('hidden');
            content.style.display = 'none';
            icon.innerText = '▲';
        }
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            syncBtn.disabled = true;
            syncBtn.classList.add('bg-muted', 'cursor-not-allowed');
            syncBtn.innerHTML = '⏳ ...';
            
            if (syncStatusField) syncStatusField.innerText = 'En cours...';
            
            fetch('api/sync_catalog.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (syncStatusField) {
                        syncStatusField.innerText = '✅ Terminé';
                        syncStatusField.classList.add('text-success', 'font-bold');
                    }
                    
                    syncStatus.innerHTML = `
                        <span class="text-success font-bold">
                            ✅ (+${data.imported}, 🔄 ${data.updated}, ❌ ${data.errors})
                        </span>
                    `;
                    
                    loadLastSyncDate();
                } else {
                    syncStatus.innerHTML = '<span class="text-danger">❌ Erreur</span>';
                    if (syncStatusField) {
                        syncStatusField.innerText = 'Erreur';
                        syncStatusField.classList.add('text-danger');
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                syncStatus.innerHTML = '<span class="text-danger">❌ Erreur réseau</span>';
                if (syncStatusField) syncStatusField.innerText = 'Erreur connexion';
            })
            .finally(() => {
                syncBtn.disabled = false;
                syncBtn.classList.remove('bg-muted', 'cursor-not-allowed');
                syncBtn.innerHTML = 'Actualiser';
                
                setTimeout(() => {
                    if (syncStatusField) {
                        syncStatusField.classList.remove('text-success', 'text-danger', 'font-bold');
                        syncStatusField.innerText = 'Prêt';
                        syncStatus.innerHTML = '';
                    }
                }, 5000);
            });
        });
    }
});

function toggleSupplierSection() {
    const content = document.getElementById('supplier_content');
    const icon = document.getElementById('toggle_supplier_icon');
    
    if (!content) {
        console.error("Élément 'supplier_content' non trouvé");
        return;
    }

    // Toggle manuel via style inline pour passer outre tout conflit CSS
    if (content.style.display === 'none') {
        content.style.setProperty('display', 'flex', 'important');
        if (icon) icon.innerText = '▼';
        localStorage.setItem('stock_supplier_expanded', 'true');
    } else {
        content.style.setProperty('display', 'none', 'important');
        if (icon) icon.innerText = '▲';
        localStorage.setItem('stock_supplier_expanded', 'false');
    }
}
</script>