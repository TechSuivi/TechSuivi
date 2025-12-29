# 📁 Structure du Projet TechSuivi

## 🎯 Vue d'ensemble

Ce document décrit la nouvelle structure organisée du projet TechSuivi après le nettoyage et la réorganisation des fichiers.

## 📂 Structure des Dossiers

```
TechSuivi/
├── 📁 docs/                          # Documentation du projet
│   ├── 📁 guides/                    # Guides d'utilisation
│   ├── 📁 installation/              # Documentation d'installation
│   └── 📁 security/                  # Documentation de sécurité
├── 📁 scripts/                       # Scripts d'automatisation
│   ├── 📁 installation/              # Scripts d'installation
│   ├── 📁 maintenance/               # Scripts de maintenance
│   └── 📁 database/                  # Scripts de base de données
├── 📁 tests/                         # Tests et validation
│   ├── 📁 security/                  # Tests de sécurité
│   └── 📁 archive/                   # Tests archivés
├── 📁 temp/                          # Fichiers temporaires
│   └── 📁 debug/                     # Fichiers de debug
├── 📁 db/                            # Base de données
│   ├── init.sql                      # Initialisation de la DB
│   ├── init_complete.sql             # Initialisation complète
│   └── *.sql                         # Scripts SQL divers
├── 📁 web/                           # Application web
│   └── 📁 src/                       # Code source PHP
├── 📁 _Autoit/                       # Scripts AutoIt
├── 📁 _Site_web/                     # Site web statique
│   └── 📁 techsuivifr/               # Version française du site
├── 📁 ftp/                           # Dossiers FTP
│   ├── 📁 ftpuser/                   # Utilisateur FTP
│   └── 📁 user/                      # Dossier utilisateur
├── � docker-compose.yml             # Configuration Docker
├── 📄 README.md                      # Documentation principale
├── 📄 LICENSE                        # Licence du projet
├── 🖼️ logo.png                       # Logo du projet
└── 🔒 .env                           # Variables d'environnement
```

## 📋 Détail des Dossiers

### 📁 docs/
Contient toute la documentation du projet organisée par catégorie :

- **guides/** : Guides d'utilisation et de dépannage
  - `GUIDE_DEPANNAGE_DB.md`
  - `GUIDE_RESOLUTION_CACHE.md`
  - `GUIDE_TEST_SERVEUR.md`

- **installation/** : Documentation d'installation et de mise à jour
  - `GUIDE_INSTALLATION.md`
  - `README_DATABASE_SETUP.md`
  - `README_SECURITE.md`
  - `INSTALL_ZIP_DOCKER.md`
  - `INSTALL_ZIP_EXTENSION.md`
  - `MISE_A_JOUR_V2.md`
  - `SOLUTION_ENV_FINALE.md`
  - `TEST_VERSION_FORCEE.md`

### 📁 scripts/
Scripts d'automatisation organisés par fonction :

- **installation/** : Scripts d'installation automatique
- **maintenance/** : Scripts de maintenance et de réparation
- **database/** : Scripts spécifiques à la base de données

### 📁 tests/
Tests et validation du projet :

- **security/** : Tests de sécurité (anciennement `_tests/`)
  - `security_audit.php`
  - `security_fixes.php`
  - `security_monitor.php`
  - `test_lfi_fix.php`

- **archive/** : Tests archivés et fichiers de test

### 📁 temp/
Fichiers temporaires et de debug :

- **debug/** : Fichiers de debug déplacés depuis `web/src/`
  - Logs de debug
  - Fichiers de test SQL
  - Scripts de diagnostic

### 📁 web/src/
Code source de l'application web (structure inchangée) :

- **actions/** : Actions PHP
- **api/** : API endpoints
- **components/** : Composants réutilisables
- **config/** : Configuration
- **css/** : Feuilles de style
- **js/** : Scripts JavaScript
- **pages/** : Pages de l'application
- **utils/** : Utilitaires
- **uploads/** : Fichiers uploadés

## 🧹 Nettoyage Effectué

### ✅ Fichiers Déplacés
- Documentation → `docs/`
- Scripts → `scripts/`
- Tests de sécurité → `tests/security/`
- Fichiers de debug → `temp/debug/`

### ✅ Fichiers Organisés
- Guides d'utilisation regroupés
- Scripts d'installation centralisés
- Tests de sécurité consolidés
- Fichiers temporaires isolés

### ✅ Structure Optimisée
- Séparation claire des responsabilités
- Facilité de navigation
- Maintenance simplifiée
- Déploiement plus propre

## 🔧 Fichiers de Configuration

### .gitignore
Fichier `.gitignore` optimisé pour :
- Ignorer les fichiers temporaires et de debug
- Protéger les fichiers de configuration sensibles
- Exclure les dépendances et caches
- Ignorer les fichiers système

### .env
Variables d'environnement pour la configuration :
- Paramètres de base de données
- Configuration Docker
- Paramètres de sécurité

## 📝 Recommandations

### Pour les Développeurs
1. **Documentation** : Consultez `docs/` pour toute information
2. **Tests** : Utilisez les scripts dans `tests/security/`
3. **Debug** : Les fichiers de debug sont dans `temp/debug/`
4. **Scripts** : Utilisez les scripts dans `scripts/` pour l'automatisation

### Pour la Maintenance
1. **Logs** : Vérifiez régulièrement `temp/debug/`
2. **Sauvegardes** : Utilisez les scripts dans `scripts/database/`
3. **Sécurité** : Exécutez les audits dans `tests/security/`
4. **Documentation** : Maintenez à jour `docs/`

## 🚀 Avantages de la Nouvelle Structure

- **🎯 Clarté** : Structure logique et intuitive
- **🔍 Facilité de recherche** : Fichiers organisés par fonction
- **🛠️ Maintenance simplifiée** : Séparation des responsabilités
- **📦 Déploiement propre** : Exclusion des fichiers temporaires
- **🔒 Sécurité améliorée** : Isolation des fichiers sensibles
- **📚 Documentation centralisée** : Toute la doc au même endroit

## 📞 Support

Pour toute question sur la structure du projet :
1. Consultez la documentation dans `docs/`
2. Vérifiez les guides dans `docs/guides/`
3. Utilisez les scripts de diagnostic dans `scripts/`

---

*Document créé lors du nettoyage du projet TechSuivi - Version 1.0*