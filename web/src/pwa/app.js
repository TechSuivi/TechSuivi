// Application PWA TechSuivi Photos
class TechSuiviPhotosApp {
    constructor() {
        this.currentInterventionId = null;
        this.selectedFiles = [];
        // Utiliser un chemin absolu pour éviter les problèmes de chemin relatif
        this.apiBaseUrl = '/api/';

        this.init();
    }

    init() {
        this.registerServiceWorker();
        this.bindEvents();
        this.checkAuth();
    }

    // Enregistrement du Service Worker
    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((registration) => {
                        console.log('SW registered: ', registration);
                    })
                    .catch((registrationError) => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    }

    async checkAuth() {
        try {
            const response = await fetch('../api/check_auth.php');
            const data = await response.json();

            if (data.logged_in) {
                this.showApp();
            } else {
                this.showLogin();
            }
        } catch (e) {
            console.error("Auth check failed", e);
            this.showLogin();
        }
    }

    showLogin() {
        document.getElementById('login-screen').classList.remove('hidden');
        document.getElementById('app-content').classList.add('hidden');
        document.querySelector('.header').style.display = 'block'; // Ensure header is visible
    }

    showApp() {
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('app-content').classList.remove('hidden');
        this.registerServiceWorker(); // Moved here to ensure it runs after login
        this.checkUrlParams();
    }

    // Liaison des événements
    bindEvents() {
        // Login Events
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', () => this.handleLogin());
        }

        // Standard App Events
        document.getElementById('load-intervention').addEventListener('click', () => this.loadIntervention());
        document.getElementById('upload-area').addEventListener('click', () => document.getElementById('photo-input').click());
        document.getElementById('photo-input').addEventListener('change', (e) => this.handleFiles(e.target.files)); // Changed to handleFiles
        document.getElementById('upload-photos').addEventListener('click', () => this.uploadPhotos());

