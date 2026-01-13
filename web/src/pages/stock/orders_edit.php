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

// Traitement du formulaire de modification de date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_date'])) {
    $newDate = $_POST['new_date'] ?? null;
    
    // Si la date est vide, on la met à NULL dans la base de données
    $dateToSave = !empty($newDate) ? $newDate : null;

    try {
        $sql = "UPDATE Stock SET date_commande = :date_commande WHERE numero_commande = :numero_commande AND fournisseur = :fournisseur";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':date_commande', $dateToSave);
        $stmt->bindParam(':numero_commande', $orderNumber);
        $stmt->bindParam(':fournisseur', $supplier);
        
        if ($stmt->execute()) {
             $message = "<div class='alert alert-success'>Date de commande mise à jour avec succès pour " . $stmt->rowCount() . " article(s).</div>";
        } else {
             $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour de la date.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur base de données : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Récupérer la date actuelle de la commande (depuis le premier article trouvé)
$currentDate = '';
try {
    $stmt = $pdo->prepare("SELECT date_commande FROM Stock WHERE numero_commande = :numero_commande AND fournisseur = :fournisseur LIMIT 1");
    $stmt->execute([':numero_commande' => $orderNumber, ':fournisseur' => $supplier]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $currentDate = $result['date_commande'];
    }
} catch (PDOException $e) {
    // Silent error or log
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 style="color: #343a40; font-size: 24px; margin: 0;">✏️ Modifier la commande</h1>
    <a href="index.php?page=orders_list" style="padding: 10px 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 6px;">Retour à la liste</a>
</div>

<?= $message ?>

<div style="display: flex; gap: 20px;">
    <!-- Colonne Gauche : Infos & Date -->
    <div class="info-section" style="flex: 1; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; color: #0d6efd;">Informations Générales</h3>
        
        <form method="POST" action="index.php?page=orders_edit&supplier=<?= urlencode($supplier) ?>&order=<?= urlencode($orderNumber) ?>">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Fournisseur :</label>
                <input type="text" value="<?= htmlspecialchars($supplier) ?>" readonly style="width: 100%; padding: 10px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Numéro de commande :</label>
                <input type="text" value="<?= htmlspecialchars($orderNumber) ?>" readonly style="width: 100%; padding: 10px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="new_date" style="display: block; font-weight: bold; margin-bottom: 5px;">Date de commande :</label>
                <input type="date" id="new_date" name="new_date" value="<?= htmlspecialchars($currentDate) ?>" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;">
                <small style="color: #666;">Modifier cette date mettra à jour tous les articles de cette commande.</small>
            </div>
            
            <button type="submit" name="update_date" style="padding: 10px 20px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Enregistrer la date</button>
        </form>
    </div>

    <!-- Colonne Droite : Documents -->
    <div class="info-section" style="flex: 1; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; color: #0d6efd;">📄 Documents (Factures / BL)</h3>
        
        <div id="documents_list" style="margin-bottom: 20px;">
            <!-- Cahrgé via JS -->
            <div style="text-align: center; color: #666;">Chargement des documents...</div>
        </div>

        <div style="border-top: 1px solid #eee; padding-top: 15px;">
             <h4 style="margin: 0 0 10px 0; font-size: 16px;">Ajouter un document</h4>
             <div style="display: flex; gap: 10px;">
                <input type="file" id="invoice_upload" accept=".pdf,image/*" style="display: none;">
                <button type="button" onclick="document.getElementById('invoice_upload').click()" style="padding: 8px 15px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 4px; cursor: pointer;">Choisir un fichier</button>
                <span id="upload_status" style="margin-left: 10px; align-self: center;"></span>
             </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
                        div.style.cssText = "display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 10px; margin-bottom: 5px; border: 1px solid #eee; border-radius: 4px;";
                        div.innerHTML = `
                            <span>📄 <a href="${doc.file_path}" target="_blank" style="text-decoration: none; color: #212529; font-weight: 500;">${doc.original_name}</a></span>
                            <button onclick="deleteDocument(${doc.id})" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2em;">&times;</button>
                        `;
                        documentsList.appendChild(div);
                    });
                } else {
                    documentsList.innerHTML = '<em style="color: #999;">Aucun document lié à cette commande.</em>';
                }
            })
            .catch(err => {
                documentsList.innerHTML = '<span style="color: red;">Erreur de chargement des documents.</span>';
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

    invoiceUpload.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            uploadStatus.innerHTML = '<span style="color: orange;">Envoi...</span>';
            
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
                    uploadStatus.innerHTML = '<span style="color: green;">✓ Ajouté</span>';
                    setTimeout(() => uploadStatus.innerHTML = '', 2000);
                    loadDocuments();
                    this.value = ''; // Reset file input
                } else {
                    uploadStatus.innerHTML = '<span style="color: red;">Erreur: ' + data.error + '</span>';
                }
            })
            .catch(err => {
                uploadStatus.innerHTML = '<span style="color: red;">Erreur réseau</span>';
                console.error(err);
            });
        }
    });

    // Chargement initial
    loadDocuments();
});
</script>
