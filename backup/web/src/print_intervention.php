<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$intervention_id = trim($_GET['id'] ?? '');
$intervention = null;
$errorMessage = '';

// Configuration de la base de données
// Inclure la configuration centralisée de la base de données
require_once 'config/database.php';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    $errorMessage = 'Erreur de connexion à la base de données.';
}

// Récupération des configurations
$config = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configuration WHERE category = 'intervention_sheet'");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $config[$row['config_key']] = $row['config_value'];
        }
    } catch (PDOException $e) {
        // Utiliser les valeurs par défaut si erreur
    }
}

// Valeurs par défaut si pas de configuration
$company_name = $config['company_name'] ?? 'QLE INFORMATIQUE';
$intervention_tarifs = $config['intervention_tarifs'] ?? 'TARIFS : FRAIS DE PRISE EN CHARGE = 30 €
Petit Dépannage : 10 € TTC
Test Matériel : 30 € TTC
Forfait 0,5 heure : 45 € TTC
Forfait 1 heure : 70 € TTC
Forfait 2 heures : 140 € TTC
Forfait 1 journée : 420 € TTC
Forfait Réinstallation : 70 € TTC
Forfait récupération de données : 30 € TTC';

$intervention_cgv = $config['intervention_cgv'] ?? 'CONDITIONS GÉNÉRALES DE VENTE
1. QLE Informatique ne pourra être tenu responsable de la perte de données lors de l\'intervention.

2. Le client s\'engage à effectuer une sauvegarde de ses données avant l\'intervention.

3. Le paiement est dû à réception de la facture.

4. Le matériel non récupéré dans un délai de 3 mois sera considéré comme abandonné.

5. Les tarifs indiqués sont TTC et peuvent être modifiés sans préavis.

6. La signature de cette fiche implique l\'acceptation de ces conditions.';

$intervention_verifications = $config['intervention_verifications'] ?? 'SEATOOLS
MEMTEST
ADW/ROGUE/MBAM/ESET
MAJ WINDOWS
NETTOYAGE OS
DEFRAG
INSTALL ANTIVIRUS';

