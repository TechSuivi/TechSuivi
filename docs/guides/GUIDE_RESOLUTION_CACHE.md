# 🔧 Guide de Résolution du Problème de Cache - TechSuivi v2.5

## 🚨 Problème Identifié

Vous voyez toujours l'ancien message de restauration :
```
✅ Restauration réussie ! 5 requêtes exécutées avec succès.
📋 Détails de la restauration :
✅ Vérifications de clés étrangères désactivées
📋 9 requêtes à exécuter
✅ Vérifications de clés étrangères réactivées
```

**Au lieu des nouveaux messages v2.5 :**
```
📊 X requêtes valides détectées
🔍 Exécution requête TYPE (#N)
📝 Lignes affectées: X
```

## 🔍 Diagnostic

Le fichier `web/src/actions/database_backup.php` contient bien toutes les corrections v2.5, mais votre serveur utilise une version mise en cache.

## 🛠️ Solutions par Ordre de Priorité

### Solution 1 : Script de Mise à Jour Forcée (Recommandé)

```bash
# Rendre le script exécutable
chmod +x force_update_cache.sh

# Exécuter le script
./force_update_cache.sh
```

Ce script va :
- ✅ Vérifier que les corrections v2.5 sont présentes
- 🔄 Redémarrer les containers Docker avec reconstruction
- 🧹 Nettoyer le cache PHP (OPcache)
- 🗑️ Supprimer les sessions PHP
- 🔧 Corriger les permissions
- 🧪 Tester la version

### Solution 2 : Redémarrage Docker Manuel

```bash
# Arrêter les containers
docker-compose down

# Nettoyer le cache Docker
docker system prune -f

# Reconstruire sans cache
docker-compose build --no-cache

# Redémarrer
docker-compose up -d

# Attendre 10 secondes puis tester
```

### Solution 3 : Nettoyage Cache PHP

```bash
# Dans le container Docker
docker-compose exec web php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared\n'; }"

# Redémarrer le container web
docker-compose restart web
```

### Solution 4 : Vérification Manuelle

```bash
# Vérifier que le fichier contient les corrections
grep -n "requêtes valides détectées" web/src/actions/database_backup.php
grep -n "Exécution requête" web/src/actions/database_backup.php
grep -n "Lignes affectées" web/src/actions/database_backup.php

# Si ces commandes ne retournent rien, le fichier n'est pas à jour
```

## 🎯 Test de Validation

Après avoir appliqué une solution :

1. **Connectez-vous à TechSuivi**
2. **Allez dans Paramètres > Sauvegarde de la base de données**
3. **Testez une restauration depuis un fichier**
4. **Vérifiez les nouveaux messages :**
   - `📊 X requêtes valides détectées`
   - `🔍 Exécution requête TYPE (#N)`
   - `📝 Lignes affectées: X`

## 🔧 Dépannage Avancé

### Si le problème persiste après toutes les solutions :

#### 1. Vérification des fichiers
```bash
# Vérifier la taille du fichier (doit être ~833 lignes)
wc -l web/src/actions/database_backup.php

# Vérifier la date de modification
ls -la web/src/actions/database_backup.php

# Vérifier le contenu spécifique
tail -20 web/src/actions/database_backup.php
```

#### 2. Cache navigateur
- Appuyez sur **Ctrl+F5** (ou Cmd+Shift+R sur Mac)
- Ou videz complètement le cache de votre navigateur
- Ou testez en navigation privée

#### 3. Réinstallation complète
```bash
# Sauvegarder vos données
docker-compose exec db mysqldump -u root -p techsuivi > backup_avant_reinstall.sql

# Supprimer complètement
docker-compose down -v
docker system prune -af

# Réinstaller
git pull origin main
./setup_auto.sh
```

## 📊 Vérification de Version

Pour confirmer que vous avez la bonne version :

```bash
# Vérifier le commit Git
git log --oneline -5

# Vous devriez voir :
# - "🔧 Correction finale système de restauration v2.5"
# - "🔧 Correction références backup_direct.php manquant v2.4"
# - etc.
```

## 🆘 Support

Si aucune solution ne fonctionne :

1. **Exécutez le diagnostic :**
   ```bash
   ./force_update_cache.sh
   ```

2. **Copiez la sortie complète du script**

3. **Vérifiez les logs Docker :**
   ```bash
   docker-compose logs web
   ```

4. **Contactez le support avec ces informations**

## ✅ Résultat Attendu

Après correction, vous devriez voir des messages détaillés comme :

```
📊 15 requêtes valides détectées
🔍 Exécution requête CREATE TABLE (#1)
✅ Table `clients` créée avec succès
🔍 Exécution requête INSERT INTO (#2)
📝 5 ligne(s) insérée(s) dans `clients` (Lignes affectées: 5)
🔍 Exécution requête INSERT INTO (#3)
📝 12 ligne(s) insérée(s) dans `interventions` (Lignes affectées: 12)
```

## 🎉 Confirmation de Succès

✅ **Le système de restauration v2.5 fonctionne correctement quand vous voyez :**
- Comptage précis des requêtes valides
- Debug détaillé de chaque requête
- Affichage des lignes affectées
- Messages informatifs pour chaque étape

---

*Guide créé pour TechSuivi v2.5 - Résolution du problème de cache de restauration*