# 🚀 Guide d'Installation TechSuivi

## 📋 Options d'Installation

TechSuivi propose maintenant **deux méthodes d'installation** pour s'adapter à tous les besoins :

### 🤖 Installation Automatique (Recommandée)
**Script :** `./setup_auto.sh`
- ✅ **Aucune interaction requise**
- ✅ **Configuration par défaut optimisée**
- ✅ **Installation rapide et fiable**
- ✅ **Idéale pour les serveurs et déploiements**

### 💬 Installation Interactive
**Script :** `./setup_interactive.sh`
- ⚙️ **Configuration personnalisée**
- 🔧 **Choix des mots de passe**
- 📝 **Prompts pour chaque paramètre**
- ⚠️ **Peut se bloquer sur certains environnements**

---

## 🎯 Installation Recommandée

### Étape 1 : Cloner le projet
```bash
git clone https://github.com/votre-username/TechSuivi.git
cd TechSuivi
```

### Étape 2 : Lancer l'installation automatique
```bash
./setup_auto.sh
```

### Étape 3 : Accéder à l'application
- 🌐 **Application web :** http://localhost:8080
- 🗄️ **PhpMyAdmin :** http://localhost:8081

---

## 🔧 Configuration Automatique

Le script [`setup_auto.sh`](setup_auto.sh:1) configure automatiquement :

### 📊 Base de données
- **Hôte :** `db`
- **Base :** `techsuivi_db`
- **Utilisateur :** `techsuivi_user`
- **Mot de passe :** `techsuivi_pass_2025` (année courante)

### 📁 Fichiers créés
- `.env` - Configuration Docker
- `web/src/.env` - Configuration application web

### 🐳 Conteneurs Docker
- **Web :** Apache + PHP 8.1
- **Base :** MariaDB 11
- **PhpMyAdmin :** Interface de gestion

---

## 🛠️ Dépannage

### Si l'installation échoue :

1. **Vérifier Docker :**
   ```bash
   docker --version
   docker-compose --version
   ```

2. **Nettoyer les conteneurs :**
   ```bash
   docker-compose down --remove-orphans
   docker system prune -f
   ```

3. **Relancer l'installation :**
   ```bash
   ./setup_auto.sh
   ```

4. **Diagnostic avancé :**
   ```bash
   ./debug_installation.sh
   ```

### Si setup_interactive.sh se bloque :

1. **Arrêter le processus :** `Ctrl+C`
2. **Utiliser l'installation automatique :**
   ```bash
   ./setup_auto.sh
   ```

---

## 📚 Scripts Disponibles

| Script | Description | Usage |
|--------|-------------|-------|
| `setup_auto.sh` | Installation automatique | `./setup_auto.sh` |
| `setup_interactive.sh` | Installation avec prompts | `./setup_interactive.sh` |
| `debug_installation.sh` | Diagnostic des problèmes | `./debug_installation.sh` |
| `fix_database_advanced.sh` | Réparation avancée | `./fix_database_advanced.sh` |

---

## 🎉 Après l'Installation

### Première connexion
1. Accédez à http://localhost:8080
2. Utilisez les identifiants par défaut ou créez un compte
3. Configurez votre profil

### Commandes utiles
```bash
# Voir les logs
docker-compose logs

# Redémarrer les services
docker-compose restart

# Arrêter l'application
docker-compose down

# Sauvegarder la base de données
docker-compose exec db mysqldump -u root -p techsuivi_db > backup.sql
```

---

## 🔒 Sécurité

### En production, pensez à :
- Changer les mots de passe par défaut
- Configurer HTTPS
- Limiter l'accès à PhpMyAdmin
- Effectuer des sauvegardes régulières

---

## 📞 Support

En cas de problème :
1. Consultez les logs : `docker-compose logs`
2. Utilisez le diagnostic : `./debug_installation.sh`
3. Vérifiez la documentation dans les fichiers `README_*.md`
4. Créez une issue sur GitHub avec les détails de l'erreur

---

**✅ Installation automatique = Solution recommandée pour éviter les blocages !**