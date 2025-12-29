# 🚀 Guide de Mise à Jour vers TechSuivi v2

## ✅ Corrections apportées

### 1. Base de données v2
- **Problème** : L'ancien fichier `techsuivi_db.sql` était utilisé
- **Solution** : Mise à jour vers `techsuivi_db v2.sql` avec toutes les nouvelles tables

### 2. Permissions des dossiers
- **Problème** : Erreur `mkdir(): Permission denied` lors de la création des dossiers de sauvegarde
- **Solution** : Création automatique des dossiers avec les bonnes permissions dans le Dockerfile

## 🔧 Modifications techniques

### Docker-compose.yml
```yaml
# AVANT
- ./db/techsuivi_db.sql:/docker-entrypoint-initdb.d/techsuivi_db.sql:ro

# APRÈS
- ./db/techsuivi_db v2.sql:/docker-entrypoint-initdb.d/techsuivi_db.sql:ro
```

### Dockerfile
```dockerfile
# Ajout de la création des dossiers avec permissions
RUN mkdir -p /var/www/html/uploads/backups \
 && mkdir -p /var/www/html/uploads/documents \
 && mkdir -p /var/www/html/uploads/temp \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/uploads
```

## 🚀 Instructions de déploiement

### Pour une nouvelle installation :
```bash
# Cloner le repository
git clone https://github.com/TechSuivi/TechSuivi.git
cd TechSuivi

# Copier le fichier d'environnement
cp web/src/.env.example .env

# Éditer les variables d'environnement
nano .env

# Construire et démarrer les conteneurs
docker-compose down
docker-compose build
docker-compose up -d
```

### Pour une mise à jour existante :
```bash
# Arrêter les conteneurs
docker-compose down

# Mettre à jour le code
git pull origin main

# Reconstruire les conteneurs (important pour les nouvelles permissions)
docker-compose build

# Redémarrer
docker-compose up -d
```

## 📋 Vérifications post-installation

### 1. Vérifier l'extension ZIP
```bash
docker exec -it web php -m | grep zip
```

### 2. Vérifier les permissions des dossiers
```bash
docker exec -it web ls -la /var/www/html/uploads/
```

### 3. Tester les fonctionnalités
- ✅ Configuration Acadia : `http://votre-serveur:8080/index.php?page=settings&tab=config`
- ✅ Sauvegarde DB : `http://votre-serveur:8080/index.php?page=database_backup`
- ✅ Gestionnaire fichiers : `http://votre-serveur:8080/index.php?page=files_manager`

## 🆕 Nouvelles fonctionnalités disponibles

### Configuration Acadia
- URL du catalogue configurable
- Clé API modifiable
- Interface utilisateur intuitive

### Système de sauvegarde avancé
- Sauvegarde complète ou partielle
- Formats SQL et ZIP
- Téléchargement direct ou stockage serveur
- Restauration avec logging détaillé

### Gestionnaire de fichiers
- Navigation dans le dossier uploads
- Sauvegarde des fichiers en ZIP
- Gestion des permissions
- Interface responsive

### Système de photos (v2.1)
- Gestion des photos d'intervention
- Redimensionnement automatique avec extension GD
- Paramètres configurables (taille, qualité)
- Dossier interventions créé automatiquement

### Interface améliorée
- Mode sombre optimisé (toutes pages y compris photos_settings)
- Navigation par onglets
- Messages d'erreur détaillés
- CSS harmonisé

### Corrections v2.1
- **Moyens de paiement** : Suppression colonne montant problématique
- **Mode sombre** : Correction affichage status items
- **Extension GD** : Ajout pour traitement d'images

## ⚠️ Notes importantes

