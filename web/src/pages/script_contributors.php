<?php
// Empêcher l'accès direct au fichier
if (!defined('TECHSUIVI_INCLUDED')) {
    die('Accès direct non autorisé.');
}

// Inclure le composant de navigation des paramètres
require_once __DIR__ . '/../components/settings_navigation.php';

// Informations sur les versions et intervenants
$projectInfo = [
    'name' => 'TechSuivi',
    'current_version' => '4.3.4',
    'release_date' => '2026-01-12',
    'license' => 'Propriétaire',
    'repository' => 'Interne',
    'status' => 'En développement actif'
];

// ... (Contributors list remains same) ...

// Historique des versions
$versionHistory = [
    [
        'version' => '4.3.4',
        'date' => '2026-01-12',
        'type' => 'Patch',
        'changes' => [
            'Feature : Cyber - Liaison robuste des sessions avec les fiches client (ID)',
            'UI : Cyber - Indicateur visuel (✅) lors de la sélection client',
            'UI : Fiches Client - Nouvel historique Cyber avec détail des paiements',
            'UI : Listes Cyber/Transactions - Nom du client cliquable avec icône 👤'
        ]
    ],
    [
        'version' => '4.3.3',
        'date' => '2026-01-09',
        'type' => 'Patch',
        'changes' => [
            'Feature : Cyber - Ajout case "➕ Cyber" pour inclure le coût temps au tarif spécifique',
            'UI : Cyber - Empilement vertical des cases options pour meilleure lisibilité',
            'UX : Cyber - Le champ "Tarif spécifique" ne se remplit plus automatiquement (reste vide pour mode Auto)',
            'Feature : Cyber - Autocomplétion recherche clients'
        ]
    ],
    [
        'version' => '4.3.2',
        'date' => '2026-01-09',
        'type' => 'Patch',
        'changes' => [
            'Fix : Encodage correct du symbole Euro (€) dans les rapports PDF',
            'Feature : Ajout de la section "Retraits Bancaires" dans le rapport PDF Résumé Caisse',
            'Fix : Uniformisation de l\'encodage des caractères spéciaux pour FPDF'
        ]
    ],
    [
        'version' => '4.3.1',
        'date' => '2026-01-09',
        'type' => 'Patch',
        'changes' => [
            'Fix : Bouton "Répondre" fonctionnel sur Dashboard et Messages',
            'Fix : Bouton "Annuler" fonctionnel sur le modal de réponse',
            'Fix : Gestion des caractères spéciaux (quotes, backslashes) dans les réponses'
        ]
    ],
    [
        'version' => '4.3.0',
        'date' => '2026-01-08',
        'type' => 'Minor Release',
        'changes' => [
            'Feature : Assistant IA complet (Chat conversationnel, Historique, Règles personnalisables)',
            'Feature : UI Premium pour l\'Assistant (Split View, Dark Mode, Animations)',
            'Persistance : Sauvegarde automatique de l\'état (Mode, Conversation, Ton) après rechargement',
            'Database : Nouvelles tables (ai_conversations, ai_messages, ai_rules)',
            'Fix : Gestion robuste des erreurs API Gemini et correction des fuites CSS'
        ]
    ],
    [
        'version' => '4.2.3',
        'date' => '2026-01-05',
        'type' => 'Minor Release',
        'changes' => [
            'Feature : Gestionnaire de Fichiers complet (Création dossiers, Upload fichiers, Navigation)',
            'Feature : Système de Sauvegarde/Restauration Rustdesk (Settings)',
            'Feature : Protection par mot de passe (Backup/Restore) pour Fichiers, DB, et Rustdesk',
            'Feature : Restauration ZIP contextuelle (Restaurer dans le dossier courant)',
            'Fix : Navigation manquante sur la page Import Clients',
            'Fix : Correction des noms de fichiers tronqués dans les ZIP générés',
            'UI : Barre d\'outils compacte pour le Gestionnaire de Fichiers'
        ]
    ],
    [
        'version' => '4.2.2',
        'date' => '2025-12-31',
        'type' => 'Patch',
        'changes' => [
            'Zero-Config : Auto-configuration IP et restauration installeur',
            'Sécurité : Génération automatique de clé API pour l\'installeur',
            'UI Dashboard : Unification des cartes Interventions/Agenda',
            'Fix : Affichage Agenda et corrections mineures',
            'Feature : Bouton "Vérifier Mise à jour" (Page Contributeurs)',
            'Fix : Permissions Docker (Volumes Uploads & VNC)',
            'Infra : Passage aux volumes Docker nommés pour VNC'
        ]
    ],
    [
        'version' => '4.2.1',
        'date' => '2025-12-30',
        'type' => 'Patch',
        'changes' => [
            'Gestion des Téléchargements : Suppression optionnelle du fichier physique',
            'Patch : Conservation du nom original du fichier uploadé (sanitisé)',
            'Correction des permissions lors de la suppression'
        ]
    ],
    [
        'version' => '4.2.0',
        'date' => '2025-12-30',
        'type' => 'Minor Release',
        'changes' => [
            'Gestion des Téléchargements : Support de l\'upload direct de fichiers',
            'Stockage automatique dans /uploads/downloads/',
            'Génération dynamique des URLs de téléchargement locaux',
            'Amélioration de l\'expérience utilisateur (Toggle URL/Upload)'
        ]
    ],
    [
        'version' => '4.1.1',
        'date' => '2025-12-30',
        'type' => 'Patch',
        'changes' => [
            'Fix CRON : Injection des variables d\'environnement Docker',
            'Amélioration du démarrage du service cron dans le conteneur',
            'Optimisation des permissions de logs cron'
        ]
    ],
    [
        'version' => '4.1.0',
        'date' => '2025-12-30',
        'type' => 'Major Release',
        'changes' => [
            'Support complet QNAP NAS (Docker Hub)',
            'Base de données auto-initialisée',
            'Service CRON autonome (Interne au conteneur)',
            'Patch VNC (Port 8085 pour éviter les conflits)',
            'Optimisation réseau Docker'
        ]
    ],
    [
        'version' => '4.0.1',
        'date' => '2024-11-04',
        'type' => 'Patch',
        'changes' => [
            'Ajout de la page informations serveur',
            'Amélioration de la navigation des paramètres',
            'Corrections de bugs mineurs',
            'Optimisation des performances'
        ]
    ],
    [
        'version' => '4.0.0',
        'date' => '2024-10-01',
        'type' => 'Major Release',
        'changes' => [
            'Refonte complète de l\'interface',
            'Nouveau système de gestion des interventions',
            'Intégration AutoIT améliorée',
            'Système de caisse intégré',
            'Mode sombre/clair',
            'API REST complète'
        ]
    ],
    [
        'version' => '3.2.5',
        'date' => '2024-08-15',
        'type' => 'Patch',
        'changes' => [
            'Corrections de sécurité',
            'Amélioration des performances',
            'Mise à jour des dépendances'
        ]
    ],
    [
        'version' => '3.2.0',
        'date' => '2024-06-01',
        'type' => 'Minor Release',
        'changes' => [
            'Nouveau système d\'agenda',
            'Gestion des photos d\'intervention',
            'Amélioration du système de sauvegarde',
            'Interface mobile optimisée'
        ]
    ]
];

