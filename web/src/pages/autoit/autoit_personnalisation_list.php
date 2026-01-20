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
                $stmt = $pdo->prepare("INSERT INTO autoit_personnalisation (nom, type_registre, fichier_reg_nom, fichier_reg_path, ligne_registre, description, OS, defaut) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_registre'],
                    $_POST['fichier_reg_nom'] ?? null,
                    $_POST['fichier_reg_path'] ?? null,
                    $_POST['ligne_registre'] ?? null,
                    $_POST['description'] ?? null,
                    $_POST['OS'],
                    isset($_POST['defaut']) ? 1 : 0
                ]);
                $success = "Personnalisation ajoutée avec succès !";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE autoit_personnalisation SET nom=?, type_registre=?, fichier_reg_nom=?, fichier_reg_path=?, ligne_registre=?, description=?, OS=?, defaut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['type_registre'],
                    $_POST['fichier_reg_nom'] ?? null,
                    $_POST['fichier_reg_path'] ?? null,
                    $_POST['ligne_registre'] ?? null,
                    $_POST['description'] ?? null,
                    $_POST['OS'],
                    isset($_POST['defaut']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "Personnalisation modifiée avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM autoit_personnalisation WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Personnalisation supprimée avec succès !";
                break;
        }
    }
}

// Gestion de l'upload de fichiers .reg (optionnel)
if (isset($_FILES['fichier_reg_upload']) && $_FILES['fichier_reg_upload']['error'] === UPLOAD_ERR_OK) {
    $uploadBaseDir = 'uploads/';
    $uploadSubDir = 'uploads/autoit/personnalisation/';
    
    // Vérifier que le dossier uploads existe et est accessible en écriture
    if (!is_dir($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'existe pas. Vous pouvez saisir le chemin manuellement.";
    } elseif (!is_writable($uploadBaseDir)) {
        $uploadError = "Le dossier uploads n'est pas accessible en écriture. Vous pouvez saisir le chemin manuellement.";
    } else {
        $fileName = basename($_FILES['fichier_reg_upload']['name']);
        
        // Vérifier l'extension .reg
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension === 'reg') {
            // Créer le sous-dossier s'il n'existe pas
            if (!is_dir($uploadSubDir)) {
                $result = createDirectoryWithPermissions($uploadSubDir);
                if (!$result['success']) {
                    $uploadError = "Impossible de créer le dossier autoit/personnalisation. " . getPermissionErrorMessage($uploadSubDir);
                }
            }
            
            if (!isset($uploadError)) {
                $uploadPath = $uploadSubDir . $fileName;
                
                if (move_uploaded_file($_FILES['fichier_reg_upload']['tmp_name'], $uploadPath)) {
                    $uploadSuccess = "Fichier .reg uploadé avec succès dans : " . $uploadPath;
                    $uploadedFile = $uploadPath;
                } else {
                    $uploadError = "Erreur lors de l'upload du fichier. Vous pouvez saisir le chemin manuellement.";
                }
            }
        } else {
            $uploadError = "Seuls les fichiers .reg sont autorisés.";
        }
    }
}

