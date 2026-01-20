<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';
$editData = null;
$historique = [];

// Vérifier si on est en mode édition
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM FC_cyber_credits WHERE id = ? AND actif = 1");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
        if (!$editData) {
            $errorMessage = "Crédit client non trouvé.";
        } else {
            // Récupérer l'historique des mouvements
            $stmt = $pdo->prepare("
                SELECT h.*, s.nom as session_nom 
                FROM FC_cyber_credits_historique h 
                LEFT JOIN FC_cyber s ON h.session_cyber_id = s.id 
                WHERE h.credit_id = ? 
                ORDER BY h.date_mouvement DESC 
                LIMIT 10
            ");
            $stmt->execute([$editId]);
            $historique = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération du crédit : " . htmlspecialchars($e->getMessage());
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_client = trim($_POST['nom_client'] ?? '');
    $id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
    $montant = !empty($_POST['montant']) ? floatval($_POST['montant']) : 0;
    $type_operation = $_POST['type_operation'] ?? 'AJOUT';
    $notes = trim($_POST['notes'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($nom_client)) {
        $errors[] = "Le nom du client est obligatoire.";
    }
    
    if ($montant <= 0 && !$editData) {
        $errors[] = "Le montant initial doit être supérieur à 0.";
    }
    
    if ($editData && ($montant != 0 || $type_operation === 'CORRECTION') && empty($description)) {
        $errors[] = "Une description est obligatoire pour les mouvements de crédit.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($editData) {
                // Modification d'un crédit existant
                $stmt = $pdo->prepare("UPDATE FC_cyber_credits SET nom_client = ?, notes = ?, id_client = ? WHERE id = ?");
                $stmt->execute([$nom_client, $notes, $id_client, $editData['id']]);
                
                // Si il y a un mouvement de crédit
                if ($montant != 0 || $type_operation === 'CORRECTION') {
                    $solde_avant = $editData['solde_actuel'];
                    
                    if ($type_operation === 'CORRECTION') {
                        $solde_apres = $montant;
                    } elseif ($type_operation === 'DEDUCTION') {
                        $solde_apres = $solde_avant - $montant;
                    } else {
                        $solde_apres = $solde_avant + $montant;
                    }
                    
                    // Mettre à jour le solde
                    $stmt = $pdo->prepare("UPDATE FC_cyber_credits SET solde_actuel = ? WHERE id = ?");
                    $stmt->execute([$solde_apres, $editData['id']]);
                    
                    // Enregistrer le mouvement
                    $stmt = $pdo->prepare("
                        INSERT INTO FC_cyber_credits_historique 
                        (credit_id, type_mouvement, montant, solde_avant, solde_apres, description, utilisateur) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $editData['id'], 
                        $type_operation, 
                        $montant, 
                        $solde_avant, 
                        $solde_apres, 
                        $description,
                        $_SESSION['username'] ?? 'Système'
                    ]);
                }
                
                $_SESSION['credits_message'] = "Crédit client modifié avec succès.";
            } else {
                // Création d'un nouveau crédit
                $stmt = $pdo->prepare("
                    INSERT INTO FC_cyber_credits (nom_client, solde_actuel, notes, id_client) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$nom_client, $montant, $notes, $id_client]);
                $credit_id = $pdo->lastInsertId();
                
                // Enregistrer le mouvement initial
                $stmt = $pdo->prepare("
                    INSERT INTO FC_cyber_credits_historique 
                    (credit_id, type_mouvement, montant, solde_avant, solde_apres, description, utilisateur) 
                    VALUES (?, 'AJOUT', ?, 0, ?, ?, ?)
                ");
                $stmt->execute([
                    $credit_id, 
                    $montant, 
                    $montant, 
                    'Crédit initial',
                    $_SESSION['username'] ?? 'Système'
                ]);
                
                $_SESSION['credits_message'] = "Crédit client créé avec succès.";
            }
            
            $pdo->commit();
            echo '<script>window.location.href = "index.php?page=cyber_credits_list";</script>';
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}
?>

<h1><?= $editData ? 'Modifier le crédit client' : 'Nouveau crédit client' ?></h1>

<p><a href="index.php?page=cyber_credits_list" class="text-accent no-underline">← Retour à la liste des crédits</a></p>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error mb-15">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>




<div class="grid grid-cols-1 lg:grid-cols-2 gap-20">
    <div>
        <form method="POST" class="card p-20">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-20 mb-20">
        <div>
            <label for="nom_client" class="block mb-5">Nom du client * :</label>
            <input type="text" id="nom_client" name="nom_client" required
                   value="<?= htmlspecialchars($editData['nom_client'] ?? '') ?>"
                   class="form-control"
                   placeholder="Nom complet du client">
            <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($editData['id_client'] ?? $_POST['id_client'] ?? '') ?>">
        </div>
        
        <?php if ($editData): ?>
            <div>
                <label class="block mb-5">Solde actuel :</label>
                <div class="p-8 mt-5 bg-secondary-light border border-light rounded-4 font-bold text-lg">
                    <?php 
                    $solde = $editData['solde_actuel'];
                    $color_class = $solde > 0 ? 'text-success' : ($solde < 0 ? 'text-danger' : 'text-muted');
                    ?>
                    <span class="<?= $color_class ?>"><?= number_format($solde, 2) ?> €</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="mb-20">
        <label for="notes" class="block mb-5">Notes :</label>
        <textarea id="notes" name="notes" rows="3"
                  class="form-control"
                  placeholder="Informations complémentaires sur le client..."><?= htmlspecialchars($editData['notes'] ?? '') ?></textarea>
    </div>
    
    <?php if ($editData): ?>
        <!-- Section mouvement de crédit -->
        <div class="bg-accent text-white p-10 -mx-20 -mt-20 mb-20 font-bold">
            Mouvement de crédit (optionnel)
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-20 mb-20">
            <div>
                <label for="type_operation" class="block mb-5">Type d'opération :</label>
                <select id="type_operation" name="type_operation" class="form-control">
                    <option value="AJOUT">Ajout de crédit</option>
                    <option value="DEDUCTION">Déduction</option>
                    <option value="CORRECTION">Correction</option>
                </select>
            </div>
            
            <div>
                <label for="montant" class="block mb-5">Montant (€) :</label>
                <input type="number" id="montant" name="montant" step="0.01" min="0"
                       class="form-control"
                       placeholder="0.00">
                <small class="text-muted block mt-5">
                    Laissez vide si pas de mouvement
                </small>
            </div>
            
            <div>
                <label for="description" class="block mb-5">Description du mouvement :</label>
                <input type="text" id="description" name="description"
                       class="form-control"
                       placeholder="Raison du mouvement de crédit">
            </div>
        </div>
    <?php else: ?>
        <div class="mb-20">
            <label for="montant" class="block mb-5">Crédit initial (€) * :</label>
            <input type="number" id="montant" name="montant" step="0.01" min="0.01" required
                   class="form-control"
                   placeholder="20.00">
            <small class="text-muted block mt-5">
                Montant initial du crédit client
            </small>
        </div>
    <?php endif; ?>
    
    <div class="flex align-center">
        <button type="submit" class="btn btn-accent btn-lg mr-15 cursor-pointer">
            <?= $editData ? 'Modifier le crédit' : 'Créer le crédit' ?>
        </button>
        <a href="index.php?page=cyber_credits_list" class="btn btn-secondary">
            Annuler
        </a>
    </div>
        </form>
    </div>

<script src="js/awesomplete.min.js"></script>
<link rel="stylesheet" href="css/awesomplete.css" />
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nomInput = document.getElementById('nom_client');
    const idInput = document.getElementById('id_client');
    let clientsList = [];

    if (nomInput) {
        let awesomplete = new Awesomplete(nomInput, {
            minChars: 2,
            maxItems: 15,
            autoFirst: true
        });

        nomInput.addEventListener('input', function() {
            if (this.value.length < 2) return;
            
            fetch('api/search_clients.php?q=' + encodeURIComponent(this.value))
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        clientsList = data;
                        awesomplete.list = data;
                    } else {
                        clientsList = [];
                    }
                })
                .catch(err => {
                    console.error('Erreur recherche:', err);
                    clientsList = [];
                });
        });
        
        nomInput.addEventListener('awesomplete-selectcomplete', function(e) {
            let selectedItem = null;
            if (e.text && e.text.id) {
                selectedItem = e.text;
            } else if (clientsList.length > 0) {
                const selectedValue = (e.text && e.text.value) ? e.text.value : e.text;
                selectedItem = clientsList.find(c => c.value === selectedValue || c.label === selectedValue);
            }

            if(selectedItem && selectedItem.id) {
                idInput.value = selectedItem.id;
            }
        });
    }
});
</script>

