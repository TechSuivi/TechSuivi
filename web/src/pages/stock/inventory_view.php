<?php
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct interdit');
}

$sessionId = $_GET['id'] ?? 0;

if (!$sessionId) {
    echo "ID de session manquant.";
    return;
}

// Fetch session info
$stmt = $pdo->prepare("SELECT * FROM inventory_sessions WHERE id = :id");
$stmt->execute([':id' => $sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo "Session introuvable.";
    return;
}

// Fetch items
$itemStmt = $pdo->prepare("SELECT * FROM inventory_items WHERE session_id = :id ORDER BY id DESC");
$itemStmt->execute([':id' => $sessionId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul total
$grandTotal = 0;
foreach ($items as $item) {
    $grandTotal += $item['prix_achat_ht'] * $item['quantity'];
}

$isClosed = $session['status'] === 'CLOSED';
?>

<div class="container container-center max-w-1200 p-20">
    <!-- En-tête -->
    <div class="flex-between-center mb-20">
        <div>
            <a href="index.php?page=inventory_list" class="text-muted no-underline hover:text-color transition-colors">&larr; Retour</a>
            <h1 class="my-5 text-color flex items-center gap-10">
                📦 <?= htmlspecialchars($session['name']) ?>
            </h1>
            <span class="text-muted text-sm flex items-center gap-5">
                <span class="badge <?= $isClosed ? 'badge-danger' : 'badge-success' ?>"><?= ($isClosed ? '🔒 CLÔTURÉ' : '🔓 OUVERT') ?></span>
                <span>- Créé le <?= date('d/m/Y', strtotime($session['created_at'])) ?></span>
            </span>
        </div>
        <div class="bg-gradient-success text-white p-15 rounded-12 text-right shadow-md">
            <div class="text-xs opacity-80 uppercase tracking-wilder font-bold">VALEUR TOTALE</div>
            <div class="text-2xl font-bold" id="display_total"><?= number_format($grandTotal, 2, ',', ' ') ?> €</div>
        </div>
    </div>

    <!-- Zone de Saisie (Scan) -->
    <?php if (!$isClosed): ?>
    <div class="card p-15 mb-20">
        <div class="flex flex-wrap gap-10 items-end">
            <!-- Code EAN -->
            <div class="flex-none w-full md:w-160">
                <label for="scan_ean" class="block font-bold mb-5 text-color text-sm">Code EAN / SN (Scan)</label>
                <input type="text" id="scan_ean" class="form-control w-full p-10 text-lg border-2 border-primary rounded bg-input text-color focus:border-primary-dark outline-none" placeholder="Scanner..." autofocus autocomplete="off">
            </div>
            
            <!-- Désignation -->
            <div class="flex-1 min-w-200">
                <label for="scan_designation" class="block font-bold mb-5 text-color text-sm">Désignation</label>
                <input type="text" id="scan_designation" class="form-control w-full p-10 border rounded bg-input text-color" placeholder="Nom du produit">
            </div>

            <!-- Prix HT -->
            <div class="flex-none w-full md:w-100">
                <label for="scan_price" class="block font-bold mb-5 text-color text-sm">Prix HT</label>
                <input type="number" id="scan_price" step="0.01" class="form-control w-full p-10 border rounded bg-input text-color">
                <!-- Selecteur d'historique (caché par défaut) -->
                <select id="price_history" class="hidden mt-5 w-full p-5 rounded bg-info text-white border-none text-xs cursor-pointer">
                    <option value="">Historique...</option>
                </select>
            </div>

            <!-- Quantité -->
            <div class="flex-none w-full md:w-70">
                <label for="scan_qty" class="block font-bold mb-5 text-color text-sm">Qté</label>
                <input type="number" id="scan_qty" value="1" min="1" class="form-control w-full p-10 border rounded font-bold bg-input text-color">
            </div>

            <!-- Bouton Ajouter -->
            <div class="flex-none w-full md:w-auto">
                <button onclick="addItem()" class="btn btn-primary h-full px-25 py-11 font-bold whitespace-nowrap w-full md:w-auto">AJOUTER</button>
            </div>
        </div>
        <div id="scan_message" class="mt-10 min-h-20 text-muted italic text-sm"></div>
    </div>
    <?php endif; ?>

    <!-- Liste des articles -->
    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-input border-b border-border text-muted uppercase text-xs font-bold">
                        <th class="p-12 text-left">Désignation</th>
                        <th class="p-12 text-left">EAN</th>
                        <th class="p-12 text-right">Prix HT</th>
                        <th class="p-12 text-center">Qté</th>
                        <th class="p-12 text-right">Total</th>
                        <th class="p-12 text-right w-50"></th>
                    </tr>
                </thead>
                <tbody id="inventory_table_body">
                    <?php foreach ($items as $item): ?>
                    <tr id="row_<?= $item['id'] ?>" class="border-b border-border hover:bg-hover transition-colors">
                        <td class="p-12 font-medium text-dark"><?= htmlspecialchars($item['designation']) ?></td>
                        <td class="p-12 text-muted font-mono text-sm"><?= htmlspecialchars($item['ean_code']) ?></td>
                        <td class="p-12 text-right text-dark"><?= number_format($item['prix_achat_ht'], 2) ?> €</td>
                        <td class="p-12 text-center">
                            <?php if (!$isClosed): ?>
                            <input type="number" value="<?= $item['quantity'] ?>" 
                                   onchange="updateItem(<?= $item['id'] ?>, this.value, <?= $item['prix_achat_ht'] ?>)"
                                   class="w-60 text-center p-5 border rounded bg-input text-dark focus:border-primary outline-none">
                            <?php else: ?>
                                <b class="text-dark"><?= $item['quantity'] ?></b>
                            <?php endif; ?>
                        </td>
                        <td class="p-12 text-right font-bold text-dark">
                            <?= number_format($item['prix_achat_ht'] * $item['quantity'], 2) ?> €
                        </td>
                        <td class="p-12 text-right">
                            <?php if (!$isClosed): ?>
                            <button onclick="deleteItem(<?= $item['id'] ?>)" class="bg-transparent border-none text-danger cursor-pointer text-lg hover:text-danger-dark transition-colors" title="Supprimer">&times;</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$isClosed): ?>
<script>
    const eanInput = document.getElementById('scan_ean');
    const desInput = document.getElementById('scan_designation');
    const priceInput = document.getElementById('scan_price');
    const qtyInput = document.getElementById('scan_qty');
    const msgDiv = document.getElementById('scan_message');
    const historySelect = document.getElementById('price_history');
    const sessionId = <?= $sessionId ?>;

    // Écouter "Entrée" sur le champ EAN
    eanInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const ean = this.value.trim();
            if (ean) {
                lookupProduct(ean);
            }
        }
    });

    // Écouter "Entrée" pour ajouter sur les autres champs
    [desInput, priceInput, qtyInput].forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') addItem();
        });
    });

    // Écouter le changement de sélection de prix
    historySelect.addEventListener('change', function() {
        if (this.value !== "") {
            priceInput.value = this.value;
            // Optionnel : Focus sur quantité après choix
            qtyInput.focus();
            qtyInput.select();
        }
    });

    function lookupProduct(ean) {
        msgDiv.textContent = 'Recherche...';
        msgDiv.className = 'mt-10 min-h-20 text-muted italic text-sm';
        
        // Reset Selector
        historySelect.classList.add('hidden');
        historySelect.innerHTML = '<option value="">▼ Historique Prix</option>';

        fetch(`api/inventory_lookup.php?ean=${encodeURIComponent(ean)}`)
            .then(res => res.json())
            .then(data => {
                if (data.found) {
                    desInput.value = data.designation;
                    priceInput.value = data.prix_achat_ht;
                    msgDiv.innerHTML = '<span class="text-success font-bold">✓ Produit connu trouvé</span>';
                    
                    // Gestion de l'historique des prix
                    if (data.history && data.history.length > 1) {
                        historySelect.classList.remove('hidden');
                        data.history.forEach(h => {
                            // Convertir date SQL en format court
                            const dateObj = new Date(h.date);
                            const dateStr = dateObj.toLocaleDateString();
                            const opt = document.createElement('option');
                            opt.value = h.price;
                            opt.text = `${parseFloat(h.price).toFixed(2)} € (${dateStr})`;
                            // Marquer le prix actuel comme sélectionné si identique (ou laisser vide pour forcer le choix)
                            // Ici on laisse l'utilisateur choisir si besoin, mais le prix par défaut est déjà dans l'input
                            historySelect.appendChild(opt);
                        });
                    }

                    qtyInput.focus();
                    qtyInput.select();
                } else {
                    msgDiv.innerHTML = '<span class="text-warning font-bold">⚠ Nouveau produit (inconnu en base)</span>';
                    desInput.focus();
                }
            })
            .catch(err => {
                msgDiv.innerHTML = '<span class="text-danger font-bold">Erreur recherche</span>';
            });
    }

    function addItem() {
        const ean = eanInput.value.trim();
        const des = desInput.value.trim();
        const price = parseFloat(priceInput.value);
        const qty = parseInt(qtyInput.value);

        if (!des || isNaN(price) || isNaN(qty)) {
            alert("Veuillez remplir désignation, prix et quantité.");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_item');
        formData.append('session_id', sessionId);
        formData.append('ean', ean);
        formData.append('designation', des);
        formData.append('quantity', qty);
        formData.append('prix', price);

        fetch('api/inventory_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Refresh simple pour l'instant (ou append row dynamiquement pour perf)
                window.location.reload(); 
            } else {
                alert("Erreur: " + data.error);
            }
        });
    }

    function deleteItem(id) {
        if (!confirm("Supprimer la ligne ?")) return;
        const formData = new FormData();
        formData.append('action', 'delete_item');
        formData.append('id', id);
        
        fetch('api/inventory_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) document.getElementById('row_'+id).remove();
            else alert(data.error);
        });
    }

    function updateItem(id, newQty, price) {
        const formData = new FormData();
        formData.append('action', 'update_item');
        formData.append('id', id);
        formData.append('quantity', newQty);
        
        fetch('api/inventory_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(() => {
            // Update total visual if helpful, or reload
            window.location.reload();
        });
    }
</script>
<?php endif; ?>
