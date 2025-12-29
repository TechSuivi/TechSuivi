<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

$message = '';
$statuts = [];

// Traitement des actions (ajout, modification, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nom = trim($_POST['nom'] ?? '');
        $couleur = trim($_POST['couleur'] ?? '#007bff');
        $description = trim($_POST['description'] ?? '');
        $ordre_affichage = intval($_POST['ordre_affichage'] ?? 0);
        
        if (empty($nom)) {
            $message = '<p style="color: red;">Le nom du statut est obligatoire.</p>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO intervention_statuts (nom, couleur, description, ordre_affichage) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$nom, $couleur, $description, $ordre_affichage])) {
                    $message = '<p style="color: green;">Statut ajouté avec succès !</p>';
                } else {
                    $message = '<p style="color: red;">Erreur lors de l\'ajout du statut.</p>';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = '<p style="color: red;">Ce nom de statut existe déjà.</p>';
                } else {
                    $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $couleur = trim($_POST['couleur'] ?? '#007bff');
        $description = trim($_POST['description'] ?? '');
        $ordre_affichage = intval($_POST['ordre_affichage'] ?? 0);
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        if (empty($nom) || $id <= 0) {
            $message = '<p style="color: red;">Données invalides pour la modification.</p>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE intervention_statuts SET nom = ?, couleur = ?, description = ?, ordre_affichage = ?, actif = ? WHERE id = ?");
                if ($stmt->execute([$nom, $couleur, $description, $ordre_affichage, $actif, $id])) {
                    $message = '<p style="color: green;">Statut modifié avec succès !</p>';
                } else {
                    $message = '<p style="color: red;">Erreur lors de la modification du statut.</p>';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = '<p style="color: red;">Ce nom de statut existe déjà.</p>';
                } else {
                    $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            $message = '<p style="color: red;">ID de statut invalide.</p>';
        } else {
            try {
                // Vérifier si le statut est utilisé
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM inter WHERE statut_id = ?");
                $stmt_check->execute([$id]);
                $count = $stmt_check->fetchColumn();
                
                if ($count > 0) {
                    $message = '<p style="color: red;">Impossible de supprimer ce statut car il est utilisé par ' . $count . ' intervention(s).</p>';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM intervention_statuts WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = '<p style="color: green;">Statut supprimé avec succès !</p>';
                    } else {
                        $message = '<p style="color: red;">Erreur lors de la suppression du statut.</p>';
                    }
                }
            } catch (PDOException $e) {
                $message = '<p style="color: red;">Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    }
}

// Récupération des statuts
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM intervention_statuts ORDER BY ordre_affichage ASC, nom ASC");
        $statuts = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = '<p style="color: red;">Erreur lors de la récupération des statuts : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
?>

<h1>Gestion des Statuts d'Intervention</h1>

<?php echo $message; ?>

<!-- Formulaire d'ajout -->
<div class="add-status-form" style="padding: 20px; border-radius: 8px; margin-bottom: 30px;">
    <h2>Ajouter un nouveau statut</h2>
    <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
        <input type="hidden" name="action" value="add">
        
        <div>
            <label for="nom" class="form-label">Nom du statut :</label>
            <input type="text" id="nom" name="nom" required class="form-input">
        </div>
        
        <div>
            <label for="couleur" class="form-label">Couleur :</label>
            <input type="color" id="couleur" name="couleur" value="#007bff" class="form-input color-input">
        </div>
        
        <div>
            <label for="description" class="form-label">Description :</label>
            <input type="text" id="description" name="description" class="form-input">
        </div>
        
        <div>
            <label for="ordre_affichage" class="form-label">Ordre d'affichage :</label>
            <input type="number" id="ordre_affichage" name="ordre_affichage" value="0" class="form-input">
        </div>
        
        <div style="grid-column: span 2;">
            <button type="submit" class="btn-primary">
                Ajouter le statut
            </button>
        </div>
    </form>
</div>

<!-- Liste des statuts -->
<div>
    <h2>Statuts existants</h2>
    
    <?php if (empty($statuts)): ?>
        <p>Aucun statut trouvé.</p>
    <?php else: ?>
        <div style="display: grid; gap: 15px;">
            <?php foreach ($statuts as $statut): ?>
                <div style="background-color: var(--bg-color, white); border: 1px solid var(--border-color, #ddd); border-radius: 8px; padding: 20px;">
                    <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background-color: <?= htmlspecialchars($statut['couleur']) ?>; border: 2px solid var(--border-color, #ddd);"></span>
                            <h3 style="margin: 0; color: var(--text-color, #333);"><?= htmlspecialchars($statut['nom']) ?></h3>
                            <?php if (!$statut['actif']): ?>
                                <span style="background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Inactif</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button onclick="editStatut(<?= $statut['id'] ?>)" style="padding: 5px 10px; background-color: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                                Modifier
                            </button>
                            <button onclick="deleteStatut(<?= $statut['id'] ?>, '<?= htmlspecialchars($statut['nom']) ?>')" style="padding: 5px 10px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                Supprimer
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($statut['description'])): ?>
                        <p style="margin: 0 0 10px 0; color: var(--text-muted, #666);"><?= htmlspecialchars($statut['description']) ?></p>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 20px; font-size: 14px; color: var(--text-muted, #666);">
                        <span>Ordre: <?= $statut['ordre_affichage'] ?></span>
                        <span>Créé: <?= date('d/m/Y H:i', strtotime($statut['created_at'])) ?></span>
                        <?php if ($statut['updated_at'] !== $statut['created_at']): ?>
                            <span>Modifié: <?= date('d/m/Y H:i', strtotime($statut['updated_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de modification -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: var(--bg-color, white); padding: 30px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3>Modifier le statut</h3>
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id" name="id">
            
            <div style="margin-bottom: 15px;">
                <label for="edit_nom" class="form-label">Nom du statut :</label>
                <input type="text" id="edit_nom" name="nom" required class="form-input">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="edit_couleur" class="form-label">Couleur :</label>
                <input type="color" id="edit_couleur" name="couleur" class="form-input color-input">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="edit_description" class="form-label">Description :</label>
                <input type="text" id="edit_description" name="description" class="form-input">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="edit_ordre_affichage" class="form-label">Ordre d'affichage :</label>
                <input type="number" id="edit_ordre_affichage" name="ordre_affichage" class="form-input">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="edit_actif" name="actif">
                    <span>Statut actif</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" class="btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn-primary">
                    Modifier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Formulaire de suppression caché -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" id="delete_id" name="id">
</form>

<div style="margin-top: 30px;">
    <a href="index.php?page=settings&tab=gestion" style="text-decoration: none; padding: 10px 15px; background-color: #6c757d; color: white; border-radius: 4px;">
        ← Retour aux paramètres
    </a>
</div>

<script>
// Données des statuts pour JavaScript
const statutsData = <?= json_encode($statuts) ?>;

function editStatut(id) {
    const statut = statutsData.find(s => s.id == id);
    if (!statut) return;
    
    document.getElementById('edit_id').value = statut.id;
    document.getElementById('edit_nom').value = statut.nom;
    document.getElementById('edit_couleur').value = statut.couleur;
    document.getElementById('edit_description').value = statut.description || '';
    document.getElementById('edit_ordre_affichage').value = statut.ordre_affichage;
    document.getElementById('edit_actif').checked = statut.actif == 1;
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function deleteStatut(id, nom) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le statut "${nom}" ?\n\nAttention: cette action est irréversible.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Fermer le modal en cliquant à l'extérieur
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Fermer le modal avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>

<style>
/* Styles pour le formulaire d'ajout */
.add-status-form {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #fff;
    color: #333;
}

.color-input {
    padding: 4px;
    height: 40px;
}

.btn-primary {
    padding: 10px 20px;
    background-color: var(--accent-color, #007bff);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary {
    padding: 10px 20px;
    background-color: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* Styles pour le mode sombre */
body.dark .add-status-form {
    background-color: #2c2c2c !important;
    border-color: #444 !important;
}

body.dark .form-label {
    color: #fff !important;
}

body.dark .form-input {
    background-color: #1a1a1a !important;
    border-color: #555 !important;
    color: #fff !important;
}

body.dark .settings-card {
    background-color: #2c2c2c;
    border-color: #444;
}

body.dark #editModal > div {
    background-color: #2c2c2c !important;
    color: #fff !important;
}

body.dark h1, body.dark h2, body.dark h3 {
    color: #fff !important;
}
</style>