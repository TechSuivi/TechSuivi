<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';
?>

<div class="container container-center max-w-1200">
    <div class="page-header">
        <h1 class="text-dark">🐳 Conteneurs Docker</h1>
    </div>

    <!-- Zone de notification -->
    <div id="docker-alert-area" class="mb-20"></div>

    <!-- Container pour le contenu chargé en AJAX -->
    <div id="docker-content" class="card min-h-200 flex items-center justify-center bg-white border border-border shadow-sm">
        <!-- Loading Spinner -->
        <div class="text-center text-muted">
            <div class="spinner w-40 h-40 border-4 border-light border-t-primary rounded-full animate-spin mx-auto mb-10"></div>
            <p>Chargement des conteneurs...</p>
        </div>
    </div>
</div>

<!-- Modal Logs -->
<div id="logsModal" class="modal fixed inset-0 z-50 hidden bg-black-opacity flex items-center justify-center">
    <div class="modal-content bg-white p-20 border w-4-5 max-w-900 rounded-lg shadow-lg relative mx-auto my-auto max-h-screen-90 flex flex-col">
        <div class="flex-between-center mb-10 shrink-0">
            <h3 id="logsTitle" class="m-0 text-primary">Logs du conteneur</h3>
            <span class="close-modal text-2xl font-bold cursor-pointer hover:text-dark text-muted" onclick="closeLogsModal()">&times;</span>
        </div>
        <pre id="logsContent" class="bg-dark text-light p-15 overflow-auto flex-1 rounded font-mono m-0 text-xs">Chargement...</pre>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDockerInfo();
});

function loadDockerInfo() {
    const container = document.getElementById('docker-content');
    
    fetch('ajax/docker_actions.php?action=render_info')
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                container.style.display = 'block'; // Reset flex centering
                container.innerHTML = data.html;
            } else if (data.error) {
                container.innerHTML = '<div class="alert alert-danger">Erreur: ' + data.error + '</div>';
            }
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Erreur de communication avec le serveur.</div>';
            console.error(err);
        });
}

function dockerAction(action, containerName, isSelf = false) {
    if (action === 'stop' && !confirm('Voulez-vous vraiment ARRÊTER le conteneur ' + containerName + ' ?')) return;
    if (action === 'restart') {
        let msg = 'Voulez-vous vraiment redémarrer le conteneur ' + containerName + ' ?';
        if (isSelf) msg = 'ATTENTION : Redémarrer ce conteneur coupera immédiatement la connexion. Êtes-vous sûr ?';
        if (!confirm(msg)) return;
    }

    // Show loading feedback (simple toast or alert place)
    showAlert('info', 'Action ' + action + ' en cours...');

    const formData = new FormData();
    formData.append('action', action);
    formData.append('container_name', containerName);

    fetch('ajax/docker_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            // Reload info to update status
            // Petit délai pour laisser Docker agir
            setTimeout(loadDockerInfo, 1000);
        } else {
            showAlert('danger', data.message || 'Erreur inconnue');
        }
    })
    .catch(err => {
        showAlert('danger', 'Erreur réseau : ' + err);
    });
}

function showAlert(type, message) {
    const area = document.getElementById('docker-alert-area');
    let alertClass = 'alert-info';
    if (type === 'success') alertClass = 'alert-success';
    if (type === 'danger') alertClass = 'alert-danger';
    
    // Style inline pour compatibilité simple
    const alertHtml = `
        <div class="alert ${alertClass}">
            ${message}
        </div>
    `;
    area.innerHTML = alertHtml;
    
    // Auto clear after 5s for success
    if (type === 'success') {
        setTimeout(() => { area.innerHTML = ''; }, 5000);
    }
}

function showDockerLogs(containerName) {
    // Force display flex directly via style or class if not managed by CSS completely yet for modal-open
    const modal = document.getElementById('logsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    document.getElementById('logsTitle').innerText = 'Logs : ' + containerName;
    document.getElementById('logsContent').innerText = 'Chargement des logs...';
    
    fetch('ajax/docker_actions.php?action=logs&container=' + encodeURIComponent(containerName))
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                document.getElementById('logsContent').innerText = 'Erreur: ' + data.error;
            } else {
                document.getElementById('logsContent').innerText = data.logs;
                const pre = document.getElementById('logsContent');
                pre.scrollTop = pre.scrollHeight;
            }
        })
        .catch(err => {
            document.getElementById('logsContent').innerText = 'Erreur de communication : ' + err;
        });
}

function closeLogsModal() {
    const modal = document.getElementById('logsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

window.onclick = function(event) {
    if (event.target == document.getElementById('logsModal')) {
        closeLogsModal();
    }
}

function uploadRustdeskKeys(input) {
    if (input.files.length === 0) return;

    const formData = new FormData();
    // On attend id_ed25519 et id_ed25519.pub
    for (let i = 0; i < input.files.length; i++) {
        formData.append('key_files[]', input.files[i]);
    }
    formData.append('action', 'upload_keys');

    showAlert('info', 'Envoi des clés en cours...');

    fetch('ajax/rustdesk_keys.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            input.value = ''; // Reset input
        } else {
            let detail = data.details ? ' (' + data.details.join(', ') + ')' : '';
            showAlert('danger', data.message + detail);
        }
    })
    .catch(err => {
        showAlert('danger', 'Erreur réseau : ' + err);
    });
}
</script>
