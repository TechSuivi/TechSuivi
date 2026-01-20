<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Vérifier si des paramètres sont présents
$supplier = $_GET['supplier'] ?? '';
$orderNumber = $_GET['order'] ?? '';

if (empty($supplier) || empty($orderNumber)) {
    echo "<div class='alert alert-danger'>Paramètres manquants : Fournisseur et Numéro de commande sont requis.</div>";
    return;
}

$message = '';

// --- TRAITEMENT DES MISES A JOUR ---

// 1. Mise à jour de la date de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_date'])) {
    $newDate = $_POST['new_date'] ?? null;
    $dateToSave = !empty($newDate) ? $newDate : null;

    try {
        $sql = "UPDATE Stock SET date_commande = :date_commande WHERE numero_commande = :numero_commande AND fournisseur = :fournisseur";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date_commande' => $dateToSave, ':numero_commande' => $orderNumber, ':fournisseur' => $supplier]);
        $message = "<div class='alert alert-success'>Date de commande mise à jour avec succès.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur base de données : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 2. Suppression d'un article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $idToDelete = $_POST['delete_item'];
    try {
        $stmt = $pdo->prepare("DELETE FROM Stock WHERE id = :id");
        $stmt->execute([':id' => $idToDelete]);
        $message = "<div class='alert alert-success'>Article supprimé avec succès.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur lors de la suppression : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 3. Mise à jour des articles (Bulk Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_items'])) {
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        $updatedCount = 0;
        $errorCount = 0;
        
        try {
            $pdo->beginTransaction();
            $sql = "UPDATE Stock SET 
                    ref_acadia = :ref, 
                    ean_code = :ean, 
                    designation = :designation, 
                    prix_achat_ht = :pa_ht, 
                    prix_vente_ttc = :pv_ttc,
                    SN = :sn
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);

            foreach ($_POST['items'] as $id => $item) {
                $params = [
                    ':ref' => $item['ref_acadia'] ?? '',
                    ':ean' => $item['ean_code'] ?? '',
                    ':designation' => $item['designation'] ?? '',
                    ':pa_ht' => !empty($item['prix_achat_ht']) ? str_replace(',', '.', $item['prix_achat_ht']) : 0,
                    ':pv_ttc' => !empty($item['prix_vente_ttc']) ? str_replace(',', '.', $item['prix_vente_ttc']) : 0,
                    ':sn' => $item['sn'] ?? '',
                    ':id' => $id
                ];
                
                if ($stmt->execute($params)) {
                    $updatedCount++;
                } else {
                    $errorCount++;
                }
            }
            $pdo->commit();
            
            if ($errorCount === 0) {
                $message .= "<div class='alert alert-success'>$updatedCount article(s) mis à jour avec succès.</div>";
            } else {
                $message .= "<div class='alert alert-warning'>$updatedCount mis à jour, $errorCount échecs.</div>";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour des articles : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}


// --- RECUPERATION DES DONNEES ---

// 1. Récupérer la date actuelle
$currentDate = '';
try {
    $stmt = $pdo->prepare("SELECT date_commande FROM Stock WHERE numero_commande = :numero_commande AND fournisseur = :fournisseur LIMIT 1");
    $stmt->execute([':numero_commande' => $orderNumber, ':fournisseur' => $supplier]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $currentDate = $result['date_commande'];
    }
} catch (PDOException $e) { /* Silent */ }

// 2. Récupérer les articles
$orderItems = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM Stock WHERE numero_commande = :numero_commande AND fournisseur = :fournisseur ORDER BY id ASC");
    $stmt->execute([':numero_commande' => $orderNumber, ':fournisseur' => $supplier]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>Impossible de charger les articles : " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="flex-between-center mb-20">
    <h1 class="text-color m-0 text-2xl">✏️ Modifier la commande</h1>
    <a href="index.php?page=orders_list" class="btn btn-secondary text-decoration-none">Retour à la liste</a>
</div>

<?= $message ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-20 mb-30">
    <!-- Colonne Gauche : Infos & Date -->
    <div class="card p-20 shadow-sm border border-border rounded">
        <h3 class="mt-0 text-primary">Informations Générales</h3>
        
        <form method="POST" action="index.php?page=orders_edit&supplier=<?= urlencode($supplier) ?>&order=<?= urlencode($orderNumber) ?>">
            <div class="mb-15">
                <label class="block font-bold mb-5 text-color">Fournisseur :</label>
                <input type="text" value="<?= htmlspecialchars($supplier) ?>" readonly class="form-control w-full p-10 bg-input opacity-70 border-border rounded text-color">
            </div>
            
            <div class="mb-15">
                <label class="block font-bold mb-5 text-color">Numéro de commande :</label>
                <input type="text" value="<?= htmlspecialchars($orderNumber) ?>" readonly class="form-control w-full p-10 bg-input opacity-70 border-border rounded text-color">
            </div>
            
            <div class="mb-20">
                <label for="new_date" class="block font-bold mb-5 text-color">Date de commande :</label>
                <input type="date" id="new_date" name="new_date" value="<?= htmlspecialchars($currentDate) ?>" class="form-control w-full p-10 border rounded bg-input text-color">
                <small class="text-muted block mt-5">Modifier cette date mettra à jour tous les articles de cette commande.</small>
            </div>
            
            <button type="submit" name="update_date" class="btn btn-primary font-bold">Enregistrer la date</button>
        </form>
    </div>

    <!-- Colonne Droite : Documents -->
    <div class="card p-20 shadow-sm border border-border rounded">
        <h3 class="mt-0 text-primary">📄 Documents (Factures / BL)</h3>
        
        <div id="documents_list" class="mb-20">
            <!-- Chargé via JS -->
            <div class="text-center text-muted">Chargement des documents...</div>
        </div>

        <div class="border-t border-border pt-15">
             <h4 class="m-0 mb-10 text-base font-bold text-color">Ajouter un document</h4>
             <div class="flex gap-10 items-center">
                <input type="file" id="invoice_upload" accept=".pdf,image/*" class="hidden">
                <button type="button" onclick="document.getElementById('invoice_upload').click()" class="btn btn-secondary btn-sm">Choisir un fichier</button>
                <span id="upload_status" class="ml-10"></span>
             </div>
        </div>
    </div>
</div>

<!-- NOUVELLE SECTION : LISTE DES ARTICLES EDITABLES -->
<div class="card p-0 overflow-hidden shadow-sm border border-border rounded">
    <div class="p-20 bg-light border-b border-border flex-between-center">
        <h3 class="m-0 text-primary">📦 Articles de la commande (<?= count($orderItems) ?>)</h3>
        <p class="m-0 text-sm text-muted">Modifiez directement les valeurs ci-dessous puis cliquez sur "Enregistrer les modifications".</p>
    </div>
    
    <form method="POST" action="index.php?page=orders_edit&supplier=<?= urlencode($supplier) ?>&order=<?= urlencode($orderNumber) ?>">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-light">
                    <tr>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted w-180">Référence</th>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted w-150">EAN</th>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted w-250">SN</th>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted">Désignation</th>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted w-100 text-right">Achat HT</th>
                        <th class="p-10 border-b border-border text-sm font-bold text-muted w-100 text-right">Vente TTC</th>
                        <th class="p-10 border-b border-border w-50"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr class="hover:bg-hover transition-colors">
                            <!-- Ref -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="text" name="items[<?= $item['id'] ?>][ref_acadia]" value="<?= htmlspecialchars($item['ref_acadia']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-mono">
                            </td>
                            <!-- EAN -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="text" name="items[<?= $item['id'] ?>][ean_code]" value="<?= htmlspecialchars($item['ean_code']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-mono" placeholder="EAN">
                            </td>
                            <!-- SN -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="text" name="items[<?= $item['id'] ?>][sn]" value="<?= htmlspecialchars($item['SN']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-mono text-warning placeholder-opacity-50" placeholder="SN">
                            </td>
                            <!-- Designation -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="text" name="items[<?= $item['id'] ?>][designation]" value="<?= htmlspecialchars($item['designation']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-medium">
                            </td>
                            <!-- Prix Achat HT -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="number" step="0.01" name="items[<?= $item['id'] ?>][prix_achat_ht]" value="<?= htmlspecialchars($item['prix_achat_ht']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-mono text-right">
                            </td>
                            <!-- Prix Vente TTC -->
                            <td class="p-5 border-b border-border align-top">
                                <input type="number" step="0.01" name="items[<?= $item['id'] ?>][prix_vente_ttc]" value="<?= htmlspecialchars($item['prix_vente_ttc']) ?>" class="form-control w-full p-5 text-sm border border-border rounded bg-input text-color font-mono text-right font-bold text-success">
                            </td>
                            <!-- Delete Button -->
                            <td class="p-5 border-b border-border align-top text-center">
                                <button type="submit" name="delete_item" value="<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger border-0 p-5" title="Supprimer cet article" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article de la commande ?');">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-20 bg-light border-t border-border text-right sticky bottom-0 z-10">
            <button type="submit" name="update_items" class="btn btn-success font-bold text-lg px-20 py-10 shadow-md">
                💾 Enregistrer les modifications
            </button>
        </div>
    </form>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Existing JS for documents...
    const supplier = "<?= addslashes($supplier) ?>";
    const orderNumber = "<?= addslashes($orderNumber) ?>";
    const documentsList = document.getElementById('documents_list');
    const invoiceUpload = document.getElementById('invoice_upload');
    const uploadStatus = document.getElementById('upload_status');

    function loadDocuments() {
        fetch(`api/get_order_documents.php?fournisseur=${encodeURIComponent(supplier)}&numero_commande=${encodeURIComponent(orderNumber)}`)
            .then(res => res.json())
            .then(docs => {
                documentsList.innerHTML = '';
                if (docs && docs.length > 0) {
                    docs.forEach(doc => {
                        const div = document.createElement('div');
                        div.className = "flex justify-between items-center bg-light p-10 mb-5 border border-border rounded";
                        div.innerHTML = `
                            <span>📄 <a href="${doc.file_path}" target="_blank" class="no-underline text-dark font-medium hover:text-primary">${doc.original_name}</a></span>
                            <button onclick="deleteDocument(${doc.id})" class="bg-transparent border-none text-danger cursor-pointer text-lg hover:text-danger-dark" title="Supprimer">&times;</button>
                        `;
                        documentsList.appendChild(div);
                    });
                } else {
                    documentsList.innerHTML = '<em class="text-muted">Aucun document lié à cette commande.</em>';
                }
            })
            .catch(err => {
                documentsList.innerHTML = '<span class="text-danger">Erreur de chargement des documents.</span>';
                console.error(err);
            });
    }

    window.deleteDocument = function(id) {
        if (!confirm('Voulez-vous vraiment supprimer ce document ?')) return;
        
        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_order_document.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadDocuments();
            } else {
                alert('Erreur: ' + data.error);
            }
        });
    };

    if (invoiceUpload) {
        invoiceUpload.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                uploadStatus.innerHTML = '<span class="text-warning">Envoi...</span>';
                
                const file = this.files[0];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('fournisseur', supplier);
                formData.append('numero_commande', orderNumber);

                fetch('api/upload_stock_document.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        uploadStatus.innerHTML = '<span class="text-success">✓ Ajouté</span>';
                        setTimeout(() => uploadStatus.innerHTML = '', 2000);
                        loadDocuments();
                        this.value = ''; // Reset file input
                    } else {
                        uploadStatus.innerHTML = '<span class="text-danger">Erreur: ' + data.error + '</span>';
                    }
                })
                .catch(err => {
                    uploadStatus.innerHTML = '<span class="text-danger">Erreur réseau</span>';
                    console.error(err);
                });
            }
        });
    }

    // Chargement initial
    loadDocuments();
});
</script>
