<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<div class="page-header">
    <h1>🔐 Sauvegarde et Restauration Rustdesk</h1>
</div>

<div class="card bg-secondary">
    <p class="m-0 mb-15">Ici vous pouvez sauvegarder l'identité de votre serveur Rustdesk (Clés privée et publique) ou les restaurer après une réinstallation.</p>
    
    <div class="alert alert-info mb-20">
        <strong>Note :</strong> Après avoir restauré des clés, il est nécessaire de 
        <a href="index.php?page=docker_info" class="font-bold underline">redémarrer le conteneur Rustdesk</a> 
        pour qu'elles soient prises en compte.
    </div>

    <div class="card mt-20" style="max-width: 600px;">
        <h4 class="card-title mb-15">🔑 Gestion des Clés</h4>
        
        <!-- Password input hidden due to env issues, keeping hidden for JS compatibility -->
        <input type="hidden" id="rustdeskPassword" value="">

        <div class="flex gap-15 flex-wrap mt-15">
            <!-- Backup Button -->
            <button onclick="downloadRustdeskBackup()" class="btn btn-primary flex items-center gap-5">
                📥 Télécharger la sauvegarde (.zip)
            </button>

            <!-- Restore Button -->
            <button class="btn btn-warning flex items-center gap-5" onclick="document.getElementById('rustdeskKeyInputBackup').click()">
                📤 Restaurer depuis une sauvegarde
            </button>
            
            <!-- Hidden Input -->
            <input type="file" id="rustdeskKeyInputBackup" multiple style="display: none;" onchange="uploadRustdeskKeysBackup(this)">
        </div>
        
        <p class="text-muted text-sm mt-15">
            Pour restaurer, sélectionnez les fichiers <code>id_ed25519</code> et <code>id_ed25519.pub</code> (vous pouvez les extraire du zip).
        </p>
    </div>
    
    <div id="rustdesk-alert-area" class="mt-20" style="max-width: 600px;"></div>
</div>

<script>
function downloadRustdeskBackup() {
    const password = document.getElementById('rustdeskPassword').value;
    let url = 'ajax/rustdesk_keys.php?action=download_keys';
    if (password) {
        url += '&password=' + encodeURIComponent(password);
    }
    window.location.href = url;
}

function uploadRustdeskKeysBackup(input) {
    if (input.files.length === 0) return;

    const formData = new FormData();
    for (let i = 0; i < input.files.length; i++) {
        formData.append('key_files[]', input.files[i]);
    }
    formData.append('action', 'upload_keys');
    
    // Ajouter le mot de passe s'il est présent
    const password = document.getElementById('rustdeskPassword').value;
    if (password) {
        formData.append('password', password);
    }

    showRustdeskAlert('info', 'Envoi des clés en cours...');

    fetch('ajax/rustdesk_keys.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showRustdeskAlert('success', data.message);
            input.value = ''; // Reset
        } else {
            let detail = data.details ? ' (' + data.details.join(', ') + ')' : '';
            showRustdeskAlert('danger', data.message + detail);
        }
    })
    .catch(err => {
        showRustdeskAlert('danger', 'Erreur réseau : ' + err);
    });
}

function showRustdeskAlert(type, message) {
    const area = document.getElementById('rustdesk-alert-area');
    let alertClass = 'alert-info';
    if (type === 'success') alertClass = 'alert-success';
    if (type === 'danger') alertClass = 'alert-error';
    
    area.innerHTML = `
        <div class="alert ${alertClass}">
            ${message}
        </div>
    `;
    
    if (type === 'success') {
        setTimeout(() => { area.innerHTML = ''; }, 5000);
    }
}
</script>
