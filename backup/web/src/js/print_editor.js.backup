/**
 * Script pour le générateur de feuilles imprimables
 * Gestion de la prévisualisation en temps réel et des interactions
 */

class PrintEditor {
    constructor() {
        this.elements = {
            messageText: document.getElementById('message_text'),
            fontSize: document.getElementById('font_size'),
            textColor: document.getElementById('text_color'),
            textAlign: document.getElementById('text_align'),
            textStyle: document.getElementById('text_style'),
            previewContent: document.getElementById('preview_content'),
            previewBtn: document.getElementById('preview_btn'),
            printForm: document.getElementById('printForm')
        };
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updatePreview();
        this.setupFormValidation();
        this.setupKeyboardShortcuts();
    }
    
    bindEvents() {
        // Événements pour la mise à jour en temps réel
        this.elements.messageText.addEventListener('input', () => this.updatePreview());
        this.elements.fontSize.addEventListener('change', () => this.updatePreview());
        this.elements.textColor.addEventListener('change', () => this.updatePreview());
        this.elements.textAlign.addEventListener('change', () => this.updatePreview());
        this.elements.textStyle.addEventListener('change', () => this.updatePreview());
        
        // Bouton de prévisualisation
        this.elements.previewBtn.addEventListener('click', () => this.handlePreviewClick());
        
        // Auto-resize du textarea
        this.elements.messageText.addEventListener('input', () => this.autoResizeTextarea());
        
        // Recalculer la taille lors du redimensionnement de la fenêtre
        window.addEventListener('resize', () => {
            // Debounce pour éviter trop d'appels
            clearTimeout(this.resizeTimeout);
            this.resizeTimeout = setTimeout(() => this.updatePreview(), 250);
        });
    }
    
    updatePreview() {
        const text = this.elements.messageText.value.trim() || 'Votre message apparaîtra ici...';
        const fontSize = parseInt(this.elements.fontSize.value);
        const color = this.elements.textColor.value;
        const align = this.elements.textAlign.value;
        const style = this.elements.textStyle.value;
        
        // Calculs basés sur les dimensions réelles du PDF
        // A4 paysage : 297mm × 210mm
        // Marges PDF : 20mm de chaque côté
        // Zone utile PDF : 257mm × 170mm
        // En pixels à 96 DPI : 257mm = 971px, 170mm = 642px
        
        const pdfUsableWidth = 971; // 257mm en pixels
        const pdfUsableHeight = 642; // 170mm en pixels
        
        // Dimensions de la zone de prévisualisation selon l'écran
        let previewContainerWidth, previewContainerHeight, scaleFactor;
        const screenWidth = window.innerWidth;
        
        if (screenWidth <= 480) {
            // Mobile : 280px total, padding 19px → zone utile : 242px × 160px
            previewContainerWidth = 242;
            previewContainerHeight = 160;
        } else if (screenWidth <= 768) {
            // Tablette : 300px total, padding 20px → zone utile : 260px × 172px
            previewContainerWidth = 260;
            previewContainerHeight = 172;
        } else if (screenWidth <= 1024) {
            // Tablette large : 350px total, padding 23px → zone utile : 304px × 201px
            previewContainerWidth = 304;
            previewContainerHeight = 201;
        } else {
            // Desktop : 420px total, padding 28px → zone utile : 364px × 241px
            previewContainerWidth = 364;
            previewContainerHeight = 241;
        }
        
        // Calculer le facteur d'échelle basé sur la largeur (plus critique pour les retours à la ligne)
        scaleFactor = previewContainerWidth / pdfUsableWidth;
        const previewFontSize = Math.max(Math.round(fontSize * scaleFactor), 8);
        
        let fontWeight = 'normal';
        let fontStyle = 'normal';
        
        switch (style) {
            case 'bold':
                fontWeight = 'bold';
                break;
            case 'italic':
                fontStyle = 'italic';
                break;
            case 'bold-italic':
                fontWeight = 'bold';
                fontStyle = 'italic';
                break;
        }
        
        // Appliquer les styles avec la largeur exacte proportionnelle
        Object.assign(this.elements.previewContent.style, {
            fontSize: previewFontSize + 'px',
            color: color,
            textAlign: align,
            fontWeight: fontWeight,
            fontStyle: fontStyle,
            fontFamily: "'Arial', 'Helvetica', sans-serif",
            lineHeight: '1.2',
            width: previewContainerWidth + 'px', // Largeur exacte proportionnelle
            maxWidth: previewContainerWidth + 'px',
            wordBreak: 'break-word',
            wordWrap: 'break-word',
            overflowWrap: 'break-word',
            hyphens: 'auto',
            boxSizing: 'border-box'
        });
        
        // Mettre à jour le contenu avec les retours à la ligne (comme dans le PDF)
        this.elements.previewContent.innerHTML = text.replace(/\n/g, '<br>');
        
        // Ajouter une animation de mise à jour
        this.elements.previewContent.style.opacity = '0.7';
        setTimeout(() => {
            this.elements.previewContent.style.opacity = '1';
        }, 100);
        
        // Simuler exactement le comportement de retour à la ligne du PDF
        this.simulateTextWrapping(text, previewFontSize, previewContainerWidth, fontWeight, fontStyle);
        
        // Debug : afficher les calculs dans la console
        console.log(`PDF: ${pdfUsableWidth}px × ${pdfUsableHeight}px`);
        console.log(`Preview: ${previewContainerWidth}px × ${previewContainerHeight}px`);
        console.log(`Facteur d'échelle: ${scaleFactor.toFixed(3)}`);
        console.log(`Taille originale: ${fontSize}px → Preview: ${previewFontSize}px`);
    }
    
