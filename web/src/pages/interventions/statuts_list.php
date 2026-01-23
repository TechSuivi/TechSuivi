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

<style>
.grid-layout-custom {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
}
@media (min-width: 768px) {
    .grid-layout-custom {
        grid-template-columns: 1fr 2fr;
    }
}
</style>

<div class="container max-w-1600">
    <div class="page-header">
        <h1>
            <span>🏷️</span>
            Gestion des Statuts d'Intervention
        </h1>
    </div>

    <?php echo $message; ?>

    <div class="grid-layout-custom">
        <!-- Colonne Gauche : Formulaire -->
        <div>
            <div class="add-status-form sticky top-20" style="padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h2 style="margin-top: 0; margin-bottom: 20px;">➕ Ajouter un statut</h2>
                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
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
                    
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                        Ajouter le statut
                    </button>
                </form>
            </div>
        </div>

        <!-- Colonne Droite : Liste -->
        <div>
            <?php if (empty($statuts)): ?>
                <div style="text-align: center; padding: 40px; border: 2px dashed #ddd; border-radius: 8px; color: #666;">
                    <p>Aucun statut trouvé.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0; background-color: var(--card-bg, #fff); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border-color, #ddd);">
                        <thead style="background-color: var(--bg-color, #f8f9fa);">
                            <tr>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color, #ddd);">Nom (Couleur)</th>
                                <th style="padding: 12px 15px; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border-color, #ddd);">Description</th>
                                <th style="padding: 12px 15px; text-align: center; font-weight: 600; border-bottom: 1px solid var(--border-color, #ddd); width: 80px;">Ordre</th>
                                <th style="padding: 12px 15px; text-align: right; font-weight: 600; border-bottom: 1px solid var(--border-color, #ddd); width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statuts as $statut): ?>
                                <tr style="border-bottom: 1px solid var(--border-color, #eee);">
                                    <td style="padding: 12px 15px; border-bottom: 1px solid var(--border-color, #eee);">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="display: inline-block; width: 24px; height: 24px; border-radius: 50%; background-color: <?= htmlspecialchars($statut['couleur']) ?>; border: 2px solid rgba(0,0,0,0.1);"></span>
                                            <span style="font-weight: bold;"><?= htmlspecialchars($statut['nom']) ?></span>
                                            <?php if (!$statut['actif']): ?>
                                                <span style="background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; margin-left: 5px;">Inactif</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid var(--border-color, #eee); color: var(--text-muted, #666);">
                                        <?= htmlspecialchars($statut['description']) ?>
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid var(--border-color, #eee); text-align: center;">
                                        <?= $statut['ordre_affichage'] ?>
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid var(--border-color, #eee); text-align: right;">
                                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                            <button onclick="editStatut(<?= $statut['id'] ?>)" class="btn-sm-action text-info border-info hover:bg-info hover:text-white" title="Modifier">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            <button onclick="deleteStatut(<?= $statut['id'] ?>, '<?= htmlspecialchars($statut['nom']) ?>')" class="btn-sm-action text-danger border-danger hover:bg-danger hover:text-white" title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
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