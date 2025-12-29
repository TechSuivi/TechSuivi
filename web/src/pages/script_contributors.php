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
    'current_version' => '4.0.1',
    'release_date' => '2024-11-04',
    'license' => 'Propriétaire',
    'repository' => 'Interne',
    'status' => 'En développement actif'
];

// Liste des créateurs et contributeurs
$contributors = [
    [
        'name' => 'Utilisateur Principal',
        'role' => 'Créateur & Chef de Projet',
        'contributions' => [
            'Conception du projet TechSuivi',
            'Définition des besoins fonctionnels',
            'Tests et validation',
            'Gestion du projet'
        ],
        'period' => '2024 - Présent',
        'type' => 'creator'
    ],
    [
        'name' => 'Kilo Code (IA Assistant)',
        'role' => 'Développeur Principal',
        'contributions' => [
            'Architecture et développement du système',
            'Interface utilisateur et design',
            'Intégration des fonctionnalités',
            'Optimisation et maintenance du code'
        ],
        'period' => '2024 - Présent',
        'type' => 'creator'
    ],
    [
        'name' => 'Contributeurs AutoIT',
        'role' => 'Scripts et Automatisation',
        'contributions' => [
            'Scripts AutoIT pour l\'automatisation système',
            'Outils de maintenance Windows',
            'Scripts de nettoyage et optimisation',
            'Intégration desktop'
        ],
        'period' => '2024',
        'type' => 'contributor'
    ]
];

// Historique des versions
$versionHistory = [
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
                    <tr><td><strong>Version actuelle :</strong></td><td><span class="version-badge"><?= htmlspecialchars($projectInfo['current_version']) ?></span></td></tr>
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