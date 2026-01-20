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
    <link rel="stylesheet" href="css/intervention_print.css">
</head>
<body>
    <?php if (!empty($errorMessage)): ?>
        <div class="error-container">
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
                <div class="signature-separator"></div>
            </div>
            <div class="signature-box">
                <h4>Signature du client</h4>
                <div class="signature-separator"></div>
            </div>
        </div>
        
        <div class="footer">
            <p>TechSuivi - Fiche d'intervention générée le <?= date('d/m/Y à H:i') ?></p>
        </div>
    <?php endif; ?>
</body>
</html>