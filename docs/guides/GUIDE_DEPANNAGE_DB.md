# Guide de dépannage - Problème de base de données

## 🔍 Diagnostic du problème

Vous avez rencontré cette erreur :
```
❌ Impossible de se connecter à la base de données après 30 tentatives
```

## 📋 Étapes de diagnostic à effectuer sur votre serveur

### 1. Vérifier les conteneurs Docker

```bash
# Voir l'état des conteneurs
docker compose ps

# Voir les logs de la base de données
docker compose logs db

# Voir les logs en temps réel
docker compose logs -f db
```

### 2. Vérifier le fichier .env

```bash
# Afficher le contenu du .env
cat .env

# Vérifier que web/src/.env existe aussi
cat web/src/.env
```

### 3. Tester la connexion à la base de données

```bash
# Récupérer le mot de passe root depuis le .env
ROOT_PASS=$(grep "MYSQL_ROOT_PASSWORD" .env | cut -d'=' -f2)
DB_NAME=$(grep "MYSQL_DATABASE" .env | cut -d'=' -f2)

echo "Mot de passe root : $ROOT_PASS"
echo "Base de données : $DB_NAME"

# Test de connexion
docker compose exec db mysql -u root -p"$ROOT_PASS" -e "SELECT 1;"
```

### 4. Vérifier l'espace disque

```bash
# Vérifier l'espace disque disponible
df -h

# Vérifier l'espace utilisé par Docker
docker system df
```

## 🔧 Solutions possibles

### Solution 1 : Redémarrage complet

```bash
# Arrêter tous les conteneurs et supprimer les volumes
docker compose down -v

# Nettoyer les images et volumes orphelins
docker system prune -f

# Relancer l'installation
./setup.sh
```

### Solution 2 : Problème de mot de passe

Si la base de données ne démarre pas à cause du mot de passe, modifiez le `.env` :

```bash
# Éditer le .env avec des mots de passe plus simples
nano .env
```

Remplacez par des mots de passe sans caractères spéciaux :
```env
MYSQL_ROOT_PASSWORD=rootpass123
MYSQL_PASSWORD=userpass123
```

### Solution 3 : Problème de dump SQL

```bash
# Vérifier que le dump existe
ls -la db/techsuivi_db.sql

# Si le fichier est corrompu, le retélécharger depuis GitHub
wget https://raw.githubusercontent.com/VOTRE_USER/TechSuivi/main/db/techsuivi_db.sql -O db/techsuivi_db.sql
```

### Solution 4 : Import manuel de la base

```bash
# Démarrer seulement la base de données
docker compose up -d db

# Attendre qu'elle soit prête
sleep 30

# Importer manuellement le dump
docker compose exec -T db mysql -u root -p"$ROOT_PASS" techsuivi_db < db/techsuivi_db.sql

# Vérifier l'import
docker compose exec db mysql -u root -p"$ROOT_PASS" techsuivi_db -e "SHOW TABLES;"
```

### Solution 5 : Utiliser le script de diagnostic

```bash
# Rendre le script exécutable
chmod +x debug_installation.sh

# Lancer le diagnostic
./debug_installation.sh
```

## 🚨 Problèmes courants

### 1. Espace disque insuffisant
- **Symptôme** : Conteneurs qui s'arrêtent, erreurs d'écriture
- **Solution** : Libérer de l'espace avec `docker system prune -a`

### 2. Port déjà utilisé
- **Symptôme** : Erreur "port already in use"
- **Solution** : Changer les ports dans `docker-compose.yml`

### 3. Fichier dump corrompu
- **Symptôme** : Erreurs SQL lors de l'import
- **Solution** : Retélécharger le fichier depuis GitHub

### 4. Problème de permissions
- **Symptôme** : Erreurs d'accès aux fichiers
- **Solution** : `sudo chown -R $USER:$USER .`

## 📞 Commandes de diagnostic rapide

Copiez-collez ces commandes pour un diagnostic complet :

```bash
echo "=== DIAGNOSTIC TECHSUIVI ==="
echo "1. État des conteneurs :"
docker compose ps
echo ""
echo "2. Contenu du .env :"
cat .env
echo ""
echo "3. Logs de la base de données (20 dernières lignes) :"
docker compose logs --tail=20 db
echo ""
echo "4. Espace disque :"
df -h
echo ""
echo "5. Test de connexion DB :"
ROOT_PASS=$(grep "MYSQL_ROOT_PASSWORD" .env | cut -d'=' -f2)
docker compose exec -T db mysql -u root -p"$ROOT_PASS" -e "SELECT 1;" 2>/dev/null && echo "✅ Connexion OK" || echo "❌ Connexion échouée"
```

## 📧 Informations à fournir

Si le problème persiste, fournissez ces informations :

1. Sortie de `docker compose ps`
2. Sortie de `docker compose logs db`
3. Contenu du fichier `.env`
4. Sortie de `df -h`
5. Version de Docker : `docker --version`