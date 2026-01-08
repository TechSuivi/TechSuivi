<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<div class="container">
    <h2>🔐 Sauvegarde et Restauration Rustdesk</h2>

    <div class="info-section">
        <p>Ici vous pouvez sauvegarder l'identité de votre serveur Rustdesk (Clés privée et publique) ou les restaurer après une réinstallation.</p>
        
        <div class="alert alert-info">
            <strong>Note :</strong> Après avoir restauré des clés, il est nécessaire de 
            <a href="index.php?page=docker_info" style="font-weight: bold; text-decoration: underline;">redémarrer le conteneur Rustdesk</a> 
            pour qu'elles soient prises en compte.
        </div>

        <div class="info-card" style="max-width: 600px;">
            <h4>🔑 Gestion des Clés</h4>
            
            <!-- Password input hidden due to env issues, keeping hidden for JS compatibility -->
            <input type="hidden" id="rustdeskPassword" value="">

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
                <!-- Backup Button -->
                <button onclick="downloadRustdeskBackup()" class="btn btn-primary" style="display: inline-flex; align-items: center;">
                    📥 Télécharger la sauvegarde (.zip)
                </button>

                <!-- Restore Button -->
                <button class="btn btn-warning" onclick="document.getElementById('rustdeskKeyInputBackup').click()" style="display: inline-flex; align-items: center;">
                    📤 Restaurer depuis une sauvegarde
                </button>
                
                <!-- Hidden Input -->
                <input type="file" id="rustdeskKeyInputBackup" multiple style="display: none;" onchange="uploadRustdeskKeysBackup(this)">
            </div>
            
            <p class="text-muted" style="margin-top: 15px; font-size: 0.9em;">
                Pour restaurer, sélectionnez les fichiers <code>id_ed25519</code> et <code>id_ed25519.pub</code> (vous pouvez les extraire du zip).
            </p>
        </div>
        
        <div id="rustdesk-alert-area" style="margin-top: 20px; max-width: 600px;"></div>
    </div>
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
    const color = type === 'success' ? '#155724' : (type === 'danger' ? '#721c24' : '#0c5460');
    const bg = type === 'success' ? '#d4edda' : (type === 'danger' ? '#f8d7da' : '#d1ecf1');
    const border = type === 'success' ? '#c3e6cb' : (type === 'danger' ? '#f5c6cb' : '#bee5eb');
    
    area.innerHTML = `
        <div class="alert alert-${type}" style="padding: 15px; border-radius: 4px; color: ${color}; background-color: ${bg}; border: 1px solid ${border};">
            ${message}
        </div>
    `;
    
    if (type === 'success') {
        setTimeout(() => { area.innerHTML = ''; }, 5000);
    }
}
</script>

<style>
.info-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}
.info-card {
    background: white;
    padding: 20px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    margin-top: 20px;
}
.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    color: white;
}
.btn-primary { background-color: var(--accent-color); }
.btn-primary:hover { opacity: 0.9; }
.btn-warning { background-color: #ffc107; color: #212529; }
.btn-warning:hover { background-color: #e0a800; }

body.dark .info-section { background-color: #2c2c2c; border-color: #444; color: #e9ecef; }
body.dark .info-card { background-color: #333; border-color: #555; }
body.dark .text-muted { color: #adb5bd !important; }
</style>
