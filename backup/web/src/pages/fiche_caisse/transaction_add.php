<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// La connexion $pdo et la session sont gérées par index.php
$errorMessage = '';
$editData = null;

// Vérifier si on est en mode édition
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    try {
        // Jointure pour récupérer le nom du client si existant
        $stmt = $pdo->prepare("
            SELECT t.*, c.nom as client_nom, c.prenom as client_prenom 
            FROM FC_transactions t
            LEFT JOIN clients c ON t.id_client = c.ID
            WHERE t.id = ?
        ");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
        if (!$editData) {
            $errorMessage = "Transaction non trouvée.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération de la transaction : " . htmlspecialchars($e->getMessage());
    }
}

// Récupération des moyens de paiement
$moyens_paiement = [];
try {
    $stmt = $pdo->query("SELECT moyen FROM FC_moyens_paiement ORDER BY moyen ASC");
    $moyens_paiement = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Pas critique, on continue sans les moyens de paiement
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $montant = $_POST['montant'] ?? null;
    $type = $_POST['type'] ?? '';
    $banque = trim($_POST['banque'] ?? '');
    $num_cheque = trim($_POST['num_cheque'] ?? '');
    $acompte = !empty($_POST['acompte']) ? $_POST['acompte'] : null;
    $solde = !empty($_POST['solde']) ? $_POST['solde'] : null;
    $num_facture = trim($_POST['num_facture'] ?? '');
    // Si la date est vide, on met la date du jour par défaut (pour éviter l'erreur SQL et répondre à la demande)
    $paye_le = !empty($_POST['paye_le']) ? $_POST['paye_le'] : date('Y-m-d');
    $id_client = !empty($_POST['id_client']) ? (int)$_POST['id_client'] : null;
    
    // Validation
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom/description est obligatoire.";
    }
    
    if (empty($montant) || !is_numeric($montant) || $montant <= 0) {
        $errors[] = "Le montant doit être supérieur à 0.";
    }
    
    // Convertir les montants vides en null
    if ($montant === '' || $montant === null) {
        $montant = null;
    }
    if ($acompte === '' || $acompte === null) {
        $acompte = null;
    }
    if ($solde === '' || $solde === null) {
        $solde = null;
    }
    
    if (empty($num_facture)) {
        $errors[] = "Le numéro de facture est obligatoire.";
    }

    if (empty($type)) {
        $errors[] = "Le moyen de paiement est obligatoire.";
    }
    
    if (empty($errors)) {
        try {
            if ($editData) {
                // Modification
                $stmt = $pdo->prepare("UPDATE FC_transactions SET nom = ?, montant = ?, type = ?, banque = ?, num_cheque = ?, acompte = ?, solde = ?, num_facture = ?, paye_le = ?, id_client = ? WHERE id = ?");
                $stmt->execute([$nom, $montant, $type, $banque, $num_cheque, $acompte, $solde, $num_facture, $paye_le, $id_client, $editData['id']]);
                $_SESSION['transaction_message'] = "Transaction modifiée avec succès.";
            } else {
                // Ajout
                $stmt = $pdo->prepare("INSERT INTO FC_transactions (nom, montant, type, banque, num_cheque, acompte, solde, num_facture, paye_le, id_client) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $montant, $type, $banque, $num_cheque, $acompte, $solde, $num_facture, $paye_le, $id_client]);
                $_SESSION['transaction_message'] = "Transaction ajoutée avec succès.";
            }
            echo '<script>window.location.href = "index.php?page=transactions_list";</script>';
            exit();
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}
?>

<h1><?= $editData ? 'Modifier la transaction' : 'Nouvelle transaction' ?></h1>

<p><a href="index.php?page=transactions_list" style="color: var(--accent-color);">← Retour à la liste</a></p>

<?php if (!empty($errorMessage)): ?>
    <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #ffe6e6; border-radius: 4px;">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<form method="POST" style="background-color: var(--card-bg); padding: 20px; border-radius: 8px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
    
    <!-- Row 1: Client/Desc (2 cols) -->
    <div style="grid-column: span 2;">
        <label for="nom">Client OU Description * :</label>
        <div style="position: relative;">
            <?php 
                $defaultValue = $editData['nom'] ?? '';
                // Priorité au nom du client si présent
                if ($editData && !empty($editData['client_nom'])) {
                    $defaultValue = $editData['client_nom'] . ' ' . ($editData['client_prenom'] ?? '');
                }
            ?>
            <input type="text" id="nom" name="nom" required autocomplete="off"
                   value="<?= htmlspecialchars($defaultValue) ?>"
                   style="width: 100%; padding: 8px; margin-top: 5px;"
                   placeholder="Nom du client ou description libre">
            <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($editData['id_client'] ?? '') ?>">
            <div id="client_suggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"></div>
        </div>
        <div style="font-size: 0.8em; color: var(--text-muted); margin-top: 4px;">
            Tapez un nom pour rechercher un client, ou écrivez simplement une description.
        </div>
    </div>

    <!-- Row 1: Montant (1 col) -->
    <div>
        <label for="montant">Montant (€) * :</label>
        <input type="number" id="montant" name="montant" step="0.01" min="0.01" required
               value="<?= htmlspecialchars($editData['montant'] ?? '') ?>"
               style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <!-- Row 1: Type (1 col) -->
    <div>
        <label for="type">Moyen de paiement * :</label>
        <select id="type" name="type" required style="width: 100%; padding: 8px; margin-top: 5px;" onchange="toggleChequeFields()">
            <option value="">Sélectionner...</option>
            <?php foreach ($moyens_paiement as $moyen): ?>
                <option value="<?= htmlspecialchars($moyen) ?>" <?= ($editData['type'] ?? '') === $moyen ? 'selected' : '' ?>>
                    <?= htmlspecialchars($moyen) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Cheque Fields (Full Width Wrapper -> Inner Grid) -->
    <div id="cheque-fields" style="display: none; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <label for="banque">Banque :</label>
            <input type="text" id="banque" name="banque"
                   value="<?= htmlspecialchars($editData['banque'] ?? '') ?>"
                   style="width: 100%; padding: 8px; margin-top: 5px;"
                   placeholder="Nom de la banque">
        </div>
        
        <div>
            <label for="num_cheque">N° Chèque :</label>
            <input type="text" id="num_cheque" name="num_cheque"
                   value="<?= htmlspecialchars($editData['num_cheque'] ?? '') ?>"
                   style="width: 100%; padding: 8px; margin-top: 5px;"
                   placeholder="Numéro de chèque">
        </div>
    </div>
    
    <!-- Row 2: Financials (1 col each) -->
    <div>
        <label for="acompte">Acompte (€) :</label>
        <input type="number" id="acompte" name="acompte" step="0.01" min="0"
               value="<?= htmlspecialchars($editData['acompte'] ?? '') ?>"
               style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <div>
        <label for="solde">Solde (€) :</label>
        <input type="number" id="solde" name="solde" step="0.01"
               value="<?= htmlspecialchars($editData['solde'] ?? '') ?>"
               style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <div>
        <label for="num_facture">N° Facture * :</label>
        <input type="text" id="num_facture" name="num_facture" required
               value="<?= htmlspecialchars($editData['num_facture'] ?? '') ?>"
               style="width: 100%; padding: 8px; margin-top: 5px;"
               placeholder="Numéro de facture">
    </div>
    
    <div>
        <label for="paye_le">Payé le :</label>
        <input type="date" id="paye_le" name="paye_le"
               value="<?= htmlspecialchars($editData['paye_le'] ?? date('Y-m-d')) ?>"
               style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <!-- Actions (Full Width) -->
    <div style="grid-column: 1 / -1; margin-top: 10px;">
        <button type="submit" style="background-color: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            <?= $editData ? 'Modifier la transaction' : 'Enregistrer la transaction' ?>
        </button>
        <a href="index.php?page=transactions_list" style="margin-left: 15px; padding: 12px 24px; background-color: var(--secondary-color); color: white; text-decoration: none; border-radius: 4px;">
            Annuler
        </a>
    </div>
</form>

<div style="margin-top: 30px; padding: 20px; background-color: var(--card-bg); border-radius: 8px;">
    <h3>Aide</h3>
    <ul>
        <li><strong>Moyen de paiement :</strong> Sélectionnez le moyen de paiement utilisé</li>
        <li><strong>Chèque :</strong> Les champs Banque et N° Chèque apparaissent automatiquement</li>
        <li><strong>Acompte/Solde :</strong> Saisissez les montants sans calcul automatique</li>
        <li><strong>Montants :</strong> Tous les champs peuvent être laissés vides si nécessaire</li>
    </ul>
</div>

<script>
// Fonction pour afficher/masquer les champs chèque
function toggleChequeFields() {
    const typeSelect = document.getElementById('type');
    const chequeFields = document.getElementById('cheque-fields');
    
    if (typeSelect.value.toLowerCase().includes('chèque') || typeSelect.value.toLowerCase().includes('cheque')) {
        chequeFields.style.display = 'grid';
    } else {
        chequeFields.style.display = 'none';
    }
}

// Initialiser l'affichage au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    toggleChequeFields();
    
    // Client Search Logic
    const clientSearch = document.getElementById('nom'); // Utilise maintentant le champ nom
    const clientId = document.getElementById('id_client');
    const suggestions = document.getElementById('client_suggestions');
    let searchTimeout;
    
    if (clientSearch) {
        clientSearch.addEventListener('input', function() {
            const term = this.value;
            // Si l'utilisateur tape, on considère qu'il modifie le lien client -> on reset l'ID
            // Sauf s'il reclique sur une suggestion après
            clientId.value = ''; 
            
            clearTimeout(searchTimeout);
            
            if (term.length === 0) {
                 clientId.value = '';
                 suggestions.style.display = 'none';
                 return;
            }
            
            if (term.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const url = `api/search_clients.php?term=${encodeURIComponent(term)}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.style.padding = '8px 12px';
                            div.style.cursor = 'pointer';
                            div.style.borderBottom = '1px solid var(--border-color)';
                            div.onmouseover = function() { this.style.backgroundColor = 'var(--input-bg)'; };
                            div.onmouseout = function() { this.style.backgroundColor = 'transparent'; };
                            
                            div.textContent = client.label;
                            div.onclick = function() {
                                clientSearch.value = client.value; // Use just Name Firstname
                                clientId.value = client.id;
                                suggestions.style.display = 'none';
                            };
                            suggestions.appendChild(div);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                })
                .catch(err => console.error("Search error:", err));
            }, 300);
        });
        
        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (e.target !== clientSearch && e.target !== suggestions) {
                suggestions.style.display = 'none';
            }
        });
    }
});
</script>