// Récupération des personnalisations
$stmt = $pdo->query("SELECT * FROM autoit_personnalisation ORDER BY nom");
$personnalisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'une personnalisation pour édition
$editPersonnalisation = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM autoit_personnalisation WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editPersonnalisation = $stmt->fetch(PDO::FETCH_ASSOC);
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
                <?= $editPersonnalisation ? 'Modifier la personnalisation' : 'Ajouter une personnalisation' ?>
            </h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editPersonnalisation ? 'edit' : 'add' ?>">
                <?php if ($editPersonnalisation): ?>
                    <input type="hidden" name="id" value="<?= $editPersonnalisation['id'] ?>">
                <?php endif; ?>
                
                <div class="mb-15">
                    <label for="nom" class="block mb-5 font-bold">Nom de la personnalisation :</label>
                    <input type="text" id="nom" name="nom" class="form-control w-full p-8 border rounded bg-input text-dark" required value="<?= htmlspecialchars($editPersonnalisation['nom'] ?? '') ?>">
                </div>
                
                <div class="mb-15">
                    <label for="type_registre" class="block mb-5 font-bold">Type de registre :</label>
                    <select id="type_registre" name="type_registre" class="form-control w-full p-8 border rounded bg-input text-dark" required onchange="toggleRegistreType()">
                        <option value="fichier_reg" <?= ($editPersonnalisation['type_registre'] ?? 'fichier_reg') === 'fichier_reg' ? 'selected' : '' ?>>Fichier .reg</option>
                        <option value="ligne_registre" <?= ($editPersonnalisation['type_registre'] ?? '') === 'ligne_registre' ? 'selected' : '' ?>>Ligne de registre</option>
                    </select>
                </div>
                
                <div id="fichier-reg-fields" class="<?= ($editPersonnalisation['type_registre'] ?? 'fichier_reg') === 'fichier_reg' ? '' : 'hidden' ?>">
                    <div class="mb-15">
                        <label for="fichier_reg_upload" class="block mb-5 font-bold">Upload fichier .reg (optionnel) :</label>
                        <input type="file" id="fichier_reg_upload" name="fichier_reg_upload" accept=".reg" class="form-control w-full p-8 border rounded bg-input text-dark">
                        <small class="text-muted text-xs mt-5 block">Si l'upload ne fonctionne pas, vous pouvez saisir le nom et le chemin manuellement ci-dessous.</small>
                        <?php if ($editPersonnalisation && $editPersonnalisation['fichier_reg_nom']): ?>
                            <p class="mt-5 text-sm font-bold">Fichier actuel : <?= htmlspecialchars($editPersonnalisation['fichier_reg_nom']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-15">
                        <label for="fichier_reg_nom" class="block mb-5 font-bold">Nom du fichier .reg :</label>
                        <input type="text" id="fichier_reg_nom" name="fichier_reg_nom" class="form-control w-full p-8 border rounded bg-input text-dark" value="<?= htmlspecialchars($editPersonnalisation['fichier_reg_nom'] ?? (isset($uploadedFile) ? $fileName : '')) ?>">
                    </div>
                    
                    <div class="mb-15">
                        <label for="fichier_reg_path" class="block mb-5 font-bold">Chemin du fichier .reg :</label>
                        <input type="text" id="fichier_reg_path" name="fichier_reg_path" class="form-control w-full p-8 border rounded bg-input text-dark" value="<?= htmlspecialchars($editPersonnalisation['fichier_reg_path'] ?? (isset($uploadedFile) ? $uploadedFile : '')) ?>">
                    </div>
                </div>
                
                <div id="ligne-registre-fields" class="<?= ($editPersonnalisation['type_registre'] ?? '') === 'ligne_registre' ? '' : 'hidden' ?>">
                    <!-- Assistant de création de ligne de registre -->
                    <div class="bg-light p-15 border border-border rounded mb-15">
                        <h4 class="mt-0 mb-15 text-sm font-bold text-muted">Assistant de création</h4>
                        <div class="flex flex-col gap-10">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                                <div>
                                    <label for="reg_hkey" class="block text-xs font-bold mb-2">Clé racine :</label>
                                    <select id="reg_hkey" class="form-control w-full p-5 text-xs bg-input border rounded">
                                        <option value="HKEY_CURRENT_USER">HKEY_CURRENT_USER</option>
                                        <option value="HKEY_LOCAL_MACHINE">HKEY_LOCAL_MACHINE</option>
                                        <option value="HKEY_CLASSES_ROOT">HKEY_CLASSES_ROOT</option>
                                        <option value="HKEY_USERS">HKEY_USERS</option>
                                        <option value="HKEY_CURRENT_CONFIG">HKEY_CURRENT_CONFIG</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="reg_path" class="block text-xs font-bold mb-2">Chemin de la clé :</label>
                                    <input type="text" id="reg_path" class="form-control w-full p-5 text-xs bg-input border rounded" placeholder="ex: Software\Microsoft\Windows\...">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                                <div>
                                    <label for="reg_name" class="block text-xs font-bold mb-2">Nom de la valeur :</label>
                                    <input type="text" id="reg_name" class="form-control w-full p-5 text-xs bg-input border rounded" placeholder="ex: HideFileExt">
                                </div>
                                <div>
                                    <label for="reg_type" class="block text-xs font-bold mb-2">Type de valeur :</label>
                                    <select id="reg_type" class="form-control w-full p-5 text-xs bg-input border rounded" onchange="updateValuePlaceholder()">
                                        <option value="dword">DWORD (32-bit)</option>
                                        <option value="qword">QWORD (64-bit)</option>
                                        <option value="sz">String (REG_SZ)</option>
                                        <option value="expand_sz">String étendue</option>
                                        <option value="multi_sz">Multi-String</option>
                                        <option value="binary">Binaire</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="reg_value" class="block text-xs font-bold mb-2">Valeur :</label>
                                    <input type="text" id="reg_value" class="form-control w-full p-5 text-xs bg-input border rounded" placeholder="ex: 00000000">
                                </div>
                            </div>
                            
                            <div class="flex gap-5 mt-5">
                                <button type="button" class="btn btn-xs btn-info" onclick="generateRegistryLine()">Générer</button>
                                <button type="button" class="btn btn-xs btn-secondary" onclick="clearRegistryBuilder()">Effacer</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-15">
                        <label for="ligne_registre" class="block mb-5 font-bold">Ligne de base de registre :</label>
                        <textarea id="ligne_registre" name="ligne_registre" rows="4" class="form-control w-full p-8 border rounded bg-input text-dark font-mono text-xs" placeholder="ex: [HKEY_CURRENT_USER\Software\...]&#10;&quot;Value&quot;=dword:00000000"><?= htmlspecialchars($editPersonnalisation['ligne_registre'] ?? '') ?></textarea>
                        <small class="text-muted text-xs mt-5 block">Format : [HKEY_...]\n"CleName"=dword:value ou "CleName"="StringValue"</small>
                    </div>
                </div>
                
                <div class="mb-15">
                    <label for="description" class="block mb-5 font-bold">Description :</label>
                    <textarea id="description" name="description" rows="3" class="form-control w-full p-8 border rounded bg-input text-dark" placeholder="Description de cette personnalisation"><?= htmlspecialchars($editPersonnalisation['description'] ?? '') ?></textarea>
                </div>
                
                <div class="mb-15">
                    <label for="OS" class="block mb-5 font-bold">Version de Windows :</label>
                    <select id="OS" name="OS" class="form-control w-full p-8 border rounded bg-input text-dark" required>
                        <option value="">-- Sélectionner une version --</option>
                        <option value="7" <?= ($editPersonnalisation['OS'] ?? '') == '7' ? 'selected' : '' ?>>Windows 7</option>
                        <option value="8" <?= ($editPersonnalisation['OS'] ?? '') == '8' ? 'selected' : '' ?>>Windows 8</option>
                        <option value="81" <?= ($editPersonnalisation['OS'] ?? '') == '81' ? 'selected' : '' ?>>Windows 8.1</option>
                        <option value="10" <?= ($editPersonnalisation['OS'] ?? '') == '10' ? 'selected' : '' ?>>Windows 10</option>
                        <option value="11" <?= ($editPersonnalisation['OS'] ?? '') == '11' ? 'selected' : '' ?>>Windows 11</option>
                        <option value="0" <?= ($editPersonnalisation['OS'] ?? '') == '0' ? 'selected' : '' ?>>Toutes versions</option>
                    </select>
                </div>

                <div class="mb-20">
                    <label class="flex items-center gap-10 cursor-pointer">
                        <input type="checkbox" name="defaut" value="1" <?= ($editPersonnalisation['defaut'] ?? 0) ? 'checked' : '' ?>>
                        <span>Personnalisation par défaut</span>
                    </label>
                </div>
                
                <div class="flex flex-wrap gap-10">
                    <button type="submit" class="btn btn-primary flex-1"><?= $editPersonnalisation ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($editPersonnalisation): ?>
                        <a href="index.php?page=autoit_personnalisation_list" class="btn btn-secondary flex-1 text-center">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Colonne de droite : Liste -->
        <div class="lg:col-span-3 card bg-secondary border p-20">
            <!-- <h2 class="mt-0 mb-20 text-lg border-b border-border pb-10">Liste des personnalisations</h2> -->
            
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-left border-b border-border text-muted uppercase text-xs">
                            <th class="p-10">Nom</th>
                            <th class="p-10">Type</th>
                            <th class="p-10">Contenu</th>
                            <th class="p-10 text-center">Défaut</th>
                            <th class="p-10">Version Windows</th>
                            <th class="p-10 text-right" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personnalisations as $personnalisation): ?>
                        <tr class="border-b border-border hover:bg-hover transition-colors">
                            <td class="p-10 align-top">
                                <strong class="block text-dark"><?= htmlspecialchars($personnalisation['nom']) ?></strong>
                                <?php if ($personnalisation['description']): ?>
                                    <div class="text-xs text-muted mt-5"><?= htmlspecialchars($personnalisation['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top">
                                <span class="badge <?= $personnalisation['type_registre'] === 'fichier_reg' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= $personnalisation['type_registre'] === 'fichier_reg' ? 'Fichier .reg' : 'Ligne registre' ?>
                                </span>
                            </td>
                            <td class="p-10 align-top">
                                <?php if ($personnalisation['type_registre'] === 'fichier_reg'): ?>
                                    <span class="bg-light px-5 py-2 rounded text-xs font-mono inline-block"><?= htmlspecialchars($personnalisation['fichier_reg_nom']) ?></span>
                                <?php else: ?>
                                    <div class="flex flex-wrap gap-5 items-center">
                                        <code class="bg-light px-5 py-2 rounded text-xs font-mono break-all inline-block"><?= htmlspecialchars(substr($personnalisation['ligne_registre'], 0, 50)) ?><?= strlen($personnalisation['ligne_registre']) > 50 ? '...' : '' ?></code>
                                        <button class="btn btn-xs btn-info" onclick="showFullRegistry(<?= $personnalisation['id'] ?>)">Voir complet</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top text-center">
                                <?php if ($personnalisation['defaut']): ?>
                                    <span class="badge badge-success">Oui</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary opacity-50">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-10 align-top">
                                <?php
                                $osLabels = [
                                    '7' => 'Windows 7',
                                    '8' => 'Windows 8',
                                    '81' => 'Windows 8.1',
                                    '10' => 'Windows 10',
                                    '11' => 'Windows 11',
                                    '0' => 'Toutes versions'
                                ];
                                $osValue = $personnalisation['OS'] ?? '0';
                                ?>
                                <span class="badge badge-info"><?= $osLabels[$osValue] ?? 'Non défini' ?></span>
                            </td>
                            <td class="p-10 align-top text-right whitespace-nowrap">
                                <a href="index.php?page=autoit_personnalisation_list&edit=<?= $personnalisation['id'] ?>" class="btn btn-xs btn-primary p-5" title="Modifier">✏️</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette personnalisation ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $personnalisation['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger bg-transparent text-danger p-5 hover:scale-110" title="Supprimer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Ligne cachée pour afficher le registre complet -->
                        <?php if ($personnalisation['type_registre'] === 'ligne_registre'): ?>
                        <tr id="full-registry-<?= $personnalisation['id'] ?>" class="hidden bg-light">
                            <td colspan="6" class="p-15 border-b border-border">
                                <div class="bg-card border border-border rounded p-15">
                                    <strong class="block mb-5">Ligne de registre complète :</strong>
                                    <pre class="bg-dark text-light p-10 rounded text-sm overflow-x-auto font-mono whitespace-pre-wrap select-all mb-10 registry-full"><?= htmlspecialchars($personnalisation['ligne_registre']) ?></pre>
                                    <div class="flex gap-10">
                                        <button class="btn btn-sm btn-secondary" onclick="hideFullRegistry(<?= $personnalisation['id'] ?>)">Masquer</button>
                                        <button class="btn btn-sm btn-success" onclick="copyRegistry(<?= $personnalisation['id'] ?>, this)">Copier</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRegistreType() {
    const type = document.getElementById('type_registre').value;
    const fichierFields = document.getElementById('fichier-reg-fields');
    const ligneFields = document.getElementById('ligne-registre-fields');
    
    if (type === 'fichier_reg') {
        fichierFields.classList.remove('hidden');
        ligneFields.classList.add('hidden');
    } else {
        fichierFields.classList.add('hidden');
        ligneFields.classList.remove('hidden');
    }
}

function showFullRegistry(id) {
    document.getElementById('full-registry-' + id).classList.remove('hidden');
}

function hideFullRegistry(id) {
    document.getElementById('full-registry-' + id).classList.add('hidden');
}

function copyRegistry(id, btn) {
    const registryElement = document.querySelector('#full-registry-' + id + ' .registry-full');
    const text = registryElement.textContent;
    
    navigator.clipboard.writeText(text).then(function() {
        const originalText = btn.innerHTML;
        const originalClass = btn.className;
        
        btn.innerHTML = 'Copié !';
        
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.className = originalClass;
        }, 2000);
    }).catch(function(err) {
        console.error('Erreur lors de la copie: ', err);
        alert('Erreur lors de la copie dans le presse-papiers');
    });
}

// Auto-fill file path when file is uploaded
if(document.getElementById('fichier_reg_upload')) {
    document.getElementById('fichier_reg_upload').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('fichier_reg_nom').value = fileName;
            document.getElementById('fichier_reg_path').value = 'uploads/autoit/personnalisation/' + fileName;
        }
    });
}

