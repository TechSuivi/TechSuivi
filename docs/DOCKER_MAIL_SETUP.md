# Configuration Mail TechSuivi avec Docker

## 🐳 Installation automatique avec Docker

PHPMailer est maintenant **automatiquement installé** lors de la construction de l'image Docker TechSuivi !

## 🚀 Reconstruction de l'image

Pour bénéficier de la fonctionnalité mail, vous devez reconstruire votre image Docker :

### Méthode 1 : Reconstruction complète

```bash
# Arrêter les conteneurs
docker-compose down

# Reconstruire l'image avec PHPMailer
docker-compose build --no-cache

# Redémarrer
docker-compose up -d
```

### Méthode 2 : Reconstruction forcée

```bash
# Supprimer l'ancienne image
docker rmi techsuivi-web

# Reconstruire
docker-compose up -d --build
```

## ✅ Vérification de l'installation

1. **Accédez à la page de configuration mail** :
   ```
   http://192.168.10.248:8080/index.php?page=mail_config
   ```

2. **Vérifiez le statut** :
   - ✅ **"PHPMailer"** = Installation réussie
   - ❌ **"Non installé"** = Reconstruction nécessaire

## 🔧 Modifications Docker apportées

### Dockerfile mis à jour

```dockerfile
# Installation automatique de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installation automatique de PHPMailer
RUN composer install --no-dev --optimize-autoloader
```

### Dépendances ajoutées

- **Composer** : Gestionnaire de dépendances PHP
- **PHPMailer 6.8+** : Bibliothèque d'envoi d'emails
- **Autoloader** : Chargement automatique des classes

## 📦 Contenu de l'image

Après reconstruction, votre image Docker contient :

```
/var/www/html/
├── vendor/
│   └── phpmailer/phpmailer/     # PHPMailer installé
├── composer.json                # Configuration Composer
├── autoload.php                 # Autoloader personnalisé
└── ... (reste du code TechSuivi)
```

## 🎯 Configuration après installation

1. **Base de données** : Exécutez le script SQL
   ```sql
   -- Contenu du fichier install_mail_config.sql
   ```

2. **Configuration SMTP** : Utilisez l'interface web
   - Serveur SMTP (Gmail, Outlook, etc.)
   - Port et chiffrement
   - Authentification

3. **Tests** : Boutons de test intégrés dans l'interface

## 🤖 Automatisation des rapports

### Configuration du cron dans Docker

Ajoutez au `docker-compose.yml` ou créez un service séparé :

```yaml
services:
  web:
    # ... configuration existante
    
  cron:
    build: ./web
    command: >
      sh -c "echo '0 8 * * * /usr/local/bin/php /var/www/html/cron/send_scheduled_reports.php' | crontab - && cron -f"
    volumes:
      - ./web/src:/var/www/html
    depends_on:
      - db
```

### Alternative : Cron externe

```bash
# Sur l'hôte Docker
0 8 * * * docker exec techsuivi-web php /var/www/html/cron/send_scheduled_reports.php
```

## 🔍 Dépannage Docker

### Problème : "PHPMailer non installé"

```bash
# Vérifier si PHPMailer est dans l'image
docker exec -it techsuivi-web ls -la /var/www/html/vendor/phpmailer/

# Reconstruire si nécessaire
docker-compose build --no-cache web
```

### Problème : Erreurs Composer

```bash
# Vérifier les logs de construction
docker-compose build web 2>&1 | grep -i error

# Entrer dans le conteneur pour déboguer
docker exec -it techsuivi-web bash
cd /var/www/html
composer diagnose
```

### Problème : Permissions

```bash
# Vérifier les permissions
docker exec -it techsuivi-web ls -la /var/www/html/vendor/

# Corriger si nécessaire
docker exec -it techsuivi-web chown -R www-data:www-data /var/www/html/vendor/
```

## 📊 Avantages de l'installation Docker

✅ **Installation automatique** : Plus besoin d'intervention manuelle  
✅ **Reproductible** : Même configuration sur tous les environnements  
✅ **Optimisé** : Version production de PHPMailer  
✅ **Sécurisé** : Dépendances vérifiées et isolées  
✅ **Maintenable** : Mises à jour via reconstruction d'image  

## 🚀 Prochaines étapes

1. **Reconstruire l'image** avec les nouvelles modifications
2. **Vérifier l'installation** sur la page de configuration
3. **Configurer SMTP** selon votre fournisseur email
4. **Tester l'envoi** avec les boutons de test
5. **Activer les rapports** automatiques si souhaité

Votre installation TechSuivi Docker est maintenant prête pour l'envoi d'emails professionnel !