    simulateTextWrapping(text, fontSize, containerWidth, fontWeight, fontStyle) {
        // Créer un élément temporaire pour mesurer le texte exactement comme il apparaîtra
        const tempElement = document.createElement('div');
        tempElement.style.cssText = `
            position: absolute;
            visibility: hidden;
            white-space: nowrap;
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: ${fontSize}px;
            font-weight: ${fontWeight};
            font-style: ${fontStyle};
            line-height: 1.2;
        `;
        document.body.appendChild(tempElement);
        
        // Traiter d'abord les retours à la ligne manuels
        const paragraphs = text.split('\n');
        let allLines = [];
        
        for (let paragraph of paragraphs) {
            if (paragraph.trim() === '') {
                // Ligne vide
                allLines.push('');
                continue;
            }
            
            // Diviser le paragraphe en mots et simuler le retour à la ligne automatique
            const words = paragraph.split(/\s+/).filter(word => word.length > 0);
            let currentLine = '';
            
            for (let word of words) {
                const testLine = currentLine ? currentLine + ' ' + word : word;
                tempElement.textContent = testLine;
                
                if (tempElement.offsetWidth > containerWidth && currentLine) {
                    // Le mot ne rentre pas, commencer une nouvelle ligne
                    allLines.push(currentLine);
                    currentLine = word;
                } else {
                    currentLine = testLine;
                }
            }
            
            if (currentLine) {
                allLines.push(currentLine);
            }
        }
        
        // Nettoyer l'élément temporaire
        document.body.removeChild(tempElement);
        
        // Mettre à jour le contenu avec les retours à la ligne calculés
        const wrappedText = allLines.join('<br>');
        this.elements.previewContent.innerHTML = wrappedText;
        
        console.log(`Simulation de retour à la ligne: ${allLines.length} lignes`);
        console.log(`Lignes: ${allLines.join(' | ')}`);
    }
    
