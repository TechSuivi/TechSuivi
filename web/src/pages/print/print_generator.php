<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure la navigation des paramètres
require_once __DIR__ . '/../../components/settings_navigation.php';
?>

<div class="page-header">
    <h1 class="m-0 text-dark">📄 Générateur de Feuilles Imprimables</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-30 mt-20">
    <div class="card bg-white border border-border p-20 shadow-sm rounded-lg">
        <h2 class="mt-0 text-lg text-primary border-b-2 border-primary pb-10 mb-20">Éditeur de Message</h2>
        
        <form id="printForm">
            <!-- Zone de saisie du texte -->
            <div class="mb-20">
                <label for="message_text" class="block mb-8 font-bold text-dark">Message à imprimer :</label>
                <textarea id="message_text" name="message_text" rows="6" class="w-full p-12 border border-border rounded text-sm bg-input text-dark focus:border-primary focus:outline-none transition-colors" placeholder="Saisissez votre message ici...
Exemple : Fermeture exceptionnelle Samedi 19 Juillet"></textarea>
            </div>
            
            <!-- Options de formatage -->
            <div class="mb-25">
                <h3 class="mb-15 text-dark text-base">Options de Formatage</h3>
                
                <div class="grid grid-cols-2 gap-15 mb-15">
                    <div>
                        <label for="font_size" class="block text-xs mb-5 text-dark">Taille de police :</label>
                        <select id="font_size" name="font_size" class="w-full p-8 border border-border rounded bg-input text-dark text-xs h-36">
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
                    
                    <div>
                        <label for="text_color" class="block text-xs mb-5 text-dark">Couleur du texte :</label>
                        <select id="text_color" name="text_color" class="w-full p-8 border border-border rounded bg-input text-dark text-xs h-36">
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
                
                <div class="grid grid-cols-2 gap-15">
                    <div>
                        <label for="text_align" class="block text-xs mb-5 text-dark">Alignement :</label>
                        <select id="text_align" name="text_align" class="w-full p-8 border border-border rounded bg-input text-dark text-xs h-36">
                            <option value="left">Gauche</option>
                            <option value="center" selected>Centre</option>
                            <option value="right">Droite</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="text_style" class="block text-xs mb-5 text-dark">Style :</label>
                        <select id="text_style" name="text_style" class="w-full p-8 border border-border rounded bg-input text-dark text-xs h-36">
                            <option value="normal">Normal</option>
                            <option value="bold" selected>Gras</option>
                            <option value="italic">Italique</option>
                            <option value="bold-italic">Gras + Italique</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="flex justify-center gap-15">
                <button type="button" id="preview_print_btn" class="btn btn-primary font-bold py-12 px-24 rounded-md shadow-sm hover:shadow-md transition-all">🖨️ Aperçu et Imprimer</button>
            </div>
        </form>
    </div>
    
    <div class="card bg-white border border-border p-20 shadow-sm rounded-lg">
        <h2 class="mt-0 text-lg text-primary border-b-2 border-primary pb-10 mb-20">Prévisualisation en Temps Réel</h2>
        <div class="border-2 border-border rounded bg-light overflow-auto p-20 min-h-500">
            <iframe id="preview_iframe" class="block mx-auto bg-white border border-gray-300 shadow-sm" style="width: 1123px; height: 794px;"></iframe>
        </div>
        <p class="text-center text-xs text-muted mt-10 italic">
            📏 Format A4 Paysage (297mm × 210mm)<br>
            <small class="block mt-5 text-xs text-success font-bold">✅ Aperçu exact - Utilisez Ctrl+P dans l'aperçu pour imprimer</small>
        </p>
    </div>
</div>

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
            return '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:Arial;color:#999;">Votre message apparaîtra ici...</body></html>';
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