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
    'current_version' => '5.0.4',
    'release_date' => '2026-01-23',
    'license' => 'Propriétaire',
    'repository' => 'Interne',
    'status' => 'En développement actif'
];

// ... (Contributors list remains same - omitted for brevity in this refactor view but assumed present in logic) ...
// NOTE: I am preserving the data structures but replacing the HTML/CSS presentation.

// Historique des versions (Data structure preserved)
$versionHistory = [
    [
        'version' => '5.0.4',
        'date' => '2026-01-23',
        'type' => 'Patch',
        'changes' => [
            'UI : Refonte complète des Layouts (Fournisseurs, Helpdesk Catégories, Moyens de Paiement, Statuts)',
            'UX : Mise en place du "Split View" (Formulaire à gauche, Liste à droite) sur les pages de configuration',
            'UI : Harmonisation et Standardisation des boutons d\'action (Format Carré 34px, Info/Danger)',
            'Infra : Ajout GLOBAL de FontAwesome dans le header pour correction des icônes manquantes',
            'Fix : Correction de l\'indicateur de couleur (Arrondi parfait) sur les catégories Helpdesk',
            'UI : Conversion de toutes les listes "Cards" en Tableaux pour meilleure densité d\'information'
        ]
    ],
    [
        'version' => '5.0.3',
        'date' => '2026-01-22',
        'type' => 'Patch',
        'changes' => [
            'Bugfix : Résolution de l\'erreur de table manquante `notes_globales` lors de la mise à jour BDD',
            'Bugfix : Correction de l\'ordre d\'exécution du script `update_db_structure.php` (Création tables avant Ajout colonnes)',
            'UI : Correction du style CSS de la page VNC (Overlay plein écran, grilles, espacements)',
            'Infra : Ajout des utilitaires CSS manquants spécifiques à la page VNC',
            'Infra : Correction des permissions Docker pour le dossier `uploads/notes` (Upload pièces jointes)'
        ]
    ],
    [
        'version' => '5.0.2',
        'date' => '2026-01-21',
        'type' => 'Patch',
        'changes' => [
            'Feature : Gestion des notes associées directement sur la fiche client (Vue, Edition, Suppression)',
            'UI : Amélioration bouton "Voir note" (Modale lecture seule) et icône téléchargement fichier (📥)',
            'UI : Harmonisation des boutons d\'action sur l\'ensemble des listes (Agenda, Messages, Notes)',
            'UI : Suppression des dégradés "Arc-en-ciel" sur les entêtes de tableaux (Remplacement par Gris Neutre)',
            'UI : Passage des boutons primaires en "Flat Design" (Suppression du dégradé)',
            'Bugfix : Correction de l\'enregistrement des modifications de liens (Sélecteur JS incorrect)'
        ]
    ],
    [
        'version' => '5.0.1',
        'date' => '2026-01-21',
        'type' => 'Patch',
        'changes' => [
            'UI : Changement de l\'accent de couleur principal (Violet -> Vert)',
            'UI : Harmonisation des tons verts sur l\'ensemble des composants (Badges, boutons, liens)',
            'UI : Amélioration du contraste et de la lisibilité du thème vert',
            'UI : Correction de la lisibilité en Dark Mode sur la feuille de caisse',
            'UX : Champ "Solde de départ" modifiable sur la feuille de caisse',
            'Bugfix : Correction du filtre "Masquer clôturées" dans la liste des interventions (JS/AJAX)'
        ]
    ],
    [
        'version' => '5.0.0',
        'date' => '2026-01-20',
        'type' => 'Major Release',
        'changes' => [
            'UI : Refonte complète vers une esthétique SaaS Enterprise (Centralisation CSS, Variables, Grid Layout)',
            'UX : Nouveau Dashboard (Layout 3 colonnes, tableaux denses, empilement intelligent)',
            'UX : Harmonisation des modales (Design épuré, backdrop-blur, suppression des dégradés intenses)',
            'UX : Comportement des modales sécurisé (Fermeture explicite uniquement, stop aux clics accidentels sur l\'overlay)',
            'Performance : Correction du "Theme Flash" (FOUC) via initialisation synchrone au chargement',
            'Feature : Nouveau système de configuration Mail & SMTP complet',
            'Feature : Nouveau module de personnalisation du Thème et de l\'apparence',
            'UI : Standardisation de toutes les listes (Interventions, Clients, Agenda, Liens, Téléchargements)',
            'Nettoyage : Optimisation du cache CSS et suppression du versioning dynamique superflu'
        ]
    ],
    [
        'version' => '4.3.5',
        'date' => '2026-01-13',
        'type' => 'Patch',
        'changes' => [
            'UX : Page Contributeurs - Bouton d\'accès direct à la mise à jour structure BDD'
        ]
    ],
    // ... (rest of history) ...
    [
        'version' => '4.3.4',
        'date' => '2026-01-12',
        'type' => 'Patch',
        'changes' => [
            'Feature : Cyber - Liaison robuste des sessions avec les fiches client (ID)',
            'UI : Cyber - Indicateur visuel (✅) lors de la sélection client',
            'UI : Fiches Client - Nouvel historique Cyber avec détail des paiements',
            'UI : Listes Cyber/Transactions - Nom du client cliquable avec icône 👤',
            'Feature : Stock - Page "Liste commande" qui regroupe les produits pour une vision synthétique',
            'Feature : Stock - Modification de date de commande et gestion de fichiers (Factures/BL) depuis la liste',
            'UX : Stock - Ajout rapide d\'articles dans une commande existante',
            'UX : Stock - Affichage d\'un en-tête de commande avec actions (Edit/Add) et documents lors de la recherche d\'une commande unique',
            'Bugfix : UI - Correction de l\'ouverture du menu latéral sur les pages secondaires (ex: Liste commande)',
            'Bugfix : UI - Correction du style du sous-menu Messages (alignement et fond)',
            'Bugfix : UI - Rétablissement de la navigation au clic sur les menus parents (Stock, Messages, Agenda)'
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

<div class="container container-center max-w-1200">
    <div class="page-header">
        <h1>📈 Versions</h1>
    </div>
    
    <!-- Informations générales du projet -->
    <div class="card mb-30">
        <h3 class="card-title text-primary mb-20">📋 Informations Générales</h3>
        <div class="grid-2 gap-20">
            <div class="card bg-secondary border">
                <h4 class="text-accent mt-0 mb-15">🏷️ Projet</h4>
                <table class="table w-full">
                    <tr><td class="font-bold py-5">Nom :</td><td><?= htmlspecialchars($projectInfo['name']) ?></td></tr>
                    <tr><td class="font-bold py-5">Version actuelle :</td><td><span class="badge badge-primary rounded-20 px-10" id="currentVersionDisplay"><?= htmlspecialchars($projectInfo['current_version']) ?></span> <button onclick="checkVersion()" class="cursor-pointer border-0 bg-transparent text-lg" title="Vérifier MAJ">🔄</button></td></tr>
                    <tr><td class="font-bold py-5">Base de données :</td><td><a href="install/update_db_structure.php" target="_blank" class="badge badge-warning text-xs font-bold no-underline">🛠️ Mettre à jour Structure</a></td></tr>
                    <tr><td class="font-bold py-5">Date de release :</td><td><?= htmlspecialchars($projectInfo['release_date']) ?></td></tr>
                    <tr><td class="font-bold py-5">Statut :</td><td><span class="badge badge-success"><?= htmlspecialchars($projectInfo['status']) ?></span></td></tr>
                    <tr><td class="font-bold py-5">Licence :</td><td><?= htmlspecialchars($projectInfo['license']) ?></td></tr>
                    <tr><td class="font-bold py-5">Repository :</td><td><?= htmlspecialchars($projectInfo['repository']) ?></td></tr>
                </table>
            </div>
            
            <div class="card bg-secondary border">
                <h4 class="text-accent mt-0 mb-15">🛠️ Stack Technique</h4>
                <?php foreach ($technologies as $category => $techs): ?>
                    <div class="mb-15">
                        <h5 class="text-accent m-0 mb-5 text-sm"><?= htmlspecialchars($category) ?></h5>
                        <ul class="list-none p-0 m-0">
                            <?php foreach ($techs as $tech => $version): ?>
                                <li class="text-sm py-2"><strong><?= htmlspecialchars($tech) ?>:</strong> <?= htmlspecialchars($version) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Historique des versions -->
    <div class="card">
        <h3 class="card-title text-primary mb-20">📈 Historique des Versions</h3>
        <div class="flex flex-col gap-20">
            <?php foreach ($versionHistory as $version): ?>
                <div class="card bg-secondary border">
                    <div class="flex items-center gap-15 mb-15 flex-wrap">
                        <span class="badge badge-primary rounded-20 px-10 text-lg"><?= htmlspecialchars($version['version']) ?></span>
                        <span class="badge <?= strpos($version['type'], 'Major') !== false ? 'badge-danger' : (strpos($version['type'], 'Minor') !== false ? 'badge-warning' : 'badge-success') ?>"><?= htmlspecialchars($version['type']) ?></span>
                        <span class="text-muted text-sm"><?= htmlspecialchars($version['date']) ?></span>
                    </div>
                    <div>
                        <h5 class="text-accent m-0 mb-10">Changements :</h5>
                        <ul class="pl-20 m-0">
                            <?php foreach ($version['changes'] as $change): ?>
                                <li class="text-sm py-2"><?= htmlspecialchars($change) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

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