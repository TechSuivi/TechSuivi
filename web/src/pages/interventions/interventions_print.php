<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

$intervention_id = trim($_GET['id'] ?? '');
$intervention = null;
$client = null;
$errorMessage = '';

if (empty($intervention_id)) {
    $errorMessage = 'ID d\'intervention manquant.';
} elseif (isset($pdo)) {
    try {
        // Récupérer les données de l'intervention avec les informations du client
        $stmt = $pdo->prepare("
            SELECT 
                i.id, 
                i.id_client, 
                i.date, 
                i.en_cours, 
                i.info, 
                i.nettoyage, 
                i.info_log, 
                i.note_user,
                c.nom,
                c.prenom,
                c.adresse1,
                c.adresse2,
                c.cp,
                c.ville,
                c.telephone,
                c.portable,
                c.mail
            FROM inter i 
            LEFT JOIN clients c ON i.id_client = c.ID 
            WHERE i.id = :id
        ");
        $stmt->bindParam(':id', $intervention_id);
        $stmt->execute();
        
        $result = $stmt->fetch();
        if ($result) {
            $intervention = $result;
        } else {
            $errorMessage = 'Intervention non trouvée.';
        }
    } catch (PDOException $e) {
        $errorMessage = 'Erreur lors de la récupération de l\'intervention : ' . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMessage = 'Erreur de configuration : la connexion à la base de données n\'est pas disponible.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'intervention - <?= htmlspecialchars($intervention_id) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2A4F9C;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2A4F9C;
            margin: 0;
            font-size: 24px;
        }
        
        .header h2 {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 16px;
            font-weight: normal;
        }
        
        .section {
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
        }
        
        .section h3 {
            margin: 0 0 15px 0;
            color: #2A4F9C;
            font-size: 16px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-item label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 3px;
        }
        
        .info-item span {
            display: block;
            padding: 5px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 3px;
            min-height: 20px;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .status.en-cours {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status.cloturee {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .description {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
            min-height: 100px;
        }
        
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        
        .signature-box {
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            min-height: 80px;
        }
        
        .signature-box h4 {
            margin: 0 0 10px 0;
            color: #555;
        }
        
        @media print {
            body {
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2A4F9C;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background-color: #1e3a7a;
        }
    </style>
</head>
<body>
    <?php if (!empty($errorMessage)): ?>
        <div style="color: red; text-align: center; padding: 20px;">
            <h2>Erreur</h2>
            <p><?= $errorMessage ?></p>
        </div>
    <?php else: ?>
        <button class="print-button no-print" onclick="window.print()">Imprimer</button>
        
        <div class="header">
            <h1>FICHE D'INTERVENTION</h1>
            <h2>N° <?= htmlspecialchars($intervention['id']) ?></h2>
        </div>
        
        <div class="info-grid">
            <div class="section">
                <h3>Informations Client</h3>
                <div class="info-item">
                    <label>Nom :</label>
                    <span><?= htmlspecialchars($intervention['nom'] . ' ' . $intervention['prenom']) ?></span>
                </div>
                <div class="info-item">
                    <label>Adresse :</label>
                    <span>
                        <?= htmlspecialchars($intervention['adresse1']) ?><br>
                        <?php if (!empty($intervention['adresse2'])): ?>
                            <?= htmlspecialchars($intervention['adresse2']) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($intervention['cp'] . ' ' . $intervention['ville']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Téléphone :</label>
                    <span><?= htmlspecialchars($intervention['telephone']) ?></span>
                </div>
                <?php if (!empty($intervention['portable'])): ?>
                <div class="info-item">
                    <label>Portable :</label>
                    <span><?= htmlspecialchars($intervention['portable']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($intervention['mail'])): ?>
                <div class="info-item">
                    <label>Email :</label>
                    <span><?= htmlspecialchars($intervention['mail']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h3>Informations Intervention</h3>
                <div class="info-item">
                    <label>Date :</label>
                    <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($intervention['date']))) ?></span>
                </div>
                <div class="info-item">
                    <label>Statut :</label>
                    <span>
                        <?php if ($intervention['en_cours'] == 1): ?>
                            <span class="status en-cours">En cours</span>
                        <?php else: ?>
                            <span class="status cloturee">Clôturée</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Technicien :</label>
                    <span><?= htmlspecialchars($_SESSION['username'] ?? 'Non défini') ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3>Description de l'intervention</h3>
            <div class="description">
                <?= htmlspecialchars($intervention['info']) ?>
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <h4>Signature du technicien</h4>
                <div style="border-bottom: 1px solid #ccc; margin-top: 50px;"></div>
            </div>
            <div class="signature-box">
                <h4>Signature du client</h4>
                <div style="border-bottom: 1px solid #ccc; margin-top: 50px;"></div>
            </div>
        </div>
        
        <div class="footer">
            <p>TechSuivi - Fiche d'intervention générée le <?= date('d/m/Y à H:i') ?></p>
        </div>
    <?php endif; ?>
</body>
</html>