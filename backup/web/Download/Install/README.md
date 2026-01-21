# Dossier d'installation TechSuivi

Ce dossier contient les fichiers qui seront téléchargés par l'installeur automatique.

## Configuration Apache

Le fichier `.htaccess` active le directory listing pour permettre à l'installeur de scanner automatiquement tous les fichiers.

## Comment utiliser ce dossier

1. **Placez vos fichiers TechSuivi ici** avec la structure de votre choix :
   ```
   Install/
   ├── auto.exe
   ├── config.ini
   ├── includes/
   │   └── *.au3
   └── images/
       └── *.png
   ```

2. **Vérifiez l'accès** dans un navigateur :
   ```
   http://192.168.10.248/Download/Install/
   ```
   
   Vous devriez voir une liste de fichiers. Si vous voyez "Forbidden", vérifiez :
   - Le fichier `.htaccess` est bien présent
   - Apache autorise `.htaccess` (AllowOverride All dans la config)
   - Les permissions du dossier permettent la lecture

3. **Lancez l'installeur** avec cette URL :
   ```
   http://192.168.10.248/Download/Install/
   ```

## Dépannage

### Erreur "Forbidden"

**Vérification 1** : Permissions du dossier
```bash
chmod 755 /TechSuivi/web/Download/Install
```

**Vérification 2** : Apache autorise .htaccess

Éditez `/etc/apache2/sites-available/000-default.conf` (ou votre vhost) :
```apache
<Directory /var/www/html/Download>
    AllowOverride All
    Require all granted
</Directory>
```

Puis redémarrez Apache :
```bash
sudo systemctl restart apache2
```

**Vérification 3** : Le module mod_autoindex est activé
```bash
sudo a2enmod autoindex
sudo systemctl restart apache2
```

### Tester manuellement

Accédez à : http://192.168.10.248/Download/Install/

✅ **Bon** : Vous voyez une liste de fichiers
❌ **Problème** : Vous voyez "Forbidden" ou une page blanche

## Structure recommandée

Copiez directement votre dossier TechSuivi compilé :
```bash
cp -r /chemin/vers/TechSuivi/compile/* /TechSuivi/web/Download/Install/
```

Ou uploadez via FTP/SFTP en conservant la structure de dossiers.
