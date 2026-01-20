<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$fournisseur = $_GET['supplier'] ?? '';
$numero_commande = $_GET['order'] ?? '';
?>
<div class="container container-center max-w-600 p-20 text-center font-sans">
    <h2 class="text-primary mt-0">📷 Ajout Photo Rapide</h2>
    
    <?php if (empty($fournisseur) || empty($numero_commande)): ?>
        <div class="alert alert-danger">
            ⚠️ Informations manquantes (Fournisseur ou N° Commande).<br>
            Veuillez rescanner le QR Code.
        </div>
    <?php else: ?>
        <div class="card p-15 text-left mb-20 border-l-4 border-l-info">
            <div class="mb-5 text-color"><strong>Fournisseur:</strong> <?= htmlspecialchars($fournisseur) ?></div>
            <div class="text-color"><strong>N° Commande:</strong> <?= htmlspecialchars($numero_commande) ?></div>
        </div>

        <div class="my-30">
            <input type="file" id="camera_input" accept="image/*" capture="environment" class="hidden">
            <button onclick="document.getElementById('camera_input').click()" class="btn btn-primary w-full py-20 px-30 rounded-full text-xl shadow-lg font-bold cursor-pointer">
                📸 PRENDRE UNE PHOTO
            </button>
        </div>

        <div id="preview_container" class="hidden mb-20">
            <img id="preview_img" class="max-w-full rounded border-2 border-border shadow-sm">
        </div>

        <div id="status_msg" class="mt-20 font-bold"></div>
    <?php endif; ?>
</div>

<script>
document.getElementById('camera_input').addEventListener('change', function(e) {
    if (this.files && this.files.length > 0) {
        const file = this.files[0];
        const supplier = "<?= addslashes($fournisseur) ?>";
        const order = "<?= addslashes($numero_commande) ?>";
        const statusDiv = document.getElementById('status_msg');
        const previewDiv = document.getElementById('preview_container');
        const previewImg = document.getElementById('preview_img');

        // Preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewDiv.classList.remove('hidden');
        }
        reader.readAsDataURL(file);

        // Upload
        statusDiv.innerHTML = '<span class="text-warning">⏳ Envoi en cours...</span>';
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('fournisseur', supplier);
        formData.append('numero_commande', order);

        fetch('api/upload_stock_document.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span class="text-success text-lg">✅ Envoyé avec succès !</span>';
                // Reset input
                document.getElementById('camera_input').value = '';
            } else {
                statusDiv.innerHTML = '<span class="text-danger">❌ Erreur: ' + data.error + '</span>';
            }
        })
        .catch(err => {
            statusDiv.innerHTML = '<span class="text-danger">❌ Erreur réseau</span>';
            console.error(err);
        });
    }
});
</script>
