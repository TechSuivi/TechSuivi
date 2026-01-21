<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<h1>📄 Générateur de Feuilles Imprimables</h1>

<div class="print-generator-container">
    <div class="editor-panel">
        <h2>Éditeur de Message</h2>
        
        <form id="printForm">
            <!-- Zone de saisie du texte -->
            <div class="form-group">
                <label for="message_text">Message à imprimer :</label>
                <textarea id="message_text" name="message_text" rows="6" placeholder="Saisissez votre message ici...
Exemple : Fermeture exceptionnelle Samedi 19 Juillet"></textarea>
            </div>
            
            <!-- Options de formatage -->
            <div class="formatting-options">
                <h3>Options de Formatage</h3>
                
                <div class="format-row">
                    <div class="format-group">
                        <label for="font_size">Taille de police :</label>
                        <select id="font_size" name="font_size">
                            <option value="12">12px</option>
                            <option value="14">14px</option>
                            <option value="16">16px</option>
                            <option value="18">18px</option>
                            <option value="24">24px</option>
                            <option value="36" selected>36px</option>
                            <option value="48">48px</option>
                            <option value="72">72px</option>
                        </select>
                    </div>
                    
                    <div class="format-group">
                        <label for="text_color">Couleur du texte :</label>
                        <select id="text_color" name="text_color">
                            <option value="#000000" selected>Noir</option>
                            <option value="#FF0000">Rouge</option>
                            <option value="#0000FF">Bleu</option>
                            <option value="#008000">Vert</option>
                            <option value="#FFA500">Orange</option>
                            <option value="#800080">Violet</option>
                            <option value="#FF1493">Rose</option>
                            <option value="#8B4513">Marron</option>
                            <option value="#808080">Gris</option>
                            <option value="#FFD700">Or</option>
                            <option value="#DC143C">Rouge foncé</option>
                            <option value="#4169E1">Bleu royal</option>
                        </select>
                    </div>
                </div>
                
                <div class="format-row">
                    <div class="format-group">
                        <label for="text_align">Alignement :</label>
                        <select id="text_align" name="text_align">
                            <option value="left">Gauche</option>
                            <option value="center" selected>Centre</option>
                            <option value="right">Droite</option>
                        </select>
                    </div>
                    
                    <div class="format-group">
                        <label for="text_style">Style :</label>
                        <select id="text_style" name="text_style">
                            <option value="normal">Normal</option>
                            <option value="bold" selected>Gras</option>
                            <option value="italic">Italique</option>
                            <option value="bold-italic">Gras + Italique</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="action-buttons">
                <button type="button" id="preview_print_btn" class="btn-generate">🖨️ Aperçu et Imprimer</button>
            </div>
        </form>
    </div>
    
    <div class="preview-panel">
        <h2>Prévisualisation en Temps Réel</h2>
        <div class="preview-container">
            <iframe id="preview_iframe" class="preview-iframe"></iframe>
        </div>
        <p class="preview-note">
            📏 Format A4 Paysage (297mm × 210mm)<br>
            <small class="preview-info">✅ Aperçu exact - Utilisez Ctrl+P dans l'aperçu pour imprimer</small>
        </p>
    </div>
</div>

