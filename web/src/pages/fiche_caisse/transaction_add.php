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
    <div class="alert alert-error">
        <?= $errorMessage ?>
    </div>
<?php endif; ?>

<form method="POST" class="card form-grid-4">
    
    <!-- Row 1: Client/Desc (2 cols) -->
    <div class="col-span-2">
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
                   class="form-control"
                   placeholder="Nom du client ou description libre">
            <input type="hidden" id="id_client" name="id_client" value="<?= htmlspecialchars($editData['id_client'] ?? '') ?>">
            <div id="client_suggestions" class="suggestions-dropdown"></div>
        </div>
        <div class="text-muted small mt-5">
            Tapez un nom pour rechercher un client, ou écrivez simplement une description.
        </div>
    </div>

    <!-- Row 1: Montant (1 col) -->
    <div>
        <label for="montant">Montant (€) * :</label>
        <input type="number" id="montant" name="montant" step="0.01" min="0.01" required
               value="<?= htmlspecialchars($editData['montant'] ?? '') ?>"
               class="form-control">
    </div>
    
    <!-- Row 1: Type (1 col) -->
    <div>
        <label for="type">Moyen de paiement * :</label>
        <select id="type" name="type" required class="form-control" onchange="toggleChequeFields()">
            <option value="">Sélectionner...</option>
            <?php foreach ($moyens_paiement as $moyen): ?>
                <option value="<?= htmlspecialchars($moyen) ?>" <?= ($editData['type'] ?? '') === $moyen ? 'selected' : '' ?>>
                    <?= htmlspecialchars($moyen) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Cheque Fields (Full Width Wrapper -> Inner Grid) -->
    <div id="cheque-fields" class="col-span-full form-grid-4" style="display: none; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
        <div class="col-span-2">
            <label for="banque">Banque :</label>
            <input type="text" id="banque" name="banque"
                   value="<?= htmlspecialchars($editData['banque'] ?? '') ?>"
                   class="form-control"
                   placeholder="Nom de la banque">
        </div>
        
        <div class="col-span-2">
            <label for="num_cheque">N° Chèque :</label>
            <input type="text" id="num_cheque" name="num_cheque"
                   value="<?= htmlspecialchars($editData['num_cheque'] ?? '') ?>"
                   class="form-control"
                   placeholder="Numéro de chèque">
        </div>
    </div>
    
    <!-- Row 2: Financials (1 col each) -->
    <div>
        <label for="acompte">Acompte (€) :</label>
        <input type="number" id="acompte" name="acompte" step="0.01" min="0"
               value="<?= htmlspecialchars($editData['acompte'] ?? '') ?>"
               class="form-control">
    </div>
    
    <div>
        <label for="solde">Solde (€) :</label>
        <input type="number" id="solde" name="solde" step="0.01"
               value="<?= htmlspecialchars($editData['solde'] ?? '') ?>"
               class="form-control">
    </div>
    
    <div>
        <label for="num_facture">N° Facture * :</label>
        <input type="text" id="num_facture" name="num_facture" required
               value="<?= htmlspecialchars($editData['num_facture'] ?? '') ?>"
               class="form-control"
               placeholder="Numéro de facture">
    </div>
    
    <div>
        <label for="paye_le">Payé le :</label>
        <input type="date" id="paye_le" name="paye_le"
               value="<?= htmlspecialchars($editData['paye_le'] ?? date('Y-m-d')) ?>"
               class="form-control">
    </div>
    
    <!-- Actions (Full Width) -->
    <!-- Actions (Full Width) -->
    <div class="col-span-full mt-10">
        <button type="submit" class="btn btn-primary">
            <?= $editData ? 'Modifier la transaction' : 'Enregistrer la transaction' ?>
        </button>
        <a href="index.php?page=transactions_list" class="btn btn-secondary ml-15">
            Annuler
        </a>
    </div>
</form>



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
                            div.className = 'suggestion-item';
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