if (empty($intervention_id)) {
    $errorMessage = 'ID d\'intervention manquant.';
} elseif ($pdo) {
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
        $errorMessage = 'Erreur lors de la récupération de l\'intervention.';
    }
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
            color: #000;
            background: white;
            line-height: 1.3;
            font-size: 12px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        
        .header-left h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .header-left .date {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .header-right h2 {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
            text-align: right;
        }
        
        .header-right .intervention-id {
            margin: 5px 0;
            font-size: 12px;
            text-align: right;
        }
        
        .client-info {
            margin-bottom: 15px;
        }
        
        .client-row {
            margin-bottom: 3px;
        }
        
        .client-row label {
            display: inline-block;
            width: 80px;
            font-weight: bold;
        }
        
        .work-section {
            margin-bottom: 15px;
        }
        
        .work-section h3 {
            margin: 10px 0 5px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .work-area {
            border: 2px solid #2A4F9C;
            border-radius: 8px;
            min-height: 120px;
            padding: 10px;
            margin-bottom: 15px;
            white-space: pre-wrap;
            background-color: #f8f9fa;
        }
        
        .work-area.large {
            min-height: 150px;
        }
        
        .work-area.extra-large {
            min-height: 200px;
        }
        
        .bottom-section {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .tarifs {
            flex: 1;
            border: 2px solid #2A4F9C;
            border-radius: 8px;
            padding: 10px;
            margin-right: 10px;
            background-color: #f0f4ff;
        }
        
        .tarifs h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .tarifs-list {
            font-size: 10px;
            line-height: 1.1;
        }
        
        .conditions {
            flex: 1;
            border: 2px solid #2A4F9C;
            border-radius: 8px;
            padding: 10px;
            margin-left: 10px;
            background-color: #f0f4ff;
            width: 50%;
        }
        
        .conditions h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .conditions-list {
            font-size: 8px;
            line-height: 1.1;
        }
        
        .bottom-row {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .signature {
            flex: 1;
            width: 50%;
        }
        
        .signature h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .signature-line {
            border-bottom: 2px solid #2A4F9C;
            height: 60px;
            width: 200px;
            border-radius: 4px;
        }
        
        .verifications {
            flex: 1;
            border: 2px solid #2A4F9C;
            border-radius: 8px;
            padding: 10px;
            background-color: #f0f4ff;
            width: 50%;
        }
        
        .verifications h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .verif-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        
        .verif-table td {
            border: 1px solid #2A4F9C;
            padding: 2px 3px;
            text-align: center;
        }
        
        .verif-table td:first-child {
            text-align: left;
            width: 60%;
            font-size: 8px;
        }
        
        @media print {
            /* Masquer les en-têtes et pieds de page du navigateur */
            @page {
                margin: 0.5in 0.5in 0.5in 0.5in;
                size: A4;
                @top-left { content: ""; }
                @top-center { content: ""; }
                @top-right { content: ""; }
                @bottom-left { content: ""; }
                @bottom-center { content: ""; }
                @bottom-right { content: ""; }
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            /* Styles pour masquer complètement les éléments du navigateur */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
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
            z-index: 1000;
        }
        
        .print-button:hover {
            background-color: #1e3a7a;
        }
        
        .error-message {
            color: red;
            text-align: center;
            padding: 20px;
            border: 1px solid red;
            border-radius: 5px;
            background-color: #ffe6e6;
        }
    </style>
</head>
<body>
    <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            <h2>Erreur</h2>
            <p><?= htmlspecialchars($errorMessage) ?></p>
            <p><a href="index.php?page=interventions_list">Retour à la liste des interventions</a></p>
        </div>
    <?php else: ?>
        <button class="print-button no-print" onclick="window.print()">Imprimer</button>
        
        <div class="header">
            <div class="header-left">
                <h1><?= htmlspecialchars($company_name) ?></h1>
                <div class="date">DATE : <?= date('d/m/Y') ?></div>
            </div>
            <div class="header-right">
                <h2>FICHE DE PRISE EN CHARGE</h2>
                <div class="intervention-id">Intervention N° : <?= htmlspecialchars($intervention['id']) ?></div>
            </div>
        </div>
        
        <div class="client-info" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div class="client-row">
                    <label>Nom :</label> <?= htmlspecialchars($intervention['nom']) ?>
                    <span style="margin-left: 50px;"><label>Prénom :</label> <?= htmlspecialchars($intervention['prenom']) ?></span>
                </div>
                <div class="client-row">
                    <label>Adresse :</label> <?= htmlspecialchars($intervention['adresse1']) ?>
                    <?php if (!empty($intervention['adresse2'])): ?>, <?= htmlspecialchars($intervention['adresse2']) ?><?php endif; ?>
                </div>
                <div class="client-row">
                    <label>Code postal :</label> <?= htmlspecialchars($intervention['cp']) ?>
                    <span style="margin-left: 30px;"><label>Ville :</label> <?= htmlspecialchars($intervention['ville']) ?></span>
                </div>
                <div class="client-row">
                    <label>Téléphone :</label> <?= htmlspecialchars($intervention['telephone']) ?>
                    <?php if (!empty($intervention['portable'])): ?> / <?= htmlspecialchars($intervention['portable']) ?><?php endif; ?>
                </div>
            </div>
            <div style="border: 2px solid #2A4F9C; width: 100px; height: 100px; padding: 5px; background: white;">
                <!-- QR Code PWA -->
                <?php
                // Détection de l'hôte pour le lien PWA
                $host = $_SERVER['HTTP_HOST'];
                $pwaUrl = "http://{$host}/pwa/?intervention_id=" . $intervention['id'];
                // Utilisation de l'API QR Server (rapide et efficace si internet)
                // Sinon, il faudrait une lib JS locale. 
                // On suppose l'accès internet pour le client qui imprime.
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($pwaUrl);
                ?>
                <img src="<?= $qrUrl ?>" alt="QR PWA" style="width: 100%; height: 100%;">
            </div>
        </div>
        
        <div class="work-section">
            <h3>Travail à effectuer :</h3>
            <div class="work-area large"><?= htmlspecialchars($intervention['info']) ?></div>
            
            <h3>Travail effectué :</h3>
            <div class="work-area extra-large"></div>
        </div>
        
        <div class="bottom-section">
            <div class="tarifs">
                <div class="tarifs-list">
                    <?= nl2br(htmlspecialchars($intervention_tarifs)) ?>
                </div>
            </div>
            
            <div class="conditions">
                <div class="conditions-list">
                    <?= nl2br(htmlspecialchars($intervention_cgv)) ?>
                </div>
            </div>
        </div>
        
        <div class="bottom-row">
            <div class="signature">
                <h3>Signature client :</h3>
                <div class="signature-line"></div>
            </div>
            
            <div class="verifications">
                <h3>Vérifications effectuées :</h3>
                <table class="verif-table">
                    <?php
                    $verifications = explode("\n", $intervention_verifications);
                    foreach ($verifications as $verif):
                        $verif = trim($verif);
                        if (!empty($verif)):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($verif) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Auto-print après chargement de la page
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>