1. **Base de données** : La v2.1 inclut toutes les nouvelles tables nécessaires
2. **Permissions** : Les dossiers sont maintenant créés automatiquement avec les bonnes permissions
3. **Extensions PHP** : ZIP et GD installées automatiquement dans le conteneur Docker
4. **Compatibilité** : Rétrocompatible avec les données existantes
5. **Moyens de paiement** : Colonne montant supprimée (source d'erreurs SQL)

## 🐛 Dépannage

### Problème de permissions
```bash
# Corriger manuellement si nécessaire
docker exec -it web chown -R www-data:www-data /var/www/html/uploads
docker exec -it web chmod -R 775 /var/www/html/uploads
```

### Extensions manquantes (ZIP/GD)
```bash
# Reconstruire le conteneur avec toutes les extensions
docker-compose build --no-cache web
docker-compose up -d
```

### Erreur SQL moyens_paiement
```bash
# Appliquer le script de correction si nécessaire
docker-compose exec db mysql -u root -p techsuivi_db < db/remove_montant_column.sql
```

### Mode sombre photos_settings
```bash
# Les corrections CSS sont incluses dans la v2.1
# Reconstruire si problème d'affichage persiste
docker-compose build --no-cache web
```

### Problèmes de permissions persistants
```bash
# Utiliser le script de correction automatique
./fix_permissions.sh

# Ou manuellement pour Docker
docker-compose exec web chown -R www-data:www-data /var/www/html/uploads
docker-compose exec web chmod -R 775 /var/www/html/uploads

# Pour serveur classique
sudo chown -R www-data:www-data web/src/uploads
sudo chmod -R 775 web/src/uploads
```

## 🛠️ Gestion automatique des permissions v2.2

### Installation automatique (recommandé)
Le script [`setup_auto.sh`](setup_auto.sh) **inclut maintenant automatiquement** la correction des permissions :

```bash
./setup_auto.sh
```

**Étape 9 du setup_auto.sh :**
- ✅ Création automatique de tous les dossiers uploads
- ✅ Application des permissions 775
- ✅ Changement de propriétaire (www-data:www-data)
- ✅ Correction via Docker si nécessaire

### Script de correction manuel (si nécessaire)
Un script [`fix_permissions.sh`](fix_permissions.sh) reste disponible pour les corrections manuelles :

```bash
# Rendre le script exécutable
chmod +x fix_permissions.sh

# Exécuter la correction
./fix_permissions.sh
```

**Utilisation recommandée :**
- 🎯 **Nouvelle installation** : Utilisez `./setup_auto.sh` (permissions incluses)
- 🔧 **Problème existant** : Utilisez `./fix_permissions.sh` pour corriger

### Améliorations techniques v2.2

**Utilitaire PHP** [`web/src/utils/permissions_helper.php`](web/src/utils/permissions_helper.php) :
- `createDirectoryWithPermissions()` : Création sécurisée de dossiers
- `ensureUploadDirectories()` : Vérification complète des uploads
- `checkDirectoryPermissions()` : Diagnostic des permissions
- `getPermissionErrorMessage()` : Messages informatifs avec solutions
- `testWritePermissions()` : Test d'écriture sécurisé

**Fichiers PHP corrigés** avec le nouveau système :
- [`web/src/pages/admin/photos_settings.php`](web/src/pages/admin/photos_settings.php) : Gestion photos sécurisée
- [`web/src/actions/files_action.php`](web/src/actions/files_action.php) : Création backups sécurisée
- [`web/src/api/photos.php`](web/src/api/photos.php) : API photos avec permissions
- [`web/src/api/autoit_api.php`](web/src/api/autoit_api.php) : Logs AutoIt sécurisés
- Pages AutoIt (logiciels, nettoyage, personnalisation) : Uploads sécurisés

### Base de données non mise à jour
```bash
# Supprimer le volume et recréer
docker-compose down
docker volume rm techsuivi_db_data
docker-compose up -d
```

---

## 🔄 Système de restauration amélioré v2.3

### Problème résolu : Tables existantes lors de la restauration
- **Problème** : Erreur "Table 'helpdesk_msg' already exists" lors de la restauration
- **Solution** : Système intelligent de gestion des conflits

### Améliorations apportées :
1. **Sauvegarde avec CREATE TABLE IF NOT EXISTS** : Évite les erreurs de création de tables
2. **Vidage automatique des données** : Les données des tables existantes sont automatiquement vidées avant restauration
3. **Gestion intelligente des conflits** :
   - Tables existantes : Structure ignorée, données restaurées
   - Données dupliquées : Gestion automatique des doublons
   - Erreurs SQL : Logging détaillé avec solutions
4. **Logging amélioré** : Messages informatifs sur chaque étape de la restauration

### Fonctionnement du nouveau système :
```
1. 🔍 Analyse du fichier SQL
2. 📋 Identification des tables à restaurer
3. 🧹 Vidage des données des tables existantes
4. ✅ Création des nouvelles tables (si nécessaire)
5. 📝 Restauration des données
6. 📊 Rapport détaillé des opérations
```

### Messages de restauration :
- ✅ **Succès** : "Restauration réussie : X requêtes exécutées"
- ⚠️ **Partiel** : "Restauration partiellement réussie : X requêtes OK, Y erreurs"
- 🧹 **Vidage** : "Données de la table `nom_table` vidées"
- 📝 **Insertion** : "X ligne(s) insérée(s) dans `nom_table`"

---

**Version** : TechSuivi v2.3
**Date** : Novembre 2025
**Compatibilité** : Docker, PHP 8.2, MariaDB 11.7
**Extensions** : ZIP, GD, PDO MySQL
**Nouveautés v2.3** : Système de restauration intelligent, gestion des conflits de tables
**Nouveautés v2.2** : Permissions automatiques dans setup_auto.sh, gestion d'erreurs améliorée