<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';

// Traitement des actions (ajout, modification, suppression)
$success = null;
$uploadSuccess = null;
$uploadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO autoit_nettoyage (nom, fichier_nom, fichier_path, est_zip, commande_lancement, description, defaut) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['fichier_nom'] ?? null,
                    $_POST['fichier_path'] ?? null,
                    isset($_POST['est_zip']) ? 1 : 0,
                    $_POST['commande_lancement'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Outil de nettoyage ajouté avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_nettoyage SET nom=?, fichier_nom=?, fichier_path=?, est_zip=?, commande_lancement=?, description=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['fichier_nom'] ?? null,
                    $_POST['fichier_path'] ?? null,
                    isset($_POST['est_zip']) ? 1 : 0,
                    $_POST['commande_lancement'],
                    $_POST['description'] ?? null,
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Outil de nettoyage modifié avec succès !";
                break;
                
            case 'delete':
                // Si la suppression du fichier est demandée
                if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
                    $stmt = $pdo->prepare("SELECT fichier_path FROM autoit_nettoyage WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $fileData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fileData && !empty($fileData['fichier_path'])) {
                        // Construire le chemin absolu
                        $filePath = realpath(__DIR__ . '/../../' . $fileData['fichier_path']); // Correction du chemin
                        
                        // Sécurité basique pour éviter de supprimer n'importe quoi
                        if ($filePath && file_exists($filePath) && strpos($filePath, 'uploads') !== false) {
                            if (unlink($filePath)) {
                                $fileDeleted = true;
                            } else {
                                $fileDeleteError = "Erreur lors de la suppression du fichier.";
                            }
                        }
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM autoit_nettoyage WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Outil de nettoyage supprimé avec succès !";
                break;
        }
    }
}

