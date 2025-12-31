# 🚀 TechSuivi

******Beaucoup de chose sont généré par l'IA, je n'est pas forcement tout verifier encore, la config via docker fonctionne correctement sur mon NAS******

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

### Option 1: Installation rapide 
```bash
git clone https://github.com/TechSuivi/TechSuivi.git
cd TechSuivi
./install_auto.sh
```

### Option 2: Installation personnalisée (Non testé)
```bash
git clone https://github.com/TechSuivi/TechSuivi.git
cd TechSuivi
./install_interactive.sh
```


### Option 3: Docker (Recommandée)
```yaml
version: '3'

services:
  web:
    image: techsuivi/web:latest
    container_name: ts_web
    ports:
      - "80:80"
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=techsuivi_db
      - DB_USER=techsuivi_user
      - DB_PASS=votre_password_ici
      - APP_URL=http://192.168.10.100
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      # Dossiers LIÉS au NAS (Il faut créer les dossiers et/ou les changer)
      - /share/Container/TechSuivi/uploads:/var/www/html/uploads
      - /share/Container/TechSuivi/vnc_tokens:/var/www/html/vnc_tokens
    restart: always

  db:
    image: techsuivi/db:latest
    container_name: ts_db
    restart: always
    environment:
      - MARIADB_ROOT_PASSWORD=votre_root_password_ici
      - MARIADB_DATABASE=techsuivi_db
      - MARIADB_USER=techsuivi_user
      - MARIADB_PASSWORD=votre_password_ici
    volumes:
      - ts_db_data:/var/lib/mysql

  novnc:
    image: techsuivi/novnc:latest
    container_name: ts_novnc
    restart: unless-stopped
    network_mode: host
    volumes:
      - /share/Container/TechSuivi/vnc_tokens:/tokens

# Déclaration du volume de base de données
volumes:
  ts_db_data:
```


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

### Ports utilisés par defaut
- **8080** - Application web
- **8081** - PhpMyAdmin

---

## 📁 Structure du projet

```
TechSuivi/
├── 📄 README.md              # Documentation principale
├── 🐳 docker-compose.yml     # Configuration Docker
├── ⚙️ install_auto.sh      # Script d'installation
├── 📄 .env                 # Fichier de configuration
├── 
├── 🌐 web/                 # Application web PHP
│   ├── src/                # Code source
│   ├── Dockerfile          # Image Docker personnalisée
│   └── ...
├── 
├── 🗄️ db/                  # Base de données
│   └── techsuivi_db.sql    # Structure initiale
├── 
└──  🤖 _Autoit/               # Scripts AutoIT
   └── script/TechSuivi V4/  # Scripts principaux

```

---



## 📚 Documentation

### API et intégrations
- Documentation API : `web/src/api/`
- Scripts AutoIT : `_Autoit/script/TechSuivi V4/`


---

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [`LICENSE`](LICENSE) pour plus de détails.

---

## 📞 Support

- 🐛 **Bugs** : Ouvrez une [issue](https://github.com/TechSuivi/TechSuivi/issues)
- 💡 **Suggestions** : Utilisez les [discussions](https://github.com/TechSuivi/TechSuivi/discussions)

---

<div align="center">

**⭐ Si ce projet vous aide, n'hésitez pas à lui donner une étoile ! ⭐**

Made with ❤️ by [TechSuivi team](https://github.com/TechSuivi - www.techsuivi.fr)

</div>
