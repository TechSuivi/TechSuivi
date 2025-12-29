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

<div class="container" style="padding: 20px; max-width: 1200px;">
    <!-- En-tête -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <a href="index.php?page=inventory_list" style="color: var(--text-muted); text-decoration: none;">&larr; Retour</a>
            <h1 style="margin: 5px 0; color: var(--text-color);">📦 <?= htmlspecialchars($session['name']) ?></h1>
            <span style="color: var(--text-muted); font-size: 0.9em;"><?= ($isClosed ? '🔒 CLÔTURÉ' : '🔓 OUVERT') ?> - Créé le <?= date('d/m/Y', strtotime($session['created_at'])) ?></span>
        </div>
        <div style="background-color: #28a745; color: white; padding: 15px 25px; border-radius: 8px; text-align: right;">
            <div style="font-size: 0.8em; opacity: 0.9;">VALEUR TOTALE</div>
            <div style="font-size: 1.5em; font-weight: bold;" id="display_total"><?= number_format($grandTotal, 2, ',', ' ') ?> €</div>
        </div>
    </div>

    <!-- Zone de Saisie (Scan) -->
    <?php if (!$isClosed): ?>
    <style>
        .scan-form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px; /* Espace réduit pour densifier */
            align-items: flex-end;
        }
        .scan-group {
            display: flex;
            flex-direction: column;
            min-width: 0; /* Important pour le flex-shrink */
        }
        /* Style des inputs/boutons pour garantir l'alignement */
        .scan-group .form-control, 
        .scan-group button {
            height: 42px; /* Hauteur fixe pour tout le monde */
            box-sizing: border-box;
        }
        .scan-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-color);
            font-size: 0.9em;
            white-space: nowrap;
        }
        
        /* Mobile first : tout en 100% large ou 50% */
        .scan-group { flex: 1 1 100%; }

        /* Tablette et + : on commence à aligner */
        @media (min-width: 768px) {
            .group-ean { flex: 0 0 160px; }
            .group-des { flex: 1 1 auto; min-width: 200px; } /* Prend la place restante mais pas moins de 200px */
            .group-price { flex: 0 0 100px; }
            .group-qty { flex: 0 0 70px; }
            .group-btn { flex: 0 0 auto; }
        }
    </style>
    <div class="card" style="background: var(--card-bg); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <div class="scan-form-container">
            <div class="scan-group group-ean">
                <label style="font-weight: bold; margin-bottom: 5px; color: var(--text-color);">Code EAN (Scan)</label>
                <input type="text" id="scan_ean" class="form-control" placeholder="Scanner ici..." style="width: 100%; padding: 10px; font-size: 1.1em; border: 2px solid #2196f3; border-radius: 4px; background: var(--bg-color); color: var(--text-color);" autofocus autocomplete="off">
            </div>
            <div class="scan-group group-des">
                <label style="font-weight: bold; margin-bottom: 5px; color: var(--text-color);">Désignation</label>
                <input type="text" id="scan_designation" class="form-control" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-color); color: var(--text-color);" placeholder="Nom du produit">
            </div>
            <div class="scan-group group-price">
                <label style="font-weight: bold; margin-bottom: 5px; color: var(--text-color);">Prix HT</label>
                <input type="number" id="scan_price" step="0.01" class="form-control" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-color); color: var(--text-color);">
                <!-- Selecteur d'historique (caché par défaut) -->
                <select id="price_history" style="display:none; margin-top: 5px; width: 100%; padding: 5px; border-radius: 4px; background: var(--accent-color); color: white; border: none; font-size: 0.9em; cursor: pointer;">
                    <option value="">Historique...</option>
                </select>
            </div>
            <div class="scan-group group-qty">
                <label style="font-weight: bold; margin-bottom: 5px; color: var(--text-color);">Qté</label>
                <input type="number" id="scan_qty" value="1" min="1" class="form-control" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; font-weight: bold; background: var(--bg-color); color: var(--text-color);">
            </div>
            <div class="scan-group group-btn">
                <button onclick="addItem()" style="padding: 11px 25px; background-color: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; white-space: nowrap;">AJOUTER</button>
            </div>
        </div>
        <div id="scan_message" style="margin-top: 10px; min-height: 20px; font-style: italic; color: var(--text-muted);"></div>
    </div>
    <?php endif; ?>

    <!-- Liste des articles -->
    <div class="card" style="background: var(--card-bg); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: var(--text-color);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: rgba(0,0,0,0.05); border-bottom: 2px solid var(--border-color);">
                <tr>
                    <th style="padding: 12px; text-align: left; color: var(--text-color);">Désignation</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-color);">EAN</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-color);">Prix HT</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-color);">Qté</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-color);">Total</th>
                    <th style="padding: 12px; text-align: right;"></th>
                </tr>
            </thead>
            <tbody id="inventory_table_body">
                <?php foreach ($items as $item): ?>
                <tr id="row_<?= $item['id'] ?>" style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding: 12px; font-weight: 500;"><?= htmlspecialchars($item['designation']) ?></td>
                    <td style="padding: 12px; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($item['ean_code']) ?></td>
                    <td style="padding: 12px; text-align: right;"><?= number_format($item['prix_achat_ht'], 2) ?> €</td>
                    <td style="padding: 12px; text-align: center;">
                        <?php if (!$isClosed): ?>
                        <input type="number" value="<?= $item['quantity'] ?>" 
                               onchange="updateItem(<?= $item['id'] ?>, this.value, <?= $item['prix_achat_ht'] ?>)"
                               style="width: 60px; text-align: center; padding: 4px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-color); color: var(--text-color);">
                        <?php else: ?>
                            <b><?= $item['quantity'] ?></b>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; text-align: right; font-weight: bold;">
                        <?= number_format($item['prix_achat_ht'] * $item['quantity'], 2) ?> €
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <?php if (!$isClosed): ?>
                        <button onclick="deleteItem(<?= $item['id'] ?>)" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2em;">&times;</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
        msgDiv.style.color = 'var(--text-muted)';
        
        // Reset Selector
        historySelect.style.display = 'none';
        historySelect.innerHTML = '<option value="">▼ Historique Prix</option>';

        fetch(`api/inventory_lookup.php?ean=${encodeURIComponent(ean)}`)
            .then(res => res.json())
            .then(data => {
                if (data.found) {
                    desInput.value = data.designation;
                    priceInput.value = data.prix_achat_ht;
                    msgDiv.innerHTML = '<span style="color: green;">✓ Produit connu trouvé</span>';
                    
                    // Gestion de l'historique des prix
                    if (data.history && data.history.length > 1) {
                        historySelect.style.display = 'block';
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
                    msgDiv.innerHTML = '<span style="color: orange;">⚠ Nouveau produit (inconnu en base)</span>';
                    desInput.focus();
                }
            })
            .catch(err => {
                msgDiv.innerHTML = '<span style="color: red;">Erreur recherche</span>';
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