// Technologies et dépendances
$technologies = [
    'Backend' => [
        'PHP' => PHP_VERSION,
        'MySQL/MariaDB' => 'Compatible 5.7+',
        'Apache/Nginx' => 'Compatible'
    ],
    'Frontend' => [
        'HTML5' => 'Standard',
        'CSS3' => 'Avec variables CSS',
        'JavaScript' => 'ES6+',
        'Responsive Design' => 'Mobile-first'
    ],
    'Outils' => [
        'AutoIT' => 'v3.3.16+',
        'Docker' => 'Support conteneurisation',
        'Git' => 'Contrôle de version'
    ]
];
?>

<div class="container">
    <h2>📈 Versions</h2>
    
    <!-- Informations générales du projet -->
    <div class="info-section">
        <h3>📋 Informations Générales</h3>
        <div class="info-grid">
            <div class="info-card">
                <h4>🏷️ Projet</h4>
                <table class="info-table">
                    <tr><td><strong>Nom :</strong></td><td><?= htmlspecialchars($projectInfo['name']) ?></td></tr>
                    <tr><td><strong>Version actuelle :</strong></td><td><span class="version-badge" id="currentVersionDisplay"><?= htmlspecialchars($projectInfo['current_version']) ?></span> <button onclick="checkVersion()" style="background:none;border:none;cursor:pointer;font-size:1.2em;" title="Vérifier MAJ">🔄</button></td></tr>
                    <tr><td><strong>Date de release :</strong></td><td><?= htmlspecialchars($projectInfo['release_date']) ?></td></tr>
                    <tr><td><strong>Statut :</strong></td><td><span class="status-active"><?= htmlspecialchars($projectInfo['status']) ?></span></td></tr>
                    <tr><td><strong>Licence :</strong></td><td><?= htmlspecialchars($projectInfo['license']) ?></td></tr>
                    <tr><td><strong>Repository :</strong></td><td><?= htmlspecialchars($projectInfo['repository']) ?></td></tr>
                </table>
            </div>
            
            <div class="info-card">
                <h4>🛠️ Stack Technique</h4>
                <?php foreach ($technologies as $category => $techs): ?>
                    <div class="tech-category">
                        <h5><?= htmlspecialchars($category) ?></h5>
                        <ul class="tech-list">
                            <?php foreach ($techs as $tech => $version): ?>
                                <li><strong><?= htmlspecialchars($tech) ?>:</strong> <?= htmlspecialchars($version) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Historique des versions -->
    <div class="info-section">
        <h3>📈 Historique des Versions</h3>
        <div class="version-timeline">
            <?php foreach ($versionHistory as $version): ?>
                <div class="version-item">
                    <div class="version-header">
                        <span class="version-number"><?= htmlspecialchars($version['version']) ?></span>
                        <span class="version-type type-<?= strtolower(str_replace(' ', '-', $version['type'])) ?>"><?= htmlspecialchars($version['type']) ?></span>
                        <span class="version-date"><?= htmlspecialchars($version['date']) ?></span>
                    </div>
                    <div class="version-changes">
                        <h5>Changements :</h5>
                        <ul>
                            <?php foreach ($version['changes'] as $change): ?>
                                <li><?= htmlspecialchars($change) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.version-badge {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 14px;
}