<?php if ($editData && !empty($historique)): ?>
    <div>
        <div class="card overflow-hidden h-full">
        <div class="bg-secondary text-white p-15 font-bold">
            Derniers mouvements
        </div>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-secondary-light">
                    <th class="p-10 text-left border-b border-light">Date</th>
                    <th class="p-10 text-center border-b border-light">Type</th>
                    <th class="p-10 text-right border-b border-light">Montant</th>
                    <th class="p-10 text-right border-b border-light">Solde après</th>
                    <th class="p-10 text-left border-b border-light">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $mouvement): ?>
                    <tr class="border-b border-light">
                        <td class="p-10 text-muted">
                            <?= date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])) ?>
                        </td>
                        <td class="p-10 text-center">
                            <?php
                            $type_classes = [
                                'AJOUT' => 'badge-success',
                                'DEDUCTION' => 'badge-danger',
                                'CORRECTION' => 'badge-warning'
                            ];
                            $badge_class = $type_classes[$mouvement['type_mouvement']] ?? 'badge-secondary';
                            ?>
                            <span class="badge <?= $badge_class ?>">
                                <?= $mouvement['type_mouvement'] ?>
                            </span>
                        </td>
                        <td class="p-10 text-right font-bold">
                            <?= number_format($mouvement['montant'], 2) ?> €
                        </td>
                        <td class="p-10 text-right font-bold">
                            <?php
                            $solde = $mouvement['solde_apres'];
                            $color_class = $solde > 0 ? 'text-success' : ($solde < 0 ? 'text-danger' : 'text-muted');
                            ?>
                            <span class="<?= $color_class ?>"><?= number_format($solde, 2) ?> €</span>
                        </td>
                        <td class="p-10">
                            <?= htmlspecialchars($mouvement['description']) ?>
                            <?php if ($mouvement['session_nom']): ?>
                                <br><small class="text-muted">Session: <?= htmlspecialchars($mouvement['session_nom']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="p-10 text-center bg-secondary-light">
            <a href="index.php?page=cyber_credits_history&id=<?= $editData['id'] ?>" class="text-accent no-underline hover:underline">
                Voir l'historique complet →
            </a>
        </div>
    </div>
        </div>
    </div>
<?php endif; ?>
</div>
