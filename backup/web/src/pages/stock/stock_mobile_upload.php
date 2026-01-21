<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$fournisseur = $_GET['supplier'] ?? '';
$numero_commande = $_GET['order'] ?? '';
?>
<div style="padding: 20px; max-width: 600px; margin: 0 auto; text-align: center; font-family: sans-serif;">
    <h2 style="color: #2196f3;">📷 Ajout Photo Rapide</h2>
    
    <?php if (empty($fournisseur) || empty($numero_commande)): ?>
        <div style="color: red; padding: 20px; background: #ffebee; border-radius: 8px;">
            ⚠️ Informations manquantes (Fournisseur ou N° Commande).<br>
            Veuillez rescanner le QR Code.
        </div>
    <?php else: ?>
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
            <div style="margin-bottom: 5px;"><strong>Fournisseur:</strong> <?= htmlspecialchars($fournisseur) ?></div>
            <div><strong>N° Commande:</strong> <?= htmlspecialchars($numero_commande) ?></div>
        </div>

        <div style="margin: 30px 0;">
            <input type="file" id="camera_input" accept="image/*" capture="environment" style="display: none;">
            <button onclick="document.getElementById('camera_input').click()" style="
                background-color: #2196f3; 
                color: white; 
                border: none; 
                padding: 20px 30px; 
                border-radius: 50px; 
                font-size: 1.2em; 
                font-weight: bold;
                box-shadow: 0 4px 10px rgba(33, 150, 243, 0.4);
                width: 100%;
                cursor: pointer;
            ">
                📸 PRENDRE UNE PHOTO
            </button>
        </div>

        <div id="preview_container" style="display: none; margin-bottom: 20px;">
            <img id="preview_img" style="max-width: 100%; border-radius: 8px; border: 2px solid #ccc;">
        </div>

        <div id="status_msg" style="margin-top: 20px; font-weight: bold;"></div>
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
            previewDiv.style.display = 'block';
        }
        reader.readAsDataURL(file);

        // Upload
        statusDiv.innerHTML = '<span style="color: orange;">⏳ Envoi en cours...</span>';
        
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
                statusDiv.innerHTML = '<span style="color: green; font-size: 1.2em;">✅ Envoyé avec succès !</span>';
                // Reset input
                document.getElementById('camera_input').value = '';
            } else {
                statusDiv.innerHTML = '<span style="color: red;">❌ Erreur: ' + data.error + '</span>';
            }
        })
        .catch(err => {
            statusDiv.innerHTML = '<span style="color: red;">❌ Erreur réseau</span>';
            console.error(err);
        });
    }
});
</script>