.status-active {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.tech-category {
    margin-bottom: 15px;
}

.tech-category h5 {
    margin: 0 0 5px 0;
    color: var(--accent-color);
    font-size: 14px;
}

.tech-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tech-list li {
    padding: 2px 0;
    font-size: 13px;
}

.contributors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.contributor-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.contributor-header h4 {
    margin: 0 0 5px 0;
    color: var(--accent-color);
}

.contributor-role {
    background: #f8f9fa;
    color: #6c757d;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.contributor-info {
    margin: 15px 0;
    font-size: 14px;
}

.contributor-info p {
    margin: 5px 0;
}

.contributor-contributions h5 {
    margin: 15px 0 10px 0;
    color: var(--accent-color);
    font-size: 14px;
}

.contributor-contributions ul {
    margin: 0;
    padding-left: 20px;
}

.contributor-contributions li {
    margin: 5px 0;
    font-size: 13px;
}

.version-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.version-item {
    background: white;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.version-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.version-number {
    background: linear-gradient(135deg, var(--accent-color), #23428a);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 16px;
}

.version-type {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.type-major-release {
    background: #dc3545;
    color: white;
}

.type-minor-release {
    background: #ffc107;
    color: #212529;
}

.type-patch {
    background: #28a745;
    color: white;
}

.version-date {
    color: #6c757d;
    font-size: 14px;
}

.version-changes h5 {
    margin: 0 0 10px 0;
    color: var(--accent-color);
}

.version-changes ul {
    margin: 0;
    padding-left: 20px;
}

.version-changes li {
    margin: 5px 0;
    font-size: 14px;
}

/* Mode sombre */
body.dark .contributor-card,
body.dark .version-item {
    background-color: #333;
    border-color: #555;
    color: var(--text-color-dark);
}

body.dark .contributor-role {
    background-color: #444;
    color: #aaa;
}

/* Responsive */
@media (max-width: 768px) {
    .contributors-grid {
        grid-template-columns: 1fr;
    }
    
    .version-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<script>
function checkVersion() {
    const btn = document.querySelector('button[title="Vérifier MAJ"]');
    if(!btn) return;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳';
    btn.disabled = true;

    // Utilisation de l'API GitHub pour éviter le cache
    fetch('https://api.github.com/repos/TechSuivi/TechSuivi/contents/web/src/pages/script_contributors.php')
        .then(response => response.json())
        .then(data => {
            if (data.content) {
                const text = atob(data.content); // Décodage Base64
                const match = text.match(/'current_version'\s*=>\s*'([^']+)'/);
                if (match && match[1]) {
                    const remoteVersion = match[1];
                    const currentVersion = document.getElementById('currentVersionDisplay').innerText.trim();
                    if (remoteVersion === currentVersion) {
                        alert("✅ Vous êtes à jour ! (Version " + currentVersion + ")");
                    } else {
                        alert("⚠️ Une mise à jour est disponible !\nActuelle : " + currentVersion + "\nDisponible : " + remoteVersion);
                    }
                } else alert("❌ Format de version non reconnu.");
            } else alert("❌ Impossible de lire le fichier distant.");
        })
        .catch(err => {
            console.error(err);
            // Fallback
             fetch('https://raw.githubusercontent.com/TechSuivi/TechSuivi/main/web/src/pages/script_contributors.php')
                .then(r => r.text())
                .then(text => {
                     const match = text.match(/'current_version'\s*=>\s*'([^']+)'/);
                     if (match && match[1]) {
                        const remoteVersion = match[1];
                        const currentVersion = document.getElementById('currentVersionDisplay').innerText.trim();
                        if (remoteVersion === currentVersion) alert("✅ Vous êtes à jour !");
                        else alert("⚠️ Mise à jour dispo : " + remoteVersion);
                     }
                });
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}
</script>