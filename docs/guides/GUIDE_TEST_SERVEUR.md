# 🧪 Guide de Test - TechSuivi sur Serveur

## 📋 Étapes de Test Complètes

### 1️⃣ **Cloner le Repository sur votre Serveur**

```bash
# Se connecter à votre serveur
ssh votre_utilisateur@votre_serveur

# Cloner le repository GitHub
git clone https://github.com/votre_username/TechSuivi.git
cd TechSuivi

# Vérifier que tous les fichiers sont présents
ls -la
```

### 2️⃣ **Installation Automatique (Recommandée)**

```bash
# Rendre le script exécutable
chmod +x setup_interactive.sh

# Lancer l'installation interactive
./setup_interactive.sh
```

**Le script va vous demander :**
- Nom de la base de données (par défaut: `techsuivi_db`)
- Utilisateur MySQL (par défaut: `techsuivi_user`)
- Mot de passe MySQL (généré automatiquement ou personnalisé)
- Port web (par défaut: `8080`)

### 3️⃣ **Vérification de l'Installation**

```bash
# Vérifier que Docker fonctionne
docker-compose ps

# Vous devriez voir :
# - techsuivi-web (running)
# - techsuivi-db (running)
```

### 4️⃣ **Test de Connexion Web**

Ouvrez votre navigateur et allez à :
```
http://votre_serveur:8080
```

**Vous devriez voir :**
- ✅ La page de connexion TechSuivi
- ✅ Pas d'erreur "Fichier .env introuvable!"
- ✅ Pas d'erreur de connexion base de données

### 5️⃣ **Test de Connexion (Utilisateur par défaut)**

```
Utilisateur : admin
Mot de passe : admin123
```

## 🔧 En cas de Problème

### **Problème 1 : Erreur de Base de Données**

```bash
# Lancer le diagnostic automatique amélioré
chmod +x debug_installation.sh
./debug_installation.sh
```

### **Problème 2 : Réparation Automatique**

```bash
# Réparation standard
chmod +x fix_database.sh
./fix_database.sh

# OU réparation avancée (recommandée)
chmod +x fix_database_advanced.sh
./fix_database_advanced.sh
```

### **Problème 3 : Vérification Manuelle**

```bash
# Vérifier le fichier .env
cat web/src/.env

# Vérifier les logs Docker
docker-compose logs web
docker-compose logs db
```

## 📊 **Tests de Fonctionnalités**

### Test 1 : Connexion
- [ ] Page de connexion s'affiche
- [ ] Connexion avec admin/admin123 fonctionne
- [ ] Redirection vers le dashboard

### Test 2 : Base de Données
- [ ] Pas d'erreur "Fichier .env introuvable"
- [ ] Connexion à la base de données réussie
- [ ] Tables créées automatiquement

### Test 3 : Interface
- [ ] Dashboard s'affiche correctement
- [ ] Menu de navigation fonctionne
- [ ] Pas d'erreurs JavaScript dans la console

## 🚨 **Dépannage Rapide**

### Si l'installation échoue :

```bash
# Nettoyer et recommencer
docker-compose down -v
docker system prune -f
./setup_interactive.sh
```

### Si la base de données ne se connecte pas :

```bash
# 1. Diagnostic complet
./debug_installation.sh

# 2. Réparation avancée (recommandée)
./fix_database_advanced.sh

# 3. Si le problème persiste, réinstallation propre
docker-compose down -v
./setup_interactive.sh
```

### **Erreurs Corrigées dans cette Version :**

- ✅ **"mysql: executable file not found"** - Scripts utilisent maintenant `mariadb`
- ✅ **"netstat: command not found"** - Diagnostic utilise des alternatives
- ✅ **"Access denied for user"** - Création explicite des utilisateurs avec privilèges
- ✅ **Fichier .env introuvable** - Création automatique dans `web/src/.env`

### Si le port 8080 est occupé :

```bash
# Modifier le port dans docker-compose.yml
nano docker-compose.yml
# Changer "8080:80" vers "8081:80" par exemple

# Redémarrer
docker-compose down
docker-compose up -d
```

## ✅ **Validation Finale**

Votre installation est réussie si :

1. ✅ `docker-compose ps` montre 2 conteneurs en cours d'exécution
2. ✅ `http://votre_serveur:8080` affiche la page de connexion
3. ✅ Connexion avec `admin/admin123` fonctionne
4. ✅ Dashboard s'affiche sans erreurs
5. ✅ Aucune erreur dans `docker-compose logs`

## 📞 **Support**

Si vous rencontrez des problèmes :

1. **Consultez** [`GUIDE_DEPANNAGE_DB.md`](GUIDE_DEPANNAGE_DB.md)
2. **Lisez** [`SOLUTION_ENV_FINALE.md`](SOLUTION_ENV_FINALE.md)
3. **Exécutez** `./debug_installation.sh` pour un diagnostic complet

---

🎯 **Objectif :** Une installation TechSuivi fonctionnelle en moins de 5 minutes !