        // Drag and drop handling
        const uploadArea = document.getElementById('upload-area');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files && files.length > 0) {
                // document.getElementById('photo-input').files = files; // This line is problematic for FileList assignment
                this.handleFiles(files); // Directly pass the FileList
            }
        });

        // Entrée dans le champ ID intervention
        document.getElementById('intervention-id').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.loadIntervention();
            }
        });
    }

    async handleLogin() {
        const usernameInput = document.getElementById('login-username');
        const passwordInput = document.getElementById('login-password');
        const errorDiv = document.getElementById('login-error');
        const btn = document.getElementById('login-btn');

        const username = usernameInput.value.trim();
        const password = passwordInput.value.trim();

        if (!username || !password) {
            errorDiv.textContent = 'Veuillez remplir tous les champs';
            return;
        }

        btn.disabled = true;
        errorDiv.textContent = '';

        try {
            const response = await fetch('../api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await response.json();

            if (data.success) {
                this.showApp();
            } else {
                errorDiv.textContent = data.error || 'Erreur de connexion';
            }
        } catch (e) {
            errorDiv.textContent = 'Erreur réseau';
        } finally {
            btn.disabled = false;
        }
    }

    // Vérifier les paramètres URL
    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);

        // Mode Upload Stock (QR Code)
        const supplier = urlParams.get('supplier');
        const order = urlParams.get('order');

        if (supplier && order) {
            this.initSupplierMode(supplier, order);
            return;
        }

        // Mode Intervention standard
        const interventionId = urlParams.get('intervention_id');
        if (interventionId) {
            document.getElementById('intervention-id').value = interventionId;
            this.loadIntervention();
        }
    }

    // Initialiser le mode Upload Fournisseur
    initSupplierMode(supplier, order) {
        // Masquer les éléments standards
        document.querySelector('.header').style.display = 'none';
        document.getElementById('intervention-selector').classList.add('hidden');
        document.getElementById('status-messages').innerHTML = '';

        // Afficher le mode fournisseur
        const section = document.getElementById('supplier-upload-mode');
        section.classList.remove('hidden');

        document.getElementById('supplier-info').innerHTML = `
            <div><strong>Fournisseur:</strong> ${this.escapeHtml(supplier)}</div>
            <div><strong>N° Commande:</strong> ${this.escapeHtml(order)}</div>
        `;

        // Bind events
        const input = document.getElementById('supplier-camera-input');
        const area = document.getElementById('supplier-upload-area');
        const previewDiv = document.getElementById('supplier-preview-container');
        const previewImg = document.getElementById('supplier-preview-img');
        const sendBtn = document.getElementById('supplier-send-btn');
        const cancelBtn = document.getElementById('supplier-cancel-btn');
        const statusDiv = document.getElementById('supplier-status');

        area.addEventListener('click', () => input.click());

        input.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                reader.onload = (ev) => {
                    previewImg.src = ev.target.result;
                    previewDiv.classList.remove('hidden');
                    area.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        cancelBtn.addEventListener('click', () => {
            input.value = '';
            previewDiv.classList.add('hidden');
            area.classList.remove('hidden');
            statusDiv.innerHTML = '';
        });

        sendBtn.addEventListener('click', async () => {
            if (!input.files || input.files.length === 0) return;

            const file = input.files[0];
            statusDiv.innerHTML = '<span style="color: orange;">⏳ Envoi en cours...</span>';
            sendBtn.disabled = true;
            cancelBtn.disabled = true;

            const formData = new FormData();
            formData.append('file', file); // Note: API expects 'file' not 'photo'
            formData.append('fournisseur', supplier);
            formData.append('numero_commande', order);

            try {
                const response = await fetch('../api/upload_stock_document.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    statusDiv.innerHTML = '<span style="color: green; font-size: 1.2em;">✅ Document enregistré !</span>';
                    // Reset après 2 secondes
                    setTimeout(() => {
                        input.value = '';
                        previewDiv.classList.add('hidden');
                        area.classList.remove('hidden');
                        statusDiv.innerHTML = '';
                        sendBtn.disabled = false;
                        cancelBtn.disabled = false;
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<span style="color: red;">❌ Erreur: ' + data.error + '</span>';
                    sendBtn.disabled = false;
                    cancelBtn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = '<span style="color: red;">❌ Erreur réseau</span>';
                sendBtn.disabled = false;
                cancelBtn.disabled = false;
            }
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Charger une intervention
    async loadIntervention() {
        const interventionId = document.getElementById('intervention-id').value.trim();
        if (!interventionId) {
            this.showMessage('Veuillez saisir un ID d\'intervention', 'error');
            return;
        }

        this.showLoading(true);

        try {
            // Simuler la récupération des infos d'intervention (à adapter selon votre API)
            const response = await fetch(`${this.apiBaseUrl}photos.php?intervention_id=${encodeURIComponent(interventionId)}`);
            const data = await response.json();

            if (data.success) {
                this.currentInterventionId = interventionId;
                this.showInterventionInfo(interventionId);
                this.showExistingPhotos(data.data);
                this.showUploadSection();
                this.showMessage('Intervention chargée avec succès', 'success');
            } else {
                this.showMessage('Intervention non trouvée ou erreur: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showMessage('Erreur lors du chargement de l\'intervention', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    // Afficher les informations de l'intervention
    showInterventionInfo(interventionId, isReadOnly = false) {
        const infoSection = document.getElementById('intervention-info');
        const detailsDiv = document.getElementById('intervention-details');

        detailsDiv.innerHTML = `
            <p><strong>ID:</strong> ${interventionId}</p>
            <p><strong>Statut:</strong> ${isReadOnly ? 'Consultation des photos uniquement' : 'Prêt pour l\'ajout de photos'}</p>
        `;

        infoSection.classList.remove('hidden');

        // Masquer la section d'upload si en lecture seule
        if (isReadOnly) {
            document.getElementById('photo-upload-section').style.display = 'none';
        }
    }

    // Afficher les photos existantes
    showExistingPhotos(photos) {
        const existingSection = document.getElementById('existing-photos-section');
        const existingDiv = document.getElementById('existing-photos');

        if (photos && photos.length > 0) {
            existingDiv.innerHTML = '';
            photos.forEach(photo => {
                const photoDiv = document.createElement('div');
                photoDiv.className = 'photo-item';
                photoDiv.innerHTML = `
                    <img src="${photo.thumbnail_url}" alt="${photo.original_filename}" onclick="this.parentElement.parentElement.parentElement.openPhotoModal('${photo.url}', '${photo.original_filename}')">
                `;
                existingDiv.appendChild(photoDiv);
            });
            existingSection.classList.remove('hidden');
        } else {
            existingSection.classList.add('hidden');
        }
    }

    // Afficher la section d'upload
    showUploadSection() {
        document.getElementById('photo-upload-section').classList.remove('hidden');
    }

    // Gérer les fichiers sélectionnés
    async handleFiles(files) {
        this.selectedFiles = [];
        const maxSize = 5 * 1024 * 1024; // 5MB limite pour mobile

        for (let file of files) {
            if (file.type.startsWith('image/')) {
                // Si le fichier est trop gros, le compresser
                if (file.size > maxSize) {
                    console.log(`🔧 Compression nécessaire pour ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
                    try {
                        const compressedFile = await this.compressImage(file, maxSize);
                        this.selectedFiles.push(compressedFile);
                        console.log(`✅ ${file.name} compressé: ${(compressedFile.size / 1024 / 1024).toFixed(2)}MB`);
                    } catch (error) {
                        console.error(`❌ Erreur compression ${file.name}:`, error);
                        this.showMessage(`Erreur lors de la compression de ${file.name}`, 'error');
                    }
                } else {
                    this.selectedFiles.push(file);
                }
            }
        }

        this.displayFilePreview();

        const uploadBtn = document.getElementById('upload-photos');
        uploadBtn.disabled = this.selectedFiles.length === 0;
    }

    // Compresser une image
    async compressImage(file, maxSize) {
        return new Promise((resolve, reject) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            img.onload = () => {
                // Calculer les nouvelles dimensions
                let { width, height } = img;
                const maxDimension = 1920; // Dimension max

                if (width > height && width > maxDimension) {
                    height = (height * maxDimension) / width;
                    width = maxDimension;
                } else if (height > maxDimension) {
                    width = (width * maxDimension) / height;
                    height = maxDimension;
                }

                canvas.width = width;
                canvas.height = height;

                // Dessiner l'image redimensionnée
                ctx.drawImage(img, 0, 0, width, height);

                // Convertir en blob avec compression progressive
                let quality = 0.8;
                const tryCompress = () => {
                    canvas.toBlob((blob) => {
                        if (blob.size <= maxSize || quality <= 0.1) {
                            // Créer un nouveau fichier avec le nom original
                            const compressedFile = new File([blob], file.name, {
                                type: file.type,
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        } else {
                            quality -= 0.1;
                            tryCompress();
                        }
                    }, file.type, quality);
                };

                tryCompress();
            };

            img.onerror = () => reject(new Error('Impossible de charger l\'image'));
            img.src = URL.createObjectURL(file);
        });
    }

    // Afficher l'aperçu des fichiers
    displayFilePreview() {
        const previewDiv = document.getElementById('photo-preview');
        previewDiv.innerHTML = '';

        this.selectedFiles.forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const photoDiv = document.createElement('div');
                    photoDiv.className = 'photo-item';
                    const sizeText = (file.size / 1024 / 1024).toFixed(2) + 'MB';
                    photoDiv.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <div class="file-info">${file.name} (${sizeText})</div>
                        <button class="remove-btn" onclick="app.removeFile(${index})">×</button>
                    `;
                    previewDiv.appendChild(photoDiv);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Supprimer un fichier de la sélection
    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.displayFilePreview();

        const uploadBtn = document.getElementById('upload-photos');
        uploadBtn.disabled = this.selectedFiles.length === 0;
    }

    // Upload des photos
    async uploadPhotos() {
        console.log('🔍 PWA Upload - ID intervention:', this.currentInterventionId);
        console.log('🔍 PWA Upload - Nombre de fichiers:', this.selectedFiles.length);

        if (!this.currentInterventionId || this.selectedFiles.length === 0) {
            this.showMessage('Aucune photo à envoyer', 'error');
            return;
        }

        const description = document.getElementById('photo-description').value.trim();
        const progressContainer = document.getElementById('progress-container');
        const progressFill = document.getElementById('progress-fill');
        const uploadStatus = document.getElementById('upload-status');
        const uploadBtn = document.getElementById('upload-photos');

        // Désactiver le bouton et afficher la progression
        uploadBtn.disabled = true;
        progressContainer.classList.remove('hidden');
        uploadStatus.classList.remove('hidden');

        let completed = 0;
        const total = this.selectedFiles.length;
        let errors = [];

        for (let i = 0; i < this.selectedFiles.length; i++) {
            const file = this.selectedFiles[i];

            try {
                uploadStatus.textContent = `Upload en cours... (${completed + 1}/${total}) - ${file.name}`;

                const formData = new FormData();
                formData.append('photo', file);
                formData.append('intervention_id', this.currentInterventionId);
                if (description) {
                    formData.append('description', description);
                }

                console.log('🔍 PWA FormData créé pour:', file.name);
                console.log('🔍 PWA intervention_id envoyé:', this.currentInterventionId);

                // Timeout plus long pour mobile
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 secondes

                const response = await fetch(`${this.apiBaseUrl}photos.php`, {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                console.log('🔍 PWA Réponse status:', response.status);
                console.log('🔍 PWA Réponse headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                console.log('🔍 PWA Réponse brute:', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    throw new Error(`Réponse non-JSON: ${responseText}`);
                }
                console.log('🔍 PWA Réponse data:', data);

                if (!data.success) {
                    errors.push(`${file.name}: ${data.message}`);
                    console.error('❌ PWA Erreur:', data.message);
                }

                completed++;
                const progress = (completed / total) * 100;
                progressFill.style.width = progress + '%';

            } catch (error) {
                console.error('❌ PWA Erreur upload:', error);
                let errorMsg = 'Erreur réseau';
                if (error.name === 'AbortError') {
                    errorMsg = 'Timeout (connexion trop lente)';
                } else if (error.message.includes('HTTP')) {
                    errorMsg = error.message;
                }
                errors.push(`${file.name}: ${errorMsg}`);
                completed++;
            }
        }

        // Finaliser l'upload
        setTimeout(() => {
            progressContainer.classList.add('hidden');
            uploadStatus.classList.add('hidden');
            uploadBtn.disabled = false;

            if (errors.length === 0) {
                this.showMessage(`${total} photo(s) envoyée(s) avec succès!`, 'success');
                this.selectedFiles = [];
                document.getElementById('photo-preview').innerHTML = '';
                document.getElementById('photo-description').value = '';
                document.getElementById('photo-input').value = '';

                // Recharger les photos existantes
                this.loadIntervention();
            } else {
                this.showMessage(`Upload terminé avec ${errors.length} erreur(s):\n${errors.join('\n')}`, 'error');
            }
        }, 500);
    }

    // Afficher un message
    showMessage(message, type = 'info') {
        const messagesDiv = document.getElementById('status-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `status-message status-${type}`;
        messageDiv.textContent = message;

        messagesDiv.appendChild(messageDiv);

        // Supprimer le message après 5 secondes
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 5000);

        // Scroll vers le message
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Afficher/masquer le loading
    showLoading(show) {
        const loadingDiv = document.getElementById('loading');
        if (show) {
            loadingDiv.classList.remove('hidden');
        } else {
            loadingDiv.classList.add('hidden');
        }
    }

    // Ouvrir une photo en modal (méthode globale)
    openPhotoModal(imageUrl, filename) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.9); z-index: 10000; 
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        `;

        modal.innerHTML = `
            <div style="max-width: 90%; max-height: 90%; position: relative;" onclick="event.stopPropagation()">
                <img src="${imageUrl}" alt="${filename}" 
                     style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
                <div style="position: absolute; bottom: -40px; left: 0; right: 0; text-align: center; color: white;">
                    <p style="margin: 0; font-size: 14px;">${filename}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="position: absolute; top: -10px; right: -10px; background-color: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 16px;">×</button>
            </div>
        `;

        modal.addEventListener('click', () => modal.remove());
        document.body.appendChild(modal);
    }
}

// Initialiser l'application
const app = new TechSuiviPhotosApp();

// Rendre la méthode openPhotoModal globalement accessible
window.openPhotoModal = (imageUrl, filename) => {
    app.openPhotoModal(imageUrl, filename);
};

// Gestion de l'installation PWA
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    // Empêcher l'affichage automatique
    e.preventDefault();
    deferredPrompt = e;

    // Afficher un bouton d'installation personnalisé
    const installBtn = document.createElement('button');
    installBtn.textContent = '📱 Installer l\'application';
    installBtn.className = 'btn btn-secondary';
    installBtn.style.marginTop = '10px';

    installBtn.addEventListener('click', () => {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('PWA installée');
            }
            deferredPrompt = null;
            installBtn.remove();
        });
    });

    document.querySelector('.header').appendChild(installBtn);
});