// Fonctions pour l'assistant de registre
function updateValuePlaceholder() {
    const regType = document.getElementById('reg_type').value;
    const regValue = document.getElementById('reg_value');
    
    switch(regType) {
        case 'dword': regValue.placeholder = 'ex: 00000000 (hexadécimal)'; break;
        case 'qword': regValue.placeholder = 'ex: 0000000000000000 (hexadécimal)'; break;
        case 'sz': regValue.placeholder = 'ex: Ma chaîne de caractères'; break;
        case 'expand_sz': regValue.placeholder = 'ex: %SystemRoot%\\system32'; break;
        case 'multi_sz': regValue.placeholder = 'ex: Ligne1\\0Ligne2\\0Ligne3'; break;
        case 'binary': regValue.placeholder = 'ex: 01,02,03,04 (hexadécimal)'; break;
        default: regValue.placeholder = '';
    }
}

function generateRegistryLine() {
    const hkey = document.getElementById('reg_hkey').value;
    const path = document.getElementById('reg_path').value.trim();
    const name = document.getElementById('reg_name').value.trim();
    const type = document.getElementById('reg_type').value;
    const value = document.getElementById('reg_value').value.trim();
    
    if (!path || !name || !value) {
        alert('Veuillez remplir tous les champs de l\'assistant.');
        return;
    }
    
    let line = `[${hkey}\\${path}]\n`;
    
    if (type === 'sz') {
        line += `"${name}"="${value}"`;
    } else if (type === 'dword') {
        line += `"${name}"=dword:${value}`;
    } else {
        // Autres types simplifiés
        let typePrefix = '';
        if (type === 'hex' || type === 'binary') typePrefix = 'hex:';
        else if (type === 'qword') typePrefix = 'hex(b):';
        else if (type === 'expand_sz') typePrefix = 'hex(2):';
        else if (type === 'multi_sz') typePrefix = 'hex(7):';
        
        line += `"${name}"=${typePrefix}${value}`;
    }
    
    document.getElementById('ligne_registre').value = line;
}

function clearRegistryBuilder() {
    document.getElementById('reg_path').value = '';
    document.getElementById('reg_name').value = '';
    document.getElementById('reg_value').value = '';
    document.getElementById('reg_hkey').selectedIndex = 0;
    document.getElementById('reg_type').selectedIndex = 0;
}
</script>