# Installation de l'extension ZIP pour PHP

## 🐧 Ubuntu/Debian
```bash
# Mettre à jour les paquets
sudo apt update

# Installer l'extension ZIP pour PHP
sudo apt install php-zip

# Redémarrer Apache/Nginx
sudo systemctl restart apache2
# OU pour Nginx
sudo systemctl restart nginx
```

## 🎩 CentOS/RHEL/Rocky Linux
```bash
# Installer l'extension ZIP
sudo yum install php-zip
# OU pour les versions récentes
sudo dnf install php-zip

# Redémarrer Apache/Nginx
sudo systemctl restart httpd
# OU pour Nginx
sudo systemctl restart nginx
```

## 🐳 Docker (si vous utilisez Docker)
```bash
# Dans votre Dockerfile, ajoutez :
RUN docker-php-ext-install zip

# OU si vous utilisez un conteneur existant :
docker exec -it votre_conteneur_php bash
apt update && apt install -y libzip-dev
docker-php-ext-install zip
exit
docker restart votre_conteneur_php
```

## 📦 Installation manuelle (si nécessaire)
```bash
# Installer les dépendances
sudo apt install libzip-dev  # Ubuntu/Debian
sudo yum install libzip-devel # CentOS/RHEL

# Compiler l'extension (si PHP compilé manuellement)
cd /path/to/php/source/ext/zip
phpize
./configure
make && sudo make install

# Ajouter dans php.ini
echo "extension=zip.so" | sudo tee -a /etc/php/*/apache2/php.ini
echo "extension=zip.so" | sudo tee -a /etc/php/*/cli/php.ini
```

## ✅ Vérification de l'installation
```bash
# Vérifier que l'extension est chargée
php -m | grep zip

# Ou créer un fichier PHP de test
echo "<?php phpinfo(); ?>" > test_zip.php
# Puis ouvrir dans le navigateur et chercher "zip"
```

## 🔧 Redémarrage des services
```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart nginx
sudo systemctl restart php*-fpm

# Vérifier le statut
sudo systemctl status apache2
sudo systemctl status nginx
sudo systemctl status php*-fpm
```

## 📝 Notes importantes
- L'extension ZIP est généralement incluse dans les installations PHP modernes
- Après installation, un redémarrage du serveur web est obligatoire
- Vérifiez votre version de PHP avec `php -v` pour installer la bonne extension
- Si vous utilisez plusieurs versions de PHP, installez l'extension pour chaque version

## 🚨 Dépannage
Si l'installation échoue :
1. Vérifiez votre version de PHP : `php -v`
2. Vérifiez les extensions disponibles : `apt search php-zip` ou `yum search php-zip`
3. Consultez les logs : `sudo tail -f /var/log/apache2/error.log`