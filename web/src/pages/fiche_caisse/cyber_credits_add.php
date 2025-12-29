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
    
    if ($editData && $montant != 0 && empty($description)) {
        $errors[] = "Une description est obligatoire pour les mouvements de crédit.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($editData) {
                // Modification d'un crédit existant
                $stmt = $pdo->prepare("UPDATE FC_cyber_credits SET nom_client = ?, notes = ? WHERE id = ?");
                $stmt->execute([$nom_client, $notes, $editData['id']]);
                
                // Si il y a un mouvement de crédit
                if ($montant != 0) {
                    $solde_avant = $editData['solde_actuel'];
                    $solde_apres = $solde_avant + ($type_operation === 'DEDUCTION' ? -$montant : $montant);
                    
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
                    INSERT INTO FC_cyber_credits (nom_client, solde_actuel, notes) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$nom_client, $montant, $notes]);
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

<p><a href="index.php?page=cyber_credits_list" style="color: var(--accent-color);">← Retour à la liste des crédits</a></p>

<?php if (!empty($errorMessage)): ?>
    <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #ffe6e6; border-radius: 4px;">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<form method="POST" style="background-color: var(--card-bg); padding: 20px; border-radius: 8px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <div>
            <label for="nom_client">Nom du client * :</label>
            <input type="text" id="nom_client" name="nom_client" required
                   value="<?= htmlspecialchars($editData['nom_client'] ?? '') ?>"
                   style="width: 100%; padding: 8px; margin-top: 5px;"
                   placeholder="Nom complet du client">
        </div>
        
        <?php if ($editData): ?>
            <div>
                <label>Solde actuel :</label>
                <div style="padding: 8px; margin-top: 5px; background-color: var(--bg-color); border: 1px solid #ddd; border-radius: 4px; font-weight: bold; font-size: 18px;">
                    <?php 
                    $solde = $editData['solde_actuel'];
                    $color = $solde > 0 ? 'var(--success-color, #28a745)' : ($solde < 0 ? 'var(--danger-color, #dc3545)' : 'var(--text-secondary)');
                    ?>
                    <span style="color: <?= $color ?>;"><?= number_format($solde, 2) ?> €</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 20px;">
        <label for="notes">Notes :</label>
        <textarea id="notes" name="notes" rows="3"
                  style="width: 100%; padding: 8px; margin-top: 5px; resize: vertical;"
                  placeholder="Informations complémentaires sur le client..."><?= htmlspecialchars($editData['notes'] ?? '') ?></textarea>
    </div>
    
    <?php if ($editData): ?>
        <!-- Section mouvement de crédit -->
        <div style="background-color: var(--accent-color); color: white; padding: 10px; margin: 20px -20px; font-weight: bold;">
            Mouvement de crédit (optionnel)
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="type_operation">Type d'opération :</label>
                <select id="type_operation" name="type_operation" style="width: 100%; padding: 8px; margin-top: 5px;">
                    <option value="AJOUT">Ajout de crédit</option>
                    <option value="DEDUCTION">Déduction</option>
                    <option value="CORRECTION">Correction</option>
                </select>
            </div>
            
            <div>
                <label for="montant">Montant (€) :</label>
                <input type="number" id="montant" name="montant" step="0.01" min="0"
                       style="width: 100%; padding: 8px; margin-top: 5px;"
                       placeholder="0.00">
                <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                    Laissez vide si pas de mouvement
                </small>
            </div>
            
            <div>
                <label for="description">Description du mouvement :</label>
                <input type="text" id="description" name="description"
                       style="width: 100%; padding: 8px; margin-top: 5px;"
                       placeholder="Raison du mouvement de crédit">
            </div>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 20px;">
            <label for="montant">Crédit initial (€) * :</label>
            <input type="number" id="montant" name="montant" step="0.01" min="0.01" required
                   style="width: 100%; padding: 8px; margin-top: 5px;"
                   placeholder="20.00">
            <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                Montant initial du crédit client
            </small>
        </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 20px;">
        <button type="submit" style="background-color: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            <?= $editData ? 'Modifier le crédit' : 'Créer le crédit' ?>
        </button>
        <a href="index.php?page=cyber_credits_list" style="margin-left: 15px; padding: 12px 24px; background-color: var(--secondary-color); color: white; text-decoration: none; border-radius: 4px;">
            Annuler
        </a>
    </div>
</form>

<?php if ($editData && !empty($historique)): ?>
    <div style="margin-top: 30px; background-color: var(--card-bg); border-radius: 8px; overflow: hidden;">
        <div style="background-color: var(--secondary-color); color: white; padding: 15px; font-weight: bold;">
            Derniers mouvements
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: var(--bg-color);">
                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Date</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">Type</th>
                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Montant</th>
                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Solde après</th>
                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $mouvement): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; color: var(--text-secondary);">
                            <?= date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])) ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php
                            $type_colors = [
                                'AJOUT' => 'var(--success-color, #28a745)',
                                'DEDUCTION' => 'var(--danger-color, #dc3545)',
                                'CORRECTION' => 'var(--warning-color, #ffc107)'
                            ];
                            $color = $type_colors[$mouvement['type_mouvement']] ?? 'var(--text-secondary)';
                            ?>
                            <span style="background-color: <?= $color ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                <?= $mouvement['type_mouvement'] ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold;">
                            <?= number_format($mouvement['montant'], 2) ?> €
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold;">
                            <?php
                            $solde = $mouvement['solde_apres'];
                            $color = $solde > 0 ? 'var(--success-color, #28a745)' : ($solde < 0 ? 'var(--danger-color, #dc3545)' : 'var(--text-secondary)');
                            ?>
                            <span style="color: <?= $color ?>;"><?= number_format($solde, 2) ?> €</span>
                        </td>
                        <td style="padding: 10px;">
                            <?= htmlspecialchars($mouvement['description']) ?>
                            <?php if ($mouvement['session_nom']): ?>
                                <br><small style="color: var(--text-secondary);">Session: <?= htmlspecialchars($mouvement['session_nom']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="padding: 10px; text-align: center; background-color: var(--bg-color);">
            <a href="index.php?page=cyber_credits_history&id=<?= $editData['id'] ?>" style="color: var(--accent-color);">
                Voir l'historique complet →
            </a>
        </div>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background-color: var(--card-bg); border-radius: 8px;">
    <h3>Aide</h3>
    <ul>
        <?php if ($editData): ?>
            <li><strong>Modification :</strong> Changez le nom ou les notes du client</li>
            <li><strong>Ajout de crédit :</strong> Augmente le solde du client</li>
            <li><strong>Déduction :</strong> Diminue le solde (remboursement, utilisation manuelle)</li>
            <li><strong>Correction :</strong> Ajustement du solde pour corriger une erreur</li>
        <?php else: ?>
            <li><strong>Nom du client :</strong> Identifiant unique pour le crédit</li>
            <li><strong>Crédit initial :</strong> Montant de départ du compte crédit</li>
            <li><strong>Notes :</strong> Informations complémentaires (téléphone, remarques...)</li>
        <?php endif; ?>
        <li><strong>Historique :</strong> Tous les mouvements sont tracés automatiquement</li>
    </ul>
</div>