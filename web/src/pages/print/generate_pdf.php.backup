<?php
// Vérifier que la requête est bien un POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Accès non autorisé.');
}

// Récupérer et valider les données du formulaire
$message_text = trim($_POST['message_text'] ?? '');
$font_size = intval($_POST['font_size'] ?? 36);
$text_color = $_POST['text_color'] ?? '#000000';
$text_align = $_POST['text_align'] ?? 'center';
$text_style = $_POST['text_style'] ?? 'bold';

// Validation des données
if (empty($message_text)) {
    die('Erreur : Le message ne peut pas être vide.');
}

if (strlen($message_text) > 500) {
    die('Erreur : Le message est trop long (maximum 500 caractères).');
}

// Valider la taille de police
$allowed_sizes = [12, 14, 16, 18, 24, 36, 48, 72];
if (!in_array($font_size, $allowed_sizes)) {
    $font_size = 36;
}

// Valider la couleur (format hexadécimal)
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $text_color)) {
    $text_color = '#000000';
}

// Valider l'alignement
$allowed_aligns = ['left', 'center', 'right'];
if (!in_array($text_align, $allowed_aligns)) {
    $text_align = 'center';
}

// Valider le style
$allowed_styles = ['normal', 'bold', 'italic', 'bold-italic'];
if (!in_array($text_style, $allowed_styles)) {
    $text_style = 'bold';
}

// Créer le PDF avec une approche simple utilisant HTML et CSS
// Nous allons générer un PDF en utilisant la bibliothèque DomPDF ou une approche HTML/CSS simple

// Pour cette implémentation, nous utiliserons une approche HTML/CSS avec print media queries
// qui peut être convertie en PDF par le navigateur

// Échapper le texte pour éviter les injections
$safe_message = htmlspecialchars($message_text, ENT_QUOTES, 'UTF-8');

// Convertir la couleur hex en RGB pour certaines utilisations
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

$rgb = hexToRgb($text_color);

// Déterminer les styles CSS
$font_weight = 'normal';
$font_style_css = 'normal';

switch ($text_style) {
    case 'bold':
        $font_weight = 'bold';
        break;
    case 'italic':
        $font_style_css = 'italic';
        break;
    case 'bold-italic':
        $font_weight = 'bold';
        $font_style_css = 'italic';
        break;
}

// Générer le nom du fichier
$filename = 'feuille_' . date('Y-m-d_H-i-s') . '.html';

// Définir les en-têtes pour le téléchargement
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="' . $filename . '"');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feuille Imprimable - <?= date('d/m/Y H:i') ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20mm;
            /* Supprimer l'URL et la pagination */
            @top-left { content: ""; }
            @top-center { content: ""; }
            @top-right { content: ""; }
            @bottom-left { content: ""; }
            @bottom-center { content: ""; }
            @bottom-right { content: ""; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background-color: white;
            color: black;
            width: 100%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20mm;
        }
        
        .message-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: <?= $text_align ?>;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        
        .message-text {
            font-size: <?= $font_size ?>px;
            color: <?= $text_color ?>;
            font-weight: <?= $font_weight ?>;
            font-style: <?= $font_style_css ?>;
            line-height: 1.2;
            max-width: 100%;
            word-break: break-word;
        }
        
        /* Styles pour l'impression */
        @media print {
            body {
                background-color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .message-text {
                color: <?= $text_color ?> !important;
            }
            
            /* Masquer complètement les en-têtes et pieds de page du navigateur */
            @page {
                margin: 20mm;
                size: A4 landscape;
                /* Supprimer tous les en-têtes et pieds de page */
                @top-left-corner { content: ""; }
                @top-left { content: ""; }
                @top-center { content: ""; }
                @top-right { content: ""; }
                @top-right-corner { content: ""; }
                @bottom-left-corner { content: ""; }
                @bottom-left { content: ""; }
                @bottom-center { content: ""; }
                @bottom-right { content: ""; }
                @bottom-right-corner { content: ""; }
                @left-top { content: ""; }
                @left-middle { content: ""; }
                @left-bottom { content: ""; }
                @right-top { content: ""; }
                @right-middle { content: ""; }
                @right-bottom { content: ""; }
            }
            
            /* Masquer les éléments de navigation du navigateur */
            html {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        /* Styles pour l'écran (prévisualisation) */
        @media screen {
            body {
                background-color: #f5f5f5;
                padding: 40px;
            }
            
            .message-container {
                background-color: white;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                border: 1px solid #ddd;
                min-height: 210mm;
                width: 297mm;
                max-width: 90vw;
                margin: 0 auto;
            }
            
            .print-info {
                position: fixed;
                top: 10px;
                right: 10px;
                background-color: #2A4F9C;
                color: white;
                padding: 10px 15px;
                border-radius: 5px;
                font-size: 14px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                z-index: 1000;
            }
            
            .print-controls {
                position: fixed;
                top: 10px;
                left: 10px;
                display: flex;
                gap: 10px;
                z-index: 1000;
            }
            
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                text-decoration: none;
                display: inline-block;
                transition: all 0.3s ease;
            }
            
            .btn-print {
                background-color: #28a745;
                color: white;
            }
            
            .btn-print:hover {
                background-color: #218838;
            }
            
            .btn-back {
                background-color: #6c757d;
                color: white;
            }
            
            .btn-back:hover {
                background-color: #5a6268;
            }
        }
        
        /* Masquer les contrôles lors de l'impression */
        @media print {
            .print-info,
            .print-controls {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-info">
        📄 Format A4 Paysage - Prêt à imprimer
    </div>
    
    <div class="print-controls">
        <button onclick="window.print()" class="btn btn-print">🖨️ Imprimer</button>
        <button onclick="window.close()" class="btn btn-back">← Retour</button>
    </div>
    
    <div class="message-container">
        <div class="message-text">
            <?= nl2br($safe_message) ?>
        </div>
    </div>
    
    <script>
        // Auto-focus sur l'impression si demandé
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des raccourcis clavier
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                if (e.key === 'Escape') {
                    window.close();
                }
            });
            
            // Message d'information
            console.log('Feuille imprimable générée le <?= date('d/m/Y à H:i:s') ?>');
            console.log('Paramètres: Taille <?= $font_size ?>px, Couleur <?= $text_color ?>, Alignement <?= $text_align ?>, Style <?= $text_style ?>');
        });
        
        // Fonction pour télécharger en PDF
        function downloadPDF() {
            alert('Pour sauvegarder en PDF, utilisez Ctrl+P puis "Enregistrer au format PDF" dans votre navigateur.');
        }
    </script>
</body>
</html>