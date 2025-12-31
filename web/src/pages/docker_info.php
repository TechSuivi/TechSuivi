<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';
?>

<div class="container">
    <h2>🐳 Conteneurs Docker</h2>

    <!-- Zone de notification -->
    <div id="docker-alert-area"></div>

    <!-- Container pour le contenu chargé en AJAX -->
    <div id="docker-content" class="info-section" style="min-height: 200px; display: flex; align-items: center; justify-content: center;">
        <!-- Loading Spinner -->
        <div class="loading-state">
            <div class="spinner"></div>
            <p>Chargement des conteneurs...</p>
        </div>
    </div>
</div>

<!-- Modal Logs -->
<div id="logsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeLogsModal()">&times;</span>
        <h3 id="logsTitle">Logs du conteneur</h3>
        <pre id="logsContent" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; overflow: auto; max-height: 500px; border-radius: 4px; font-family: monospace;">Chargement...</pre>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDockerInfo();
});

function loadDockerInfo() {
    const container = document.getElementById('docker-content');
    // Si déjà chargé ou en cours, on peut remettre le spinner si souhaité, 
    // mais ici on le laisse gérer par l'initialisation HTML
    
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
    const color = type === 'success' ? '#155724' : (type === 'danger' ? '#721c24' : '#0c5460');
    const bg = type === 'success' ? '#d4edda' : (type === 'danger' ? '#f8d7da' : '#d1ecf1');
    const border = type === 'success' ? '#c3e6cb' : (type === 'danger' ? '#f5c6cb' : '#bee5eb');
    
    // Style inline pour compatibilité simple
    const alertHtml = `
        <div class="alert alert-${type}" style="margin-bottom: 20px; padding: 15px; border-radius: 4px; color: ${color}; background-color: ${bg}; border: 1px solid ${border};">
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
    document.getElementById('logsModal').style.display = 'block';
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
    document.getElementById('logsModal').style.display = 'none';
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

<style>
/* Spinner */
.loading-state {
    text-align: center;
    color: #666;
}
.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--accent-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px auto;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.info-section {
    margin-bottom: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
}

.info-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: var(--accent-color);
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.info-card {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.info-card h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--accent-color);
    font-size: 16px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.info-table td:first-child {
    width: 40%;
    padding-right: 15px;
}

/* Actions */
.btn-action {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 5px 10px;
    cursor: pointer;
    transition: all 0.2s;
    margin-left: 2px;
}
.btn-action:hover {
    background: #e2e6ea;
    border-color: #dae0e5;
}
.btn-stop:hover { color: #dc3545; border-color: #dc3545; }
.btn-start:hover { color: #28a745; border-color: #28a745; }
.btn-logs:hover { color: #007bff; border-color: #007bff; }
.btn-restart:hover { transform: rotate(180deg); }

/* Modal Styles */
.modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4); 
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto; 
    padding: 20px;
    border: 1px solid #888;
    width: 80%; 
    max-width: 900px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.close-modal {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover { color: black; }

/* Styles pour la ligne du serveur actuel */
tr.is-self {
    background-color: #f8f9fa;
}
.badge-self {
    font-size: 0.8em;
    background: #e2e3e5;
    padding: 2px 5px;
    border-radius: 4px;
    margin-left: 5px;
    color: #495057;
}

/* Mode sombre */
body.dark .info-section {
    background-color: #2c2c2c;
    border-color: #444;
}
body.dark .info-card {
    background-color: #333;
    border-color: #555;
    color: #e9ecef;
}
body.dark .info-section h3,
body.dark .info-card h4 {
    color: #61afef;
    border-bottom-color: #61afef;
}
body.dark .info-table th {
    color: #ced4da;
    border-bottom-color: #555;
}
body.dark .info-table td {
    border-bottom-color: #444;
    color: #e9ecef;
}
body.dark strong { color: #fff; }
body.dark .btn-action {
    background-color: #444;
    border-color: #666;
    color: #e9ecef;
}
body.dark .btn-action:hover {
    background-color: #555;
    border-color: #888;
}
body.dark .text-muted { color: #adb5bd !important; }
body.dark .alert-info {
    background-color: #1d3846; /* Darker blue background */
    color: #b8daff;
    border-color: #204058;
}
body.dark .loading-state { color: #ccc; }
body.dark tr.is-self { background-color: #444c54; }
body.dark .badge-self { background-color: #5a6268; color: #e9ecef; }
body.dark .modal-content {
    background-color: #2c2c2c;
    border-color: #444;
    color: #e9ecef;
}

/* Specific colors for dark mode status */
body.dark span[style*="color: #28a745"] { color: #5dd879 !important; } /* Brighter green */
body.dark span[style*="color: #dc3545"] { color: #ff6b6b !important; } /* Brighter red */
</style>
