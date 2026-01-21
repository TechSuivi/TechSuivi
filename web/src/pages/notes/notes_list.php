<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$notes = [];
$errorMessage = '';
$searchTerm = $_GET['search'] ?? '';

if (isset($pdo)) {
    try {
        // Récupérer les notes avec recherche
        $sql = "SELECT n.*, c.nom as client_nom, c.prenom as client_prenom 
                FROM notes_globales n 
                LEFT JOIN clients c ON n.id_client = c.ID";
        
        if (!empty($searchTerm)) {
            $sql .= " WHERE n.titre LIKE :search OR n.contenu LIKE :search OR c.nom LIKE :search OR c.prenom LIKE :search";
        }
        
        $sql .= " ORDER BY n.date_note DESC";
        
        $stmt = $pdo->prepare($sql);
        if (!empty($searchTerm)) {
            $searchParam = "%$searchTerm%";
            $stmt->bindParam(':search', $searchParam);
        }
        $stmt->execute();
        $notes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Erreur BDD : " . $e->getMessage();
    }
}
?>

<div class="list-page">
    <div class="page-header">
        <h1><span>📓</span> Notes Globales</h1>
        <button onclick="openAddNoteModal()" class="btn btn-success flex items-center gap-10">
            <span>➕</span> Nouvelle note
        </button>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <!-- Zone de recherche -->
    <div class="card bg-secondary border p-20 rounded-12 shadow-sm mb-25">
        <form method="GET" action="index.php" class="flex gap-15 items-end">
            <input type="hidden" name="page" value="notes_list">
            <div class="flex-1">
                <label class="block mb-8 font-bold text-muted">Rechercher</label>
                <input type="text" name="search" class="form-control w-full p-10 border rounded-8 bg-input text-dark" 
                       placeholder="Titre, contenu, client..." value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Rechercher</button>
            <?php if (!empty($searchTerm)): ?>
                <a href="index.php?page=notes_list" class="btn btn-secondary">Effacer</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($notes)): ?>
        <div class="card text-center p-40 border-dashed">
            <div class="text-4xl mb-20 opacity-50">📓</div>
            <h3 class="text-dark">Aucune note trouvée</h3>
            <p class="text-muted">Commencez par créer votre première note.</p>
        </div>
    <?php else: ?>
        <div class="card border p-0 overflow-hidden shadow-sm">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="text-left p-15 bg-light border-b">Date</th>
                        <th class="text-left p-15 bg-light border-b">Titre</th>
                        <th class="text-left p-15 bg-light border-b">Client lié</th>
                        <th class="text-left p-15 bg-light border-b">Contenu</th>
                        <th class="text-center p-15 bg-light border-b">Public</th>
                        <th class="text-right p-15 bg-light border-b">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $note): ?>
                        <tr class="hover:bg-hover transition-colors">
                            <td class="p-15 border-b text-sm whitespace-nowrap">
                                <?= date('d/m/Y', strtotime($note['date_note'])) ?>
                            </td>
                            <td class="p-15 border-b font-bold">
                                <?= htmlspecialchars($note['titre']) ?>
                            </td>
                            <td class="p-15 border-b text-sm">
                                <?php if ($note['id_client']): ?>
                                    👤 <a href="index.php?page=clients_view&id=<?= $note['id_client'] ?>" class="text-primary hover:underline">
                                        <?= htmlspecialchars($note['client_nom'] . ' ' . $note['client_prenom']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-15 border-b text-sm">
                                <div class="truncate max-w-300" title="<?= htmlspecialchars($note['contenu']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($note['contenu'], 0, 80, "...")) ?>
                                </div>
                                <?php if ($note['fichier_path']): ?>
                                    <div class="mt-5">
                                        <a href="<?= htmlspecialchars($note['fichier_path']) ?>" target="_blank" class="text-xs text-primary flex items-center gap-4">
                                            📎 Fichier joint
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-15 border-b text-center">
                                <?php if ($note['show_on_login']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-15 border-b text-right whitespace-nowrap">
                                <button onclick='openEditNoteModal(<?= json_encode($note) ?>)' class="btn btn-primary btn-sm" title="Modifier">✏️</button>
                                <button onclick="confirmDeleteNote(<?= $note['id'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Ajout/Edition Note -->
<div id="noteModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 800px; width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title" id="noteModalTitle">📓 Nouvelle Note</h3>
            <span class="modal-close" onclick="closeNoteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="noteAlerts"></div>
            <form id="noteForm" enctype="multipart/form-data">
                <input type="hidden" id="note_id" name="id">
                
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label class="form-label">Titre *</label>
                        <input type="text" id="note_titre" name="titre" class="form-control" required placeholder="Titre de la note...">
                    </div>
                    <div class="form-group flex-1">
                        <label class="form-label">Date</label>
                        <input type="datetime-local" id="note_date" name="date_note" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <div class="form-group client-search-container" style="position: relative;">
                    <label class="form-label">Client associé</label>
                    <div class="flex gap-10">
                        <div class="flex-grow relative" style="position: relative;">
                            <input type="text" id="client_search_note" class="form-control" placeholder="Rechercher un client..." autocomplete="off">
                            <input type="hidden" id="note_id_client" name="id_client">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contenu *</label>
                    <textarea id="note_contenu" name="contenu" class="form-control" rows="12" required placeholder="Votre note ici..."></textarea>
                </div>

                <div class="form-row items-center">
                    <div class="form-group flex-2">
                        <label class="form-label">Fichier joint</label>
                        <input type="file" name="fichier" class="form-control">
                        <div id="current_file_info" class="text-xs text-muted mt-5"></div>
                    </div>
                    <div class="form-group flex-1">
                        <div class="checkbox-group" style="margin-top: 25px;">
                            <input type="checkbox" id="note_show_on_login" name="show_on_login" value="1">
                            <label for="note_show_on_login" class="form-label">Afficher sur le login</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">Annuler</button>
            <button type="button" class="btn btn-success" id="noteSaveBtn" onclick="submitNoteForm()">💾 Enregistrer</button>
        </div>
    </div>
</div>

<script>
let awesompleteClient;

document.addEventListener('DOMContentLoaded', function() {
    initClientSearchNote();
});

function initClientSearchNote() {
    const input = document.getElementById('client_search_note');
    const hiddenInput = document.getElementById('note_id_client');
    
    awesompleteClient = new Awesomplete(input, {
        minChars: 2,
        maxItems: 15,
        autoFirst: true,
        filter: function() { return true; },
        item: function(text, input) {
            // Awesomplete passe l'objet complet dans 'text' quand on utilise un tableau d'objets
            const itemLabel = text.label || text; 
            const li = document.createElement("li");
            li.innerHTML = `<div style="padding: 5px 10px;">
                <div style="font-weight: 600;">${itemLabel.split(' - ')[0]}</div>
                <div style="font-size: 0.85em; color: var(--text-muted);">${itemLabel.split(' - ').slice(1).join(' - ')}</div>
            </div>`;
            return li;
        }
    });

    let lastSearchResults = [];

    let debounceTimer;
    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value;
        if (query.length < 2) return;
        
        debounceTimer = setTimeout(() => {
            console.log("Searching for:", query);
            fetch(`api/search_clients.php?term=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    console.log("Search results:", data);
                    lastSearchResults = data; // Stockage global des résultats
                    
                    awesompleteClient.list = data.map(item => ({
                        label: item.label,
                        value: item.value,
                        id: item.id,
                        original: item
                    }));
                    awesompleteClient.evaluate();
                })
                .catch(err => console.error("Search error:", err));
        }, 300);
    });

    input.addEventListener('awesomplete-selectcomplete', function(e) {

        // L'objet sélectionné est disponible dans e.text
        console.log("Selection brute:", e.text);
        
        // On cherche l'objet correspondant dans nos résultats stockés
        const match = lastSearchResults.find(item => 
            item.label === e.text.label || item.value === e.text.value
        );
        
        if (match) {
            console.log("Client trouvé dans users:", match);
            hiddenInput.value = match.id;
            input.value = match.value;
        } else if (e.text && e.text.id) {
             // Fallback
             hiddenInput.value = e.text.id;
             input.value = e.text.value || e.text;
        } else {
             console.error("Impossible de retrouver l'ID pour ce client.");
        }
    });

    // On vide l'ID si le champ texte est vidé manuellement
    input.addEventListener('input', function() {
        if (!this.value) {
             hiddenInput.value = '';
        }
    });
}

function openAddNoteModal() {
    document.getElementById('noteModalTitle').textContent = '📓 Nouvelle Note';
    document.getElementById('noteForm').reset();
    document.getElementById('note_id').value = '';
    document.getElementById('note_id_client').value = '';
    document.getElementById('client_search_note').value = '';
    document.getElementById('note_date').value = new Date().toLocaleString('sv-SE').slice(0, 16).replace(' ', 'T');
    document.getElementById('current_file_info').innerHTML = '';
    document.getElementById('noteAlerts').innerHTML = '';
    document.getElementById('noteModal').style.display = 'flex';
    document.getElementById('noteSaveBtn').disabled = false;
}

function openEditNoteModal(note) {
    document.getElementById('noteModalTitle').textContent = '✏️ Modifier la Note';
    document.getElementById('note_id').value = note.id;
    document.getElementById('note_titre').value = note.titre;
    document.getElementById('note_date').value = note.date_note.replace(' ', 'T').slice(0, 16);
    document.getElementById('note_id_client').value = note.id_client || '';
    document.getElementById('client_search_note').value = note.id_client ? (note.client_nom + ' ' + (note.client_prenom || '')) : '';
    document.getElementById('note_contenu').value = note.contenu;
    document.getElementById('note_show_on_login').checked = note.show_on_login == 1;
    document.getElementById('current_file_info').innerHTML = note.fichier_path ? `Fichier actuel : ${note.fichier_path}` : '';
    document.getElementById('noteAlerts').innerHTML = '';
    document.getElementById('noteModal').style.display = 'flex';
    document.getElementById('noteSaveBtn').disabled = false;
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
}

function submitNoteForm() {
    const form = document.getElementById('noteForm');
    const formData = new FormData(form);
    const alerts = document.getElementById('noteAlerts');
    const btn = document.getElementById('noteSaveBtn');
    
    btn.disabled = true;
    btn.innerHTML = '⌛ Sauvegarde...';
    
    fetch('actions/notes_save.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alerts.innerHTML = `<div class="alert alert-success">✅ ${data.message}</div>`;
            setTimeout(() => location.reload(), 800);
        } else {
            alerts.innerHTML = `<div class="alert alert-danger">⚠️ ${data.error}</div>`;
            btn.disabled = false;
            btn.innerHTML = '💾 Enregistrer';
        }
    })
    .catch(e => {
        alerts.innerHTML = `<div class="alert alert-danger">⚠️ Erreur réseau ou serveur. Vérifiez la taille du fichier.</div>`;
        btn.disabled = false;
        btn.innerHTML = '💾 Enregistrer';
    });
}

function confirmDeleteNote(id) {
    if (confirm('Supprimer cette note définitivement ?')) {
        fetch('actions/notes_delete.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error);
        });
    }
}
</script>

<style>
.table th { background: var(--bg-hover); color: var(--text-muted); font-weight: 600; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; }
.truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.max-w-300 { max-width: 300px; }
.badge-success { background: #2ecc71; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; }
.badge-secondary { background: #95a5a6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; }
.form-row { display: flex; gap: 15px; }
.flex-1 { flex: 1; }
.flex-2 { flex: 2; }
.relative { position: relative; }

/* Force Awesomplete styles visibility in Modal */
.awesomplete > ul {
    z-index: 9999 !important;
    background: #fff !important;
    border: 1px solid #ccc !important;
}
.awesomplete > ul > li {
    color: #333 !important;
    background: #fff;
    border-bottom: 1px solid #eee;
}
.awesomplete > ul > li:hover, .awesomplete > ul > li[aria-selected="true"] {
    background: #e9ecef !important;
    color: #000 !important;
}
.awesomplete mark {
    background: #ffeeba;
    padding: 0 2px;
}
</style>
