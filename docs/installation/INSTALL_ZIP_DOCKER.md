# 🐳 Installation de l'extension ZIP via Docker

## ✅ Dockerfile modifié
J'ai déjà modifié votre `web/Dockerfile` pour inclure l'extension ZIP.

## 🚀 Commandes à exécuter

### 1. Reconstruire le conteneur web
```bash
# Arrêter les conteneurs
docker-compose down

# Reconstruire le conteneur web avec l'extension ZIP
docker-compose build web

# Redémarrer tous les services
docker-compose up -d
```

### 2. Vérification de l'installation
```bash
# Vérifier que l'extension ZIP est installée
docker exec -it web php -m | grep zip

# Ou créer un fichier de test
docker exec -it web php -r "echo extension_loaded('zip') ? 'ZIP OK' : 'ZIP NOK';"
```

### 3. Alternative : Installation dans un conteneur existant (temporaire)
Si vous ne voulez pas reconstruire, vous pouvez installer temporairement :
```bash
# Entrer dans le conteneur
docker exec -it web bash

# Installer les dépendances
apt-get update
apt-get install -y libzip-dev

# Installer l'extension ZIP
docker-php-ext-install zip

# Redémarrer Apache
service apache2 restart

# Sortir du conteneur
exit
```

⚠️ **Note :** Cette méthode temporaire sera perdue au redémarrage du conteneur.

## 🔍 Vérification finale
Après redémarrage, testez la sauvegarde de fichiers dans l'interface web.

## 📝 Modifications apportées au Dockerfile
```dockerfile
FROM php:8.2-apache

# Installer les dépendances nécessaires pour ZIP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Installer PDO + extension MySQL + extension ZIP
RUN docker-php-ext-install pdo pdo_mysql zip

# Activer mod_rewrite
RUN a2enmod rewrite

# Copier tout le code de web/src/ dans /var/www/html
COPY src/ /var/www/html/

WORKDIR /var/www/html

# Ajuster permissions si nécessaire
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html
```

## 🎯 Commande rapide complète
```bash
docker-compose down && docker-compose build web && docker-compose up -d
```

Une fois terminé, l'extension ZIP sera disponible et les sauvegardes de fichiers fonctionneront parfaitement !