<style>
.print-generator-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.editor-panel, .preview-panel {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.editor-panel h2, .preview-panel h2 {
    margin-top: 0;
    color: var(--accent-color);
    font-size: 18px;
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: var(--text-color);
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    background-color: var(--bg-color);
    color: var(--text-color);
}

.formatting-options {
    margin-bottom: 25px;
}

.formatting-options h3 {
    margin-bottom: 15px;
    color: var(--text-color);
    font-size: 16px;
}

.format-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.format-group label {
    font-size: 13px;
    margin-bottom: 5px;
}

.format-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: var(--bg-color);
    color: var(--text-color);
    font-size: 13px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn-generate {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: var(--accent-color);
    color: white;
}

.btn-generate:hover {
    background-color: #23428a;
    transform: translateY(-1px);
}

.preview-container {
    border: 2px solid var(--border-color);
    border-radius: 4px;
    background-color: #f8f9fa;
    overflow: auto;
    padding: 20px;
    min-height: 500px;
}

.preview-iframe {
    /* Dimensions exactes A4 paysage : 297mm × 210mm */
    /* À 96 DPI : 297mm = 1123px, 210mm = 794px */
    /* PAS de transform scale - on garde les dimensions réelles */
    width: 1123px;
    height: 794px;
    border: 1px solid #ccc;
    background-color: white;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    display: block;
    margin: 0 auto;
}

.preview-note {
    text-align: center;
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 10px;
    font-style: italic;
}

.preview-info {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    color: #27ae60;
    font-weight: bold;
}

.preview-scroll-hint {
    display: block;
    margin-top: 5px;
    font-size: 10px;
    color: #f39c12;
    font-weight: normal;
}

/* Mode sombre */
body.dark .preview-container {
    background-color: #2c2c2c;
    border-color: #555;
}

body.dark .form-group textarea:focus,
body.dark .format-group select:focus {
    outline: 2px solid var(--accent-color);
    border-color: var(--accent-color);
}

/* Responsive */
@media (max-width: 1024px) {
    .print-generator-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .format-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-generate {
        width: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('printForm');
    const previewPrintBtn = document.getElementById('preview_print_btn');
    const previewIframe = document.getElementById('preview_iframe');
    
    // Fonction pour générer le HTML
    function generateHTML() {
        const messageText = document.getElementById('message_text').value.trim();
        const fontSize = document.getElementById('font_size').value;
        const textColor = document.getElementById('text_color').value;
        const textAlign = document.getElementById('text_align').value;
        const textStyle = document.getElementById('text_style').value;
        
        if (!messageText) {
            return '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:Arial;color:#999;">Votre message apparaîtra ici...</body></html>';
        }
        
        let fontWeight = 'normal';
        let fontStyleCSS = 'normal';
        
        switch (textStyle) {
            case 'bold':
                fontWeight = 'bold';
                break;
            case 'italic':
                fontStyleCSS = 'italic';
                break;
            case 'bold-italic':
                fontWeight = 'bold';
                fontStyleCSS = 'italic';
                break;
        }
        
        const safeMessage = messageText
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
        
        return `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feuille Imprimable - ${new Date().toLocaleDateString('fr-FR')}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20mm;
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
            text-align: ${textAlign};
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        
        .message-text {
            font-size: ${fontSize}px;
            color: ${textColor};
            font-weight: ${fontWeight};
            font-style: ${fontStyleCSS};
            line-height: 1.2;
            max-width: 100%;
            word-break: break-word;
        }
        
        @media print {
            body {
                background-color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .message-text {
                color: ${textColor} !important;
            }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="message-text">
            ${safeMessage}
        </div>
    </div>
</body>
</html>`;
    }
    
    // Fonction pour mettre à jour la prévisualisation
    function updatePreview() {
        const html = generateHTML();
        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        previewIframe.src = url;
        
        previewIframe.onload = function() {
            URL.revokeObjectURL(url);
        };
    }
    
    // Prévisualisation initiale
    updatePreview();
    
    // Mise à jour automatique lors des changements
    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    
    // Bouton aperçu et impression
    previewPrintBtn.addEventListener('click', function() {
        const messageText = document.getElementById('message_text').value.trim();
        
        if (!messageText) {
            alert('Veuillez saisir un message avant d\'imprimer.');
            document.getElementById('message_text').focus();
            return;
        }
        
        if (messageText.length > 500) {
            alert(`Le message est trop long (maximum 500 caractères). Actuel : ${messageText.length} caractères.`);
            document.getElementById('message_text').focus();
            return;
        }
        
        // Ouvrir dans un nouvel onglet
        const html = generateHTML();
        const newWindow = window.open('', '_blank');
        newWindow.document.write(html);
        newWindow.document.close();
        
        // Attendre que la page soit chargée puis ouvrir la boîte d'impression
        newWindow.onload = function() {
            setTimeout(function() {
                newWindow.print();
            }, 250);
        };
    });
    
    console.log('🖨️ Générateur de feuilles imprimables initialisé');
    console.log('✅ Prévisualisation en temps réel activée');
});
</script>