    handlePreviewClick() {
        this.updatePreview();
        
        // Animation du bouton
        this.elements.previewBtn.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.elements.previewBtn.style.transform = 'scale(1)';
        }, 150);
        
        // Scroll vers la prévisualisation sur mobile
        if (window.innerWidth <= 1024) {
            document.querySelector('.preview-panel').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Feedback visuel
        this.showNotification('Prévisualisation mise à jour !', 'success');
    }
    
    autoResizeTextarea() {
        const textarea = this.elements.messageText;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
    }
    
    setupFormValidation() {
        this.elements.printForm.addEventListener('submit', (e) => {
            const text = this.elements.messageText.value.trim();
            
            if (!text) {
                e.preventDefault();
                this.showNotification('Veuillez saisir un message avant de générer le PDF.', 'error');
                this.elements.messageText.focus();
                return false;
            }
            
            if (text.length > 500) {
                e.preventDefault();
                this.showNotification(`Le message est trop long (maximum 500 caractères). Actuel : ${text.length} caractères.`, 'error');
                this.elements.messageText.focus();
                return false;
            }
            
            // Feedback de génération
            this.showNotification('Génération du PDF en cours...', 'info');
            
            // Désactiver temporairement le bouton pour éviter les doubles clics
            const submitBtn = this.elements.printForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Génération...';
            
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '📄 Générer PDF';
            }, 3000);
        });
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl + Enter pour prévisualiser
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                this.handlePreviewClick();
            }
            
            // Ctrl + Shift + Enter pour générer le PDF
            if (e.ctrlKey && e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                this.elements.printForm.dispatchEvent(new Event('submit'));
            }
            
            // Échap pour effacer le message
            if (e.key === 'Escape' && e.target === this.elements.messageText) {
                if (confirm('Voulez-vous effacer le message ?')) {
                    this.elements.messageText.value = '';
                    this.updatePreview();
                }
            }
        });
    }
    
    showNotification(message, type = 'info') {
        // Créer la notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Styles de base
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 20px',
            borderRadius: '6px',
            color: 'white',
            fontWeight: 'bold',
            fontSize: '14px',
            zIndex: '10000',
            opacity: '0',
            transform: 'translateX(100%)',
            transition: 'all 0.3s ease',
            maxWidth: '300px',
            wordWrap: 'break-word'
        });
        
        // Couleurs selon le type
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            info: '#17a2b8',
            warning: '#ffc107'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Ajouter au DOM
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Suppression automatique
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    
    // Méthodes utilitaires
    getFormData() {
        return {
            message_text: this.elements.messageText.value,
            font_size: this.elements.fontSize.value,
            text_color: this.elements.textColor.value,
            text_align: this.elements.textAlign.value,
            text_style: this.elements.textStyle.value
        };
    }
    
    setFormData(data) {
        Object.keys(data).forEach(key => {
            if (this.elements[key.replace('_', '')]) {
                this.elements[key.replace('_', '')].value = data[key];
            }
        });
        this.updatePreview();
    }
    
    resetForm() {
        this.elements.messageText.value = '';
        this.elements.fontSize.value = '36';
        this.elements.textColor.value = '#000000';
        this.elements.textAlign.value = 'center';
        this.elements.textStyle.value = 'bold';
        this.updatePreview();
    }
}

// Initialisation quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier que nous sommes sur la bonne page
    if (document.getElementById('message_text')) {
        window.printEditor = new PrintEditor();
        
        // Ajouter des exemples de messages
        const examples = [
            "Fermeture exceptionnelle\nSamedi 19 Juillet",
            "PROMOTION SPÉCIALE\n-50% sur tous les services\nJusqu'au 31 décembre",
            "MAINTENANCE PROGRAMMÉE\nSystème indisponible\nDimanche 2h-6h",
            "NOUVEAU HORAIRE\nOuvert 7j/7\n9h-19h non-stop"
        ];
        
        // Les exemples sont disponibles mais sans bouton visible pour simplifier l'interface
        // Ils peuvent être ajoutés manuellement si nécessaire
        
        console.log('🖨️ Générateur de feuilles imprimables initialisé');
        console.log('💡 Raccourcis clavier:');
        console.log('   Ctrl + Enter: Prévisualiser');
        console.log('   Ctrl + Shift + Enter: Générer PDF');
        console.log('   Échap (dans le textarea): Effacer le message');
    }
});