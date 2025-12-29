# 🚀 TechSuivi

**Application de gestion d'interventions techniques avec interface web et intégration AutoIT**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)](https://www.docker.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)

---

## 📋 Table des matières

- [🎯 Fonctionnalités](#-fonctionnalités)
- [🚀 Installation rapide](#-installation-rapide)
- [🔧 Configuration](#-configuration)
- [📁 Structure du projet](#-structure-du-projet)
- [🌐 Accès à l'application](#-accès-à-lapplication)
- [📚 Documentation](#-documentation)
- [🛠️ Développement](#️-développement)
- [🤝 Contribution](#-contribution)

---

## 🎯 Fonctionnalités

### 💼 Gestion d'entreprise
- **Gestion des interventions** - Création, suivi et historique complet
- **Base de données clients** - Informations détaillées et historique
- **Fiche de caisse** - Gestion financière
- **Système de photos** - Upload et gestion d'images pour les interventions

### 🤖 Automatisation
- **Intégration AutoIT** - Scripts d'automatisation pour les techniciens
- **API REST** - Interface pour applications externes
- **Synchronisation** - Échange de données entre l'application web et AutoIT

---

## 🚀 Installation

### Prérequis
- [Docker](https://www.docker.com/) et Docker Compose
- Git

### Option 1: Installation rapide (Recommandée)
```bash
git clone https://github.com/TechSuivi/TechSuivi.git
cd TechSuivi
./setup_auto.sh
```

### Option 2: Installation personnalisée
```bash
git clone https://github.com/TechSuivi/TechSuivi.git
cd TechSuivi
./setup_interactive.sh
```
*Permet de personnaliser les mots de passe et noms de base de données*

L'application sera accessible sur **http://localhost:8080**

---

## 🔧 Configuration

### Configuration automatique
Les scripts d'installation créent automatiquement le fichier `.env` :
- **Installation rapide** : Utilise les valeurs par défaut de `.env.example`
- **Installation interactive** : Vous demande de personnaliser les paramètres

### Configuration manuelle (optionnelle)
```bash
cp .env.example .env
# Puis modifiez .env selon vos besoins
```

### Variables importantes
```env
# Base de données (utilisées par database.php)
DB_HOST=db
DB_NAME=techsuivi_db
DB_USER=techsuivi_user
DB_PASS=votre_mot_de_passe

# Configuration Docker
MYSQL_ROOT_PASSWORD=votre_mot_de_passe_root
MYSQL_DATABASE=techsuivi_db
MYSQL_USER=techsuivi_user
MYSQL_PASSWORD=votre_mot_de_passe

# FTP (pour AutoIT)
FTP_USER=ftpuser
FTP_PASS=votre_mot_de_passe_ftp
```

### Ports utilisés par defaut
- **8080** - Application web
- **8081** - PhpMyAdmin
- **21** - Serveur FTP

---

## 📁 Structure du projet

```
TechSuivi/
├── 📄 README.md              # Documentation principale
├── 🐳 docker-compose.yml     # Configuration Docker
├── ⚙️ setup.sh               # Script d'installation
├── 📋 .env.example           # Template de configuration
├── 
├── 🌐 web/                   # Application web PHP
│   ├── src/                  # Code source
│   ├── Dockerfile            # Image Docker personnalisée
│   └── ...
├── 
├── 🗄️ db/                    # Base de données
│   ├── init_complete.sql     # Structure initiale
│   └── *.sql                 # Scripts de migration
├── 
├── 🤖 _Autoit/               # Scripts AutoIT
│   └── script/TechSuivi V4/  # Scripts principaux
├── 
└── 🧪 _tests/                # Tests et migrations
    ├── security_*.php        # Tests de sécurité
    └── test_*.php            # Tests fonctionnels
```

---

## 🌐 Accès à l'application

### Interfaces web
| Service | URL | Description |
|---------|-----|-------------|
| **Application principale** | http://localhost:8080 | Interface de gestion |
| **PhpMyAdmin** | http://localhost:8081 | Administration base de données |
| **FTP** | localhost:21 | Échange de fichiers AutoIT |

### Identifiants par défaut
```
👤 Utilisateur : admin
🔑 Mot de passe : admin123
```

> ⚠️ **Important** : Changez ces identifiants après la première connexion !

---

## 📚 Documentation

### API et intégrations
- Documentation API : `web/src/api/`
- Scripts AutoIT : `_Autoit/script/TechSuivi V4/`

---

## 🛠️ Développement

### Commandes utiles
```bash
# Démarrer les services
docker compose up -d

# Voir les logs
docker compose logs -f

# Redémarrer un service
docker compose restart web

# Arrêter tous les services
docker compose down

# Supprimer les données (⚠️ ATTENTION!)
docker compose down -v
```

### Tests
```bash
# Exécuter les tests de sécurité
docker compose exec web php _tests/security_audit.php

# Test de connexion API
docker compose exec web php _tests/test_api_interventions.php
```

---

## 🤝 Contribution

### Comment contribuer
1. **Fork** le projet
2. Créez une **branche** pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. **Committez** vos changements (`git commit -m 'Add some AmazingFeature'`)
4. **Poussez** vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une **Pull Request**

### Standards de code
- Code en **français** (commentaires et variables)
- Respect des standards **PSR-12** pour PHP
- Tests obligatoires pour les nouvelles fonctionnalités

---

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [`LICENSE`](LICENSE) pour plus de détails.

---

## 📞 Support

- 🐛 **Bugs** : Ouvrez une [issue](https://github.com/VOTRE_USERNAME/TechSuivi/issues)
- 💡 **Suggestions** : Utilisez les [discussions](https://github.com/VOTRE_USERNAME/TechSuivi/discussions)
- 📧 **Contact** : [votre.email@example.com](mailto:votre.email@example.com)

---

<div align="center">

**⭐ Si ce projet vous aide, n'hésitez pas à lui donner une étoile ! ⭐**

Made with ❤️ by [Votre Nom](https://github.com/VOTRE_USERNAME)

</div>