// Gestion de l'upload de fichiers (optionnel)
if (isset($_FILES['fichier_upload']) && $_FILES['fichier_upload']['error'] === UPLOAD_ERR_OK) {
    $uploadBaseDir = 'uploads/';
    $uploadSubDir = 'uploads/autoit/nettoyage/';
    
    // Vérifier que le dossier uploads existe et est accessible en écriture
    if (!is_dir($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'existe pas. Vous pouvez saisir le chemin manuellement.";
    } elseif (!is_writable($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'est pas accessible en écriture. Vous pouvez saisir le chemin manuellement.";
    } else {
        // Créer le sous-dossier s'il n'existe pas
        if (!is_dir($uploadSubDir)) {
            $result = createDirectoryWithPermissions($uploadSubDir);
            if (!$result['success']) {
                $uploadError = "Impossible de créer le dossier autoit/nettoyage. " . getPermissionErrorMessage($uploadSubDir);
            }
        }
        
        if (!isset($uploadError)) {
            $fileName = basename($_FILES['fichier_upload']['name']);
            $uploadPath = $uploadSubDir . $fileName;
            
            if (move_uploaded_file($_FILES['fichier_upload']['tmp_name'], $uploadPath)) {
                $uploadSuccess = "Fichier uploadé avec succès dans : " . $uploadPath;
                $uploadedFile = $uploadPath;
            } else {
                $uploadError = "Erreur lors de l'upload du fichier. Vous pouvez saisir le chemin manuellement.";
            }
        }
    }
}

// Récupération des outils de nettoyage
$stmt = $pdo->query("SELECT * FROM autoit_nettoyage ORDER BY nom");
$nettoyages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'un outil pour édition
$editNettoyage = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_nettoyage WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editNettoyage = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container container-center max-w-1600">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-20 items-start">
        
        <!-- Colonne de gauche : Formulaire -->
        <div class="lg:col-span-1 card bg-secondary border p-20 sticky top-20">
            <?php if (isset($success)): ?>
                <div class="alert alert-success mb-20"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($uploadSuccess)): ?>
                <div class="alert alert-success mb-20"><?= htmlspecialchars($uploadSuccess) ?></div>
            <?php endif; ?>

            <?php if (isset($uploadError)): ?>
                <div class="alert alert-danger mb-20"><?= htmlspecialchars($uploadError) ?></div>
            <?php endif; ?>

            <h2 class="mt-0 mb-20 text-lg border-b border-border pb-10">
                <?= $editNettoyage ? 'Modifier l\'outil' : 'Ajouter un outil' ?>
            </h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editNettoyage ? 'edit' : 'add' ?>">
                <?php if ($editNettoyage): ?>
                    <input type="hidden" name="id" value="<?= $editNettoyage['id'] ?>">
                <?php endif; ?>
                
                <div class="mb-15">
                    <label for="nom" class="block mb-5 font-bold">Nom de l'outil :</label>
                    <input type="text" id="nom" name="nom" class="form-control w-full p-8 border rounded bg-input text-dark" required value="<?= htmlspecialchars($editNettoyage['nom'] ?? '') ?>">
                </div>
                
                <div class="mb-15">
                    <label for="fichier_upload" class="block mb-5 font-bold">Upload fichier (optionnel) :</label>
                    <input type="file" id="fichier_upload" name="fichier_upload" class="form-control w-full p-8 border rounded bg-input text-dark">
                    <small class="text-muted text-xs mt-5 block">Si l'upload ne fonctionne pas, vous pouvez saisir le nom et le chemin manuellement ci-dessous.</small>
                    <?php if ($editNettoyage && $editNettoyage['fichier_nom']): ?>
                        <p class="mt-5 text-sm font-bold">Fichier actuel : <?= htmlspecialchars($editNettoyage['fichier_nom']) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="mb-15">
                    <label for="fichier_nom" class="block mb-5 font-bold">Nom du fichier :</label>
                    <input type="text" id="fichier_nom" name="fichier_nom" class="form-control w-full p-8 border rounded bg-input text-dark" value="<?= htmlspecialchars($editNettoyage['fichier_nom'] ?? (isset($uploadedFile) ? $fileName : '')) ?>">
                </div>
                
                <div class="mb-15">
                    <label for="fichier_path" class="block mb-5 font-bold">Chemin du fichier :</label>
                    <input type="text" id="fichier_path" name="fichier_path" class="form-control w-full p-8 border rounded bg-input text-dark" value="<?= htmlspecialchars($editNettoyage['fichier_path'] ?? (isset($uploadedFile) ? $uploadedFile : '')) ?>">
                </div>
                
                <div class="mb-15">
                    <label class="flex items-center gap-10 cursor-pointer">
                        <input type="checkbox" name="est_zip" <?= ($editNettoyage['est_zip'] ?? 0) ? 'checked' : '' ?>>
                        <span>Fichier ZIP</span>
                    </label>
                </div>
                
                <div class="mb-15">
                    <label for="commande_lancement" class="block mb-5 font-bold">Commande de lancement :</label>
                    <input type="text" id="commande_lancement" name="commande_lancement" class="form-control w-full p-8 border rounded bg-input text-dark" required value="<?= htmlspecialchars($editNettoyage['commande_lancement'] ?? '') ?>" placeholder="ex: CCleaner.exe /AUTO">
                </div>
                
                <div class="mb-15">
                    <label for="description" class="block mb-5 font-bold">Description :</label>
                    <textarea id="description" name="description" rows="3" class="form-control w-full p-8 border rounded bg-input text-dark" placeholder="Description de l'outil de nettoyage"><?= htmlspecialchars($editNettoyage['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-20">
                    <label class="flex items-center gap-10 cursor-pointer">
                        <input type="checkbox" name="defaut" value="1" <?= ($editNettoyage['defaut'] ?? 0) ? 'checked' : '' ?>>
                        <span>Outil par défaut</span>
                    </label>
                </div>
                
                <div class="flex flex-wrap gap-10">
                    <button type="submit" class="btn btn-primary flex-1"><?= $editNettoyage ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editNettoyage): ?>
                        <a href="index.php?page=autoit_nettoyage_list" class="btn btn-secondary flex-1 text-center">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Colonne de droite : Liste -->
        <div class="lg:col-span-3 card bg-secondary border p-20">
            <!-- <h2 class="mt-0 mb-20 text-lg border-b border-border pb-10">Liste des outils</h2> -->
            
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-left border-b border-border text-muted uppercase text-xs">
                            <th class="p-10">Nom</th>
                            <th class="p-10">Fichier</th>
                            <th class="p-10">Commande</th>
                            <th class="p-10 text-center">Défaut</th>
                            <th class="p-10 text-right" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nettoyages as $nettoyage): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-10 align-top">
                                <strong class="block text-dark"><?= htmlspecialchars($nettoyage['nom']) ?></strong>
                                <?php if ($nettoyage['description']): ?>
                                    <div class="text-xs text-muted mt-5"><?= htmlspecialchars($nettoyage['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top">
                                <?php if ($nettoyage['fichier_nom']): ?>
                                    <div class="flex items-center gap-5">
                                        <span class="bg-light px-5 py-2 rounded text-xs font-mono inline-block"><?= htmlspecialchars($nettoyage['fichier_nom']) ?></span>
                                        <?php if ($nettoyage['est_zip']): ?>
                                            <span class="badge badge-info">ZIP</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-xs">Aucun fichier</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top">
                                <code class="bg-light px-5 py-2 rounded text-xs font-mono break-all inline-block"><?= htmlspecialchars($nettoyage['commande_lancement']) ?></code>
                            </td>
                            <td class="p-10 align-top text-center">
                                <?php if ($nettoyage['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary opacity-50">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top text-right whitespace-nowrap">
                                <a href="index.php?page=autoit_nettoyage_list&edit=<?= $nettoyage['id'] ?>" class="btn btn-xs btn-primary p-5" title="Modifier">✏️</a>
                                <button type="button" class="btn btn-xs btn-danger bg-transparent text-danger p-5 hover:scale-110" onclick="confirmDelete(<?= $nettoyage['id'] ?>)" title="Supprimer">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-auto bg-dark bg-opacity-50 backdrop-blur-sm flex items-center justify-center">
    <div class="card bg-secondary border p-0 w-full max-w-lg shadow-xl m-20">
        <div class="p-20 border-b border-border flex justify-between items-center">
            <h3 class="m-0 text-lg font-bold">Confirmer la suppression</h3>
            <span class="cursor-pointer text-2xl leading-none text-muted hover:text-dark" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="p-20">
            <p class="mb-15">Êtes-vous sûr de vouloir supprimer cet outil ?</p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteIdInput">
                <div class="mt-15">
                    <label class="flex items-center gap-10 cursor-pointer">
                        <input type="checkbox" name="delete_file" id="deleteFileCheckbox" value="1">
                        <span>Supprimer également le fichier sur le serveur ?</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="p-20 border-t border-border flex justify-end gap-10">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
            <button class="btn btn-danger" onclick="submitDelete()">Supprimer</button>
        </div>
    </div>
</div>

<script>
// Auto-fill file path when file is uploaded
if (document.getElementById('fichier_upload')) {
    document.getElementById('fichier_upload').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('fichier_nom').value = fileName;
            document.getElementById('fichier_path').value = 'uploads/autoit/nettoyage/' + fileName;
        }
    });
}

// Delete Modal Logic
const deleteModal = document.getElementById('deleteModal');
const deleteIdInput = document.getElementById('deleteIdInput');
const deleteFileCheckbox = document.getElementById('deleteFileCheckbox');

function confirmDelete(id) {
    if (!deleteModal) return;
    deleteIdInput.value = id;
    if(deleteFileCheckbox) deleteFileCheckbox.checked = false; 
    deleteModal.classList.remove('hidden');
}

function closeDeleteModal() {
    if (!deleteModal) return;
    deleteModal.classList.add('hidden');
}

function submitDelete() {
    document.getElementById('deleteForm').submit();
}

window.onclick = function(event) {
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
}
</script>
